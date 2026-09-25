<?php
/**
 * Toocheke Companion — Custom Permalink Slugs.
 *
 * Lets the site owner change the URL slug used for each of Toocheke
 * Companion's post types and taxonomies (Series, Comic, Manga Series,
 * Manga Volume, Manga Chapter, Genres, Series Tags, Collections, Chapters,
 * Manga Genre, Manga Publisher) from Toocheke > Options > Navigation >
 * Permalinks.
 *
 * Each submitted value is sanitized, and rejected (falling back to
 * whatever was previously saved, or the default) if it's empty,
 * duplicates another one of these 11 fields, matches a WordPress-
 * reserved term, or collides with another registered post type/
 * taxonomy — a bad slug should never take the site offline.
 *
 * Actual slug resolution happens via toocheke_permalinks_get_slug(),
 * called from class-toocheke-companion-cpt-taxonomy.php with each
 * former hardcoded slug as its default, so a site that never visits
 * this tab sees no change in behavior.
 */

if (! defined('ABSPATH')) {
    exit;
}

trait Toocheke_Companion_Permalinks
{
    // Computed once per request and reused across all 11 fields'
    // sanitize callbacks, since they all need the same cross-field view.
    private $toocheke_permalinks_conflict_cache = null;

    public function toocheke_permalinks_register_hooks()
    {
        // Deferred, one-time flush — see toocheke_permalinks_maybe_flush_rewrite_rules().
        add_action('admin_init', [$this, 'toocheke_permalinks_maybe_flush_rewrite_rules']);
    }

    // Single source of truth for all 11 customizable slugs — label and
    // default (each default matches the former hardcoded slug in
    // class-toocheke-companion-cpt-taxonomy.php).
    private function toocheke_permalinks_get_field_definitions()
    {
        return [
            'series'          => ['label' => __('Series', 'toocheke-companion'), 'default' => 'series'],
            'comic'           => ['label' => __('Comic', 'toocheke-companion'), 'default' => 'comic'],
            'manga_series'    => ['label' => __('Manga Series', 'toocheke-companion'), 'default' => 'manga_series'],
            'manga_volume'    => ['label' => __('Manga Volume', 'toocheke-companion'), 'default' => 'manga_volume'],
            'manga_chapter'   => ['label' => __('Manga Chapter', 'toocheke-companion'), 'default' => 'manga'],
            'genres'          => ['label' => __('Genres (Series taxonomy)', 'toocheke-companion'), 'default' => 'genre'],
            'series_tags'     => ['label' => __('Series Tags', 'toocheke-companion'), 'default' => 'series-tag'],
            'collections'     => ['label' => __('Collections', 'toocheke-companion'), 'default' => 'collection'],
            'chapters'        => ['label' => __('Chapters', 'toocheke-companion'), 'default' => 'chapter'],
            'manga_genre'     => ['label' => __('Manga Genre', 'toocheke-companion'), 'default' => 'manga-genre'],
            'manga_publisher' => ['label' => __('Manga Publisher', 'toocheke-companion'), 'default' => 'manga-publisher'],
        ];
    }

    // The one place any CPT/taxonomy registration should get one of
    // these slugs from — never a bare get_option() — so it always
    // falls back to something valid even if the option is empty.
    public function toocheke_permalinks_get_slug($key, $default)
    {
        $value = sanitize_title(get_option("toocheke-permalink-{$key}", $default));
        return ('' !== $value) ? $value : $default;
    }

    public function toocheke_permalinks_register_settings_fields()
    {
        add_settings_section(
            'toocheke_permalinks_section',
            'Permalinks',
            [$this, 'toocheke_permalinks_section_message'],
            'toocheke-options-page'
        );

        foreach ($this->toocheke_permalinks_get_field_definitions() as $key => $def) {
            $option_name = "toocheke-permalink-{$key}";

            add_settings_field(
                $option_name,
                $def['label'],
                [$this, 'toocheke_permalinks_slug_field'],
                'toocheke-options-page',
                'toocheke_permalinks_section',
                ['key' => $key, 'default' => $def['default'], 'label' => $def['label']]
            );

            register_setting('toocheke-settings', $option_name, [
                'sanitize_callback' => [$this, 'toocheke_permalinks_sanitize_slug'],
                'default'           => $def['default'],
            ]);
        }
    }

