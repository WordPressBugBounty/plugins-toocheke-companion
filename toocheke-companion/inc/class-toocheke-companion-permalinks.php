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
 * Designed defensively, since a bad rewrite slug can genuinely break a
 * site (silent 404s, colliding URLs) rather than just look wrong:
 * - Every submitted value is sanitized (sanitize_title()) before it's ever
 *   used in a rewrite rule.
 * - An empty value is rejected and reset to that field's default.
 * - A value that duplicates another one of these same 11 fields (on the
 *   very same save) is rejected outright.
 * - A value matching a common WordPress-reserved term (page, feed,
 *   category, etc.) is rejected.
 * - A value matching another currently-registered post type or taxonomy
 *   key, site-wide, is rejected -- a reasonable best-effort check for
 *   colliding with another plugin or WordPress core, though it can't catch
 *   every possible conflict (e.g. a manually-added add_rewrite_rule() with
 *   no registered post type/taxonomy behind it).
 *
 * Any rejected value keeps whatever was previously saved (or the default,
 * on a first save) instead of silently applying something broken --
 * changing one of these slugs can therefore never take the site offline
 * on its own, even with a careless or conflicting entry.
 *
 * Actual slug resolution for CPT/taxonomy registration happens via
 * toocheke_permalinks_get_slug(), called from
 * class-toocheke-companion-cpt-taxonomy.php in place of each previously
 * hardcoded slug string -- every one of those calls passes that exact
 * former hardcoded value back in as its $default, so a site that never
 * touches this new Permalinks tab sees zero change in behavior.
 */

if (! defined('ABSPATH')) {
    exit;
}

trait Toocheke_Companion_Permalinks
{
    /**
     * Computed once per request by toocheke_permalinks_compute_conflicts()
     * and reused across all 11 fields' sanitize callbacks, since they all
     * need to evaluate the exact same cross-field picture.
     */
    private $toocheke_permalinks_conflict_cache = null;

    public function toocheke_permalinks_register_hooks()
    {
        // Deferred, one-time flush -- see the docblock on
        // toocheke_permalinks_maybe_flush_rewrite_rules() for why this
        // can't just call flush_rewrite_rules() directly inside the
        // sanitize callback itself.
        add_action('admin_init', [$this, 'toocheke_permalinks_maybe_flush_rewrite_rules']);
    }

    /* =========================================================================
       FIELD DEFINITIONS
       Single source of truth for all 11 customizable slugs -- their
       label, and their default value, which is exactly what each one's
       hardcoded slug string used to be in class-toocheke-companion-cpt-taxonomy.php
       before this feature existed.
    ========================================================================= */

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

    /**
     * The one place any CPT/taxonomy registration should ever get one of
     * these 11 slugs from -- never a bare get_option() call, so the
     * "always fall back to something valid" guarantee holds even if the
     * stored option somehow ended up empty or otherwise invalid.
     */
    public function toocheke_permalinks_get_slug($key, $default)
    {
        $value = sanitize_title(get_option("toocheke-permalink-{$key}", $default));
        return ('' !== $value) ? $value : $default;
    }

    /* =========================================================================
       SETTINGS REGISTRATION
       Called from the 'navigation_options' case's 'permalinks' subsection
       gate in class-toocheke-companion-settings-page.php, matching how
       Bluesky/Notifications keep their own settings registration
       self-contained in their own file.
    ========================================================================= */

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

    /* =========================================================================
       SANITIZE + CROSS-FIELD VALIDATION
    ========================================================================= */

    /**
     * The shared sanitize_callback for all 11 fields. Which field is
     * currently being processed is determined via current_filter() --
     * register_setting()'s sanitize_callback is invoked as the callback
     * attached to WordPress's own 'sanitize_option_{$option_name}'
     * filter, so current_filter() reliably returns e.g.
     * 'sanitize_option_toocheke-permalink-comic' here, letting one shared
     * method serve all 11 fields without 11 near-identical copies of it.
     */
    public function toocheke_permalinks_sanitize_slug($value)
    {
        $key         = $this->toocheke_permalinks_key_from_current_filter();
        $definitions = $this->toocheke_permalinks_get_field_definitions();

        if (! $key || ! isset($definitions[$key])) {
            // Shouldn't happen in practice -- defensive fallback only.
            return sanitize_title($value);
        }

        $option_name = "toocheke-permalink-{$key}";
        $default     = $definitions[$key]['default'];
        $current     = get_option($option_name, $default);

        $conflicts = $this->toocheke_permalinks_compute_conflicts();

        if (isset($conflicts[$key])) {
            // A field can end up "in conflict" two different ways: either
            // the user genuinely just tried to change it to a value that
            // collides with something, or -- just as validly -- its own
            // value was never touched at all, and it's only implicated
            // because some OTHER field's new value happens to collide
            // with this one's existing, already-saved value. Either way
            // the safe outcome is identical (this field simply keeps its
            // current value), but only the first case is something the
            // user actually did and needs to be told about; showing an
            // error about a field they never touched is just confusing.
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

    /**
     * Common WordPress-reserved top-level terms -- not exhaustive, but
     * covers the well-known ones that would genuinely break routing or
     * collide with core behavior if used as a rewrite slug.
     */
    private function toocheke_permalinks_get_reserved_slugs()
    {
        return [
            'page', 'post', 'posts', 'attachment', 'feed', 'embed', 'category',
            'tag', 'author', 'comments', 'search', 'trackback', 'rss', 'rss2',
            'rdf', 'atom', 'date', 'admin', 'wp-admin', 'wp-login', 'wp-content',
            'wp-includes', 'wp-json', 'xmlrpc', 'sitemap',
        ];
    }

    /**
     * Computes, once per request, which of the 11 proposed values (if
     * any) can't be saved as-is -- either because two of our own fields
     * collided with each other on this same save, because a value matches
     * a WordPress-reserved term, or because it matches another currently
     * registered post type/taxonomy key elsewhere on the site.
     *
     * Reading directly from $_POST (rather than the $value each
     * individual sanitize call receives) is what makes the cross-field
     * duplicate check possible at all -- register_setting()'s
     * sanitize_callback for one option is never given visibility into
     * what the other 10 fields on the same form were submitted as, but
     * they're all still part of the same $_POST for this one request.
     */
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

    /* =========================================================================
       DEFERRED REWRITE-RULES FLUSH
    ========================================================================= */

    /**
     * Deliberately NOT called directly from toocheke_permalinks_sanitize_slug()
     * above. By the time a save request reaches that sanitize callback,
     * this same request's 'init' hook (where every CPT/taxonomy actually
     * registers, at priority 0 — see toocheke-companion.php) has already
     * run, using the OLD option values. Calling flush_rewrite_rules()
     * right there would rebuild the rewrite rules from those still-stale
     * registrations, not the new slug that's only about to be saved.
     *
     * Instead, this just sets a flag; the actual flush happens here, on
     * admin_init, the NEXT time any admin page loads — by then, that
     * request's own earlier 'init' has already re-registered everything
     * using the freshly-saved slug, so the flush correctly picks it up.
     */
    public function toocheke_permalinks_maybe_flush_rewrite_rules()
    {
        if (get_option('toocheke_permalinks_flush_needed')) {
            flush_rewrite_rules();
            delete_option('toocheke_permalinks_flush_needed');
        }
    }
}
