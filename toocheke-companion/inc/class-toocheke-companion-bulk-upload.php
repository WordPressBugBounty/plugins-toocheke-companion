<?php
/**
 * Toocheke Companion — Bulk Upload.
 *
 * Toocheke > Bulk Upload: upload or pick many images and create
 * scheduled, backdated or draft comics from them in batches.
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (! defined('TOOCHEKE_BULK_UPLOAD_OPTION')) {
    define('TOOCHEKE_BULK_UPLOAD_OPTION', 'toocheke_bulk_upload_batches');
}

trait Toocheke_Companion_Bulk_Upload
{
    protected function toocheke_bulk_max_items()
    {
        return 500;
    }

    public function toocheke_bulk_register_hooks()
    {
        if (! is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'toocheke_bulk_enqueue_admin_assets']);

        foreach (['preview', 'upload', 'start', 'step', 'undo'] as $action) {
            add_action('wp_ajax_toocheke_bulk_' . $action, [$this, 'toocheke_bulk_ajax_' . $action]);
        }
    }

    public function toocheke_bulk_enqueue_admin_assets()
    {
        if (empty($_GET['page']) || 'toocheke-bulk-upload' !== sanitize_key(wp_unslash($_GET['page']))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
            return;
        }

        $css_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'css/toocheke-bulk-upload.css';
        $js_path  = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/toocheke-bulk-upload.js';

        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_style(
            'toocheke-bulk-upload',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'css/toocheke-bulk-upload.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : TOOCHEKE_COMPANION_VERSION
        );

        wp_enqueue_script(
            'toocheke-bulk-upload',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'js/toocheke-bulk-upload.js',
            ['jquery', 'jquery-ui-sortable', 'media-editor'],
            file_exists($js_path) ? filemtime($js_path) : TOOCHEKE_COMPANION_VERSION,
            true
        );

        wp_localize_script('toocheke-bulk-upload', 'toochekeBulkUpload', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('toocheke_bulk_upload'),
            'maxItems'   => $this->toocheke_bulk_max_items(),
            'maxBytes'   => (int) wp_max_upload_size(),
            'concurrent' => 3,
            'allowed'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
            'i18n'       => [
                /* translators: %s: file name */
                'tooLarge'      => __('%s is larger than the server upload limit.', 'toocheke-companion'),
                /* translators: %s: file name */
                'badType'       => __('%s is not a supported image type.', 'toocheke-companion'),
                /* translators: %d: maximum number of pages */
                'tooMany'       => __('You can add up to %d pages at a time.', 'toocheke-companion'),
                /* translators: %s: error message */
                'uploadFailed'  => __('Upload failed: %s', 'toocheke-companion'),
                'uploading'     => __('Uploading…', 'toocheke-companion'),
                'retry'         => __('Retry', 'toocheke-companion'),
                'remove'        => __('Remove', 'toocheke-companion'),
                'previewEmpty'  => __('Add pages and choose a schedule to see the preview.', 'toocheke-companion'),
                'duplicate'     => __('Already exists — will be skipped', 'toocheke-companion'),
                'draft'         => __('Draft', 'toocheke-companion'),
                /* translators: 1: comics created so far, 2: total comics */
                'creating'      => __('Creating comics… %1$d of %2$d', 'toocheke-companion'),
                /* translators: 1: comics created, 2: comics skipped */
                'finished'      => __('Done! %1$d comic(s) created, %2$d skipped.', 'toocheke-companion'),
                /* translators: %s: error message */
                'error'         => __('Something went wrong: %s', 'toocheke-companion'),
                /* translators: %d: number of comics */
                'confirmCreate' => __('Create %d comic(s) now?', 'toocheke-companion'),
                'confirmUndo'   => __('Move all comics from this batch to the trash?', 'toocheke-companion'),
                'undoing'       => __('Undoing…', 'toocheke-companion'),
                'undone'        => __('Batch undone.', 'toocheke-companion'),
                'noAnchor'      => __('No existing comics found — using the date you chose.', 'toocheke-companion'),
                /* translators: 1: title of the latest comic, 2: its date */
                'anchorAfter'   => __('Continuing after “%1$s” (%2$s).', 'toocheke-companion'),
                /* translators: 1: title of the earliest comic, 2: its date */
                'anchorBefore'  => __('Filling in before “%1$s” (%2$s).', 'toocheke-companion'),
                'colTitle'      => __('Title', 'toocheke-companion'),
                'colDate'       => __('Publish date', 'toocheke-companion'),
                'createSchedule' => __('Create Scheduled Comics', 'toocheke-companion'),
                'createBackdate' => __('Create Backdated Comics', 'toocheke-companion'),
                'createDraft'   => __('Create Draft Comics', 'toocheke-companion'),
                'viewComics'    => __('View comics', 'toocheke-companion'),
                'resume'        => __('Resume', 'toocheke-companion'),
                'firstDate'     => __('First publish date', 'toocheke-companion'),
                'lastDate'      => __('Last publish date', 'toocheke-companion'),
            ],
        ]);
    }

    private function toocheke_bulk_authorize()
    {
        check_ajax_referer('toocheke_bulk_upload', 'nonce');

        if (! current_user_can('edit_posts') || ! current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'toocheke-companion')], 403);
        }

        return isset($_POST['tbu_batch']) ? sanitize_key(wp_unslash($_POST['tbu_batch'])) : '';
    }

    private function toocheke_bulk_ids_from_csv($value)
    {
        $ids = array_map('absint', explode(',', $value));
        return array_values(array_unique(array_filter($ids)));
    }

    private function toocheke_bulk_request()
    {
        check_ajax_referer('toocheke_bulk_upload', 'nonce');

        if (! current_user_can('edit_posts') || ! current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'toocheke-companion')], 403);
        }

        $text = static function ($key) {
            return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by check_ajax_referer() above.
        };

        $weekdays = [];
        foreach (explode(',', $text('tbu_weekdays')) as $day) {
            if ('' !== $day && ctype_digit($day) && (int) $day <= 6) {
                $weekdays[] = (int) $day;
            }
        }

        $mode = $text('tbu_mode');
        $dir  = $text('tbu_direction');

        return [
            'ids'            => array_slice($this->toocheke_bulk_ids_from_csv($text('tbu_ids')), 0, $this->toocheke_bulk_max_items()),
            'uploaded'       => $this->toocheke_bulk_ids_from_csv($text('tbu_uploaded')),
            'mode'           => in_array($mode, ['schedule', 'backdate', 'draft'], true) ? $mode : 'schedule',
            'direction'      => 'backward' === $dir ? 'backward' : 'forward',
            'date'           => $text('tbu_date'),
            'time'           => $text('tbu_time'),
            'weekdays'       => array_values(array_unique($weekdays)),
            'per_day'        => max(1, min(10, absint($text('tbu_per_day')))),
            'continue'       => '1' === $text('tbu_continue'),
            'pattern'        => $text('tbu_pattern'),
            'first'          => max(0, absint($text('tbu_first'))),
            'series'         => absint($text('tbu_series')),
            'chapters'       => $this->toocheke_bulk_ids_from_csv($text('tbu_chapters')),
            'collections'    => $this->toocheke_bulk_ids_from_csv($text('tbu_collections')),
            'new_chapter'    => $text('tbu_new_chapter'),
            'new_collection' => $text('tbu_new_collection'),
        ];
    }

    private function toocheke_bulk_valid_image_ids(array $ids)
    {
        $valid = [];
        foreach ($ids as $id) {
            if ('attachment' !== get_post_type($id) || 0 !== strpos((string) get_post_mime_type($id), 'image/')) {
                continue;
            }
            if (! current_user_can('edit_post', $id)) {
                continue;
            }
            $valid[] = $id;
        }
        return $valid;
    }

    private function toocheke_bulk_title($pattern, $number)
    {
        if ('' === $pattern) {
            $pattern = __('Page {n}', 'toocheke-companion');
        }
        if (false === strpos($pattern, '{n}')) {
            $pattern .= ' {n}';
        }
        return str_replace('{n}', (string) $number, $pattern);
    }

    private function toocheke_bulk_existing_titles(array $titles, $series)
    {
        global $wpdb;

        if (! $titles) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($titles), '%s'));
        $args         = array_merge([$series], $titles);

        $found = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'comic' AND post_parent = %d AND post_status NOT IN ('trash','auto-draft') AND post_title IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $args
            )
        );

        return array_fill_keys($found, true);
    }

    private function toocheke_bulk_edge_comic($series, $order)
    {
        $args = [
            'post_type'      => 'comic',
            'post_status'    => ['publish', 'future', 'private'],
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => $order,
            'no_found_rows'  => true,
        ];

        if ($series) {
            $args['post_parent'] = $series;
        }

        $posts = get_posts($args);

        return $posts ? $posts[0] : null;
    }

    private function toocheke_bulk_slots(array $p, $count)
    {
        if (! $p['weekdays']) {
            return new WP_Error('weekdays', __('Choose at least one day of the week.', 'toocheke-companion'));
        }
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $p['time'])) {
            return new WP_Error('time', __('Choose a valid time.', 'toocheke-companion'));
        }

        $tz       = wp_timezone();
        $now      = time();
        $backward = ('backdate' === $p['mode'] && 'backward' === $p['direction']);
        $start    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['date']) ? DateTimeImmutable::createFromFormat('!Y-m-d', $p['date'], $tz) : false;
        $anchor   = null;

        if ($p['continue']) {
            $edge = $this->toocheke_bulk_edge_comic($p['series'], $backward ? 'ASC' : 'DESC');
            if ($edge) {
                $anchor = $edge;
                $day    = (new DateTimeImmutable($edge->post_date, $tz))->setTime(0, 0);
                $start  = $backward ? $day->modify('-1 day') : $day->modify('+1 day');
            }
        }

        if (! $start) {
            return new WP_Error('date', __('Choose a valid date.', 'toocheke-companion'));
        }

        $hour   = (int) substr($p['time'], 0, 2);
        $minute = (int) substr($p['time'], 3, 2);
        $slots  = [];
        $day    = $start;
        $guard  = 0;

        while (count($slots) < $count && $guard++ < 9000) {
            if (in_array((int) $day->format('w'), $p['weekdays'], true)) {
                $day_slots = [];
                for ($k = 0; $k < $p['per_day'] && count($slots) + count($day_slots) < $count; $k++) {
                    $dt = $day->setTime($hour, $minute + $k);
                    if ('schedule' === $p['mode'] && $dt->getTimestamp() <= $now) {
                        continue;
                    }
                    if ('backdate' === $p['mode'] && $dt->getTimestamp() > $now) {
                        return new WP_Error('future', __('Backdated comics must have dates in the past. Choose “ending on” a past date or add fewer pages.', 'toocheke-companion'));
                    }
                    $day_slots[] = $dt->format('Y-m-d H:i:s');
                }
                if ($backward) {
                    $day_slots = array_reverse($day_slots);
                }
                $slots = array_merge($slots, $day_slots);
            }
            $day = $backward ? $day->modify('-1 day') : $day->modify('+1 day');
        }

        if (count($slots) < $count) {
            return new WP_Error('slots', __('Could not find enough dates for these pages.', 'toocheke-companion'));
        }

        if ($backward) {
            $slots = array_reverse($slots);
        }

        return ['slots' => $slots, 'anchor' => $anchor];
    }

    private function toocheke_bulk_plan(array $p)
    {
        $ids = $this->toocheke_bulk_valid_image_ids($p['ids']);
        if (! $ids) {
            return new WP_Error('no_images', __('Add at least one image.', 'toocheke-companion'));
        }

        if ($p['series'] && 'series' !== get_post_type($p['series'])) {
            return new WP_Error('series', __('Choose a valid series.', 'toocheke-companion'));
        }

        $titles = [];
        foreach ($ids as $i => $id) {
            $titles[$i] = $this->toocheke_bulk_title($p['pattern'], $p['first'] + $i);
        }

        $dups   = $this->toocheke_bulk_existing_titles(array_values(array_unique($titles)), $p['series']);
        $rows   = [];
        $active = 0;

        foreach ($ids as $i => $id) {
            $dup    = isset($dups[$titles[$i]]);
            $rows[] = ['id' => $id, 'title' => $titles[$i], 'page' => $p['first'] + $i, 'dup' => $dup, 'slot' => ''];
            if (! $dup) {
                $active++;
            }
        }

        $anchor = null;

        if ($active && 'draft' !== $p['mode']) {
            $result = $this->toocheke_bulk_slots($p, $active);
            if (is_wp_error($result)) {
                return $result;
            }
            $anchor = $result['anchor'];
            $n      = 0;
            foreach ($rows as &$row) {
                if (! $row['dup']) {
                    $row['slot'] = $result['slots'][$n++];
                }
            }
            unset($row);
        }

        return ['rows' => $rows, 'anchor' => $anchor, 'active' => $active];
    }

    public function toocheke_bulk_ajax_preview()
    {
        $p    = $this->toocheke_bulk_request();
        $plan = $this->toocheke_bulk_plan($p);

        if (is_wp_error($plan)) {
            wp_send_json_error(['message' => $plan->get_error_message()]);
        }

        $tz   = wp_timezone();
        $rows = [];
        foreach ($plan['rows'] as $row) {
            $label = '';
            if ($row['slot']) {
                $dt    = new DateTimeImmutable($row['slot'], $tz);
                $label = wp_date('D, M j, Y · g:i a', $dt->getTimestamp());
            }
            $rows[] = ['id' => $row['id'], 'title' => $row['title'], 'dup' => $row['dup'], 'date' => $label];
        }

        $anchor = null;
        if ($plan['anchor']) {
            $anchor = [
                'title' => get_the_title($plan['anchor']),
                'date'  => wp_date(get_option('date_format'), strtotime($plan['anchor']->post_date_gmt . ' UTC')),
            ];
        }

        wp_send_json_success([
            'rows'   => $rows,
            'anchor' => $anchor,
            'active' => $plan['active'],
            'tz'     => wp_timezone_string(),
        ]);
    }

    public function toocheke_bulk_ajax_upload()
    {
        $this->toocheke_bulk_authorize();

        if (empty($_FILES['tbu_file']) || ! is_array($_FILES['tbu_file'])) {
            wp_send_json_error(['message' => __('No file received.', 'toocheke-companion')]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'avif'         => 'image/avif',
        ];

        $id = media_handle_upload('tbu_file', 0, [], ['test_form' => false, 'mimes' => $mimes]);

        if (is_wp_error($id)) {
            wp_send_json_error(['message' => $id->get_error_message()]);
        }

        update_post_meta($id, '_toocheke_bulk_uploaded_by', get_current_user_id());

        $thumb = wp_get_attachment_image_url($id, 'thumbnail');

        wp_send_json_success([
            'id'    => $id,
            'thumb' => $thumb ? $thumb : wp_get_attachment_url($id),
            'name'  => wp_basename((string) get_attached_file($id)),
        ]);
    }

    private function toocheke_bulk_get_batches()
    {
        $batches = get_option(TOOCHEKE_BULK_UPLOAD_OPTION, []);
        return is_array($batches) ? $batches : [];
    }

    private function toocheke_bulk_save_batches(array $batches)
    {
        if (count($batches) > 15) {
            $batches = array_slice($batches, -15, null, true);
        }
        update_option(TOOCHEKE_BULK_UPLOAD_OPTION, $batches, false);
    }

    private function toocheke_bulk_get_batch($batch_id, array $batches)
    {
        if (! $batch_id || ! isset($batches[$batch_id])) {
            return null;
        }
        $batch = $batches[$batch_id];
        if ((int) $batch['user'] !== get_current_user_id() && ! current_user_can('manage_options')) {
            return null;
        }
        return $batch;
    }

    private function toocheke_bulk_resolve_terms(array $p)
    {
        $out = ['chapters' => [], 'collections' => []];

        foreach (['chapters' => 'new_chapter', 'collections' => 'new_collection'] as $taxonomy => $new_key) {
            $tax = get_taxonomy($taxonomy);
            if (! $tax) {
                continue;
            }

            foreach ($p[$taxonomy] as $term_id) {
                if (term_exists($term_id, $taxonomy)) {
                    $out[$taxonomy][] = $term_id;
                }
            }

            if ('' !== $p[$new_key] && current_user_can($tax->cap->edit_terms)) {
                $created = wp_insert_term($p[$new_key], $taxonomy);
                if (is_wp_error($created)) {
                    $existing = $created->get_error_data('term_exists');
                    if ($existing) {
                        $out[$taxonomy][] = (int) $existing;
                    }
                } else {
                    $out[$taxonomy][] = (int) $created['term_id'];
                }
            }

            $out[$taxonomy] = array_values(array_unique($out[$taxonomy]));
        }

        return $out;
    }

    public function toocheke_bulk_ajax_start()
    {
        $p    = $this->toocheke_bulk_request();
        $plan = $this->toocheke_bulk_plan($p);

        if (is_wp_error($plan)) {
            wp_send_json_error(['message' => $plan->get_error_message()]);
        }

        if (! $plan['active']) {
            wp_send_json_error(['message' => __('Every page already exists, so there is nothing to create.', 'toocheke-companion')]);
        }

        $terms    = $this->toocheke_bulk_resolve_terms($p);
        $queue    = [];
        $skipped  = 0;
        $uploaded = [];

        foreach ($plan['rows'] as $row) {
            if ($row['dup']) {
                $skipped++;
                continue;
            }
            $queue[] = ['id' => $row['id'], 'title' => $row['title'], 'slot' => $row['slot']];
            if (in_array($row['id'], $p['uploaded'], true) && (int) get_post_meta($row['id'], '_toocheke_bulk_uploaded_by', true) === get_current_user_id()) {
                $uploaded[] = $row['id'];
            }
        }

        $batch_id = strtolower(wp_generate_password(12, false));
        $batches  = $this->toocheke_bulk_get_batches();

        $batches[$batch_id] = [
            'id'          => $batch_id,
            'user'        => get_current_user_id(),
            'created'     => time(),
            'mode'        => $p['mode'],
            'series'      => $p['series'],
            'chapters'    => $terms['chapters'],
            'collections' => $terms['collections'],
            'queue'       => $queue,
            'cursor'      => 0,
            'total'       => count($queue),
            'skipped'     => $skipped,
            'failed'      => 0,
            'post_ids'    => [],
            'uploaded'    => $uploaded,
            'status'      => 'running',
        ];

        $this->toocheke_bulk_save_batches($batches);

        wp_send_json_success(['batch' => $batch_id, 'total' => count($queue), 'skipped' => $skipped]);
    }

    private function toocheke_bulk_create_comic(array $batch, array $item)
    {
        $attachment_id = (int) $item['id'];

        if (! wp_attachment_is_image($attachment_id)) {
            return new WP_Error('image', __('Not a valid image.', 'toocheke-companion'));
        }

        $url = wp_get_attachment_url($attachment_id);
        if (! $url) {
            return new WP_Error('url', __('Image file is missing.', 'toocheke-companion'));
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        $dims = '';
        if (! empty($meta['width']) && ! empty($meta['height'])) {
            $dims = ' width="' . (int) $meta['width'] . '" height="' . (int) $meta['height'] . '"';
        }

        $title   = $item['title'];
        $content = sprintf(
            '<img src="%1$s" alt="%2$s"%3$s class="alignnone size-full wp-image-%4$d" />',
            esc_url($url),
            esc_attr($title),
            $dims,
            $attachment_id
        );

        $postarr = [
            'post_type'    => 'comic',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content,
            'post_parent'  => (int) $batch['series'],
            'post_author'  => (int) $batch['user'],
            'meta_input'   => ['_toocheke_bulk_batch' => $batch['id']],
        ];

        if ($item['slot']) {
            $postarr['post_date']     = $item['slot'];
            $postarr['post_date_gmt'] = get_gmt_from_date($item['slot']);
        }

        $post_id = wp_insert_post($postarr, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if ('' === (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);
        }

        set_post_thumbnail($post_id, $attachment_id);

        if (! wp_get_post_parent_id($attachment_id)) {
            wp_update_post(['ID' => $attachment_id, 'post_parent' => $post_id]);
        }

        if ($batch['chapters']) {
            wp_set_object_terms($post_id, $batch['chapters'], 'chapters');
        }
        if ($batch['collections']) {
            wp_set_object_terms($post_id, $batch['collections'], 'collections');
        }

        if ('draft' !== $batch['mode']) {
            $status = ('schedule' === $batch['mode']) ? 'future' : 'publish';
            $update = wp_update_post([
                'ID'            => $post_id,
                'post_status'   => $status,
                'post_date'     => $item['slot'],
                'post_date_gmt' => get_gmt_from_date($item['slot']),
                'edit_date'     => true,
            ], true);

            if (is_wp_error($update)) {
                return $update;
            }
        }

        return $post_id;
    }

    public function toocheke_bulk_ajax_step()
    {
        $batch_id = $this->toocheke_bulk_authorize();
        $batches  = $this->toocheke_bulk_get_batches();
        $batch    = $this->toocheke_bulk_get_batch($batch_id, $batches);

        if (! $batch || 'running' !== $batch['status']) {
            wp_send_json_error(['message' => __('This batch is not available.', 'toocheke-companion')]);
        }

        $deadline = microtime(true) + 4;
        $created  = [];
        $errors   = [];
        $count    = count($batch['queue']);

        wp_defer_term_counting(true);
        add_filter('wp_revisions_to_keep', '__return_zero');
        remove_action('transition_post_status', [$this, 'toocheke_notifications_maybe_queue_on_publish'], 10);

        while ($batch['cursor'] < $count && microtime(true) < $deadline) {
            $item = $batch['queue'][$batch['cursor']];
            $batch['cursor']++;

            $post_id = $this->toocheke_bulk_create_comic($batch, $item);

            if (is_wp_error($post_id)) {
                $batch['failed']++;
                $errors[] = $item['title'] . ': ' . $post_id->get_error_message();
                continue;
            }

            $batch['post_ids'][] = $post_id;
            $created[]           = [
                'id'     => $post_id,
                'title'  => $item['title'],
                'status' => get_post_status($post_id),
                'date'   => $item['slot'] ? wp_date('D, M j, Y · g:i a', (new DateTimeImmutable($item['slot'], wp_timezone()))->getTimestamp()) : '',
                'edit'   => get_edit_post_link($post_id, 'raw'),
            ];
        }

        wp_defer_term_counting(false);
        remove_filter('wp_revisions_to_keep', '__return_zero');

        $done = $batch['cursor'] >= $count;

        if ($done) {
            $batch['status'] = 'done';
            $batch['queue']  = [];
        }

        $batches[$batch_id] = $batch;
        $this->toocheke_bulk_save_batches($batches);

        wp_send_json_success([
            'done'    => $done,
            'cursor'  => $batch['cursor'],
            'total'   => $batch['total'],
            'failed'  => $batch['failed'],
            'skipped' => $batch['skipped'],
            'created' => $created,
            'errors'  => $errors,
            'row'     => $done ? $this->toocheke_bulk_batch_row($batch) : '',
        ]);
    }

    public function toocheke_bulk_ajax_undo()
    {
        $batch_id = $this->toocheke_bulk_authorize();
        $batches  = $this->toocheke_bulk_get_batches();
        $batch    = $this->toocheke_bulk_get_batch($batch_id, $batches);

        if (! $batch || 'undone' === $batch['status']) {
            wp_send_json_error(['message' => __('This batch is not available.', 'toocheke-companion')]);
        }

        $delete_images = isset($_POST['tbu_delete_images']) && '1' === sanitize_text_field(wp_unslash($_POST['tbu_delete_images'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in toocheke_bulk_authorize().
        $deadline      = microtime(true) + 4;

        wp_defer_term_counting(true);

        while ($batch['post_ids'] && microtime(true) < $deadline) {
            $post_id = (int) array_shift($batch['post_ids']);

            if ($batch['id'] !== get_post_meta($post_id, '_toocheke_bulk_batch', true) || ! current_user_can('delete_post', $post_id)) {
                continue;
            }

            if ($delete_images) {
                $protected = get_post_meta($post_id, '_toocheke_protected_images', true);
                if (is_array($protected)) {
                    foreach ($protected as $entry) {
                        if (! empty($entry['private_path']) && file_exists($entry['private_path'])) {
                            wp_delete_file($entry['private_path']);
                        }
                    }
                }
            }

            wp_trash_post($post_id);
        }

        if (! $batch['post_ids'] && $delete_images) {
            while ($batch['uploaded'] && microtime(true) < $deadline) {
                $attachment_id = (int) array_shift($batch['uploaded']);
                if ('attachment' === get_post_type($attachment_id)
                    && (int) get_post_meta($attachment_id, '_toocheke_bulk_uploaded_by', true) === (int) $batch['user']
                    && current_user_can('delete_post', $attachment_id)) {
                    wp_delete_attachment($attachment_id, true);
                }
            }
        }

        wp_defer_term_counting(false);

        $done = ! $batch['post_ids'] && (! $delete_images || ! $batch['uploaded']);

        if ($done) {
            $batch['status']   = 'undone';
            $batch['uploaded'] = [];
        }

        $batches[$batch_id] = $batch;
        $this->toocheke_bulk_save_batches($batches);

        wp_send_json_success([
            'done'      => $done,
            'remaining' => count($batch['post_ids']),
            'row'       => $done ? $this->toocheke_bulk_batch_row($batch) : '',
        ]);
    }

    private function toocheke_bulk_batch_row(array $batch)
    {
        $modes = [
            'schedule' => __('Scheduled', 'toocheke-companion'),
            'backdate' => __('Backdated', 'toocheke-companion'),
            'draft'    => __('Drafts', 'toocheke-companion'),
        ];

        $created = max(0, (int) $batch['total'] - (int) $batch['failed']);
        $running = ('running' === $batch['status']);

        ob_start();
        ?>
        <li class="toocheke-bulk-batch" data-batch="<?php echo esc_attr($batch['id']); ?>" data-status="<?php echo esc_attr($batch['status']); ?>" data-total="<?php echo esc_attr((string) $batch['total']); ?>" data-cursor="<?php echo esc_attr((string) $batch['cursor']); ?>">
            <div class="toocheke-bulk-batch-info">
                <strong><?php echo esc_html($modes[$batch['mode']] ?? $batch['mode']); ?></strong>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: number of comics, 2: date the batch was created */
                    _n('%1$d comic · %2$s', '%1$d comics · %2$s', $created, 'toocheke-companion'),
                    $created,
                    wp_date(get_option('date_format') . ' ' . get_option('time_format'), $batch['created'])
                ));
                ?>
                <?php if ('undone' === $batch['status']) : ?>
                    <span class="toocheke-bulk-badge"><?php esc_html_e('Undone', 'toocheke-companion'); ?></span>
                <?php elseif ($running) : ?>
                    <span class="toocheke-bulk-badge toocheke-bulk-badge--warn"><?php esc_html_e('Interrupted', 'toocheke-companion'); ?></span>
                <?php endif; ?>
            </div>
            <div class="toocheke-bulk-batch-actions">
                <?php if ($running) : ?>
                    <button type="button" class="button toocheke-bulk-resume"><?php esc_html_e('Resume', 'toocheke-companion'); ?></button>
                <?php endif; ?>
                <?php if ('undone' !== $batch['status'] && ($batch['post_ids'] || $batch['uploaded'])) : ?>
                    <?php if ($batch['uploaded']) : ?>
                        <label><input type="checkbox" class="toocheke-bulk-delete-images"> <?php esc_html_e('Also delete uploaded images', 'toocheke-companion'); ?></label>
                    <?php endif; ?>
                    <button type="button" class="button toocheke-bulk-undo"><?php esc_html_e('Undo batch', 'toocheke-companion'); ?></button>
                <?php endif; ?>
            </div>
        </li>
        <?php
        return ob_get_clean();
    }

    public function toocheke_bulk_render_page()
    {
        if (! current_user_can('upload_files')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'toocheke-companion'));
        }

        $series = get_posts([
            'post_type'      => 'series',
            'post_status'    => ['publish', 'draft', 'private', 'future'],
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        $chapters    = get_terms(['taxonomy' => 'chapters', 'hide_empty' => false]);
        $collections = get_terms(['taxonomy' => 'collections', 'hide_empty' => false]);
        $chapters    = is_wp_error($chapters) ? [] : $chapters;
        $collections = is_wp_error($collections) ? [] : $collections;

        $days = [
            1 => __('Mon', 'toocheke-companion'),
            2 => __('Tue', 'toocheke-companion'),
            3 => __('Wed', 'toocheke-companion'),
            4 => __('Thu', 'toocheke-companion'),
            5 => __('Fri', 'toocheke-companion'),
            6 => __('Sat', 'toocheke-companion'),
            0 => __('Sun', 'toocheke-companion'),
        ];

        $batches = array_reverse($this->toocheke_bulk_get_batches());
        ?>
        <div class="wrap toocheke-bulk-wrap">
            <h2><?php esc_html_e('Bulk Upload', 'toocheke-companion'); ?></h2>
            <p class="toocheke-bulk-intro"><?php
                /* translators: %s: name of the create button */
                echo wp_kses(sprintf(__('Upload or select images from your Media Library, then create multiple posts at once — scheduled for later, backdated, or saved as drafts. Nothing will be created until you click %s.', 'toocheke-companion'), '<b id="toocheke-bulk-create-label">' . esc_html__('Create Scheduled Comics', 'toocheke-companion') . '</b>'), ['b' => ['id' => true]]);
            ?></p>

            <div class="toocheke-bulk-card">
                <h3><span class="toocheke-bulk-step">1</span> <?php esc_html_e('Add pages', 'toocheke-companion'); ?></h3>
                <div id="toocheke-bulk-drop" class="toocheke-bulk-drop" tabindex="0">
                    <p><strong><?php esc_html_e('Drag and drop images here', 'toocheke-companion'); ?></strong></p>
                    <p>
                        <button type="button" class="button button-primary" id="toocheke-bulk-browse"><?php esc_html_e('Upload files', 'toocheke-companion'); ?></button>
                        <button type="button" class="button" id="toocheke-bulk-library"><?php esc_html_e('Choose from Media Library', 'toocheke-companion'); ?></button>
                    </p>
                    <p class="description"><?php esc_html_e('JPG, PNG, GIF, WebP or AVIF. Files are ordered by name (page-2 before page-10); drag thumbnails to reorder.', 'toocheke-companion'); ?></p>
                    <input type="file" id="toocheke-bulk-file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" multiple hidden>
                </div>
                <div class="toocheke-bulk-toolbar" id="toocheke-bulk-toolbar" hidden>
                    <span id="toocheke-bulk-count"></span>
                    <button type="button" class="button-link" id="toocheke-bulk-sort"><?php esc_html_e('Sort A–Z', 'toocheke-companion'); ?></button>
                    <button type="button" class="button-link" id="toocheke-bulk-reverse"><?php esc_html_e('Reverse', 'toocheke-companion'); ?></button>
                    <button type="button" class="button-link toocheke-bulk-danger" id="toocheke-bulk-clear"><?php esc_html_e('Clear all', 'toocheke-companion'); ?></button>
                </div>
                <ul id="toocheke-bulk-grid" class="toocheke-bulk-grid"></ul>
                <div id="toocheke-bulk-upload-errors" class="toocheke-bulk-errors"></div>
            </div>

            <div class="toocheke-bulk-card">
                <h3><span class="toocheke-bulk-step">2</span> <?php esc_html_e('Choose the dates', 'toocheke-companion'); ?></h3>
                <div class="toocheke-bulk-modes">
                    <label><input type="radio" name="tbu_mode" value="schedule" checked> <?php esc_html_e('Schedule for future dates', 'toocheke-companion'); ?></label>
                    <label><input type="radio" name="tbu_mode" value="backdate"> <?php esc_html_e('Publish now with a past date', 'toocheke-companion'); ?></label>
                    <label><input type="radio" name="tbu_mode" value="draft"> <?php esc_html_e('Save as drafts (no dates)', 'toocheke-companion'); ?></label>
                </div>
                <div id="toocheke-bulk-dates">
                    <div class="toocheke-bulk-row">
                        <label class="toocheke-bulk-field" id="toocheke-bulk-direction-wrap" hidden>
                            <span><?php esc_html_e('Dates run', 'toocheke-companion'); ?></span>
                            <select id="tbu_direction">
                                <option value="forward"><?php esc_html_e('starting on', 'toocheke-companion'); ?></option>
                                <option value="backward"><?php esc_html_e('ending on', 'toocheke-companion'); ?></option>
                            </select>
                        </label>
                        <label class="toocheke-bulk-field">
                            <span id="toocheke-bulk-date-label"><?php esc_html_e('First publish date', 'toocheke-companion'); ?></span>
                            <input type="date" id="tbu_date" value="<?php echo esc_attr(wp_date('Y-m-d', time() + DAY_IN_SECONDS)); ?>">
                        </label>
                        <label class="toocheke-bulk-field">
                            <span><?php esc_html_e('Time', 'toocheke-companion'); ?></span>
                            <input type="time" id="tbu_time" value="12:00">
                        </label>
                        <label class="toocheke-bulk-field toocheke-bulk-field--small">
                            <span><?php esc_html_e('Pages per day', 'toocheke-companion'); ?></span>
                            <input type="number" id="tbu_per_day" min="1" max="10" value="1">
                        </label>
                    </div>
                    <p class="description" id="toocheke-bulk-direction-help" hidden><?php esc_html_e('Starting on: the first page gets this date and the rest follow. Ending on: the last page gets this date and earlier pages count back.', 'toocheke-companion'); ?></p>
                    <div class="toocheke-bulk-field">
                        <span><?php esc_html_e('Post on these days', 'toocheke-companion'); ?></span>
                        <div class="toocheke-bulk-days">
                            <?php foreach ($days as $value => $label) : ?>
                                <label><input type="checkbox" class="tbu-weekday" value="<?php echo esc_attr((string) $value); ?>"> <?php echo esc_html($label); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="toocheke-bulk-continue">
                        <label><input type="checkbox" id="tbu_continue"> <?php esc_html_e('Continue after my last comic', 'toocheke-companion'); ?></label>
                        <span class="description" id="toocheke-bulk-continue-help"><?php esc_html_e('Starts at the next available day after the latest comic in the selected series (or on the whole site if no series is chosen).', 'toocheke-companion'); ?></span>
                    </p>
                    <p class="description" id="toocheke-bulk-anchor"></p>
                    <p class="description"><?php
                        /* translators: %s: site timezone */
                        echo esc_html(sprintf(__('Times use your site timezone: %s.', 'toocheke-companion'), wp_timezone_string()));
                    ?></p>
                </div>
            </div>

            <div class="toocheke-bulk-card">
                <h3><span class="toocheke-bulk-step">3</span> <?php esc_html_e('Comic details', 'toocheke-companion'); ?></h3>
                <div class="toocheke-bulk-row toocheke-bulk-row--top">
                    <label class="toocheke-bulk-field toocheke-bulk-field--wide">
                        <span><?php esc_html_e('Title pattern', 'toocheke-companion'); ?></span>
                        <input type="text" id="tbu_pattern" value="<?php echo esc_attr(__('Page {n}', 'toocheke-companion')); ?>">
                        <small class="description"><?php esc_html_e('{n} becomes the page number, e.g. “Issue 2 – Page {n}”.', 'toocheke-companion'); ?></small>
                    </label>
                    <label class="toocheke-bulk-field toocheke-bulk-field--small">
                        <span><?php esc_html_e('First page number', 'toocheke-companion'); ?></span>
                        <input type="number" id="tbu_first" min="0" value="1">
                    </label>
                </div>
                <div class="toocheke-bulk-row">
                    <label class="toocheke-bulk-field">
                        <span><?php esc_html_e('Series', 'toocheke-companion'); ?></span>
                        <select id="tbu_series">
                            <option value="0"><?php esc_html_e('(No Series)', 'toocheke-companion'); ?></option>
                            <?php foreach ($series as $item) : ?>
                                <option value="<?php echo esc_attr((string) $item->ID); ?>"><?php echo esc_html(get_the_title($item)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="toocheke-bulk-row toocheke-bulk-row--top">
                    <div class="toocheke-bulk-field">
                        <span><?php esc_html_e('Chapters', 'toocheke-companion'); ?></span>
                        <div class="toocheke-bulk-checklist" id="tbu_chapters">
                            <?php foreach ($chapters as $term) : ?>
                                <label><input type="checkbox" value="<?php echo esc_attr((string) $term->term_id); ?>"> <?php echo esc_html($term->name); ?></label>
                            <?php endforeach; ?>
                            <?php if (! $chapters) : ?><em><?php esc_html_e('No chapters yet.', 'toocheke-companion'); ?></em><?php endif; ?>
                        </div>
                        <input type="text" id="tbu_new_chapter" placeholder="<?php esc_attr_e('Or create a new chapter…', 'toocheke-companion'); ?>">
                    </div>
                    <div class="toocheke-bulk-field">
                        <span><?php esc_html_e('Collections', 'toocheke-companion'); ?></span>
                        <div class="toocheke-bulk-checklist" id="tbu_collections">
                            <?php foreach ($collections as $term) : ?>
                                <label><input type="checkbox" value="<?php echo esc_attr((string) $term->term_id); ?>"> <?php echo esc_html($term->name); ?></label>
                            <?php endforeach; ?>
                            <?php if (! $collections) : ?><em><?php esc_html_e('No collections yet.', 'toocheke-companion'); ?></em><?php endif; ?>
                        </div>
                        <input type="text" id="tbu_new_collection" placeholder="<?php esc_attr_e('Or create a new collection…', 'toocheke-companion'); ?>">
                    </div>
                </div>
                <p class="description"><?php esc_html_e('Each page’s image is placed in the comic content and set as the featured image.', 'toocheke-companion'); ?></p>
            </div>

            <div class="toocheke-bulk-card">
                <h3><span class="toocheke-bulk-step">4</span> <?php esc_html_e('Review and create', 'toocheke-companion'); ?></h3>
                <div id="toocheke-bulk-preview" class="toocheke-bulk-preview"><em><?php esc_html_e('Add pages and choose a schedule to see the preview.', 'toocheke-companion'); ?></em></div>
                <p>
                    <button type="button" class="button button-primary button-hero" id="toocheke-bulk-create" disabled><?php esc_html_e('Create Comics', 'toocheke-companion'); ?></button>
                </p>
                <div class="toocheke-bulk-progress" id="toocheke-bulk-progress" hidden>
                    <div class="toocheke-bulk-progressbar"><div class="toocheke-bulk-progressbar-fill" id="toocheke-bulk-fill"></div></div>
                    <p id="toocheke-bulk-status"></p>
                    <div class="toocheke-bulk-log" id="toocheke-bulk-log"></div>
                </div>
            </div>

            <div class="toocheke-bulk-card">
                <h3><?php esc_html_e('Recent batches', 'toocheke-companion'); ?></h3>
                <ul class="toocheke-bulk-batches" id="toocheke-bulk-batches">
                    <?php
                    $shown = 0;
                    foreach ($batches as $batch) {
                        if ((int) $batch['user'] !== get_current_user_id() && ! current_user_can('manage_options')) {
                            continue;
                        }
                        $shown++;
                        echo $this->toocheke_bulk_batch_row($batch); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in toocheke_bulk_batch_row().
                    }
                    ?>
                </ul>
                <p class="description" id="toocheke-bulk-no-batches" <?php echo $shown ? 'hidden' : ''; ?>><?php esc_html_e('No batches yet.', 'toocheke-companion'); ?></p>
            </div>
        </div>
        <?php
    }
}