    public function toocheke_permalinks_section_message()
    {
        $notice_html = '<p>'
            . '🚨 <strong>' . esc_html__('IMPORTANT', 'toocheke-companion') . '</strong> 🚨 — '
            . esc_html__('If you change any of the settings on this page from the default, go to', 'toocheke-companion')
            . ' <strong>' . esc_html__('Settings → Permalinks', 'toocheke-companion') . '</strong> '
            . esc_html__('and click', 'toocheke-companion') . ' <strong>' . esc_html__('Save Changes', 'toocheke-companion') . '</strong> '
            . esc_html__('so that the new permalink structure can be recognized by WordPress.', 'toocheke-companion')
            . '</p>'
            . '<p>' . esc_html__('This plugin already flushes the rewrite rules on its own after you save changes here, so existing links should keep working — but visiting Settings → Permalinks and saving is still the most reliable way to make sure WordPress and any caching/security plugin on your site are fully in sync with the new URLs.', 'toocheke-companion') . '</p>';

        $this->toocheke_render_dismissible_info('toocheke_permalinks_flush_notice', $notice_html);

        echo '<p>' . esc_html__('Customize the URL slugs used for Toocheke Companion\'s post types and taxonomies below. Each slug must be unique — two items here can never share the same slug, and a slug should never match a WordPress-reserved term (like "page" or "feed") or something another plugin is already using. If a value you enter conflicts with anything, it\'s automatically rejected and your previous value is kept instead, so a mistake here can\'t take your site offline or cause 404 errors.', 'toocheke-companion') . '</p>';
    }

    public function toocheke_permalinks_slug_field($args)
    {
        $option_name = "toocheke-permalink-{$args['key']}";
        $value       = get_option($option_name, $args['default']);
        ?>
        <input type="text" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php
            printf(
                /* translators: %s: this field's default slug value */
                esc_html__('Default: "%s". Changing this will modify the permalink for how this is addressed in the URL. Lowercase letters, numbers, and hyphens only — no spaces or slashes.', 'toocheke-companion'),
                esc_html($args['default'])
            );
            ?>
        </p>
        <?php
    }

    // Shared sanitize_callback for all 11 fields — current_filter()
    // returns e.g. 'sanitize_option_toocheke-permalink-comic', so one
    // method can serve all 11 without near-identical copies.
    public function toocheke_permalinks_sanitize_slug($value)
    {
        $key         = $this->toocheke_permalinks_key_from_current_filter();
        $definitions = $this->toocheke_permalinks_get_field_definitions();

        if (! $key || ! isset($definitions[$key])) {
            // Shouldn't happen — defensive fallback only.
            return sanitize_title($value);
        }

        $option_name = "toocheke-permalink-{$key}";
        $default     = $definitions[$key]['default'];
        $current     = get_option($option_name, $default);

        $conflicts = $this->toocheke_permalinks_compute_conflicts();

        if (isset($conflicts[$key])) {
            // A field can be "in conflict" either because it was just
            // changed to a colliding value, or because it was never
            // touched and another field's new value collides with its
            // existing one — only the first case is worth an error.
            if (sanitize_title($value) !== $current) {
                add_settings_error(
                    'toocheke-settings',
                    "{$option_name}-conflict",
                    sprintf(
                        /* translators: 1: field label, 2: reason the value was rejected */
                        __('"%1$s" was not changed: %2$s Your previous value was kept.', 'toocheke-companion'),
                        $definitions[$key]['label'],
                        $conflicts[$key]
                    )
                );
            }
            return $current;
        }

        $sanitized = sanitize_title($value);

        if ('' === $sanitized) {
            add_settings_error(
                'toocheke-settings',
                "{$option_name}-empty",
                sprintf(
                    /* translators: %s: field label */
                    __('"%s" cannot be empty and was reset to its default.', 'toocheke-companion'),
                    $definitions[$key]['label']
                )
            );
            $sanitized = $default;
        }

        if ($sanitized !== $current) {
            // Deliberately not flushing here directly -- see the docblock
            // on toocheke_permalinks_maybe_flush_rewrite_rules() for why
            // that has to happen on a later request instead.
            update_option('toocheke_permalinks_flush_needed', 1);

            // A specific, per-field confirmation for whatever actually
            // changed -- symmetric with the per-field error messages
            // above. WordPress core's own generic "Settings saved."
            // message (added automatically by options.php after any
            // successful save) is registered under the 'general' group,
            // not 'toocheke-settings', so it would never actually be
            // shown by the settings_errors('toocheke-settings') call in
            // toocheke_display_options_page() -- same class of mismatch
            // already fixed for the error messages above. This avoids
            // relying on that generic message at all, and is more useful
            // regardless: it names exactly which slug changed and what
            // it's now set to, rather than one vague confirmation
            // covering the whole page.
            add_settings_error(
                'toocheke-settings',
                "{$option_name}-updated",
                sprintf(
                    /* translators: 1: field label, 2: new slug value */
                    __('"%1$s" slug updated to "%2$s".', 'toocheke-companion'),
                    $definitions[$key]['label'],
                    $sanitized
                ),
                'success'
            );
        }

        return $sanitized;
    }

