<?php
/**
 * Handles the admin-list filter dropdowns and custom sort order for manga
 * series, volume, and chapter post types, and the AJAX endpoint that
 * populates the volume dropdown for a selected series.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Manga_Sort_Filter
{
            /**
             * Sets up a WP_Query to sort admin-list results numerically by a
             * meta key, treating posts with no value (or a value of 0) as
             * included rather than excluded -- used for sorting manga
             * series/volume/chapter lists by like or view counts.
             *
             * @param WP_Query $wp_query
             * @param string   $meta_key The meta key to sort by, e.g. '_post_like_count', 'post_views_count'.
             */
            private function toocheke_set_meta_numeric_sort($wp_query, $meta_key)
            {
                $wp_query->set('meta_query', [
                    'relation' => 'OR',
                    ['key' => $meta_key, 'compare' => 'NOT EXISTS'],
                    ['key' => $meta_key, 'value' => 0, 'compare' => '>='],
                ]);
                $wp_query->set('orderby', 'meta_value_num');
                $wp_query->set('meta_type', 'NUMERIC');
            }

            public function toocheke_manga_filters($query)
            {
                if (! is_admin() || ! $query->is_main_query()) {
                    return;
                }

                $post_type = $query->get('post_type');
                if (! in_array($post_type, ['manga_volume', 'manga_chapter'])) {
                    return;
                }

                $meta_query = $query->get('meta_query') ?: [];

                // Filter by series_id (works for both)
                if (! empty($_GET['series_id'])) {
                    $meta_query[] = [
                        'key'     => 'series_id',
                        'value'   => intval($_GET['series_id']),
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ];
                }

                // Filter by volume_id (only manga_chapter)
                if ($post_type === 'manga_chapter' && ! empty($_GET['volume_id'])) {
                    $meta_query[] = [
                        'key'     => 'volume_id',
                        'value'   => intval($_GET['volume_id']),
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ];
                }

                if (! empty($meta_query)) {
                    $query->set('meta_query', $meta_query);
                }
            }

            public function toocheke_add_manga_reader_body_class($classes)
            {
                if (is_singular('manga_chapter')) {
                    $classes[] = 'manga-reader';
                }

                if (is_singular('manga_volume') && isset($_GET['reader'])) {
                    $classes[] = 'manga-reader';
                }

                return $classes;
            }

            public function toocheke_manga_series_sort($wp_query)
            {
                global $pagenow;
                if (! is_admin() || $pagenow !== 'edit.php') {
                    return;
                }

                if (($wp_query->get('orderby') === 'manga_series_likes') && $wp_query->get('post_type') === 'manga_series') {
                    $this->toocheke_set_meta_numeric_sort($wp_query, '_post_like_count');
                }
            }

            public function toocheke_manga_volume_sort($wp_query)
            {
                global $pagenow;
                if (! is_admin() || $pagenow !== 'edit.php') {
                    return;
                }

                if (($wp_query->get('orderby') === 'manga_volume_likes') && $wp_query->get('post_type') === 'manga_volume') {
                    $this->toocheke_set_meta_numeric_sort($wp_query, '_post_like_count');
                }
            }

            public function toocheke_manga_chapter_sort($wp_query)
            {
                global $pagenow;
                if (! is_admin() || $pagenow !== 'edit.php') {
                    return;
                }

                if ($wp_query->get('post_type') === 'manga_chapter') {
                    switch ($wp_query->get('orderby')) {
                        case 'manga_chapter_views':
                            $this->toocheke_set_meta_numeric_sort($wp_query, 'post_views_count');
                            break;

                        case 'manga_chapter_likes':
                            $this->toocheke_set_meta_numeric_sort($wp_query, '_post_like_count');
                            break;
                    }
                }
            }

            /**
             * Dropdown filters for Manga post types
             */
            public function toocheke_manga_filter_dropdowns($post_type)
            {
                // --- Manga Series: filter by Genre and Publisher ---
                if ($post_type === 'manga_series') {

                    $selected_genre = isset($_GET['manga_genre']) ? sanitize_text_field($_GET['manga_genre']) : '';
                    $genres = get_terms(['taxonomy' => 'manga_genre', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);

                    if (!is_wp_error($genres) && !empty($genres)) {
                        echo '<select name="manga_genre" id="filter-by-manga-genre">';
                        echo '<option value="">' . esc_html__('All Genres', 'toocheke-companion') . '</option>';
                        foreach ($genres as $term) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($term->slug),
                                selected($selected_genre, $term->slug, false),
                                esc_html($term->name)
                            );
                        }
                        echo '</select>';
                    }

                    $selected_publisher = isset($_GET['manga_publisher']) ? sanitize_text_field($_GET['manga_publisher']) : '';
                    $publishers = get_terms(['taxonomy' => 'manga_publisher', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);

                    if (!is_wp_error($publishers) && !empty($publishers)) {
                        echo '<select name="manga_publisher" id="filter-by-manga-publisher">';
                        echo '<option value="">' . esc_html__('All Publishers', 'toocheke-companion') . '</option>';
                        foreach ($publishers as $term) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($term->slug),
                                selected($selected_publisher, $term->slug, false),
                                esc_html($term->name)
                            );
                        }
                        echo '</select>';
                    }
                }

                // --- Manga Volumes: filter by Manga Series ---
                if ($post_type === 'manga_volume') {

                    $selected_series = isset($_GET['manga_series_id']) ? absint($_GET['manga_series_id']) : 0;

                    $series_posts = get_posts([
                        'post_type'      => 'manga_series',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                    ]);

                    if (!empty($series_posts)) {
                        echo '<select name="manga_series_id" id="filter-by-manga-series">';
                        echo '<option value="0">' . esc_html__('All Series', 'toocheke-companion') . '</option>';
                        foreach ($series_posts as $series) {
                            printf(
                                '<option value="%d"%s>%s</option>',
                                $series->ID,
                                selected($selected_series, $series->ID, false),
                                esc_html($series->post_title)
                            );
                        }
                        echo '</select>';
                    }
                }

                // --- Manga Chapters: filter by Manga Series and Manga Volume ---
                if ($post_type === 'manga_chapter') {

                    $selected_series = isset($_GET['manga_series_id']) ? absint($_GET['manga_series_id']) : 0;

                    $series_posts = get_posts([
                        'post_type'      => 'manga_series',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                    ]);

                    if (!empty($series_posts)) {
                        echo '<select name="manga_series_id" id="filter-by-manga-series">';
                        echo '<option value="0">' . esc_html__('All Series', 'toocheke-companion') . '</option>';
                        foreach ($series_posts as $series) {
                            printf(
                                '<option value="%d"%s>%s</option>',
                                $series->ID,
                                selected($selected_series, $series->ID, false),
                                esc_html($series->post_title)
                            );
                        }
                        echo '</select>';
                    }

                    $selected_volume = isset($_GET['manga_volume_id']) ? absint($_GET['manga_volume_id']) : 0;

                    // If a series is selected, only show volumes for that series
                    $volume_query_args = [
                        'post_type'      => 'manga_volume',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                    ];

                    if ($selected_series > 0) {
                        $volume_query_args['meta_query'] = [
                            [
                                'key'     => 'series_id',
                                'value'   => $selected_series,
                                'compare' => '=',
                                'type'    => 'NUMERIC',
                            ],
                        ];
                    }

                    $volume_posts = get_posts($volume_query_args);

                    if (!empty($volume_posts)) {
                        echo '<select name="manga_volume_id" id="filter-by-manga-volume">';
                        echo '<option value="0">' . esc_html__('All Volumes', 'toocheke-companion') . '</option>';
                        foreach ($volume_posts as $volume) {
                            printf(
                                '<option value="%d"%s>%s</option>',
                                $volume->ID,
                                selected($selected_volume, $volume->ID, false),
                                esc_html($volume->post_title)
                            );
                        }
                        echo '</select>';
                    }
                }
            }

            /**
             * Apply filters for Manga post types
             */
            public function toocheke_manga_filter_query($query)
            {
                global $pagenow;

                if (
                    ! is_admin() ||
                    $pagenow !== 'edit.php' ||
                    ! $query->is_main_query() ||
                    ! isset($_GET['post_type'])
                ) {
                    return;
                }

                $post_type = $_GET['post_type'];

                // --- Manga Series: taxonomy filters ---
                if ($post_type === 'manga_series') {
                    $tax_query = [];

                    if (!empty($_GET['manga_genre'])) {
                        $tax_query[] = [
                            'taxonomy' => 'manga_genre',
                            'field'    => 'slug',
                            'terms'    => sanitize_text_field($_GET['manga_genre']),
                        ];
                    }

                    if (!empty($_GET['manga_publisher'])) {
                        $tax_query[] = [
                            'taxonomy' => 'manga_publisher',
                            'field'    => 'slug',
                            'terms'    => sanitize_text_field($_GET['manga_publisher']),
                        ];
                    }

                    if (!empty($tax_query)) {
                        $tax_query['relation'] = 'AND';
                        $query->set('tax_query', $tax_query);
                    }
                }

                // --- Manga Volumes: filter by series_id meta ---
                if ($post_type === 'manga_volume') {
                    $series_id = isset($_GET['manga_series_id']) ? absint($_GET['manga_series_id']) : 0;

                    if ($series_id > 0) {
                        $query->set('meta_query', [
                            [
                                'key'     => 'series_id',
                                'value'   => $series_id,
                                'compare' => '=',
                                'type'    => 'NUMERIC',
                            ],
                        ]);
                    }
                }

                // --- Manga Chapters: filter by series_id and/or volume_id meta ---
                if ($post_type === 'manga_chapter') {
                    $series_id = isset($_GET['manga_series_id']) ? absint($_GET['manga_series_id']) : 0;
                    $volume_id = isset($_GET['manga_volume_id']) ? absint($_GET['manga_volume_id']) : 0;

                    $meta_query = [];

                    if ($series_id > 0) {
                        $meta_query[] = [
                            'key'     => 'series_id',
                            'value'   => $series_id,
                            'compare' => '=',
                            'type'    => 'NUMERIC',
                        ];
                    }

                    if ($volume_id > 0) {
                        $meta_query[] = [
                            'key'     => 'volume_id',
                            'value'   => $volume_id,
                            'compare' => '=',
                            'type'    => 'NUMERIC',
                        ];
                    }

                    if (!empty($meta_query)) {
                        $meta_query['relation'] = 'AND';
                        $query->set('meta_query', $meta_query);
                    }
                }
            }

            /**
             * AJAX: return volumes belonging to a manga series
             */
            public function toocheke_get_volumes_by_series()
            {
                check_ajax_referer('toocheke_manga_admin_nonce', 'nonce');

                if (! current_user_can('edit_posts')) {
                    wp_send_json_error('Unauthorized');
                }

                $series_id = isset($_GET['series_id']) ? absint($_GET['series_id']) : 0;

                if (! $series_id) {
                    wp_send_json_success([]);
                }

                $volumes = get_posts([
                    'post_type'      => 'manga_volume',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'meta_query'     => [
                        [
                            'key'     => 'series_id',
                            'value'   => $series_id,
                            'compare' => '=',
                            'type'    => 'NUMERIC',
                        ],
                    ],
                ]);

                $data = array_map(function($v) {
                    return [
                        'id'    => $v->ID,
                        'title' => $v->post_title,
                    ];
                }, $volumes);

                wp_send_json_success($data);
            }

}
