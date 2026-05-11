<?php
/**
 * Toocheke Future Comic Image Protection
 *
 * Intercepts image uploads attached to future/scheduled comic posts,
 * moves them to private storage outside direct web access,
 * and restores them on publish.
 *
 * Triggered only when option 'toocheke-future-post-image-protection' is enabled.
 *
 * Compatibility note: works correctly whether or not Toocheke_Image_Optimization
 * is active. The upload hook uses priority 999 on add_attachment and resolves the
 * actual file on disk directly, avoiding any dependency on _wp_attached_file meta
 * being corrected before this hook fires.
 */

class Toocheke_Future_Comic_Image_Protection
{

    /**
     * Private storage directory — one level above WP_CONTENT_DIR keeps it
     * off the public web on virtually every shared/managed host.
     * Falls back to wp-content/toocheke-private if the parent isn't writable.
     */
    const PRIVATE_DIR_NAME = 'toocheke-private';

    private static $instance = null;

    /**
     * Flag to prevent cleanup_private_file from running during our own protect step.
     */
    private bool $protecting = false;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        if (! $this->is_protection_enabled()) {
            return;
        }

        // Protect all attachments when a comic post is scheduled.
        add_action('transition_post_status', [$this, 'toocheke_maybe_protect_on_schedule'], 10, 3);

        // Protect any image uploaded while the post is already in 'future' status.
        // Priority 999 ensures _wp_attached_file meta is fully settled, and that
        // Toocheke_Image_Optimization (if active) has already run its own hooks.
        // We then resolve the actual file on disk ourselves rather than trusting meta.
        add_action('add_attachment', [$this, 'toocheke_maybe_protect_uploaded_image'], 999, 1);

        // When a comic transitions to 'publish', restore all protected images.
        add_action('transition_post_status', [$this, 'toocheke_maybe_restore_image_on_publish'], 10, 3);

        // Fallback for WP-Cron scheduled publishing.
        add_action('publish_comic', [$this, 'toocheke_restore_on_publish_cron'], 10, 2);

        // Clean up private files if an attachment is force-deleted externally.
        add_action('delete_attachment', [$this, 'toocheke_cleanup_private_file'], 10, 1);

