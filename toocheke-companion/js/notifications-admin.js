/**
 * Toocheke Companion — Notifications settings tab admin script.
 * Only enqueued on Toocheke > Options > Notifications (see
 * toocheke_notifications_enqueue_admin_assets() in
 * class-toocheke-companion-notifications.php).
 */
jQuery(function ($) {
	'use strict';

	var $siteKeyField   = $('#toocheke-notify-turnstile-site-key');
	var $secretKeyField = $('#toocheke-notify-turnstile-secret-key');
	var $widgetMount     = $('#toocheke-notify-turnstile-widget');
	var $testButton      = $('#toocheke-notify-turnstile-test-button');
	var $result          = $('#toocheke-notify-turnstile-test-result');

	var widgetId = null;
	var currentToken = null;

	// Renders (or re-renders, if the Site Key field changes) the Turnstile
	// widget using the explicit-render API. Auto-render via a data-sitekey
	// attribute would only pick up whatever was in the field at page load,
	// so an admin pasting in a new key wouldn't be able to test it without
	// a full page reload.
	function renderWidget() {
		if (typeof turnstile === 'undefined' || !$widgetMount.length) {
			return;
		}

		var siteKey = $.trim($siteKeyField.val());

		if (widgetId !== null) {
			turnstile.remove(widgetId);
			widgetId = null;
		}
		currentToken = null;
		$testButton.prop('disabled', true).text('Solve the widget above, then test');
		$result.text('');

		if (!siteKey) {
			$widgetMount.html('<em>Enter a Site Key above to load the widget.</em>');
			return;
		}

		$widgetMount.empty();
		widgetId = turnstile.render($widgetMount[0], {
			sitekey: siteKey,
			callback: function (token) {
				currentToken = token;
				$testButton.prop('disabled', false).text('Test Connection');
			},
			'expired-callback': function () {
				currentToken = null;
				$testButton.prop('disabled', true).text('Solve the widget above, then test');
			}
		});
	}

	// Debounce so the widget doesn't try to re-render on every keystroke
	// while the admin is still typing/pasting the key.
	var renderTimeout = null;
	$siteKeyField.on('input', function () {
		clearTimeout(renderTimeout);
		renderTimeout = setTimeout(renderWidget, 500);
	});

	if (typeof turnstile !== 'undefined') {
		renderWidget();
	} else {
		// Cloudflare's api.js loads asynchronously; poll briefly until the
		// global it defines is available rather than assuming load order.
		var attempts = 0;
		var waitForTurnstile = setInterval(function () {
			attempts++;
			if (typeof turnstile !== 'undefined') {
				clearInterval(waitForTurnstile);
				renderWidget();
			} else if (attempts > 40) {
				clearInterval(waitForTurnstile);
				$widgetMount.html('<em>Could not load the Turnstile widget script.</em>');
			}
		}, 250);
	}

	$testButton.on('click', function () {
		var secretKey = $.trim($secretKeyField.val());

		if (!secretKey || !currentToken) {
			$result.html('<span style="color:#b32d2e; font-weight:bold;">Please enter a Secret Key and solve the widget above first.</span>');
			return;
		}

		$testButton.prop('disabled', true).text('Testing…');

		$.post(toochekeNotifications.ajaxUrl, {
			action: 'toocheke_notifications_test_turnstile',
			nonce: toochekeNotifications.nonce,
			secret_key: secretKey,
			token: currentToken
		}).done(function (response) {
			if (response && response.success) {
				$result.html('<span style="color:#00a32a;">' + response.data.message + '</span>');
			} else {
				var message = (response && response.data && response.data.message) ? response.data.message : 'Verification failed.';
				$result.html('<span style="color:#b32d2e; font-weight:bold;">' + message + '</span>');
			}
		}).fail(function () {
			$result.html('<span style="color:#b32d2e; font-weight:bold;">Could not reach the server. Please try again.</span>');
		}).always(function () {
			$testButton.prop('disabled', false).text('Test Connection');
		});
	});
});
