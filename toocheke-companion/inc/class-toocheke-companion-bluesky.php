<?php
/**
 * Toocheke Companion — Bluesky auto-posting.
 *
 * Shares "comic" and "manga_chapter" posts to Bluesky, either the moment
 * they're published or, optionally, at random from the back-catalogue on a
 * recurring schedule so nothing repeats until the whole archive has been
 * cycled through once.
 */

if (! defined('ABSPATH')) { exit; }

// Every Bluesky/AT-Protocol endpoint used by this file is built from this
// one constant.
if (! defined('TOOCHEKE_BLUESKY_API_BASE')) {
    define('TOOCHEKE_BLUESKY_API_BASE', 'https://bsky.social/xrpc/');
}

trait Toocheke_Companion_Bluesky
{
    // Called once from init() in toocheke-companion.php.

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

    // Called from toocheke_init_option_fields() in
    // class-toocheke-companion-settings-page.php.

    // $active_subsection comes from toocheke_init_option_fields(), which
    // also renders the subnav links off the same subsection list, so
    // only the matching subsection's fields render/save at a time.
    public function toocheke_bluesky_register_settings_fields($active_subsection = 'connection')
    {
        if ('connection' === $active_subsection) {
            // --- Connection ---
            add_settings_section('toocheke_bluesky_connection_section', 'Bluesky Connection', [$this, 'toocheke_bluesky_connection_section_message'], 'toocheke-options-page');

            add_settings_field('toocheke-bluesky-handle', 'Bluesky Handle', [$this, 'toocheke_bluesky_handle_field'], 'toocheke-options-page', 'toocheke_bluesky_connection_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-handle', ['sanitize_callback' => 'sanitize_text_field']);

            add_settings_field('toocheke-bluesky-app-password', 'App Password', [$this, 'toocheke_bluesky_app_password_field'], 'toocheke-options-page', 'toocheke_bluesky_connection_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-app-password', ['sanitize_callback' => 'sanitize_text_field']);
        }

        if ('automatic_posting' === $active_subsection) {
            // --- Automatic posting on publish ---
            add_settings_section('toocheke_bluesky_posting_section', 'Automatic Posting', [$this, 'toocheke_bluesky_posting_section_message'], 'toocheke-options-page');

            add_settings_field('toocheke-bluesky-enable-comics', 'Post comics to Bluesky?', [$this, 'toocheke_bluesky_enable_comics_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_posting_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-enable-comics', ['sanitize_callback' => 'absint']);

            add_settings_field('toocheke-bluesky-enable-manga-chapters', 'Post manga chapters to Bluesky?', [$this, 'toocheke_bluesky_enable_manga_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_posting_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-enable-manga-chapters', ['sanitize_callback' => 'absint']);

            $this->toocheke_bluesky_register_filter_fields('auto', 'toocheke_bluesky_posting_section');
        }

        if ('post_format' === $active_subsection) {
            // --- Post format ---
            add_settings_section('toocheke_bluesky_format_section', 'Post Format', [$this, 'toocheke_bluesky_format_section_message'], 'toocheke-options-page');

            add_settings_field('toocheke-bluesky-post-format', 'How should posts appear on Bluesky?', [$this, 'toocheke_bluesky_post_format_radio'], 'toocheke-options-page', 'toocheke_bluesky_format_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-post-format', ['sanitize_callback' => 'sanitize_text_field']);

            add_settings_field('toocheke-bluesky-message-template', 'Message Template', [$this, 'toocheke_bluesky_message_template_field'], 'toocheke-options-page', 'toocheke_bluesky_format_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-message-template', ['sanitize_callback' => 'sanitize_textarea_field']);

            add_settings_field('toocheke-bluesky-card-caption', 'Card Caption', [$this, 'toocheke_bluesky_card_caption_field'], 'toocheke-options-page', 'toocheke_bluesky_format_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-card-caption', ['sanitize_callback' => 'sanitize_textarea_field']);
        }

        if ('random_posting' === $active_subsection) {
            // --- Random archive posting ---
            add_settings_section('toocheke_bluesky_random_section', 'Random Archive Posting', [$this, 'toocheke_bluesky_random_section_message'], 'toocheke-options-page');

            add_settings_field('toocheke-bluesky-random-comics', 'Randomly re-post comics from the archive?', [$this, 'toocheke_bluesky_random_comics_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_random_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-random-comics', ['sanitize_callback' => 'absint']);

            add_settings_field('toocheke-bluesky-random-manga-chapters', 'Randomly re-post manga chapters from the archive?', [$this, 'toocheke_bluesky_random_manga_checkbox'], 'toocheke-options-page', 'toocheke_bluesky_random_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-random-manga-chapters', ['sanitize_callback' => 'absint']);

            add_settings_field('toocheke-bluesky-random-frequency', 'Post every...', [$this, 'toocheke_bluesky_random_frequency_field'], 'toocheke-options-page', 'toocheke_bluesky_random_section');
            register_setting('toocheke-settings', 'toocheke-bluesky-random-frequency-number', ['sanitize_callback' => 'absint']);
            register_setting('toocheke-settings', 'toocheke-bluesky-random-frequency-unit', ['sanitize_callback' => 'sanitize_text_field']);

            $this->toocheke_bluesky_register_filter_fields('random', 'toocheke_bluesky_random_section');
        }
    }

    // Lets the admin scope Automatic/Random posting to specific
    // Series/Collections/Chapters or Manga Series/Volumes. Matching is
    // OR across everything selected. Not applied on the manual "Post to
    // Bluesky Now" checkbox — that's an explicit override.

    // Registers the mode radio + filter fields for one context ('auto'
    // or 'random') — shared so both stay structurally identical.
    private function toocheke_bluesky_register_filter_fields($context, $section_id)
    {
        $label_prefix = ('auto' === $context) ? __('auto-posted', 'toocheke-companion') : __('eligible for random re-posting', 'toocheke-companion');

        add_settings_field(
            "toocheke-bluesky-{$context}-comic-filter",
            sprintf(
                /* translators: %s: "auto-posted" or "eligible for random re-posting" */
                __('Which comics should be %s?', 'toocheke-companion'),
                $label_prefix
            ),
            [$this, 'toocheke_bluesky_comic_filter_field'],
            'toocheke-options-page',
            $section_id,
            ['context' => $context]
        );
        register_setting('toocheke-settings', "toocheke-bluesky-{$context}-comic-filter-mode", ['sanitize_callback' => [$this, 'toocheke_bluesky_sanitize_filter_mode'], 'default' => 'all']);
        register_setting('toocheke-settings', "toocheke-bluesky-{$context}-comic-filter-series", ['sanitize_callback' => [$this, 'toocheke_bluesky_sanitize_filter_ids']]);
        register_setting('toocheke-settings', "toocheke-bluesky-{$context}-comic-filter-collections", ['sanitize_callback' => [$this, 'toocheke_bluesky_sanitize_filter_ids']]);
        register_setting('toocheke-settings', "toocheke-bluesky-{$context}-comic-filter-chapters", ['sanitize_callback' => [$this, 'toocheke_bluesky_sanitize_filter_ids']]);

        add_settings_field(
            "toocheke-bluesky-{$context}-manga-filter",
            sprintf(
                /* translators: %s: "auto-posted" or "eligible for random re-posting" */
                __('Which manga chapters should be %s?', 'toocheke-companion'),
                $label_prefix
            ),
            [$this, 'toocheke_bluesky_manga_filter_field'],
            'toocheke-options-page',
            $section_id,
            ['context' => $context]
        );
        register_setting('toocheke-settings', "toocheke-bluesky-{$context}-manga-filter-mode", ['sanitize_callback' => [$this, 'toocheke_bluesky_sanitize_filter_mode'], 'default' => 'all']);
        register_setting('toocheke-settings', "toocheke-bluesky-{$context}-manga-filter-series", ['sanitize_callback' => [$this, 'toocheke_bluesky_sanitize_filter_ids']]);
        register_setting('toocheke-settings', "toocheke-bluesky-{$context}-manga-filter-volumes", ['sanitize_callback' => [$this, 'toocheke_bluesky_sanitize_filter_ids']]);
    }

    public function toocheke_bluesky_sanitize_filter_mode($value)
    {
        return ('selected' === $value) ? 'selected' : 'all';
    }

    public function toocheke_bluesky_sanitize_filter_ids($value)
    {
        if (! is_array($value)) {
            return [];
        }
        return array_values(array_unique(array_map('absint', $value)));
    }

    public function toocheke_bluesky_comic_filter_field($args)
    {
        $this->toocheke_bluesky_render_filter_ui($args['context'], 'comic');
    }

    public function toocheke_bluesky_manga_filter_field($args)
    {
        $this->toocheke_bluesky_render_filter_ui($args['context'], 'manga');
    }

    // "Post everything" / "Only post these:" radio plus one collapsible
    // pill group per relevant taxonomy/post type. $type is 'comic' or
    // 'manga' — only changes which groups get built.
    private function toocheke_bluesky_render_filter_ui($context, $type)
    {
        $mode_option = "toocheke-bluesky-{$context}-{$type}-filter-mode";
        $mode        = get_option($mode_option, 'all');

        if ('comic' === $type) {
            $groups = [
                'series'      => [
                    'label' => __('Series', 'toocheke-companion'),
                    'items' => get_posts(['post_type' => 'series', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']),
                ],
                'collections' => [
                    'label' => __('Collections', 'toocheke-companion'),
                    'items' => get_terms(['taxonomy' => 'collections', 'hide_empty' => false]),
                ],
                'chapters'    => [
                    'label' => __('Chapters', 'toocheke-companion'),
                    'items' => get_terms(['taxonomy' => 'chapters', 'hide_empty' => false]),
                ],
            ];
        } else {
            $groups = [
                'series'  => [
                    'label' => __('Manga Series', 'toocheke-companion'),
                    'items' => get_posts(['post_type' => 'manga_series', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']),
                ],
                'volumes' => [
                    'label' => __('Manga Volumes', 'toocheke-companion'),
                    'items' => get_posts(['post_type' => 'manga_volume', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']),
                ],
            ];
        }
        ?>
        <div class="toocheke-bluesky-filter">
            <p>
                <label>
                    <input type="radio" name="<?php echo esc_attr($mode_option); ?>" value="all" <?php checked('all', $mode); ?> class="toocheke-bluesky-filter-mode-radio" />
                    <?php esc_html_e('Post everything', 'toocheke-companion'); ?>
                </label>
                <br />
                <label>
                    <input type="radio" name="<?php echo esc_attr($mode_option); ?>" value="selected" <?php checked('selected', $mode); ?> class="toocheke-bluesky-filter-mode-radio" />
                    <?php esc_html_e('Only these:', 'toocheke-companion'); ?>
                </label>
            </p>
            <div class="toocheke-bluesky-filter-groups"<?php echo ('selected' !== $mode) ? ' style="display:none;"' : ''; ?>>
                <?php foreach ($groups as $key => $group) :
                    $option_name  = "toocheke-bluesky-{$context}-{$type}-filter-{$key}";
                    $selected_ids = array_map('absint', (array) get_option($option_name, []));
                    ?>
                    <details class="toocheke-bluesky-filter-group"<?php echo esc_attr($selected_ids ? ' open' : ''); ?>>
                        <summary>
                            <?php
                            printf(
                                /* translators: 1: group label (e.g. "Series"), 2: number currently selected */
                                esc_html__('%1$s (%2$d selected)', 'toocheke-companion'),
                                esc_html($group['label']),
                                count($selected_ids)
                            );
                            ?>
                        </summary>
                        <div class="toocheke-pill-group">
                            <?php if (empty($group['items'])) : ?>
                                <p class="description"><?php esc_html_e('None found yet.', 'toocheke-companion'); ?></p>
                            <?php endif; ?>
                            <?php foreach ($group['items'] as $item) :
                                $item_id    = isset($item->ID) ? (int) $item->ID : (int) $item->term_id;
                                $item_title = isset($item->post_title) ? $item->post_title : $item->name;
                                $is_selected = in_array($item_id, $selected_ids, true);
                                ?>
                                <label class="toocheke-pill<?php echo esc_attr($is_selected ? ' is-selected' : ''); ?>">
                                    <input type="checkbox" name="<?php echo esc_attr($option_name); ?>[]" value="<?php echo esc_attr($item_id); ?>" <?php checked($is_selected); ?> />
                                    <?php echo esc_html($item_title); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * The single entry point both the automatic-publish path and the
     * random-archive selection use to decide whether a given post qualifies.
     */
    private function toocheke_bluesky_post_passes_filter($post_id, $post_type, $context)
    {
        if ('comic' === $post_type) {
            return $this->toocheke_bluesky_comic_passes_filter($post_id, $context);
        }
        if ('manga_chapter' === $post_type) {
            return $this->toocheke_bluesky_manga_chapter_passes_filter($post_id, $context);
        }
        return true;
    }

    private function toocheke_bluesky_comic_passes_filter($post_id, $context)
    {
        $mode = get_option("toocheke-bluesky-{$context}-comic-filter-mode", 'all');
        if ('selected' !== $mode) {
            return true;
        }

        $selected_series      = array_map('absint', (array) get_option("toocheke-bluesky-{$context}-comic-filter-series", []));
        $selected_collections = array_map('absint', (array) get_option("toocheke-bluesky-{$context}-comic-filter-collections", []));
        $selected_chapters    = array_map('absint', (array) get_option("toocheke-bluesky-{$context}-comic-filter-chapters", []));

        if (! $selected_series && ! $selected_collections && ! $selected_chapters) {
            // "Only these" is selected but nothing has actually been
            // chosen yet -- treat as "nothing qualifies" rather than
            // silently falling back to "everything," so an incomplete
            // setup doesn't surprise anyone by posting more than intended.
            return false;
        }

        if ($selected_series) {
            // Mirrors the same post_parent-as-series relationship used
            // everywhere else in this file (see
            // toocheke_bluesky_get_post_url()) -- not the separate legacy
            // 'series_id' meta field.
            $parent_id = (int) wp_get_post_parent_id($post_id);
            if ($parent_id && in_array($parent_id, $selected_series, true)) {
                return true;
            }
        }

        if ($selected_collections && $this->toocheke_bluesky_post_has_any_term($post_id, 'collections', $selected_collections)) {
            return true;
        }

        if ($selected_chapters && $this->toocheke_bluesky_post_has_any_term($post_id, 'chapters', $selected_chapters)) {
            return true;
        }

        return false;
    }

    private function toocheke_bluesky_manga_chapter_passes_filter($post_id, $context)
    {
        $mode = get_option("toocheke-bluesky-{$context}-manga-filter-mode", 'all');
        if ('selected' !== $mode) {
            return true;
        }

        $selected_manga_series = array_map('absint', (array) get_option("toocheke-bluesky-{$context}-manga-filter-series", []));
        $selected_manga_volumes = array_map('absint', (array) get_option("toocheke-bluesky-{$context}-manga-filter-volumes", []));

        if (! $selected_manga_series && ! $selected_manga_volumes) {
            return false;
        }

        // Same meta fields the manga_chapter metabox itself saves (see
        // class-toocheke-companion-metaboxes.php) -- 'series_id' here
        // points to the parent Manga Series, 'volume_id' to the Manga
        // Volume.
        $manga_series_id = (int) get_post_meta($post_id, 'series_id', true);
        if ($manga_series_id && in_array($manga_series_id, $selected_manga_series, true)) {
            return true;
        }

        $manga_volume_id = (int) get_post_meta($post_id, 'volume_id', true);
        if ($manga_volume_id && in_array($manga_volume_id, $selected_manga_volumes, true)) {
            return true;
        }

        return false;
    }

    private function toocheke_bluesky_post_has_any_term($post_id, $taxonomy, array $selected_term_ids)
    {
        $terms = get_the_terms($post_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            return false;
        }
        $term_ids = wp_list_pluck($terms, 'term_id');
        return (bool) array_intersect($term_ids, $selected_term_ids);
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
                            /* translators: %s: app passwords settings URL */
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

        // Anti-flash: hides whichever of the two format-specific rows
        // below shouldn't show, computed here from the CURRENTLY SAVED
        // format -- server-side, so there's nothing to flash on initial
        // page load. js/bluesky-admin.js's toggleFormatRows() still
        // handles the live case (changing the radio before saving);
        // this just makes sure the very first paint already matches
        // whatever's saved, instead of briefly showing both rows before
        // JS decides which one to hide. :has() is safe to rely on here
        // (unlike on the public-facing front end) since wp-admin only
        // needs to support modern, evergreen browsers.
        $is_card = ('card' === get_option('toocheke-bluesky-post-format', 'text_image'));
        ?>
        <style>
            <?php if ($is_card) : ?>
            tr:has(#toocheke-bluesky-template-row) { display: none; }
            <?php else : ?>
            tr:has(#toocheke-bluesky-card-caption-row) { display: none; }
            <?php endif; ?>
        </style>
        <?php
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

        // Same anti-flash technique as toocheke_bluesky_format_section_message()
        // above -- computed server-side from the saved checkboxes, so
        // the "Post every..." row never briefly flashes visible on load
        // when neither random-posting checkbox is actually on.
        $show_frequency = get_option('toocheke-bluesky-random-comics') || get_option('toocheke-bluesky-random-manga-chapters');
        if (! $show_frequency) :
            ?>
            <style>
                tr:has(#toocheke-bluesky-frequency-row) { display: none; }
            </style>
            <?php
        endif;

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

        // Post Filtering UI (pill-toggle Series/Collections/Chapters and
        // Manga Series/Volumes) only lives on these two subsections.
        if (! empty($_GET['subsection']) && in_array($_GET['subsection'], ['automatic_posting', 'random_posting'], true)) {
            $filter_css_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'css/toocheke-bluesky-filters.css';
            $filter_js_path  = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/toocheke-bluesky-filters.js';

            wp_enqueue_style(
                'toocheke-bluesky-filters',
                TOOCHEKE_COMPANION_PLUGIN_URL . 'css/toocheke-bluesky-filters.css',
                [],
                file_exists($filter_css_path) ? filemtime($filter_css_path) : TOOCHEKE_COMPANION_VERSION
            );
            wp_enqueue_script(
                'toocheke-bluesky-filters',
                TOOCHEKE_COMPANION_PLUGIN_URL . 'js/toocheke-bluesky-filters.js',
                ['jquery'],
                file_exists($filter_js_path) ? filemtime($filter_js_path) : TOOCHEKE_COMPANION_VERSION,
                true
            );
        }
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

    // Manual "Post to Bluesky (Again)" button — the one posting path
    // with no automatic trigger, for when a post's "posted" flag no
    // longer matches reality (e.g. deleted from Bluesky directly).
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

        // Built directly rather than via get_edit_post_link(), which can
        // silently return empty on a failed capability check, sending
        // the redirect to the plain Posts list instead of back here.
        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    // Flags a one-time success notice for this post's next edit-screen
    // load. A transient rather than a query arg, since WordPress core
    // controls the redirect after a normal Publish click.
    private function toocheke_bluesky_set_success_notice($post_id)
    {
        set_transient('toocheke_bluesky_success_' . get_current_user_id() . '_' . $post_id, 1, MINUTE_IN_SECONDS);
    }

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

    // Lets an author know their "Post to Bluesky Now" checkbox click was
    // a no-op because this post was already shared before.
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

    // Post IDs that toocheke_bluesky_maybe_post_on_publish() decided
    // should be posted, and whether that was the manual checkbox path.
    // Populated on transition_post_status, consumed on save_post
    // (priority 999) — see that method for why posting is deferred.
    //
    // @var array<int, bool> post ID => true if manual/checkbox path
    private $toocheke_bluesky_pending = [];

    // Only acts on comic/manga_chapter posts moving INTO "publish" for
    // the first time. Only DECIDES whether to post — doesn't post here,
    // since transition_post_status fires before metabox save handlers
    // write fields like transcript/hovertext, so the post could be built
    // from stale (pre-save) data. Actual posting happens on save_post
    // instead (toocheke_bluesky_maybe_post_after_save()), once every
    // metabox has saved.
    public function toocheke_bluesky_maybe_post_on_publish($new_status, $old_status, $post)
    {
        if (! in_array($post->post_type, ['comic', 'manga_chapter'], true)) {
            return;
        }
        // Only a fresh transition into publish, not a re-save.
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

        // The filtering feature only ever narrows the AUTOMATIC (scheduled)
        // path -- checking the manual "Post to Bluesky Now" box is the
        // author explicitly saying "yes, post this one," which always
        // overrides any filter. See the Post Filtering section under
        // Automatic Posting.
        if (! $is_manual && ! $this->toocheke_bluesky_post_passes_filter($post->ID, $post->post_type, 'auto')) {
            return;
        }

        // Record the decision; the actual posting happens later, on the
        // generic save_post hook (priority 999), once this post's own
        // field values are guaranteed to be fully saved.
        $this->toocheke_bluesky_pending[$post->ID] = $is_manual;
    }

    // Fires on save_post at priority 999, deliberately after this
    // plugin's own field-save handlers, so any field the author just
    // typed is already in the database by the time this runs. Fires for
    // every post save — the pending-list check below filters it down to
    // only the posts flagged on publish.
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

    // Schedules/unschedules the random-post cron based on whether
    // either random-posting option is enabled.
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

    // wp_schedule_event keeps the OLD interval for an already-queued run,
    // so re-schedule immediately when frequency settings change.
    public function toocheke_bluesky_reschedule_cron_on_settings_change()
    {
        if (wp_next_scheduled('toocheke_bluesky_random_post_cron')) {
            wp_clear_scheduled_hook('toocheke_bluesky_random_post_cron');
            wp_schedule_event(time(), 'toocheke_bluesky_random_interval', 'toocheke_bluesky_random_post_cron');
        }
    }

    // Posts one random, not-yet-posted comic or manga chapter — round
    // robin between types when both are enabled.
    public function toocheke_bluesky_run_random_post()
    {
        $comics_on = get_option('toocheke-bluesky-random-comics');
        $manga_on  = get_option('toocheke-bluesky-random-manga-chapters');
        if (! $comics_on && ! $manga_on) {
            return;
        }

        $last_type = get_option('toocheke-bluesky-last-random-type', '');
        if ($comics_on && $manga_on) {
            $order = ('comic' === $last_type) ? ['manga_chapter', 'comic'] : ['comic', 'manga_chapter'];
        } elseif ($comics_on) {
            $order = ['comic'];
        } else {
            $order = ['manga_chapter'];
        }

        foreach ($order as $post_type) {
            $post_id = $this->toocheke_bluesky_get_random_eligible_id($post_type);
            if (! $post_id) {
                continue;
            }

            $uri = $this->toocheke_bluesky_post_to_bluesky($post_id, $post_type);
            $this->toocheke_bluesky_mark_as_posted($post_id, is_string($uri) ? $uri : '');
            update_option('toocheke-bluesky-last-random-type', $post_type);
            return; // One post per cron run.
        }
    }

    // Random eligible post ID, resetting the pool if exhausted.
    public function toocheke_bluesky_get_random_eligible_id($post_type)
    {
        $post_id = $this->toocheke_bluesky_query_random_unposted_id($post_type);
        if ($post_id) {
            return $post_id;
        }

        // Empty pool: either nothing eligible exists yet, or the archive
        // needs to loop.
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

    // Picks a random eligible ID in PHP rather than `orderby => rand` in
    // WP_Query, which forces a full table sort and slows down as the
    // archive grows.
    public function toocheke_bluesky_query_random_unposted_id($post_type)
    {
        $ids = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => '_thumbnail_id', 'compare' => 'EXISTS'],
                ['key' => 'toocheke_bluesky_posted', 'compare' => 'NOT EXISTS'],
            ],
        ]);

        $ids = $this->toocheke_bluesky_filter_ids_for_random($ids, $post_type);

        if (empty($ids)) {
            return false;
        }

        return (int) $ids[array_rand($ids)];
    }

    // Distinguishes "reset the archive" from "nothing to post at all" —
    // the latter should never trigger a reset loop.
    public function toocheke_bluesky_type_has_any_eligible_post($post_type)
    {
        $ids = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
        ]);

        $ids = $this->toocheke_bluesky_filter_ids_for_random($ids, $post_type);

        return ! empty($ids);
    }

    // Applies the Random Archive Posting filter to a list of candidates.
    private function toocheke_bluesky_filter_ids_for_random(array $ids, $post_type)
    {
        if (empty($ids)) {
            return $ids;
        }

        return array_values(array_filter($ids, function ($id) use ($post_type) {
            return $this->toocheke_bluesky_post_passes_filter($id, $post_type, 'random');
        }));
    }

    // Bulk-clears "posted" flags for one post type in a single query.
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

    // Appends ?sid= for a comic assigned to a series (mirrors
    // toocheke_add_series_id_to_rss_permalink() in
    // class-toocheke-companion-rss-feeds.php) — manga chapters don't use
    // post_parent-as-series, so this only applies to 'comic'.
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

    // Shared entry point for both the publish-time trigger and the
    // random-repost cron job.
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

    // Link-card embed (title, description, image) plus a short caption
    // above it so the post doesn't read as a caption-less link drop.
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

    // Short caption above a Card-format post. No %%URL%% placeholder
    // here — the link card already carries the link, so any literal
    // "%%URL%%" left in the template is stripped rather than shown raw.
    // Still returns facets so %%CHARACTERS%%/%%LOCATIONS%%/%%TAGS%%
    // render as real hashtags.
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

    // Featured image embedded directly, with a visible clickable link.
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

    // Alt text for the Text+Image image. Comic: same fallback chain as
    // the Card description. Manga chapter: the "notes" field.
    private function toocheke_bluesky_get_alt_text($post_id, $post_type)
    {
        if ('comic' === $post_type) {
            return $this->toocheke_bluesky_get_comic_fallback_text($post_id);
        }
        // manga_chapter
        return trim((string) get_post_meta($post_id, 'notes', true));
    }

    // Description for the Card format. Comic: same fallback chain as the
    // Text+Image alt text. Manga chapter: the "notes" field, or empty.
    private function toocheke_bluesky_get_card_description($post_id, $post_type)
    {
        if ('manga_chapter' === $post_type) {
            return trim((string) get_post_meta($post_id, 'notes', true));
        }

        return $this->toocheke_bluesky_get_comic_fallback_text($post_id);
    }

    // Comic-only fallback chain, shared by the alt text and description
    // above: hovertext -> manually-set excerpt -> transcript (200 chars)
    // -> blog-post content (200 chars) -> empty.
    //
    // Excerpt is read with 'raw' context, not the default 'display' —
    // 'display' runs it through the post_excerpt filter, which another
    // plugin could hook to make it look non-empty when nothing was
    // actually typed, silently skipping the fallbacks below.
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

    // Resolves every %%PLACEHOLDER%% for a post into an associative
    // array, used by both the message-text and card-caption builders.
    // Values are plain text and left untruncated — length limits are
    // enforced by the callers, not here. Comic-only placeholders resolve
    // to '' on a manga_chapter post and vice versa, so a shared template
    // never leaves a raw %%TOKEN%% visible.
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

    // Builds one app.bsky.richtext.facet#tag per hashtag occurrence in
    // $text — the AT Protocol requires an explicit facet for any client
    // to treat a substring as a clickable tag; posting text starting
    // with "#" alone does nothing via the API. Built against the FINAL,
    // already-truncated text so a facet never points past what's
    // actually posted; a hashtag cut in half by truncation is left as
    // plain text rather than an invalid facet.
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

    // Term names as Bluesky-safe hashtags for %%CHARACTERS%%,
    // %%LOCATIONS%%, %%TAGS%% — each stripped to letters/numbers/
    // underscore (Unicode-aware) before the # prefix, since hashtags
    // can't contain spaces or punctuation. Each clean tag name is also
    // appended to $collected_tags by reference, so
    // toocheke_bluesky_build_tag_facets() knows which substrings need a
    // real facet — without one, a "#Word" is just inert, unclickable text.
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

    // Assembles the Text+Image post text from the admin template.
    // Bluesky's 300-character limit includes the URL (no auto-shortening),
    // so if the assembled text runs over, %%TITLE%% is shortened first —
    // every other placeholder keeps its full value — and the whole string
    // is hard-truncated as a last resort. Returns facets (link + hashtag)
    // built against the final text, so the caller doesn't need its own.
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

    // The only three functions that talk to Bluesky directly.
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

    // Bluesky's blob size limit (2MB as of April 2026), with a small
    // safety margin since that's not a documented hard constant. A
    // method rather than a class constant because traits can't have
    // constants until PHP 8.2.
    private function toocheke_bluesky_max_image_bytes()
    {
        return 1950 * 1024;
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
            wp_delete_file( $tmp_file );
            return new WP_Error('toocheke_bluesky_image_empty', 'Downloaded image was empty or unreadable.');
        }

        if ($file_size > $this->toocheke_bluesky_max_image_bytes()) {
            $max_bytes = $this->toocheke_bluesky_max_image_bytes();
            $shrunk = $this->toocheke_bluesky_shrink_image_to_fit($tmp_file, $max_bytes);

            if (is_wp_error($shrunk)) {
                wp_delete_file( $tmp_file );
                return new WP_Error(
                    'toocheke_bluesky_image_too_large',
                    'Image exceeds Bluesky\'s 2MB image limit (' . round($file_size / 1024) . 'KB) and could not be shrunk to fit: ' . $shrunk->get_error_message()
                );
            }

            // toocheke_bluesky_shrink_image_to_fit() wrote a new temp file;
            // stop tracking the original so we don't delete it twice.
            wp_delete_file( $tmp_file );
            $tmp_file = $shrunk;
        }

        $image_info = @getimagesize($tmp_file);
        $mime       = $image_info['mime'] ?? 'image/jpeg';
        $image_data = file_get_contents($tmp_file);
        wp_delete_file( $tmp_file );

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

    /**
     * Shrink an oversized image down to fit within $max_bytes, writing the
     * result to a new temp file (the caller is responsible for deleting
     * both the original and the returned file).
     *
     * Strategy — dimensions first, quality as a last resort:
     *
     *   1. Step the long edge down through 4000 -> 3000 -> 2000 -> 1500 ->
     *      1000px (skipping any cap the image is already smaller than),
     *      re-encoding at a solid quality (85) each time. 4000px is
     *      Bluesky's own maximum render resolution, so this isn't an
     *      arbitrary guess — anything above that is wasted bytes Bluesky
     *      would only downscale anyway. Cutting pixel count this way drops
     *      file size fast without the macro-blocking/artifacting that
     *      comes from crushing quality on a still-huge image.
     *   2. Only if the smallest dimension step still doesn't fit (a very
     *      high-entropy image, or one that was already small on disk),
     *      fall back to stepping quality down (70 -> 55 -> 40) at whatever
     *      the last-tried dimensions were.
     *
     * Uses wp_get_image_editor() — WordPress core's own GD/Imagick
     * abstraction — so this adds no new dependency and no extra library
     * weight to the plugin.
     *
     * @param string $source_path Path to the downloaded original.
     * @param int    $max_bytes   Target ceiling in bytes.
     * @return string|WP_Error   Path to a new temp file under $max_bytes, or WP_Error if it couldn't get there.
     */
    private function toocheke_bluesky_shrink_image_to_fit($source_path, $max_bytes)
    {
        $editor = wp_get_image_editor($source_path);

        if (is_wp_error($editor)) {
            return new WP_Error('toocheke_bluesky_no_editor', 'No image editor (GD/Imagick) available on this server: ' . $editor->get_error_message());
        }

        $size        = $editor->get_size();
        $orig_width  = $size['width'] ?? 0;
        $orig_height = $size['height'] ?? 0;
        $long_edge   = max($orig_width, $orig_height);

        // Bluesky's own max render resolution is 4000px on the long edge;
        // the smaller steps below are just further fallback if that alone
        // isn't enough to clear the byte limit.
        $dimension_caps  = [4000, 3000, 2000, 1500, 1000];
        $last_dimensions = null;

        if ($long_edge > 0) {
            foreach ($dimension_caps as $cap) {
                if ($long_edge <= $cap) {
                    continue; // Already at or under this cap; don't upscale.
                }

                $dimensions = $this->toocheke_bluesky_scale_to_long_edge($orig_width, $orig_height, $cap);
                $last_dimensions = $dimensions;

                $result = $this->toocheke_bluesky_try_save_under_limit($source_path, $max_bytes, 85, $dimensions);
                if (! is_wp_error($result)) {
                    return $result;
                }
            }
        }

        // Dimension steps alone weren't enough (or the image was already
        // small on disk despite its byte size). Fall back to quality
        // reduction at whatever the smallest dimensions we tried were.
        foreach ([70, 55, 40] as $quality) {
            $result = $this->toocheke_bluesky_try_save_under_limit($source_path, $max_bytes, $quality, $last_dimensions);
            if (! is_wp_error($result)) {
                return $result;
            }
        }

        return new WP_Error('toocheke_bluesky_shrink_failed', 'Could not compress the image under the size limit even after reducing dimensions and quality.');
    }

    /**
     * Scale [$width, $height] down so its longest edge equals $long_edge,
     * preserving aspect ratio.
     *
     * @return array [width, height]
     */
    private function toocheke_bluesky_scale_to_long_edge($width, $height, $long_edge)
    {
        if ($width >= $height) {
            $new_width  = $long_edge;
            $new_height = (int) round($height * ($long_edge / $width));
        } else {
            $new_height = $long_edge;
            $new_width  = (int) round($width * ($long_edge / $height));
        }

        return [max(1, $new_width), max(1, $new_height)];
    }

    /**
     * One attempt: re-encode $source_path at $quality (and optionally resized
     * to $dimensions = [width, height]) and check if the result fits under
     * $max_bytes. Returns the temp file path on success, cleaning up after
     * itself on failure so callers never leak temp files from failed attempts.
     *
     * @param string     $source_path
     * @param int        $max_bytes
     * @param int        $quality
     * @param array|null $dimensions  [width, height] or null to keep original size.
     * @return string|WP_Error
     */
    private function toocheke_bluesky_try_save_under_limit($source_path, $max_bytes, $quality, $dimensions)
    {
        $editor = wp_get_image_editor($source_path);
        if (is_wp_error($editor)) {
            return $editor;
        }

        $editor->set_quality($quality);

        if (null !== $dimensions) {
            $resized = $editor->resize($dimensions[0], $dimensions[1], false);
            if (is_wp_error($resized)) {
                return $resized;
            }
        }

        $saved = $editor->save();
        if (is_wp_error($saved)) {
            return $saved;
        }

        $new_path = $saved['path'];
        $new_size = filesize($new_path);

        if (false === $new_size || $new_size > $max_bytes) {
            wp_delete_file( $new_path );
            return new WP_Error('toocheke_bluesky_still_too_large', 'Still too large at this quality/size.');
        }

        return $new_path;
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

    // One capped array option for site-wide errors — no per-post log.
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
                        absint($count)
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
