/**
 * Toocheke Companion — Notifications front-end script.
 * Only enqueued on pages containing [toocheke_notify_signup] or
 * [toocheke_notify_manage] (see toocheke_notifications_enqueue_frontend_assets()
 * in class-toocheke-companion-notifications.php).
 */
jQuery(function ($) {
	'use strict';

	/**
	 * Mirrors the color palette in
	 * toocheke_notifications_message_box() in
	 * class-toocheke-companion-notifications.php, so AJAX results look
	 * identical to the PHP-rendered confirm/unsubscribe/manage messages.
	 * Plain colored text was found during testing to be illegible
	 * against dark page/sidebar backgrounds — a tinted box with bold
	 * text stays readable everywhere.
	 */
	var TOOCHEKE_NOTIFY_PALETTE = {
		success: { background: '#eaf7ec', color: '#1e7e34' },
		error:   { background: '#fdecea', color: '#b32d2e' },
		warning: { background: '#fff4e5', color: '#8a5300' }
	};

	function toochekeNotifyShowMessage($container, type, message) {
		var colors = TOOCHEKE_NOTIFY_PALETTE[type] || TOOCHEKE_NOTIFY_PALETTE.warning;
		$container.html(
			'<span style="display:block;background:' + colors.background + ';color:' + colors.color +
			';font-weight:bold;padding:12px 16px;border-radius:4px;">' + message + '</span>'
		);
	}

	/* ---------------------------------------------------------------------
	   Turnstile widget for the signup form (only present if the admin has
	   Turnstile enabled — see the PHP template's conditional div).

	   A page can have more than one signup form at once (e.g. one inline
	   in the content and another in a sidebar widget that appears
	   site-wide). Cloudflare Turnstile, however, isn't designed to run
	   multiple simultaneous widget instances for the SAME site key on one
	   page — confirmed in testing: only the first render actually shows a
	   challenge, any additional ones just stay empty. So rather than try
	   to render one per mount point, this renders exactly one widget (on
	   whichever mount point appears first in the DOM), removes any other
	   mount points on the page entirely so they don't sit there as
	   confusing empty gaps, and shares that single resulting token across
	   every signup form on the page — solving the CAPTCHA once covers
	   every form a visitor might submit from that page.
	--------------------------------------------------------------------- */
	var toochekeNotifySharedTurnstileToken = null;
	var toochekeNotifySharedTurnstileWidgetId = null;

	function renderTurnstileWidget() {
		if (typeof turnstile === 'undefined') {
			return;
		}
		var $mounts = $('.toocheke-notify-turnstile-widget');
		if (!$mounts.length) {
			return;
		}

		var $primaryMount = $mounts.first();
		toochekeNotifySharedTurnstileWidgetId = turnstile.render($primaryMount[0], {
			sitekey: $primaryMount.data('sitekey'),
			callback: function (token) {
				toochekeNotifySharedTurnstileToken = token;
			},
			'expired-callback': function () {
				toochekeNotifySharedTurnstileToken = null;
			}
		});

		$mounts.slice(1).remove();
	}

	var $turnstileMounts = $('.toocheke-notify-turnstile-widget');

	if ($turnstileMounts.length) {
		if (typeof turnstile !== 'undefined') {
			renderTurnstileWidget();
		} else {
			var attempts = 0;
			var waitForTurnstile = setInterval(function () {
				attempts++;
				if (typeof turnstile !== 'undefined') {
					clearInterval(waitForTurnstile);
					renderTurnstileWidget();
				} else if (attempts > 40) {
					clearInterval(waitForTurnstile);
				}
			}, 250);
		}
	}

	/* ---------------------------------------------------------------------
	   Signup form
	--------------------------------------------------------------------- */
	$('.toocheke-notify-signup-form').on('submit', function (e) {
		e.preventDefault();

		var $form   = $(this);
		var $button = $form.find('.toocheke-notify-submit');
		var $result = $form.find('.toocheke-notify-result');
		var email   = $form.find('input[name="email"]').val();
		var seriesId = $form.find('input[name="series_id"]').val();
		var honeypot = $form.find('input[name="toocheke_notify_hp"]').val();
		var turnstileToken = toochekeNotifySharedTurnstileToken;

		$result.text('');

		if (!email) {
			toochekeNotifyShowMessage($result, 'error', 'Please enter your email address.');
			return;
		}

		if (toochekeNotifyPublic.turnstileEnabled && !turnstileToken) {
			toochekeNotifyShowMessage($result, 'error', 'Please complete the CAPTCHA above.');
			return;
		}

		$button.prop('disabled', true).text('Subscribing…');

		$.post(toochekeNotifyPublic.ajaxUrl, {
			action: 'toocheke_notify_signup',
			nonce: toochekeNotifyPublic.nonce,
			email: email,
			series_id: seriesId,
			toocheke_notify_hp: honeypot,
			turnstile_token: turnstileToken
		}).done(function (response) {
			if (response && response.success) {
				toochekeNotifyShowMessage($result, 'success', response.data.message);
				$form.find('input[name="email"]').val('');
				if (toochekeNotifySharedTurnstileWidgetId !== null && typeof turnstile !== 'undefined') {
					turnstile.reset(toochekeNotifySharedTurnstileWidgetId);
					toochekeNotifySharedTurnstileToken = null;
				}
			} else {
				var message = (response && response.data && response.data.message) ? response.data.message : 'Something went wrong. Please try again.';
				toochekeNotifyShowMessage($result, 'error', message);
			}
		}).fail(function () {
			toochekeNotifyShowMessage($result, 'error', 'Could not reach the server. Please try again.');
		}).always(function () {
			$button.prop('disabled', false).text('Subscribe');
		});
	});

	/* ---------------------------------------------------------------------
	   Manage-preferences form
	--------------------------------------------------------------------- */
	$('.toocheke-notify-manage-form').on('submit', function (e) {
		e.preventDefault();

		var $form   = $(this);
		var $button = $form.find('.toocheke-notify-manage-submit');
		var $result = $form.find('.toocheke-notify-result');
		var token   = $form.find('input[name="token"]').val();
		var mode    = $form.find('input[name="mode"]:checked').val();
		var seriesIds = $form.find('input[name="series_ids[]"]:checked').map(function () {
			return $(this).val();
		}).get();

		$result.text('');
		$button.prop('disabled', true).text('Saving…');

		$.post(toochekeNotifyPublic.ajaxUrl, {
			action: 'toocheke_notify_update_prefs',
			nonce: toochekeNotifyPublic.nonce,
			token: token,
			mode: mode,
			series_ids: seriesIds
		}).done(function (response) {
			if (response && response.success) {
				toochekeNotifyShowMessage($result, 'success', response.data.message);
			} else {
				var message = (response && response.data && response.data.message) ? response.data.message : 'Something went wrong. Please try again.';
				toochekeNotifyShowMessage($result, 'error', message);
			}
		}).fail(function () {
			toochekeNotifyShowMessage($result, 'error', 'Could not reach the server. Please try again.');
		}).always(function () {
			$button.prop('disabled', false).text('Update Preferences');
		});
	});
});
