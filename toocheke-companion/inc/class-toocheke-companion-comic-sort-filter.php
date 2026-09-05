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
             * Full recalculation of comic numbering -- NOT hooked to save/delete
             * anymore (see toocheke_comic_numbering_on_status_transition(),
             * toocheke_comic_numbering_on_post_updated(), and
             * toocheke_comic_numbering_on_before_delete() below for the
             * incremental, O(1)-per-event replacement that is). This full-table
             * version is kept as a manual/one-off utility -- e.g. to run once
             * after a bulk import, or if numbering ever needs a from-scratch
             * rebuild -- since it recomputes every published comic in one pass
             * rather than a single comic's position.
             */
            public function toocheke_update_comic_post_numbers()
            {
                /* numbering the published posts, starting with 1 for oldest;
        / creates and updates custom field 'incr_number' -- the comic's GLOBAL
        / position across every published comic, regardless of series (this is
        / the original, unscoped behavior);
        / also creates and updates custom field 'incr_number_series' -- the
        / comic's position within its own series only, where a comic's series
        / is its post_parent (the same post_parent-as-series convention used
        / elsewhere in this plugin -- see e.g. class-toocheke-companion-bluesky.php,
        / -notifications.php, -rss-feeds.php). Comics with no parent
        / (post_parent = 0, i.e. not in a series) are counted together as
        / their own "no series" group for incr_number_series.
        / Both counters are derived from the same post_date-ASC pass, so a
        / series' subsequence of that pass is still oldest-first -- no
        / separate query/ordering needed for the series counter.
        / to show in post (within the loop) use <?php echo get_post_meta($post->ID,'incr_number',true); ?>
        / alchymyth 2010; incr_number_series added later */
                global $wpdb;

                $pageposts = $wpdb->get_results("SELECT $wpdb->posts.* FROM $wpdb->posts
WHERE $wpdb->posts.post_status = 'publish'
AND $wpdb->posts.post_type = 'comic'
ORDER BY $wpdb->posts.post_date ASC"); // WPCS: unprepared SQL OK

                $counts = 0;
                $series_counts = []; // keyed by post_parent (series ID); 0 = no series

                if ($pageposts):
                    foreach ($pageposts as $post):
                        $counts++;
                        update_post_meta($post->ID, 'incr_number', $counts);

                        $series_id = (int) $post->post_parent;
                        if (! isset($series_counts[$series_id])) {
                            $series_counts[$series_id] = 0;
                        }
                        $series_counts[$series_id]++;
                        update_post_meta($post->ID, 'incr_number_series', $series_counts[$series_id]);
                    endforeach;
                endif;
            }

            /**
             * Incremental comic numbering.
             *
             * Maintains 'incr_number' (a comic's GLOBAL position across every
             * published comic) and 'incr_number_series' (its position within
             * its own series, i.e. its post_parent -- see the post_parent-as-
             * series convention used elsewhere in this plugin) WITHOUT
             * recalculating the whole comic table on every save. Each hook
             * below does a small, fixed number of targeted queries, so the
             * cost of a single publish/edit/delete stays flat (O(1)) whether
             * the site has dozens of comics or tens of thousands.
             *
             * Three primitives, composed by the hooks below:
             *   - toocheke_comic_numbering_insert()  -- a comic ENTERING the
             *     numbered set (new publish, restored from trash, a draft
             *     finally published). Counts how many published comics sort
             *     before it (globally, and within its series), shifts
             *     everyone from that position onward up by 1, then writes
             *     its own two values directly.
             *   - toocheke_comic_numbering_remove()  -- a comic LEAVING the
             *     numbered set (unpublished, trashed, force-deleted). Reads
             *     its own current numbers (still accurate at this point --
             *     nothing has touched them yet), shifts everyone after it
             *     down by 1, then clears its own two values.
             *   - A "move" (post_date and/or post_parent changed while
             *     staying published) is simply remove() using the OLD
             *     date/parent, followed by insert() using the NEW ones --
             *     no separate "move" SQL needed. remove() runs first and
             *     excludes the moving post from its own shift, so insert()'s
             *     position count (run against the now-already-closed-up
             *     table) is accurate, and its shift-up correctly opens a gap
             *     at the new position.
             *
             * Hooked from toocheke-companion.php:
             *   - transition_post_status : detects entering/leaving 'publish'
             *   - post_updated           : detects post_date/post_parent
             *                              changes for a comic that was, and
             *                              still is, published
             *   - before_delete_post     : safety net for a force-delete of a
             *                              still-published comic that skips
             *                              trash entirely (deleted_post fires
             *                              too late -- the post's meta is
             *                              already gone by then)
             */

            /**
             * transition_post_status callback.
             */
            public function toocheke_comic_numbering_on_status_transition($new_status, $old_status, $post)
            {
                if (! $post || 'comic' !== $post->post_type || $new_status === $old_status) {
                    return; // wrong post type, or not an actual transition
                }

                $was_published = ('publish' === $old_status);
                $is_published  = ('publish' === $new_status);

                if (! $was_published && $is_published) {
                    // Entering the set: new publish, restored from trash, or a draft finally published.
                    $this->toocheke_comic_numbering_insert($post->ID, $post->post_date, (int) $post->post_parent);
                } elseif ($was_published && ! $is_published) {
                    // Leaving the set: unpublished, trashed, etc.
                    $this->toocheke_comic_numbering_remove($post->ID, (int) $post->post_parent);
                }
                // publish -> publish isn't a real transition (caught by the
                // $new_status === $old_status check above); a comic that STAYS
                // published but moves position is handled by post_updated instead.
            }

            /**
             * post_updated callback. Only relevant when the comic was
             * published both before and after the save (entering/leaving
             * publish is handled by the status-transition hook above) and its
             * post_date or post_parent actually changed underneath it.
             */
            public function toocheke_comic_numbering_on_post_updated($post_id, $post_after, $post_before)
            {
                if ('comic' !== $post_after->post_type) {
                    return;
                }
                if ('publish' !== $post_after->post_status || 'publish' !== $post_before->post_status) {
                    return; // entering/leaving 'publish' is handled by transition_post_status
                }

                $date_changed   = ($post_after->post_date !== $post_before->post_date);
                $parent_changed = ((int) $post_after->post_parent !== (int) $post_before->post_parent);

                if (! $date_changed && ! $parent_changed) {
                    return; // nothing position-relevant changed (e.g. just editing dialogue) -- zero extra queries
                }

                // remove() using the OLD parent (needed for the series shift
                // regardless of whether it's the date or the parent that
                // changed), then insert() using the NEW date/parent.
                $this->toocheke_comic_numbering_remove($post_id, (int) $post_before->post_parent);
                $this->toocheke_comic_numbering_insert($post_id, $post_after->post_date, (int) $post_after->post_parent);
            }

            /**
             * before_delete_post callback. Safety net for a force-delete of a
             * still-published comic that skips trash entirely -- the far more
             * common trash-then-delete path is already handled by the
             * status-transition hook when the comic moves to 'trash', so by
             * the time an actual delete happens the post is usually no
             * longer 'publish' and this is a no-op.
             */
            public function toocheke_comic_numbering_on_before_delete($post_id)
            {
                $post = get_post($post_id);
                if (! $post || 'comic' !== $post->post_type || 'publish' !== $post->post_status) {
                    return; // wasn't published/numbered in the first place -- nothing to shift
                }

                $this->toocheke_comic_numbering_remove($post_id, (int) $post->post_parent);
            }

            /**
             * Comic ENTERING the numbered set. Computes its global position
             * (and its position within its own series), shifts everyone from
             * that position onward up by 1, then writes its own two values.
             */
            public function toocheke_comic_numbering_insert($post_id, $post_date, $parent_id)
            {
                global $wpdb;

                $global_number = 1 + (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type = 'comic' AND post_status = 'publish'
                       AND (post_date < %s OR (post_date = %s AND ID < %d))",
                    $post_date,
                    $post_date,
                    $post_id
                ));

                $series_number = 1 + (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type = 'comic' AND post_status = 'publish' AND post_parent = %d
                       AND (post_date < %s OR (post_date = %s AND ID < %d))",
                    $parent_id,
                    $post_date,
                    $post_date,
                    $post_id
                ));

                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     SET pm.meta_value = CAST(pm.meta_value AS UNSIGNED) + 1
                     WHERE pm.meta_key = 'incr_number'
                       AND p.post_type = 'comic' AND p.post_status = 'publish'
                       AND CAST(pm.meta_value AS UNSIGNED) >= %d
                       AND pm.post_id != %d",
                    $global_number,
                    $post_id
                ));

                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     SET pm.meta_value = CAST(pm.meta_value AS UNSIGNED) + 1
                     WHERE pm.meta_key = 'incr_number_series'
                       AND p.post_type = 'comic' AND p.post_status = 'publish' AND p.post_parent = %d
                       AND CAST(pm.meta_value AS UNSIGNED) >= %d
                       AND pm.post_id != %d",
                    $parent_id,
                    $series_number,
                    $post_id
                ));

                update_post_meta($post_id, 'incr_number', $global_number);
                update_post_meta($post_id, 'incr_number_series', $series_number);
            }

            /**
             * Comic LEAVING the numbered set. Reads its own current numbers
             * (still accurate -- nothing has touched them yet), shifts
             * everyone after it down by 1, then clears its own two values so
             * nothing stale lingers if it's ever displayed by mistake.
             *
             * $parent_id must be the comic's series AT THE TIME its current
             * 'incr_number_series' was computed -- the caller passes the OLD
             * parent explicitly (rather than this re-reading the post's
             * current post_parent) since by the time this runs during a
             * "move" the post's own row may already reflect a NEW parent.
             */
            public function toocheke_comic_numbering_remove($post_id, $parent_id)
            {
                global $wpdb;

                $old_global_number = (int) get_post_meta($post_id, 'incr_number', true);
                $old_series_number = (int) get_post_meta($post_id, 'incr_number_series', true);

                if ($old_global_number > 0) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                         SET pm.meta_value = CAST(pm.meta_value AS UNSIGNED) - 1
                         WHERE pm.meta_key = 'incr_number'
                           AND p.post_type = 'comic' AND p.post_status = 'publish'
                           AND CAST(pm.meta_value AS UNSIGNED) > %d
                           AND pm.post_id != %d",
                        $old_global_number,
                        $post_id
                    ));
                }

                if ($old_series_number > 0) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                         SET pm.meta_value = CAST(pm.meta_value AS UNSIGNED) - 1
                         WHERE pm.meta_key = 'incr_number_series'
                           AND p.post_type = 'comic' AND p.post_status = 'publish' AND p.post_parent = %d
                           AND CAST(pm.meta_value AS UNSIGNED) > %d
                           AND pm.post_id != %d",
                        $parent_id,
                        $old_series_number,
                        $post_id
                    ));
                }

                delete_post_meta($post_id, 'incr_number');
                delete_post_meta($post_id, 'incr_number_series');
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
                            absint($series->ID),
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
