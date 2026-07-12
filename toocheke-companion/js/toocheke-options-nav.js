/**
 * Toocheke Companion — options page nav collapse.
 * Only enqueued on Toocheke > Options.
 *
 * Rather than guessing a fixed pixel breakpoint (which drifts as tabs are
 * added/removed, and depends on the WP admin sidebar's collapsed/expanded
 * state and browser zoom), this measures whether the tab row has actually
 * WRAPPED onto more than one line, and only shows the hamburger toggle
 * when that's genuinely true. Above that point the tab row renders exactly
 * as it always has — nothing here changes it.
 *
 * This deliberately measures height, not width. An earlier version tried
 * forcing white-space: nowrap via CSS and comparing scrollWidth vs.
 * clientWidth, but that depends on that CSS override actually winning —
 * and wp-admin is a shared environment where other active plugins can load
 * CSS this plugin doesn't control, which turned out to be unreliable in
 * practice. Measuring rendered height instead works no matter what CSS is
 * actually in effect: however the browser ends up laying the tabs out,
 * wrapping onto multiple lines makes the row taller, and that's directly
 * measurable regardless of the cause.
 *
 * Note: there is also a small, intentionally-duplicated copy of this same
 * height check as an inline <script> right in the page HTML (see
 * toocheke_display_options_page() in this same directory) — that one runs
 * synchronously the instant the tabs exist in the DOM, before this
 * externally-loaded file has even finished downloading, which is what
 * actually prevents the tabs-then-hamburger flash on page load. This file
 * still owns everything ongoing: the click-to-open toggle, and re-checking
 * on window resize.
 */
jQuery(function ($) {
	'use strict';

	var $wrap    = $('#toocheke-options-wrap');
	var $nav     = $('#toocheke-nav-tab-wrapper');
	var $toggle  = $('#toocheke-nav-toggle');
	var $label   = $('#toocheke-nav-toggle-label');

	if (!$nav.length || !$toggle.length) {
		return;
	}

	// Simple debounce so rapid resize events don't run the measurement
	// dozens of times a second.
	function debounce(fn, wait) {
		var timeout;
		return function () {
			var args = arguments;
			clearTimeout(timeout);
			timeout = setTimeout(function () {
				fn.apply(null, args);
			}, wait);
		};
	}

	function setToggleLabel() {
		var $active = $nav.find('.nav-tab-active').first();
		$label.text($active.length ? $.trim($active.text()) : 'Menu');
	}

	function checkOverflow() {
		// Measure from a clean baseline every time — if .toocheke-nav-open
		// or .toocheke-nav-overflowing were still applied from a prior
		// check, the row could already be hidden or restyled going into
		// this measurement.
		$wrap.removeClass('toocheke-nav-open toocheke-nav-overflowing');

		var $firstTab = $nav.find('.nav-tab').first();
		if (!$firstTab.length) {
			return;
		}

		// A single tab's own height is "one line." If the whole row is
		// meaningfully taller than that, the tabs have wrapped onto more
		// than one line and need to collapse behind the toggle. The 1.5x
		// margin avoids false positives from ordinary sub-pixel/line-height
		// rounding differences between the row and an individual tab.
		var oneLineHeight = $firstTab.outerHeight(true);
		var rowHeight     = $nav.outerHeight(true);
		var isOverflowing = oneLineHeight > 0 && rowHeight > oneLineHeight * 1.5;

		$wrap.toggleClass('toocheke-nav-overflowing', isOverflowing);

		if (!isOverflowing) {
			$toggle.attr('aria-expanded', 'false');
		}
	}

	$toggle.on('click', function () {
		var isOpen = $wrap.toggleClass('toocheke-nav-open').hasClass('toocheke-nav-open');
		$toggle.attr('aria-expanded', isOpen ? 'true' : 'false');
	});

	setToggleLabel();
	checkOverflow();

	$(window).on('resize', debounce(checkOverflow, 150));
});
