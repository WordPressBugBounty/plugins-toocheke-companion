<?php
/**
 * Handles the admin-list filter dropdown and custom sort order for the
 * comic post type, plus related cleanup (renumbering comics, deleting a
 * series' comics when the series is deleted).
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Comic_Sort_Filter
{
            /* Add comics tags functionality */
            public function toocheke_companion_add_comics_to_defalt_tax_archive($query)
            {

                if (is_tag() && $query->is_archive() && empty($query->query_vars['suppress_filters'])) {

                    $query->set('post_type', [
                        'post',
                        'comic',
                    ]);
                }
                return $query;
            }

            /**
             * The yearly archive templates (content-comicarchiveyearlytext.php,
             * -gallery.php, -thumbnail.php) loop over the main query directly
             * with have_posts()/the_post() instead of building their own
             * WP_Query. That happens in two different contexts, both of which
             * need the toocheke-comics-order option applied here rather than
             * as an 'order' arg inside those templates, since by the time the
             * templates run the main query has already been executed:
             *
             * 1. An actual year archive, e.g. /2014/?post_type=comic
             *    (is_date() is true).
             * 2. The default comic post-type archive, e.g. /comic/, when
             *    'toocheke-comics-archive'[layout_type] is set to one of the
             *    yearly-* options — content-comicdefaultarchive.php routes
             *    that case to the same yearly templates, but is_date() is
             *    false there since no year is present in the URL.
             */
            public function toocheke_companion_year_archive_order($query)
            {
                if (is_admin() || ! $query->is_main_query()) {
                    return $query;
                }

                $post_type = $query->get('post_type');
                $is_comic_archive = ('comic' === $post_type) || (is_array($post_type) && in_array('comic', $post_type, true));

                if (! $is_comic_archive) {
                    return $query;
                }

                $is_year_context = false;

                if ($query->is_date()) {
                    $is_year_context = true;
                } elseif ($query->is_post_type_archive('comic')) {
                    $archive_options = get_option('toocheke-comics-archive');
                    $layout_type = isset($archive_options['layout_type']) ? $archive_options['layout_type'] : '';
                    $yearly_layouts = ['yearly-plain-text-list', 'yearly-gallery', 'yearly-thumbnail-list'];
                    if (in_array($layout_type, $yearly_layouts, true)) {
                        $is_year_context = true;
                    }
                }

                if (! $is_year_context) {
                    return $query;
                }

                $comic_order = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
                $query->set('orderby', 'post_date');
                $query->set('order', $comic_order);

                return $query;
            }

            /**
             * Post Number.
             */
            public function toocheke_update_comic_post_numbers()
            {
                /* numbering the published posts, starting with 1 for oldest;
        / creates and updates custom field 'incr_number';
        / to show in post (within the loop) use <?php echo get_post_meta($post->ID,'incr_number',true); ?>
        / alchymyth 2010 */
                global $wpdb;
                //$querystr = "SELECT $wpdb->posts.* FROM $wpdb->posts
                //WHERE $wpdb->posts.post_status = 'publish'
                //AND $wpdb->posts.post_type = 'comic'
                //ORDER BY $wpdb->posts.post_date ASC";
                //$pageposts = $wpdb->get_results( $wpdb->get_results( $wpdb->prepare( $querystr, OBJECT))); // WPCS: unprepared SQL OK
                //$pageposts = $wpdb->get_results($querystr, OBJECT); // WPCS: unprepared SQL OK

                $pageposts = $wpdb->get_results("SELECT $wpdb->posts.* FROM $wpdb->posts
WHERE $wpdb->posts.post_status = 'publish'
AND $wpdb->posts.post_type = 'comic'
ORDER BY $wpdb->posts.post_date ASC"); // WPCS: unprepared SQL OK
                $counts = 0;
                if ($pageposts):
                    foreach ($pageposts as $post):
                        $counts++;
                        add_post_meta($post->ID, 'incr_number', $counts, true);
                        update_post_meta($post->ID, 'incr_number', $counts);
                    endforeach;
                endif;
            }

            /**
             * Delete all comics for a series
             */
            public function toocheke_delete_series_comics($post_id)
            {
                if ('series' != get_post_type($post_id)) {
                    return;
                }
                $args = [
                    'post_parent' => $post_id,
                    'post_type'   => 'comic',
                ];

                $comics = get_posts($args);

                if (empty($comics)) {
                    return;
                }

                if (is_array($comics) && count($comics) > 0) {

                    // Delete all the Children of the Parent Page
                    foreach ($comics as $comic) {
                        wp_delete_post($comic->ID, true);
                    }
                }
            }

                    /*
            * Sorting comic columns
            */
            //sort by views
            public function toocheke_companion_comics_sort($wp_query)
            {
                global $pagenow;
                if (! is_admin()) {
                    return $wp_query;
                }

                if ($pagenow == 'edit.php' && isset($_GET['orderby']) && ($_GET['orderby'] == 'views' || $_GET['orderby'] == 'likes')) {
                    $post_type = $wp_query->query['post_type'];
                    if ($post_type == 'comic') {
                        // get the orderby, if it is not set, leave it blank
                        $orderby = (! empty($wp_query->query['orderby'])) ? $wp_query->query['orderby'] : '';

                        // get the order and set it, we want our default to asc and not desc
                        $order = (! empty($wp_query->query['order'])) ? $wp_query->query['order'] : 'asc';
                        $wp_query->set('order', $order);
                        // set our orderby for our columns

                        switch ($orderby) {
                            case 'views':
                                $wp_query->set('meta_query', [
                                    'relation' => 'OR',
                                    [
                                        'key'     => 'post_views_count',
                                        'compare' => 'NOT EXISTS',
                                    ],
                                    [
                                        'key'     => 'post_views_count',
                                        'value'   => 0,
                                        'compare' => '>=',
                                    ],

                                ]);
                                break;

                            case 'likes':
                                $wp_query->set('meta_query', [
                                    'relation' => 'OR',
                                    [
                                        'key'     => '_post_like_count',
                                        'compare' => 'NOT EXISTS',
                                    ],
                                    [
                                        'key'     => '_post_like_count',
                                        'value'   => 0,
                                        'compare' => '>=',
                                    ],

                                ]);
                                break;
                            default:
                                $wp_query->set('orderby', 'menu_order');
                                break;
                        }
                    }
                    $wp_query->set('orderby', 'meta_value_num');
                    $wp_query->set('meta_type', 'NUMERIC');
                }
                return $wp_query;
            }

            /**
             *  Dropdown filter for comics
             */
            public function toocheke_comic_filter_dropdown($post_type)
            {
                if ($post_type !== 'comic') {
                    return;
                }

                // Existing Series filter
                $selected_series = isset($_GET['post_parent']) ? absint($_GET['post_parent']) : 0;

                $series_posts = get_posts([
                    'post_type'      => 'series',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);

                if (!empty($series_posts)) {
                    echo '<select name="post_parent" id="filter-by-series">';
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

                // Taxonomy filters
                $taxonomy_filters = [
                    'collections'     => __('All Collections', 'toocheke-companion'),
                    'chapters'        => __('All Chapters', 'toocheke-companion'),
                    'comic_tags'      => __('All Tags', 'toocheke-companion'),
                    'comic_locations' => __('All Locations', 'toocheke-companion'),
                    'comic_characters'=> __('All Characters', 'toocheke-companion'),
                ];

                foreach ($taxonomy_filters as $taxonomy => $all_label) {
                    $selected_term = isset($_GET[ $taxonomy ]) ? sanitize_text_field($_GET[ $taxonomy ]) : '';

                    $terms = get_terms([
                        'taxonomy'   => $taxonomy,
                        'hide_empty' => true,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                    ]);

                    if (is_wp_error($terms) || empty($terms)) {
                        continue;
                    }

                    echo '<select name="' . esc_attr($taxonomy) . '" id="filter-by-' . esc_attr($taxonomy) . '">';
                    echo '<option value="">' . esc_html($all_label) . '</option>';

                    foreach ($terms as $term) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr($term->slug),
                            selected($selected_term, $term->slug, false),
                            esc_html($term->name)
                        );
                    }

                    echo '</select>';
                }
            }

            /**
             * Apply the Series dropdown filter to the query
             */
            public function toocheke_comic_filter_query($query)
            {
                global $pagenow;

                if (
                    ! is_admin() ||
                    $pagenow !== 'edit.php' ||
                    ! $query->is_main_query() ||
                    ! isset($_GET['post_type']) ||
                    $_GET['post_type'] !== 'comic'
                ) {
                    return;
                }

                // Series (post_parent) filter
                $series_id = isset($_GET['post_parent']) ? absint($_GET['post_parent']) : 0;
                if ($series_id > 0) {
                    $query->set('post_parent', $series_id);
                } else {
                    // Explicitly clear post_parent so WordPress doesn't filter
                    // to "no parent" comics when post_parent=0 is in the URL
                    $query->set('post_parent', '');
                }

                // Taxonomy filters
                $taxonomies = ['collections', 'chapters', 'comic_tags', 'comic_locations', 'comic_characters'];
                $tax_query  = [];

                foreach ($taxonomies as $taxonomy) {
                    if (!empty($_GET[$taxonomy])) {
                        $tax_query[] = [
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => sanitize_text_field($_GET[$taxonomy]),
                        ];
                    }
                }

                if (!empty($tax_query)) {
                    $tax_query['relation'] = 'AND';
                    $query->set('tax_query', $tax_query);
                }
            }

}
