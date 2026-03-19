<?php
/**
 * Toocheke Image Access Protection
 *
 * Automatically protects all comic post images by serving them through a
 * proxy script
 */
class Toocheke_Image_Access_Protection
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Initialize hooks
        add_action('init', [$this, 'init']);
        add_action('template_redirect', [$this, 'serve_protected_image']);

        // Hook into content and meta filtering - with lower priority to run AFTER existing filters
        add_filter('the_content', [$this, 'protect_content_images'], 999);
        add_filter('get_post_metadata', [$this, 'protect_meta_images'], 9999, 4);
        add_filter('post_thumbnail_html', [$this, 'protect_featured_image'], 999, 5);
    }

    public function init()
    {
        // Nothing needed here anymore - using $_GET directly
    }

    /**
     * Check if this is a comic post
     */
    private function is_comic_post($post_id = null)
    {
        if (! $post_id) {
            $post_id = get_the_ID();
        }
        return get_post_type($post_id) === 'comic';
    }

    /**
     * Protect images in the_content
     * Runs AFTER toocheke_wrap_content_img (priority 999 vs default 10)
     */
    public function protect_content_images($content)
    {
        if (! $this->is_comic_post() || is_admin()) {
            return $content;
        }

        return $this->replace_image_urls($content);
    }

    /**
     * Protect images in meta fields
     * Runs AFTER toocheke_wrap_meta_content_img (priority 9999 vs 999)
     */
    public function protect_meta_images($metadata, $post_id, $meta_key, $single)
    {
        if (! $this->is_comic_post($post_id) || is_admin()) {
            return $metadata;
        }

        // Only process specific meta fields
        $protected_fields = [
            'desktop_comic_editor',
            'desktop_comic_2nd_language_editor',
            'mobile_comic_2nd_language_editor',
        ];

        if (! isset($meta_key) || ! in_array($meta_key, $protected_fields)) {
            return $metadata;
        }

        // Get list of filters to prevent infinite loop
        global $wp_current_filter;
        $filter_key = count($wp_current_filter) - 2;
        if (isset($wp_current_filter[$filter_key]) && 'get_post_metadata' === $wp_current_filter[$filter_key]) {
            return $metadata;
        }

        // Get the actual meta value
        remove_filter('get_post_metadata', [$this, 'protect_meta_images'], 9999);
        $current_meta = get_post_meta($post_id, $meta_key, true);
        add_filter('get_post_metadata', [$this, 'protect_meta_images'], 9999, 4);

        if (empty($current_meta)) {
            return $metadata;
        }

        // Protect images in the meta content
        $protected_meta = $this->replace_image_urls($current_meta);

        return $protected_meta;
    }

    /**
     * Protect featured image
     */
    public function protect_featured_image($html, $post_id, $post_thumbnail_id, $size, $attr)
    {
        if (! $this->is_comic_post($post_id) || is_admin()) {
            return $html;
        }

        return $this->replace_image_urls($html);
    }

    /**
     * Replace image URLs with protected proxy URLs
     */
    private function replace_image_urls($content)
    {
        if (empty($content)) {
            return $content;
        }

        // Use DOMDocument to parse HTML properly
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $content,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        // Process img tags
        $images       = $dom->getElementsByTagName('img');
        $images_array = iterator_to_array($images);

        foreach ($images_array as $img) {
            $src = $img->getAttribute('src');
            if ($src && $this->is_local_image($src)) {
                $protected_url = $this->create_protected_url($src);
                $img->setAttribute('src', $protected_url);
                $img->setAttribute('data-toocheke-protected', '1');
            }

            // Also handle srcset if present
            $srcset = $img->getAttribute('srcset');
            if ($srcset) {
                $protected_srcset = $this->protect_srcset($srcset);
                $img->setAttribute('srcset', $protected_srcset);
            }
        }

        // Process source tags (for picture elements)
        $sources       = $dom->getElementsByTagName('source');
        $sources_array = iterator_to_array($sources);

        foreach ($sources_array as $source) {
            $srcset = $source->getAttribute('srcset');
            if ($srcset && $this->is_local_image($srcset)) {
                $protected_url = $this->create_protected_url($srcset);
                $source->setAttribute('srcset', $protected_url);
            }
        }

        $content = $dom->saveHTML();
        $content = str_replace('<?xml encoding="UTF-8">', '', $content);

        // Decode any HTML entities that might have been created
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $content;
    }

    /**
     * Protect srcset attribute
     */
    private function protect_srcset($srcset)
    {
        $sources           = explode(',', $srcset);
        $protected_sources = [];

        foreach ($sources as $source) {
            $source = trim($source);
            $parts  = preg_split('/\s+/', $source);

            if (count($parts) >= 1 && $this->is_local_image($parts[0])) {
                $protected_url       = $this->create_protected_url($parts[0]);
                $parts[0]            = $protected_url;
                $protected_sources[] = implode(' ', $parts);
            } else {
                $protected_sources[] = $source;
            }
        }

        return implode(', ', $protected_sources);
    }

    /**
     * Check if image is local (from your site)
     */
    private function is_local_image($url)
    {
        $site_url   = site_url();
        $upload_dir = wp_upload_dir();

        // Check if URL contains site domain or upload directory
        return (strpos($url, $site_url) !== false || strpos($url, $upload_dir['baseurl']) !== false);
    }

    /**
     * Create protected URL for image
     */
    private function create_protected_url($original_url)
    {
        // Use URL-safe base64 encoding (no padding, URL-safe characters)
        // Remove = padding and replace +/ with -_
        $encoded = rtrim(strtr(base64_encode($original_url), '+/', '-_'), '=');

        // Create a hash for verification (not user-dependent like nonces)
        $hash = $this->create_verification_hash($encoded);

        // Create protected URL
        $protected_url = add_query_arg(
            [
                'toocheke_img'  => $encoded,
                'toocheke_hash' => $hash,
            ],
            home_url('/')
        );

        return $protected_url;
    }

    /**
     * Create verification hash for encoded URL
     */
    private function create_verification_hash($encoded)
    {
        // Use ONLY values that are 100% stable across all requests
        $site = site_url();
        $home = home_url();
        $db   = DB_NAME;

        $secret = $site . $home . $db;

        return substr(md5($encoded . $secret), 0, 10);
    }

    /**
     * Verify the hash matches the encoded URL
     */
    private function verify_hash($encoded, $hash)
    {
        return $hash === $this->create_verification_hash($encoded);
    }

    /**
     * Serve protected image
     */
    public function serve_protected_image()
    {
        // Use $_GET directly instead of query_vars to avoid routing issues
        $img  = isset($_GET['toocheke_img']) ? $_GET['toocheke_img'] : '';
        $hash = isset($_GET['toocheke_hash']) ? $_GET['toocheke_hash'] : '';

        if (empty($img)) {
            return;
        }

        // Normalize for URL-safe base64 (space might have replaced -)
        $img = str_replace(' ', '-', $img);

        // Verify hash
        if (! $this->verify_hash($img, $hash)) {
            $this->serve_denied_image();
            exit;
        }

        // HOTLINK PROTECTION: Check referer

        if (! $this->is_valid_referer()) {
            $this->serve_denied_image();
            exit;
        }

        // Decode URL-safe base64
        // Convert back from URL-safe format: - and _ to + and /
        // Add back padding if needed
        $base64  = strtr($img, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        $original_url = base64_decode($base64);

        // Verify it's a local image

        if (! $this->is_local_image($original_url)) {
            $this->serve_denied_image();
            exit;
        }

        // Get file path from URL
        $upload_dir = wp_upload_dir();
        $file_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $original_url);

        // Also handle theme directory images
        if (strpos($original_url, get_stylesheet_directory_uri()) !== false) {
            $file_path = str_replace(get_stylesheet_directory_uri(), get_stylesheet_directory(), $original_url);
        } elseif (strpos($original_url, get_template_directory_uri()) !== false) {
            $file_path = str_replace(get_template_directory_uri(), get_template_directory(), $original_url);
        }

        // Clean up the file path (remove query strings if any)
        $file_path = preg_replace('/\?.*$/', '', $file_path);

        // Check if file exists
        if (! file_exists($file_path)) {
            $this->serve_denied_image();
            exit;
        }

        // Security check - ensure file is within allowed directories
        $real_path = realpath($file_path);

        if (! $real_path) {
            $this->serve_denied_image();
            exit;
        }

        $allowed_paths = [
            realpath($upload_dir['basedir']),
            realpath(get_stylesheet_directory()),
            realpath(get_template_directory()),
        ];

        $is_allowed = false;
        foreach ($allowed_paths as $allowed_path) {
            if ($allowed_path && strpos($real_path, $allowed_path) === 0) {
                $is_allowed = true;
                break;
            }
        }

        if (! $is_allowed) {
            $this->serve_denied_image();
            exit;
        }

        // Get mime type
        $mime_type = wp_check_filetype($real_path);

        if (! $mime_type['type']) {
            $this->serve_denied_image();
            exit;
        }

        // Set headers and serve image
        header('Content-Type: ' . $mime_type['type']);
        header('Content-Length: ' . filesize($real_path));
        header('Cache-Control: private, max-age=3600');
        header('X-Robots-Tag: noindex, nofollow');

        readfile($real_path);

        exit;
    }

    /**
     * Check if the referer is valid (not a hotlink)
     */
    private function is_valid_referer()
    {
        $referer   = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $site_host = parse_url(site_url(), PHP_URL_HOST);
        $home_host = parse_url(home_url(), PHP_URL_HOST);

        // If referer is present, check it matches our domain
        if (! empty($referer)) {
            $referer_host = parse_url($referer, PHP_URL_HOST);
            if ($referer_host === $site_host || $referer_host === $home_host) {
                return true;
            }
            // Referer is from a different domain = hotlink
            return false;
        }

        // No referer — check server-side signals instead of blocking outright

        // 1. Same-server request: REMOTE_ADDR matches server's own IP
        $server_addr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if (! empty($server_addr) && $remote_addr === $server_addr) {
            return true;
        }

        // 2. Loopback addresses (common in shared hosting / reverse proxies)
        $loopback = ['127.0.0.1', '::1', 'localhost'];
        if (in_array($remote_addr, $loopback, true)) {
            return true;
        }

        // 3. X-Requested-With header (set by WordPress when loading via AJAX/JS)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // 4. Fetch/image request metadata (modern browsers send these)
        $fetch_dest = isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? $_SERVER['HTTP_SEC_FETCH_DEST'] : '';
        $fetch_site = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
        if (! empty($fetch_dest) && ! empty($fetch_site)) {
            // sec-fetch-site: 'same-origin' or 'same-site' = legitimate same-site load
            if (in_array($fetch_site, ['same-origin', 'same-site'], true)) {
                return true;
            }
            // sec-fetch-site: 'cross-site' = hotlink from another domain
            if ($fetch_site === 'cross-site') {
                return false;
            }
            // sec-fetch-site: 'none' = direct navigation/bookmark (no referer, no cross-site)
            // Allow if it's an image destination
            if ($fetch_site === 'none' && $fetch_dest === 'image') {
                return false; // Direct access to image URL — block it
            }
        }

        // No referer and no positive signals = deny
        return false;
    }

    /**
     * Serve the "access denied" image
     */
    private function serve_denied_image()
    {
        $denied_image = plugin_dir_path(__FILE__) . 'img/denied.jpg';

        // Check if denied image exists
        if (! file_exists($denied_image)) {
            // Fallback: serve a simple text response
            status_header(403);
            header('Content-Type: text/plain');
            die('Access Denied');
        }

        // Serve the denied image
        status_header(403);
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($denied_image));
        header('Cache-Control: public, max-age=86400'); // Cache for 24 hours
        header('X-Robots-Tag: noindex, nofollow');

        readfile($denied_image);
        exit;
    }
}
