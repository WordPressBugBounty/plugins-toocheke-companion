<?php
/**
 * Series filter for the Chapters admin screen.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Toocheke_Companion_Chapter_Series_Filter
{
    const TAXONOMY   = 'chapters';
    const MAX_SERIES = 200;

    private $term_ids = null;

    public function __construct()
    {
        add_action('load-edit-tags.php', [$this, 'init']);
    }

    public function init()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
        if (self::TAXONOMY !== $taxonomy) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        if ($this->series_id()) {
            add_filter('terms_clauses', [$this, 'filter_clauses'], 10, 2);
            add_action(self::TAXONOMY . '_pre_add_form', [$this, 'stop_filtering']);
        }
    }

    private function series_id()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['sid']) ? absint(wp_unslash($_GET['sid'])) : 0;
    }

    public function filter_clauses($clauses, $taxonomies)
    {
        global $wpdb;

        if ([self::TAXONOMY] !== array_values((array) $taxonomies)) {
            return $clauses;
        }

        $ids = $this->term_ids();

        $clauses['where'] .= $ids ? ' AND t.term_id IN (' . implode(',', $ids) . ')' : ' AND 1 = 0';

        return $clauses;
    }

    private function term_ids()
    {
        global $wpdb;

        if (null !== $this->term_ids) {
            return $this->term_ids;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ids = array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT tt.term_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = 'comic' AND p.post_parent = %d AND p.post_status NOT IN ('trash', 'auto-draft')",
            self::TAXONOMY,
            $this->series_id()
        )));

        foreach ($ids as $id) {
            $ids = array_merge($ids, array_map('absint', get_ancestors($id, self::TAXONOMY, 'taxonomy')));
        }

        $this->term_ids = array_values(array_unique($ids));

        return $this->term_ids;
    }

    public function stop_filtering()
    {
        remove_filter('terms_clauses', [$this, 'filter_clauses'], 10);
    }

    public function enqueue()
    {
        $series = get_posts([
            'post_type'              => 'series',
            'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'posts_per_page'         => self::MAX_SERIES,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (! $series) {
            return;
        }

        $current = $this->series_id();
        $html    = '<label for="toocheke-series-filter" class="screen-reader-text">' . esc_html__('Filter by series', 'toocheke-companion') . '</label>';
        $html   .= '<select name="sid" id="toocheke-series-filter" class="toocheke-series-filter">';
        $html   .= '<option value="0">' . esc_html__('All series', 'toocheke-companion') . '</option>';

        foreach ($series as $item) {
            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                $item->ID,
                selected($current, $item->ID, false),
                esc_html(get_the_title($item))
            );
        }

        $html .= '</select>';

        wp_add_inline_style('common', '.search-box .toocheke-series-filter{margin-right:4px;max-width:220px;height:32px;min-height:32px;line-height:1;padding-top:0;padding-bottom:0}');

        wp_register_script('toocheke-chapter-series-filter', '', [], TOOCHEKE_COMPANION_VERSION, true);
        wp_enqueue_script('toocheke-chapter-series-filter');
        wp_add_inline_script(
            'toocheke-chapter-series-filter',
            '(function(){var f=document.querySelector(".search-form"),b=f&&f.querySelector(".search-box");if(!f){return;}if(!b){b=document.createElement("p");b.className="search-box";f.appendChild(b);}b.insertAdjacentHTML("afterbegin",' . wp_json_encode($html) . ');b.querySelector("select[name=sid]").addEventListener("change",function(){f.submit();});}());'
        );
    }
}
