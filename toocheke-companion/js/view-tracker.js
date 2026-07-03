/**
 * Toocheke Companion - view tracker
 *
 * Records a view for the current comic/manga_chapter post at most once per
 * visitor per 7-day window, without touching cookies. Previously, view
 * de-duplication set a separate cookie per post ID (toocheke_viewed_{ID}),
 * which grew without bound as a visitor read more comics and could
 * eventually push the browser's request headers past what a host allows
 * (causing "431 Request Header Fields Too Large" errors). This version
 * keeps a single small record in localStorage instead, which is never sent
 * to the server, and reports the view via one lightweight async request.
 */
(function () {
    if (typeof toochekeViewTracker === 'undefined') {
        return;
    }

    var STORAGE_KEY = 'toocheke_viewed';
    var postId = String(toochekeViewTracker.postId);
    var windowMs = (toochekeViewTracker.windowDays || 7) * 24 * 60 * 60 * 1000;
    var now = Date.now();

    var viewed = {};
    try {
        viewed = JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || {};
    } catch (e) {
        viewed = {};
    }

    // Prune anything older than the window while we're in here, so this
    // never grows unbounded even over a very long browsing history.
    Object.keys(viewed).forEach(function (id) {
        if (typeof viewed[id] !== 'number' || (now - viewed[id]) > windowMs) {
            delete viewed[id];
        }
    });

    var alreadyViewed = Object.prototype.hasOwnProperty.call(viewed, postId);

    if (!alreadyViewed) {
        var body = new URLSearchParams();
        body.set('action', 'toocheke_record_post_view');
        body.set('nonce', toochekeViewTracker.nonce);
        body.set('post_id', toochekeViewTracker.postId);

        var sent = false;
        if (navigator.sendBeacon) {
            sent = navigator.sendBeacon(toochekeViewTracker.ajaxurl, body);
        }
        if (!sent) {
            fetch(toochekeViewTracker.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).catch(function () {
                // Fail silently -- a missed view count isn't worth surfacing
                // an error to the reader over.
            });
        }

        viewed[postId] = now;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(viewed));
    } catch (e) {
        // localStorage unavailable (private mode restrictions, quota, etc).
        // Nothing further to do -- the view attempt above already fired.
    }
})();