    private function toocheke_permalinks_key_from_current_filter()
    {
        $filter = current_filter();
        $prefix = 'sanitize_option_toocheke-permalink-';
        if (0 === strpos($filter, $prefix)) {
            return substr($filter, strlen($prefix));
        }
        return '';
    }

    // Common WordPress-reserved top-level terms — not exhaustive, but
    // covers the well-known ones that would break routing as a slug.
    private function toocheke_permalinks_get_reserved_slugs()
    {
        return [
            'page', 'post', 'posts', 'attachment', 'feed', 'embed', 'category',
            'tag', 'author', 'comments', 'search', 'trackback', 'rss', 'rss2',
            'rdf', 'atom', 'date', 'admin', 'wp-admin', 'wp-login', 'wp-content',
            'wp-includes', 'wp-json', 'xmlrpc', 'sitemap',
        ];
    }

    // Computes, once per request, which of the 11 proposed values can't
    // be saved — colliding with each other, a reserved term, or another
    // registered post type/taxonomy. Reads directly from $_POST rather
    // than the single $value each sanitize call gets, since that's the
    // only way to see what all 11 fields were submitted as at once.
    private function toocheke_permalinks_compute_conflicts()
    {
        if (null !== $this->toocheke_permalinks_conflict_cache) {
            return $this->toocheke_permalinks_conflict_cache;
        }

        $definitions = $this->toocheke_permalinks_get_field_definitions();
        $reserved    = $this->toocheke_permalinks_get_reserved_slugs();

        $proposed = [];
        foreach ($definitions as $key => $def) {
            $option_name = "toocheke-permalink-{$key}";
            $raw         = isset($_POST[$option_name]) ? wp_unslash($_POST[$option_name]) : get_option($option_name, $def['default']);
            $clean       = sanitize_title($raw);
            $proposed[$key] = ('' !== $clean) ? $clean : $def['default'];
        }

        // Every other currently-registered post type/taxonomy key,
        // site-wide, excluding our own 11 -- see this method's docblock
        // for why this is a best-effort check, not a guarantee.
        $external_types = array_diff(array_merge(get_post_types(), get_taxonomies()), array_keys($definitions));

        $by_slug = [];
        foreach ($proposed as $key => $slug) {
            $by_slug[$slug][] = $key;
        }

        $conflicts = [];
        foreach ($proposed as $key => $slug) {
            if (in_array($slug, $reserved, true)) {
                $conflicts[$key] = sprintf(
                    /* translators: %s: the rejected slug value */
                    __('"%s" is a reserved WordPress term and cannot be used.', 'toocheke-companion'),
                    $slug
                );
                continue;
            }

            if (count($by_slug[$slug]) > 1) {
                $conflicts[$key] = sprintf(
                    /* translators: %s: the rejected slug value */
                    __('"%s" is already used by another field on this page — each slug must be unique.', 'toocheke-companion'),
                    $slug
                );
                continue;
            }

            if (in_array($slug, $external_types, true)) {
                $conflicts[$key] = sprintf(
                    /* translators: %s: the rejected slug value */
                    __('"%s" is already used by another post type or taxonomy on this site.', 'toocheke-companion'),
                    $slug
                );
                continue;
            }
        }

        $this->toocheke_permalinks_conflict_cache = $conflicts;
        return $conflicts;
    }

    // Not called directly from the sanitize callback — by the time a
    // save reaches it, this request's 'init' has already registered
    // CPTs/taxonomies using the OLD values, so flushing there would
    // rebuild from stale registrations. Instead this just sets a flag;
    // the actual flush happens here on the NEXT admin page load, by
    // which point 'init' has re-registered everything with the new slug.
    public function toocheke_permalinks_maybe_flush_rewrite_rules()
    {
        if (get_option('toocheke_permalinks_flush_needed')) {
            flush_rewrite_rules();
            delete_option('toocheke_permalinks_flush_needed');
        }
    }
}
