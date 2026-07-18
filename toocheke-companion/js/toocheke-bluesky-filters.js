/**
 * Toocheke Companion — Bluesky post-filtering UI behavior.
 * Enqueued only alongside css/toocheke-bluesky-filters.css (see
 * toocheke_bluesky_enqueue_admin_assets() in
 * class-toocheke-companion-bluesky.php).
 */
jQuery(function ($) {
	'use strict';

	// A pill is really just a styled <label> wrapping a checkbox — this
	// just keeps the visual "selected" class in sync with the checkbox's
	// actual checked state, including on load (a checkbox already
	// checked from a saved value needs its pill to start selected too).
	$('.toocheke-pill').each(function () {
		var $pill = $(this);
		$pill.toggleClass('is-selected', $pill.find('input[type="checkbox"]').is(':checked'));
	});

	$(document).on('change', '.toocheke-pill input[type="checkbox"]', function () {
		$(this).closest('.toocheke-pill').toggleClass('is-selected', this.checked);
	});

	// "Post everything" vs "Only post these:" — the group of collapsible
	// taxonomy/post-type pill lists only makes sense to show once
	// "selected" is chosen; no reason to show empty, irrelevant controls
	// otherwise.
	$('.toocheke-bluesky-filter-mode-radio').on('change', function () {
		var $groups = $(this).closest('.toocheke-bluesky-filter').find('.toocheke-bluesky-filter-groups');
		$groups.toggle($(this).val() === 'selected');
	});
});
