<?php
/**
 * Adds and renders the custom columns shown on the post and term list
 * tables in wp-admin (comics, series, manga series/volume/chapter,
 * collections, chapters, characters, genres), including the optional
 * Patreon level column.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Admin_Columns
{
    /**
     * Shared helpers for the genre/chapter/collection/character taxonomy
     * image and order columns below. $word is the taxonomy word root (e.g.
     * 'chapter'), used to derive the column key ('{word}_image' or
     * '{word}_order') and the underlying term meta key ('{word}-image-id'
     * or '{word}-order').
     */

    /**
     * Adds an "Image" column header for a taxonomy's term list table.
     *
     * @param array  $columns Existing column headers.
     * @param string $word    The taxonomy word root, e.g. 'genre'.
     * @return array
     */
    private function toocheke_add_term_image_column($columns, $word)
    {
        $columns[$word . '_image'] = __('Image', 'toocheke-companion');
        return $columns;
    }

    /**
     * Marks the "Image" column as sortable.
     *
     * @param array  $sortable Existing sortable columns.
     * @param string $word     The taxonomy word root, e.g. 'genre'.
     * @return array
     */
    private function toocheke_add_term_image_column_sortable($sortable, $word)
    {
        $sortable[$word . '_image'] = $word . '_image';
        return $sortable;
    }

    /**
     * Renders the manual sort-order value into the "Order" column for a
     * taxonomy's term list table.
     *
     * @param string $content     Existing column content (passed through if this isn't our column).
     * @param string $column_name The column being rendered, checked against '{word}_order'.
     * @param int    $term_id     The term whose order value to show.
     * @param string $word        The taxonomy word root, e.g. 'chapter'.
     * @return string
     */
    private function toocheke_render_term_order_column($content, $column_name, $term_id, $word)
    {
        if ($column_name !== $word . '_order') {
            return $content;
        }

        $term_id = absint($term_id);
        $order   = get_term_meta($term_id, $word . '-order', true);

        if (! empty($order)) {
            $content .= esc_attr($order);
        }

        return $content;
    }

    /* Functions for registering meta term for the toocheke taxonamies*/
    /*
     * Displaying the patreon column
     */

    public function toocheke_companion_add_patreon_level_column($columns)
    {

        $new_columns = [];

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $columns[$key];
            if ($key === 'author') {
                $new_columns['patreon_level'] = __('Patreon Level', 'toocheke-companion');
            }
        }

        return $new_columns;
    }

    /*
 * Add comics columns
 */

    public function toocheke_companion_add_comic_columns($columns)
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            // Insert comic_series after patreon_level (if Patreon plugin active) OR after author
            if ($key === 'patreon_level' && is_plugin_active('patreon-connect/patreon.php')) {
                $new_columns['comic_series'] = __('Series', 'toocheke-companion');
            } elseif ($key === 'author' && ! isset($new_columns['comic_series'])) {
                $new_columns['comic_series'] = __('Series', 'toocheke-companion');
            }

            // Insert tags, characters, and locations after taxonomy-chapters
            if ($key === 'taxonomy-chapters') {
                $new_columns['comic_tags']       = __('Tag', 'toocheke-companion');
                $new_columns['comic_characters'] = __('Character', 'toocheke-companion');
                $new_columns['comic_locations']  = __('Location', 'toocheke-companion');
            }

            // Insert likes, views, and thumbnail after comments
            if ($key === 'comments') {
                $new_columns['comic_likes']     = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
                $new_columns['comic_views']     = __('<span class="dashicons dashicons-visibility"></span>', 'toocheke-companion');
                $new_columns['comic_thumbnail'] = __('Thumbnail', 'toocheke-companion');
            }
        }

        return $new_columns;
    }

    /*
 * Add Manga Series columns
 */

    public function toocheke_companion_add_manga_series_columns($columns)
    {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // Insert custom columns after the title
            if ($key === 'title') {
                $new_columns['manga_series_genres']     = __('Genres', 'toocheke-companion');
                $new_columns['manga_series_publishers'] = __('Publishers', 'toocheke-companion');
                $new_columns['manga_series_likes']      = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
                $new_columns['manga_series_thumbnail']  = __('Thumbnail', 'toocheke-companion');
            }
        }

        return $new_columns;
    }

    public function toocheke_companion_add_manga_volume_columns($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'title') {
                $new_columns['manga_volume_thumbnail'] = __('Thumbnail', 'toocheke-companion');
                $new_columns['manga_volume_series']    = __('Series', 'toocheke-companion');
                $new_columns['manga_volume_likes']     = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
            }
        }
        return $new_columns;
    }

    public function toocheke_companion_add_manga_chapter_columns($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'title') {
                $new_columns['manga_chapter_thumbnail'] = __('Thumbnail', 'toocheke-companion');
                $new_columns['manga_chapter_series']    = __('Series', 'toocheke-companion');
                $new_columns['manga_chapter_volume']    = __('Volume', 'toocheke-companion');
                $new_columns['manga_chapter_likes']     = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
                $new_columns['manga_chapter_views']     = __('<span class="dashicons dashicons-visibility"></span>', 'toocheke-companion');
            }
        }
        return $new_columns;
    }

    /*
     * Adding series columns
     */

    public function toocheke_companion_add_series_columns($columns)
    {
        // Define the new columns in order
        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            // Insert after taxonomy-genres
            if ($key === 'taxonomy-genres') {
                $new_columns['series_tags'] = __('Tag', 'toocheke-companion');
            }

            // Insert after series_tags
            if ($key === 'taxonomy-genres') { // <-- rely on the known order
                $new_columns['series_thumbnail']   = __('Thumbnail', 'toocheke-companion');
                $new_columns['series_hero']        = __('Hero - Desktop', 'toocheke-companion');
                $new_columns['series_mobile_hero'] = __('Hero - Mobile', 'toocheke-companion');
                $new_columns['series_bg_img']      = __('Background<br/> Image', 'toocheke-companion');
                $new_columns['series_bg_color']    = __('Background<br/> Color', 'toocheke-companion');
            }
        }

        return $new_columns;
    }

    /* Adding content to columns */

    public function toocheke_companion_add_series_column_content($column_name, $id)
    {
        global $wpdb;
        switch ($column_name) {
            case 'series_tags':
                $terms_list = get_the_terms($id, 'series_tags');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $tags_list[] = '<a href="' . admin_url('/edit.php?post_type=series&series_tags=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $tags_list);
                }
                break;
            case 'series_thumbnail':
                $post_thumbnail_id = get_post_thumbnail_id($id);
                if ($post_thumbnail_id) {
                    $post_thumbnail_img     = wp_get_attachment_image_src($post_thumbnail_id, 'featured_preview');
                    $post_thumbnail_img_src = $post_thumbnail_img[0];
                    if ($post_thumbnail_img_src) {
                        echo '<img src="' . $post_thumbnail_img_src . '" class="series-thumbnail" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-thumbnail" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-thumbnail" />';
                }
                break;
            case 'series_hero':
                $post_hero_id = get_post_meta($id, 'series_hero_image_id', true);
                get_post_thumbnail_id($id);
                if ($post_hero_id) {
                    $post_hero_img     = wp_get_attachment_image_src($post_hero_id, 'featured_preview');
                    $post_hero_img_src = $post_hero_img[0];
                    if ($post_hero_img_src) {
                        echo '<img src="' . $post_hero_img_src . '" class="series-hero" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                }
                break;
            case 'series_mobile_hero':
                $post_hero_id = get_post_meta($id, 'series_mobile_hero_image_id', true);
                get_post_thumbnail_id($id);
                if ($post_hero_id) {
                    $post_hero_img     = wp_get_attachment_image_src($post_hero_id, 'featured_preview');
                    $post_hero_img_src = $post_hero_img[0];
                    if ($post_hero_img_src) {
                        echo '<img src="' . $post_hero_img_src . '" class="series-hero" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                }
                break;
            case 'series_bg_img':
                $post_bg_id = get_post_meta($id, 'series_bg_image_id', true);
                get_post_thumbnail_id($id);
                if ($post_bg_id) {
                    $post_bg_img     = wp_get_attachment_image_src($post_bg_id, 'featured_preview');
                    $post_bg_img_src = $post_bg_img[0];
                    if ($post_bg_img_src) {
                        echo '<img src="' . $post_bg_img_src . '" class="series-bg" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-bg" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-bg" />';
                }
                break;
            case 'series_bg_color':
                $bg_color = get_post_meta($id, 'series_bg_color');
                if (! empty($bg_color) && ! isset($bg_color->errors)) {
                    $color_box = '<div class="color-box" style="background-color: ' . $bg_color[0] . '"></div>';
                    echo $color_box;
                }
                break;

            default:
                break;
        } // end switch
    }

    public function toocheke_companion_render_manga_series_columns($column_name, $id)
    {
        global $wpdb;
        switch ($column_name) {
            case 'manga_series_genres':
                $terms_list = get_the_terms($id, 'manga_genre');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $genres_list[] = '<a href="' . admin_url('edit.php?post_type=manga_series&manga_genre=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $genres_list);
                }
                break;
            case 'manga_series_publishers':
                $terms_list = get_the_terms($id, 'manga_publisher');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $publishers_list[] = '<a href="' . admin_url('/edit.php?post_type=manga_series&manga_publisher=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $publishers_list);
                }
                break;
            case 'manga_series_likes':
                if (get_post_meta($id, "_post_like_count", true)) {
                    echo get_post_meta($id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }
                break;
            case 'manga_series_thumbnail':
                $this->toocheke_render_thumbnail($id);
                break;

            default:
                break;
        } // end switch
    }

    public function toocheke_companion_render_manga_volume_columns($column_name, $post_id)
    {
        global $wpdb;
        switch ($column_name) {

            case 'manga_volume_thumbnail':
                $this->toocheke_render_thumbnail($post_id);
                break;
            case 'manga_volume_series':
                $series_id = get_post_meta($post_id, 'series_id', true);
                if ($series_id) {
                    $series_title = get_the_title($series_id);
                    $link         = add_query_arg([
                        'post_type' => 'manga_volume',
                        'series_id' => $series_id,
                    ], admin_url('edit.php'));
                    echo '<a href="' . esc_url($link) . '">' . esc_html($series_title) . '</a>';
                } else {
                    echo '-';
                }
                break;
            case 'manga_volume_likes':
                if (get_post_meta($post_id, "_post_like_count", true)) {
                    echo get_post_meta($post_id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }
                break;
            default:
                break;
        } // end switch
    }

    public function toocheke_companion_render_manga_chapter_columns($column_name, $post_id)
    {
        switch ($column_name) {

            case 'manga_chapter_thumbnail':
                $this->toocheke_render_thumbnail($post_id);
                break;

            case 'manga_chapter_series':
                $series_id = get_post_meta($post_id, 'series_id', true);
                if ($series_id) {
                    $series_title = get_the_title($series_id);
                    $link         = add_query_arg([
                        'post_type' => 'manga_chapter',
                        'series_id' => $series_id,
                    ], admin_url('edit.php'));
                    echo '<a href="' . esc_url($link) . '">' . esc_html($series_title) . '</a>';
                } else {
                    echo '-';
                }
                break;

            case 'manga_chapter_volume':
                $volume_id = get_post_meta($post_id, 'volume_id', true);
                if ($volume_id) {
                    $volume_title = get_the_title($volume_id);
                    $link         = add_query_arg([
                        'post_type' => 'manga_chapter',
                        'volume_id' => $volume_id,
                    ], admin_url('edit.php'));
                    echo '<a href="' . esc_url($link) . '">' . esc_html($volume_title) . '</a>';
                } else {
                    echo '-';
                }
                break;
            case 'manga_chapter_likes':
                if (get_post_meta($post_id, "_post_like_count", true)) {
                    echo get_post_meta($post_id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }
                break;
            case 'manga_chapter_views':
                if (get_post_meta($post_id, "post_views_count", true)) {
                    echo get_post_meta($post_id, "post_views_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }

                break;
            default:
                break;
        }
    }

    public function toocheke_companion_add_comic_column_content($column_name, $id)
    {
        global $wpdb;
        switch ($column_name) {
            case 'comic_characters':
                $terms_list = get_the_terms($id, 'comic_characters');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $character_list[] = '<a href="' . admin_url('edit.php?post_type=comic&comic_characters=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $character_list);
                }
                break;
            case 'comic_locations':
                $terms_list = get_the_terms($id, 'comic_locations');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $locations_list[] = '<a href="' . admin_url('/edit.php?post_type=comic&comic_locations=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $locations_list);
                }
                break;
            case 'comic_thumbnail':
                $this->toocheke_render_thumbnail($id);
                break;
            case 'comic_likes':
                if (get_post_meta($id, "_post_like_count", true)) {
                    echo get_post_meta($id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }

                break;
            case 'comic_views':
                if (get_post_meta($id, "post_views_count", true)) {
                    echo get_post_meta($id, "post_views_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }

                break;
            case 'comic_tags':
                $terms_list = get_the_terms($id, 'comic_tags');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $tags_list[] = '<a href="' . admin_url('/edit.php?post_type=comic&comic_tags=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $tags_list);
                }
                break;
            case 'comic_series':
                $ancestors     = get_ancestors($id, 'series');
                $post_ancestor = end($ancestors);
                if ($post_ancestor != 0) {
                    echo '<a href="' . admin_url('/edit.php?post_type=comic&post_parent=' . $post_ancestor) . '">' . get_the_title($post_ancestor) . '</a>';
                } else {
                    echo '—';
                }
                break;
           case 'patreon_level':
            // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_plugin_active('patreon-connect/patreon.php')) {
                $patreon_level = get_post_meta($id, 'patreon-level', true);
                
                // Escape both the URL and the displayed text
                echo '<a href="' . esc_url(admin_url('/edit.php?post_type=comic&patreon_level=' . urlencode($patreon_level))) . '">' . esc_html($this->toocheke_get_patreon_level_label($patreon_level)) . '</a>';
            }
            break;
            default:
                break;
        } // end switch
    }

    public function toocheke_filter_patreon_levels($query)
    {
        if (! is_admin() || 'comic' != $query->query['post_type'] || ! isset($_GET['patreon_level'])) {
            return;
        }

        $query->set('meta_key', 'patreon-level');
        // Sanitize the GET parameter before using it
        $query->set('meta_value', sanitize_text_field($_GET['patreon_level']));
    }

    /* Functions for Comic Genre */
    /* Displaying image meta data in column */
    public function toocheke_companion_genre_add_image_column($columns)
    {
        return $this->toocheke_add_term_image_column($columns, 'genre');
    }

    /* Make column sortable */

    public function toocheke_companion_genre_add_image_column_sortable($sortable)
    {
        return $this->toocheke_add_term_image_column_sortable($sortable, 'genre');
    }

            /* Add content into column */
            public function toocheke_companion_add_chapter_column_content($content, $column_name, $term_id)
            {
                return $this->toocheke_render_term_order_column($content, $column_name, $term_id, 'chapter');
            }

            /* Functions for Comic Chapter */
            /* Displaying image meta data in column */
            public function toocheke_companion_chapter_add_image_column($columns)
            {
                return $this->toocheke_add_term_image_column($columns, 'chapter');
            }

            /* Make column sortable */

            public function toocheke_companion_chapter_add_image_column_sortable($sortable)
            {
                return $this->toocheke_add_term_image_column_sortable($sortable, 'chapter');
            }

            /* Add content into column */
            public function toocheke_companion_add_collection_column_content($content, $column_name, $term_id)
            {
                return $this->toocheke_render_term_order_column($content, $column_name, $term_id, 'collection');
            }

            /* Functions for Comic Collection */
            /* Displaying image meta data in column */
            public function toocheke_companion_collection_add_image_column($columns)
            {
                return $this->toocheke_add_term_image_column($columns, 'collection');
            }

            /* Make column sortable */

            public function toocheke_companion_collection_add_image_column_sortable($sortable)
            {
                return $this->toocheke_add_term_image_column_sortable($sortable, 'collection');
            }

            /* Add content into column */
            public function toocheke_companion_add_character_order_column_content($content, $column_name, $term_id)
            {
                return $this->toocheke_render_term_order_column($content, $column_name, $term_id, 'character');
            }

            /* Displaying image meta data in column */
            public function toocheke_companion_character_add_image_column($columns)
            {
                return $this->toocheke_add_term_image_column($columns, 'character');
            }

            /* Make column sortable */

            public function toocheke_companion_character_add_image_column_sortable($sortable)
            {
                return $this->toocheke_add_term_image_column_sortable($sortable, 'character');
            }

            public function toocheke_comic_sortable_columns($columns)
            {
                $columns['comic_likes'] = 'likes';
                $columns['comic_views'] = 'views';
                return $columns;
            }

            //Manga Sorting functionality

            // --- SERIES ---
            public function toocheke_manga_series_sortable_columns($columns)
            {
                $columns['manga_series_likes'] = 'manga_series_likes';
                return $columns;
            }

            // --- VOLUME ---
            public function toocheke_manga_volume_sortable_columns($columns)
            {
                $columns['manga_volume_likes'] = 'manga_volume_likes';
                return $columns;
            }

            // --- CHAPTER ---
            public function toocheke_manga_chapter_sortable_columns($columns)
            {
                $columns['manga_chapter_views'] = 'manga_chapter_views';
                $columns['manga_chapter_likes'] = 'manga_chapter_likes';
                return $columns;
            }

}
