<?php
/**
 * Builds the Toocheke options page: the admin menu entry, every settings
 * section and field (checkboxes, image upload buttons, color/number/select
 * fields, etc.), and the option defaults set on activation. Also contains
 * the small set of generic field-renderer helpers used by most of the
 * checkbox/upload/preview/URL settings fields, and the descriptive help text
 * shown under groups of related settings.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Settings_Page
{
    /* Get Patreon Level */
    public function toocheke_get_patreon_level_label($patreon_level)
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (is_plugin_active('patreon-connect/patreon.php')) {
            $label         = PATREON_TEXT_EVERYONE;
            $creator_tiers = get_option('patreon-creator-tiers', false);

            if (is_array($creator_tiers['included'])) {

                $tier_count = 1;

                // Flag for determining if the matching tier was found during iteration of tiers
                $matching_level_found = false;

                foreach ($creator_tiers['included'] as $key => $value) {

                    // If its not a reward element, continue, just to make sure

                    if (
                        ! isset($creator_tiers['included'][$key]['type'])
                        or ($creator_tiers['included'][$key]['type'] != 'reward' and $creator_tiers['included'][$key]['type'] != 'tier')
                    ) {
                        continue;
                    }

                    $reward = $creator_tiers['included'][$key];

                    // Special conditions for label for element 0, which is 'everyone' and '1, which is 'patron only'

                    if ($reward['id'] == -1) {
                        $label = PATREON_TEXT_EVERYONE;
                    }
                    if ($reward['id'] == 0) {
                        $label = PATREON_TEXT_ANY_PATRON;
                    }

                    // Use title if exists, and cents amount converted to dollar for any other reward level
                    if ($reward['id'] > 0) {

                        $tier_title = 'Tier ' . $tier_count;

                        $tier_count++;

                        if ($reward['attributes']['title'] != '') {

                            $tier_title = $reward['attributes']['title'];

                            // If the title is too long, snip it
                            if (strlen($tier_title) > 23) {
                                $tier_title = substr($tier_title, 0, 23) . '...';
                            }
                        }

                        $label = $tier_title . ' - $' . ($reward['attributes']['amount_cents'] / 100);
                        if (($reward['attributes']['amount_cents'] / 100) == $patreon_level) {
                            break;
                        }
                    }

                    if (($reward['attributes']['amount_cents'] / 100) >= $patreon_level and ! $matching_level_found) {

                        $matching_level_found = true;

                        // Check if a precise amount is set for this content. If so, add the actual locking amount in parantheses

                        if (($reward['attributes']['amount_cents'] / 100) != $patreon_level) {

                            $label .= ' ($' . $patreon_level . ' exact)';
                        }
                        break;
                    }
                }
            }
            return $label;
        }
    }

            /**
             * Toocheke Options
             */

            public function toocheke_display_options_page()
            {
                $theme = wp_get_theme(); // gets the current theme
    ?>
        <style>
            /**
             * Inline, applied instantly at parse time (no wait on the
             * external toocheke-options-nav.css file to download) --
             * hides each collapsible nav's toggle button and tab row by
             * default via visibility, not display, so their layout/
             * height is still fully measurable while hidden. The
             * synchronous inline <script> further down this same page
             * (see below) adds the "toocheke-nav-ready" class once it
             * has actually decided whether that nav needs to collapse
             * behind the hamburger toggle or not -- only then does the
             * second rule below reveal it again.
             *
             * Deliberately scoped to the nav/toggle elements themselves
             * via a descendant selector, NOT the .toocheke-collapsible-nav-wrap
             * element directly -- on the main Options page, that wrap
             * class sits on the same div as the page's overall .wrap
             * container (#toocheke-options-wrap), which holds the
             * entire page's content (the whole settings form, every
             * field, the Save button). Hiding that element directly
             * would hide the whole page, not just its nav row.
             *
             * Without this, there was a brief but real flash on every
             * page load and every tab click: the browser can begin
             * painting a nav in its default expanded state before the
             * synchronous script actually finishes measuring and
             * toggling its overflow class, even though that script runs
             * about as early as physically possible. Starting hidden and
             * only ever revealing once styled correctly means there's
             * nothing wrong-looking to flash in the first place.
             *
             * This does mean a nav stays invisible if JavaScript is
             * disabled entirely -- an acceptable tradeoff here, since
             * wp-admin already assumes JavaScript is available for
             * virtually everything else it does.
             */
            .toocheke-collapsible-nav-wrap .toocheke-collapsible-nav,
            .toocheke-collapsible-nav-wrap .toocheke-nav-toggle {
                visibility: hidden;
            }
            .toocheke-collapsible-nav-wrap.toocheke-nav-ready .toocheke-collapsible-nav,
            .toocheke-collapsible-nav-wrap.toocheke-nav-ready .toocheke-nav-toggle {
                visibility: visible;
            }
        </style>
        <div class="wrap" id="toocheke-options-wrap">
            <h1>Toocheke Options</h1>
            <?php
                $active_tab         = isset($_GET['tab']) ? $_GET['tab'] : 'comic_display_options';
                $tab_subsections    = $this->toocheke_get_tab_subsections($active_tab);
                $active_subsection  = isset($_GET['subsection']) ? sanitize_key($_GET['subsection']) : '';
                if (! isset($tab_subsections[$active_subsection])) {
                    $keys              = array_keys($tab_subsections);
                    $active_subsection = $keys ? $keys[0] : '';
                }
            ?>

            <div class="toocheke-collapsible-nav-wrap" id="toocheke-main-nav-wrap">
            <button type="button" id="toocheke-nav-toggle" class="toocheke-nav-toggle" aria-expanded="false">
                <span class="dashicons dashicons-menu"></span>
                <span id="toocheke-nav-toggle-label" class="toocheke-nav-toggle-label">Menu</span>
            </button>

            <h2 class="nav-tab-wrapper toocheke-collapsible-nav" id="toocheke-nav-tab-wrapper">
                <a href="?page=toocheke-options-page&tab=comic_display_options"
                class="nav-tab <?php echo $active_tab == 'comic_display_options' ? 'nav-tab-active' : ''; ?>">
                    Display
                </a>

                <a href="?page=toocheke-options-page&tab=ordering_options"
                class="nav-tab <?php echo $active_tab == 'ordering_options' ? 'nav-tab-active' : ''; ?>">
                    Ordering
                </a>

                <a href="?page=toocheke-options-page&tab=comic_archive_options"
                class="nav-tab <?php echo $active_tab == 'comic_archive_options' ? 'nav-tab-active' : ''; ?>">
                    Archive
                </a>

                <a href="?page=toocheke-options-page&tab=navigation_options"
                class="nav-tab <?php echo $active_tab == 'navigation_options' ? 'nav-tab-active' : ''; ?>">
                    Navigation
                </a>

                <a href="?page=toocheke-options-page&tab=social_options"
                class="nav-tab <?php echo $active_tab == 'social_options' ? 'nav-tab-active' : ''; ?>">
                    Social Sharing
                </a>

                <a href="?page=toocheke-options-page&tab=support_options"
                class="nav-tab <?php echo $active_tab == 'support_options' ? 'nav-tab-active' : ''; ?>">
                    Support Links
                </a>

                <a href="?page=toocheke-options-page&tab=analytics_options"
                class="nav-tab <?php echo $active_tab == 'analytics_options' ? 'nav-tab-active' : ''; ?>">
                    Analytics
                </a>

                <a href="?page=toocheke-options-page&tab=top_ten_comics_options"
                class="nav-tab <?php echo $active_tab == 'top_ten_comics_options' ? 'nav-tab-active' : ''; ?>">
                    Top 10
                </a>

                <a href="?page=toocheke-options-page&tab=series_options"
                class="nav-tab <?php echo $active_tab == 'series_options' ? 'nav-tab-active' : ''; ?>">
                    Series
                </a>

                <a href="?page=toocheke-options-page&tab=comic_discussion_options"
                class="nav-tab <?php echo $active_tab == 'comic_discussion_options' ? 'nav-tab-active' : ''; ?>">
                    Discussion
                </a>

                <a href="?page=toocheke-options-page&tab=blog_options"
                class="nav-tab <?php echo $active_tab == 'blog_options' ? 'nav-tab-active' : ''; ?>">
                    Blog
                </a>

                <a href="?page=toocheke-options-page&tab=age_options"
                class="nav-tab <?php echo $active_tab == 'age_options' ? 'nav-tab-active' : ''; ?>">
                    Age
                </a>

                <a href="?page=toocheke-options-page&tab=language_options"
                class="nav-tab <?php echo $active_tab == 'language_options' ? 'nav-tab-active' : ''; ?>">
                    Language
                </a>

                <a href="?page=toocheke-options-page&tab=comic_images_options"
                class="nav-tab <?php echo $active_tab == 'comic_images_options' ? 'nav-tab-active' : ''; ?>">
                    Images
                </a>

                <a href="?page=toocheke-options-page&tab=rss_options"
                class="nav-tab <?php echo $active_tab == 'rss_options' ? 'nav-tab-active' : ''; ?>">
                    RSS
                </a>
                <a href="?page=toocheke-options-page&tab=comicscout_options"
                class="nav-tab <?php echo $active_tab == 'comicscout_options' ? 'nav-tab-active' : ''; ?>">
                    ComicScout
                </a>

                <a href="?page=toocheke-options-page&tab=bluesky_options"
                class="nav-tab <?php echo $active_tab == 'bluesky_options' ? 'nav-tab-active' : ''; ?>">
                    Bluesky
                </a>

                <?php if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme): ?>
                    <a href="?page=toocheke-options-page&tab=buy_options"
                    class="nav-tab <?php echo $active_tab == 'buy_options' ? 'nav-tab-active' : ''; ?>">
                        Buy Comic
                    </a>

                    <a href="?page=toocheke-options-page&tab=sponsor_options"
                    class="nav-tab <?php echo $active_tab == 'sponsor_options' ? 'nav-tab-active' : ''; ?>">
                        Sponsor Comic
                    </a>

                    <a href="?page=toocheke-options-page&tab=notification_options"
                    class="nav-tab <?php echo $active_tab == 'notification_options' ? 'nav-tab-active' : ''; ?>">
                        Notifications
                    </a>
                <?php endif; ?>
            </h2>
            </div>

            <?php if ($tab_subsections): ?>
            <div class="toocheke-collapsible-nav-wrap" id="toocheke-subnav-wrap">
                <button type="button" class="toocheke-nav-toggle" id="toocheke-subnav-toggle" aria-expanded="false">
                    <span class="dashicons dashicons-menu"></span>
                    <span class="toocheke-nav-toggle-label" id="toocheke-subnav-toggle-label">Menu</span>
                </button>
                <h2 class="nav-tab-wrapper toocheke-collapsible-nav toocheke-subnav-tab-wrapper" id="toocheke-subnav-tab-wrapper">
                    <?php foreach ($tab_subsections as $sub_slug => $sub_label) : ?>
                        <a href="?page=toocheke-options-page&tab=<?php echo esc_attr($active_tab); ?>&subsection=<?php echo esc_attr($sub_slug); ?>"
                        class="nav-tab <?php echo $active_subsection === $sub_slug ? 'nav-tab-active' : ''; ?>">
                            <?php echo esc_html($sub_label); ?>
                        </a>
                    <?php endforeach; ?>
                </h2>
            </div>
            <?php endif; ?>

            <script>
            /**
             * Critical, inline, synchronous check — deliberately NOT in the
             * external toocheke-options-nav.js file. That file only runs
             * once fully downloaded and parsed, which on a page also
             * loading several other plugins' scripts can take long enough
             * to be visible as tabs flashing full-width before collapsing.
             * This tiny inline copy runs the instant the browser reaches
             * this point in the HTML — no network wait at all — so the
             * hamburger-vs-tabs decision is already made before the page
             * ever paints, for every collapsible nav instance on the page
             * (the main tab row, and any subnav). The external file still
             * owns click-to-open and re-checking on window resize; this
             * only handles the very first, otherwise-visible flash on
             * initial page load.
             */
            (function () {
                var wraps = document.querySelectorAll('.toocheke-collapsible-nav-wrap');
                for (var i = 0; i < wraps.length; i++) {
                    var wrap = wraps[i];
                    var nav  = wrap.querySelector('.toocheke-collapsible-nav');
                    if (!nav) { continue; }
                    var firstTab = nav.querySelector('.nav-tab');
                    if (firstTab) {
                        var oneLineHeight = firstTab.offsetHeight;
                        var rowHeight = nav.offsetHeight;
                        if (oneLineHeight > 0 && rowHeight > oneLineHeight * 1.5) {
                            wrap.classList.add('toocheke-nav-overflowing');
                        }
                    }
                    // Reveal now that the correct state (collapsed or
                    // not) has already been applied -- see the inline
                    // <style> above for why this starts hidden.
                    wrap.classList.add('toocheke-nav-ready');
                }
            })();
            </script>

            <?php
            // Anti-flash for the Buttons subtab: js/media.js hides every
            // button-upload row (except the checkbox's own row) via a
            // click handler and a document-ready check, both of which
            // only run once jQuery and that external file have loaded --
            // same class of flash as the Bluesky Post Format/Random
            // Archive Posting rows fixed earlier. This applies the
            // CURRENTLY SAVED value of "Use default navigation buttons?"
            // immediately, server-side, so there's nothing to flash in
            // the first place; media.js still fully handles the live,
            // before-saving toggle once the page has loaded.
            if (
                'navigation_options' === $active_tab
                && 'buttons' === $active_subsection
                && get_option('toocheke-comics-navigation', 1)
            ) :
                ?>
                <style>
                    #toocheke-options-wrap .form-table tr:not(:has(#toocheke-comics-navigation)) {
                        display: none;
                    }
                </style>
                <?php
            endif;
            ?>

            <form method="post" action="<?php echo esc_url(add_query_arg(array_filter(['tab' => $active_tab, 'subsection' => $active_subsection]), admin_url('options.php'))); ?>">
                <?php
                // Two groups get displayed here:
                // - 'toocheke-settings': this plugin's own explicit
                //   messages (currently only the Permalinks tab adds any --
                //   see class-toocheke-companion-permalinks.php).
                // - 'general': WordPress core's own generic "Settings
                //   saved." message, which options.php adds automatically
                //   after any successful save on ANY tab, but only when
                //   nothing more specific was already added during that
                //   same request -- so on Permalinks, its own specific
                //   messages simply take the place of this generic one
                //   instead of both showing at once; every other tab,
                //   which has never added anything specific of its own,
                //   gets this generic confirmation instead. Deliberately
                //   NOT an unscoped settings_errors() call, which would
                //   also show any other plugin's own unrelated notices.
                //
                // Both are deduplicated first, defensively -- WordPress's
                // own settings-errors mechanism (a transient merged into
                // the global $wp_settings_errors array) is known to
                // display the same message more than once if
                // get_settings_errors()/settings_errors() ends up invoked
                // more than once in a single page render, and separately,
                // a duplicate form submission (a double-click on Save
                // Changes, or the browser's own "Confirm Form
                // Resubmission" prompt after a refresh) can queue the
                // identical message twice for entirely legitimate reasons.
                // Either way, nobody should ever see the same confirmation
                // or error twice.
                global $wp_settings_errors;
                $toocheke_error_groups  = ['toocheke-settings', 'general'];
                $toocheke_queued_errors = [];
                foreach ($toocheke_error_groups as $toocheke_group) {
                    $toocheke_queued_errors = array_merge($toocheke_queued_errors, get_settings_errors($toocheke_group));
                }
                if (! empty($toocheke_queued_errors)) {
                    $toocheke_deduped_errors    = [];
                    $toocheke_seen_fingerprints = [];
                    foreach ($toocheke_queued_errors as $toocheke_error) {
                        $fingerprint = $toocheke_error['setting'] . '|' . $toocheke_error['code'] . '|' . $toocheke_error['message'];
                        if (! isset($toocheke_seen_fingerprints[$fingerprint])) {
                            $toocheke_seen_fingerprints[$fingerprint] = true;
                            $toocheke_deduped_errors[] = $toocheke_error;
                        }
                    }
                    // Only ever replace these two groups' own entries
                    // within the global array -- any other plugin's own
                    // queued errors (a different 'setting' value) are
                    // left completely untouched.
                    $wp_settings_errors = array_merge(
                        array_filter((array) $wp_settings_errors, function ($error) use ($toocheke_error_groups) {
                            return ! in_array($error['setting'], $toocheke_error_groups, true);
                        }),
                        $toocheke_deduped_errors
                    );
                }
                foreach ($toocheke_error_groups as $toocheke_group) {
                    settings_errors($toocheke_group);
                }

                // Option for display desktop and mobile versions of comic

                do_settings_sections("toocheke-options-page");
                settings_fields("toocheke-settings");

                submit_button();
                ?>
            </form>
        </div>
    <?php
            }

            /**
             * Single source of truth for which tabs have a subnav, and what
             * subsections each one has, in display order. An empty array
             * means "no subnav for this tab" -- both
             * toocheke_display_options_page() (which renders the subnav
             * links) and toocheke_init_option_fields() (which gates which
             * fields actually register) key off this same list, so they
             * can never drift out of sync with each other.
             */
            private function toocheke_get_tab_subsections($tab)
            {
                $subsections = [
                    'navigation_options' => [
                        'manga_reading'         => 'Manga Reading',
                        'comic_navigation'      => 'Comic Navigation',
                        'buttons'               => 'Buttons',
                        'chapter_navigation'    => 'Chapter Navigation',
                        'collection_navigation' => 'Collection Navigation',
                        'permalinks'            => 'Permalinks',
                    ],
                    'bluesky_options' => [
                        'connection'        => 'Connection',
                        'automatic_posting' => 'Automatic Posting',
                        'post_format'       => 'Post Format',
                        'random_posting'    => 'Random Archive Posting',
                    ],
                ];

                return isset($subsections[$tab]) ? $subsections[$tab] : [];
            }

            public function toocheke_init_option_fields()
            {
                // This is hooked to admin_init, which fires on every
                // single wp-admin page load site-wide (Dashboard, Posts,
                // everywhere) -- not just this plugin's own Options page.
                // Without this guard, the entire switch statement below
                // (every tab's add_settings_section/add_settings_field/
                // register_setting calls) ran on every admin request
                // across the whole site, which is pure wasted work
                // everywhere except here. Two cases need to pass through:
                // actually viewing the Options page ($_GET['page']), and
                // the save request itself, which POSTs to options.php
                // directly and has no 'page' query var at all -- WordPress
                // identifies which plugin's settings are being saved via
                // the hidden 'option_page' field settings_fields() prints
                // (see toocheke_display_options_page()), so that's checked
                // here too, or a legitimate save would be silently skipped.
                $is_options_page = (! empty($_GET['page']) && 'toocheke-options-page' === $_GET['page']);
                $is_options_save = (! empty($_POST['option_page']) && 'toocheke-settings' === $_POST['option_page']);
                if (! $is_options_page && ! $is_options_save) {
                    return;
                }

                $active_tab        = isset($_GET['tab']) ? $_GET['tab'] : 'comic_display_options';
                $theme              = wp_get_theme();
                $tab_subsections    = $this->toocheke_get_tab_subsections($active_tab);
                $active_subsection  = isset($_GET['subsection']) ? sanitize_key($_GET['subsection']) : '';
                if (! isset($tab_subsections[$active_subsection])) {
                    $keys              = array_keys($tab_subsections);
                    $active_subsection = $keys ? $keys[0] : '';
                }
                switch ($active_tab) {
                    case 'comic_display_options':
                        //Option for determining whether to show both a desktop and mobile version of the comic
                        add_settings_section("toocheke_comic_devices_layout_section", "Comic Display", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether two image versions of the comic will be displayed depending on device(desktop or mobile).']);
                        add_settings_field("toocheke-comic-layout-devices", "Do you want to have two versions of your comic on each post(one for desktop users one for mobile device users)?", [$this, 'toocheke_options_devices_checkbox'], "toocheke-options-page", "toocheke_comic_devices_layout_section");
                        register_setting("toocheke-settings", "toocheke-comic-layout-devices");
                        break;
                    case 'ordering_options':
                        //Option for setting the order of the comics
                        add_settings_section("toocheke_comics_order_section", "Comics Ordering", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This sets the sorting order for comics.']);
                        add_settings_field("toocheke-comics-order", "How would you like to order your comics?", [$this, 'toocheke_comics_order_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-comics-order");

                        add_settings_field("toocheke-comics-slider-order", "How would you like to order your comics in the slider navigation on the comic page?", [$this, 'toocheke_comics_slider_order_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-comics-slider-order");

                        //Option for setting the which comics is displayed on clicking chapter thumbnail
                        add_settings_field("toocheke-chapter-first-comic", "On clicking a chapter, which comic would you like to navigate to first?", [$this, 'toocheke_chapter_first_comic_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-chapter-first-comic");

                        //Option for setting the which comics is displayed on clicking collection thumbnail
                        add_settings_field("toocheke-collection-first-comic", "On clicking a collection, which comic would you like to navigate to first?", [$this, 'toocheke_collection_first_comic_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-collection-first-comic");

                        //Option for setting the order of the series
                        add_settings_section("toocheke_series_order_section", "Series Ordering", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This sets the sorting order for series listing on the home page.']);
                        add_settings_field("toocheke-series-order", "How would you like to order your series?", [$this, 'toocheke_series_order_radio'], "toocheke-options-page", "toocheke_series_order_section");
                        register_setting("toocheke-settings", "toocheke-series-order");
                        break;
                    case 'comic_archive_options':
                        //Option for setting the layout for the comic archive page
                        add_settings_section("toocheke_comics_archive_section", "Comic Archive Layout", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This sets the layout for the comic archive page. <h4 style="color: #2271b1;">To change the number of items shown per page on the archive page, <a href="' . esc_url(admin_url('options-reading.php')) . '" style="font-weight: bold">click here</a> and update the number for the "Blog pages show at most" field.</h4>']);
                        add_settings_field("toocheke-comics-archive", "Select the layout for the comic archive page.", [$this, 'toocheke_comics_archive_layout_select'], "toocheke-options-page", "toocheke_comics_archive_section");
                        register_setting("toocheke-settings", "toocheke-comics-archive");
                        break;
                    case 'top_ten_comics_options':
                        //Option for setting the layout for the comic archive page
                        add_settings_section("toocheke_top_10_comics_layout_section", "Top 10 Comics Page Layout", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This sets the layout for the top 10 comics page.']);
                        add_settings_field("toocheke-top-10-comics-layout", "Select the layout for the top 10 comic page.", [$this, 'toocheke_top_10_comics_layout_select'], "toocheke-options-page", "toocheke_top_10_comics_layout_section");
                        register_setting("toocheke-settings", "toocheke-top-10-comics-layout");
                        break;
                    case 'navigation_options':
                        if ('manga_reading' === $active_subsection) {
                        //manga option
                        add_settings_section("toocheke_manga_page_navigation_section", "Manga Page Navigation", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Configure the navigation for your manga reader.']);
                        add_settings_field("toocheke-manga-default-pages", "How many pages do you want to display by default?", [$this, 'toocheke_manga_default_pages_radio'], "toocheke-options-page", "toocheke_manga_page_navigation_section");
                        register_setting("toocheke-settings", "toocheke-manga-default-pages");
                        add_settings_field("toocheke-manga-rtl", "Which reading format do you want?", [$this, 'toocheke_manga_rtl_radio'], "toocheke-options-page", "toocheke_manga_page_navigation_section");
                        register_setting("toocheke-settings", "toocheke-manga-rtl");
                        }

                        if ('comic_navigation' === $active_subsection) {
                        $theme = wp_get_theme(); // gets the current theme
                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            //Option for determining whether enable swipe navigation
                            add_settings_section("toocheke_comic_panel_swipe_navigation_section", "Comic Panel Swipe Navigation", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether a panel-by-panel swipe navigation(similar to Instagram) will be added to the comic page.']);
                            add_settings_field("toocheke-comic-panel-swipe-navigation", "Do you want to enable the ability to swipe through the comic, panel-by-panel, similar to Instgram's swipe navigation?", [$this, 'toocheke_comic_panel_swipe_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_panel_swipe_navigation_section");
                            register_setting("toocheke-settings", "toocheke-comic-panel-swipe-navigation");
                        }
                        //navigation buttons settings
                        add_settings_section("toocheke_comic_navigation_options_section", "Comic Navigation", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Customize your comic\'s navigation options. You can upload your own navigation button images to replace the default buttons.']);
                        }
                        // This blank/untitled section is a shared container --
                        // its fields span three different subsections (Comic
                        // Navigation's button-image uploads, Social Icons, and
                        // Support Icons), not just comic_nav_buttons, so it
                        // must be registered regardless of which of those
                        // three is currently active. Registering a section
                        // that ends up with no fields attached (e.g. on a tab
                        // that doesn't need it) is harmless -- do_settings_sections()
                        // simply renders nothing for it.
                        add_settings_section("toocheke_custom_comic_navigation_section", "", "", "toocheke-options-page");
                        // Default-seeding for every navigation button image below --
                        // deliberately left unconditional (not gated by subsection) since
                        // these are one-time defaults for buttons that live across three
                        // different subsections (Comic Navigation, Social Icons, Support
                        // Icons), not just one -- they need to be seeded regardless of
                        // which subsection tab happens to be open on first visit.
                        //initialize  navigation options
                        if (! get_option('toocheke-random-navigation')) {
                            add_option('toocheke-random-navigation', 1);
                        }

                        if (! get_option('toocheke-comics-navigation')) {
                            add_option('toocheke-comics-navigation', 1);
                        }

                        if (! get_option('toocheke-first-button')) {
                            add_option('toocheke-first-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-previous-button')) {
                            add_option('toocheke-previous-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-random-button')) {
                            add_option('toocheke-random-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-next-button')) {
                            add_option('toocheke-next-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-latest-button')) {
                            add_option('toocheke-latest-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-facebook-button')) {
                            add_option('toocheke-facebook-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-twitter-button')) {
                            add_option('toocheke-twitter-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-tumblr-button')) {
                            add_option('toocheke-tumblr-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-reddit-button')) {
                            add_option('toocheke-reddit-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-threads-button')) {
                            add_option('toocheke-threads-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-bluesky-button')) {
                            add_option('toocheke-bluesky-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-copy-button')) {
                            add_option('toocheke-copy-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-buymeacoffee-button')) {
                            add_option('toocheke-buymeacoffee-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-gumroad-button')) {
                            add_option('toocheke-gumroad-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-indiegogo-button')) {
                            add_option('toocheke-indiegogo-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-kickstarter-button')) {
                            add_option('toocheke-kickstarter-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-kofi-button')) {
                            add_option('toocheke-kofi-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-liberapay-button')) {
                            add_option('toocheke-liberapay-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-patreon-button')) {
                            add_option('toocheke-patreon-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-paypal-button')) {
                            add_option('toocheke-paypal-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-substack-button')) {
                            add_option('toocheke-substack-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-tipeee-button')) {
                            add_option('toocheke-tipeee-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if ('comic_navigation' === $active_subsection) {
                        //Option for determining whether to display infinite scroll of comics on the home page.
                        add_settings_field("toocheke-infinite-scroll", "Do you want to display your comic archive as an infinite scroll(no previous/next buttons) on the homepage?", [$this, 'toocheke_infinite_scroll_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-infinite-scroll");

                        //Option for determining whether to display random navigation button
                        add_settings_field("toocheke-random-navigation", "Do you want to display the random button?", [$this, 'toocheke_random_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-random-navigation");

                        //Option for determining whether to display comic archive navigation button
                        add_settings_field("toocheke-comic-archive-navigation", "Do you want to display the archive button?", [$this, 'toocheke_comic_archive_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-comic-archive-navigation");

                        //Option for determining whether to display comic bookmark button
                        add_settings_field("toocheke-comic-bookmark", "Do you want to display a bookmark button?", [$this, 'toocheke_comic_bookmark_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-comic-bookmark");

                        //Option for determining whether to go the next comic on clicking current comic image
                        add_settings_field("toocheke-click-comic-next", "Do you want to go to the next comic on clicking a comic(only works with images in the post)?", [$this, 'toocheke_click_comic_next_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-click-comic-next");

                        //Option for determining whether to display comic navigation above comic
                        add_settings_field("toocheke-comic-nav-above-comic", "Do you want to display comic navigation buttons above the comic(only applies to traditional page layouts)", [$this, 'toocheke_comic_nav_above_comic_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-comic-nav-above-comic");

                        //Option for determining whether to display chapter navigation
                        add_settings_field("toocheke-chapter-navigation-buttons", "Do you want to display chapter navigation buttons?", [$this, 'toocheke_chapter_navigation_buttons_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-chapter-navigation-buttons");

                        //Option for determining whether to display chapter dropdown below comic navigation
                        add_settings_field("toocheke-chapter-dropdown", "Do you want to display a dropdown of the Chapters below the comic navigation?", [$this, 'toocheke_chapter_dropdown_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-chapter-dropdown");

                        //Option for determining whether to disable keyboard comic navigation
                        add_settings_field("toocheke-keyboard", "Do you want to disable keyboard comic navigation?", [$this, 'toocheke_keyboard_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-keyboard");

                        //Option for determining whether to scroll past header
                        add_settings_field("toocheke-scroll-past-header", "Do you want readers to scroll past the header when navigating through the comics?", [$this, 'toocheke_scroll_past_header_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-scroll-past-header");

                        //Option for determining whether to always display comic navigation buttons(first, previous, next, last)
                        add_settings_field("toocheke-always-show-nav-buttons", "Always show all comic navigation buttons, even if there isn’t a first, previous, next, or last comic available?", [$this, 'toocheke_always_show_nav_buttons_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-always-show-nav-buttons");

                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            //Option for determining whether to enable navigation to early access comics
                            add_settings_field("toocheke-early-access", "Do you want to enable navigation to comics scheduled in the future?", [$this, 'toocheke_early_access_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                            register_setting("toocheke-settings", "toocheke-early-access");
                        }

                        //Chapter Navigation section
                        }

                        if ('chapter_navigation' === $active_subsection) {
                        add_settings_section("toocheke_chapter_navigation_section", "Chapter Navigation", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Customize chapter-related navigation options. By default, chapter links point to the first or latest comic in the chapter (depending on your Comic Ordering settings) rather than the chapter archive page.']);
                        add_settings_field("toocheke-chapter-archive-link", "Do you want to link to Chapter archive page?", [$this, 'toocheke_chapter_archive_link_checkbox'], "toocheke-options-page", "toocheke_chapter_navigation_section");
                        register_setting("toocheke-settings", "toocheke-chapter-archive-link");
                        }

                        if ('collection_navigation' === $active_subsection) {
                        //Collection Navigation section
                        add_settings_section("toocheke_collection_navigation_section", "Collection Navigation", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Customize collection-related navigation options. By default, collection links point to the first or latest comic in the collection (depending on your Comic Ordering settings) rather than the collection archive page.']);
                        add_settings_field("toocheke-collection-archive-link", "Do you want to link to Collection archive page?", [$this, 'toocheke_collection_archive_link_checkbox'], "toocheke-options-page", "toocheke_collection_navigation_section");
                        register_setting("toocheke-settings", "toocheke-collection-archive-link");

                        //Option for determining whether to use the default comic navigation buttons
                        }

                        // Custom permalink slugs -- all registration lives in
                        // inc/class-toocheke-companion-permalinks.php so the
                        // feature stays self-contained, same as Bluesky/Notifications.
                        if ('permalinks' === $active_subsection) {
                            $this->toocheke_permalinks_register_settings_fields();
                        }

                        if ('buttons' === $active_subsection) {
                        add_settings_field("toocheke-comics-navigation", "Do you want to use the default navigation buttons?", [$this, 'toocheke_comics_navigation_checkbox'], "toocheke-options-page", "toocheke_custom_comic_navigation_section");
                        register_setting("toocheke-settings", "toocheke-comics-navigation");

                        add_settings_field('toocheke-first-preview', 'Current first button', [$this, 'toocheke_first_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-first-button', 'Replace first button', [$this, 'toocheke_first_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-first-button');
                        add_settings_field('toocheke-previous-preview', 'Current previous button', [$this, 'toocheke_previous_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-previous-button', 'Replace previous button', [$this, 'toocheke_previous_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-previous-button');
                        add_settings_field('toocheke-random-preview', 'Current random button', [$this, 'toocheke_random_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-random-button', 'Replace random button', [$this, 'toocheke_random_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-random-button');
                        add_settings_field('toocheke-comic-archive-preview', 'Current comic archive button', [$this, 'toocheke_comic_archive_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-comic-archive-button', 'Replace comic archive button', [$this, 'toocheke_comic_archive_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-comic-archive-button');
                        add_settings_field('toocheke-next-preview', 'Current next button', [$this, 'toocheke_next_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-next-button', 'Replace next button', [$this, 'toocheke_next_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-next-button');
                        add_settings_field('toocheke-latest-preview', 'Current latest button', [$this, 'toocheke_latest_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-latest-button', 'Replace latest button', [$this, 'toocheke_latest_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-latest-button');
                        add_settings_field('toocheke-next-chapter-preview', 'Current next chapter button', [$this, 'toocheke_next_chapter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-next-chapter-button', 'Replace next chapter button', [$this, 'toocheke_next_chapter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-next-chapter-button');
                        add_settings_field('toocheke-previous-chapter-preview', 'Current previous chapter button', [$this, 'toocheke_previous_chapter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-previous-chapter-button', 'Replace previous chapter button', [$this, 'toocheke_previous_chapter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-previous-chapter-button');
                        add_settings_field('toocheke-facebook-preview', 'Current Facebook button', [$this, 'toocheke_facebook_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-facebook-button', 'Replace Facebook button', [$this, 'toocheke_facebook_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-facebook-button');
                        add_settings_field('toocheke-twitter-preview', 'Current Twitter button', [$this, 'toocheke_twitter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-twitter-button', 'Replace Twitter button', [$this, 'toocheke_twitter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-twitter-button');
                        add_settings_field('toocheke-tumblr-preview', 'Current Tumblr button', [$this, 'toocheke_tumblr_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-tumblr-button', 'Replace Tumblr button', [$this, 'toocheke_tumblr_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-tumblr-button');
                        add_settings_field('toocheke-reddit-preview', 'Current Reddit button', [$this, 'toocheke_reddit_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-reddit-button', 'Replace Reddit button', [$this, 'toocheke_reddit_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-reddit-button');
                        add_settings_field('toocheke-threads-preview', 'Current Threads button', [$this, 'toocheke_threads_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-threads-button', 'Replace Threads button', [$this, 'toocheke_threads_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-threads-button');
                        add_settings_field('toocheke-bluesky-preview', 'Current Bluesky button', [$this, 'toocheke_bluesky_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-bluesky-button', 'Replace Bluesky button', [$this, 'toocheke_bluesky_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-bluesky-button');
                        add_settings_field('toocheke-whatsapp-preview', 'Current WhatsApp button', [$this, 'toocheke_whatsapp_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-whatsapp-button', 'Replace WhatsApp button', [$this, 'toocheke_whatsapp_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-whatsapp-button');
                        add_settings_field('toocheke-linkedin-preview', 'Current LinkedIn button', [$this, 'toocheke_linkedin_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-linkedin-button', 'Replace LinkedIn button', [$this, 'toocheke_linkedin_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-linkedin-button');
                        add_settings_field('toocheke-copy-preview', 'Current copy button', [$this, 'toocheke_copy_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-copy-button', 'Replace copy button', [$this, 'toocheke_copy_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-copy-button');
                        add_settings_field('toocheke-buymeacoffee-preview', 'Current Buy me a coffee button', [$this, 'toocheke_buymeacoffee_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-buymeacoffee-button', 'Replace Buy me a coffee button', [$this, 'toocheke_buymeacoffee_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-buymeacoffee-button');
                        add_settings_field('toocheke-gumroad-preview', 'Current Gumroad button', [$this, 'toocheke_gumroad_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-gumroad-button', 'Replace Gumroad button', [$this, 'toocheke_gumroad_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-gumroad-button');
                        add_settings_field('toocheke-indiegogo-preview', 'Current Indiegogo button', [$this, 'toocheke_indiegogo_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-indiegogo-button', 'Replace Indiegogo button', [$this, 'toocheke_indiegogo_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-indiegogo-button');
                        add_settings_field('toocheke-kickstarter-preview', 'Current Kickstarter button', [$this, 'toocheke_kickstarter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-kickstarter-button', 'Replace Kickstarter button', [$this, 'toocheke_kickstarter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-kickstarter-button');
                        add_settings_field('toocheke-kofi-preview', 'Current Ko-fi button', [$this, 'toocheke_kofi_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-kofi-button', 'Replace Ko-fi button', [$this, 'toocheke_kofi_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-kofi-button');
                        add_settings_field('toocheke-liberapay-preview', 'Current Liberapay button', [$this, 'toocheke_liberapay_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-liberapay-button', 'Replace Liberapay button', [$this, 'toocheke_liberapay_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-liberapay-button');
                        add_settings_field('toocheke-patreon-preview', 'Current Patreon button', [$this, 'toocheke_patreon_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-patreon-button', 'Replace Patreon button', [$this, 'toocheke_patreon_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-patreon-button');
                        add_settings_field('toocheke-paypal-preview', 'Current PayPal button', [$this, 'toocheke_paypal_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-paypal-button', 'Replace PayPal button', [$this, 'toocheke_paypal_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-paypal-button');
                        add_settings_field('toocheke-substack-preview', 'Current Substack button', [$this, 'toocheke_substack_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-substack-button', 'Replace Substack button', [$this, 'toocheke_substack_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-substack-button');
                        add_settings_field('toocheke-tipeee-preview', 'Current Tipeee button', [$this, 'toocheke_tipeee_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-tipeee-button', 'Replace Tipeee button', [$this, 'toocheke_tipeee_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-tipeee-button');
                        }
                        break;
                    case 'social_options':
                        //social share settings
                        add_settings_section("toocheke_social_share_config_section", "Social Sharing Buttons", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This will determine which social sharing buttons will appear for every comic.']);

                        add_settings_field("toocheke-social-share-facebook", "Do you want to display a Facebook share button?", [$this, 'toocheke_social_share_facebook_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-twitter", "Do you want to display a Twitter share button?", [$this, 'toocheke_social_share_twitter_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-tumblr", "Do you want to display a Tumblr share button?", [$this, 'toocheke_social_share_tumblr_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-reddit", "Do you want to display a Reddit share button?", [$this, 'toocheke_social_share_reddit_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-threads", "Do you want to display a Threads share button?", [$this, 'toocheke_social_share_threads_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-bluesky", "Do you want to display a Bluesky share button?", [$this, 'toocheke_social_share_bluesky_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-whatsapp", "Do you want to display a WhatsApp share button?", [$this, 'toocheke_social_share_whatsapp_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-linkedin", "Do you want to display a LinkedIn share button?", [$this, 'toocheke_social_share_linkedin_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-copy", "Do you want to display a copy button?", [$this, 'toocheke_social_share_copy_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");

                        register_setting("toocheke-settings", "toocheke-social-share-facebook");
                        register_setting("toocheke-settings", "toocheke-social-share-twitter");
                        register_setting("toocheke-settings", "toocheke-social-share-tumblr");
                        register_setting("toocheke-settings", "toocheke-social-share-reddit");
                        register_setting("toocheke-settings", "toocheke-social-share-threads");
                        register_setting("toocheke-settings", "toocheke-social-share-bluesky");
                        register_setting("toocheke-settings", "toocheke-social-share-whatsapp");
                        register_setting("toocheke-settings", "toocheke-social-share-linkedin");
                        register_setting("toocheke-settings", "toocheke-social-share-copy");
                        break;
                    case 'support_options':
                        //support settings
                        add_settings_section("toocheke_support_links_config_section", "Support Links", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Enter what you would like displayed for your supporter/patron/donation links.']);

                        add_settings_field("toocheke-support-link-buymeacoffee", "Buy me a coffee", [$this, 'toocheke_support_link_buymeacoffee_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-gumroad", "Gumroad", [$this, 'toocheke_support_link_gumroad_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-indiegogo", "Indiegogo", [$this, 'toocheke_support_link_indiegogo_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-kickstarter", "Kickstarter", [$this, 'toocheke_support_link_kickstarter_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-kofi", "Ko-fi", [$this, 'toocheke_support_link_kofi_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-liberapay", "Liberapay", [$this, 'toocheke_support_link_liberapay_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-patreon", "Patreon", [$this, 'toocheke_support_link_patreon_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-paypal", "PayPal", [$this, 'toocheke_support_link_paypal_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-substack", "Substack", [$this, 'toocheke_support_link_substack_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-tipeee", "Tipeee", [$this, 'toocheke_support_link_tipeee_url'], "toocheke-options-page", "toocheke_support_links_config_section");

                        register_setting("toocheke-settings", "toocheke-support-link-buymeacoffee");
                        register_setting("toocheke-settings", "toocheke-support-link-gumroad");
                        register_setting("toocheke-settings", "toocheke-support-link-indiegogo");
                        register_setting("toocheke-settings", "toocheke-support-link-kickstarter");
                        register_setting("toocheke-settings", "toocheke-support-link-kofi");
                        register_setting("toocheke-settings", "toocheke-support-link-liberapay");
                        register_setting("toocheke-settings", "toocheke-support-link-patreon");
                        register_setting("toocheke-settings", "toocheke-support-link-paypal");
                        register_setting("toocheke-settings", "toocheke-support-link-substack");
                        register_setting("toocheke-settings", "toocheke-support-link-tipeee");
                        break;
                    case 'analytics_options':
                        //Option for determining whether to comic analytics
                        add_settings_section("toocheke_comic_analytics_section", "Comic Analytics", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines which comic analytics your want to display for each comic.']);
                        add_settings_field("toocheke-comic-likes", "Do you want to display comic likes?", [$this, 'toocheke_comic_likes_checkbox'], "toocheke-options-page", "toocheke_comic_analytics_section");
                        register_setting("toocheke-settings", "toocheke-comic-likes");
                        add_settings_field("toocheke-comic-no-of-comments", "Do you want to display number of comments for comic?", [$this, 'toocheke_comic_no_of_comments_checkbox'], "toocheke-options-page", "toocheke_comic_analytics_section");
                        register_setting("toocheke-settings", "toocheke-comic-no-of-comments");
                        add_settings_field("toocheke-comic-no-of-views", "Do you want to display number of views for comic?", [$this, 'toocheke_comic_no_of_views_checkbox'], "toocheke-options-page", "toocheke_comic_analytics_section");
                        register_setting("toocheke-settings", "toocheke-comic-no-of-views");
                        break;
                    case 'series_options':
                        //Option for determining whether to show a single comic series or multiple comic series
                        add_settings_section("toocheke_multiple_series_display_section", "Publish Multiple Comic Series", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This sets whether you will display a single or multiple comic series on your website.']);
                        add_settings_field("toocheke-display-multiple-series", "Do you want to publish more than one comic series?", [$this, 'toocheke_series_publish_options_checkbox'], "toocheke-options-page", "toocheke_multiple_series_display_section");
                        register_setting("toocheke-settings", "toocheke-display-multiple-series");

                        //Option for determining whether to show latest comic listings on a multiple series home page
                        add_settings_field("toocheke-display-latest-comics-of-all-multiple-series", "Do you want to display a list of ALL comics(for multiple series) on the home page?", [$this, 'toocheke_display_latest_comics_of_all_multiple_series_checkbox'], "toocheke-options-page", "toocheke_multiple_series_display_section");
                        register_setting("toocheke-settings", "toocheke-display-latest-comics-of-all-multiple-series");

                        //Option for displaying regular blog posts on series landing page
                        add_settings_section("toocheke_series_landing_blog_section", "Blog Posts on Series Landing Page", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This sets whether you will display blog post listing on the series landing page.']);
                        add_settings_field("toocheke-series-landing-blog", "Do you want to display list of regular blog posts on the series landing page(applies to webtoon layouts)?", [$this, 'toocheke_series_landing_blog_checkbox'], "toocheke-options-page", "toocheke_series_landing_blog_section");
                        register_setting("toocheke-settings", "toocheke-series-landing-blog");

                        //Option for determining which series to display on home page(applies to only traditional layouts.)
                        add_settings_section("toocheke_traditional_home_series", "Series to Display on Home Page", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This sets which series will be displayed on the home page. Please note that this only applies to the traditional layouts for the home page.']);
                        add_settings_field("toocheke-traditional-home-series", "Select the series you would like displayed on the home page", [$this, 'toocheke_traditional_home_series_dropdown'], "toocheke-options-page", "toocheke_traditional_home_series");
                        register_setting("toocheke-settings", "toocheke-traditional-home-series");

                        break;
                    case 'comic_discussion_options':
                        //Option for determining whether to allow discussion on comic posts on the home page
                        add_settings_section("toocheke_comic_discussion_section", "Comic Discussion", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether to allow commenting on comic posts on the home page.']);
                        add_settings_field("toocheke-comic-discussion", "Do you want to allow commenting on comic posts on the home page?", [$this, 'toocheke_comic_discussion_checkbox'], "toocheke-options-page", "toocheke_comic_discussion_section");
                        register_setting("toocheke-settings", "toocheke-comic-discussion");
                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            //Option for determining whether to allow discussion on comic posts on the home page
                            add_settings_section("toocheke_paywalled_discussion_section", "Paywalled Discussion", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether to allow commenting only for paying patrons.']);
                            add_settings_field("toocheke-paywalled-discussion", "Do you want to allow commenting only for patrons?", [$this, 'toocheke_paywalled_discussion_checkbox'], "toocheke-options-page", "toocheke_paywalled_discussion_section");
                            register_setting("toocheke-settings", "toocheke-paywalled-discussion");
                            break;
                        }
                        break;

                    case 'blog_options':
                        //Option for determining whether to show hide blog posts
                        add_settings_section("toocheke_hide_blog_section", "Hide Blogs", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether or not to display the latest blog posts section in the landing/home pages).']);
                        add_settings_field("toocheke-hide-blog", "Do you want to hide blog posts on your comics homepage/landing page?", [$this, 'toocheke_hide_blog_checkbox'], "toocheke-options-page", "toocheke_hide_blog_section");
                        register_setting("toocheke-settings", "toocheke-hide-blog");

                        //Option for determining whether to show blog post on webtoon layout
                        add_settings_section("toocheke_display_blog_on_webtoon", "Display Comic's Blog Post on Webtoon Layouts", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether or not to display the accompanying blog post for a comic in the webtoon layouts.']);
                        add_settings_field("toocheke-dspay-blog-on-webtoon", "Do you want to display the blog post on webtoon layout?", [$this, 'toocheke_display_blog_webtoon_checkbox'], "toocheke-options-page", "toocheke_display_blog_on_webtoon");
                        register_setting("toocheke-settings", "toocheke-dspay-blog-on-webtoon");

                        break;
                    case 'age_options':
                        //Option for determining whether to display popup for mature audiences
                        add_settings_section("toocheke_age_verification_section", "Age Verification", [$this, 'toocheke_age_verification_message'], "toocheke-options-page");
                        add_settings_field("toocheke-age-verification", "Do you want to add a pop-up window to your website and verify the age of the visitor?", [$this, 'toocheke_age_verification_checkbox'], "toocheke-options-page", "toocheke_age_verification_section");
                        register_setting("toocheke-settings", "toocheke-age-verification");
                        break;
                    case 'language_options':
                        //Option for determining whether to display a bilingual language comic
                        add_settings_section("toocheke_bilingual_display_section", "Bilingual Display", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether to display a bilingual version of your comic']);
                        add_settings_field("toocheke-bilingual-display", "Do you want to publish a bilingual comic?", [$this, 'toocheke_bilingual_display_checkbox'], "toocheke-options-page", "toocheke_bilingual_display_section");
                        register_setting("toocheke-settings", "toocheke-bilingual-display");
                        break;
                    case 'comic_images_options':
                        //Option for determining whether to allow the click to enlarge behavior for images
                        add_settings_section("toocheke_image_click_section", "Allow Click to Enlarge for Images", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether you can click an image to enlarge on comic pages']);
                        add_settings_field("toocheke-image-click", "Do you want to enable the click to enlarge feature for comic images?", [$this, 'toocheke_image_click_checkbox'], "toocheke-options-page", "toocheke_image_click_section");
                        register_setting("toocheke-settings", "toocheke-image-click");

                        //Options for optimizing images
                        add_settings_section("toocheke_image_optimization_section", "Optimizaton of Images", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Automatically optimize uploaded images by converting them to AVIF when supported, or WebP as a fallback. This reduces file sizes and saves server disk space while maintaining image quality.']);
                        add_settings_field("toocheke-image-optimization", "Do you want to optimize images?", [$this, 'toocheke_image_optimization_checkbox'], "toocheke-options-page", "toocheke_image_optimization_section");
                        register_setting("toocheke-settings", "toocheke-image-optimization", ['sanitize_callback' => 'absint',]);

                        add_settings_field(
                            "toocheke-avif-quality",
                            "AVIF Image Quality",
                            [$this, 'toocheke_image_quality_number_field'],
                            "toocheke-options-page",
                            "toocheke_image_optimization_section",
                            ['option_name' => 'toocheke-avif-quality', 'default' => 50]
                        );
                        register_setting("toocheke-settings", "toocheke-avif-quality", [
                            'sanitize_callback' => [$this, 'toocheke_sanitize_image_quality'],
                        ]);

                        add_settings_field(
                            "toocheke-webp-quality",
                            "WebP Image Quality",
                            [$this, 'toocheke_image_quality_number_field'],
                            "toocheke-options-page",
                            "toocheke_image_optimization_section",
                            ['option_name' => 'toocheke-webp-quality', 'default' => 75]
                        );
                        register_setting("toocheke-settings", "toocheke-webp-quality", [
                            'sanitize_callback' => [$this, 'toocheke_sanitize_image_quality'],
                        ]);

                        //Option for determining whether to protect images
                        
                        add_settings_section("toocheke_image_protect_section", "Protection of Comic Images", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Helps prevent other websites from displaying your comic images by blocking direct access (hotlink protection).']);
                        add_settings_field("toocheke-image-protection", "Do you want to protect the images in your comic post?", [$this, 'toocheke_image_protection_checkbox'], "toocheke-options-page", "toocheke_image_protect_section");
                        register_setting("toocheke-settings", "toocheke-image-protection");

                        //Option for determining whether to protect images on future scheduled posts
                        add_settings_field("toocheke-future-post-image-protection", "Do you want to protect the images in future, scheduled comic posts?", [$this, 'toocheke_future_image_protection_checkbox'], "toocheke-options-page", "toocheke_image_protect_section");
                        register_setting("toocheke-settings", "toocheke-future-post-image-protection");
                        break;
                        break;
                    
                    case 'rss_options':
                        //Option for determining whether to add comics to main RSS feed
                        add_settings_section("toocheke_comics_to_main_rss_section", "Add comic posts to main feed?", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'This determines whether the comic posts will be added to the main feed: ' . esc_url(get_bloginfo('url') . '/feed')]);
                        add_settings_field("toocheke-comics-to-main-rss", "Do you want to add comic posts to the main feed?", [$this, 'toocheke_comics_to_main_rss_checkbox'], "toocheke-options-page", "toocheke_comics_to_main_rss_section");
                        register_setting("toocheke-settings", "toocheke-comics-to-main-rss");
                        break;
                    case 'comicscout_options':
                        //Option for determining whether to add comics to main RSS feed
                        add_settings_section("toocheke_comicscout_section", "ComicScout", [$this, 'toocheke_comicscout_message'], "toocheke-options-page");
                        add_settings_field(
                            "toocheke-comicscout-global-social-share-image",
                            "Default ComicScout Social Share Image",
                            [$this, 'toocheke_comicscout_global_social_share_image_field'],
                            "toocheke-options-page",
                            "toocheke_comicscout_section"
                        );

                        register_setting(
                            "toocheke-settings",
                            "toocheke-comicscout-global-social-share-image"
                        );
                        break;
                    case 'bluesky_options':
                        // All Bluesky settings registration lives in
                        // inc/class-toocheke-companion-bluesky.php so the
                        // feature stays self-contained; see that file.
                        $this->toocheke_bluesky_register_settings_fields($active_subsection);
                        break;
                    // Email notifications — Premium only, same gate as
                    // 'buy_options' / 'sponsor_options' below. All settings
                    // registration lives in
                    // inc/class-toocheke-companion-notifications.php so the
                    // feature stays self-contained; see that file.
                    case 'notification_options':
                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            $this->toocheke_notifications_register_settings_fields();
                        }
                        break;
                    //Options for sponsoring a comic
                    case 'sponsor_options':
                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            add_settings_section("toocheke_sponsor_comic_info_section", "Sponsor Comic", [$this, 'toocheke_sponsor_comic_display_message'], "toocheke-options-page");
                            //PayPal Fields section
                            add_settings_section("toocheke_sponsor_comic_paypal_settings_section", "PayPal Settings", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => '  Enter the following settings for your PayPal account.']);
                            add_settings_field(
                                'toocheke-paypal-client-id',
                                'Client ID',
                                [$this, 'toocheke_display_input_text'],
                                "toocheke-options-page",
                                'toocheke_sponsor_comic_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-paypal-client-id',
                                    'class'     => 'toocheke-companion',        // for <tr> element
                                    'name'      => 'toocheke-paypal-client-id', // pass any custom parameters
                                ]
                            );

                            add_settings_field(
                                'toocheke-paypal-client-secret',
                                'Client Secret',
                                [$this, 'toocheke_display_input_text'],
                                "toocheke-options-page",
                                'toocheke_sponsor_comic_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-paypal-client-secret',
                                    'class'     => 'toocheke-companion',            // for <tr> element
                                    'name'      => 'toocheke-paypal-client-secret', // pass any custom parameters
                                ]
                            );

                            add_settings_field("toocheke-enable-paypal-sandbox", "Sandbox Mode?", [$this, 'toocheke_enable_paypal_sandbox_checkbox'], "toocheke-options-page", "toocheke_sponsor_comic_paypal_settings_section");

                            register_setting("toocheke-settings", "toocheke-paypal-client-id");
                            register_setting("toocheke-settings", "toocheke-paypal-client-secret");
                            register_setting("toocheke-settings", "toocheke-enable-paypal-sandbox");

                            add_settings_section("toocheke_comic_sponsor_pricing_section", "Pricing", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Enter the cost per day you wish to charge your sponsors.']);
                            //Currency field
                            $currencies = $this->toocheke_get_paypal_currencies();
                            foreach ($currencies as $currency_code => $currency_label) {
                                $currency_list[$currency_code] = sprintf(
                                    '%s (%s)',
                                    $currency_label,
                                    $this->toocheke_get_currency_symbol($currency_code)
                                );
                            }
                            $toocheke_paypal_currency_args = [
                                'option'         => 'toocheke_paypal_currency',
                                'label'          => 'Currency',
                                'desc'           => null,
                                'select_options' => $currency_list,
                            ];
                            add_settings_field("toocheke_paypal_currency", "Currency", [$this, 'toocheke_select_dropdown_generator'], 'toocheke-options-page', "toocheke_comic_sponsor_pricing_section", $toocheke_paypal_currency_args);
                            register_setting("toocheke-settings", "toocheke_paypal_currency", ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
                            //Price field
                            add_settings_field(
                                'toocheke-comic-sponsorship-price',
                                'Price per day',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_comic_sponsor_pricing_section',
                                [
                                    'label_for' => 'toocheke-comic-sponsorship-price',
                                    'class'     => 'toocheke-companion',               // for <tr> element
                                    'name'      => 'toocheke-comic-sponsorship-price', // pass any custom parameters
                                ]
                            );

                            register_setting("toocheke-settings", 'toocheke-comic-sponsorship-price', 'absint');
                        }
                        break;
                    //Options for buying a comic
                    case 'buy_options':

                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            add_settings_section("toocheke_buy_comic_info_section", "Buy Comic", [$this, 'toocheke_buy_comic_display_message'], "toocheke-options-page");
                            add_settings_section("toocheke_buy_comic_options_section", "Options to enable", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Choose which purchase options you would like to enable for all comics.']);
                            add_settings_field("toocheke-global-buy-comic", "Do you want to set the pricing globally", [$this, 'toocheke_global_buy_comic_checkbox'], "toocheke-options-page", "toocheke_buy_comic_options_section");
                            add_settings_field("toocheke-original-art", "Do you wish to offer sales of the original art for each comic?", [$this, 'toocheke_buy_original_checkbox'], "toocheke-options-page", "toocheke_buy_comic_options_section");
                            add_settings_field("toocheke-print", "Do you wish to offer sales of the print for each comic?", [$this, 'toocheke_buy_print_checkbox'], "toocheke-options-page", "toocheke_buy_comic_options_section");
                            register_setting("toocheke-settings", "toocheke-original-art");
                            register_setting("toocheke-settings", "toocheke-print");
                            register_setting("toocheke-settings", "toocheke-global-buy-comic");
                            //PayPal Fields section
                            add_settings_section("toocheke_paypal_settings_section", "PayPal Settings", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => '  Enter the following settings for your PayPal account.']);
                            add_settings_field(
                                'toocheke-paypal-email',
                                'Email address for your PayPal account',
                                [$this, 'toocheke_display_input_email'],
                                "toocheke-options-page",
                                'toocheke_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-paypal-email',
                                    'class'     => 'toocheke-companion',    // for <tr> element
                                    'name'      => 'toocheke-paypal-email', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-comic-title',
                                'The name of your comic',
                                [$this, 'toocheke_display_input_text'],
                                "toocheke-options-page",
                                'toocheke_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-comic-title',
                                    'class'     => 'toocheke-companion',   // for <tr> element
                                    'name'      => 'toocheke-comic-title', // pass any custom parameters
                                ]
                            );

                            register_setting("toocheke-settings", "toocheke-paypal-email");
                            register_setting("toocheke-settings", "toocheke-comic-title");
                            //Original art section
                            register_setting("toocheke-settings", "toocheke-original-description");
                            register_setting("toocheke-settings", 'toocheke-original-us-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-us-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-canada-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-canada-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-international-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-international-shipping', 'absint');
                            add_settings_section("toocheke_original_art_info_section", "Original art information", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Enter the following fields for selling a comic\'s original.']);
                            add_settings_field(
                                'toocheke-original-description',
                                'Description of original art',
                                [$this, 'toocheke_display_input_WYSIWYG'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-description',
                                    'class'     => 'toocheke-companion',            // for <tr> element
                                    'name'      => 'toocheke-original-description', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-us-price',
                                'US Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-us-price',
                                    'class'     => 'toocheke-companion',         // for <tr> element
                                    'name'      => 'toocheke-original-us-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-us-shipping',
                                'US Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-us-shipping',
                                    'class'     => 'toocheke-companion',            // for <tr> element
                                    'name'      => 'toocheke-original-us-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-canada-price',
                                'Canada Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-canada-price',
                                    'class'     => 'toocheke-companion',             // for <tr> element
                                    'name'      => 'toocheke-original-canada-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-canada-shipping',
                                'Canada Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-canada-shipping',
                                    'class'     => 'toocheke-companion',                // for <tr> element
                                    'name'      => 'toocheke-original-canada-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-international-price',
                                'International Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-international-price',
                                    'class'     => 'toocheke-companion',                    // for <tr> element
                                    'name'      => 'toocheke-original-international-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-international-shipping',
                                'International Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-international-shipping',
                                    'class'     => 'toocheke-companion',                       // for <tr> element
                                    'name'      => 'toocheke-original-international-shipping', // pass any custom parameters
                                ]
                            );
                            //Print section
                            register_setting("toocheke-settings", "toocheke-print-description");
                            register_setting("toocheke-settings", 'toocheke-print-us-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-us-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-canada-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-canada-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-international-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-international-shipping', 'absint');
                            add_settings_field(
                                'toocheke-print-description',
                                'Description of prints',
                                [$this, 'toocheke_display_input_WYSIWYG'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-description',
                                    'class'     => 'toocheke-companion',         // for <tr> element
                                    'name'      => 'toocheke-print-description', // pass any custom parameters
                                ]
                            );
                            add_settings_section("toocheke_print_info_section", "Print information", [$this, 'toocheke_render_section_message'], "toocheke-options-page", ['message' => 'Enter the following fields for selling a comic\'s print.']);
                            add_settings_field(
                                'toocheke-print-us-price',
                                'US Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-us-price',
                                    'class'     => 'toocheke-companion',      // for <tr> element
                                    'name'      => 'toocheke-print-us-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-us-shipping',
                                'US Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-us-shipping',
                                    'class'     => 'toocheke-companion',         // for <tr> element
                                    'name'      => 'toocheke-print-us-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-canada-price',
                                'Canada Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-canada-price',
                                    'class'     => 'toocheke-companion',          // for <tr> element
                                    'name'      => 'toocheke-print-canada-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-canada-shipping',
                                'Canada Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-canada-shipping',
                                    'class'     => 'toocheke-companion',             // for <tr> element
                                    'name'      => 'toocheke-print-canada-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-international-price',
                                'International Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-international-price',
                                    'class'     => 'toocheke-companion',                 // for <tr> element
                                    'name'      => 'toocheke-print-international-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-international-shipping',
                                'International Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-international-shipping',
                                    'class'     => 'toocheke-companion',                    // for <tr> element
                                    'name'      => 'toocheke-print-international-shipping', // pass any custom parameters
                                ]
                            );
                        }

                        break;
                }
            }

            
            
            
            
            
            
            
            
            
            
            
            
            public function toocheke_buy_comic_display_message()
            {
                echo '<div class="notice notice-info inline"><p>To use the \'Buy Comic\' features, you will require a PayPal business account. Within your PayPal account, you will need to enter the <b>Instant Payment Notification(IPN)</b> setting. This is what PayPal uses to notify the website that a purchase has been made. Make sure to enter the <b style="color: #ff0000;">Notification URL</b>.</p><p> <a href="https://developer.paypal.com/api/nvp-soap/ipn/IPNSetup/" target="_blank">Here</a> is a tutorial on how to access the setting. <a href="https://www.paypal.com/merchantnotification/ipn/preference/edit" target="_blank">This link</a> should take you directly to where you need to update the URL(once you are logged in). Make sure to enter the following URL:<p><b style="color:#0000ff">' . get_site_url() . '/?action=IPN_Handler</b></p></div><hr/>';
            }

            public function toocheke_sponsor_comic_display_message()
            {
                echo '<div class="notice notice-info inline"><p>To use the \'Sponsor Comic\' features, you will require a PayPal business account. You will also need to obtain a <b>CLIENT_ID</b> and <b>CLIENT_SECRET </b>, that you will copy and paste into the corresponding fields on this page. This is what PayPal uses to process your payments.</p><p><a href="https://developer.paypal.com/docs/api/overview/" target="_blank">Here is a detailed guide</a> showing you how to get the API credentials you will need</p><p>Only enable the <b>sandbox mode</b> if you are testing. Otherwise leave this option unchecked. <p><b style="color: #ff0000;">NOTE:</b> If you are going to enable sandbox mode, you will need to use a different <b>CLIENT_ID</b> and <b>CLIENT_SECRET</b> that you can get from your sandbox account.</p><a href="https://developer.paypal.com/tools/sandbox/" target="_blank">Here is a helpful guide</a> on using Sandbox accounts in PayPal</p><p><a href="https://developer.paypal.com/docs/multiparty/create-account/#create-your-sandbox-api-caller-account" target="_blank">Here is a helpful guide</a> for obtaining the <b>CLIENT_ID</b> and <b>CLIENT_SECRET</b> from your sandbox account</p></div><hr/>';
            }

            
            
            
            
            
            
            
            
            
            public function toocheke_comicscout_message()
            {
                echo '
                <p>
                    <strong>ComicScout</strong> is a curated discovery platform designed to help readers find great independent comics across the web.
                </p>

                <p>
                    If your comic uses the <strong>Toocheke Companion</strong> plugin, your updates can be automatically discovered and promoted to new readers.
                </p>

                <p style="background:#f0f6fc;border-left:4px solid #2271b1;padding:10px;margin:10px 0;">
                    ⭐ <strong>Learn more about ComicScout:</strong><br>
                    <a href="https://www.thecomicscout.com/" target="_blank">https://www.thecomicscout.com/</a>
                </p>

                <p>
                    The settings below allow you to configure how your comic appears when shared and promoted through ComicScout.
                </p>
                ';
            }

            public function toocheke_comicscout_global_social_share_image_field()
            {
                $image_id = get_option('toocheke-comicscout-global-social-share-image');
                $image_id = $image_id ? (int) $image_id : 0;

                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

                $this->toocheke_render_dismissible_info('comicscout_global_social_share', '⭐ <strong>Used by <a href="https://www.thecomicscout.com/" target="_blank">ComicScout</a> for social promotion.</strong><br>This image will be used as the default social share image for ComicScout when no ComicScout Social Share Image is set on an individual post.<br><strong>Recommended size: 1200 × 630px</strong>.');

                if ($image_url) {
                    echo '<img id="toocheke_comicscout_global_social_share_image_preview" src="' . esc_url($image_url) . '" style="width:100%;height:auto;border:0;display:block;margin-bottom:10px;" />';

                    echo '<p class="hide-if-no-js">
                        <a href="javascript:;" id="remove_toocheke_comicscout_global_social_share_image_button">'
                        . esc_html__('Remove global ComicScout social share image', 'toocheke-companion') .
                        '</a>
                    </p>';
                } else {
                    echo '<div id="toocheke_comicscout_global_social_share_image_ratio_guide" style="
                        width:100%;
                        max-width:254px;
                        aspect-ratio:1.91/1;
                        background:#f6f7f7;
                        border:2px dashed #ccd0d4;
                        display:flex;
                        flex-direction:column;
                        align-items:center;
                        justify-content:center;
                        text-align:center;
                        font-size:12px;
                        color:#646970;
                        margin-bottom:10px;">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            width="40"
                            height="40"
                            viewBox="0 0 24 24"
                            fill="none"
                            style="display:block">

                            <rect x="3" y="5"
                                width="18"
                                height="14"
                                rx="2"
                                stroke="currentColor"
                                stroke-width="1"/>

                            <circle cx="9"
                                    cy="10"
                                    r="1.5"
                                    stroke="currentColor"
                                    stroke-width="1"/>

                            <path d="M4.5 16l4.5-3.5a2 2 0 0 1 2.5 0l2.2 1.8"
                                stroke="currentColor"
                                stroke-width="1"
                                stroke-linecap="round"
                                stroke-linejoin="round"/>

                            <path d="M10.8 16l4-3a2 2 0 0 1 2.5.1L19.5 15"
                                stroke="currentColor"
                                stroke-width="1"
                                stroke-linecap="round"
                                stroke-linejoin="round"/>
                        </svg>

                        <span>Image Preview</span>
                    </div>';

                    echo '<img id="toocheke_comicscout_global_social_share_image_preview" src="" style="width:100%;height:auto;border:0;display:none;margin-bottom:10px;" />';

                    echo '<p class="hide-if-no-js">
                        <a title="' . esc_attr__('Upload global ComicScout social share image', 'toocheke-companion') . '"
                        href="javascript:;"
                        id="upload_toocheke_comicscout_global_social_share_image_button"
                        data-uploader_title="' . esc_attr__('Choose an image', 'toocheke-companion') . '"
                        data-uploader_button_text="' . esc_attr__('Use this image', 'toocheke-companion') . '">'
                        . esc_html__('Upload global ComicScout social share image', 'toocheke-companion') .
                        '</a>
                    </p>';
                }

                // Always output hidden input
                echo '<input type="hidden"
                    id="toocheke_comicscout_global_social_share_image"
                    name="toocheke-comicscout-global-social-share-image"
                    value="' . esc_attr($image_id) . '" />';

                // If image exists, still show upload link for replacing it
                if ($image_url) {
                    echo '<p class="hide-if-no-js">
                        <a title="' . esc_attr__('Replace global ComicScout social share image', 'toocheke-companion') . '"
                        href="javascript:;"
                        id="upload_toocheke_comicscout_global_social_share_image_button"
                        data-uploader_title="' . esc_attr__('Choose an image', 'toocheke-companion') . '"
                        data-uploader_button_text="' . esc_attr__('Use this image', 'toocheke-companion') . '">'
                        . esc_html__('Replace global ComicScout social share image', 'toocheke-companion') .
                        '</a>
                    </p>';
                }
            }

            
            
            
            
            
            
            
            
            
            public function toocheke_allowed_html()
            {

                $allowed_atts = [
                    'align'      => [],
                    'class'      => [],
                    'type'       => [],
                    'id'         => [],
                    'dir'        => [],
                    'lang'       => [],
                    'style'      => [],
                    'xml:lang'   => [],
                    'src'        => [],
                    'alt'        => [],
                    'href'       => [],
                    'rel'        => [],
                    'rev'        => [],
                    'target'     => [],
                    'novalidate' => [],
                    'type'       => [],
                    'value'      => [],
                    'name'       => [],
                    'tabindex'   => [],
                    'action'     => [],
                    'method'     => [],
                    'for'        => [],
                    'width'      => [],
                    'height'     => [],
                    'data'       => [],
                    'title'      => [],
                    'selected'   => [],
                    'disabled'   => [],
                    'checked'    => [],
                ];
                $allowed_tags['form']     = $allowed_atts;
                $allowed_tags['label']    = $allowed_atts;
                $allowed_tags['select']   = $allowed_atts;
                $allowed_tags['option']   = $allowed_atts;
                $allowed_tags['input']    = $allowed_atts;
                $allowed_tags['textarea'] = $allowed_atts;
                $allowed_tags['iframe']   = $allowed_atts;
                $allowed_tags['script']   = $allowed_atts;
                $allowed_tags['style']    = $allowed_atts;
                $allowed_tags['strong']   = $allowed_atts;
                $allowed_tags['small']    = $allowed_atts;
                $allowed_tags['table']    = $allowed_atts;
                $allowed_tags['span']     = $allowed_atts;
                $allowed_tags['abbr']     = $allowed_atts;
                $allowed_tags['code']     = $allowed_atts;
                $allowed_tags['pre']      = $allowed_atts;
                $allowed_tags['div']      = $allowed_atts;
                $allowed_tags['img']      = $allowed_atts;
                $allowed_tags['h1']       = $allowed_atts;
                $allowed_tags['h2']       = $allowed_atts;
                $allowed_tags['h3']       = $allowed_atts;
                $allowed_tags['h4']       = $allowed_atts;
                $allowed_tags['h5']       = $allowed_atts;
                $allowed_tags['h6']       = $allowed_atts;
                $allowed_tags['ol']       = $allowed_atts;
                $allowed_tags['ul']       = $allowed_atts;
                $allowed_tags['li']       = $allowed_atts;
                $allowed_tags['em']       = $allowed_atts;
                $allowed_tags['hr']       = $allowed_atts;
                $allowed_tags['br']       = $allowed_atts;
                $allowed_tags['tr']       = $allowed_atts;
                $allowed_tags['td']       = $allowed_atts;
                $allowed_tags['p']        = $allowed_atts;
                $allowed_tags['a']        = $allowed_atts;
                $allowed_tags['b']        = $allowed_atts;
                $allowed_tags['i']        = $allowed_atts;

                return $allowed_tags;
            }

            /**
             * Renders a simple "1/0" settings checkbox. Shared by most of the
             * plugin's option checkboxes; pass the option key it should control.
             *
             * @param string $option_key The wp_options key this checkbox controls.
             * @param bool   $has_id      Whether to also output an id="" attribute matching $option_key.
             * @param string $label       Trailing label text (defaults to "Check for Yes").
             */
            private function toocheke_render_checkbox_field($option_key, $has_id = false, $label = 'Check for Yes')
            {
    ?>
        <input type="checkbox" <?php if ($has_id) : ?>id="<?php echo esc_attr($option_key); ?>" <?php endif; ?>name="<?php echo esc_attr($option_key); ?>" value="1"
            <?php checked(1, get_option($option_key), true); ?> /> <?php echo esc_html($label); ?>
    <?php
            }

            /**
             * Renders the "Choose an image" upload button + hidden field pair used
             * by every custom navigation/social/support button setting.
             *
             * @param string $slug The button slug, e.g. "first", "comic-archive", "facebook".
             *                     Option key is always "toocheke-{$slug}-button".
             */
            private function toocheke_render_button_upload_field($slug)
            {
                $option_key = 'toocheke-' . $slug . '-button';
                $button_value = esc_attr(get_option($option_key));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-<?php echo esc_attr($slug); ?>-button"
            data-hidden="<?php echo esc_attr($option_key); ?>" data-image="<?php echo esc_attr($slug); ?>-image">
        <input type="hidden" id="<?php echo esc_attr($option_key); ?>" name="<?php echo esc_attr($option_key); ?>" value="<?php echo $button_value ?>" />
    <?php
            }

            /**
             * Renders the live image preview shown next to each custom button upload field.
             *
             * @param string $slug Same slug used in toocheke_render_button_upload_field().
             */
            private function toocheke_render_button_preview_field($slug)
            {
                $option_key = 'toocheke-' . $slug . '-button';
                $button_value = esc_attr(get_option($option_key));
                // The preview wrapper div id uses underscores even for hyphenated
                // slugs (e.g. "comic_archive_button_preview"), while the option
                // key and image id use hyphens. This must stay consistent with
                // the corresponding CSS/JS selectors.
                $div_id = str_replace('-', '_', $slug);
    ?>
        <div id="<?php echo esc_attr($div_id); ?>_button_preview">
            <img id="<?php echo esc_attr($slug); ?>-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($button_value) ? $button_value : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }

            /**
             * Renders a support-link URL text field.
             *
             * @param string $slug The support platform slug, e.g. "patreon", "kofi".
             *                     Option key is always "toocheke-support-link-{$slug}".
             */
            private function toocheke_render_url_field($slug)
            {
                $option_key = 'toocheke-support-link-' . $slug;
    ?>
        <input type="url" name="<?php echo esc_attr($option_key); ?>" value="<?php echo get_option($option_key); ?>" />
    <?php
            }

            /**
             * Generic settings-section description renderer. WordPress calls a
             * settings-section callback with the section's array as its only
             * argument. add_settings_section()'s $args parameter is merged
             * directly into that array via wp_parse_args() -- it is NOT nested
             * under an 'args' key (that nesting only happens for
             * add_settings_field(), not add_settings_section()) -- so the
             * message text passed at registration ends up at $section['message']
             * directly, e.g.:
             *
             *   add_settings_section('my_section', 'My Section',
             *       [$this, 'toocheke_render_section_message'], 'toocheke-options-page',
             *       ['message' => 'Description shown under the section title.']);
             *
             * @param array $section The section array WordPress passes to settings-section callbacks.
             */
            public function toocheke_render_section_message($section)
            {
                echo $section['message'] ?? '';
            }

            public function toocheke_select_dropdown_generator(array $args)
            {
                $html           = '';
                $option         = $args['option'];
                $desc           = $args['desc'];
                $value          = get_option($option) ? get_option($option) : [];
                $select_options = $args['select_options'];

                $html .= '<select name="' . esc_attr($option) . '" id="' . esc_attr($option) . '">';
                foreach ($select_options as $k => $v) {
                    $selected = false;
                    if ($k == $value) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                $html .= $desc ? ' <p><em>' . esc_html($desc) . '</em></p>' : '';
                echo wp_kses($html, $this->toocheke_allowed_html());
            }

            public function toocheke_display_input_number($args)
            {
                printf(
                    '<input type="number" id="%s" name="%s" value="%d" />',
                    $args['name'],
                    $args['name'],
                    get_option($args['name'], 0) // 2 is the default number of slides
                );
            }

            public function toocheke_display_input_text($args)
            {
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" />',
                    $args['name'],
                    $args['name'],
                    get_option($args['name'], '') // 2 is the default number of slides
                );
            }

            public function toocheke_display_input_email($args)
            {
                printf(
                    '<input placeholder="Email address" type="email" id="%s" name="%s" value="%s" />',
                    $args['name'],
                    $args['name'],
                    get_option($args['name'], '') // 2 is the default number of slides
                );
            }

            public function toocheke_display_input_WYSIWYG($args)
            {
                 wp_editor(
        get_option($args['name'], ''),
        $args['name'],
        ['textarea_name' => $args['name']]
    );
            }

            public function toocheke_series_publish_options_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-display-multiple-series', false);
            }

            public function toocheke_display_latest_comics_of_all_multiple_series_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-display-latest-comics-of-all-multiple-series', false);
            }

            public function toocheke_series_landing_blog_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-series-landing-blog', false);
            }

            public function toocheke_traditional_home_series_dropdown($args)
            {
                $selected_series_id = get_option('toocheke-traditional-home-series', 0);
                $options_markup     = '<option value="0" ' . selected($selected_series_id, 0, false) . '>No series</option>';
                if (post_type_exists('series')):
                    $series_args = [
                        'post_type'   => 'series',
                        'post_status' => 'publish',
                        'nopaging'    => true,
                        'orderby'     => 'title',
                        'order'       => 'ASC',
                    ];
                    $series_query = new WP_Query($series_args);
                    if ($series_query->have_posts()):
                        while ($series_query->have_posts()): $series_query->the_post();
                            $post_id = get_the_ID();
                            $title   = get_the_title();
                            $options_markup .= sprintf('<option value="%s" %s>%s</option>', $post_id, selected($selected_series_id, $post_id, false), $title);
                        endwhile;
                        $series_query = null;
                        wp_reset_postdata();
                    endif;
                endif;
                printf('<select name="toocheke-traditional-home-series" id="toocheke-traditional-home-series">%1$s</select>', $options_markup);
            }

            public function toocheke_options_devices_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-layout-devices', false);
            }

            public function toocheke_comic_discussion_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-discussion', false);
            }

            public function toocheke_paywalled_discussion_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-paywalled-discussion', false);
            }

            public function toocheke_hide_blog_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-hide-blog', false);
            }

            public function toocheke_display_blog_webtoon_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-dspay-blog-on-webtoon', false);
            }

            public function toocheke_bilingual_display_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-bilingual-display', false);
            }

            public function toocheke_enable_paypal_sandbox_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-enable-paypal-sandbox', false, 'Enable');
            }

            public function toocheke_buy_original_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-original-art', false);
            }

            public function toocheke_global_buy_comic_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-global-buy-comic', false);
            }

            public function toocheke_buy_print_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-print', false);
            }

            public function toocheke_image_click_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-image-click', false);
            }

             public function toocheke_image_protection_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-image-protection', false);
            }

             public function toocheke_image_optimization_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-image-optimization" value="1"
            <?php checked(1, get_option('toocheke-image-optimization'), true); ?> /> Check for Yes
            <p style="background:#f0f6fc;border-left:4px solid #2271b1;padding:10px;margin:10px 0;">
        For best results, use a quality range of 50–60 for AVIF and 75–85 for WebP.
        These ranges provide strong compression while maintaining good visual quality.
        Higher values may reduce the benefits of image optimization.
    </p>
    <?php
            }

            /**
             * Shared callback for rendering a number input field.
             *
             * @param array $args  Must contain 'option_name' (string) and 'default' (int).
             */
            public function toocheke_image_quality_number_field(array $args)
            {
                $option_name = sanitize_key($args['option_name']);
                $default     = isset($args['default']) ? absint($args['default']) : 75;
                $value       = get_option($option_name, $default);
                ?>
                <input
                    type="number"
                    name="<?php echo esc_attr($option_name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    min="1"
                    max="100"
                    step="1"
                />
                <span class="description"><?php esc_html_e('Enter a value between 1 and 100.', 'toocheke'); ?></span>
                <?php
            }

            /**
             * Sanitize image quality: clamps the value to 1–100, falls back to 75.
             *
             * @param  mixed $value  Raw input from the settings form.
             * @return int           Sanitized integer between 1 and 100.
             */
            public function toocheke_sanitize_image_quality($value)
            {
                $int = absint($value);

                if ($int < 1 || $int > 100) {
                    add_settings_error(
                        'toocheke-settings',
                        'invalid-quality',
                        __('Image quality must be between 1 and 100. The value has been reset to 75.', 'toocheke'),
                        'error'
                    );
                    return 75;
                }

                return $int;
            }

            public function toocheke_future_image_protection_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-future-post-image-protection', false);
            }

            public function toocheke_comics_to_main_rss_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comics-to-main-rss', false);
            }

            public function toocheke_comic_likes_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-likes', false);
            }

            public function toocheke_comic_no_of_comments_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-no-of-comments', false);
            }

            public function toocheke_comic_no_of_views_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-no-of-views', false);
            }

            public function toocheke_comic_panel_swipe_navigation_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-panel-swipe-navigation', false);
            }

            public function toocheke_social_share_facebook_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-facebook', false);
            }

            public function toocheke_social_share_twitter_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-twitter', false);
            }

            public function toocheke_social_share_tumblr_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-tumblr', false);
            }

            public function toocheke_social_share_reddit_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-reddit', false);
            }

            public function toocheke_social_share_threads_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-threads', false);
            }

            public function toocheke_social_share_bluesky_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-bluesky', false);
            }

            public function toocheke_social_share_whatsapp_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-whatsapp', false);
            }

            public function toocheke_social_share_linkedin_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-linkedin', false);
            }

            public function toocheke_social_share_copy_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-social-share-copy', false);
            }

            public function toocheke_support_link_buymeacoffee_url()
            {
                $this->toocheke_render_url_field('buymeacoffee');
            }

            public function toocheke_support_link_gumroad_url()
            {
                $this->toocheke_render_url_field('gumroad');
            }

            public function toocheke_support_link_indiegogo_url()
            {
                $this->toocheke_render_url_field('indiegogo');
            }

            public function toocheke_support_link_kickstarter_url()
            {
                $this->toocheke_render_url_field('kickstarter');
            }

            public function toocheke_support_link_kofi_url()
            {
                $this->toocheke_render_url_field('kofi');
            }

            public function toocheke_support_link_liberapay_url()
            {
                $this->toocheke_render_url_field('liberapay');
            }

            public function toocheke_support_link_patreon_url()
            {
                $this->toocheke_render_url_field('patreon');
            }

            public function toocheke_support_link_paypal_url()
            {
                $this->toocheke_render_url_field('paypal');
            }

            public function toocheke_support_link_substack_url()
            {
                $this->toocheke_render_url_field('substack');
            }

            public function toocheke_support_link_tipeee_url()
            {
                $this->toocheke_render_url_field('tipeee');
            }

            public function toocheke_infinite_scroll_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-infinite-scroll', true);
            }

            public function toocheke_random_navigation_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-random-navigation', true);
            }

            public function toocheke_comic_archive_navigation_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-archive-navigation', true);
            }

            public function toocheke_click_comic_next_navigation_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-click-comic-next', true);
            }

            public function toocheke_comic_nav_above_comic_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-nav-above-comic', true);
            }

            public function toocheke_chapter_navigation_buttons_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-chapter-navigation-buttons', true);
            }

            public function toocheke_chapter_dropdown_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-chapter-dropdown', true);
            }

            public function toocheke_chapter_archive_link_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-chapter-archive-link', true);
            }

            public function toocheke_collection_archive_link_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-collection-archive-link', true);
            }

            public function toocheke_keyboard_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-keyboard', true);
            }

            public function toocheke_scroll_past_header_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-scroll-past-header', true);
            }

            public function toocheke_always_show_nav_buttons_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-always-show-nav-buttons', true);
            }

            public function toocheke_early_access_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-early-access', true);
            }

            public function toocheke_comic_bookmark_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comic-bookmark', true);
            }

            public function toocheke_comics_navigation_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-comics-navigation', true);
            }

                public function toocheke_manga_default_pages_radio()
            {
    ?>
        <input type="radio" name="toocheke-manga-default-pages" value="1"
            <?php checked('1', get_option('toocheke-manga-default-pages'), true); ?>> 1 page
        <input type="radio" name="toocheke-manga-default-pages" value="2"
            <?php checked('2', get_option('toocheke-manga-default-pages'), true); ?>> 2 pages
    <?php
            }

              public function toocheke_manga_rtl_radio()
            {
    ?>
        <input type="radio" name="toocheke-manga-rtl" value="0"
            <?php checked('0', get_option('toocheke-manga-rtl'), true); ?>> Left to right
        <input type="radio" name="toocheke-manga-rtl" value="1"
            <?php checked('1', get_option('toocheke-manga-rtl'), true); ?>> Right to left
    <?php
            }

            public function toocheke_comics_order_radio()
            {
    ?>
        <input type="radio" name="toocheke-comics-order" value="ASC"
            <?php checked('ASC', get_option('toocheke-comics-order'), true); ?>> Ascending
        <input type="radio" name="toocheke-comics-order" value="DESC"
            <?php checked('DESC', get_option('toocheke-comics-order'), true); ?>> Descending
    <?php
            }

            public function toocheke_comics_slider_order_radio()
            {
    ?>
        <input type="radio" name="toocheke-comics-slider-order" value="ASC"
            <?php checked('ASC', get_option('toocheke-comics-slider-order'), true); ?>> Ascending
        <input type="radio" name="toocheke-comics-slider-order" value="DESC"
            <?php checked('DESC', get_option('toocheke-comics-slider-order'), true); ?>> Descending
    <?php
            }

            public function toocheke_chapter_first_comic_radio()
            {
    ?>
        <input type="radio" name="toocheke-chapter-first-comic" value="ASC"
            <?php checked('ASC', get_option('toocheke-chapter-first-comic'), true); ?>> Oldest
        <input type="radio" name="toocheke-chapter-first-comic" value="DESC"
            <?php checked('DESC', get_option('toocheke-chapter-first-comic'), true); ?>> Latest
    <?php
            }

            public function toocheke_collection_first_comic_radio()
            {
    ?>
        <input type="radio" name="toocheke-collection-first-comic" value="ASC"
            <?php checked('ASC', get_option('toocheke-collection-first-comic'), true); ?>> Oldest
        <input type="radio" name="toocheke-collection-first-comic" value="DESC"
            <?php checked('DESC', get_option('toocheke-collection-first-comic'), true); ?>> Latest
    <?php
            }

            public function toocheke_series_order_radio()
            {
    ?>
        <input type="radio" name="toocheke-series-order" value="ASC"
            <?php checked('ASC', get_option('toocheke-series-order'), true); ?>> Ascending
        <input type="radio" name="toocheke-series-order" value="DESC"
            <?php checked('DESC', get_option('toocheke-series-order'), true); ?>> Descending
    <?php
            }

            public function toocheke_comics_archive_layout_select()
            {
                $options = get_option('toocheke-comics-archive');
    ?>
        <select name="toocheke-comics-archive[layout_type]">
            <option value="" disabled selected>Select your option</option>
            <option value="thumbnail-list"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "thumbnail-list"); ?>>Thumbnail
                List</option>
            <option value="plain-text-list"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "plain-text-list"); ?>>Plain Text
                List
            </option>
            <option value="calendar"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "calendar"); ?>>Calendar</option>
            <option value="gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "gallery"); ?>>
                Gallery/Grid</option>
            <option value="chapters-plain-text-list" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "chapters-plain-text-list"); ?>>
                Segmented By Chapters - Plain Text List</option>
            <option value="chapters-gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "chapters-gallery"); ?>>
                Segmented By Chapters - Gallery/Grid</option>
            <option value="collections-plain-text-list" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "collections-plain-text-list"); ?>>
                Segmented By Collections - Plain Text List</option>
            <option value="collections-gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "collections-gallery"); ?>>
                Segmented By Collections - Gallery/Grid</option>
            <option value="series-plain-text-list" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "series-plain-text-list"); ?>>
                Segmented By Series - Plain Text List</option>
            <option value="series-gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "series-gallery"); ?>>
                Segmented By Series - Gallery/Grid</option>
            <option value="yearly-plain-text-list" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "yearly-plain-text-list"); ?>>
                Segmented By Year - Plain Text List</option>
            <option value="yearly-gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "yearly-gallery"); ?>>
                Segmented By Year - Gallery/Grid</option>
        </select>
    <?php
            }

            public function toocheke_top_10_comics_layout_select()
            {
                $options = get_option('toocheke-top-10-comics-layout');
    ?>
        <select name="toocheke-top-10-comics-layout[layout_type]">
            <option value="" disabled selected>Select your option</option>
            <option value="thumbnail-list"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "thumbnail-list"); ?>>Thumbnail
                List</option>
            <option value="gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "gallery"); ?>>
                Gallery/Grid</option>
        </select>
    <?php
            }

            public function toocheke_first_button_upload()
            {
                $this->toocheke_render_button_upload_field('first');
            }

            public function toocheke_previous_button_upload()
            {
                $this->toocheke_render_button_upload_field('previous');
            }

            public function toocheke_random_button_upload()
            {
                $this->toocheke_render_button_upload_field('random');
            }

            public function toocheke_comic_archive_button_upload()
            {
                $this->toocheke_render_button_upload_field('comic-archive');
            }

            public function toocheke_next_button_upload()
            {
                $this->toocheke_render_button_upload_field('next');
            }

            public function toocheke_latest_button_upload()
            {
                $this->toocheke_render_button_upload_field('latest');
            }

            public function toocheke_next_chapter_button_upload()
            {
                $this->toocheke_render_button_upload_field('next-chapter');
            }

            public function toocheke_previous_chapter_button_upload()
            {
                $this->toocheke_render_button_upload_field('previous-chapter');
            }

            public function toocheke_facebook_button_upload()
            {
                $this->toocheke_render_button_upload_field('facebook');
            }

            public function toocheke_twitter_button_upload()
            {
                $this->toocheke_render_button_upload_field('twitter');
            }

            public function toocheke_tumblr_button_upload()
            {
                $this->toocheke_render_button_upload_field('tumblr');
            }

            public function toocheke_reddit_button_upload()
            {
                $this->toocheke_render_button_upload_field('reddit');
            }

            public function toocheke_threads_button_upload()
            {
                $this->toocheke_render_button_upload_field('threads');
            }

            public function toocheke_bluesky_button_upload()
            {
                $this->toocheke_render_button_upload_field('bluesky');
            }

            public function toocheke_whatsapp_button_upload()
            {
                $this->toocheke_render_button_upload_field('whatsapp');
            }

            public function toocheke_linkedin_button_upload()
            {
                $this->toocheke_render_button_upload_field('linkedin');
            }

            public function toocheke_copy_button_upload()
            {
                $this->toocheke_render_button_upload_field('copy');
            }

            public function toocheke_buymeacoffee_button_upload()
            {
                $this->toocheke_render_button_upload_field('buymeacoffee');
            }

            public function toocheke_gumroad_button_upload()
            {
                $this->toocheke_render_button_upload_field('gumroad');
            }

            public function toocheke_indiegogo_button_upload()
            {
                $this->toocheke_render_button_upload_field('indiegogo');
            }

            public function toocheke_kickstarter_button_upload()
            {
                $this->toocheke_render_button_upload_field('kickstarter');
            }

            public function toocheke_kofi_button_upload()
            {
                $this->toocheke_render_button_upload_field('kofi');
            }

            public function toocheke_liberapay_button_upload()
            {
                $this->toocheke_render_button_upload_field('liberapay');
            }

            public function toocheke_patreon_button_upload()
            {
                $this->toocheke_render_button_upload_field('patreon');
            }

            public function toocheke_paypal_button_upload()
            {
                $this->toocheke_render_button_upload_field('paypal');
            }

            public function toocheke_substack_button_upload()
            {
                $this->toocheke_render_button_upload_field('substack');
            }

            public function toocheke_tipeee_button_upload()
            {
                $this->toocheke_render_button_upload_field('tipeee');
            }

            public function toocheke_first_button_preview()
            {
                $this->toocheke_render_button_preview_field('first');
            }

            public function toocheke_previous_button_preview()
            {
                $this->toocheke_render_button_preview_field('previous');
            }

            public function toocheke_random_button_preview()
            {
                $this->toocheke_render_button_preview_field('random');
            }

            public function toocheke_comic_archive_button_preview()
            {
                $this->toocheke_render_button_preview_field('comic-archive');
            }

            public function toocheke_next_button_preview()
            {
                $this->toocheke_render_button_preview_field('next');
            }

            public function toocheke_next_chapter_button_preview()
            {
                $this->toocheke_render_button_preview_field('next-chapter');
            }

            public function toocheke_latest_button_preview()
            {
                $this->toocheke_render_button_preview_field('latest');
            }

            public function toocheke_previous_chapter_button_preview()
            {
                $this->toocheke_render_button_preview_field('previous-chapter');
            }

            public function toocheke_facebook_button_preview()
            {
                $this->toocheke_render_button_preview_field('facebook');
            }

            public function toocheke_twitter_button_preview()
            {
                $this->toocheke_render_button_preview_field('twitter');
            }

            public function toocheke_tumblr_button_preview()
            {
                $this->toocheke_render_button_preview_field('tumblr');
            }

            public function toocheke_reddit_button_preview()
            {
                $this->toocheke_render_button_preview_field('reddit');
            }

            public function toocheke_threads_button_preview()
            {
                $this->toocheke_render_button_preview_field('threads');
            }

            public function toocheke_bluesky_button_preview()
            {
                $this->toocheke_render_button_preview_field('bluesky');
            }

            public function toocheke_whatsapp_button_preview()
            {
                $this->toocheke_render_button_preview_field('whatsapp');
            }

            public function toocheke_linkedin_button_preview()
            {
                $this->toocheke_render_button_preview_field('linkedin');
            }

            public function toocheke_copy_button_preview()
            {
                $this->toocheke_render_button_preview_field('copy');
            }

            public function toocheke_buymeacoffee_button_preview()
            {
                $this->toocheke_render_button_preview_field('buymeacoffee');
            }

            public function toocheke_gumroad_button_preview()
            {
                $this->toocheke_render_button_preview_field('gumroad');
            }

            public function toocheke_indiegogo_button_preview()
            {
                $this->toocheke_render_button_preview_field('indiegogo');
            }

            public function toocheke_kickstarter_button_preview()
            {
                $this->toocheke_render_button_preview_field('kickstarter');
            }

            public function toocheke_kofi_button_preview()
            {
                $this->toocheke_render_button_preview_field('kofi');
            }

            public function toocheke_liberapay_button_preview()
            {
                $this->toocheke_render_button_preview_field('liberapay');
            }

            public function toocheke_patreon_button_preview()
            {
                $this->toocheke_render_button_preview_field('patreon');
            }

            public function toocheke_paypal_button_preview()
            {
                $this->toocheke_render_button_preview_field('paypal');
            }

            public function toocheke_substack_button_preview()
            {
                $this->toocheke_render_button_preview_field('substack');
            }

            public function toocheke_tipeee_button_preview()
            {
                $this->toocheke_render_button_preview_field('tipeee');
            }

            /**
             * Implement default image link.
             */
            public function toocheke_default_image_settings()
            {
                update_option('image_default_align', 'none');
                update_option('image_default_size', 'full');
                update_option('image_default_link_type', 'none');
            }

            /**
             * Add Admin Menus.
             */
            //showing the import comic easel page
            public function toocheke_include_import_comic_easel_page()
            {
                require_once __DIR__ . '/toocheke-companion-import-comic-easel.php';
            }

            //showing the import webcomic page
            public function toocheke_include_import_webcomic_page()
            {
                require_once __DIR__ . '/toocheke-companion-import-webcomic.php';
            }

            /*Replace description text field with WYSIWYG*/
            public function toocheke_description_wysiwyg($term, $taxonomy)
            {
    ?>
        <tr valign="top">
            <th scope="row">Description</th>
            <td>
                <?php wp_editor(html_entity_decode($term->description), 'description', ['media_buttons' => false]); ?>
                <script>
                    jQuery(window).ready(function() {
                        jQuery('label[for=description]').parent().parent().remove();
                    });
                </script>
            </td>
        </tr>
    <?php
            }

            public function toocheke_replace_term_description_field()
            {
                $taxonomies = get_taxonomies(['public' => true]);
                foreach ($taxonomies as $taxonomy) {
                    //add_action( $taxonomy . '_add_form_fields', array($this, 'toocheke_wysiwyg_term_description'), 10, 2 );
                    add_action($taxonomy . '_edit_form_fields', [$this, 'toocheke_wysiwyg_term_description'], 10, 2);
                }
            }

            public function toocheke_wysiwyg_term_description($term, $taxonomy)
            {
                // Remove the default description field and replace it with wp_editor
    ?>
        <tr class="form-field">
            <th valign="top" scope="row">
                <label for="wysiwyg-description"><?php _e('Description', 'toocheke-companion'); ?></label>
            </th>
            <td>
                <script type="text/javascript">
                    var description = document.getElementById("description");
                    if (description !== undefined && description.type == "textarea") {
                        description.parentElement.parentElement.remove();
                    } else {
                        var description = document.getElementById("tag-description");
                        if (description !== undefined && description.type == "textarea") {
                            description.parentElement.remove();
                        }
                    }
                </script>
                <?php wp_editor(htmlspecialchars_decode($term->description), "wysiwyg-description", ['textarea_name' => 'description']); ?>
            </td>
        </tr>
    <?php
            }

            //Displaying the meta box
            public function toocheke_manga_series_details_display($post)
            { ?>

        <?php
                // Nonce field
                wp_nonce_field('manga_series_save_meta', 'manga_series_nonce');
                $creator      = get_post_meta($post->ID, 'manga_creator', true);
                $status       = get_post_meta($post->ID, 'manga_status', true);
                $release_year = get_post_meta($post->ID, 'manga_release_year', true);
                $rating       = get_post_meta($post->ID, 'manga_rating', true);

        ?>
        <p>
            <label><?php _e('Creator', 'toocheke-companion'); ?>:</label>
            <input type="text" name="manga_creator" value="<?php echo esc_attr($creator); ?>" style="width:100%" />
        </p>

        <p>
            <label><?php _e('Status', 'toocheke-companion'); ?>:</label>
            <select name="manga_status">
                <option value="Ongoing" <?php selected($status, 'Ongoing'); ?>>Ongoing</option>
                <option value="Completed" <?php selected($status, 'Completed'); ?>>Completed</option>
                <option value="Hiatus" <?php selected($status, 'Hiatus'); ?>>Hiatus</option>
            </select>
        </p>
        <p>
            <label><?php _e('Release Year', 'toocheke-companion'); ?>:</label>
            <input type="number" name="manga_release_year" value="<?php echo esc_attr($release_year); ?>" />
        </p>
        <p>
            <label>Rating:</label>
            <input type="text" name="manga_rating" value="<?php echo esc_attr($rating); ?>" />
        </p>
    <?php }

            //Displaying the meta box
            public function toocheke_manga_volume_details_display($post)
            {
                // Nonce field
                wp_nonce_field('manga_volume_save_meta', 'manga_volume_nonce');

                $series_id       = get_post_meta($post->ID, 'series_id', true);
                $volume_number   = get_post_meta($post->ID, 'volume_number', true);
                $release_date    = get_post_meta($post->ID, 'release_date', true);
                $isbn            = get_post_meta($post->ID, 'isbn', true);
                $rating          = get_post_meta($post->ID, 'rating', true);
                $pages           = get_post_meta($post->ID, 'pages', true);
                $buy_digital_url = get_post_meta($post->ID, 'buy_digital_url', true);
                $buy_print_url   = get_post_meta($post->ID, 'buy_print_url', true);

                // Get all Manga Series
                $series_posts = get_posts([
                    'post_type'      => 'manga_series',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);
    ?>
        <p>
            <label>Series:</label>
            <select name="series_id">
                <option value="">-- Select Series --</option>
                <?php foreach ($series_posts as $series): ?>
                    <option value="<?php echo esc_attr($series->ID); ?>" <?php selected($series_id, $series->ID); ?>>
                        <?php echo esc_html($series->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Volume Number:</label>
            <input type="number" name="volume_number" value="<?php echo esc_attr($volume_number); ?>" />
        </p>
        <p>
            <label>Release Date:</label>
            <input type="date" name="release_date" value="<?php echo esc_attr($release_date); ?>" />
        </p>
        <p>
            <label>ISBN:</label>
            <input type="text" name="isbn" value="<?php echo esc_attr($isbn); ?>" />
        </p>
        <p>
            <label>Rating:</label>
            <input type="text" name="rating" value="<?php echo esc_attr($rating); ?>" />
        </p>
        <p>
            <label>Pages:</label>
            <input type="number" name="pages" value="<?php echo esc_attr($pages); ?>" />
        </p>
        <p>
            <label>Buy Digital URL:</label>
            <input type="url" name="buy_digital_url" value="<?php echo esc_attr($buy_digital_url); ?>" />
        </p>
        <p>
            <label>Buy Print URL:</label>
            <input type="url" name="buy_print_url" value="<?php echo esc_attr($buy_print_url); ?>" />
        </p>
    <?php
            }

            //Displaying the meta box
            public function toocheke_manga_chapter_pages_display($post)
            {
                // Get saved images
                $page_ids = get_post_meta($post->ID, 'manga_chapter_pages', true);
                // Normalize to array
                if (! is_array($page_ids)) {
                    $page_ids = $page_ids ? array_filter(explode(',', $page_ids)) : [];
                }

    ?>
        <ul class="manga-chapter-gallery chama-gallery-input">
            <?php foreach ($page_ids as $i => $id):
                    $url = wp_get_attachment_image_url($id, [140, 140]);
                    if ($url): ?>
                    <li data-id="<?php echo esc_attr($id); ?>">
                        <span class="manga-chapter-page" style="background-image:url('<?php echo esc_url($url); ?>')"></span>
                        <a class="button manga-chapter-gallery-remove" href="#"
                            data-hidden="manga_chapter_pages"
                            data-id="<?php echo esc_attr($id); ?>">
                            <?php _e('Remove', 'toocheke-companion'); ?>
                        </a>
                    </li>
            <?php endif;
                endforeach; ?>
        </ul>

        <input type="hidden" id="manga_chapter_pages"
            name="manga_chapter_pages"
            value="<?php echo esc_attr(join(',', $page_ids)); ?>" />

        <a href="#" class="button manga-page-upload-button">
            <?php _e('Add Images', 'toocheke-companion'); ?>
        </a>
    <?php
            }

            public function toocheke_manga_chapter_details_display($post)
            {
                // Nonce field
                wp_nonce_field('manga_chapter_save_meta', 'manga_chapter_nonce');

                $series_id      = get_post_meta($post->ID, 'series_id', true);
                $volume_id      = get_post_meta($post->ID, 'volume_id', true);
                $chapter_number = get_post_meta($post->ID, 'chapter_number', true);
                $release_date   = get_post_meta($post->ID, 'release_date', true);
                $notes          = get_post_meta($post->ID, 'notes', true);
                $pages          = get_post_meta($post->ID, 'pages', true);

                // Get all Manga Series
                $series_posts = get_posts([
                    'post_type'      => 'manga_series',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);

                // Get all Volumes
                $volume_posts = get_posts([
                    'post_type'      => 'manga_volume',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);
    ?>
        <p>
            <label>Series:</label>
            <select name="series_id">
                <option value="">-- Select Series --</option>
                <?php foreach ($series_posts as $series): ?>
                    <option value="<?php echo esc_attr($series->ID); ?>" <?php selected($series_id, $series->ID); ?>>
                        <?php echo esc_html($series->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Volume:</label>
            <select name="volume_id">
                <option value="">-- Select Volume --</option>
                <?php foreach ($volume_posts as $volume): ?>
                    <option value="<?php echo esc_attr($volume->ID); ?>" <?php selected($volume_id, $volume->ID); ?>>
                        <?php echo esc_html($volume->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Chapter Number:</label>
            <input type="number" name="chapter_number" value="<?php echo esc_attr($chapter_number); ?>" />
        </p>
        <p>
            <label>Release Date:</label>
            <input type="date" name="release_date" value="<?php echo esc_attr($release_date); ?>" />
        </p>
        <p>
            <label>Pages:</label>
            <input type="number" name="pages" value="<?php echo esc_attr($pages); ?>" />
        </p>
        <p>
            <label>Notes:</label>
            <textarea name="notes" style="width:100%"><?php echo esc_textarea($notes); ?></textarea>
        </p>

        <?php
            }

            /* Enqueue Styles and scripts*/
            public function toocheke_admin_styles_and_scripts()
            {
                wp_register_style('toocheke-companion-dashicons', plugins_url('toocheke-companion' . '/css/toocheke.css'), array(), TOOCHEKE_COMPANION_VERSION);
                wp_enqueue_style('toocheke-companion-dashicons');

                //enqueue wordpress js media library.
                wp_enqueue_media();
                wp_enqueue_script('toocheke-admin-script', plugins_url('toocheke-companion' . '/js/toocheke.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);

               //Localize nonce JS for dismissing alerts
               wp_localize_script('toocheke-admin-script', 'toochekeNotices', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('toocheke_dismiss_notice'),
]);
                // Localize for manga admin filters
                $screen = get_current_screen();
                if ($screen && $screen->base === 'edit' && $screen->post_type === 'manga_chapter') {
                    wp_localize_script('toocheke-admin-script', 'toochekeMangaAdmin', [
                        'ajaxUrl'    => admin_url('admin-ajax.php'),
                        'nonce'      => wp_create_nonce('toocheke_manga_admin_nonce'),
                        'allVolumes' => __('All Volumes', 'toocheke-companion'),
                    ]);
                }

                //enqueue wordpress js media library.
                wp_enqueue_media();
                wp_enqueue_script('toocheke-media-library-script', plugins_url('toocheke-companion' . '/js/media.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                wp_enqueue_script('toocheke-media-library-script');

                $screen = get_current_screen();
                // Check we're only on the edit-tags page in the plugin
                if ('edit-tags' === $screen->base && ('series' === $screen->post_type || 'comic' === $screen->post_type)) {
                    wp_enqueue_script('toocheke-tags-script', plugins_url('toocheke-companion' . '/js/handle-tags-menu.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                    wp_enqueue_script('toocheke-tags-script');
                }

                if ('edit' === $screen->base && 'comic' === $screen->post_type) {
                    wp_enqueue_script('toocheke-populate-script', plugins_url('toocheke-companion' . '/js/populate.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                    wp_enqueue_script('toocheke-populate-script');
                }
                //color picker
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
            }

            /**
             * Enqueues the options-page nav collapse CSS/JS — scoped to just
             * the Toocheke options screen (not site-wide admin, unlike
             * toocheke_admin_styles_and_scripts() above) since it's only
             * relevant there.
             *
             * Cache-busted with filemtime() rather than the plugin's static
             * version constant. This plugin isn't being version-bumped for
             * every small fix during this feature's development, so a
             * static ?ver= would leave browsers serving a stale cached copy
             * of these files after every edit, indefinitely, with no way
             * for the browser to know anything changed. filemtime() means
             * the cache-busting value changes automatically the moment the
             * file's content does, with no version bump required.
             */
            public function toocheke_enqueue_options_nav_assets()
            {
                if (empty($_GET['page']) || 'toocheke-options-page' !== $_GET['page']) {
                    return;
                }

                $css_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'css/toocheke-options-nav.css';
                $js_path  = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/toocheke-options-nav.js';

                wp_enqueue_style(
                    'toocheke-options-nav',
                    TOOCHEKE_COMPANION_PLUGIN_URL . 'css/toocheke-options-nav.css',
                    [],
                    file_exists($css_path) ? filemtime($css_path) : TOOCHEKE_COMPANION_VERSION
                );
                wp_enqueue_script(
                    'toocheke-options-nav',
                    TOOCHEKE_COMPANION_PLUGIN_URL . 'js/toocheke-options-nav.js',
                    ['jquery'],
                    file_exists($js_path) ? filemtime($js_path) : TOOCHEKE_COMPANION_VERSION,
                    true
                );
            }

            /**
             * Add Toocheke Menu
             */
            public function toocheke_add_plugin_main_menu()
            {
                global $submenu;
                $theme = wp_get_theme();

                // Add separator before Toocheke menus 
                $menu[] = ['', 'read', 'separator-toocheke', '', 'wp-menu-separator'];

                $icon_comics = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M2 3a1 1 0 011-1h14a1 1 0 011 1v10a1 1 0 01-1 1H6l-4 3V3z"/><line x1="5" y1="7" x2="15" y2="7" stroke="#1d2327" stroke-width="1.2"/><line x1="5" y1="10" x2="11" y2="10" stroke="#1d2327" stroke-width="1.2"/></svg>');

                $icon_series = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect fill="#a7aaad" x="2" y="3" width="10" height="14" rx="1"/><rect fill="#a7aaad" x="13" y="3" width="3" height="14" rx="1" opacity=".55"/><rect fill="#a7aaad" x="17" y="4" width="1.5" height="12" rx=".5" opacity=".3"/></svg>');

                $icon_manga_series = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect fill="#a7aaad" x="3" y="2" width="11" height="16" rx="1"/><rect fill="#a7aaad" x="14" y="2" width="3" height="16" rx="1" opacity=".5"/><line x1="6" y1="6" x2="11" y2="6" stroke="#1d2327" stroke-width="1"/><line x1="6" y1="9" x2="11" y2="9" stroke="#1d2327" stroke-width="1"/><line x1="6" y1="12" x2="9" y2="12" stroke="#1d2327" stroke-width="1"/></svg>');

                $icon_manga_volume = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h9l5-5V4a2 2 0 00-2-2H4z"/><path fill="#a7aaad" d="M13 14v4.5L18 14h-5z" opacity=".6"/><line x1="5" y1="7" x2="13" y2="7" stroke="#1d2327" stroke-width="1"/><line x1="5" y1="10" x2="13" y2="10" stroke="#1d2327" stroke-width="1"/><line x1="5" y1="13" x2="9" y2="13" stroke="#1d2327" stroke-width="1"/></svg>');

                $icon_manga_chapter = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M2 3a1 1 0 011-1h6v16H3a1 1 0 01-1-1V3z"/><path fill="#a7aaad" d="M11 2h6a1 1 0 011 1v14a1 1 0 01-1 1h-6V2z" opacity=".7"/><line x1="4" y1="6" x2="7" y2="6" stroke="#1d2327" stroke-width=".9"/><line x1="4" y1="9" x2="7" y2="9" stroke="#1d2327" stroke-width=".9"/><line x1="4" y1="12" x2="7" y2="12" stroke="#1d2327" stroke-width=".9"/><line x1="12" y1="6" x2="15" y2="6" stroke="#1d2327" stroke-width=".9"/><line x1="12" y1="9" x2="15" y2="9" stroke="#1d2327" stroke-width=".9"/><line x1="12" y1="12" x2="15" y2="12" stroke="#1d2327" stroke-width=".9"/></svg>');

                // ── 1. TOOCHEKE (position 2) ────────────────────────────────────
                add_menu_page('Toocheke', 'Toocheke', 'edit_posts', 'toocheke-menu', [$this, 'toocheke_dashboard_hub_page'], 'dashicons-toocheke-companion', 2);
                add_submenu_page('toocheke-menu', 'Dashboard', 'Dashboard', 'edit_posts', 'toocheke-menu',         [$this, 'toocheke_dashboard_hub_page']);
                add_submenu_page('toocheke-menu', 'Options',   'Options',   'edit_posts', 'toocheke-options-page', [$this, 'toocheke_display_options_page']);

                if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {

                    $icon_slides = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect fill="#a7aaad" x="1" y="3" width="18" height="14" rx="1"/><rect fill="#1d2327" x="3" y="5" width="14" height="10" rx=".5" opacity=".4"/><polygon fill="#a7aaad" points="8,7 14,10 8,13"/></svg>');

                    $icon_sponsorships = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 2l2.4 4.8 5.3.8-3.8 3.7.9 5.2L10 14l-4.8 2.5.9-5.2L2.3 7.6l5.3-.8z"/></svg>');

                    // ── SLIDES (Premium only) ───────────────────────────────────
                    add_menu_page(
                        'Slides',
                        'Slides',
                        'edit_posts',
                        'toocheke-slides',
                        [$this, 'toocheke_slides_hub_page'],
                        $icon_slides,
                        8
                    );
                    add_submenu_page('toocheke-slides', 'All Slides',    'All Slides', 'edit_posts', 'edit.php?post_type=slide',     null);
                    add_submenu_page('toocheke-slides', 'Add New Slide', 'Add New',    'edit_posts', 'post-new.php?post_type=slide', null);

                    // ── SPONSORSHIPS (Premium only) ─────────────────────────────
                    add_menu_page(
                        'Sponsorships',
                        'Sponsorships',
                        'edit_posts',
                        'toocheke-sponsorships',
                        [$this, 'toocheke_sponsorships_hub_page'],
                        $icon_sponsorships,
                        9
                    );
                    add_submenu_page('toocheke-sponsorships', 'All Sponsorships',    'All Sponsorships', 'edit_posts', 'edit.php?post_type=comic_sponsorship',     null);
                    add_submenu_page('toocheke-sponsorships', 'Add New Sponsorship', 'Add New',          'edit_posts', 'post-new.php?post_type=comic_sponsorship', null);
                }

                add_submenu_page('toocheke-menu', 'Import From Comic Easel', 'Import: Comic Easel', 'edit_posts', 'toocheke-import-comic-easel', [$this, 'toocheke_include_import_comic_easel_page']);
                add_submenu_page('toocheke-menu', 'Import From Webcomic',    'Import: Webcomic',    'edit_posts', 'toocheke-import-webcomic',    [$this, 'toocheke_include_import_webcomic_page']);

                $submenu['toocheke-menu'][9999] = ['Promote on <b>ComicScout</b>', 'edit_posts', 'https://www.thecomicscout.com/', '', 'toocheke-comicscout-link'];

                // ── 2. COMICS (position 3) ──────────────────────────────────────
                add_menu_page('Comics', 'Comics', 'edit_posts', 'toocheke-comics-hub', [$this, 'toocheke_comics_hub_page'], $icon_comics, 3);
                add_submenu_page('toocheke-comics-hub', 'Comics Hub',    'Hub',        'edit_posts', 'toocheke-comics-hub',                                    [$this, 'toocheke_comics_hub_page']);
                add_submenu_page('toocheke-comics-hub', 'All Comics',    'All Comics', 'edit_posts', 'edit.php?post_type=comic',                               null);
                add_submenu_page('toocheke-comics-hub', 'Add New Comic', 'Add New',    'edit_posts', 'post-new.php?post_type=comic',                           null);
                add_submenu_page('toocheke-comics-hub', 'Collections',   'Collections','edit_posts', 'edit-tags.php?taxonomy=collections&post_type=comic',     null);
                add_submenu_page('toocheke-comics-hub', 'Chapters',      'Chapters',   'edit_posts', 'edit-tags.php?taxonomy=chapters&post_type=comic',        null);
                add_submenu_page('toocheke-comics-hub', 'Tags',          'Tags',       'edit_posts', 'edit-tags.php?taxonomy=comic_tags&post_type=comic',      null);
                add_submenu_page('toocheke-comics-hub', 'Locations',     'Locations',  'edit_posts', 'edit-tags.php?taxonomy=comic_locations&post_type=comic', null);
                add_submenu_page('toocheke-comics-hub', 'Characters',    'Characters', 'edit_posts', 'edit-tags.php?taxonomy=comic_characters&post_type=comic',null);

                // ── 3. SERIES (position 4) ──────────────────────────────────────
                add_menu_page('Series', 'Series', 'edit_posts', 'toocheke-series-hub', [$this, 'toocheke_series_hub_page'], $icon_series, 4);
                add_submenu_page('toocheke-series-hub', 'Series Hub',     'Hub',        'edit_posts', 'toocheke-series-hub',                                  [$this, 'toocheke_series_hub_page']);
                add_submenu_page('toocheke-series-hub', 'All Series',     'All Series', 'edit_posts', 'edit.php?post_type=series',                            null);
                add_submenu_page('toocheke-series-hub', 'Add New Series', 'Add New',    'edit_posts', 'post-new.php?post_type=series',                        null);
                add_submenu_page('toocheke-series-hub', 'Genres',         'Genres',     'edit_posts', 'edit-tags.php?taxonomy=genres&post_type=series',       null);
                add_submenu_page('toocheke-series-hub', 'Tags',           'Tags',       'edit_posts', 'edit-tags.php?taxonomy=series_tags&post_type=series',  null);

                // ── 4. MANGA SERIES (position 5) ───────────────────────────────
                add_menu_page('Manga Series', 'Manga Series', 'edit_posts', 'toocheke-manga-series-hub', [$this, 'toocheke_manga_series_hub_page'], $icon_manga_series, 5);
                add_submenu_page('toocheke-manga-series-hub', 'Manga Series Hub',            'Hub',        'edit_posts', 'toocheke-manga-series-hub',                                              [$this, 'toocheke_manga_series_hub_page']);
                add_submenu_page('toocheke-manga-series-hub', 'All Manga Series',     'All Series', 'edit_posts', 'edit.php?post_type=manga_series',                                 null);
                add_submenu_page('toocheke-manga-series-hub', 'Add New Manga Series', 'Add New',    'edit_posts', 'post-new.php?post_type=manga_series',                             null);
                add_submenu_page('toocheke-manga-series-hub', 'Manga Genres',         'Genres',     'edit_posts', 'edit-tags.php?taxonomy=manga_genre&post_type=manga_series',       null);
                add_submenu_page('toocheke-manga-series-hub', 'Manga Publishers',     'Publishers', 'edit_posts', 'edit-tags.php?taxonomy=manga_publisher&post_type=manga_series',   null);

                // ── 5. MANGA VOLUMES (position 6) ──────────────────────────────
                add_menu_page('Manga Volumes', 'Manga Volumes', 'edit_posts', 'toocheke-manga-volumes-hub', [$this, 'toocheke_manga_volumes_hub_page'], $icon_manga_volume, 6);
                  add_submenu_page('toocheke-manga-volumes-hub', 'Manga Volummes Hub',            'Hub',        'edit_posts', 'toocheke-manga-volumes-hub',                                              [$this, 'toocheke_manga_volumes_hub_page']);
                add_submenu_page('toocheke-manga-volumes-hub', 'All Manga Volumes',    'All Volumes',    'edit_posts', 'edit.php?post_type=manga_volume',    null);
                add_submenu_page('toocheke-manga-volumes-hub', 'Add New Manga Volume', 'Add New Volume', 'edit_posts', 'post-new.php?post_type=manga_volume',null);

                // ── 6. MANGA CHAPTERS (position 7) ─────────────────────────────
                add_menu_page('Manga Chapters', 'Manga Chapters', 'edit_posts', 'toocheke-manga-chapters-hub', [$this, 'toocheke_manga_chapters_hub_page'], $icon_manga_chapter, 7);
                add_submenu_page('toocheke-manga-chapters-hub', 'Manga Chapters Hub',            'Hub',        'edit_posts', 'toocheke-manga-chapters-hub',                                              [$this, 'toocheke_manga_chapters_hub_page']);
                add_submenu_page('toocheke-manga-chapters-hub', 'All Manga Chapters',    'All Chapters',    'edit_posts', 'edit.php?post_type=manga_chapter',    null);
                add_submenu_page('toocheke-manga-chapters-hub', 'Add New Manga Chapter', 'Add New Chapter', 'edit_posts', 'post-new.php?post_type=manga_chapter',null);
            }

           public function toocheke_dashboard_hub_page()
{
    $theme = wp_get_theme();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Welcome to Toocheke!', 'toocheke-companion'); ?></h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=series')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=series')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Comics', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all comics', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add Comic', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new comic', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Manga Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all manga series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_series')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add Manga Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new manga series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_series')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Manga Volumes', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all manga volumes', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_volume')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Manga Chapters', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all manga chapters', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_chapter')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Options', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Configure theme & plugin settings', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=toocheke-options-page')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Comic Easel Import', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Import comics from the Comic Easel plugin', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=toocheke-import-comic-easel')); ?>" class="button button-primary"><?php esc_html_e('Import', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Webcomic Import', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Import comics from the Webcomic plugin', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=toocheke-import-webcomic')); ?>" class="button button-primary"><?php esc_html_e('Import', 'toocheke-companion'); ?></a>
            </div>

        </div>
    </div>
    <?php
}

public function toocheke_comics_hub_page()
{
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Comic Management', 'toocheke-companion'); ?></h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Comics', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all comics', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add New Comic', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new comic', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Comic Collections', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage comic collections', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=collections&post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Comic Chapters', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage comic chapters', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=chapters&post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Comic Tags', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage comic tags', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=comic_tags&post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Comic Locations', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage comic locations', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=comic_locations&post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Comic Characters', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage comic characters', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=comic_characters&post_type=comic')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

        </div>
    </div>
    <?php
}

public function toocheke_series_hub_page()
{
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Series Management', 'toocheke-companion'); ?></h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=series')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add New Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=series')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Series Genres', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage genres for series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=genres&post_type=series')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Series Tags', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage tags for series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=series_tags&post_type=series')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

        </div>
    </div>
    <?php
}

public function toocheke_manga_series_hub_page()
{
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Manga Series Management', 'toocheke-companion'); ?></h1>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Manga Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all manga series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_series')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add Manga Series', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new manga series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_series')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Manga Series Genres', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage genres for manga series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=manga_genre&post_type=manga_series')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Manga Series Publishers', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Manage publishers for manga series', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=manga_publisher&post_type=manga_series')); ?>" class="button button-primary"><?php esc_html_e('Manage', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Manga Volumes', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all manga volumes', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_volume')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add Manga Volume', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new manga volume', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_volume')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('All Manga Chapters', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('View and manage all manga chapters', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_chapter')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
            </div>

            <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                <h2><?php esc_html_e('Add Manga Chapter', 'toocheke-companion'); ?></h2>
                <p><?php esc_html_e('Create a new manga chapter', 'toocheke-companion'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_chapter')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
            </div>

        </div>
    </div>
    <?php
}

            public function toocheke_admin_menu_highlighting()
            {
                global $parent_file, $submenu_file, $pagenow;

                // ── COMICS ─────────────────────────────────────────────────────
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'comic') {
                    $parent_file  = 'toocheke-comics-hub';
                    $submenu_file = 'edit.php?post_type=comic';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'comic') {
                    $parent_file  = 'toocheke-comics-hub';
                    $submenu_file = 'post-new.php?post_type=comic';
                }
                if ($pagenow === 'post.php' && get_post_type() === 'comic') {
                    $parent_file  = 'toocheke-comics-hub';
                    $submenu_file = 'edit.php?post_type=comic';
                }
                // Comic taxonomies (list screen)
                if ($pagenow === 'edit-tags.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'comic') {
                    $parent_file  = 'toocheke-comics-hub';
                    $submenu_file = 'edit-tags.php?taxonomy=' . ($_GET['taxonomy'] ?? '') . '&post_type=comic';
                }
                // Comic taxonomies (editing a single term)
                if ($pagenow === 'term.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'comic') {
                    $parent_file  = 'toocheke-comics-hub';
                    $submenu_file = 'edit-tags.php?taxonomy=' . ($_GET['taxonomy'] ?? '') . '&post_type=comic';
                }

                // ── SERIES ─────────────────────────────────────────────────────
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'series') {
                    $parent_file  = 'toocheke-series-hub';
                    $submenu_file = 'edit.php?post_type=series';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'series') {
                    $parent_file  = 'toocheke-series-hub';
                    $submenu_file = 'post-new.php?post_type=series';
                }
                if ($pagenow === 'post.php' && get_post_type() === 'series') {
                    $parent_file  = 'toocheke-series-hub';
                    $submenu_file = 'edit.php?post_type=series';
                }
                // Series taxonomies (list screen)
                if ($pagenow === 'edit-tags.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'series') {
                    $parent_file  = 'toocheke-series-hub';
                    $submenu_file = 'edit-tags.php?taxonomy=' . ($_GET['taxonomy'] ?? '') . '&post_type=series';
                }
                // Series taxonomies (editing a single term)
                if ($pagenow === 'term.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'series') {
                    $parent_file  = 'toocheke-series-hub';
                    $submenu_file = 'edit-tags.php?taxonomy=' . ($_GET['taxonomy'] ?? '') . '&post_type=series';
                }

                // ── MANGA SERIES ───────────────────────────────────────────────
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_series') {
                    $parent_file  = 'toocheke-manga-series-hub';
                    $submenu_file = 'edit.php?post_type=manga_series';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_series') {
                    $parent_file  = 'toocheke-manga-series-hub';
                    $submenu_file = 'post-new.php?post_type=manga_series';
                }
                if ($pagenow === 'post.php' && get_post_type() === 'manga_series') {
                    $parent_file  = 'toocheke-manga-series-hub';
                    $submenu_file = 'edit.php?post_type=manga_series';
                }
                // Manga Series taxonomies (list screen)
                if ($pagenow === 'edit-tags.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_series') {
                    $parent_file  = 'toocheke-manga-series-hub';
                    $submenu_file = 'edit-tags.php?taxonomy=' . ($_GET['taxonomy'] ?? '') . '&post_type=manga_series';
                }
                // Manga Series taxonomies (editing a single term)
                if ($pagenow === 'term.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_series') {
                    $parent_file  = 'toocheke-manga-series-hub';
                    $submenu_file = 'edit-tags.php?taxonomy=' . ($_GET['taxonomy'] ?? '') . '&post_type=manga_series';
                }

                // ── MANGA VOLUMES ──────────────────────────────────────────────
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_volume') {
                    $parent_file  = 'toocheke-manga-volumes-hub';
                    $submenu_file = 'edit.php?post_type=manga_volume';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_volume') {
                    $parent_file  = 'toocheke-manga-volumes-hub';
                    $submenu_file = 'post-new.php?post_type=manga_volume';
                }
                if ($pagenow === 'post.php' && get_post_type() === 'manga_volume') {
                    $parent_file  = 'toocheke-manga-volumes-hub';
                    $submenu_file = 'edit.php?post_type=manga_volume';
                }

                // ── MANGA CHAPTERS ─────────────────────────────────────────────
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_chapter') {
                    $parent_file  = 'toocheke-manga-chapters-hub';
                    $submenu_file = 'edit.php?post_type=manga_chapter';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_chapter') {
                    $parent_file  = 'toocheke-manga-chapters-hub';
                    $submenu_file = 'post-new.php?post_type=manga_chapter';
                }
                if ($pagenow === 'post.php' && get_post_type() === 'manga_chapter') {
                    $parent_file  = 'toocheke-manga-chapters-hub';
                    $submenu_file = 'edit.php?post_type=manga_chapter';
                }
                // Slides (Premium) — under toocheke-slides
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'slide') {
                    $parent_file  = 'toocheke-slides';
                    $submenu_file = 'edit.php?post_type=slide';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'slide') {
                    $parent_file  = 'toocheke-slides';
                    $submenu_file = 'post-new.php?post_type=slide';
                }
                if ($pagenow === 'post.php' && get_post_type() === 'slide') {
                    $parent_file  = 'toocheke-slides';
                    $submenu_file = 'edit.php?post_type=slide';
                }

                // Sponsorships (Premium) — under toocheke-sponsorships
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'comic_sponsorship') {
                    $parent_file  = 'toocheke-sponsorships';
                    $submenu_file = 'edit.php?post_type=comic_sponsorship';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'comic_sponsorship') {
                    $parent_file  = 'toocheke-sponsorships';
                    $submenu_file = 'post-new.php?post_type=comic_sponsorship';
                }
                if ($pagenow === 'post.php' && get_post_type() === 'comic_sponsorship') {
                    $parent_file  = 'toocheke-sponsorships';
                    $submenu_file = 'edit.php?post_type=comic_sponsorship';
                }
            }

            public function toocheke_force_menu_order($menu_order)
            {
                $theme = wp_get_theme();

                $toocheke_menus = [
                    'separator-toocheke',
                    'toocheke-menu',
                    'toocheke-comics-hub',
                    'toocheke-series-hub',
                    'toocheke-manga-series-hub',
                    'toocheke-manga-volumes-hub',
                    'toocheke-manga-chapters-hub',
                ];

                // Add Premium menus only if Toocheke Premium is active
                if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                    $toocheke_menus[] = 'toocheke-slides';
                    $toocheke_menus[] = 'toocheke-sponsorships';
                }

                // Remove Toocheke menus from wherever they currently sit
                $menu_order = array_values(array_filter($menu_order, function($item) use ($toocheke_menus) {
                    return !in_array($item, $toocheke_menus);
                }));

                // Find Dashboard and insert Toocheke block right after it
                $dashboard_pos = array_search('index.php', $menu_order);

                if ($dashboard_pos !== false) {
                    array_splice($menu_order, $dashboard_pos + 1, 0, $toocheke_menus);
                } else {
                    array_splice($menu_order, 0, 0, $toocheke_menus);
                }

                return $menu_order;
            }

            /**
             * Add "All [Post Type]" button to Add New CPT pages
             */
            public function toocheke_add_all_posts_button()
            {
                global $pagenow, $post_type;

                // Only run on "Add New" pages
                if ($pagenow !== 'post-new.php') {
                    return;
                }

                // List of post types you want this for
                $cpt_list = ['series', 'comic', 'manga_series', 'manga_volume', 'manga_chapter'];

                if (in_array($post_type, $cpt_list)) {
                    $post_type_obj = get_post_type_object($post_type);
                    if ($post_type_obj) {
                        $all_posts_url = admin_url('edit.php?post_type=' . $post_type);
                        $button_label  = 'All ' . $post_type_obj->labels->name;

                        echo '<style>
                .toocheke-all-posts-button {
                    margin-left: 10px;
                }
            </style>';

                        echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    $(".wrap h1").after("<a href=\'' . esc_url($all_posts_url) . '\' class=\'page-title-action toocheke-all-posts-button\'>' . esc_html($button_label) . '</a>");
                });
            </script>';
                    }
                }
            }

            public function toocheke_manga_volumes_hub_page()
            {
                ?>
                <div class="wrap">
                    <h1><?php esc_html_e('Manga Volumes Management', 'toocheke-companion'); ?></h1>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('All Manga Volumes', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('View and manage all manga volumes', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_volume')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
                        </div>
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('Add New Volume', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('Create a new manga volume', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_volume')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
                        </div>
                    </div>
                </div>
                <?php
            }

            public function toocheke_manga_chapters_hub_page()
            {
                ?>
                <div class="wrap">
                    <h1><?php esc_html_e('Manga Chapters Management', 'toocheke-companion'); ?></h1>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('All Manga Chapters', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('View and manage all manga chapters', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_chapter')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
                        </div>
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('Add New Chapter', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('Create a new manga chapter', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_chapter')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
                        </div>
                    </div>
                </div>
                <?php
            }

            public function toocheke_slides_hub_page()
            {
                ?>
                <div class="wrap">
                    <h1><?php esc_html_e('Slides', 'toocheke-companion'); ?></h1>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('All Slides', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('View and manage all slides', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=slide')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
                        </div>
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('Add New Slide', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('Create a new slide', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=slide')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
                        </div>
                    </div>
                </div>
                <?php
            }

            public function toocheke_sponsorships_hub_page()
            {
                ?>
                <div class="wrap">
                    <h1><?php esc_html_e('Sponsorships', 'toocheke-companion'); ?></h1>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:20px;">
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('All Sponsorships', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('View and manage all comic sponsorships', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=comic_sponsorship')); ?>" class="button button-primary"><?php esc_html_e('Open', 'toocheke-companion'); ?></a>
                        </div>
                        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;">
                            <h2><?php esc_html_e('Add New Sponsorship', 'toocheke-companion'); ?></h2>
                            <p><?php esc_html_e('Create a new comic sponsorship', 'toocheke-companion'); ?></p>
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=comic_sponsorship')); ?>" class="button button-primary"><?php esc_html_e('Add', 'toocheke-companion'); ?></a>
                        </div>
                    </div>
                </div>
                <?php
            }

            public function toocheke_get_paypal_currencies()
            {
                return [
                    'AUD' => esc_html__('Australian Dollar', 'toocheke-companion'),
                    'BRL' => esc_html__('Brazilian Real', 'toocheke-companion'),
                    'CAD' => esc_html__('Canadian Dollar', 'toocheke-companion'),
                    'CZK' => esc_html__('Czech Koruna', 'toocheke-companion'),
                    'DKK' => esc_html__('Danish Krone', 'toocheke-companion'),
                    'EUR' => esc_html__('Euro', 'toocheke-companion'),
                    'HKD' => esc_html__('Hong Kong Dollar', 'toocheke-companion'),
                    'HUF' => esc_html__('Hungarian Forint', 'toocheke-companion'),
                    'INR' => esc_html__('Indian Rupee', 'toocheke-companion'),
                    'ILS' => esc_html__('Israeli New Shekel', 'toocheke-companion'),
                    'JPY' => esc_html__('Japanese Yen', 'toocheke-companion'),
                    'MYR' => esc_html__('Malaysian Ringgit', 'toocheke-companion'),
                    'MXN' => esc_html__('Mexican Peso', 'toocheke-companion'),
                    'TWD' => esc_html__('New Taiwan Dollar', 'toocheke-companion'),
                    'NZD' => esc_html__('New Zealand Dollar', 'toocheke-companion'),
                    'NOK' => esc_html__('Norwegian Krone', 'toocheke-companion'),
                    'PHP' => esc_html__('Philippine Peso', 'toocheke-companion'),
                    'PLN' => esc_html__('Polish Zloty', 'toocheke-companion'),
                    'GBP' => esc_html__('British Pound', 'toocheke-companion'),
                    'RUB' => esc_html__('Russian Ruble', 'toocheke-companion'),
                    'SGD' => esc_html__('Singapore Dollar', 'toocheke-companion'),
                    'SEK' => esc_html__('Swedish Krona', 'toocheke-companion'),
                    'CHF' => esc_html__('Swiss Franc', 'toocheke-companion'),
                    'THB' => esc_html__('Thai Baht', 'toocheke-companion'),
                    'USD' => esc_html__('United States Dollar', 'toocheke-companion'),
                    'AED' => esc_html__('United Arab Emirates Dirham', 'toocheke-companion'),
                    'CNY' => esc_html__('Chinese Yuan Renminbi', 'toocheke-companion'),
                    'ZAR' => esc_html__('South African Rand', 'toocheke-companion'),
                ];
            }

            public function toocheke_get_currencies()
            {
                $currencies = [
                    'AED' => '&#x62f;.&#x625;',
                    'AFN' => '&#x60b;',
                    'ALL' => 'L',
                    'AMD' => 'AMD',
                    'ANG' => '&fnof;',
                    'AOA' => 'Kz',
                    'ARS' => '&#36;',
                    'AUD' => '&#36;',
                    'AWG' => '&fnof;',
                    'AZN' => 'AZN',
                    'BAM' => 'KM',
                    'BBD' => '&#36;',
                    'BDT' => '&#2547;&nbsp;',
                    'BGN' => '&#1083;&#1074;.',
                    'BIF' => 'Fr',
                    'BMD' => '&#36;',
                    'BND' => '&#36;',
                    'BOB' => 'Bs.',
                    'BRL' => '&#82;&#36;',
                    'BSD' => '&#36;',
                    'BWP' => 'P',
                    'BYR' => 'Br',
                    'BZD' => '&#36;',
                    'CAD' => '&#36;',
                    'CDF' => 'Fr',
                    'CHF' => '&#67;&#72;&#70;',
                    'CLP' => '&#36;',
                    'CNY' => '&yen;',
                    'COP' => '&#36;',
                    'CRC' => '&#x20a1;',
                    'CVE' => '&#36;',
                    'CZK' => '&#75;&#269;',
                    'DJF' => 'Fr',
                    'DKK' => 'DKK',
                    'DOP' => 'RD&#36;',
                    'DZD' => '&#x62f;.&#x62c;',
                    'EGP' => 'EGP',
                    'ETB' => 'Br',
                    'EUR' => '&euro;',
                    'FJD' => '&#36;',
                    'FKP' => '&pound;',
                    'GBP' => '&pound;',
                    'GEL' => '&#x10da;',
                    'GIP' => '&pound;',
                    'GMD' => 'D',
                    'GNF' => 'Fr',
                    'GTQ' => 'Q',
                    'GYD' => '&#36;',
                    'HKD' => '&#36;',
                    'HNL' => 'L',
                    'HRK' => 'Kn',
                    'HTG' => 'G',
                    'HUF' => '&#70;&#116;',
                    'IDR' => 'Rp',
                    'ILS' => '&#8362;',
                    'INR' => '&#8377;',
                    'ISK' => 'Kr.',
                    'JMD' => '&#36;',
                    'JPY' => '&yen;',
                    'KES' => 'KSh',
                    'KGS' => '&#x43b;&#x432;',
                    'KHR' => '&#x17db;',
                    'KMF' => 'Fr',
                    'KRW' => '&#8361;',
                    'KYD' => '&#36;',
                    'KZT' => 'KZT',
                    'LAK' => '&#8365;',
                    'LBP' => '&#x644;.&#x644;',
                    'LKR' => '&#xdbb;&#xdd4;',
                    'LRD' => '&#36;',
                    'LSL' => 'L',
                    'MAD' => '&#x62f;. &#x645;.',
                    'MDL' => 'L',
                    'MGA' => 'Ar',
                    'MKD' => '&#x434;&#x435;&#x43d;',
                    'MMK' => 'Ks',
                    'MNT' => '&#x20ae;',
                    'MOP' => 'P',
                    'MRO' => 'UM',
                    'MUR' => '&#x20a8;',
                    'MVR' => '.&#x783;',
                    'MWK' => 'MK',
                    'MXN' => 'MXN',
                    'MYR' => '&#82;&#77;',
                    'MZN' => 'MT',
                    'NAD' => '&#36;',
                    'NGN' => '&#8358;',
                    'NIO' => 'C&#36;',
                    'NOK' => '&#107;&#114;',
                    'NPR' => '&#8360;',
                    'NZD' => '&#36;',
                    'PAB' => 'B/.',
                    'PEN' => 'S/.',
                    'PGK' => 'K',
                    'PHP' => '&#8369;',
                    'PKR' => '&#8360;',
                    'PLN' => '&#122;&#322;',
                    'PYG' => '&#8370;',
                    'QAR' => '&#x631;.&#x642;',
                    'RON' => 'lei',
                    'RSD' => '&#x434;&#x438;&#x43d;.',
                    'RUB' => '&#8381;',
                    'RWF' => 'Fr',
                    'SAR' => '&#x631;.&#x633;',
                    'SBD' => '&#36;',
                    'SCR' => '&#x20a8;',
                    'SEK' => '&#107;&#114;',
                    'SGD' => 'S&#36;',
                    'SHP' => '&pound;',
                    'SLL' => 'Le',
                    'SOS' => 'Sh',
                    'SRD' => '&#36;',
                    'STD' => 'Db',
                    'SZL' => 'L',
                    'THB' => '&#3647;',
                    'TJS' => '&#x405;&#x41c;',
                    'TOP' => 'T&#36;',
                    'TRY' => '&#8378;',
                    'TTD' => '&#36;',
                    'TWD' => '&#78;&#84;&#36;',
                    'TZS' => 'Sh',
                    'UAH' => '&#8372;',
                    'UGX' => 'UGX',
                    'USD' => '&#36;',
                    'UYU' => '&#36;',
                    'UZS' => 'UZS',
                    'VND' => '&#8363;',
                    'VUV' => 'Vt',
                    'WST' => 'T',
                    'XAF' => 'Fr',
                    'XCD' => '&#36;',
                    'XOF' => 'Fr',
                    'XPF' => 'Fr',
                    'YER' => '&#xfdfc;',
                    'ZAR' => '&#82;',
                    'ZMW' => 'ZK',
                ];

                $currencies = apply_filters('toocheke_get_currencies', $currencies);

                return $currencies;
            }

            public function toocheke_get_currency_symbol($currency = '')
            {

                if (! $currency) {

                    // If no currency is passed then default it to USD.
                    $currency = 'USD';
                }

                $currency   = strtoupper($currency);
                $currencies = $this->toocheke_get_currencies();

                $symbols = apply_filters('toocheke_currency_symbols', $currencies);

                $currency_symbol = isset($symbols[$currency]) ? $symbols[$currency] : '';

                return apply_filters('toocheke_currency_symbol', $currency_symbol, $currency);
            }

            public function toocheke_companion_upgrade_check(){
                 $installed_version = get_option('toocheke_companion_version');

                // Only run when updating from a previous version to 1.190+
                if (version_compare($installed_version, '1.190', '<')) {

                    // Set the new option ONLY if it doesn't exist already
                    if (get_option('toocheke-global-buy-comic') === false) {
                        update_option('toocheke-global-buy-comic', 1);
                    }

                    // Store new version so this never runs again
                    update_option('toocheke_companion_version', TOOCHEKE_COMPANION_VERSION);
                }
            }

private function toocheke_is_notice_dismissed($notice_id)
{
    $user_id = get_current_user_id();
    if (! $user_id) {
        return false;
    }
    $dismissed = get_user_meta($user_id, 'toocheke_dismissed_notices', true);
    return is_array($dismissed) && in_array($notice_id, $dismissed, true);
}

private function toocheke_render_dismissible_info($notice_id, $html_content)
{
    if ($this->toocheke_is_notice_dismissed($notice_id)) {
        return;
    }
    echo '<div class="toocheke-info-notice" data-notice-id="' . esc_attr($notice_id) . '" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:8px;margin-bottom:10px;">';
    echo $html_content;
    echo '<button type="button" class="toocheke-dismiss-notice" data-notice-id="' . esc_attr($notice_id) . '">';
    echo '<span class="screen-reader-text">' . esc_html__('Dismiss this tip', 'toocheke-companion') . '</span>';
    echo '</button>';
    echo '</div>';
}

public function toocheke_dismiss_notice_handler()
{
    check_ajax_referer('toocheke_dismiss_notice', 'nonce');

    if (! current_user_can('edit_posts')) {
        wp_send_json_error();
    }

    $notice_id = isset($_POST['notice_id']) ? sanitize_key($_POST['notice_id']) : '';
    if (! $notice_id) {
        wp_send_json_error();
    }

    $user_id   = get_current_user_id();
    $dismissed = get_user_meta($user_id, 'toocheke_dismissed_notices', true);
    if (! is_array($dismissed)) {
        $dismissed = [];
    }
    if (! in_array($notice_id, $dismissed, true)) {
        $dismissed[] = $notice_id;
        update_user_meta($user_id, 'toocheke_dismissed_notices', $dismissed);
    }

    wp_send_json_success();
}

}
