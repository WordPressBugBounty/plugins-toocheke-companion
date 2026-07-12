/**
 * Toocheke Companion — Bluesky settings tab admin script.
 * Only enqueued on Toocheke > Options > Bluesky (see
 * toocheke_bluesky_enqueue_admin_assets() in class-toocheke-companion-bluesky.php).
 */
jQuery(function ($) {
	'use strict';

	// Show the message-template row only when "Text + Image" is selected,
	// and the card-caption row only when "Card" is selected — the two
	// formats each have their own, non-overlapping text field. Toggling the
	// whole <tr> (not just the inner div) so the row label doesn't linger
	// visibly with nothing under it.
	function toggleFormatRows() {
		var isCard = $('#toocheke-bluesky-format-card').is(':checked');
		$('#toocheke-bluesky-template-row').closest('tr').toggle(!isCard);
		$('#toocheke-bluesky-card-caption-row').closest('tr').toggle(isCard);
	}
	$('input[name="toocheke-bluesky-post-format"]').on('change', toggleFormatRows);
	toggleFormatRows();

	// Show the random-post frequency row only when at least one of the two
	// random-posting checkboxes (comics / manga chapters) is checked. Same
	// whole-row toggle as above, so the "Post every..." row label is fully
	// hidden rather than left showing above an empty field.
	function toggleFrequencyRow() {
		var comicsOn = $('input[name="toocheke-bluesky-random-comics"]').is(':checked');
		var mangaOn  = $('input[name="toocheke-bluesky-random-manga-chapters"]').is(':checked');
		$('#toocheke-bluesky-frequency-row').closest('tr').toggle(comicsOn || mangaOn);
	}
	$('input[name="toocheke-bluesky-random-comics"], input[name="toocheke-bluesky-random-manga-chapters"]').on('change', toggleFrequencyRow);
	toggleFrequencyRow();

	// Live character counter for the message template. This can only count
	// the literal template text as typed — the real, final length depends on
	// each post's actual title and URL once substituted, which varies per
	// post, so this is a guide rather than an exact preview.
	var $template = $('#toocheke-bluesky-message-template');
	var $counter  = $('#toocheke-bluesky-char-counter');

	function updateCounter() {
		if (!$template.length) {
			return;
		}
		$counter.text($template.val().length + ' / 300 characters (final length varies once a real title and link are inserted)');
	}
	$template.on('input', updateCounter);
	updateCounter();

	// Test Connection button — verifies the handle/app password can
	// authenticate with Bluesky without saving the settings first.
	$('#toocheke-bluesky-test-connection').on('click', function () {
		var $button = $(this);
		var $result = $('#toocheke-bluesky-test-connection-result');
		var handle  = $('#toocheke-bluesky-handle').val();
		var appPassword = $('#toocheke-bluesky-app-password').val();

		$result.text('');

		if (!handle || !appPassword) {
			$result.html('<span style="color:#b32d2e; font-weight:bold;">Please enter both a handle and an app password first.</span>');
			return;
		}

		$button.prop('disabled', true).text('Testing…');

		$.post(toochekeBluesky.ajaxUrl, {
			action: 'toocheke_bluesky_test_connection',
			nonce: toochekeBluesky.nonce,
			handle: handle,
			app_password: appPassword
		}).done(function (response) {
			if (response && response.success) {
				$result.html('<span style="color:#00a32a;">' + response.data.message + '</span>');
			} else {
				var message = (response && response.data && response.data.message) ? response.data.message : 'Connection failed.';
				$result.html('<span style="color:#b32d2e; font-weight:bold;">' + message + '</span>');
			}
		}).fail(function () {
			$result.html('<span style="color:#b32d2e; font-weight:bold;">Could not reach the server. Please try again.</span>');
		}).always(function () {
			$button.prop('disabled', false).text('Test Connection');
		});
	});
});
