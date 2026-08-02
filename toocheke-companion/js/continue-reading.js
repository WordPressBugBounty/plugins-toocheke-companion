/**
 * Toocheke Companion - Continue Reading + read-indicator tracker
 *
 * Records, per series, the last comic (or manga chapter, or a manga volume
 * specifically in reader mode) a visitor read, and separately keeps a flat
 * set of every individual post ID they've read -- both entirely client-side
 * in localStorage, no cookies, no account required. Mirrors the approach
 * already used by js/view-tracker.js.
 *
 * Three jobs, all driven by this one file:
 *   1. On a tracked singular page, record/update this series' "last read"
 *      entry, and mark this specific post as read.
 *   2. On any page, fill in every [data-toocheke-continue-reading] button
 *      with the right link/label once localStorage has been checked. The
 *      button already has a sensible server-rendered fallback (usually
 *      "Start Reading" -> the first comic in the series, or the comic
 *      archive if no series context applies), so this only overrides it
 *      when there's actually a saved reading position to resume.
 *   3. On any page, add a "read" class to any comic list item (identified
 *      by its id="post-{ID}" attribute -- see content-comiclistitem.php)
 *      whose post has already been read.
 */
(function () {
    var CONTINUE_READING_KEY = 'toocheke_continue_reading';
    var READ_POSTS_KEY       = 'toocheke_read_posts';
    var READ_ITEM_CLASS      = 'toocheke-comic-read';

    function readStore(key) {
        try {
            return JSON.parse(window.localStorage.getItem(key)) || {};
        } catch (e) {
            return {};
        }
    }

    function writeStore(key, store) {
        try {
            window.localStorage.setItem(key, JSON.stringify(store));
        } catch (e) {
            // localStorage unavailable (private mode restrictions, quota, etc).
        }
    }

    // --- Job 1: record progress for the page currently being viewed ---
    if (typeof toochekeContinueReading !== 'undefined' && toochekeContinueReading.postId) {
        // Always mark this specific post as read, even if it doesn't
        // belong to a resolvable series (e.g. a standalone comic with no
        // parent series) -- read-tracking shouldn't depend on series
        // membership.
        var readPostsStore = readStore(READ_POSTS_KEY);
        readPostsStore[String(toochekeContinueReading.postId)] = Date.now();
        writeStore(READ_POSTS_KEY, readPostsStore);

        // Only series-scoped comics/chapters get a "continue reading"
        // pointer -- a standalone comic with no series has nowhere for
        // that to point.
        if (toochekeContinueReading.seriesId) {
            var seriesStore = readStore(CONTINUE_READING_KEY);

            seriesStore[String(toochekeContinueReading.seriesId)] = {
                postId: toochekeContinueReading.postId,
                url: toochekeContinueReading.url,
                title: toochekeContinueReading.title,
                seriesTitle: toochekeContinueReading.seriesTitle,
                timestamp: Date.now()
            };

            writeStore(CONTINUE_READING_KEY, seriesStore);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // --- Job 2: fill in any Continue Reading buttons on this page ---
        var buttons = document.querySelectorAll('[data-toocheke-continue-reading]');
        if (buttons.length) {
            var seriesStore = readStore(CONTINUE_READING_KEY);

            buttons.forEach(function (button) {
                var sid   = button.getAttribute('data-sid');
                var entry = null;

                if (sid) {
                    entry = seriesStore[sid] || null;
                } else {
                    // No specific series context -- use whichever series was
                    // read most recently, site-wide.
                    Object.keys(seriesStore).forEach(function (key) {
                        if (! entry || seriesStore[key].timestamp > entry.timestamp) {
                            entry = seriesStore[key];
                        }
                    });
                }

                if (! entry || ! entry.url) {
                    return;
                }

                button.setAttribute('href', entry.url);

                var label = button.querySelector('[data-toocheke-continue-reading-label]');
                var text  = button.getAttribute('data-continue-text') || 'Continue Reading';
                if (label) {
                    label.textContent = text;
                } else {
                    button.textContent = text;
                }
            });
        }

        // --- Job 3: mark already-read comics in any list on this page ---
        var listItems = document.querySelectorAll('li[id^="post-"]');
        if (listItems.length) {
            var readPostsStore = readStore(READ_POSTS_KEY);

            listItems.forEach(function (item) {
                var postId = item.id.slice('post-'.length);
                if (readPostsStore[postId]) {
                    item.classList.add(READ_ITEM_CLASS);
                }
            });
        }
    });
})();
