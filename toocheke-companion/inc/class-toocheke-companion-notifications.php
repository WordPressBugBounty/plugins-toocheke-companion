<?php
/**
 * Toocheke Companion — Email Notifications (Premium only).
 *
 * Lets readers subscribe by email to be notified when new comics, manga
 * chapters, or posts go up. This is a Toocheke Premium feature: the
 * settings tab is only ever shown/registered when the active theme is
 * Toocheke Premium (or a child of it) — see the 'notification_options'
 * case in toocheke_init_option_fields() in
 * class-toocheke-companion-settings-page.php, which mirrors the existing
 * premium gate already used for 'buy_options' / 'sponsor_options'.
 *
 * PASS 1 SCOPE (this file, this pass):
 * - The two custom database tables this feature needs (subscribers +
 *   send queue), created/upgraded via their own independent DB-version
 *   check (see toocheke_notifications_maybe_upgrade_db()) rather than
 *   being tied to TOOCHEKE_COMPANION_VERSION. This is deliberate: the
 *   plugin version bump to 2.5 is being held until the feature is fully
 *   built across all passes, but the tables need to exist (and need to
 *   get created for existing 2.4 sites that update the plugin files
 *   without deactivating/reactivating, which never fires
 *   register_activation_hook) as soon as this pass ships. Decoupling the
 *   schema version from the plugin release version means this works
 *   correctly regardless of which pass a given site is currently on.
 * - The "Notifications" settings tab: which post types should trigger a
 *   notification email, plus Cloudflare Turnstile bot-protection config
 *   for the (not-yet-built) public signup form, with a live test-connection
 *   check.
 * - A dismissible info notice recommending a real SMTP delivery plugin +
 *   provider, since wp_mail() alone is unreliable at any real subscriber
 *   count.
 *
 * NOT in this pass (later passes): the signup/confirm/unsubscribe
 * shortcodes, the actual publish-time queueing hook, the cron batch
 * sender, and the email template itself. Settings fields for those
 * (sender name/email, logo, signature) will be added to this same tab
 * in later passes.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (! defined('ABSPATH')) { exit; }

// The schema version for this feature's two custom tables. Bump this
// (and add a new branch in toocheke_notifications_maybe_upgrade_db())
// any time the table structure changes in a later pass. This is
// intentionally separate from TOOCHEKE_COMPANION_VERSION.
if (! defined('TOOCHEKE_NOTIFICATIONS_DB_VERSION')) {
    define('TOOCHEKE_NOTIFICATIONS_DB_VERSION', '1.1');
}

// Every Cloudflare Turnstile endpoint used by this file is built from
// this one constant, matching the pattern TOOCHEKE_BLUESKY_API_BASE
// uses in class-toocheke-companion-bluesky.php.
if (! defined('TOOCHEKE_TURNSTILE_VERIFY_URL')) {
    define('TOOCHEKE_TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
}

trait Toocheke_Companion_Notifications
{
    /**
     * Set by toocheke_notifications_signup_shortcode() /
     * toocheke_notifications_manage_shortcode() at the moment each
     * actually renders its form. Widgets (sidebars, footers, etc.)
     * render outside $post->post_content, so there's no reliable way to
     * know ahead of time whether either shortcode is present on a given
     * page — instead, toocheke_notifications_enqueue_frontend_assets()
     * checks these flags from wp_footer, which fires after every widget
     * area and the main content have already rendered.
     */
    private $toocheke_notify_render_flags = [
        'signup_rendered'    => false,
        'manage_rendered'    => false,
        'turnstile_rendered' => false,
    ];

    /* =========================================================================
       HOOK REGISTRATION
       Everything this feature needs is wired up from this single method,
       called once from init() in toocheke-companion.php — same pattern as
       toocheke_bluesky_register_hooks().
    ========================================================================= */

    public function toocheke_notifications_register_hooks()
    {
        // Keep the two custom tables current on every admin page load
        // (cheap: one get_option() check unless a real upgrade is due —
        // see the docblock on toocheke_notifications_maybe_upgrade_db()).
        add_action('admin_init', [$this, 'toocheke_notifications_maybe_upgrade_db']);

        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'toocheke_notifications_enqueue_admin_assets']);
            add_action('wp_ajax_toocheke_notifications_test_turnstile', [$this, 'toocheke_notifications_ajax_test_turnstile']);

            // Email Subscriptions admin page — separate from the
            // Notifications settings tab, listing/deleting individual
            // subscribers and exporting them as CSV. Registered from a
            // dedicated admin_menu callback (rather than editing
            // class-toocheke-companion-settings-page.php's existing one)
            // so this feature stays self-contained, same as everything
            // else in this file.
            // Priority 1 -- must run AFTER class-toocheke-companion-settings-page.php's
            // own admin_menu callback (priority 0), which registers
            // Dashboard/Options/Import: Comic Easel/Import: Webcomic in
            // that order, and hard-codes "Promote on ComicScout" at array
            // key 9999 (a common WP trick to force it last). Running
            // after that means Dashboard/Options/etc. already exist with
            // their normal sequential keys by the time
            // toocheke_notifications_register_admin_menu() below calls
            // add_submenu_page() with an explicit $position — the
            // position argument (supported since WP 5.3, which this
            // plugin already requires) splices this item in relative to
            // whatever's already there, rather than just appending, so
            // it lands exactly where intended without touching the raw
            // $submenu array or risking a key collision.
            add_action('admin_menu', [$this, 'toocheke_notifications_register_admin_menu'], 1);
            add_action('admin_post_toocheke_notifications_delete_subscriber', [$this, 'toocheke_notifications_handle_delete_subscriber']);
            add_action('admin_post_toocheke_notifications_export_subscribers', [$this, 'toocheke_notifications_handle_export_subscribers']);

            // Cumulative, site-wide error notice for failed sends —
            // mirrors the exact pattern already used for Bluesky posting
            // errors (toocheke-bluesky-errors) in
            // class-toocheke-companion-bluesky.php, just under its own
            // option name so dismissing one never clears the other.
            add_action('admin_notices', [$this, 'toocheke_notifications_admin_notice_errors']);
            add_action('admin_post_toocheke_notifications_clear_errors', [$this, 'toocheke_notifications_handle_clear_errors']);
        }

        // Shortcodes must register unconditionally (not inside is_admin())
        // since they render on the front end. Each shortcode callback
        // still checks toocheke_notifications_is_premium_active() itself
        // and renders nothing if the feature isn't licensed, so a stray
        // shortcode left in content after a theme switch away from
        // Premium just goes quiet rather than erroring.
        $this->toocheke_notifications_register_shortcodes();

        // Public AJAX endpoints — registered for both logged-in and
        // logged-out visitors, since subscribing is anonymous. The
        // is_admin() wrapper here matches the existing convention used
        // for the Likes/view-tracker nopriv hooks elsewhere in this
        // plugin: admin-ajax.php requests satisfy is_admin() === true
        // regardless of who's logged in, so this still only registers
        // the hook when actually handling an AJAX request, not on every
        // normal front-end page load.
        if (is_admin()) {
            add_action('wp_ajax_toocheke_notify_signup', [$this, 'toocheke_notifications_ajax_signup']);
            add_action('wp_ajax_nopriv_toocheke_notify_signup', [$this, 'toocheke_notifications_ajax_signup']);
            add_action('wp_ajax_toocheke_notify_update_prefs', [$this, 'toocheke_notifications_ajax_update_prefs']);
            add_action('wp_ajax_nopriv_toocheke_notify_update_prefs', [$this, 'toocheke_notifications_ajax_update_prefs']);
        }

        // Front-end JS/Turnstile script. Deliberately hooked to
        // wp_footer, not wp_enqueue_scripts — see the docblock on the
        // $toocheke_notify_render_flags property for why: the signup/
        // manage shortcodes can be placed in a sidebar widget, which
        // hasn't rendered yet by the time wp_enqueue_scripts fires.
        // Priority 1 ensures this runs before WordPress core's own
        // wp_print_footer_scripts callback (priority 20) prints
        // everything that's been enqueued so far.
        add_action('wp_footer', [$this, 'toocheke_notifications_enqueue_frontend_assets'], 1);

        // Publish-time queueing — same transition_post_status event
        // Bluesky already hooks in class-toocheke-companion-bluesky.php,
        // just building a notify-queue entry per matching subscriber
        // instead of a Bluesky post.
        add_action('transition_post_status', [$this, 'toocheke_notifications_maybe_queue_on_publish'], 10, 3);

        // WP-Cron batch sender that actually drains the queue built
        // above. Registering the custom interval and the send-queue
        // callback are both safe to do unconditionally (no-ops until
        // something is actually scheduled/queued); the schedule itself
        // is only created for Premium sites, checked inside
        // toocheke_notifications_maybe_schedule_cron().
        add_filter('cron_schedules', [$this, 'toocheke_notifications_register_cron_schedule']);
        add_action('toocheke_notifications_send_queue', [$this, 'toocheke_notifications_process_queue_batch']);
        add_action('init', [$this, 'toocheke_notifications_maybe_schedule_cron']);
    }

    /* =========================================================================
       DATABASE TABLES
    ========================================================================= */

    /**
     * Creates (or updates, via dbDelta's own diffing) the two tables this
     * feature needs. Safe to call repeatedly — dbDelta only ever adds/
     * modifies what's different from the schema passed in, it never drops
     * or truncates existing data.
     *
     * Called from two places, both intentional:
     * - register_activation_hook in toocheke-companion.php, for sites
     *   installing/activating the plugin fresh.
     * - toocheke_notifications_maybe_upgrade_db(), for sites that already
     *   have the plugin active and simply update the plugin files (the
     *   normal WP auto-update path), which never fires an activation hook.
     */
    public function toocheke_notifications_create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $subscribers_table = $wpdb->prefix . 'toocheke_notify_subscribers';
        $queue_table       = $wpdb->prefix . 'toocheke_notify_queue';

        // Subscribers: one row per email address per site. `token` is
        // reused for both the confirmation link and the manage/unsubscribe
        // link — it's regenerated whenever a new one needs to be issued,
        // so a stale token in an old email simply stops matching.
        // `series_prefs` holds a JSON-encoded array of series/manga_series
        // post IDs the subscriber wants; NULL/empty means "everything".
        $sql_subscribers = "CREATE TABLE {$subscribers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            token VARCHAR(64) NOT NULL,
            series_prefs LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            confirmed_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY token (token),
            KEY status (status)
        ) {$charset_collate};";

        // Send queue: one row per (subscriber, post) notification to be
        // sent. Populated by the publish-time hook (later pass) and
        // drained in batches by WP-Cron (later pass), mirroring the
        // two-phase AJAX pattern already used elsewhere in this plugin
        // for the CSV importer. The UNIQUE KEY exists so
        // toocheke_notifications_queue_for_subscribers() can safely use
        // INSERT IGNORE — WordPress/Gutenberg is known to fire
        // transition_post_status more than once for a single publish
        // action in some cases, and this guarantees that never results
        // in the same subscriber getting queued (and thus emailed)
        // twice for the same post.
        $sql_queue = "CREATE TABLE {$queue_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY subscriber_post (subscriber_id, post_id),
            KEY subscriber_id (subscriber_id),
            KEY post_id (post_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql_subscribers);
        dbDelta($sql_queue);

        update_option('toocheke_notifications_db_version', TOOCHEKE_NOTIFICATIONS_DB_VERSION);
    }

    /**
     * Version-gated dbDelta runner — see the docblock on
     * toocheke_notifications_create_tables() for why this exists
     * alongside the activation hook rather than instead of it.
     *
     * Mirrors the existing toocheke_companion_upgrade_check() pattern in
     * class-toocheke-companion-settings-page.php (version_compare against
     * a stored option), just scoped to this feature's own table schema
     * instead of the whole plugin.
     */
    public function toocheke_notifications_maybe_upgrade_db()
    {
        $installed_db_version = get_option('toocheke_notifications_db_version', '0');

        if (version_compare($installed_db_version, TOOCHEKE_NOTIFICATIONS_DB_VERSION, '<')) {
            $this->toocheke_notifications_create_tables();
        }
    }

    /* =========================================================================
       SETTINGS TAB
       Registration is called from the 'notification_options' case inside
       toocheke_init_option_fields() in class-toocheke-companion-settings-page.php,
       inside the same premium-theme check already wrapping 'buy_options'
       and 'sponsor_options'. Everything else related to this tab lives
       here so the feature stays self-contained, same as Bluesky.
    ========================================================================= */

    /**
     * Single source of truth for each post type's default
     * enabled/disabled state, used both when registering the settings
     * fields below and when actually checking whether to queue a
     * notification at publish time (see
     * toocheke_notifications_is_post_type_enabled()). Keeping this in
     * one place matters: get_option()'s own registered default only
     * applies during a request where register_setting() has already run
     * earlier in that same request (i.e. while actively viewing this
     * settings tab) — a normal front-end publish or a WP-Cron run never
     * triggers that, so relying on it there would silently treat every
     * post type as disabled on any site where this tab had never once
     * been saved.
     */
    private function toocheke_notifications_get_post_type_defaults()
    {
        return [
            'post'          => 1,
            'comic'         => 1,
            'manga_chapter' => 1,
            'series'        => 0,
            'manga_series'  => 0,
            'manga_volume'  => 0,
        ];
    }

    /**
     * The correct, always-safe way to check whether a post type should
     * trigger notifications — used at publish time. Do not call
     * get_option('toocheke-notify-enable-' . $post_type) directly
     * without this fallback; see the docblock above.
     */
    private function toocheke_notifications_is_post_type_enabled($post_type)
    {
        $defaults = $this->toocheke_notifications_get_post_type_defaults();
        $default  = isset($defaults[$post_type]) ? $defaults[$post_type] : 0;
        return (bool) get_option('toocheke-notify-enable-' . $post_type, $default);
    }

    public function toocheke_notifications_register_settings_fields()
    {
        // --- Which post types should send a notification email? ---
        add_settings_section(
            'toocheke_notifications_post_types_section',
            'Notify Subscribers On',
            [$this, 'toocheke_notifications_post_types_section_message'],
            'toocheke-options-page'
        );

        $post_type_labels = [
            'post'          => 'Post',
            'comic'         => 'Comic',
            'manga_chapter' => 'Manga Chapter',
            'series'        => 'Series',
            'manga_series'  => 'Manga Series',
            'manga_volume'  => 'Manga Volume',
        ];
        $post_type_defaults = $this->toocheke_notifications_get_post_type_defaults();

        foreach ($post_type_labels as $post_type => $label) {
            $option_key = 'toocheke-notify-enable-' . $post_type;
            $default    = $post_type_defaults[$post_type];

            add_settings_field(
                $option_key,
                'Send an email when a new ' . $label . ' is published?',
                [$this, 'toocheke_notifications_post_type_checkbox'],
                'toocheke-options-page',
                'toocheke_notifications_post_types_section',
                ['option_key' => $option_key, 'default' => $default]
            );
            register_setting('toocheke-settings', $option_key, [
                'sanitize_callback' => [$this, 'toocheke_notifications_sanitize_checkbox'],
                'default'           => $default,
            ]);
        }

        // --- Email delivery (informational only in this pass — no
        // fields yet, just pointing the admin at reliable delivery
        // before any sending logic exists). ---
        add_settings_section(
            'toocheke_notifications_delivery_section',
            'Email Delivery',
            [$this, 'toocheke_notifications_delivery_section_message'],
            'toocheke-options-page'
        );

        // --- Cloudflare Turnstile (bot protection for the public signup
        // form built in a later pass) ---
        add_settings_section(
            'toocheke_notifications_turnstile_section',
            'Bot Protection (Cloudflare Turnstile)',
            [$this, 'toocheke_notifications_turnstile_section_message'],
            'toocheke-options-page'
        );

        add_settings_field(
            'toocheke-notify-turnstile-enable',
            'Enable Turnstile CAPTCHA on the signup form?',
            [$this, 'toocheke_notifications_turnstile_enable_checkbox'],
            'toocheke-options-page',
            'toocheke_notifications_turnstile_section'
        );
        register_setting('toocheke-settings', 'toocheke-notify-turnstile-enable', [
            'sanitize_callback' => [$this, 'toocheke_notifications_sanitize_checkbox'],
            'default'           => 0,
        ]);

        add_settings_field(
            'toocheke-notify-turnstile-site-key',
            'Turnstile Site Key',
            [$this, 'toocheke_display_input_text'],
            'toocheke-options-page',
            'toocheke_notifications_turnstile_section',
            [
                'label_for' => 'toocheke-notify-turnstile-site-key',
                'class'     => 'toocheke-companion',
                'name'      => 'toocheke-notify-turnstile-site-key',
            ]
        );
        register_setting('toocheke-settings', 'toocheke-notify-turnstile-site-key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_field(
            'toocheke-notify-turnstile-secret-key',
            'Turnstile Secret Key',
            [$this, 'toocheke_display_input_text'],
            'toocheke-options-page',
            'toocheke_notifications_turnstile_section',
            [
                'label_for' => 'toocheke-notify-turnstile-secret-key',
                'class'     => 'toocheke-companion',
                'name'      => 'toocheke-notify-turnstile-secret-key',
            ]
        );
        register_setting('toocheke-settings', 'toocheke-notify-turnstile-secret-key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_field(
            'toocheke-notify-turnstile-test',
            'Test Connection',
            [$this, 'toocheke_notifications_turnstile_test_area_field'],
            'toocheke-options-page',
            'toocheke_notifications_turnstile_section'
        );

        // --- Email Design (Pass 4: logo + signature for the HTML
        // notification email template) ---
        add_settings_section(
            'toocheke_notifications_email_design_section',
            'Email Design',
            [$this, 'toocheke_notifications_email_design_section_message'],
            'toocheke-options-page'
        );

        add_settings_field(
            'toocheke-notify-email-logo',
            'Header Logo',
            [$this, 'toocheke_notifications_email_logo_field'],
            'toocheke-options-page',
            'toocheke_notifications_email_design_section'
        );
        register_setting('toocheke-settings', 'toocheke-notify-email-logo', [
            'sanitize_callback' => 'esc_url_raw',
        ]);

        add_settings_field(
            'toocheke-notify-email-signature',
            'Signature',
            [$this, 'toocheke_notifications_email_signature_field'],
            'toocheke-options-page',
            'toocheke_notifications_email_design_section'
        );
        register_setting('toocheke-settings', 'toocheke-notify-email-signature', [
            'sanitize_callback' => 'wp_kses_post',
        ]);

        // --- Pages & Shortcodes ---
        // The confirm/unsubscribe/manage shortcodes need to live on real
        // pages so the links sent in emails (see
        // toocheke_notifications_send_confirmation_email()) have
        // somewhere to point. This section is both the shortcode
        // reference and the page picker.
        add_settings_section(
            'toocheke_notifications_pages_section',
            'Pages & Shortcodes',
            [$this, 'toocheke_notifications_pages_section_message'],
            'toocheke-options-page'
        );

        $page_fields = [
            'toocheke-notify-confirm-page'     => __('Confirmation Page', 'toocheke-companion'),
            'toocheke-notify-unsubscribe-page' => __('Unsubscribe Page', 'toocheke-companion'),
            'toocheke-notify-manage-page'      => __('Manage Subscription Page', 'toocheke-companion'),
        ];
        foreach ($page_fields as $option_key => $label) {
            add_settings_field(
                $option_key,
                $label,
                [$this, 'toocheke_notifications_page_dropdown_field'],
                'toocheke-options-page',
                'toocheke_notifications_pages_section',
                ['option_key' => $option_key]
            );
            register_setting('toocheke-settings', $option_key, [
                'sanitize_callback' => 'absint',
            ]);
        }
    }

    /* --- Section message callbacks --- */

    public function toocheke_notifications_post_types_section_message()
    {
        echo '<p>' . esc_html__('Choose which kinds of new posts trigger a notification email to subscribed readers. Post, Comic, and Manga Chapter are on by default; Series-level content is off by default since most readers only want to hear about new pages, not new series/volume listings.', 'toocheke-companion') . '</p>';
    }

    public function toocheke_notifications_delivery_section_message()
    {
        $notice_html = '<p>' . esc_html__('WordPress\'s default mail function is unreliable once you have real subscribers — many hosts throttle it or have it flagged as spam. Before turning notifications on, install a plugin like ', 'toocheke-companion')
            . '<b>WP Mail SMTP</b>'
            . esc_html__(' and connect it to a free-tier transactional email service such as Brevo or SendGrid.', 'toocheke-companion')
            . '</p><p><a href="https://leetoo.net/how-to-ensure-your-wordpress-emails-are-delivered-using-wp-mail-smtp-with-brevo/" target="_blank" rel="noopener noreferrer">'
            . esc_html__('Step-by-step tutorial: WP Mail SMTP with Brevo', 'toocheke-companion')
            . '</a></p>';

        $this->toocheke_render_dismissible_info('toocheke_notifications_delivery_notice', $notice_html);
    }

    public function toocheke_notifications_turnstile_section_message()
    {
        $notice_html = '<p>' . esc_html__('Cloudflare Turnstile keeps bots from spamming your signup form with fake email addresses. It\'s free.', 'toocheke-companion') . '</p>'
            . '<ol style="margin-left:1.2em;list-style:decimal;">'
            . '<li>' . esc_html__('Go to the Cloudflare Turnstile dashboard and add a new Widget.', 'toocheke-companion') . '</li>'
            . '<li>' . esc_html__('Add your site\'s domain to the widget.', 'toocheke-companion') . '</li>'
            . '<li>' . esc_html__('Copy the Site Key and Secret Key it gives you into the two fields below.', 'toocheke-companion') . '</li>'
            . '</ol>'
            . '<p><a href="https://www.cloudflare.com/products/turnstile/" target="_blank" rel="noopener noreferrer">'
            . esc_html__('Cloudflare Turnstile', 'toocheke-companion')
            . '</a></p>';

        $this->toocheke_render_dismissible_info('toocheke_notifications_turnstile_notice', $notice_html);
    }

    /* --- Field renderers --- */

    public function toocheke_notifications_post_type_checkbox($args)
    {
        $option_key = $args['option_key'];
        $default    = $args['default'];
        $value      = get_option($option_key, $default);
        ?>
        <input type="checkbox" id="<?php echo esc_attr($option_key); ?>" name="<?php echo esc_attr($option_key); ?>" value="1" <?php checked(1, $value, true); ?> />
        <?php
    }

    public function toocheke_notifications_turnstile_enable_checkbox()
    {
        $value = get_option('toocheke-notify-turnstile-enable', 0);
        ?>
        <input type="checkbox" id="toocheke-notify-turnstile-enable" name="toocheke-notify-turnstile-enable" value="1" <?php checked(1, $value, true); ?> />
        <?php
    }

    /**
     * Renders the live Turnstile widget + "Test Connection" button.
     * The widget itself is mounted client-side by
     * js/notifications-admin.js using Turnstile's explicit-render API
     * (rather than the auto-render script tag) so it can be re-rendered
     * if the admin pastes in a different Site Key without reloading the
     * page. Solving the widget produces a token, which is sent — along
     * with whatever Site/Secret Key are currently in the two fields
     * above, NOT necessarily the saved options — to
     * toocheke_notifications_ajax_test_turnstile() for server-side
     * verification against Cloudflare's siteverify endpoint. This lets
     * the admin test unsaved keys, same as the Bluesky tab's Test
     * Connection button does for an unsaved handle/app password.
     */
    public function toocheke_notifications_turnstile_test_area_field()
    {
        ?>
        <div id="toocheke-notify-turnstile-test-wrap">
            <div id="toocheke-notify-turnstile-widget"></div>
            <p>
                <button type="button" class="button" id="toocheke-notify-turnstile-test-button" disabled>
                    <?php esc_html_e('Solve the widget above, then test', 'toocheke-companion'); ?>
                </button>
            </p>
            <span id="toocheke-notify-turnstile-test-result"></span>
        </div>
        <?php
    }

    public function toocheke_notifications_email_design_section_message()
    {
        echo '<p>' . esc_html__('Customize how the notification email itself looks — the logo shown above the message, and the signature shown at the bottom.', 'toocheke-companion') . '</p>';
    }

    /**
     * Reuses the plugin's existing generic media-uploader button (see
     * .upload-custom-button in js/media.js, already enqueued site-wide
     * in wp-admin via toocheke_admin_styles_and_scripts()) rather than
     * adding a new uploader script — the button just needs matching
     * data-hidden/data-image attributes pointing at this field's hidden
     * input and preview image.
     */
    public function toocheke_notifications_email_logo_field()
    {
        $logo_url = get_option('toocheke-notify-email-logo', '');
        ?>
        <input type="hidden" id="toocheke-notify-email-logo" name="toocheke-notify-email-logo" value="<?php echo esc_attr($logo_url); ?>" />
        <p>
            <img id="toocheke-notify-email-logo-preview" src="<?php echo esc_url($logo_url); ?>" style="max-width:200px;max-height:80px;<?php echo $logo_url ? '' : 'display:none;'; ?>" />
        </p>
        <button type="button" class="button upload-custom-button" data-hidden="toocheke-notify-email-logo" data-image="toocheke-notify-email-logo-preview">
            <?php esc_html_e('Choose Logo', 'toocheke-companion'); ?>
        </button>
        <p class="description"><?php esc_html_e('Shown centered above the email, on the grey background. Leave empty to send with no logo.', 'toocheke-companion'); ?></p>
        <?php
    }

    public function toocheke_notifications_email_signature_field()
    {
        $signature = get_option('toocheke-notify-email-signature', '');
        ?>
        <textarea id="toocheke-notify-email-signature" name="toocheke-notify-email-signature" rows="3" class="large-text"><?php echo esc_textarea($signature); ?></textarea>
        <p class="description"><?php esc_html_e('Shown at the bottom of the email, above the unsubscribe link. Basic formatting and links are allowed.', 'toocheke-companion'); ?></p>
        <?php
    }

    /* --- Sanitization --- */

    public function toocheke_notifications_sanitize_checkbox($value)
    {
        return $value ? 1 : 0;
    }

    /* =========================================================================
       ADMIN ASSETS
    ========================================================================= */

    public function toocheke_notifications_enqueue_admin_assets()
    {
        if (empty($_GET['page']) || 'toocheke-options-page' !== $_GET['page']) {
            return;
        }
        if (empty($_GET['tab']) || 'notification_options' !== $_GET['tab']) {
            return;
        }

        // Cloudflare's own widget script — only ever loaded on this one
        // admin tab, so it can't affect anything else on the site.
        wp_enqueue_script(
            'toocheke-turnstile-api',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            [],
            null,
            true
        );

        // filemtime() rather than the static plugin version — see the
        // matching comment on toocheke_enqueue_options_nav_assets() in
        // class-toocheke-companion-settings-page.php for why.
        $js_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/notifications-admin.js';

        wp_enqueue_script(
            'toocheke-notifications-admin',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'js/notifications-admin.js',
            ['jquery', 'toocheke-turnstile-api'],
            file_exists($js_path) ? filemtime($js_path) : TOOCHEKE_COMPANION_VERSION,
            true
        );
        wp_localize_script('toocheke-notifications-admin', 'toochekeNotifications', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('toocheke_notifications_test_turnstile'),
        ]);
    }

    /* =========================================================================
       AJAX: TEST TURNSTILE CONNECTION
    ========================================================================= */

    public function toocheke_notifications_ajax_test_turnstile()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'toocheke-companion')], 403);
        }
        check_ajax_referer('toocheke_notifications_test_turnstile', 'nonce');

        $secret_key = isset($_POST['secret_key']) ? sanitize_text_field(wp_unslash($_POST['secret_key'])) : '';
        $token      = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';

        if (empty($secret_key) || empty($token)) {
            wp_send_json_error(['message' => __('Please enter a Secret Key and solve the widget above first.', 'toocheke-companion')]);
        }

        $response = wp_remote_post(TOOCHEKE_TURNSTILE_VERIFY_URL, [
            'timeout' => 15,
            'body'    => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => sprintf(
                /* translators: %s: underlying error message */
                __('Connection failed: %s', 'toocheke-companion'),
                $response->get_error_message()
            )]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! empty($body['success'])) {
            wp_send_json_success(['message' => __('Success! Turnstile is configured correctly. Please save your settings.', 'toocheke-companion')]);
        }

        $error_codes = ! empty($body['error-codes']) ? implode(', ', (array) $body['error-codes']) : 'unknown_error';
        wp_send_json_error(['message' => sprintf(
            /* translators: %s: Cloudflare error code(s) */
            __('Verification failed (%s). Double-check your Site Key and Secret Key match the same Turnstile widget.', 'toocheke-companion'),
            $error_codes
        )]);
    }

    /* =========================================================================
       PAGES & SHORTCODES SETTINGS FIELDS
    ========================================================================= */

    public function toocheke_notifications_pages_section_message()
    {
        ?>
        <p><?php esc_html_e('Create a page for each row below, add the matching shortcode to its content, then select that page in the dropdown so links in notification emails point to the right place. The signup form itself has no page setting — its shortcode can go anywhere (a page, a sidebar widget, etc.).', 'toocheke-companion'); ?></p>
        <table class="widefat" style="max-width:640px;margin-bottom:1em;">
            <tbody>
                <tr>
                    <td><?php esc_html_e('Signup form', 'toocheke-companion'); ?></td>
                    <td><input type="text" readonly onclick="this.select();" value="[toocheke_notify_signup]" style="width:100%;" /></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Confirmation page', 'toocheke-companion'); ?></td>
                    <td><input type="text" readonly onclick="this.select();" value="[toocheke_notify_confirmed]" style="width:100%;" /></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Unsubscribe page', 'toocheke-companion'); ?></td>
                    <td><input type="text" readonly onclick="this.select();" value="[toocheke_notify_unsubscribed]" style="width:100%;" /></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Manage subscription page', 'toocheke-companion'); ?></td>
                    <td><input type="text" readonly onclick="this.select();" value="[toocheke_notify_manage]" style="width:100%;" /></td>
                </tr>
            </tbody>
        </table>
        <?php
        // Nudge, don't block: the feature still technically works without
        // these selected (links fall back to the homepage — see
        // toocheke_notifications_send_confirmation_email()), but a
        // subscriber clicking a homepage link instead of a real
        // confirmation page would be confusing, so flag it early.
        $missing = [];
        foreach ([
            'toocheke-notify-confirm-page'     => __('Confirmation', 'toocheke-companion'),
            'toocheke-notify-unsubscribe-page' => __('Unsubscribe', 'toocheke-companion'),
            'toocheke-notify-manage-page'      => __('Manage Subscription', 'toocheke-companion'),
        ] as $option_key => $label) {
            if (! get_option($option_key)) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            echo '<div class="notice notice-warning inline"><p>' . sprintf(
                /* translators: %s: comma-separated list of page types not yet selected */
                esc_html__('Heads up: you haven\'t selected a page for: %s. Notification emails will link to your homepage until these are set.', 'toocheke-companion'),
                esc_html(implode(', ', $missing))
            ) . '</p></div>';
        }
    }

    public function toocheke_notifications_page_dropdown_field($args)
    {
        wp_dropdown_pages([
            'name'              => $args['option_key'],
            'id'                => $args['option_key'],
            'selected'          => (int) get_option($args['option_key']),
            'show_option_none'  => __('— Select a Page —', 'toocheke-companion'),
            'option_none_value' => 0,
        ]);
    }

    /* =========================================================================
       PREMIUM GATE
       The settings TAB is hidden for non-Premium themes (see the
       'notification_options' case in class-toocheke-companion-settings-page.php),
       but that alone doesn't stop the public-facing shortcodes/AJAX from
       working if a shortcode is left in page content after switching away
       from Premium (or on a Companion-only site that never had the tab).
       Every shortcode callback and the signup AJAX handler check this
       directly so the feature is actually gated end-to-end, not just
       hidden in wp-admin.
    ========================================================================= */

    private function toocheke_notifications_is_premium_active()
    {
        $theme = wp_get_theme();
        return ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme);
    }

    /* =========================================================================
       SHORTCODES
    ========================================================================= */

    public function toocheke_notifications_register_shortcodes()
    {
        add_shortcode('toocheke_notify_signup', [$this, 'toocheke_notifications_signup_shortcode']);
        add_shortcode('toocheke_notify_confirmed', [$this, 'toocheke_notifications_confirmed_shortcode']);
        add_shortcode('toocheke_notify_unsubscribed', [$this, 'toocheke_notifications_unsubscribed_shortcode']);
        add_shortcode('toocheke_notify_manage', [$this, 'toocheke_notifications_manage_shortcode']);
    }

    /**
     * [toocheke_notify_signup] — the public signup form.
     *
     * @param array $atts Supports two optional, mutually-exclusive
     *                     attributes for scoping a signup to one specific
     *                     series: `series` (a Series post ID) or
     *                     `manga_series` (a Manga Series post ID) — never
     *                     both at once on the same form. When given, the
     *                     new subscriber's series_prefs is set to just
     *                     that one series/manga series rather than
     *                     "everything" — for placing a scoped signup form
     *                     on that series' own landing page.
     *                     `[toocheke_notify_signup series="123"]` or
     *                     `[toocheke_notify_signup manga_series="456"]`.
     *                     Internally these are handled identically: WordPress
     *                     post IDs are unique across post types, so a
     *                     Series ID and a Manga Series ID can never
     *                     collide in the same series_prefs list.
     */
    public function toocheke_notifications_signup_shortcode($atts)
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            return '';
        }

        $params    = shortcode_atts(['series' => 0, 'manga_series' => 0], $atts);
        $series_id = absint($params['series']) ?: absint($params['manga_series']);

        $turnstile_enabled  = (bool) get_option('toocheke-notify-turnstile-enable', 0);
        $turnstile_site_key = get_option('toocheke-notify-turnstile-site-key', '');

        // Tell toocheke_notifications_enqueue_frontend_assets() (which
        // runs later, on wp_footer) that this form actually rendered
        // somewhere on the page — including inside a widget, which is
        // exactly the case $post->post_content can't see.
        $this->toocheke_notify_render_flags['signup_rendered'] = true;
        if ($turnstile_enabled && $turnstile_site_key) {
            $this->toocheke_notify_render_flags['turnstile_rendered'] = true;
        }

        ob_start();
        ?>
        <form class="toocheke-notify-signup-form">
            <?php
            // Honeypot: a field real visitors never see or fill in.
            // Hidden with an inline style (not just a CSS class) so it
            // stays hidden even if the active theme's stylesheet doesn't
            // define a matching utility class — this only needs to work,
            // not look good, since a sighted human never sees it.
            ?>
            <p style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
                <label for="toocheke-notify-hp-<?php echo esc_attr($series_id); ?>"><?php esc_html_e('Leave this field blank', 'toocheke-companion'); ?></label>
                <input type="text" id="toocheke-notify-hp-<?php echo esc_attr($series_id); ?>" name="toocheke_notify_hp" tabindex="-1" autocomplete="off" />
            </p>
            <input type="hidden" name="series_id" value="<?php echo esc_attr($series_id); ?>" />
            <p class="toocheke-notify-field">
                <label>
                    <?php esc_html_e('Email address', 'toocheke-companion'); ?><br />
                    <input type="email" name="email" required="required" />
                </label>
            </p>
            <?php if ($turnstile_enabled && $turnstile_site_key) : ?>
                <div class="toocheke-notify-turnstile-widget" data-sitekey="<?php echo esc_attr($turnstile_site_key); ?>"></div>
            <?php endif; ?>
            <p>
                <button type="submit" class="toocheke-notify-submit"><?php esc_html_e('Subscribe', 'toocheke-companion'); ?></button>
            </p>
            <p class="toocheke-notify-result" aria-live="polite"></p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * [toocheke_notify_confirmed] — lands here from the link in the
     * confirmation email. Reads ?token= directly off the current page's
     * URL; no AJAX involved, same as most newsletter confirm links.
     */
    public function toocheke_notifications_confirmed_shortcode($atts)
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            return '';
        }

        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if (empty($token)) {
            return $this->toocheke_notifications_message_box('error', __('This confirmation link is missing its token. Please use the link from your email.', 'toocheke-companion'));
        }

        $subscriber = $this->toocheke_notifications_get_subscriber_by_token($token);
        if (! $subscriber) {
            return $this->toocheke_notifications_message_box('error', __('This confirmation link is invalid or has expired.', 'toocheke-companion'));
        }
        if ('confirmed' === $subscriber->status) {
            return $this->toocheke_notifications_message_box('warning', __('This email address is already confirmed — you\'re all set!', 'toocheke-companion'));
        }
        if ('unsubscribed' === $subscriber->status) {
            return $this->toocheke_notifications_message_box('warning', __('This link has already been used to unsubscribe. Sign up again below if you\'d like to resubscribe.', 'toocheke-companion'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';
        $now   = current_time('mysql');
        $wpdb->update(
            $table,
            ['status' => 'confirmed', 'confirmed_at' => $now, 'updated_at' => $now],
            ['id' => $subscriber->id]
        );

        return $this->toocheke_notifications_message_box('success', __('You\'re confirmed! You\'ll now receive email notifications for new updates.', 'toocheke-companion'));
    }

    /**
     * [toocheke_notify_unsubscribed] — lands here from the unsubscribe
     * link at the bottom of every notification email, or from the
     * "Unsubscribe from all emails" link on the manage-preferences page.
     */
    public function toocheke_notifications_unsubscribed_shortcode($atts)
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            return '';
        }

        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if (empty($token)) {
            return $this->toocheke_notifications_message_box('error', __('This unsubscribe link is missing its token. Please use the link from your email.', 'toocheke-companion'));
        }

        $subscriber = $this->toocheke_notifications_get_subscriber_by_token($token);
        if (! $subscriber) {
            return $this->toocheke_notifications_message_box('error', __('This unsubscribe link is invalid or has expired.', 'toocheke-companion'));
        }
        if ('unsubscribed' === $subscriber->status) {
            return $this->toocheke_notifications_message_box('warning', __('This email address is already unsubscribed.', 'toocheke-companion'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';
        $wpdb->update(
            $table,
            ['status' => 'unsubscribed', 'updated_at' => current_time('mysql')],
            ['id' => $subscriber->id]
        );

        return $this->toocheke_notifications_message_box('success', __('You\'ve been unsubscribed. Sorry to see you go!', 'toocheke-companion'));
    }

    /**
     * [toocheke_notify_manage] — lets an existing subscriber switch
     * between "notify me about everything" and a specific list of
     * series/manga series, or unsubscribe entirely. Reads ?token= off
     * the URL to identify the subscriber; the preference update itself
     * goes through AJAX (toocheke_notifications_ajax_update_prefs) so
     * the page doesn't reload.
     */
    public function toocheke_notifications_manage_shortcode($atts)
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            return '';
        }

        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if (empty($token)) {
            return $this->toocheke_notifications_message_box('warning', __('Use the link from one of your subscription emails to manage your preferences here.', 'toocheke-companion'));
        }

        $subscriber = $this->toocheke_notifications_get_subscriber_by_token($token);
        if (! $subscriber) {
            return $this->toocheke_notifications_message_box('error', __('This link is invalid or has expired.', 'toocheke-companion'));
        }
        if ('unsubscribed' === $subscriber->status) {
            return $this->toocheke_notifications_message_box('warning', __('This email address is unsubscribed. Sign up again if you\'d like to resubscribe.', 'toocheke-companion'));
        }

        $current_prefs = $subscriber->series_prefs ? (array) json_decode($subscriber->series_prefs, true) : [];
        $current_prefs = array_map('absint', $current_prefs);

        // Fetched and labeled separately (rather than one merged,
        // alphabetized list) so it's immediately clear to the reader
        // which items are Series vs. Manga Series.
        $series_posts = get_posts([
            'post_type'      => 'series',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        $manga_series_posts = get_posts([
            'post_type'      => 'manga_series',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $unsubscribe_page_id = (int) get_option('toocheke-notify-unsubscribe-page');
        $unsubscribe_url     = $unsubscribe_page_id ? add_query_arg('token', $token, get_permalink($unsubscribe_page_id)) : '';

        // Only set once we know the real form is actually about to
        // render — the info/error early-returns above have no AJAX
        // interaction, so there's no need to load JS just for those.
        $this->toocheke_notify_render_flags['manage_rendered'] = true;

        ob_start();
        ?>
        <form class="toocheke-notify-manage-form">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>" />
            <p>
                <label>
                    <input type="radio" name="mode" value="all" <?php checked(empty($current_prefs)); ?> />
                    <?php esc_html_e('Notify me about every series', 'toocheke-companion'); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="radio" name="mode" value="selected" <?php checked(! empty($current_prefs)); ?> />
                    <?php esc_html_e('Only notify me about these series:', 'toocheke-companion'); ?>
                </label>
            </p>
            <?php if ($series_posts) : ?>
                <h4 class="toocheke-notify-series-group-title"><?php esc_html_e('Series', 'toocheke-companion'); ?></h4>
                <ul class="toocheke-notify-series-list">
                    <?php foreach ($series_posts as $series_post) : ?>
                        <li>
                            <label>
                                <input type="checkbox" name="series_ids[]" value="<?php echo esc_attr($series_post->ID); ?>" <?php checked(in_array((int) $series_post->ID, $current_prefs, true)); ?> />
                                <?php echo esc_html(get_the_title($series_post)); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($manga_series_posts) : ?>
                <h4 class="toocheke-notify-manga-series-group-title"><?php esc_html_e('Manga Series', 'toocheke-companion'); ?></h4>
                <ul class="toocheke-notify-manga-series-list">
                    <?php foreach ($manga_series_posts as $manga_series_post) : ?>
                        <li>
                            <label>
                                <input type="checkbox" name="series_ids[]" value="<?php echo esc_attr($manga_series_post->ID); ?>" <?php checked(in_array((int) $manga_series_post->ID, $current_prefs, true)); ?> />
                                <?php echo esc_html(get_the_title($manga_series_post)); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p>
                <button type="submit" class="toocheke-notify-manage-submit"><?php esc_html_e('Update Preferences', 'toocheke-companion'); ?></button>
            </p>
            <p class="toocheke-notify-result" aria-live="polite"></p>
        </form>
        <?php if ($unsubscribe_url) : ?>
            <p><a href="<?php echo esc_url($unsubscribe_url); ?>"><?php esc_html_e('Unsubscribe from all emails', 'toocheke-companion'); ?></a></p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Small shared renderer for the success/warning/error states used by
     * the confirm/unsubscribe/manage shortcodes above. Uses inline
     * styles (not just a CSS class) so the message stays legible
     * regardless of the theme's page or widget background — a plain
     * class was found during testing to be unreadable against dark
     * sidebar backgrounds. A theme can still target
     * .toocheke-notify-message-{success,warning,error} directly if it
     * wants to restyle these; the inline styles just guarantee a sane
     * default everywhere.
     */
    private function toocheke_notifications_message_box($type, $message)
    {
        $palette = [
            'success' => ['background' => '#eaf7ec', 'color' => '#1e7e34'],
            'error'   => ['background' => '#fdecea', 'color' => '#b32d2e'],
            'warning' => ['background' => '#fff4e5', 'color' => '#8a5300'],
        ];
        $colors = isset($palette[$type]) ? $palette[$type] : $palette['warning'];
        $class  = 'toocheke-notify-message toocheke-notify-message-' . sanitize_html_class($type);

        return sprintf(
            '<p class="%1$s" style="background:%2$s;color:%3$s;font-weight:bold;padding:12px 16px;border-radius:4px;">%4$s</p>',
            esc_attr($class),
            esc_attr($colors['background']),
            esc_attr($colors['color']),
            esc_html($message)
        );
    }

    /* =========================================================================
       FRONT-END ASSETS
    ========================================================================= */

    /**
     * Only enqueues the (small) public JS file — and Cloudflare's
     * Turnstile script, if it was actually used — on pages where the
     * signup or manage shortcode actually rendered. Hooked to wp_footer
     * (priority 1); see the docblock on $toocheke_notify_render_flags
     * for why this can't be decided any earlier, e.g. from
     * wp_enqueue_scripts against $post->post_content, which misses
     * shortcodes placed in a sidebar/footer widget entirely.
     */
    public function toocheke_notifications_enqueue_frontend_assets()
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            return;
        }

        $needs_signup = $this->toocheke_notify_render_flags['signup_rendered'];
        $needs_manage = $this->toocheke_notify_render_flags['manage_rendered'];

        if (! $needs_signup && ! $needs_manage) {
            return;
        }

        if ($this->toocheke_notify_render_flags['turnstile_rendered']) {
            wp_enqueue_script('toocheke-turnstile-api', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
        }

        $js_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/notifications-public.js';

        wp_enqueue_script(
            'toocheke-notifications-public',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'js/notifications-public.js',
            ['jquery'],
            file_exists($js_path) ? filemtime($js_path) : TOOCHEKE_COMPANION_VERSION,
            true
        );
        wp_localize_script('toocheke-notifications-public', 'toochekeNotifyPublic', [
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('toocheke_notify_public_action'),
            'turnstileEnabled' => (bool) get_option('toocheke-notify-turnstile-enable', 0),
        ]);
    }

    /* =========================================================================
       AJAX: PUBLIC SIGNUP + PREFERENCE UPDATES
    ========================================================================= */

    public function toocheke_notifications_ajax_signup()
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            wp_send_json_error(['message' => __('This feature is not available.', 'toocheke-companion')]);
        }
        if (! check_ajax_referer('toocheke_notify_public_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'toocheke-companion')]);
        }

        // Honeypot: a real visitor never sees or fills this field in
        // (see the inline style in toocheke_notifications_signup_shortcode()).
        // Report success without actually processing anything, rather
        // than telling a bot what tripped it.
        if (! empty($_POST['toocheke_notify_hp'])) {
            wp_send_json_success(['message' => $this->toocheke_notifications_signup_success_message()]);
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (! is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'toocheke-companion')]);
        }

        if (get_option('toocheke-notify-turnstile-enable', 0)) {
            $turnstile_token = isset($_POST['turnstile_token']) ? sanitize_text_field(wp_unslash($_POST['turnstile_token'])) : '';
            $secret_key      = get_option('toocheke-notify-turnstile-secret-key');

            if (empty($turnstile_token) || empty($secret_key) || ! $this->toocheke_notifications_verify_turnstile_token($secret_key, $turnstile_token)) {
                wp_send_json_error(['message' => __('CAPTCHA verification failed. Please try again.', 'toocheke-companion')]);
            }
        }

        $series_id = isset($_POST['series_id']) ? absint($_POST['series_id']) : 0;

        $this->toocheke_notifications_create_or_resend_subscriber($email, $series_id);

        // Same generic message whether this was a brand-new signup, a
        // resend for an unconfirmed one, or a silent no-op for an
        // already-confirmed address — so the response never reveals
        // whether a given email is already subscribed.
        wp_send_json_success(['message' => $this->toocheke_notifications_signup_success_message()]);
    }

    public function toocheke_notifications_ajax_update_prefs()
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            wp_send_json_error(['message' => __('This feature is not available.', 'toocheke-companion')]);
        }
        if (! check_ajax_referer('toocheke_notify_public_action', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'toocheke-companion')]);
        }

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $mode  = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'all';
        $ids   = isset($_POST['series_ids']) ? array_map('absint', (array) $_POST['series_ids']) : [];

        $subscriber = $this->toocheke_notifications_get_subscriber_by_token($token);
        if (! $subscriber) {
            wp_send_json_error(['message' => __('This link is invalid or has expired.', 'toocheke-companion')]);
        }

        global $wpdb;
        $table        = $wpdb->prefix . 'toocheke_notify_subscribers';
        $series_prefs = ('selected' === $mode && ! empty($ids)) ? wp_json_encode($ids) : null;

        $wpdb->update(
            $table,
            ['series_prefs' => $series_prefs, 'updated_at' => current_time('mysql')],
            ['id' => $subscriber->id]
        );

        wp_send_json_success(['message' => __('Your preferences have been updated.', 'toocheke-companion')]);
    }

    private function toocheke_notifications_signup_success_message()
    {
        return __('Almost done! Check your inbox for a confirmation email.', 'toocheke-companion');
    }

    /* =========================================================================
       TURNSTILE VERIFICATION (public path)
       Separate from toocheke_notifications_ajax_test_turnstile() above,
       which is the admin-only test panel and accepts an unsaved
       secret/site key straight from the settings form. This one always
       verifies against the SAVED secret key, for real public signups.
    ========================================================================= */

    private function toocheke_notifications_verify_turnstile_token($secret_key, $token)
    {
        $response = wp_remote_post(TOOCHEKE_TURNSTILE_VERIFY_URL, [
            'timeout' => 15,
            'body'    => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return ! empty($body['success']);
    }

    /* =========================================================================
       SUBSCRIBER DATA HELPERS
    ========================================================================= */

    private function toocheke_notifications_generate_token()
    {
        return bin2hex(random_bytes(32));
    }

    private function toocheke_notifications_get_subscriber_by_email($email)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE email = %s", $email));
    }

    private function toocheke_notifications_get_subscriber_by_token($token)
    {
        if (empty($token)) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token));
    }

    /**
     * Inserts a brand-new subscriber, or handles a repeat signup for an
     * email address already on file:
     * - pending      -> resend the same confirmation link (no new token
     *                   needed; the old email's link still works).
     * - unsubscribed -> treat as re-subscribing: issue a fresh token
     *                   (deliberately not reusing the old one, in case
     *                   it's sitting in an old, possibly-forwarded email)
     *                   and send a new confirmation.
     * - confirmed    -> already subscribed; do nothing. The AJAX handler
     *                   still reports generic success either way, so
     *                   this never reveals subscription status.
     */
    private function toocheke_notifications_create_or_resend_subscriber($email, $series_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';
        $now   = current_time('mysql');

        $existing = $this->toocheke_notifications_get_subscriber_by_email($email);

        if (! $existing) {
            $token        = $this->toocheke_notifications_generate_token();
            $series_prefs = $series_id ? wp_json_encode([$series_id]) : null;

            $wpdb->insert($table, [
                'email'        => $email,
                'status'       => 'pending',
                'token'        => $token,
                'series_prefs' => $series_prefs,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $this->toocheke_notifications_send_confirmation_email($email, $token);
            return;
        }

        if ('pending' === $existing->status) {
            // They might submit the signup form twice, for two different
            // series, before ever confirming — broaden (never narrow)
            // their pending prefs the same way a confirmed subscriber's
            // would be.
            $new_prefs = $this->toocheke_notifications_broaden_series_prefs($existing->series_prefs, $series_id);
            if ($new_prefs !== $existing->series_prefs) {
                $wpdb->update($table, ['series_prefs' => $new_prefs, 'updated_at' => $now], ['id' => $existing->id]);
            }
            $this->toocheke_notifications_send_confirmation_email($email, $existing->token);
            return;
        }

        if ('unsubscribed' === $existing->status) {
            // Re-subscribing after having unsubscribed starts fresh from
            // exactly what this signup requests, rather than reviving
            // whatever scope they had before unsubscribing — a
            // deliberate, visible resubscribe rather than a silent scope
            // change.
            $token        = $this->toocheke_notifications_generate_token();
            $series_prefs = $series_id ? wp_json_encode([$series_id]) : null;

            $wpdb->update(
                $table,
                ['status' => 'pending', 'token' => $token, 'series_prefs' => $series_prefs, 'updated_at' => $now],
                ['id' => $existing->id]
            );

            $this->toocheke_notifications_send_confirmation_email($email, $token);
            return;
        }

        if ('confirmed' === $existing->status) {
            // Signing up again can only ever broaden an already-confirmed
            // subscription, never narrow it — narrowing is a deliberate
            // action available only on the Manage page, where the
            // subscriber can see exactly what they're opting in/out of.
            // No email needed either way; this stays a silent scope
            // update, consistent with the AJAX handler's generic
            // "check your inbox" response, so this never reveals whether
            // the email address was already subscribed.
            $new_prefs = $this->toocheke_notifications_broaden_series_prefs($existing->series_prefs, $series_id);
            if ($new_prefs !== $existing->series_prefs) {
                $wpdb->update($table, ['series_prefs' => $new_prefs, 'updated_at' => $now], ['id' => $existing->id]);
            }
        }
    }

    /**
     * Returns the series_prefs value (a JSON-encoded array of post IDs,
     * or null meaning "everything") that results from broadening
     * $current_prefs_json to also include $new_series_id — this never
     * narrows an existing subscription:
     * - Already unrestricted (null) stays unrestricted, regardless of
     *   what this signup requests.
     * - An unscoped signup ($new_series_id === 0 — no series/manga_series
     *   attribute on the shortcode) is a request for "everything," which
     *   is broader than any specific list, so it always wins and clears
     *   any existing filter.
     * - Otherwise, the new series/manga series ID is added to whatever
     *   list already existed (a plain union — WordPress post IDs are
     *   unique across post types, so Series and Manga Series IDs can
     *   never collide in this list).
     */
    private function toocheke_notifications_broaden_series_prefs($current_prefs_json, $new_series_id)
    {
        if (empty($current_prefs_json)) {
            return null;
        }

        if (! $new_series_id) {
            return null;
        }

        $current_ids = array_map('absint', (array) json_decode($current_prefs_json, true));

        if (in_array((int) $new_series_id, $current_ids, true)) {
            return $current_prefs_json;
        }

        $current_ids[] = (int) $new_series_id;
        return wp_json_encode(array_values($current_ids));
    }

    /**
     * Now uses the same branded HTML template as the actual notification
     * email (toocheke_notifications_render_email_html()), per later
     * feedback — this and every other email this feature sends all go
     * through the one shared toocheke_notifications_send_email()
     * wrapper below, so they can never drift out of sync in styling or
     * error handling again.
     */
    private function toocheke_notifications_send_confirmation_email($email, $token)
    {
        $confirm_page_id = (int) get_option('toocheke-notify-confirm-page');
        $link            = $confirm_page_id ? add_query_arg('token', $token, get_permalink($confirm_page_id)) : home_url('/');

        $unsubscribe_page_id = (int) get_option('toocheke-notify-unsubscribe-page');
        $unsubscribe_link    = $unsubscribe_page_id ? add_query_arg('token', $token, get_permalink($unsubscribe_page_id)) : home_url('/');

        $subject = sprintf(
            /* translators: %s: site name */
            __('Confirm your subscription to %s', 'toocheke-companion'),
            get_bloginfo('name')
        );

        $body_html = '<p>' . esc_html__('Please confirm your subscription by clicking the button below.', 'toocheke-companion') . '</p>'
            . '<p>' . esc_html__("If you didn't request this, you can safely ignore this email.", 'toocheke-companion') . '</p>';

        // No manage-subscription link here — there's nothing to manage
        // yet until they've actually confirmed.
        return $this->toocheke_notifications_send_email($email, $subject, $body_html, $link, __('Confirm Now', 'toocheke-companion'), $unsubscribe_link, '');
    }

    /* =========================================================================
       PASS 3: PUBLISH-TIME QUEUEING
       Fires on the same transition_post_status event Bluesky already
       hooks (see class-toocheke-companion-bluesky.php) -- comic and
       manga_chapter auto-posting and email queueing are two independent
       features reacting to the same underlying "this just got published"
       moment, not related to each other.
    ========================================================================= */

    /**
     * Post types this feature can ever notify on.
     */
    private $toocheke_notify_supported_post_types = ['post', 'comic', 'manga_chapter', 'series', 'manga_series', 'manga_volume'];

    public function toocheke_notifications_maybe_queue_on_publish($new_status, $old_status, $post)
    {
        if ('publish' !== $new_status || 'publish' === $old_status) {
            return;
        }

        if (! $this->toocheke_notifications_is_premium_active()) {
            return;
        }

        $post_type = $post->post_type;
        if (! in_array($post_type, $this->toocheke_notify_supported_post_types, true)) {
            return;
        }

        if (! $this->toocheke_notifications_is_post_type_enabled($post_type)) {
            return;
        }

        $scope_id = $this->toocheke_notifications_get_scope_id($post->ID, $post_type);
        $subscriber_ids = $this->toocheke_notifications_get_matching_subscriber_ids($post_type, $scope_id);

        if ($subscriber_ids) {
            $this->toocheke_notifications_queue_for_subscribers($post->ID, $subscriber_ids);
        }
    }

    /**
     * Resolves the series/manga_series post ID a given post is scoped
     * to, for matching against subscribers' series_prefs. Mirrors the
     * exact relationships toocheke_bluesky_get_post_url() already relies
     * on in class-toocheke-companion-bluesky.php, rather than the
     * separate legacy 'series_id' meta field that also exists on comic
     * posts:
     * - comic: native WordPress post_parent (NOT the 'series_id' meta).
     * - manga_chapter / manga_volume: the 'series_id' meta field, which
     *   for these two post types does point to the parent Manga Series
     *   (manga content doesn't use the post_parent convention).
     * - series / manga_series: publishing one of these IS that series,
     *   so its own post ID is the scope.
     * - post: not series-scoped at all.
     */
    private function toocheke_notifications_get_scope_id($post_id, $post_type)
    {
        switch ($post_type) {
            case 'comic':
                return (int) wp_get_post_parent_id($post_id);

            case 'manga_chapter':
            case 'manga_volume':
                return (int) get_post_meta($post_id, 'series_id', true);

            case 'series':
            case 'manga_series':
                return (int) $post_id;

            default:
                return 0;
        }
    }

    /**
     * Returns the IDs of every confirmed subscriber who should be
     * notified for a post with the given post type and scope ID.
     * - 'post' isn't series-scoped at all, so every confirmed subscriber
     *   qualifies regardless of their series_prefs.
     * - Otherwise, a subscriber qualifies if their series_prefs is
     *   NULL/empty ("notify me about everything") or if $scope_id
     *   appears in their series_prefs list.
     */
    private function toocheke_notifications_get_matching_subscriber_ids($post_type, $scope_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';

        if ('post' === $post_type) {
            return $wpdb->get_col("SELECT id FROM {$table} WHERE status = 'confirmed'");
        }

        if (! $scope_id) {
            return $wpdb->get_col("SELECT id FROM {$table} WHERE status = 'confirmed' AND (series_prefs IS NULL OR series_prefs = '')");
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, series_prefs FROM {$table} WHERE status = 'confirmed' AND (series_prefs IS NULL OR series_prefs = '' OR series_prefs LIKE %s)",
            '%' . $wpdb->esc_like((string) $scope_id) . '%'
        ));

        $matching_ids = [];
        foreach ($rows as $row) {
            if (empty($row->series_prefs)) {
                $matching_ids[] = (int) $row->id;
                continue;
            }
            $prefs = json_decode($row->series_prefs, true);
            if (is_array($prefs) && in_array((int) $scope_id, array_map('absint', $prefs), true)) {
                $matching_ids[] = (int) $row->id;
            }
        }

        return $matching_ids;
    }

    private function toocheke_notifications_queue_for_subscribers($post_id, $subscriber_ids)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_queue';
        $now   = current_time('mysql');

        foreach ($subscriber_ids as $subscriber_id) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (subscriber_id, post_id, status, attempts, created_at) VALUES (%d, %d, 'pending', 0, %s)",
                $subscriber_id,
                $post_id,
                $now
            ));
        }
    }

    /* =========================================================================
       PASS 3: WP-CRON BATCH SENDER
    ========================================================================= */

    public function toocheke_notifications_register_cron_schedule($schedules)
    {
        if (! isset($schedules['toocheke_notify_five_minutes'])) {
            $schedules['toocheke_notify_five_minutes'] = [
                'interval' => 300,
                'display'  => __('Every 5 Minutes (Toocheke Notifications)', 'toocheke-companion'),
            ];
        }
        return $schedules;
    }

    public function toocheke_notifications_maybe_schedule_cron()
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            return;
        }
        if (! wp_next_scheduled('toocheke_notifications_send_queue')) {
            wp_schedule_event(time(), 'toocheke_notify_five_minutes', 'toocheke_notifications_send_queue');
        }
    }

    /**
     * Called on plugin deactivation (wired in toocheke-companion.php) so
     * a deactivated Companion doesn't leave an orphaned recurring cron
     * event behind.
     */
    public function toocheke_notifications_deactivation_cleanup()
    {
        wp_clear_scheduled_hook('toocheke_notifications_send_queue');
    }

    /**
     * Drains up to $batch_size pending queue rows per cron tick. Kept
     * deliberately small and frequent (20 every 5 minutes, rather than a
     * large batch once an hour) so a popular post with many subscribers
     * doesn't create one big send spike.
     */
    public function toocheke_notifications_process_queue_batch()
    {
        global $wpdb;
        $queue_table       = $wpdb->prefix . 'toocheke_notify_queue';
        $subscribers_table = $wpdb->prefix . 'toocheke_notify_subscribers';
        $batch_size        = 20;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT q.id AS queue_id, q.subscriber_id, q.post_id, q.attempts, s.email, s.token, s.status AS subscriber_status
             FROM {$queue_table} q
             INNER JOIN {$subscribers_table} s ON s.id = q.subscriber_id
             WHERE q.status = 'pending'
             ORDER BY q.id ASC
             LIMIT %d",
            $batch_size
        ));

        if (! $rows) {
            return;
        }

        foreach ($rows as $row) {
            if ('confirmed' !== $row->subscriber_status) {
                $wpdb->update($queue_table, ['status' => 'failed'], ['id' => $row->queue_id]);
                continue;
            }

            $post = get_post($row->post_id);
            if (! $post || 'publish' !== $post->post_status) {
                $wpdb->update($queue_table, ['status' => 'failed'], ['id' => $row->queue_id]);
                continue;
            }

            $sent = $this->toocheke_notifications_send_notification_email($row->email, $row->token, $post);

            if ($sent) {
                $wpdb->update($queue_table, ['status' => 'sent', 'sent_at' => current_time('mysql')], ['id' => $row->queue_id]);
                continue;
            }

            $attempts = (int) $row->attempts + 1;
            $new_status = ($attempts >= 3) ? 'failed' : 'pending';
            $wpdb->update($queue_table, ['status' => $new_status, 'attempts' => $attempts], ['id' => $row->queue_id]);
        }
    }

    /**
     * Builds the notification email's heading/content/links, then hands
     * off to the shared toocheke_notifications_send_email() wrapper —
     * every email this feature sends (this one and the confirmation
     * email above) goes through that one method now, so template
     * rendering, HTML content-type, and error logging/capture all stay
     * in exactly one place.
     */
    private function toocheke_notifications_send_notification_email($email, $token, $post)
    {
        $post_type  = $post->post_type;
        $type_label = $this->toocheke_notifications_get_post_type_label($post_type);
        $link       = $this->toocheke_notifications_get_notification_link($post);

        $unsubscribe_page_id = (int) get_option('toocheke-notify-unsubscribe-page');
        $unsubscribe_link    = $unsubscribe_page_id ? add_query_arg('token', $token, get_permalink($unsubscribe_page_id)) : home_url('/');

        $manage_page_id = (int) get_option('toocheke-notify-manage-page');
        $manage_link    = $manage_page_id ? add_query_arg('token', $token, get_permalink($manage_page_id)) : '';

        $body_content = $this->toocheke_notifications_build_email_content($post, $post_type);

        $heading = sprintf(
            /* translators: 1: post type label (e.g. "Comic"), 2: post title */
            __('New %1$s: %2$s', 'toocheke-companion'),
            $type_label,
            get_the_title($post)
        );

        return $this->toocheke_notifications_send_email($email, $heading, $body_content, $link, __('Read it here', 'toocheke-companion'), $unsubscribe_link, $manage_link);
    }

    /**
     * The one place every email this feature sends actually goes through
     * — wraps the given content in the branded HTML template, sends it
     * as HTML (scoped tightly to just this one wp_mail() call via
     * add_filter/remove_filter, so it never affects any other plugin's
     * or WP core's own mail), and captures the underlying WP_Error
     * message on failure (via the same tightly-scoped
     * add_action/remove_action technique on wp_mail_failed) so the
     * admin-facing error log below has an actual reason, not just
     * "failed."
     */
    private function toocheke_notifications_send_email($to, $subject, $body_html, $cta_url, $cta_label, $unsubscribe_url, $manage_url = '')
    {
        $html = $this->toocheke_notifications_render_email_html($subject, $body_html, $cta_url, $cta_label, $unsubscribe_url, $manage_url);

        $this->toocheke_notify_last_mail_error = '';
        add_filter('wp_mail_content_type', [$this, 'toocheke_notifications_set_html_content_type']);
        add_action('wp_mail_failed', [$this, 'toocheke_notifications_capture_mail_error']);

        $sent = wp_mail($to, $subject, $html);

        remove_filter('wp_mail_content_type', [$this, 'toocheke_notifications_set_html_content_type']);
        remove_action('wp_mail_failed', [$this, 'toocheke_notifications_capture_mail_error']);

        if (! $sent) {
            $this->toocheke_notifications_log_error(sprintf(
                /* translators: 1: recipient email address, 2: underlying error detail, if known */
                __('Failed to send to %1$s: %2$s', 'toocheke-companion'),
                $to,
                $this->toocheke_notify_last_mail_error ? $this->toocheke_notify_last_mail_error : __('no further details available', 'toocheke-companion')
            ));
        }

        return $sent;
    }

    /**
     * Holds the most recent wp_mail_failed error message, captured only
     * during the brief window the hook is attached inside
     * toocheke_notifications_send_email() above.
     */
    private $toocheke_notify_last_mail_error = '';

    public function toocheke_notifications_capture_mail_error($wp_error)
    {
        if (is_wp_error($wp_error)) {
            $this->toocheke_notify_last_mail_error = $wp_error->get_error_message();
        }
    }

    public function toocheke_notifications_set_html_content_type()
    {
        return 'text/html';
    }

    /**
     * Wraps the per-post-type body content (see
     * toocheke_notifications_build_email_content()) in the actual
     * branded email layout: grey background, a centered logo above a
     * 600px white rounded-corner card, the content itself, a styled CTA
     * button, an optional signature, and a styled unsubscribe
     * button/link. Table-based layout with all-inline styles
     * deliberately — email clients (particularly Outlook desktop) don't
     * reliably support external/embedded stylesheets or flexbox/grid,
     * so tables + inline styles remain the only broadly compatible
     * approach. border-radius degrades gracefully to square corners in
     * the handful of clients that don't support it (mainly older
     * Outlook), which is standard, accepted practice for HTML email.
     */
    private function toocheke_notifications_render_email_html($heading, $body_html, $cta_url, $cta_label, $unsubscribe_url, $manage_url)
    {
        $logo_url  = get_option('toocheke-notify-email-logo', '');
        $signature = get_option('toocheke-notify-email-signature', '');
        $site_name = get_bloginfo('name');

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo esc_html($heading); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f0f0f0;font-family:Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f0f0;">
<tr>
<td align="center" style="padding:30px 16px;">

<?php if ($logo_url) : ?>
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
<tr>
<td align="center" style="padding-bottom:20px;">
<img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width:220px;max-height:90px;display:block;border:0;" />
</td>
</tr>
</table>
<?php endif; ?>

<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;">
<tr>
<td style="padding:32px;color:#333333;font-size:16px;line-height:1.6;border-radius:12px;">

<h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#111111;font-family:Arial, Helvetica, sans-serif;"><?php echo esc_html($heading); ?></h1>

<div style="margin-bottom:24px;">
<?php echo $body_html; ?>
</div>

<table role="presentation" cellpadding="0" cellspacing="0">
<tr>
<td style="border-radius:6px;background-color:#10ae98;">
<a href="<?php echo esc_url($cta_url); ?>" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;border-radius:6px;font-family:Arial, Helvetica, sans-serif;">
<?php echo esc_html($cta_label); ?>
</a>
</td>
</tr>
</table>

<p style="margin:12px 0 0;font-size:12px;color:#999999;word-break:break-all;overflow-wrap:break-word;line-height:1.4;">
<?php esc_html_e('Or copy and paste this link into your browser:', 'toocheke-companion'); ?><br />
<a href="<?php echo esc_url($cta_url); ?>" style="color:#999999;word-break:break-all;overflow-wrap:break-word;"><?php echo esc_html($cta_url); ?></a>
</p>

<?php if ($signature) : ?>
<div style="margin-top:32px;padding-top:20px;border-top:1px solid #eeeeee;color:#555555;font-size:14px;line-height:1.5;">
<?php echo wp_kses_post(wpautop($signature)); ?>
</div>
<?php endif; ?>

</td>
</tr>
</table>

<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
<tr>
<td align="center" style="padding-top:20px;font-size:12px;color:#888888;font-family:Arial, Helvetica, sans-serif;">
<?php if ($manage_url) : ?>
<a href="<?php echo esc_url($manage_url); ?>" style="color:#888888;text-decoration:underline;"><?php esc_html_e('Manage subscription', 'toocheke-companion'); ?></a>
&nbsp;&middot;&nbsp;
<?php endif; ?>
<a href="<?php echo esc_url($unsubscribe_url); ?>" style="display:inline-block;margin-top:10px;padding:8px 16px;background-color:#e2e2e2;color:#555555;text-decoration:none;border-radius:4px;font-size:12px;"><?php esc_html_e('Unsubscribe', 'toocheke-companion'); ?></a>
</td>
</tr>
</table>

</td>
</tr>
</table>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Content per post type:
     * - manga_chapter: the featured image as a real embedded <img>
     *   (Pass 3 only linked to the image URL as plain text, since a
     *   plain-text email has no way to embed one) + the 'notes' meta
     *   field (the same field Bluesky's auto-posting already uses as
     *   its description — see toocheke_bluesky_get_alt_text() in
     *   class-toocheke-companion-bluesky.php).
     * - everything else (post, comic, series, manga_series,
     *   manga_volume): the full post content, run through the_content
     *   filter so shortcodes/embeds expand the same way they would on
     *   the live page, then passed through wp_kses_post() — this keeps
     *   images and formatting intact (Pass 3 stripped all tags,
     *   including any <img>, since that pass was plain text) while still
     *   stripping anything genuinely unsafe or email-incompatible
     *   (scripts, iframes, etc).
     */
    private function toocheke_notifications_build_email_content($post, $post_type)
    {
        if ('manga_chapter' === $post_type) {
            $notes      = trim((string) get_post_meta($post->ID, 'notes', true));
            $notes_html = $notes ? wpautop(esc_html($notes)) : '';
            $image_html = '';

            if (has_post_thumbnail($post)) {
                $image_url  = get_the_post_thumbnail_url($post, 'large');
                $image_html = '<p style="margin:0 0 16px;"><img src="' . esc_url($image_url) . '" alt="" style="max-width:100%;height:auto;display:block;border-radius:6px;border:0;" /></p>';
            }

            return $image_html . $notes_html;
        }

        $content = apply_filters('the_content', $post->post_content);
        return wp_kses_post($content);
    }

    /**
     * Mirrors toocheke_bluesky_get_post_url() in
     * class-toocheke-companion-bluesky.php exactly, so a comic's link in
     * a notification email matches the sid-scoped link used everywhere
     * else across the plugin.
     */
    private function toocheke_notifications_get_notification_link($post)
    {
        $permalink = get_permalink($post);

        if ('comic' !== $post->post_type) {
            return $permalink;
        }

        $series_id = (int) wp_get_post_parent_id($post->ID);
        return $series_id > 0 ? add_query_arg('sid', $series_id, $permalink) : $permalink;
    }

    private function toocheke_notifications_get_post_type_label($post_type)
    {
        $labels = [
            'post'          => __('Post', 'toocheke-companion'),
            'comic'         => __('Comic', 'toocheke-companion'),
            'manga_chapter' => __('Manga Chapter', 'toocheke-companion'),
            'series'        => __('Series', 'toocheke-companion'),
            'manga_series'  => __('Manga Series', 'toocheke-companion'),
            'manga_volume'  => __('Manga Volume', 'toocheke-companion'),
        ];

        return isset($labels[$post_type]) ? $labels[$post_type] : ucfirst(str_replace('_', ' ', $post_type));
    }

    /* =========================================================================
       CUMULATIVE, SITE-WIDE ERROR NOTICE
       Deliberately mirrors the exact pattern already used for Bluesky
       posting errors -- see toocheke_bluesky_log_error() and friends in
       class-toocheke-companion-bluesky.php -- just under this feature's
       own option name so dismissing one never clears the other.
    ========================================================================= */

    private function toocheke_notifications_log_error($message)
    {
        $errors = get_option('toocheke-notifications-errors', []);
        if (! is_array($errors)) {
            $errors = [];
        }

        $errors[] = [
            'time'    => current_time('mysql'),
            'message' => $message,
        ];

        // Same cap as Bluesky's error log, for the same reason: a
        // persistently failing mail setup shouldn't grow this option
        // indefinitely between dismissals.
        if (count($errors) > 20) {
            $errors = array_slice($errors, -20);
        }

        update_option('toocheke-notifications-errors', $errors);
    }

    public function toocheke_notifications_admin_notice_errors()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $errors = get_option('toocheke-notifications-errors', []);
        if (empty($errors)) {
            return;
        }

        $clear_url = wp_nonce_url(admin_url('admin-post.php?action=toocheke_notifications_clear_errors'), 'toocheke_notifications_clear_errors');
        $count     = count($errors);
        ?>
        <div class="notice notice-error">
            <p>
                <strong>
                    <?php
                    printf(
                        /* translators: %d: number of errors */
                        esc_html(_n('%d email notification error has occurred:', '%d email notification errors have occurred:', $count, 'toocheke-companion')),
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

    public function toocheke_notifications_handle_clear_errors()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'toocheke-companion'));
        }
        check_admin_referer('toocheke_notifications_clear_errors');

        delete_option('toocheke-notifications-errors');

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /* =========================================================================
       EMAIL SUBSCRIPTIONS ADMIN PAGE
       A separate page from the Notifications settings tab: lets the
       site admin see who's subscribed, delete an individual subscriber
       outright (not just mark them unsubscribed), and export the whole
       list as CSV. Registered under the existing Toocheke top-level menu
       (parent slug 'toocheke-menu', defined in
       class-toocheke-companion-settings-page.php) from its own
       admin_menu callback here, so this feature stays self-contained
       rather than requiring an edit to that file.

       Deliberately gated on 'manage_options' rather than the 'edit_posts'
       capability the rest of that menu uses -- this page can export a
       list of real people's email addresses, which warrants a tighter
       bar than the rest of the Toocheke admin menu.
    ========================================================================= */

    public function toocheke_notifications_register_admin_menu()
    {
        if (! $this->toocheke_notifications_is_premium_active()) {
            return;
        }

        add_submenu_page(
            'toocheke-menu',
            __('Email Subscriptions', 'toocheke-companion'),
            __('Email Subscriptions', 'toocheke-companion'),
            'manage_options',
            'toocheke-notify-subscribers',
            [$this, 'toocheke_notifications_render_subscribers_page'],
            // 'Dashboard' and 'Options' are registered (in that order,
            // with no explicit position) immediately before this hook
            // runs -- see the priority-1 admin_menu registration above.
            // Position 1.5 lands this between them (position 1, i.e.
            // 'Options') and whatever comes next ('Import: Comic
            // Easel', position 2).
            1.5
        );
    }

    public function toocheke_notifications_render_subscribers_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'toocheke-companion'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';

        $per_page     = 20;
        $current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset       = ($current_page - 1) * $per_page;

        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $valid_statuses = ['pending', 'confirmed', 'unsubscribed'];
        if (! in_array($status_filter, $valid_statuses, true)) {
            $status_filter = '';
        }

        $where = $status_filter ? $wpdb->prepare('WHERE status = %s', $status_filter) : '';

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");

        $subscribers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $counts = [
            'all'          => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'pending'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
            'confirmed'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'confirmed'"),
            'unsubscribed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'unsubscribed'"),
        ];

        $base_url   = admin_url('admin.php?page=toocheke-notify-subscribers');
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=toocheke_notifications_export_subscribers'), 'toocheke_notifications_export_subscribers');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Email Subscriptions', 'toocheke-companion'); ?></h1>
            <a href="<?php echo esc_url($export_url); ?>" class="page-title-action"><?php esc_html_e('Export CSV', 'toocheke-companion'); ?></a>
            <hr class="wp-header-end" />

            <ul class="subsubsub">
                <li><a href="<?php echo esc_url($base_url); ?>" <?php echo $status_filter === '' ? 'class="current"' : ''; ?>><?php esc_html_e('All', 'toocheke-companion'); ?> (<?php echo (int) $counts['all']; ?>)</a> |</li>
                <li><a href="<?php echo esc_url(add_query_arg('status', 'confirmed', $base_url)); ?>" <?php echo $status_filter === 'confirmed' ? 'class="current"' : ''; ?>><?php esc_html_e('Confirmed', 'toocheke-companion'); ?></a> (<?php echo (int) $counts['confirmed']; ?>) |</li>
                <li><a href="<?php echo esc_url(add_query_arg('status', 'pending', $base_url)); ?>" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>><?php esc_html_e('Pending', 'toocheke-companion'); ?></a> (<?php echo (int) $counts['pending']; ?>) |</li>
                <li><a href="<?php echo esc_url(add_query_arg('status', 'unsubscribed', $base_url)); ?>" <?php echo $status_filter === 'unsubscribed' ? 'class="current"' : ''; ?>><?php esc_html_e('Unsubscribed', 'toocheke-companion'); ?></a> (<?php echo (int) $counts['unsubscribed']; ?>)</li>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Email', 'toocheke-companion'); ?></th>
                        <th><?php esc_html_e('Status', 'toocheke-companion'); ?></th>
                        <th><?php esc_html_e('Subscribed', 'toocheke-companion'); ?></th>
                        <th><?php esc_html_e('Series Scope', 'toocheke-companion'); ?></th>
                        <th><?php esc_html_e('Action', 'toocheke-companion'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscribers)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No subscribers found.', 'toocheke-companion'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($subscribers as $subscriber) : ?>
                            <?php
                            $delete_url = wp_nonce_url(
                                admin_url('admin-post.php?action=toocheke_notifications_delete_subscriber&subscriber_id=' . (int) $subscriber->id),
                                'toocheke_notifications_delete_subscriber_' . (int) $subscriber->id
                            );
                            ?>
                            <tr>
                                <td><?php echo esc_html($subscriber->email); ?></td>
                                <td><?php echo esc_html(ucfirst($subscriber->status)); ?></td>
                                <td><?php echo esc_html($subscriber->created_at); ?></td>
                                <td><?php echo esc_html($this->toocheke_notifications_describe_series_prefs($subscriber->series_prefs)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js(__('Delete this subscriber permanently? This cannot be undone.', 'toocheke-companion')); ?>');">
                                        <?php esc_html_e('Delete', 'toocheke-companion'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $total_pages = (int) ceil($total / $per_page);
            if ($total_pages > 1) :
                $page_links = paginate_links([
                    'base'      => add_query_arg('paged', '%#%', $status_filter ? add_query_arg('status', $status_filter, $base_url) : $base_url),
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                ]);
                ?>
                <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post($page_links); ?></div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Resolves a subscriber's series_prefs (a JSON array of post IDs, or
     * NULL/empty meaning "everything") into a human-readable string for
     * the admin table above -- e.g. "All" or "Trinity, Volume 2".
     */
    private function toocheke_notifications_describe_series_prefs($series_prefs_json)
    {
        if (empty($series_prefs_json)) {
            return __('All', 'toocheke-companion');
        }

        $ids = array_map('absint', (array) json_decode($series_prefs_json, true));
        if (empty($ids)) {
            return __('All', 'toocheke-companion');
        }

        $titles = [];
        foreach ($ids as $id) {
            $title = get_the_title($id);
            $titles[] = $title ? $title : sprintf('#%d', $id);
        }

        return implode(', ', $titles);
    }

    public function toocheke_notifications_handle_delete_subscriber()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'toocheke-companion'));
        }

        $subscriber_id = isset($_GET['subscriber_id']) ? absint($_GET['subscriber_id']) : 0;
        check_admin_referer('toocheke_notifications_delete_subscriber_' . $subscriber_id);

        if ($subscriber_id) {
            global $wpdb;
            $subscribers_table = $wpdb->prefix . 'toocheke_notify_subscribers';
            $queue_table       = $wpdb->prefix . 'toocheke_notify_queue';

            // Remove any pending/sent queue rows tied to this subscriber
            // first, so deleting them doesn't leave orphaned rows behind
            // in the send queue.
            $wpdb->delete($queue_table, ['subscriber_id' => $subscriber_id]);
            $wpdb->delete($subscribers_table, ['id' => $subscriber_id]);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=toocheke-notify-subscribers'));
        exit;
    }

    /**
     * Streams every subscriber as a CSV download. CSV rather than a
     * native .xlsx: it opens directly in Excel with no extra library
     * needed, and it's the universally-accepted import format for every
     * other email platform (Mailchimp, ConvertKit, Brevo, etc.), so one
     * export covers both "open in Excel" and "import elsewhere" without
     * a second file format to maintain.
     */
    public function toocheke_notifications_handle_export_subscribers()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'toocheke-companion'));
        }
        check_admin_referer('toocheke_notifications_export_subscribers');

        global $wpdb;
        $table = $wpdb->prefix . 'toocheke_notify_subscribers';
        $subscribers = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=toocheke-email-subscribers-' . gmdate('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'Status', 'Series Scope', 'Subscribed At', 'Confirmed At']);

        foreach ($subscribers as $subscriber) {
            fputcsv($output, [
                $subscriber->email,
                $subscriber->status,
                $this->toocheke_notifications_describe_series_prefs($subscriber->series_prefs),
                $subscriber->created_at,
                $subscriber->confirmed_at ? $subscriber->confirmed_at : '',
            ]);
        }

        fclose($output);
        exit;
    }
}
