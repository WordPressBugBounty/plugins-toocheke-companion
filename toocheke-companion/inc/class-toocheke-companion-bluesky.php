<?php
/**
 * Toocheke Companion — Bluesky auto-posting.
 *
 * Shares "comic" and "manga_chapter" posts to Bluesky, either the moment
 * they're published or, optionally, at random from the back-catalogue on a
 * recurring schedule so nothing repeats until the whole archive has been
 * cycled through once.
 *
 * DESIGN NOTES (read this before extending):
 *
 * - The publish-time trigger is deliberately split across two hooks:
 *   transition_post_status decides WHETHER a post should go to Bluesky,
 *   the generic save_post hook (at priority 999) actually POSTS it. This
 *   is not incidental — see toocheke_bluesky_maybe_post_on_publish()'s
 *   docblock. transition_post_status fires before any metabox has saved
 *   its fields to the database, so building the Bluesky message that
 *   early would silently use stale (often empty) data for anything the
 *   author just typed in on the same Publish click. The posting hook
 *   specifically targets the *generic* save_post, not save_post_{type} —
 *   WordPress fires save_post_{type} BEFORE the generic save_post, and
 *   this plugin's own field-save handlers (transcript, hovertext, blog
 *   post editor, etc.) are all hooked to the generic one, so posting from
 *   save_post_{type} would still run too early.
 *
 * - All raw Bluesky/AT-Protocol HTTP calls are isolated to exactly three
 *   functions: toocheke_bluesky_authenticate(), toocheke_bluesky_upload_image(),
 *   and toocheke_bluesky_create_record(). If Bluesky ever changes its API,
 *   those are the only three places that should need touching — everything
 *   else in this file only deals with WordPress data and hands a finished
 *   "record" array to toocheke_bluesky_create_record().
 *
 * - There is exactly ONE post meta flag, `toocheke_bluesky_posted`, shared by
 *   both the "post on publish" feature and the "random archive repost"
 *   feature. Once a comic/chapter has been sent to Bluesky once (successfully
 *   or not — see below), it is never sent again until the whole archive pool
 *   for its post type is deliberately reset (see toocheke_bluesky_reset_posted_flags()).
 *
 * - A failed post attempt is logged (see toocheke_bluesky_log_error()) but is
 *   NOT retried automatically — it is still marked as "posted" so it can
 *   never silently repost later. This is deliberate: it keeps the feature
 *   simple and avoids ever double-posting the same thing.
 *
 * - There is no dashboard, activity log, or per-post attempt counter by
 *   design. The only persistent "log" is a single capped array of error
 *   strings (toocheke-bluesky-errors) shown as one cumulative, dismissible,
 *   site-wide admin notice.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (! defined('ABSPATH')) { exit; }

// Every Bluesky/AT-Protocol endpoint used by this file is built from this one
// constant. If Bluesky changes its API host, this is the only line to edit.
if (! defined('TOOCHEKE_BLUESKY_API_BASE')) {
    define('TOOCHEKE_BLUESKY_API_BASE', 'https://bsky.social/xrpc/');
}

trait Toocheke_Companion_Bluesky
{
    /* =========================================================================
       HOOK REGISTRATION
       Everything this feature needs is wired up from this single method,
       called once from init() in toocheke-companion.php. Keeping every hook
       registration in one place (rather than scattered add_action calls in
       the main plugin file) keeps this feature self-contained and easy to
       find/extend later.
    ========================================================================= */

    public function toocheke_bluesky_register_hooks()
    {
        // Settings tab (fields are only registered when that tab is active;
        // see the 'bluesky_options' case in toocheke_init_option_fields()).
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'toocheke_bluesky_enqueue_admin_assets']);
            add_action('wp_ajax_toocheke_bluesky_test_connection', [$this, 'toocheke_bluesky_ajax_test_connection']);
        }

        // "Post to Bluesky" metabox checkbox (manual-publish path only).
        if (is_admin()) {
            add_action('admin_init', [$this, 'toocheke_bluesky_add_publish_checkbox_metabox_comic']);
            add_action('admin_init', [$this, 'toocheke_bluesky_add_publish_checkbox_metabox_manga']);
        }

        // The actual "share to Bluesky" trigger. Split across two hooks —
        // see toocheke_bluesky_maybe_post_on_publish()'s docblock for why:
        // transition_post_status decides whether a post should be shared,
        // but the actual posting happens later, on the generic 'save_post'
        // hook at a deliberately high priority (999) — every one of this
        // plugin's own field-save handlers (transcript, hovertext, blog
        // post editor, etc.) is hooked to plain 'save_post' at the default
        // priority, and WordPress fires save_post_{type} BEFORE the generic
        // save_post, not after, so hooking the type-specific variant here
        // would still run too early. Priority 999 on the generic hook is
        // what actually guarantees we run last, after all of them.
        add_action('transition_post_status', [$this, 'toocheke_bluesky_maybe_post_on_publish'], 10, 3);
        add_action('save_post', [$this, 'toocheke_bluesky_maybe_post_after_save'], 999, 3);

        // Random archive re-posting: cron registration + the cron job itself.
        add_filter('cron_schedules', [$this, 'toocheke_bluesky_register_cron_interval']);
        add_action('init', [$this, 'toocheke_bluesky_maybe_schedule_cron']);
        add_action('toocheke_bluesky_random_post_cron', [$this, 'toocheke_bluesky_run_random_post']);
        add_action('update_option_toocheke-bluesky-random-frequency-number', [$this, 'toocheke_bluesky_reschedule_cron_on_settings_change']);
        add_action('update_option_toocheke-bluesky-random-frequency-unit', [$this, 'toocheke_bluesky_reschedule_cron_on_settings_change']);

        // The cumulative, site-wide error notice.
        if (is_admin()) {
            add_action('admin_notices', [$this, 'toocheke_bluesky_admin_notice_errors']);
            add_action('admin_post_toocheke_bluesky_clear_errors', [$this, 'toocheke_bluesky_handle_clear_errors']);
            add_action('admin_post_toocheke_bluesky_republish', [$this, 'toocheke_bluesky_handle_republish']);
        }

        // The one-time green "Successfully posted to Bluesky" notice shown
        // after a manual (checkbox or button) post — see
        // toocheke_bluesky_set_success_notice() for how it's set. Its
        // orange counterpart warns when a checkbox click was a no-op
        // because the post was already shared before.
        if (is_admin()) {
            add_action('admin_notices', [$this, 'toocheke_bluesky_show_success_notice']);
            add_action('admin_notices', [$this, 'toocheke_bluesky_show_already_posted_notice']);
        }
    }

    /* =========================================================================
       SETTINGS TAB
       Registration is called from the 'bluesky_options' case inside
       toocheke_init_option_fields() in class-toocheke-companion-settings-page.php.
       Everything else related to this tab (field renderers, messages) lives
       here so the feature stays self-contained.
    ========================================================================= */

    public function toocheke_bluesky_register_settings_fields()
    {
        // --- Connection ---
        add_settings_section('toocheke_bluesky_connection_section', 'Bluesky Connection', [$this, 'toocheke_bluesky_connection_section_message'], 'toocheke-options-page');

        add_settings_field('toocheke-bluesky-handle', 'Bluesky Handle', [$this, 'toocheke_bluesky_handle_field'], 'toocheke-options-page', 'toocheke_bluesky_connection_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-handle', ['sanitize_callback' => 'sanitize_text_field']);

        add_settings_field('toocheke-bluesky-app-password', 'App Password', [$this, 'toocheke_bluesky_app_password_field'], 'toocheke-options-page', 'toocheke_bluesky_connection_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-app-password', ['sanitize_callback' => 'sanitize_text_field']);

        // --- Automatic posting on publish ---
        add_settings_section('toocheke_bluesky_posting_section', 'Automatic Posting', [$this, 'toocheke_bluesky_posting_section_message'], 'toocheke-options-page');

        add_settings_field('toocheke-bluesky-enable-comics', 'Post comics to Bluesky?', [$this, 'toocheke_bluesky_enable_comics_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_posting_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-enable-comics');

        add_settings_field('toocheke-bluesky-enable-manga-chapters', 'Post manga chapters to Bluesky?', [$this, 'toocheke_bluesky_enable_manga_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_posting_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-enable-manga-chapters');

        // --- Post format ---
        add_settings_section('toocheke_bluesky_format_section', 'Post Format', [$this, 'toocheke_bluesky_format_section_message'], 'toocheke-options-page');

        add_settings_field('toocheke-bluesky-post-format', 'How should posts appear on Bluesky?', [$this, 'toocheke_bluesky_post_format_radio'], 'toocheke-options-page', 'toocheke_bluesky_format_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-post-format');

        add_settings_field('toocheke-bluesky-message-template', 'Message Template', [$this, 'toocheke_bluesky_message_template_field'], 'toocheke-options-page', 'toocheke_bluesky_format_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-message-template', ['sanitize_callback' => 'sanitize_textarea_field']);

        add_settings_field('toocheke-bluesky-card-caption', 'Card Caption', [$this, 'toocheke_bluesky_card_caption_field'], 'toocheke-options-page', 'toocheke_bluesky_format_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-card-caption', ['sanitize_callback' => 'sanitize_textarea_field']);

        // --- Random archive posting ---
        add_settings_section('toocheke_bluesky_random_section', 'Random Archive Posting', [$this, 'toocheke_bluesky_random_section_message'], 'toocheke-options-page');

        add_settings_field('toocheke-bluesky-random-comics', 'Randomly re-post comics from the archive?', [$this, 'toocheke_bluesky_random_comics_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_random_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-random-comics');

        add_settings_field('toocheke-bluesky-random-manga-chapters', 'Randomly re-post manga chapters from the archive?', [$this, 'toocheke_bluesky_random_manga_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_random_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-random-manga-chapters');

        add_settings_field('toocheke-bluesky-random-frequency', 'Post every...', [$this, 'toocheke_bluesky_random_frequency_field'], 'toocheke-options-page', 'toocheke_bluesky_random_section');
        register_setting('toocheke-settings', 'toocheke-bluesky-random-frequency-number');
        register_setting('toocheke-settings', 'toocheke-bluesky-random-frequency-unit');
    }

    public function toocheke_bluesky_connection_section_message()
    {
        echo '<p>' . esc_html__('Connect a Bluesky account so comics and manga chapters can be shared automatically.', 'toocheke-companion') . '</p>';
    }

    public function toocheke_bluesky_handle_field()
    {
        $value = get_option('toocheke-bluesky-handle', '');
        printf(
            '<input type="text" id="toocheke-bluesky-handle" name="toocheke-bluesky-handle" value="%s" class="regular-text" placeholder="yourname.bsky.social" />',
            esc_attr($value)
        );
    }

    public function toocheke_bluesky_app_password_field()
    {
        $handle       = get_option('toocheke-bluesky-handle', '');
        $app_password = get_option('toocheke-bluesky-app-password', '');
        ?>
        <input type="text" id="toocheke-bluesky-app-password" name="toocheke-bluesky-app-password" value="<?php echo esc_attr($app_password); ?>" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php
            printf(
                wp_kses(
                    /* translators: %s: URL to Bluesky's app password settings page */
                    __('Enter your Bluesky App Password here. <strong>Note, this is not your Bluesky password!</strong> Click <a href="%s" target="_blank" rel="noopener noreferrer">here</a> to create your password.', 'toocheke-companion'),
                    ['strong' => [], 'a' => ['href' => [], 'target' => [], 'rel' => []]]
                ),
                'https://bsky.app/settings/app-passwords'
            );
            ?>
        </p>
        <?php if (empty($handle) || empty($app_password)): ?>
            <div class="notice notice-warning inline" style="margin: 10px 0;">
                <p>
                    <?php
                    printf(
                        wp_kses(
                            __('Bluesky is not connected yet. Add your handle and app password above — you can create an app password <a href="%s" target="_blank" rel="noopener noreferrer">here</a>.', 'toocheke-companion'),
                            ['a' => ['href' => [], 'target' => [], 'rel' => []]]
                        ),
                        'https://bsky.app/settings/app-passwords'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>
        <p>
            <button type="button" class="button" id="toocheke-bluesky-test-connection"><?php esc_html_e('Test Connection', 'toocheke-companion'); ?></button>
            <span id="toocheke-bluesky-test-connection-result" style="margin-left:8px;"></span>
        </p>
        <?php
    }

    public function toocheke_bluesky_posting_section_message()
    {
        echo '<p>' . esc_html__('When enabled, scheduled comic/manga chapter posts are shared to Bluesky automatically the moment they go live. A "Post to Bluesky" checkbox also appears on the edit screen for posts published manually — that checkbox is the only way a manually-published post gets shared. Posting to Bluesky requires a featured image; posts without one are never shared.', 'toocheke-companion') . '</p>';
    }

    public function toocheke_bluesky_enable_comics_checkbox()
    {
        $this->toocheke_render_checkbox_field('toocheke-bluesky-enable-comics', false);
    }

    public function toocheke_bluesky_enable_manga_checkbox()
    {
        $this->toocheke_render_checkbox_field('toocheke-bluesky-enable-manga-chapters', false);
    }

    public function toocheke_bluesky_format_section_message()
    {
        echo '<p>' . esc_html__('Choose how comics/manga chapters posts look on Bluesky. This applies to both post types and to random archive re-posts.', 'toocheke-companion') . '</p>';
    }

    public function toocheke_bluesky_post_format_radio()
    {
        $value = get_option('toocheke-bluesky-post-format', 'text_image');
        ?>
        <label style="display:block; margin-bottom:8px;">
            <input type="radio" name="toocheke-bluesky-post-format" value="card" id="toocheke-bluesky-format-card" <?php checked($value, 'card'); ?> />
            <?php esc_html_e('Link card (title, description, and image inside a preview card)', 'toocheke-companion'); ?>
        </label>
        <label style="display:block;">
            <input type="radio" name="toocheke-bluesky-post-format" value="text_image" id="toocheke-bluesky-format-text-image" <?php checked($value, 'text_image'); ?> />
            <?php esc_html_e('Text post with an embedded image and a link to the post', 'toocheke-companion'); ?>
        </label>
        <?php
    }

    public function toocheke_bluesky_card_caption_field()
    {
        $value = get_option('toocheke-bluesky-card-caption', 'New page is up! %%TITLE%%');
        // Note: no inline display:none based on the saved format option — see
        // the comment in toocheke_bluesky_random_frequency_field() for why
        // this row's visibility is left entirely to JS toggling the parent <tr>.
        ?>
        <div id="toocheke-bluesky-card-caption-row">
            <textarea name="toocheke-bluesky-card-caption" id="toocheke-bluesky-card-caption" rows="2" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description">
                <?php
                echo wp_kses(
                    __('A short caption shown above the link card. Available placeholders: <strong>%%TITLE%%</strong>, <strong>%%SERIES_TITLE%%</strong>, <strong>%%MANGA_SERIES_TITLE%%</strong>, <strong>%%MANGA_VOLUME_TITLE%%</strong>, <strong>%%EXCERPT%%</strong>, <strong>%%BLOG_POST%%</strong>, <strong>%%NOTES%%</strong>, <strong>%%CHARACTERS%%</strong>, <strong>%%LOCATIONS%%</strong>, <strong>%%TAGS%%</strong>, <strong>%%CHAPTER%%</strong>, and <strong>%%COLLECTIONS%%</strong>. There\'s no %%URL%% here since the card itself already links to the post. Comic-only placeholders resolve to nothing on a Manga Chapter post, and Manga-only placeholders resolve to nothing on a Comic post, so it\'s safe to use the same caption for both.', 'toocheke-companion'),
                    ['strong' => []]
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function toocheke_bluesky_message_template_field()
    {
        $value = get_option('toocheke-bluesky-message-template', "New page is up!\n\n%%TITLE%%\n%%URL%%");
        // Note: no inline display:none here based on the saved format option —
        // see the comment in toocheke_bluesky_random_frequency_field() for why
        // this row's visibility is left entirely to JS toggling the parent <tr>.
        ?>
        <div id="toocheke-bluesky-template-row">
            <textarea name="toocheke-bluesky-message-template" id="toocheke-bluesky-message-template" rows="4" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description">
                <?php
                echo wp_kses(
                    __('Available placeholders: <strong>%%TITLE%%</strong>, <strong>%%URL%%</strong>, <strong>%%SERIES_TITLE%%</strong>, <strong>%%MANGA_SERIES_TITLE%%</strong>, <strong>%%MANGA_VOLUME_TITLE%%</strong>, <strong>%%EXCERPT%%</strong>, <strong>%%BLOG_POST%%</strong>, <strong>%%NOTES%%</strong>, <strong>%%CHARACTERS%%</strong>, <strong>%%LOCATIONS%%</strong>, <strong>%%TAGS%%</strong>, <strong>%%CHAPTER%%</strong>, and <strong>%%COLLECTIONS%%</strong>. Comic-only placeholders (Excerpt, Blog Post, Series Title, Characters, Locations, Tags, Chapter, Collections) resolve to nothing on a Manga Chapter post, and Manga-only placeholders (Notes, Manga Series Title, Manga Volume Title) resolve to nothing on a Comic post, so it\'s safe to use the same template for both.', 'toocheke-companion'),
                    ['strong' => []]
                );
                ?>
                <?php esc_html_e('Bluesky posts are limited to 300 characters, and the full link always counts toward that limit (Bluesky does not shorten URLs). The counter below is a guide only — the final length depends on each post\'s actual title, which varies per post; if a real post would go over the limit its title is shortened automatically so the post always still goes through.', 'toocheke-companion'); ?>
            </p>
            <p><span id="toocheke-bluesky-char-counter"></span></p>
        </div>
        <?php
    }

    public function toocheke_bluesky_random_section_message()
    {
        echo '<p>' . esc_html__('Optionally re-share older comics/manga chapters from the archive at a set interval. Nothing repeats until every eligible post has been shared once, at which point the archive loops back to the start automatically.', 'toocheke-companion') . '</p>';

        $comic_reset = get_option('toocheke-bluesky-last-reset-comic');
        $manga_reset = get_option('toocheke-bluesky-last-reset-manga');

        if ($comic_reset) {
            printf('<p><em>%s</em></p>', esc_html(sprintf(
                /* translators: %s: date/time */
                __('Comic archive last looped back to the start on %s.', 'toocheke-companion'),
                $comic_reset
            )));
        }
        if ($manga_reset) {
            printf('<p><em>%s</em></p>', esc_html(sprintf(
                /* translators: %s: date/time */
                __('Manga chapter archive last looped back to the start on %s.', 'toocheke-companion'),
                $manga_reset
            )));
        }
    }

    public function toocheke_bluesky_random_comics_checkbox()
    {
        $this->toocheke_render_checkbox_field('toocheke-bluesky-random-comics', false);
    }

    public function toocheke_bluesky_random_manga_checkbox()
    {
        $this->toocheke_render_checkbox_field('toocheke-bluesky-random-manga-chapters', false);
    }

    public function toocheke_bluesky_random_frequency_field()
    {
        $number = get_option('toocheke-bluesky-random-frequency-number', 6);
        $unit   = get_option('toocheke-bluesky-random-frequency-unit', 'hours');
        // Note: no inline display:none here based on the saved option value —
        // visibility of this whole field's row is controlled entirely by JS
        // (see toggleFrequencyRow() in bluesky-admin.js), which toggles the
        // parent <tr>. Having both a JS-controlled row AND a PHP-computed
        // inline style on this div caused them to get out of sync: checking
        // the box live (before saving) revealed the row but left this div's
        // stale "display:none" from the last-saved state in place, since a
        // child's own inline style always overrides its parent being shown.
        ?>
        <div id="toocheke-bluesky-frequency-row">
            <input type="number" name="toocheke-bluesky-random-frequency-number" min="1" step="1" value="<?php echo esc_attr($number); ?>" style="width:80px;" />
            <select name="toocheke-bluesky-random-frequency-unit">
                <option value="hours" <?php selected($unit, 'hours'); ?>><?php esc_html_e('Hours', 'toocheke-companion'); ?></option>
                <option value="days" <?php selected($unit, 'days'); ?>><?php esc_html_e('Days', 'toocheke-companion'); ?></option>
                <option value="weeks" <?php selected($unit, 'weeks'); ?>><?php esc_html_e('Weeks', 'toocheke-companion'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How often a random archive post is shared. This one schedule applies to both comics and manga chapters (they alternate, when both are enabled).', 'toocheke-companion'); ?></p>
        </div>
        <?php
    }

    /* =========================================================================
       ADMIN ASSETS (character counter, show/hide toggles, Test Connection)
    ========================================================================= */

    public function toocheke_bluesky_enqueue_admin_assets()
    {
        if (empty($_GET['page']) || 'toocheke-options-page' !== $_GET['page']) {
            return;
        }
        if (empty($_GET['tab']) || 'bluesky_options' !== $_GET['tab']) {
            return;
        }

        // filemtime() rather than the static plugin version — see the
        // matching comment on toocheke_enqueue_options_nav_assets() in
        // class-toocheke-companion-settings-page.php for why.
        $js_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/bluesky-admin.js';

        wp_enqueue_script(
            'toocheke-bluesky-admin',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'js/bluesky-admin.js',
            ['jquery'],
            file_exists($js_path) ? filemtime($js_path) : TOOCHEKE_COMPANION_VERSION,
            true
        );
        wp_localize_script('toocheke-bluesky-admin', 'toochekeBluesky', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('toocheke_bluesky_test_connection'),
        ]);
    }

    public function toocheke_bluesky_ajax_test_connection()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'toocheke-companion')], 403);
        }
        check_ajax_referer('toocheke_bluesky_test_connection', 'nonce');

        $handle       = isset($_POST['handle']) ? sanitize_text_field(wp_unslash($_POST['handle'])) : '';
        $app_password = isset($_POST['app_password']) ? sanitize_text_field(wp_unslash($_POST['app_password'])) : '';

        if (empty($handle) || empty($app_password)) {
            wp_send_json_error(['message' => __('Please enter both a handle and an app password.', 'toocheke-companion')]);
        }

        $response = wp_remote_post(TOOCHEKE_BLUESKY_API_BASE . 'com.atproto.server.createSession', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'identifier' => $handle,
                'password'   => $app_password,
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => sprintf(
                /* translators: %s: underlying error message */
                __('Connection failed: %s', 'toocheke-companion'),
                $response->get_error_message()
            )]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (200 === $code) {
            wp_send_json_success(['message' => __('Success! Toocheke Companion was able to connect to Bluesky. Please save your settings.', 'toocheke-companion')]);
        }

        if (429 === $code) {
            wp_send_json_error(['message' => __('Rate limit exceeded. Please wait a while before testing again.', 'toocheke-companion')]);
        }

        $message = $body['message'] ?? sprintf(
            /* translators: %d: HTTP status code */
            __('Connection failed (HTTP %d).', 'toocheke-companion'),
            $code
        );
        wp_send_json_error(['message' => $message]);
    }

    /* =========================================================================
       "POST TO BLUESKY" METABOX (manual-publish path only)
    ========================================================================= */

    public function toocheke_bluesky_add_publish_checkbox_metabox_comic()
    {
        if (! get_option('toocheke-bluesky-enable-comics')) {
            return;
        }
        add_meta_box('toocheke-bluesky-publish-now', __('Bluesky', 'toocheke-companion'), [$this, 'toocheke_bluesky_publish_checkbox_display'], 'comic', 'side', 'high');
    }

    public function toocheke_bluesky_add_publish_checkbox_metabox_manga()
    {
        if (! get_option('toocheke-bluesky-enable-manga-chapters')) {
            return;
        }
        add_meta_box('toocheke-bluesky-publish-now', __('Bluesky', 'toocheke-companion'), [$this, 'toocheke_bluesky_publish_checkbox_display'], 'manga_chapter', 'side', 'high');
    }

    public function toocheke_bluesky_publish_checkbox_display($post)
    {
        // Already published — offer a manual, deliberate "(re-)post" action
        // instead of the automatic checkbox. This is the escape hatch for
        // cases the automatic flag can't know about on its own, e.g. the
        // post was manually deleted from Bluesky and needs to go out again,
        // or it was published before a featured image was added and so was
        // originally skipped. It never fires on its own — only a direct
        // click here does.
        if ('publish' === $post->post_status) {
            $already_posted = $this->toocheke_bluesky_has_already_been_posted($post->ID);

            echo '<p>' . ($already_posted
                ? esc_html__('This has already been shared to Bluesky and will not repeat automatically.', 'toocheke-companion')
                : esc_html__('This is published but has not been shared to Bluesky yet.', 'toocheke-companion')
            ) . '</p>';

            if (! has_post_thumbnail($post->ID)) {
                echo '<p style="color:#b32d2e; font-weight:bold;">' . esc_html__('Add a featured image to be able to post this to Bluesky.', 'toocheke-companion') . '</p>';
                return;
            }

            // A plain nonce-link, not a <form> — this metabox renders
            // inside WordPress's single big #post form that wraps the
            // entire edit screen (content, Publish button, everything), and
            // nesting another <form> inside it is invalid HTML. Browsers
            // handle that by breaking the DOM around the nested form, which
            // was silently hijacking the real Publish/Update button's
            // submission. A link avoids the problem entirely — same
            // approach already used for the "Dismiss all" errors link.
            $republish_url = wp_nonce_url(
                admin_url('admin-post.php?action=toocheke_bluesky_republish&post_id=' . $post->ID),
                'toocheke_bluesky_republish_' . $post->ID
            );
            ?>
            <a href="<?php echo esc_url($republish_url); ?>" class="button">
                <?php echo $already_posted
                    ? esc_html__('Post to Bluesky Again', 'toocheke-companion')
                    : esc_html__('Post to Bluesky Now', 'toocheke-companion'); ?>
            </a>
            <?php
            return;
        }

        // Scheduled — this always posts automatically when it goes live, no
        // checkbox needed, so there's nothing to decide here.
        if ('future' === $post->post_status) {
            echo '<p>' . esc_html__('This is scheduled — it will be shared to Bluesky automatically when it goes live.', 'toocheke-companion') . '</p>';
            return;
        }

        wp_nonce_field('toocheke_bluesky_publish_now', 'toocheke_bluesky_publish_now_nonce');
        ?>
        <label>
            <input type="checkbox" name="toocheke_bluesky_publish_now" value="1" />
            <?php esc_html_e('Post to Bluesky Now', 'toocheke-companion'); ?>
        </label>
        <div style="margin-top:10px;">
            <?php
            $this->toocheke_render_dismissible_info(
                'bluesky_publish_now',
                esc_html__('Checking this box is the only way a manually-published (non-scheduled) post gets shared on Bluesky. This option has no effect on scheduled posts — those are always shared automatically when they go live.', 'toocheke-companion')
            );
            ?>
        </div>
        <?php
    }

    /**
     * Handles a manual "Post to Bluesky (Again)" button click from the
     * metabox above. Unlike every other posting path in this file, this one
     * is a deliberate, explicit action with no automatic trigger of its own
     * — it exists specifically so a post whose "posted" flag no longer
     * matches reality (e.g. it was deleted from Bluesky directly) can be
     * sent again without needing to unpublish/republish the WordPress post.
     */
    public function toocheke_bluesky_handle_republish()
    {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (! $post_id || ! current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('Unauthorized', 'toocheke-companion'));
        }
        check_admin_referer('toocheke_bluesky_republish_' . $post_id);

        $post = get_post($post_id);

        if ($post && in_array($post->post_type, ['comic', 'manga_chapter'], true) && has_post_thumbnail($post_id)) {
            $uri = $this->toocheke_bluesky_post_to_bluesky($post_id, $post->post_type);
            $this->toocheke_bluesky_mark_as_posted($post_id, is_string($uri) ? $uri : '');
            if (is_string($uri)) {
                $this->toocheke_bluesky_set_success_notice($post_id);
            }
        }

        // Built directly rather than via get_edit_post_link(), which
        // performs its own internal capability re-check and silently
        // returns an empty string if that fails for any reason — sending
        // wp_safe_redirect() an empty location falls back to admin_url(),
        // which is what was actually landing people on the plain Posts
        // list screen instead of back on this specific post.
        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    /**
     * Flags a one-time, per-user, per-post success notice to show the next
     * time this post's edit screen loads (see toocheke_bluesky_show_success_notice()).
     * A short-lived transient rather than a query-string flag, since the
     * redirect after a normal WordPress "Publish" click is controlled by
     * WordPress core, not by this plugin, so there's no query arg of ours to
     * read on that particular page load.
     */
    private function toocheke_bluesky_set_success_notice($post_id)
    {
        set_transient('toocheke_bluesky_success_' . get_current_user_id() . '_' . $post_id, 1, MINUTE_IN_SECONDS);
    }

    /**
     * Shows a standard green "Successfully posted to Bluesky" admin notice
     * once, right after either: (a) a manual publish with the "Post to
     * Bluesky Now" checkbox checked, or (b) a manual "Post to Bluesky
     * (Again)" button click. Scheduled/automatic posts don't set this flag
     * (see toocheke_bluesky_maybe_post_on_publish()), since nobody is
     * necessarily looking at the edit screen at the moment those fire.
     */
    public function toocheke_bluesky_show_success_notice()
    {
        if (empty($_GET['post'])) {
            return;
        }

        $post_id = absint($_GET['post']);
        $key     = 'toocheke_bluesky_success_' . get_current_user_id() . '_' . $post_id;

        if (! get_transient($key)) {
            return;
        }
        delete_transient($key);

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Successfully posted to Bluesky.', 'toocheke-companion') . '</p></div>';
    }

    /**
     * Companion to toocheke_bluesky_set_success_notice() for the opposite
     * case: the "Post to Bluesky Now" checkbox was checked on a manual
     * publish, but nothing was actually sent because this exact post had
     * already been shared before (e.g. unpublish -> republish). Without
     * this, an author checking the box would have no way of knowing their
     * checkbox click was silently a no-op.
     */
    private function toocheke_bluesky_set_already_posted_notice($post_id)
    {
        set_transient('toocheke_bluesky_already_posted_' . get_current_user_id() . '_' . $post_id, 1, MINUTE_IN_SECONDS);
    }

    public function toocheke_bluesky_show_already_posted_notice()
    {
        if (empty($_GET['post'])) {
            return;
        }

        $post_id = absint($_GET['post']);
        $key     = 'toocheke_bluesky_already_posted_' . get_current_user_id() . '_' . $post_id;

        if (! get_transient($key)) {
            return;
        }
        delete_transient($key);

        echo '<div class="notice notice-warning is-dismissible"><p>' .
            esc_html__('This was not posted to Bluesky again, because it was already shared previously. If you want to share it again, use the "Post to Bluesky Again" button in the Bluesky box.', 'toocheke-companion') .
            '</p></div>';
    }

    /* =========================================================================
       PUBLISH-TIME TRIGGER
    ========================================================================= */

    /**
     * Post IDs that toocheke_bluesky_maybe_post_on_publish() has determined
     * should be posted, along with whether that determination came from the
     * scheduled/automatic path or the manual checkbox path. Populated on
     * transition_post_status (which is the hook that actually tells us
     * old/new status), consumed by toocheke_bluesky_maybe_post_after_save()
     * on the generic save_post hook (priority 999) — see that method's docblock for why posting
     * itself is deliberately deferred to a later hook rather than happening
     * immediately here.
     *
     * @var array<int, bool> post ID => true if this was the manual/checkbox
     *      path (false/absent = scheduled/automatic path)
     */
    private $toocheke_bluesky_pending = [];

    /**
     * Fires on every post status transition. Only acts on comic/manga_chapter
     * posts moving INTO "publish" for the first time (never on subsequent
     * saves/updates of an already-published post, which prevents any
     * possibility of a duplicate Bluesky post).
     *
     * This method only DECIDES whether a post should go to Bluesky — it
     * deliberately does not post it. transition_post_status fires quite
     * early inside wp_insert_post(), BEFORE the metabox save handlers that
     * write fields like the transcript, hovertext, or blog post content to
     * the database. Posting from here would mean building the Bluesky
     * message from whatever those fields happened to contain from a PRIOR
     * save — empty, for a field filled in for the very first time on this
     * same Publish click — even though the author just typed real content
     * into it. The actual posting happens on the generic save_post hook instead (see
     * toocheke_bluesky_maybe_post_after_save()), which fires after every
     * metabox has already saved, so the post is always built from the
     * genuinely-current, just-saved field values.
     */
    public function toocheke_bluesky_maybe_post_on_publish($new_status, $old_status, $post)
    {
        if (! in_array($post->post_type, ['comic', 'manga_chapter'], true)) {
            return;
        }
        // Only the actual transition INTO publish matters, and only once —
        // this also naturally excludes ordinary "Update" saves of a post
        // that was already published.
        if ('publish' !== $new_status || 'publish' === $old_status) {
            return;
        }
        if (! $this->toocheke_bluesky_is_type_enabled($post->post_type)) {
            return;
        }

        if ('future' === $old_status) {
            // WordPress's own scheduler (or an author promoting a scheduled
            // post to publish early) — always automatic, no checkbox needed.
            $should_post  = true;
            $is_manual    = false;
        } else {
            // A manual draft/pending -> publish transition. Only proceed if
            // the author explicitly checked the box on this exact request —
            // this is what prevents multiple Bluesky posts from a
            // publish -> unpublish -> republish cycle.
            $nonce_ok = isset($_POST['toocheke_bluesky_publish_now_nonce'])
                && wp_verify_nonce($_POST['toocheke_bluesky_publish_now_nonce'], 'toocheke_bluesky_publish_now');
            $should_post = $nonce_ok && ! empty($_POST['toocheke_bluesky_publish_now']);
            $is_manual   = true;
        }

        if (! $should_post) {
            return;
        }
        if ($this->toocheke_bluesky_has_already_been_posted($post->ID)) {
            // The checkbox was checked, but this exact post was already
            // shared to Bluesky at some point before (e.g. it was
            // unpublished and republished). Silently doing nothing here
            // would leave the author thinking it went out again when it
            // didn't — so flag it, on the manual/checkbox path only (see
            // the success-notice comment above for why scheduled publishes
            // don't get this treatment).
            if ($is_manual) {
                $this->toocheke_bluesky_set_already_posted_notice($post->ID);
            }
            return;
        }
        if (! has_post_thumbnail($post->ID)) {
            return; // Hard requirement — never post without a featured image.
        }

        // Record the decision; the actual posting happens later, on the
        // generic save_post hook (priority 999), once this post's own
        // field values are guaranteed to be fully saved.
        $this->toocheke_bluesky_pending[$post->ID] = $is_manual;
    }

    /**
     * Fires on the generic 'save_post' hook at priority 999 — deliberately
     * high, so it runs after every one of this plugin's own field-save
     * handlers (transcript, hovertext, blog post editor, etc.), which are
     * all themselves hooked to plain 'save_post' at the default priority.
     * By the time this runs, any field the author just typed in is
     * guaranteed to already be in the database. Fires for every post save
     * on the site, not just comic/manga_chapter — the pending-list check
     * below is what filters that down to only the exact posts
     * toocheke_bluesky_maybe_post_on_publish() actually flagged.
     */
    public function toocheke_bluesky_maybe_post_after_save($post_id, $post, $update)
    {
        if (! array_key_exists($post_id, $this->toocheke_bluesky_pending)) {
            return;
        }

        $is_manual = $this->toocheke_bluesky_pending[$post_id];
        unset($this->toocheke_bluesky_pending[$post_id]);

        // Defensive re-checks — cheap, and guards against edge cases like
        // save_post firing more than once for the same request.
        if ($this->toocheke_bluesky_has_already_been_posted($post_id)) {
            return;
        }
        if (! has_post_thumbnail($post_id)) {
            return;
        }

        $uri = $this->toocheke_bluesky_post_to_bluesky($post_id, $post->post_type);
        // Marked as posted either way (success or failure) — a failed
        // attempt is logged, never automatically retried. See file header.
        $this->toocheke_bluesky_mark_as_posted($post_id, is_string($uri) ? $uri : '');

        // Only for the manual/checkbox path — the redirect back to this
        // exact edit screen after clicking Publish is what makes the
        // transient reliably show up on the very next page load. Scheduled
        // publishes fire via WP-Cron with nobody necessarily viewing this
        // screen at that moment, so there's nothing to show a notice for
        // there.
        if (is_string($uri) && $is_manual) {
            $this->toocheke_bluesky_set_success_notice($post_id);
        }
    }

    /* =========================================================================
       RANDOM ARCHIVE RE-POSTING (cron)
    ========================================================================= */

    public function toocheke_bluesky_register_cron_interval($schedules)
    {
        $number = max(1, (int) get_option('toocheke-bluesky-random-frequency-number', 6));
        $unit   = get_option('toocheke-bluesky-random-frequency-unit', 'hours');

        $seconds_per_unit = [
            'hours' => HOUR_IN_SECONDS,
            'days'  => DAY_IN_SECONDS,
            'weeks' => WEEK_IN_SECONDS,
        ];
        $interval = $number * ($seconds_per_unit[$unit] ?? HOUR_IN_SECONDS);

        $schedules['toocheke_bluesky_random_interval'] = [
            'interval' => $interval,
            'display'  => sprintf('Every %1$d %2$s (Toocheke Bluesky random post)', $number, $unit),
        ];

        return $schedules;
    }

    /**
     * Schedules or unschedules the random-post cron based on whether either
     * random-posting option is currently enabled. Cheap to run on every
     * 'init' since it's just two get_option() calls and, at most, a
     * wp_next_scheduled() check.
     */
    public function toocheke_bluesky_maybe_schedule_cron()
    {
        $enabled   = get_option('toocheke-bluesky-random-comics') || get_option('toocheke-bluesky-random-manga-chapters');
        $scheduled = wp_next_scheduled('toocheke_bluesky_random_post_cron');

        if ($enabled && ! $scheduled) {
            wp_schedule_event(time(), 'toocheke_bluesky_random_interval', 'toocheke_bluesky_random_post_cron');
        } elseif (! $enabled && $scheduled) {
            wp_clear_scheduled_hook('toocheke_bluesky_random_post_cron');
        }
    }

    /**
     * When the frequency number/unit changes, the already-queued next cron
     * run is still using the OLD interval (that's just how wp_schedule_event
     * works). Re-schedule immediately so a settings change takes effect on
     * the very next run rather than one run late.
     */
    public function toocheke_bluesky_reschedule_cron_on_settings_change()
    {
        if (wp_next_scheduled('toocheke_bluesky_random_post_cron')) {
            wp_clear_scheduled_hook('toocheke_bluesky_random_post_cron');
            wp_schedule_event(time(), 'toocheke_bluesky_random_interval', 'toocheke_bluesky_random_post_cron');
        }
    }

    /**
     * The cron job itself — posts exactly one random, not-yet-posted comic
     * or manga chapter. When both types are enabled, alternates (round
     * robin) between them across runs.
     */
    public function toocheke_bluesky_run_random_post()
    {
        $comics_on = get_option('toocheke-bluesky-random-comics');
        $manga_on  = get_option('toocheke-bluesky-random-manga-chapters');
        if (! $comics_on && ! $manga_on) {
            return; // Cron will be unscheduled separately; bail defensively.
        }

        $last_type = get_option('toocheke-bluesky-last-random-type', '');
        if ($comics_on && $manga_on) {
            // Round robin: try whichever type didn't run last time first.
            $order = ('comic' === $last_type) ? ['manga_chapter', 'comic'] : ['comic', 'manga_chapter'];
        } elseif ($comics_on) {
            $order = ['comic'];
        } else {
            $order = ['manga_chapter'];
        }

        foreach ($order as $post_type) {
            $post_id = $this->toocheke_bluesky_get_random_eligible_id($post_type);
            if (! $post_id) {
                continue; // Nothing postable for this type even after a reset attempt.
            }

            $uri = $this->toocheke_bluesky_post_to_bluesky($post_id, $post_type);
            $this->toocheke_bluesky_mark_as_posted($post_id, is_string($uri) ? $uri : '');
            update_option('toocheke-bluesky-last-random-type', $post_type);
            return; // One post per cron run.
        }
    }

    /**
     * Returns a random eligible post ID for the given type, automatically
     * resetting (and re-querying) if the pool has been fully exhausted.
     */
    public function toocheke_bluesky_get_random_eligible_id($post_type)
    {
        $post_id = $this->toocheke_bluesky_query_random_unposted_id($post_type);
        if ($post_id) {
            return $post_id;
        }

        // Pool is empty — either genuinely nothing eligible exists yet (e.g.
        // a brand-new site with no featured images), or every eligible post
        // has already been shared once and it's time to loop the archive.
        if (! $this->toocheke_bluesky_type_has_any_eligible_post($post_type)) {
            return false;
        }

        $this->toocheke_bluesky_reset_posted_flags($post_type);
        update_option(
            'toocheke-bluesky-last-reset-' . ('comic' === $post_type ? 'comic' : 'manga'),
            current_time('mysql')
        );

        return $this->toocheke_bluesky_query_random_unposted_id($post_type);
    }

    /**
     * Pulls just the IDs of eligible, not-yet-posted posts and picks one at
     * random in PHP — deliberately avoiding `orderby => rand` in WP_Query,
     * which forces a full, index-less table sort in MySQL and gets slower as
     * an archive grows. This ID-only approach stays fast even on very large
     * archives since cron runs are infrequent (whatever the admin sets, e.g.
     * every few hours/days) so there's no need for extra caching here.
     */
    public function toocheke_bluesky_query_random_unposted_id($post_type)
    {
        $ids = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => '_thumbnail_id', 'compare' => 'EXISTS'], // must have a featured image
                ['key' => 'toocheke_bluesky_posted', 'compare' => 'NOT EXISTS'],
            ],
        ]);

        if (empty($ids)) {
            return false;
        }

        return (int) $ids[array_rand($ids)];
    }

    /**
     * Used only to decide whether an empty result from the query above means
     * "reset the archive" or "there's nothing to post at all" (e.g. no
     * comics with featured images exist yet) — the latter should never
     * trigger a reset loop.
     */
    public function toocheke_bluesky_type_has_any_eligible_post($post_type)
    {
        $ids = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
        ]);

        return ! empty($ids);
    }

    /**
     * Bulk-clears the "posted" flags for every post of the given type in one
     * query, so the random-archive pool starts over. Mirrors the efficient
     * bulk-delete approach used elsewhere in the Toocheke ecosystem for
     * exactly this kind of "reset everything" operation.
     */
    public function toocheke_bluesky_reset_posted_flags($post_type)
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE pm FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND pm.meta_key IN ('toocheke_bluesky_posted', 'toocheke_bluesky_post_uri')",
            $post_type
        ));
    }

    /* =========================================================================
       SHARED "POSTED" STATE HELPERS
    ========================================================================= */

    public function toocheke_bluesky_is_type_enabled($post_type)
    {
        if ('comic' === $post_type) {
            return (bool) get_option('toocheke-bluesky-enable-comics');
        }
        if ('manga_chapter' === $post_type) {
            return (bool) get_option('toocheke-bluesky-enable-manga-chapters');
        }
        return false;
    }

    public function toocheke_bluesky_has_already_been_posted($post_id)
    {
        return (bool) get_post_meta($post_id, 'toocheke_bluesky_posted', true);
    }

    public function toocheke_bluesky_mark_as_posted($post_id, $uri = '')
    {
        update_post_meta($post_id, 'toocheke_bluesky_posted', 1);
        if ($uri) {
            update_post_meta($post_id, 'toocheke_bluesky_post_uri', $uri);
        }
    }

    /**
     * Permalink for a post, with a series id (?sid=) appended when the post
     * is a comic assigned to a series. Mirrors the same handling already
     * used for RSS permalinks — see toocheke_add_series_id_to_rss_permalink()
     * in class-toocheke-companion-rss-feeds.php: a comic's post_parent is
     * its series, and the front end needs that ?sid= param to render the
     * comic in the context of that series (series-scoped navigation,
     * background/branding, etc.) rather than as a standalone page. Manga
     * chapters don't use this same post_parent-as-series convention, so
     * this only applies to the 'comic' post type, exactly like the RSS
     * version.
     */
    private function toocheke_bluesky_get_post_url($post_id, $post_type)
    {
        $permalink = get_permalink($post_id);

        if ('comic' !== $post_type) {
            return $permalink;
        }

        $series_id = (int) wp_get_post_parent_id($post_id);
        if ($series_id > 0) {
            $permalink = add_query_arg('sid', $series_id, $permalink);
        }

        return $permalink;
    }

    /* =========================================================================
       CORE: BUILD + SEND ONE POST
       This is the single shared entry point used by both the publish-time
       trigger and the random-repost cron job.
    ========================================================================= */

    public function toocheke_bluesky_post_to_bluesky($post_id, $post_type)
    {
        $auth = $this->toocheke_bluesky_authenticate();
        if (is_wp_error($auth)) {
            $this->toocheke_bluesky_log_error($this->toocheke_bluesky_error_context($post_id) . $auth->get_error_message());
            return false;
        }

        $format = get_option('toocheke-bluesky-post-format', 'text_image');
        if ('card' === $format) {
            $record = $this->toocheke_bluesky_build_card_record($post_id, $post_type, $auth['token']);
        } else {
            $record = $this->toocheke_bluesky_build_text_image_record($post_id, $post_type, $auth['token']);
        }

        if (is_wp_error($record)) {
            $this->toocheke_bluesky_log_error($this->toocheke_bluesky_error_context($post_id) . $record->get_error_message());
            return false;
        }

        $uri = $this->toocheke_bluesky_create_record($record, $auth['token'], $auth['did']);
        if (is_wp_error($uri)) {
            $this->toocheke_bluesky_log_error($this->toocheke_bluesky_error_context($post_id) . $uri->get_error_message());
            return false;
        }

        return $uri;
    }

    private function toocheke_bluesky_error_context($post_id)
    {
        return sprintf('"%s" (#%d): ', get_the_title($post_id), $post_id);
    }

    /* =========================================================================
       RECORD BUILDERS (one per post format)
    ========================================================================= */

    /**
     * Builds an app.bsky.feed.post record using a link-card embed (title,
     * description, and image inside one preview card), plus a short caption
     * above the card (see toocheke_bluesky_build_card_caption_text()) so the
     * post doesn't read as an automated, caption-less link drop.
     */
    private function toocheke_bluesky_build_card_record($post_id, $post_type, $token)
    {
        $image_url = get_the_post_thumbnail_url($post_id, 'full');
        $upload    = $this->toocheke_bluesky_upload_image($image_url, $token);
        if (is_wp_error($upload)) {
            return $upload;
        }

        $caption = $this->toocheke_bluesky_build_card_caption_text($post_id, $post_type, get_the_title($post_id));

        return [
            '$type'     => 'app.bsky.feed.post',
            'text'      => $caption['text'],
            'facets'    => $caption['facets'],
            'createdAt' => gmdate('c'),
            'embed'     => [
                '$type'    => 'app.bsky.embed.external',
                'external' => [
                    'uri'         => $this->toocheke_bluesky_get_post_url($post_id, $post_type),
                    'title'       => get_the_title($post_id),
                    'description' => $this->toocheke_bluesky_get_card_description($post_id, $post_type),
                    'thumb'       => $upload['blob'],
                ],
            ],
        ];
    }

    /**
     * Builds the short caption shown above a Card-format post. Unlike the
     * Text+Image template, there's no %%URL%% placeholder — the link card
     * itself already carries the link, so repeating it in the caption text
     * would be redundant and would need its own separate facet handling for
     * no real benefit. %%URL%% is deliberately excluded from the
     * substitution map here (see toocheke_bluesky_get_template_placeholders()),
     * and any literal "%%URL%%" left in a caption (e.g. from copy-pasting
     * the Text+Image template by mistake) is stripped to an empty string
     * rather than left visible as a raw, broken-looking token in a live
     * post. Because there's no URL to protect, truncation here is a simple
     * hard cap rather than the title-shortening logic used in
     * toocheke_bluesky_build_message_text().
     *
     * Returns ['text' => ..., 'facets' => [...]] rather than a bare
     * string, so any %%CHARACTERS%%/%%LOCATIONS%%/%%TAGS%% used in the
     * caption still render as real hashtags (see
     * toocheke_bluesky_build_tag_facets()) — not just the URL format gets
     * that treatment.
     */
    private function toocheke_bluesky_build_card_caption_text($post_id, $post_type, $title)
    {
        $template = get_option('toocheke-bluesky-card-caption');
        if (empty($template)) {
            $template = '%%TITLE%%';
        }

        $resolved     = $this->toocheke_bluesky_get_template_placeholders($post_id, $post_type, $title, '');
        $placeholders = $resolved['values'];
        $hashtags     = $resolved['hashtags'];
        unset($placeholders['%%URL%%']);

        $text = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        $text = str_replace('%%URL%%', '', $text);

        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 300);
        }

        return [
            'text'   => $text,
            'facets' => $this->toocheke_bluesky_build_tag_facets($text, $hashtags),
        ];
    }

    /**
     * Builds an app.bsky.feed.post record with the featured image embedded
     * directly and a visible, clickable link to the post in the text.
     */
    private function toocheke_bluesky_build_text_image_record($post_id, $post_type, $token)
    {
        $image_url = get_the_post_thumbnail_url($post_id, 'full');
        $upload    = $this->toocheke_bluesky_upload_image($image_url, $token);
        if (is_wp_error($upload)) {
            return $upload;
        }

        $url     = $this->toocheke_bluesky_get_post_url($post_id, $post_type);
        $message = $this->toocheke_bluesky_build_message_text($post_id, $post_type, get_the_title($post_id), $url);
        $alt     = $this->toocheke_bluesky_get_alt_text($post_id, $post_type);

        // array_filter with a strict `!== null` check, not the default
        // truthy check — an empty-string alt (meaning "no hovertext/notes
        // available") is a valid, intentional value and must not be dropped.
        $image = array_filter([
            'image'       => $upload['blob'],
            'alt'         => $alt,
            'aspectRatio' => ($upload['width'] > 0 && $upload['height'] > 0) ? [
                '$type'  => 'app.bsky.embed.defs#aspectRatio',
                'width'  => $upload['width'],
                'height' => $upload['height'],
            ] : null,
        ], function ($value) {
            return null !== $value;
        });

        return [
            '$type'     => 'app.bsky.feed.post',
            'text'      => $message['text'],
            'createdAt' => gmdate('c'),
            'facets'    => $message['facets'],
            'embed'     => [
                '$type'  => 'app.bsky.embed.images',
                'images' => [$image],
            ],
        ];
    }

    /* =========================================================================
       TEXT / ALT-TEXT / DESCRIPTION LOGIC
    ========================================================================= */

    /**
     * Alt text for the Text+Image format's embedded image.
     * Comic: same fallback chain as the Card format's description, see
     * toocheke_bluesky_get_comic_fallback_text(). Manga chapter: the
     * "notes" meta field, if present, same as the Card description.
     */
    private function toocheke_bluesky_get_alt_text($post_id, $post_type)
    {
        if ('comic' === $post_type) {
            return $this->toocheke_bluesky_get_comic_fallback_text($post_id);
        }
        // manga_chapter
        return trim((string) get_post_meta($post_id, 'notes', true));
    }

    /**
     * Description for the Card format.
     * Comic: same fallback chain as the Text+Image alt text, see
     * toocheke_bluesky_get_comic_fallback_text(). Manga chapter: the "notes"
     * meta field only, or empty.
     */
    private function toocheke_bluesky_get_card_description($post_id, $post_type)
    {
        if ('manga_chapter' === $post_type) {
            return trim((string) get_post_meta($post_id, 'notes', true));
        }

        return $this->toocheke_bluesky_get_comic_fallback_text($post_id);
    }

    /**
     * Shared comic-only fallback chain, used by both the Text+Image format's
     * alt text and the Card format's description: comic-hovertext -> the
     * post excerpt (only if one was actually, manually set) ->
     * transcript meta field (truncated to 200 chars) -> the blog-post field
     * content (truncated to 200 chars) -> empty if none of those have
     * anything.
     *
     * The excerpt is read with an explicit 'raw' context — get_post_field()
     * defaults to 'display' context, which runs the value through the
     * `post_excerpt` filter. If any other plugin/theme hooks that filter,
     * it can make this look non-empty even when no excerpt was ever
     * manually typed, which would silently skip the transcript/blog-post
     * fallbacks below every single time. 'raw' reads the literal database
     * value with no filtering, which is what "if it has text" actually means.
     */
    private function toocheke_bluesky_get_comic_fallback_text($post_id)
    {
        $hovertext = trim((string) get_post_meta($post_id, 'comic-hovertext', true));
        if ('' !== $hovertext) {
            return $hovertext;
        }

        $excerpt = trim((string) get_post_field('post_excerpt', $post_id, 'raw'));
        if ('' !== $excerpt) {
            return $excerpt;
        }

        $transcript = trim(wp_strip_all_tags((string) get_post_meta($post_id, 'transcript', true)));
        if ('' !== $transcript) {
            return $this->toocheke_bluesky_truncate_plain_text($transcript, 200);
        }

        $blog_content = trim(wp_strip_all_tags((string) get_post_meta($post_id, 'comic_blog_post_editor', true)));
        if ('' !== $blog_content) {
            return $this->toocheke_bluesky_truncate_plain_text($blog_content, 200);
        }

        return '';
    }

    private function toocheke_bluesky_truncate_plain_text($text, $max_chars)
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $max_chars) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max_chars)) . '…';
    }

    /**
     * Resolves every supported %%PLACEHOLDER%% for the given post into an
     * associative array, used by both toocheke_bluesky_build_message_text()
     * (Text+Image template) and toocheke_bluesky_build_card_caption_text()
     * (Card Caption, which additionally excludes %%URL%% -- see that
     * method's own docblock for why).
     *
     * Every value here is already plain text (HTML stripped where the
     * source field can contain rich content, e.g. %%BLOG_POST%%) and
     * left untruncated -- the two callers above are what enforce
     * Bluesky's 300-character limit, once, after full substitution; this
     * method's only job is producing correct values, not managing length.
     *
     * Comic-only placeholders resolve to '' on a manga_chapter post, and
     * manga_chapter-only placeholders resolve to '' on a comic post --
     * deliberately, so a single shared template can be used across both
     * post types without ever leaving a raw, unresolved %%TOKEN%% visible
     * in a published post.
     */
    private function toocheke_bluesky_get_template_placeholders($post_id, $post_type, $title, $url)
    {
        $placeholders = [
            '%%TITLE%%' => $title,
            '%%URL%%'   => $url,
        ];

        $hashtag_names = [];

        if ('comic' === $post_type) {
            $placeholders['%%EXCERPT%%']   = trim((string) get_post_field('post_excerpt', $post_id, 'raw'));
            $placeholders['%%BLOG_POST%%'] = trim(wp_strip_all_tags((string) get_post_meta($post_id, 'comic_blog_post_editor', true)));

            // Mirrors the same post_parent-as-series relationship
            // toocheke_bluesky_get_post_url() already relies on for this
            // exact post type -- see that method's docblock.
            $parent_id                        = (int) wp_get_post_parent_id($post_id);
            $placeholders['%%SERIES_TITLE%%'] = $parent_id > 0 ? get_the_title($parent_id) : '';

            $placeholders['%%CHARACTERS%%']  = $this->toocheke_bluesky_terms_as_hashtags($post_id, 'comic_characters', $hashtag_names);
            $placeholders['%%LOCATIONS%%']   = $this->toocheke_bluesky_terms_as_hashtags($post_id, 'comic_locations', $hashtag_names);
            $placeholders['%%TAGS%%']        = $this->toocheke_bluesky_terms_as_hashtags($post_id, 'comic_tags', $hashtag_names);
            $placeholders['%%CHAPTER%%']     = $this->toocheke_bluesky_terms_as_list($post_id, 'chapters', ', ');
            $placeholders['%%COLLECTIONS%%'] = $this->toocheke_bluesky_terms_as_list($post_id, 'collections', ', ');

            $placeholders['%%NOTES%%']              = '';
            $placeholders['%%MANGA_SERIES_TITLE%%'] = '';
            $placeholders['%%MANGA_VOLUME_TITLE%%'] = '';
        } elseif ('manga_chapter' === $post_type) {
            $placeholders['%%NOTES%%'] = trim((string) get_post_meta($post_id, 'notes', true));

            // Same meta fields the manga_chapter metabox itself saves
            // (see the manga_chapter save handler in
            // class-toocheke-companion-metaboxes.php) -- 'series_id'
            // here points to the parent Manga Series, not a Series post.
            $manga_series_id                        = (int) get_post_meta($post_id, 'series_id', true);
            $placeholders['%%MANGA_SERIES_TITLE%%'] = $manga_series_id > 0 ? get_the_title($manga_series_id) : '';

            $manga_volume_id                        = (int) get_post_meta($post_id, 'volume_id', true);
            $placeholders['%%MANGA_VOLUME_TITLE%%'] = $manga_volume_id > 0 ? get_the_title($manga_volume_id) : '';

            $placeholders['%%EXCERPT%%']      = '';
            $placeholders['%%BLOG_POST%%']    = '';
            $placeholders['%%SERIES_TITLE%%'] = '';
            $placeholders['%%CHARACTERS%%']   = '';
            $placeholders['%%LOCATIONS%%']    = '';
            $placeholders['%%TAGS%%']         = '';
            $placeholders['%%CHAPTER%%']      = '';
            $placeholders['%%COLLECTIONS%%']  = '';
        }

        // Bluesky's API expects plain UTF-8 text, not HTML -- but several
        // of these values (most notably titles, via get_the_title(),
        // which runs through wptexturize()) can come back containing
        // literal HTML entities. For example "Volume 2 - Title" becomes
        // "Volume 2 &#8211; Title" (an en dash entity) rather than the
        // actual "–" character, which would otherwise post to Bluesky as
        // that literal entity text instead of a dash. Decoding every
        // placeholder here, once, fixes this for every current and
        // future placeholder built from a WordPress title/content field,
        // rather than patching it per-field. %%URL%% is deliberately
        // skipped -- a URL should never contain HTML entities in the
        // first place, and decoding one on the rare chance it did could
        // corrupt an intentionally percent/entity-encoded query string.
        foreach ($placeholders as $key => $value) {
            if ('%%URL%%' === $key) {
                continue;
            }
            $placeholders[$key] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }

        return [
            'values'   => $placeholders,
            'hashtags' => array_values(array_unique($hashtag_names)),
        ];
    }

    /**
     * Builds one app.bsky.richtext.facet#tag facet per occurrence of each
     * given hashtag name found in $text (as "#Name") -- this is what
     * actually makes %%CHARACTERS%%/%%LOCATIONS%%/%%TAGS%% render as
     * real, clickable, searchable hashtags on Bluesky. Just posting text
     * that happens to start with "#" does nothing on its own via the API
     * (unlike typing directly into Bluesky's own compose box, which does
     * its own client-side detection) -- the AT Protocol requires an
     * explicit facet annotating the exact byte range for any client to
     * treat a substring as a tag.
     *
     * Built against the FINAL, already-truncated text (not the
     * pre-truncation template output) -- passed in by both callers only
     * after their own truncation/shortening logic has already run, so a
     * facet can never point past the end of what's actually being
     * posted. If truncation happened to cut a hashtag in half, that
     * leftover partial "#Wo" text is simply left as plain text rather
     * than producing an invalid or misleading facet for it.
     */
    private function toocheke_bluesky_build_tag_facets($text, array $hashtag_names)
    {
        $facets = [];

        foreach ($hashtag_names as $name) {
            $needle = '#' . $name;
            $offset = 0;

            while (false !== ($pos = strpos($text, $needle, $offset))) {
                $byte_start = strlen(substr($text, 0, $pos));
                $byte_end   = $byte_start + strlen($needle);

                $facets[] = [
                    'index'    => [
                        '$type'     => 'app.bsky.richtext.facet#byteSlice',
                        'byteStart' => $byte_start,
                        'byteEnd'   => $byte_end,
                    ],
                    'features' => [[
                        '$type' => 'app.bsky.richtext.facet#tag',
                        'tag'   => $name,
                    ]],
                ];

                $offset = $pos + strlen($needle);
            }
        }

        return $facets;
    }

    /**
     * Comma-separated term names for %%CHAPTER%% and %%COLLECTIONS%%
     * (explicitly requested as a plain list, not hashtags, unlike
     * %%CHARACTERS%%/%%LOCATIONS%%/%%TAGS%% below).
     */
    private function toocheke_bluesky_terms_as_list($post_id, $taxonomy, $separator)
    {
        $terms = get_the_terms($post_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        return implode($separator, wp_list_pluck($terms, 'name'));
    }

    /**
     * Term names as Bluesky-safe hashtags for %%CHARACTERS%%,
     * %%LOCATIONS%%, and %%TAGS%% -- same convention Jetpack Social uses
     * for its own {tags} placeholder. Hashtags can't contain spaces or
     * punctuation, so each term name is stripped down to letters/numbers/
     * underscore only (Unicode-aware, so non-English character names
     * aren't mangled) before being prefixed with #.
     *
     * Every clean tag name (without the # prefix) is also appended to
     * $collected_tags by reference -- toocheke_bluesky_get_template_placeholders()
     * gathers these across all three hashtag placeholders so
     * toocheke_bluesky_build_tag_facets() knows exactly which substrings
     * in the final assembled text need a real app.bsky.richtext.facet#tag
     * facet, rather than just being plain "#Word" text with no facet at
     * all (which is what a hashtag with no facet actually is to Bluesky
     * -- inert, unclickable, unsearchable text).
     */
    private function toocheke_bluesky_terms_as_hashtags($post_id, $taxonomy, array &$collected_tags)
    {
        $terms = get_the_terms($post_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $hashtags = [];
        foreach ($terms as $term) {
            $clean = preg_replace('/[^\p{L}\p{N}_]/u', '', $term->name);
            if ('' !== $clean) {
                $hashtags[]        = '#' . $clean;
                $collected_tags[] = $clean;
            }
        }

        return implode(' ', $hashtags);
    }

    /**
     * Assembles the Text+Image format's post text from the admin-configured
     * template, substituting every placeholder resolved by
     * toocheke_bluesky_get_template_placeholders().
     *
     * Bluesky's 300-character (grapheme) limit applies to the full text
     * including the URL — Bluesky does not shorten links or exempt them from
     * the count (confirmed directly against the AT Protocol post lexicon).
     * If a real post's combined placeholders push the assembled text over
     * that limit, only %%TITLE%% is shortened to make room — it was the
     * single elastic placeholder before this template grew to support many
     * more fields, and remains the one deliberately shortened now; every
     * other placeholder (excerpt, blog post content, hashtags, etc.) keeps
     * its full value. If TITLE alone can't free up enough room, the whole
     * assembled string is hard-truncated as a last resort so the API call
     * never fails outright.
     *
     * Returns ['text' => ..., 'facets' => [...]] rather than a bare
     * string — the URL link facet (previously built separately by the
     * caller) and any %%CHARACTERS%%/%%LOCATIONS%%/%%TAGS%% hashtag
     * facets are both built here, once, against the final text, so the
     * caller doesn't need its own facet-building logic at all.
     */
    private function toocheke_bluesky_build_message_text($post_id, $post_type, $title, $url)
    {
        $template = get_option('toocheke-bluesky-message-template');
        if (empty($template)) {
            $template = "%%TITLE%%\n%%URL%%";
        }

        $resolved     = $this->toocheke_bluesky_get_template_placeholders($post_id, $post_type, $title, $url);
        $placeholders = $resolved['values'];
        $hashtags     = $resolved['hashtags'];

        $text = str_replace(array_keys($placeholders), array_values($placeholders), $template);

        $overflow = mb_strlen($text) - 300;
        if ($overflow > 0) {
            $shortened_title            = mb_substr($title, 0, max(0, mb_strlen($title) - $overflow - 1)) . '…';
            $placeholders['%%TITLE%%']  = $shortened_title;
            $text                       = str_replace(array_keys($placeholders), array_values($placeholders), $template);

            if (mb_strlen($text) > 300) {
                $text = mb_substr($text, 0, 300);
            }
        }

        $facets = $this->toocheke_bluesky_build_tag_facets($text, $hashtags);

        $url_pos = strpos($text, $url);
        if (false !== $url_pos) {
            $byte_start = strlen(substr($text, 0, $url_pos));
            $byte_end   = $byte_start + strlen($url);
            $facets[]   = [
                'index'    => [
                    '$type'     => 'app.bsky.richtext.facet#byteSlice',
                    'byteStart' => $byte_start,
                    'byteEnd'   => $byte_end,
                ],
                'features' => [[
                    '$type' => 'app.bsky.richtext.facet#link',
                    'uri'   => $url,
                ]],
            ];
        }

        return [
            'text'   => $text,
            'facets' => $facets,
        ];
    }

    /* =========================================================================
       BLUESKY / AT-PROTOCOL API LAYER
       These three functions are the only places that talk to Bluesky
       directly. See the file header note about keeping this isolated for
       easy upgrades if the API ever changes.
    ========================================================================= */

    private function toocheke_bluesky_authenticate()
    {
        $handle   = get_option('toocheke-bluesky-handle');
        $password = get_option('toocheke-bluesky-app-password');

        if (empty($handle) || empty($password)) {
            return new WP_Error('toocheke_bluesky_not_configured', __('Bluesky handle or app password is not configured.', 'toocheke-companion'));
        }

        $response = wp_remote_post(TOOCHEKE_BLUESKY_API_BASE . 'com.atproto.server.createSession', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'identifier' => $handle,
                'password'   => $password,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('toocheke_bluesky_auth_request', 'Bluesky auth request failed: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $status || empty($body['accessJwt']) || empty($body['did'])) {
            $message = $body['message'] ?? ('Unknown error (HTTP ' . $status . ').');
            return new WP_Error('toocheke_bluesky_auth_failed', 'Bluesky authentication failed: ' . $message);
        }

        return ['token' => $body['accessJwt'], 'did' => $body['did']];
    }

    private function toocheke_bluesky_upload_image($image_url, $token)
    {
        if (empty($image_url)) {
            return new WP_Error('toocheke_bluesky_no_image', 'No image available to upload.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp_file = download_url($image_url, 30);

        if (is_wp_error($tmp_file)) {
            return new WP_Error('toocheke_bluesky_image_download', 'Image download failed: ' . $tmp_file->get_error_message());
        }

        $file_size = filesize($tmp_file);
        if (false === $file_size || $file_size < 100) {
            @unlink($tmp_file);
            return new WP_Error('toocheke_bluesky_image_empty', 'Downloaded image was empty or unreadable.');
        }

        // Bluesky's blob size limit is 1MB; stay a little under it for safety.
        if ($file_size > 976 * 1024) {
            @unlink($tmp_file);
            return new WP_Error('toocheke_bluesky_image_too_large', 'Image exceeds Bluesky\'s 1MB image limit (' . round($file_size / 1024) . 'KB).');
        }

        $image_info = @getimagesize($tmp_file);
        $mime       = $image_info['mime'] ?? 'image/jpeg';
        $image_data = file_get_contents($tmp_file);
        @unlink($tmp_file);

        if (! $image_data) {
            return new WP_Error('toocheke_bluesky_image_read', 'Could not read the downloaded image.');
        }

        $response = wp_remote_post(TOOCHEKE_BLUESKY_API_BASE . 'com.atproto.repo.uploadBlob', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => $mime,
            ],
            'body' => $image_data,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('toocheke_bluesky_blob_request', 'Image upload request failed: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $status || empty($body['blob'])) {
            $message = $body['message'] ?? ('Unknown error (HTTP ' . $status . ').');
            return new WP_Error('toocheke_bluesky_blob_failed', 'Image upload failed: ' . $message);
        }

        return [
            'blob'   => $body['blob'],
            'width'  => isset($image_info[0]) ? (int) $image_info[0] : 0,
            'height' => isset($image_info[1]) ? (int) $image_info[1] : 0,
        ];
    }

    private function toocheke_bluesky_create_record($record, $token, $did)
    {
        $response = wp_remote_post(TOOCHEKE_BLUESKY_API_BASE . 'com.atproto.repo.createRecord', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'repo'       => $did,
                'collection' => 'app.bsky.feed.post',
                'record'     => $record,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('toocheke_bluesky_post_request', 'Post request failed: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $status || empty($body['uri'])) {
            $message = $body['message'] ?? ('Unknown error (HTTP ' . $status . ').');
            return new WP_Error('toocheke_bluesky_post_failed', 'Post creation failed: ' . $message);
        }

        return $body['uri'];
    }

    /* =========================================================================
       CUMULATIVE, SITE-WIDE ERROR NOTICE
       Deliberately just one capped array option — no per-post log, no
       dashboard. See file header for the reasoning.
    ========================================================================= */

    public function toocheke_bluesky_log_error($message)
    {
        $errors = get_option('toocheke-bluesky-errors', []);
        if (! is_array($errors)) {
            $errors = [];
        }

        $errors[] = [
            'time'    => current_time('mysql'),
            'message' => $message,
        ];

        // Cap so a persistently failing setup (e.g. a revoked app password)
        // can't grow this option indefinitely between dismissals.
        if (count($errors) > 20) {
            $errors = array_slice($errors, -20);
        }

        update_option('toocheke-bluesky-errors', $errors);
    }

    public function toocheke_bluesky_admin_notice_errors()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $errors = get_option('toocheke-bluesky-errors', []);
        if (empty($errors)) {
            return;
        }

        $clear_url = wp_nonce_url(admin_url('admin-post.php?action=toocheke_bluesky_clear_errors'), 'toocheke_bluesky_clear_errors');
        $count     = count($errors);
        ?>
        <div class="notice notice-error">
            <p>
                <strong>
                    <?php
                    printf(
                        /* translators: %d: number of errors */
                        esc_html(_n('%d Bluesky posting error has occurred:', '%d Bluesky posting errors have occurred:', $count, 'toocheke-companion')),
                        $count
                    );
                    ?>
                </strong>
            </p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach (array_slice(array_reverse($errors), 0, 10) as $error): ?>
                    <li><code><?php echo esc_html($error['time'] ?? ''); ?></code> — <?php echo esc_html($error['message'] ?? ''); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><a href="<?php echo esc_url($clear_url); ?>" class="button"><?php esc_html_e('Dismiss all', 'toocheke-companion'); ?></a></p>
        </div>
        <?php
    }

    public function toocheke_bluesky_handle_clear_errors()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'toocheke-companion'));
        }
        check_admin_referer('toocheke_bluesky_clear_errors');

        delete_option('toocheke-bluesky-errors');

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * Called on plugin deactivation so the random-repost cron doesn't linger
     * as an orphaned scheduled event.
     */
    public function toocheke_bluesky_deactivation_cleanup()
    {
        wp_clear_scheduled_hook('toocheke_bluesky_random_post_cron');
    }
}