        // Admin notice warning editors when a comic is already scheduled.
        add_action('admin_notices', [$this, 'toocheke_scheduled_comic_admin_notice']);
    }

    // -------------------------------------------------------------------------
    // Option check
    // -------------------------------------------------------------------------

    private function is_protection_enabled(): bool
    {
        return (bool) get_option('toocheke-future-post-image-protection', false);
    }

    // -------------------------------------------------------------------------
    // Path helpers
    // -------------------------------------------------------------------------

    /**
     * Normalize path separators to forward slashes for safe DB storage.
     * Forward slashes work on all platforms including Windows PHP,
     * and survive WordPress serialization without backslash stripping.
     */
    private function normalize_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Returns the absolute path to the private storage directory.
     * Tries one level above wp-content first; falls back inside wp-content.
     */
    public function get_private_base_dir(): string
    {
        $above_content = dirname(WP_CONTENT_DIR) . DIRECTORY_SEPARATOR . self::PRIVATE_DIR_NAME;

        if ($this->ensure_directory($above_content)) {
            return $above_content;
        }

        $inside_content = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . self::PRIVATE_DIR_NAME;
        $this->ensure_directory($inside_content);

        return $inside_content;
    }

    /**
     * Creates the directory (and an .htaccess + index.php) if it doesn't exist.
     * Returns true on success.
     */
    private function ensure_directory(string $dir): bool
    {
        if (! is_dir($dir)) {
            if (! wp_mkdir_p($dir)) {
                return false;
            }
        }

        if (! is_writable($dir)) {
            return false;
        }

        // Block direct HTTP access (Apache).
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (! file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\nDeny from all\n");
        }

        // Silence directory listing for PHP-served requests.
        $index = $dir . DIRECTORY_SEPARATOR . 'index.php';
        if (! file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden.');
        }

        return true;
    }

    /**
     * Generates a unique private file path, preserving the original extension.
     */
    private function get_private_path(int $attachment_id, string $original_path): string
    {
        $ext      = pathinfo($original_path, PATHINFO_EXTENSION);
        $filename = $attachment_id . '_' . wp_generate_password(12, false) . ($ext ? '.' . $ext : '');
        return $this->get_private_base_dir() . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Swaps the extension of a file path.
     * e.g. /var/www/uploads/photo.jpg + 'avif' → /var/www/uploads/photo.avif
     */
    private function swap_extension(string $path, string $extension): string
    {
        $path = $this->normalize_path($path);
        return dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME) . '.' . $extension;
    }

    /**
     * Resolves the actual file on disk for an attachment, accounting for the
     * possibility that Toocheke_Image_Optimization has converted it to avif/webp
     * but _wp_attached_file meta may not yet reflect the new extension.
     *
     * Returns the absolute path string if a file is found, null otherwise.
     */
    private function resolve_actual_file(int $attachment_id): ?string
    {
          $file = get_attached_file($attachment_id);
    //error_log('Toocheke resolve_actual_file: meta says=' . $file);

    if (file_exists($file ?? '')) {
        //error_log('Toocheke resolve_actual_file: file exists at meta path');
        return $this->normalize_path($file);
    }

    foreach (['avif', 'webp'] as $ext) {
        $candidate = $this->swap_extension($file, $ext);
        //error_log('Toocheke resolve_actual_file: checking candidate=' . $candidate . ' exists=' . (file_exists($candidate) ? 'YES' : 'NO'));
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    //'Toocheke resolve_actual_file: returning NULL — nothing found');
    return null;
    }

    // -------------------------------------------------------------------------
    // Protect on schedule (primary hook)
    // -------------------------------------------------------------------------

    /**
     * Fires on every post status transition.
     * When a comic moves to 'future' (scheduled), protect all its attachments.
     */
    public function toocheke_maybe_protect_on_schedule(string $new_status, string $old_status, \WP_Post $post): void
{
    if ($new_status !== 'future' || $old_status === 'future') {
        return;
    }

    if ($post->post_type !== 'comic') {
        return;
    }

    //error_log('Toocheke: protect_on_schedule fired for comic ' . $post->ID);

    $attachments = get_posts([
        'post_type'      => 'attachment',
        'post_parent'    => $post->ID,
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    //error_log('Toocheke: found ' . count($attachments) . ' attachments: ' . implode(', ', $attachments));

    foreach ($attachments as $attachment_id) {
        //error_log('Toocheke: resolving file for attachment ' . $attachment_id . ' → ' . (get_attached_file($attachment_id) ?: 'NULL'));
        $this->protect_attachment($post->ID, $attachment_id);
    }
}

    // -------------------------------------------------------------------------
    // Protect on upload to already-scheduled post (secondary hook)
    // -------------------------------------------------------------------------

    /**
     * Fires after an attachment is added to the DB (priority 999).
     *
     * Running at priority 999 means all earlier hooks — including
     * Toocheke_Image_Optimization's wp_generate_attachment_metadata correction —
     * have already had a chance to settle. We then resolve the actual file on
     * disk directly via resolve_actual_file() rather than trusting meta alone,
     * so the method works correctly whether optimization is active or not.
     */
    public function toocheke_maybe_protect_uploaded_image(int $attachment_id): void
    {
        //error_log('Toocheke UPLOAD HOOK fired for attachment ' . $attachment_id);
        $parent_id = wp_get_post_parent_id($attachment_id);
        //error_log('Toocheke parent_id=' . $parent_id . ' is_future=' . ($this->is_future_comic($parent_id) ? 'YES' : 'NO'));

        if (! $parent_id) {
            return;
        }

        if (! $this->is_future_comic($parent_id)) {
            return;
        }

        $file = $this->resolve_actual_file($attachment_id);

        if (! $file) {
            //error_log( 'Toocheke: could not resolve actual file for attachment ' . $attachment_id );
            return;
        }

        $this->protect_attachment_with_file($parent_id, $attachment_id, $file);
    }

    // -------------------------------------------------------------------------
    // Core: protect attachment
    // -------------------------------------------------------------------------

    /**
     * Wrapper for the common case where we need to look up the file ourselves.
     * Used by toocheke_maybe_protect_on_schedule.
     */
    private function protect_attachment(int $parent_id, int $attachment_id): void
    {
        $file = $this->resolve_actual_file($attachment_id);

        if (! $file) {
            //error_log( 'Toocheke: source file not found for attachment ' . $attachment_id );
            return;
        }

        $this->protect_attachment_with_file($parent_id, $attachment_id, $file);
    }

    /**
     * Moves the physical file for an attachment into private storage,
     * removes the attachment record from the Media Library, and saves
     * the private path + original metadata in post meta on the parent post.
     *
     * Accepts the resolved $file path directly to avoid re-reading potentially
     * stale _wp_attached_file meta after optimization has renamed the file.
     */
    private function protect_attachment_with_file(int $parent_id, int $attachment_id, string $file): void
    {
        if (! file_exists($file)) {
            //error_log( 'Toocheke: source file not found for attachment ' . $attachment_id . ' at ' . $file );
            return;
        }

        $private_path = $this->get_private_path($attachment_id, $file);

        // Move the file FIRST before deleting the attachment record.
        if (! rename($file, $private_path)) {
            if (copy($file, $private_path)) {
                @unlink($file);
            } else {
                //error_log( 'Toocheke: could not move file to private storage: ' . $file );
                return;
            }
        }

        if (! file_exists($private_path)) {
            //error_log( 'Toocheke: file move reported success but not found at private path: ' . $private_path );
            return;
        }

        // Normalize paths to forward slashes before storing in DB.
        // Prevents backslash stripping during WordPress serialization on Windows.
        $meta_entry = [
            'attachment_id'    => $attachment_id,
            'private_path'     => $this->normalize_path($private_path),
            'original_file'    => $this->normalize_path($file),
            'original_title'   => get_the_title($attachment_id),
            'original_caption' => wp_get_attachment_caption($attachment_id),
            'original_alt'     => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'mime_type'        => get_post_mime_type($attachment_id),
            'is_featured'      => (int) get_post_meta($parent_id, '_thumbnail_id', true) === $attachment_id,
            'protected_at'     => current_time('mysql'),
        ];

        $existing   = get_post_meta($parent_id, '_toocheke_protected_images', true) ?: [];
        $existing[] = $meta_entry;
        update_post_meta($parent_id, '_toocheke_protected_images', $existing);

        // Set flag so toocheke_cleanup_private_file knows to skip this deletion.
        $this->protecting = true;
        wp_delete_attachment($attachment_id, true);
        $this->protecting = false;

        //error_log( 'Toocheke: protected attachment ' . $attachment_id . ' for comic ' . $parent_id . ' → ' . $this->normalize_path( $private_path ) );
    }

    // -------------------------------------------------------------------------
    // Core: restore on publish
    // -------------------------------------------------------------------------

    /**
     * When a comic moves to 'publish', restore all its protected images.
     */
    public function toocheke_maybe_restore_image_on_publish(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($new_status !== 'publish') {
            return;
        }

        if ($post->post_type !== 'comic') {
            return;
        }

        //error_log( 'Toocheke: restore triggered for comic ' . $post->ID . ' (transition: ' . $old_status . ' → ' . $new_status . ')' );

        $protected = get_post_meta($post->ID, '_toocheke_protected_images', true);

        if (empty($protected) || ! is_array($protected)) {
            //error_log( 'Toocheke: no protected images meta found for comic ' . $post->ID );
            return;
        }

        foreach ($protected as $entry) {
            $this->restore_attachment($post->ID, $entry);
        }

        delete_post_meta($post->ID, '_toocheke_protected_images');
    }

    /**
     * Fallback for WP-Cron scheduled publishing.
     * 'publish_{post_type}' fires reliably even when transition_post_status
     * has already been called, so we guard against double-restoring by
     * checking whether the meta still exists.
     */
    public function toocheke_restore_on_publish_cron(int $post_id, \WP_Post $post): void
    {
        $protected = get_post_meta($post_id, '_toocheke_protected_images', true);

        if (empty($protected) || ! is_array($protected)) {
            return;
        }

        //error_log( 'Toocheke: cron restore triggered for comic ' . $post_id );

        foreach ($protected as $entry) {
            $this->restore_attachment($post_id, $entry);
        }

        delete_post_meta($post_id, '_toocheke_protected_images');
    }

    /**
     * Moves the private file back into uploads and recreates the attachment post.
     */
    private function restore_attachment(int $post_id, array $entry): void
    {
        // Normalize paths coming out of DB — guards against any residual
        // backslash issues and ensures cross-platform compatibility.
        $private_path  = $this->normalize_path($entry['private_path'] ?? '');
        $original_file = $this->normalize_path($entry['original_file'] ?? '');

        if (! $private_path || ! file_exists($private_path)) {
            //error_log( 'Toocheke: private file not found at: ' . $private_path );
            return;
        }

        if (! $original_file) {
            //error_log( 'Toocheke: no original_file in meta for post ' . $post_id );
            return;
        }

        // Ensure the target uploads directory exists.
        $target_dir = dirname($original_file);
        if (! wp_mkdir_p($target_dir)) {
            //error_log( 'Toocheke: could not create directory: ' . $target_dir );
            return;
        }

        if (file_exists($original_file)) {
            $original_file = $this->unique_filename($original_file);
        }

        // Move file back — fall back to copy+delete across filesystem boundaries.
        if (! rename($private_path, $original_file)) {
            if (copy($private_path, $original_file)) {
                @unlink($private_path);
            } else {
                //error_log( 'Toocheke: could not restore file from ' . $private_path . ' to ' . $original_file );
                return;
            }
        }

        if (! file_exists($original_file)) {
            //error_log( 'Toocheke: restore move succeeded but file missing at: ' . $original_file );
            return;
        }

        // Build correct URL from actual file path.
        // Do NOT use wp_upload_dir()['url'] — that returns the current month's
        // URL which may differ from when the post was originally drafted.
        $upload_dir  = wp_upload_dir();
        $correct_url = str_replace(
            $this->normalize_path($upload_dir['basedir']),
            $upload_dir['baseurl'],
            $original_file
        );

        $attachment = [
            'post_title'     => $entry['original_title'] ?? basename($original_file),
            'post_excerpt'   => $entry['original_caption'] ?? '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
            'post_mime_type' => $entry['mime_type'] ?? '',
            'guid'           => $correct_url,
        ];

        $new_attachment_id = wp_insert_attachment($attachment, $original_file, $post_id);

        if (is_wp_error($new_attachment_id)) {
            //error_log( 'Toocheke: wp_insert_attachment failed — ' . $new_attachment_id->get_error_message() );
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($new_attachment_id, $original_file);
        wp_update_attachment_metadata($new_attachment_id, $metadata);

        if (! empty($entry['original_alt'])) {
            update_post_meta($new_attachment_id, '_wp_attachment_image_alt', $entry['original_alt']);
        }

        // Restore featured image relationship if this was the thumbnail.
        if (! empty($entry['is_featured'])) {
            update_post_meta($post_id, '_thumbnail_id', $new_attachment_id);
        }

        //error_log( 'Toocheke: successfully restored attachment ID ' . $new_attachment_id . ' for comic ' . $post_id );
    }

    // -------------------------------------------------------------------------
    // Cleanup — only fires for genuine external deletions
    // -------------------------------------------------------------------------

    /**
     * If an attachment is deleted externally, remove its private file and
     * clean the meta entry. Skipped entirely when we triggered the deletion
     * ourselves during protect_attachment_with_file.
     */
    public function toocheke_cleanup_private_file(int $attachment_id): void
    {
        // Skip if we triggered this deletion ourselves during protect_attachment_with_file.
        if ($this->protecting) {
            return;
        }

        // We can no longer rely on wp_get_post_parent_id here as the attachment
        // may already be detached — search all comics with protected meta instead.
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = '_toocheke_protected_images'
                 AND meta_value LIKE %s",
                '%"attachment_id";i:' . $attachment_id . ';%'
            )
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $protected = maybe_unserialize($row->meta_value);
            if (! is_array($protected)) {
                continue;
            }

            $updated = [];
            foreach ($protected as $entry) {
                if ((int) ($entry['attachment_id'] ?? 0) === $attachment_id) {
                    $private_path = $this->normalize_path($entry['private_path'] ?? '');
                    if ($private_path && file_exists($private_path)) {
                        @unlink($private_path);
                        //error_log( 'Toocheke: cleaned up private file for deleted attachment ' . $attachment_id );
                    }
                } else {
                    $updated[] = $entry;
                }
            }

            update_post_meta($row->post_id, '_toocheke_protected_images', $updated);
        }
    }

    // -------------------------------------------------------------------------
    // Admin notice
    // -------------------------------------------------------------------------

    /**
     * Warns editors when they are editing an already-scheduled comic.
     * Any images uploaded now will immediately be moved to private storage
     * and will appear broken in the editor until the post publishes.
     */
    public function toocheke_scheduled_comic_admin_notice(): void
    {
        $screen = get_current_screen();

        if (! $screen || $screen->post_type !== 'comic' || $screen->base !== 'post') {
            return;
        }

        $post = get_post();

        if (! $post || $post->post_status !== 'future') {
            return;
        }

        $scheduled_date = get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $post);

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s <strong>%s</strong>. %s</p></div>',
            esc_html__('Image Protection Active:', 'toocheke-companion'),
            esc_html__('This comic is scheduled to publish on', 'toocheke-companion'),
            esc_html($scheduled_date),
            esc_html__('Any images uploaded now will be moved to private storage immediately and will appear broken in the editor until the post publishes.', 'toocheke-companion')
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function is_future_comic(int $post_id): bool
    {
        $post = get_post($post_id);
        return $post
            && $post->post_type === 'comic'
            && $post->post_status === 'future';
    }

    private function unique_filename(string $filepath): string
    {
        $dir  = dirname($filepath);
        $name = pathinfo($filepath, PATHINFO_FILENAME);
        $ext  = pathinfo($filepath, PATHINFO_EXTENSION);
        $i    = 1;
        do {
            $candidate = $dir . DIRECTORY_SEPARATOR . $name . '-' . $i . ($ext ? '.' . $ext : '');
            $i++;
        } while (file_exists($candidate));
        return $candidate;
    }

}