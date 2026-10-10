<?php
/**
 * WordPress Dashboard widgets for Toocheke Companion.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Toocheke_Companion_Dashboard_Widgets
{
    const CACHE_TTL      = 600;
    const ROW_LIMIT      = 10;
    const LIST_LIMIT     = 5;
    const GAP_DAYS       = 7;
    const PATREON_TTL    = 3600;
    const PATREON_BATCH  = 200;
    const PATREON_MAX    = 10000;
    const DISMISS_META   = 'toocheke_comicscout_widget_dismissed';
    const CACHE_PREFIX   = 'tk_dw_';
    const CACHE_KEYS     = ['overview', 'top', 'attention', 'series', 'manga', 'manga_table', 'comicscout', 'patreon'];
    const WATCHED_TYPES  = ['comic', 'series', 'manga_series', 'manga_volume', 'manga_chapter'];

    public function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'register_widgets']);
        add_action('admin_head-index.php', [$this, 'print_styles']);
        add_action('admin_post_toocheke_dismiss_comicscout', [$this, 'dismiss_comicscout']);
        add_action('save_post', [$this, 'maybe_flush'], 10, 2);
        add_action('transition_post_status', [$this, 'maybe_flush_on_transition'], 10, 3);
        add_action('deleted_post', [$this, 'maybe_flush_by_id']);
        add_action('trashed_post', [$this, 'maybe_flush_by_id']);
        add_action('untrashed_post', [$this, 'maybe_flush_by_id']);
    }

    public function register_widgets()
    {
        if (! apply_filters('toocheke_dashboard_widgets_enabled', true) || ! current_user_can('edit_posts')) {
            return;
        }

        $this->add('toocheke_dw_overview', __('Toocheke: Comics at a Glance', 'toocheke-companion'), 'render_overview');

        if (get_option('toocheke-comic-likes') && 1 == get_option('toocheke-comic-likes')) {
            $this->add('toocheke_dw_top', __('Toocheke: Top Comics', 'toocheke-companion'), 'render_top');
        }

        $this->add('toocheke_dw_attention', __('Toocheke: Needs Attention', 'toocheke-companion'), 'render_attention');

        if ($this->has_posts('series')) {
            $this->add('toocheke_dw_series', __('Toocheke: Series Overview', 'toocheke-companion'), 'render_series');
        }

        if ($this->has_posts('manga_series')) {
            $this->add('toocheke_dw_manga', __('Toocheke: Manga Progress', 'toocheke-companion'), 'render_manga');
            $this->add('toocheke_dw_manga_table', __('Toocheke: Manga Series Overview', 'toocheke-companion'), 'render_manga_table');
        }

        if (current_user_can('moderate_comments')) {
            $this->add('toocheke_dw_comments', __('Toocheke: Recent Comic Comments', 'toocheke-companion'), 'render_comments');
        }

        if (current_user_can('manage_options') && $this->notifications_active()) {
            $this->add('toocheke_dw_subscribers', __('Toocheke: Email Subscribers', 'toocheke-companion'), 'render_subscribers');
        }

        if (current_user_can('manage_options') && $this->patreon_active()) {
            $this->add('toocheke_dw_patreon', __('Toocheke: Patreon Summary', 'toocheke-companion'), 'render_patreon');
        }

        if (! get_user_meta(get_current_user_id(), self::DISMISS_META, true)) {
            $this->add('toocheke_dw_comicscout', __('Get Discovered on ComicScout', 'toocheke-companion'), 'render_comicscout');
        }
    }

    private function add($id, $title, $method)
    {
        wp_add_dashboard_widget($id, $title, [$this, $method]);
    }

    public function print_styles()
    {
        echo '<style>'
            . '.tk-dw-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px;padding:0;list-style:none}'
            . '.tk-dw-stats li{flex:1 1 70px;margin:0;padding:8px;background:#f6f7f7;border-radius:4px;text-align:center}'
            . '.tk-dw-stats strong{display:block;font-size:20px;line-height:1.2}'
            . '.tk-dw-stats a{text-decoration:none}'
            . '.tk-dw-warn{margin:0 0 12px;padding:6px 10px;border-left:4px solid #dba617;background:#fcf9e8;color:#1d2327}'
            . '.tk-dw-ok{color:#007017}'
            . '.tk-dw-table{width:100%;border-collapse:collapse}'
            . '.tk-dw-table th,.tk-dw-table td{padding:6px 4px;text-align:left;border-bottom:1px solid #f0f0f1}'
            . '.tk-dw-table th.n,.tk-dw-table td.n{text-align:right;white-space:nowrap}'
            . '.tk-dw-list{margin:0 0 12px;padding:0;list-style:none}'
            . '.tk-dw-list li{display:flex;justify-content:space-between;gap:8px;margin:0;padding:6px 0;border-bottom:1px solid #f0f0f1}'
            . '.tk-dw-list .m{color:#646970;white-space:nowrap}'
            . '.tk-dw-links{margin:12px 0 0}'
            . '.tk-dw-links a+a:before{content:"\\00b7";margin:0 6px;color:#646970}'
            . '.tk-dw-dismiss{float:right;font-size:12px}'
            . '</style>';
    }

    private function remember($key, callable $callback)
    {
        $cache_key = self::CACHE_PREFIX . $key;
        $value     = get_transient($cache_key);
        if (false === $value) {
            $value = $callback();
            set_transient($cache_key, $value, self::CACHE_TTL);
        }
        return $value;
    }

    public function maybe_flush($post_id, $post)
    {
        if ($post instanceof WP_Post && in_array($post->post_type, self::WATCHED_TYPES, true)) {
            $this->flush_cache();
        }
    }

    public function maybe_flush_on_transition($new_status, $old_status, $post)
    {
        if ($new_status !== $old_status && $post instanceof WP_Post && in_array($post->post_type, self::WATCHED_TYPES, true)) {
            $this->flush_cache();
        }
    }

    public function maybe_flush_by_id($post_id)
    {
        if (in_array(get_post_type($post_id), self::WATCHED_TYPES, true)) {
            $this->flush_cache();
        }
    }

    public function flush_cache()
    {
        foreach (self::CACHE_KEYS as $key) {
            delete_transient(self::CACHE_PREFIX . $key);
        }
    }

    private function has_posts($post_type)
    {
        if (! post_type_exists($post_type)) {
            return false;
        }
        $counts = wp_count_posts($post_type);
        return ((int) $counts->publish + (int) $counts->future + (int) $counts->draft + (int) $counts->pending + (int) $counts->private) > 0;
    }

    private function title($post_id)
    {
        $title = get_the_title($post_id);
        return '' !== $title ? $title : __('(no title)', 'toocheke-companion');
    }

    private function edit_link($post_id)
    {
        $link = current_user_can('edit_post', $post_id) ? get_edit_post_link($post_id) : '';
        return $link ? $link : (string) get_permalink($post_id);
    }

    private function list_url($post_type, array $args = [])
    {
        return add_query_arg(array_merge(['post_type' => $post_type], $args), admin_url('edit.php'));
    }

    private function ago($timestamp)
    {
        if ($timestamp <= 0) {
            return '—';
        }
        /* translators: %s: time difference, e.g. "3 days" */
        return sprintf(__('%s ago', 'toocheke-companion'), human_time_diff($timestamp));
    }

    private function gmt_to_timestamp($gmt)
    {
        if (empty($gmt) || 0 === strpos($gmt, '0000')) {
            return 0;
        }
        $timestamp = strtotime($gmt . ' UTC');
        return $timestamp ? (int) $timestamp : 0;
    }

    private function local_to_timestamp($local)
    {
        $date = date_create((string) $local, wp_timezone());
        return $date ? $date->getTimestamp() : 0;
    }

    private function first_post($post_type, $status, $order)
    {
        $ids = get_posts([
            'post_type'              => $post_type,
            'post_status'            => $status,
            'posts_per_page'         => 1,
            'orderby'                => 'date',
            'order'                  => $order,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        if (empty($ids)) {
            return null;
        }
        $id = (int) $ids[0];
        return ['id' => $id, 'title' => $this->title($id), 'time' => (int) get_post_time('U', true, $id)];
    }

    private function print_links(array $links)
    {
        echo '<p class="tk-dw-links">';
        foreach ($links as $label => $url) {
            echo '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</p>';
    }

    public function render_overview()
    {
        $data = $this->remember('overview', function () {
            $counts = wp_count_posts('comic');
            return [
                'publish' => (int) $counts->publish,
                'future'  => (int) $counts->future,
                'draft'   => (int) $counts->draft,
                'pending' => (int) $counts->pending,
                'latest'  => $this->first_post('comic', 'publish', 'DESC'),
                'next'    => $this->first_post('comic', 'future', 'ASC'),
            ];
        });

        $stats = [
            __('Published', 'toocheke-companion') => [$data['publish'], $this->list_url('comic', ['post_status' => 'publish'])],
            __('Scheduled', 'toocheke-companion') => [$data['future'], $this->list_url('comic', ['post_status' => 'future'])],
            __('Drafts', 'toocheke-companion')    => [$data['draft'], $this->list_url('comic', ['post_status' => 'draft'])],
            __('Pending', 'toocheke-companion')   => [$data['pending'], $this->list_url('comic', ['post_status' => 'pending'])],
        ];

        $gap_days = (int) apply_filters('toocheke_dashboard_gap_days', self::GAP_DAYS);
        if ($data['latest'] && ! $data['next'] && $gap_days > 0) {
            $days = (int) floor((time() - $data['latest']['time']) / DAY_IN_SECONDS);
            if ($days >= $gap_days) {
                echo '<p class="tk-dw-warn">' . esc_html(sprintf(
                    /* translators: %d: number of days */
                    _n('Your last comic was published %d day ago and nothing is scheduled.', 'Your last comic was published %d days ago and nothing is scheduled.', $days, 'toocheke-companion'),
                    $days
                )) . '</p>';
            }
        }

        echo '<ul class="tk-dw-stats">';
        foreach ($stats as $label => $stat) {
            echo '<li><a href="' . esc_url($stat[1]) . '"><strong>' . esc_html(number_format_i18n($stat[0])) . '</strong>' . esc_html($label) . '</a></li>';
        }
        echo '</ul>';

        if ($data['latest']) {
            echo '<p><strong>' . esc_html__('Latest:', 'toocheke-companion') . '</strong> <a href="' . esc_url($this->edit_link($data['latest']['id'])) . '">'
                . esc_html($data['latest']['title']) . '</a> &middot; ' . esc_html($this->ago($data['latest']['time'])) . '</p>';
        } else {
            echo '<p>' . esc_html__('No comics published yet.', 'toocheke-companion') . '</p>';
        }

        if ($data['next']) {
            echo '<p><strong>' . esc_html__('Next scheduled:', 'toocheke-companion') . '</strong> <a href="' . esc_url($this->edit_link($data['next']['id'])) . '">'
                . esc_html($data['next']['title']) . '</a> &middot; '
                . esc_html(sprintf(
                    /* translators: %s: time difference, e.g. "2 days" */
                    __('in %s', 'toocheke-companion'),
                    human_time_diff(time(), $data['next']['time'])
                )) . '</p>';
        }

        $this->print_links([
            __('All Comics', 'toocheke-companion') => $this->list_url('comic'),
            __('Add New', 'toocheke-companion')    => admin_url('post-new.php?post_type=comic'),
        ]);
    }

    public function render_top()
    {
        $items = $this->remember('top', function () {
            $ids = get_posts([
                'post_type'              => 'comic',
                'post_status'            => 'publish',
                'posts_per_page'         => self::LIST_LIMIT,
                'meta_key'               => '_post_like_count', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- cached; limited to 5 rows.
                'orderby'                => 'meta_value_num',
                'order'                  => 'DESC',
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
            ]);
            $items = [];
            foreach ($ids as $id) {
                $likes = (int) get_post_meta($id, '_post_like_count', true);
                if ($likes > 0) {
                    $items[] = ['id' => (int) $id, 'title' => $this->title($id), 'likes' => $likes];
                }
            }
            return $items;
        });

        if (empty($items)) {
            echo '<p>' . esc_html__('No comic likes yet.', 'toocheke-companion') . '</p>';
        } else {
            echo '<ol class="tk-dw-list">';
            foreach ($items as $item) {
                echo '<li><a href="' . esc_url($this->edit_link($item['id'])) . '">' . esc_html($item['title']) . '</a>'
                    . '<span class="m">' . esc_html(sprintf(
                        /* translators: %s: number of likes */
                        _n('%s like', '%s likes', $item['likes'], 'toocheke-companion'),
                        number_format_i18n($item['likes'])
                    )) . '</span></li>';
            }
            echo '</ol>';
        }

        $this->print_links([
            __('Sort comics by likes', 'toocheke-companion') => $this->list_url('comic', ['orderby' => 'likes', 'order' => 'desc']),
        ]);
    }

    private function attention_query(array $args)
    {
        $query = new WP_Query(array_merge([
            'post_type'              => 'comic',
            'post_status'            => ['publish', 'future'],
            'posts_per_page'         => self::LIST_LIMIT,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ], $args));

        $items = [];
        foreach ($query->posts as $id) {
            $items[] = ['id' => (int) $id, 'title' => $this->title($id)];
        }
        return ['total' => (int) $query->found_posts, 'items' => $items];
    }

    public function render_attention()
    {
        $data = $this->remember('attention', function () {
            return [
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- cached; limited to 5 rows.
                'no_image'  => $this->attention_query(['meta_query' => [['key' => '_thumbnail_id', 'compare' => 'NOT EXISTS']]]),
                'no_series' => (int) wp_count_posts('series')->publish > 0 ? $this->attention_query(['post_parent' => 0]) : null,
            ];
        });

        $sections = [
            __('Comics without a featured image', 'toocheke-companion') => $data['no_image'],
            __('Comics not assigned to a series', 'toocheke-companion')  => $data['no_series'],
        ];

        $flagged = false;
        foreach ($sections as $label => $section) {
            if (empty($section) || empty($section['total'])) {
                continue;
            }
            $flagged = true;
            echo '<p><strong>' . esc_html($label) . '</strong> (' . esc_html(number_format_i18n($section['total'])) . ')</p><ul class="tk-dw-list">';
            foreach ($section['items'] as $item) {
                echo '<li><a href="' . esc_url($this->edit_link($item['id'])) . '">' . esc_html($item['title']) . '</a></li>';
            }
            if ($section['total'] > count($section['items'])) {
                echo '<li><span class="m">' . esc_html(sprintf(
                    /* translators: %s: number of additional comics */
                    __('and %s more', 'toocheke-companion'),
                    number_format_i18n($section['total'] - count($section['items']))
                )) . '</span></li>';
            }
            echo '</ul>';
        }

        if (! $flagged) {
            echo '<p class="tk-dw-ok">' . esc_html__('Everything looks good.', 'toocheke-companion') . '</p>';
        }
    }

    private function index_stats($rows)
    {
        $stats = [];
        foreach ((array) $rows as $row) {
            $stats[(int) $row->parent_id] = ['total' => (int) $row->total, 'last' => $this->gmt_to_timestamp($row->last_gmt)];
        }
        return $stats;
    }

    private function manga_stats($post_type)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- result is cached in a transient by the caller.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value AS parent_id, COUNT(*) AS total, MAX(p.post_date_gmt) AS last_gmt FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s WHERE p.post_type = %s AND p.post_status = %s GROUP BY pm.meta_value",
            'series_id',
            $post_type,
            'publish'
        ));
        return $this->index_stats($rows);
    }

    private function series_rows($post_type)
    {
        $query = new WP_Query([
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => self::ROW_LIMIT,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $rows = [];
        foreach ($query->posts as $id) {
            $rows[] = ['id' => (int) $id, 'title' => $this->title($id)];
        }
        return ['rows' => $rows, 'total' => (int) $query->found_posts];
    }

    public function render_series()
    {
        $data = $this->remember('series', function () {
            global $wpdb;
            $list  = $this->series_rows('series');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- result is cached in a transient by the caller.
            $stats = $this->index_stats($wpdb->get_results($wpdb->prepare(
                "SELECT post_parent AS parent_id, COUNT(*) AS total, MAX(post_date_gmt) AS last_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_parent > 0 GROUP BY post_parent",
                'comic',
                'publish'
            )));
            foreach ($list['rows'] as &$row) {
                $row += isset($stats[$row['id']]) ? $stats[$row['id']] : ['total' => 0, 'last' => 0];
            }
            unset($row);
            return $list;
        });

        if (empty($data['rows'])) {
            echo '<p>' . esc_html__('No published series yet.', 'toocheke-companion') . '</p>';
            return;
        }

        echo '<table class="tk-dw-table"><thead><tr><th>' . esc_html__('Series', 'toocheke-companion') . '</th><th class="n">'
            . esc_html__('Comics', 'toocheke-companion') . '</th><th class="n">' . esc_html__('Last update', 'toocheke-companion') . '</th></tr></thead><tbody>';
        foreach ($data['rows'] as $row) {
            echo '<tr><td><a href="' . esc_url($this->edit_link($row['id'])) . '">' . esc_html($row['title']) . '</a></td><td class="n">'
                . '<a href="' . esc_url($this->list_url('comic', ['post_parent' => $row['id']])) . '">' . esc_html(number_format_i18n($row['total'])) . '</a></td>'
                . '<td class="n">' . esc_html($this->ago($row['last'])) . '</td></tr>';
        }
        echo '</tbody></table>';

        $this->print_links([
            __('All Series', 'toocheke-companion') => $this->list_url('series'),
            __('Add New', 'toocheke-companion')    => admin_url('post-new.php?post_type=series'),
        ]);
    }

    public function render_manga()
    {
        $data = $this->remember('manga', function () {
            $chapters = wp_count_posts('manga_chapter');
            return [
                'series'   => (int) wp_count_posts('manga_series')->publish,
                'volumes'  => (int) wp_count_posts('manga_volume')->publish,
                'chapters' => (int) $chapters->publish,
                'drafts'   => (int) $chapters->draft,
                'latest'   => $this->first_post('manga_chapter', 'publish', 'DESC'),
            ];
        });

        $stats = [
            __('Series', 'toocheke-companion')   => [$data['series'], $this->list_url('manga_series')],
            __('Volumes', 'toocheke-companion')  => [$data['volumes'], $this->list_url('manga_volume')],
            __('Chapters', 'toocheke-companion') => [$data['chapters'], $this->list_url('manga_chapter', ['post_status' => 'publish'])],
            __('Drafts', 'toocheke-companion')   => [$data['drafts'], $this->list_url('manga_chapter', ['post_status' => 'draft'])],
        ];

        echo '<ul class="tk-dw-stats">';
        foreach ($stats as $label => $stat) {
            echo '<li><a href="' . esc_url($stat[1]) . '"><strong>' . esc_html(number_format_i18n($stat[0])) . '</strong>' . esc_html($label) . '</a></li>';
        }
        echo '</ul>';

        if ($data['latest']) {
            echo '<p><strong>' . esc_html__('Latest chapter:', 'toocheke-companion') . '</strong> <a href="' . esc_url($this->edit_link($data['latest']['id'])) . '">'
                . esc_html($data['latest']['title']) . '</a> &middot; ' . esc_html($this->ago($data['latest']['time'])) . '</p>';
        } else {
            echo '<p>' . esc_html__('No chapters published yet.', 'toocheke-companion') . '</p>';
        }

        $this->print_links([
            __('All Chapters', 'toocheke-companion') => $this->list_url('manga_chapter'),
            __('Add Chapter', 'toocheke-companion')  => admin_url('post-new.php?post_type=manga_chapter'),
        ]);
    }

    public function render_manga_table()
    {
        $data = $this->remember('manga_table', function () {
            $list     = $this->series_rows('manga_series');
            $volumes  = $this->manga_stats('manga_volume');
            $chapters = $this->manga_stats('manga_chapter');
            foreach ($list['rows'] as &$row) {
                $row['volumes']  = isset($volumes[$row['id']]) ? $volumes[$row['id']]['total'] : 0;
                $row['chapters'] = isset($chapters[$row['id']]) ? $chapters[$row['id']]['total'] : 0;
                $row['last']     = isset($chapters[$row['id']]) ? $chapters[$row['id']]['last'] : 0;
            }
            unset($row);
            return $list;
        });

        if (empty($data['rows'])) {
            echo '<p>' . esc_html__('No published manga series yet.', 'toocheke-companion') . '</p>';
            return;
        }

        echo '<table class="tk-dw-table"><thead><tr><th>' . esc_html__('Series', 'toocheke-companion') . '</th><th class="n">'
            . esc_html__('Volumes', 'toocheke-companion') . '</th><th class="n">' . esc_html__('Chapters', 'toocheke-companion') . '</th><th class="n">'
            . esc_html__('Last chapter', 'toocheke-companion') . '</th></tr></thead><tbody>';
        foreach ($data['rows'] as $row) {
            $filter = ['manga_series_id' => $row['id']];
            echo '<tr><td><a href="' . esc_url($this->edit_link($row['id'])) . '">' . esc_html($row['title']) . '</a></td>'
                . '<td class="n"><a href="' . esc_url($this->list_url('manga_volume', $filter)) . '">' . esc_html(number_format_i18n($row['volumes'])) . '</a></td>'
                . '<td class="n"><a href="' . esc_url($this->list_url('manga_chapter', $filter)) . '">' . esc_html(number_format_i18n($row['chapters'])) . '</a></td>'
                . '<td class="n">' . esc_html($this->ago($row['last'])) . '</td></tr>';
        }
        echo '</tbody></table>';

        $this->print_links([
            __('All Manga Series', 'toocheke-companion') => $this->list_url('manga_series'),
            __('All Volumes', 'toocheke-companion')      => $this->list_url('manga_volume'),
        ]);
    }

    public function render_comments()
    {
        $comments = get_comments([
            'post_type' => 'comic',
            'status'    => 'approve',
            'number'    => self::LIST_LIMIT,
            'orderby'   => 'comment_date_gmt',
            'order'     => 'DESC',
        ]);
        $pending = (int) get_comments(['post_type' => 'comic', 'status' => 'hold', 'count' => true]);

        if ($pending > 0) {
            echo '<p class="tk-dw-warn"><a href="' . esc_url(add_query_arg(['comment_status' => 'moderated'], admin_url('edit-comments.php'))) . '">'
                . esc_html(sprintf(
                    /* translators: %s: number of comments */
                    _n('%s comic comment is awaiting moderation.', '%s comic comments are awaiting moderation.', $pending, 'toocheke-companion'),
                    number_format_i18n($pending)
                )) . '</a></p>';
        }

        if (empty($comments)) {
            echo '<p>' . esc_html__('No comments on comics yet.', 'toocheke-companion') . '</p>';
            return;
        }

        echo '<ul class="tk-dw-list">';
        foreach ($comments as $comment) {
            $link = get_edit_comment_link($comment);
            $link = $link ? $link : get_comment_link($comment);
            echo '<li><span><strong>' . esc_html(get_comment_author($comment)) . '</strong> '
                . esc_html__('on', 'toocheke-companion') . ' <em>' . esc_html($this->title($comment->comment_post_ID)) . '</em><br>'
                . '<a href="' . esc_url($link) . '">' . esc_html(wp_trim_words(wp_strip_all_tags($comment->comment_content), 14)) . '</a></span>'
                . '<span class="m">' . esc_html($this->ago($this->gmt_to_timestamp($comment->comment_date_gmt))) . '</span></li>';
        }
        echo '</ul>';
    }

    private function is_premium_theme()
    {
        $theme  = wp_get_theme();
        $parent = $theme->parent();
        return 'Toocheke Premium' === $theme->get('Name') || ($parent && 'Toocheke Premium' === $parent->get('Name'));
    }

    private function notifications_active()
    {
        if (! $this->is_premium_theme() || ! get_option('toocheke_notifications_db_version')) {
            return false;
        }
        $defaults = ['post' => 1, 'comic' => 1, 'manga_chapter' => 1, 'series' => 0, 'manga_series' => 0, 'manga_volume' => 0];
        foreach ($defaults as $post_type => $default) {
            if (get_option('toocheke-notify-enable-' . $post_type, $default)) {
                return true;
            }
        }
        return false;
    }

    private function mask_email($email)
    {
        $parts = explode('@', (string) $email, 2);
        if (2 !== count($parts) || '' === $parts[0]) {
            return '***';
        }
        $first = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1);
        return $first . '***@' . $parts[1];
    }

    public function render_subscribers()
    {
        global $wpdb;

        $data = get_transient(self::CACHE_PREFIX . 'subscribers');
        if (false === $data) {
            $counts = [];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- result is cached in a transient.
            $count_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}toocheke_notify_subscribers WHERE status IN (%s, %s, %s) GROUP BY status",
                'confirmed',
                'pending',
                'unsubscribed'
            ));
            foreach ((array) $count_rows as $row) {
                $counts[$row->status] = (int) $row->total;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- result is cached in a transient.
            $recent = (array) $wpdb->get_results($wpdb->prepare("SELECT email, status, created_at FROM {$wpdb->prefix}toocheke_notify_subscribers ORDER BY created_at DESC LIMIT %d", self::LIST_LIMIT));
            $data   = ['counts' => $counts, 'recent' => $recent];
            set_transient(self::CACHE_PREFIX . 'subscribers', $data, 300);
        }

        $stats = [
            __('Confirmed', 'toocheke-companion')    => isset($data['counts']['confirmed']) ? $data['counts']['confirmed'] : 0,
            __('Pending', 'toocheke-companion')      => isset($data['counts']['pending']) ? $data['counts']['pending'] : 0,
            __('Unsubscribed', 'toocheke-companion') => isset($data['counts']['unsubscribed']) ? $data['counts']['unsubscribed'] : 0,
        ];

        echo '<ul class="tk-dw-stats">';
        foreach ($stats as $label => $total) {
            echo '<li><strong>' . esc_html(number_format_i18n($total)) . '</strong>' . esc_html($label) . '</li>';
        }
        echo '</ul>';

        if (empty($data['recent'])) {
            echo '<p>' . esc_html__('No subscribers yet.', 'toocheke-companion') . '</p>';
        } else {
            echo '<p><strong>' . esc_html__('Recent signups', 'toocheke-companion') . '</strong></p><ul class="tk-dw-list">';
            foreach ($data['recent'] as $row) {
                echo '<li><span>' . esc_html($this->mask_email($row->email)) . ' <span class="m">(' . esc_html($row->status) . ')</span></span>'
                    . '<span class="m">' . esc_html($this->ago($this->local_to_timestamp($row->created_at))) . '</span></li>';
            }
            echo '</ul>';
        }

        $this->print_links([__('Manage Subscribers', 'toocheke-companion') => admin_url('admin.php?page=toocheke-notify-subscribers')]);
    }

    private function patreon_active()
    {
        return $this->is_premium_theme() && class_exists('Patreon_Wordpress') && get_option('patreon-creator-id');
    }

    private function patreon_tiers()
    {
        $tiers   = [];
        $options = get_option('patreon-creator-tiers', false);
        if (is_array($options) && ! empty($options['included']) && is_array($options['included'])) {
            foreach ($options['included'] as $item) {
                if (! is_array($item) || ! isset($item['id'], $item['type']) || ! in_array($item['type'], ['tier', 'reward'], true) || (int) $item['id'] <= 0) {
                    continue;
                }
                $tiers[(string) $item['id']] = [
                    'title' => isset($item['attributes']['title']) ? (string) $item['attributes']['title'] : '',
                    'cents' => isset($item['attributes']['amount_cents']) ? (int) $item['attributes']['amount_cents'] : 0,
                ];
            }
        }
        return $tiers;
    }

    private function patreon_parse($raw, $creator_id, array $tiers)
    {
        $info = is_string($raw) && is_serialized($raw) ? unserialize($raw, ["allowed_classes" => false]) : $raw;
        if (! is_array($info) || empty($info['included']) || ! is_array($info['included'])) {
            return null;
        }

        $pledge = null;
        foreach ($info['included'] as $item) {
            if (is_array($item) && isset($item['type']) && 'pledge' === $item['type'] && (string) ($item['relationships']['creator']['data']['id'] ?? '') === (string) $creator_id) {
                $pledge = $item;
                break;
            }
        }
        if (! $pledge) {
            return null;
        }

        $attributes = isset($pledge['attributes']) && is_array($pledge['attributes']) ? $pledge['attributes'] : [];
        $cents      = (int) ($attributes['amount_cents'] ?? 0);
        $status     = isset($attributes['patron_status']) ? (string) $attributes['patron_status'] : '';
        $declined   = ! empty($attributes['declined_since']);
        if ($cents <= 0 || $declined || ('' !== $status && 'active_patron' !== $status)) {
            return null;
        }

        $cadence = (int) ($attributes['pledge_cadence'] ?? 1);
        if ($cadence > 1) {
            $cents = (int) round($cents / $cadence);
        }

        $label     = '';
        $best      = -1;
        $entitled  = $pledge['relationships']['currently_entitled_tiers']['data'] ?? [];
        foreach ((array) $entitled as $tier) {
            $id = isset($tier['id']) ? (string) $tier['id'] : '';
            if (isset($tiers[$id]) && $tiers[$id]['cents'] > $best) {
                $best  = $tiers[$id]['cents'];
                $label = '' !== $tiers[$id]['title'] ? $tiers[$id]['title'] : sprintf('$%s', number_format_i18n($tiers[$id]['cents'] / 100, 2));
            }
        }
        if ('' === $label) {
            $best  = $cents;
            $label = sprintf(
                /* translators: %s: monthly amount in dollars */
                __('Custom ($%s)', 'toocheke-companion'),
                number_format_i18n($cents / 100, 2)
            );
        }

        $since = ! empty($attributes['created_at']) ? strtotime((string) $attributes['created_at']) : 0;
        return ['label' => $label, 'rank' => $best, 'since' => $since ? (int) $since : 0];
    }

    private function patreon_stats()
    {
        return $this->remember('patreon', function () {
            global $wpdb;
            $creator_id = get_option('patreon-creator-id');
            $tiers      = $this->patreon_tiers();
            $total      = 0;
            $by_tier    = [];
            $recent     = [];
            $last_id    = 0;
            $seen       = 0;
            $max        = (int) apply_filters('toocheke_dashboard_patreon_max_users', self::PATREON_MAX);

            while ($seen < $max) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- result is cached in a transient by remember().
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT umeta_id, user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND umeta_id > %d ORDER BY umeta_id ASC LIMIT %d",
                    'patreon_latest_patron_info',
                    $last_id,
                    self::PATREON_BATCH
                ));
                if (empty($rows)) {
                    break;
                }
                foreach ($rows as $row) {
                    $last_id = (int) $row->umeta_id;
                    $seen++;
                    $patron = $this->patreon_parse($row->meta_value, $creator_id, $tiers);
                    if (! $patron) {
                        continue;
                    }
                    $total++;
                    $key = $patron['label'];
                    if (! isset($by_tier[$key])) {
                        $by_tier[$key] = ['label' => $patron['label'], 'rank' => $patron['rank'], 'count' => 0];
                    }
                    $by_tier[$key]['count']++;
                    $recent[] = ['user_id' => (int) $row->user_id, 'since' => $patron['since'], 'label' => $patron['label']];
                }
                unset($rows);
            }

            usort($by_tier, function ($a, $b) {
                return $b['rank'] <=> $a['rank'];
            });
            usort($recent, function ($a, $b) {
                return $b['since'] <=> $a['since'];
            });
            $recent = array_slice($recent, 0, self::LIST_LIMIT);
            foreach ($recent as &$item) {
                $user         = get_userdata($item['user_id']);
                $item['name'] = $user ? $user->display_name : '';
            }
            unset($item);

            return ['total' => $total, 'tiers' => array_values($by_tier), 'recent' => $recent];
        });
    }

    public function render_patreon()
    {
        $data = $this->patreon_stats();

        echo '<ul class="tk-dw-stats"><li><strong>' . esc_html(number_format_i18n($data['total'])) . '</strong>'
            . esc_html__('Paying patrons', 'toocheke-companion') . '</li></ul>';

        if (empty($data['tiers'])) {
            echo '<p>' . esc_html__('No paying patrons have logged in yet.', 'toocheke-companion') . '</p>';
        } else {
            echo '<table class="tk-dw-table"><thead><tr><th>' . esc_html__('Tier', 'toocheke-companion') . '</th><th class="n">'
                . esc_html__('Patrons', 'toocheke-companion') . '</th></tr></thead><tbody>';
            foreach ($data['tiers'] as $tier) {
                echo '<tr><td>' . esc_html($tier['label']) . '</td><td class="n">' . esc_html(number_format_i18n($tier['count'])) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        if (! empty($data['recent'])) {
            echo '<p><strong>' . esc_html__('Newest patrons', 'toocheke-companion') . '</strong></p><ul class="tk-dw-list">';
            foreach ($data['recent'] as $item) {
                echo '<li><span>' . esc_html('' !== $item['name'] ? $item['name'] : __('(unknown)', 'toocheke-companion'))
                    . ' <span class="m">(' . esc_html($item['label']) . ')</span></span><span class="m">' . esc_html($this->ago($item['since'])) . '</span></li>';
            }
            echo '</ul>';
        }

        echo '<p class="description">' . esc_html__('Based on patrons who have logged in with Patreon; figures update as patrons visit your site.', 'toocheke-companion') . '</p>';
        $this->print_links([__('Users', 'toocheke-companion') => admin_url('users.php')]);
    }

    public function render_comicscout()
    {
        $data = $this->remember('comicscout', function () {
            $ids = get_posts([
                'post_type'      => 'comic',
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            $with_thumb = 0;
            if ($ids) {
                update_meta_cache('post', $ids);
                foreach ($ids as $id) {
                    if (get_post_meta($id, 'comicscout_image_id', true)) {
                        $with_thumb++;
                    }
                }
            }
            return ['checked' => count($ids), 'with_thumb' => $with_thumb];
        });

        $dismiss = wp_nonce_url(admin_url('admin-post.php?action=toocheke_dismiss_comicscout'), 'toocheke_dismiss_comicscout');
        echo '<a class="tk-dw-dismiss" href="' . esc_url($dismiss) . '">' . esc_html__('Dismiss', 'toocheke-companion') . '</a>';
        echo '<p>' . esc_html__('ComicScout is a curated discovery platform that connects readers with independent webcomic creators. Each listing links straight back to your own site, and your content stays yours.', 'toocheke-companion') . '</p>';
        echo '<ul style="list-style:disc;margin:0 0 12px 18px">'
            . '<li>' . esc_html__('More traffic: readers click through to your site.', 'toocheke-companion') . '</li>'
            . '<li>' . esc_html__('More discoverability: reach readers looking for new comics.', 'toocheke-companion') . '</li>'
            . '<li>' . esc_html__('No extra work: new comics appear automatically after setup.', 'toocheke-companion') . '</li>'
            . '</ul>';
        echo '<p><strong>' . esc_html__('How to get listed', 'toocheke-companion') . '</strong></p><ol style="margin:0 0 12px 18px">'
            . '<li>' . esc_html__('Submit your comic website.', 'toocheke-companion') . '</li>'
            . '<li>' . esc_html__('Wait for approval.', 'toocheke-companion') . '</li>'
            . '<li>' . esc_html__('Keep Toocheke Companion active so your updates sync automatically.', 'toocheke-companion') . '</li>'
            . '</ol>';

        if (! empty($data['checked'])) {
            echo '<p>' . esc_html(sprintf(
                /* translators: 1: comics with a ComicScout thumbnail, 2: comics checked */
                __('Comics with a ComicScout thumbnail (1000 × 1500 px): %1$d of your latest %2$d.', 'toocheke-companion'),
                $data['with_thumb'],
                $data['checked']
            )) . '</p>';
        }

        echo '<p class="tk-dw-links"><a href="' . esc_url('https://www.thecomicscout.com/submit-comic/') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Submit your comic', 'toocheke-companion') . '</a>'
            . '<a href="' . esc_url('https://www.thecomicscout.com/about/') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Learn more', 'toocheke-companion') . '</a></p>';
    }

    public function dismiss_comicscout()
    {
        check_admin_referer('toocheke_dismiss_comicscout');
        if (current_user_can('edit_posts')) {
            update_user_meta(get_current_user_id(), self::DISMISS_META, 1);
        }
        wp_safe_redirect(admin_url('index.php'));
        exit;
    }
}
