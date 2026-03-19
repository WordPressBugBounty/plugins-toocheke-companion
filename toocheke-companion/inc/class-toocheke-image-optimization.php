<?php
/**
 * Class Toocheke_Image_Optimization
 *
 * Handles automatic image conversion to AVIF or WebP on upload,
 * with proportional resizing to a maximum width of 1920px.
 * Only instantiated when the toocheke-image-optimization option is enabled.
 */
class Toocheke_Image_Optimization
{

    /**
     * Maximum image width in pixels.
     */
    private const MAX_WIDTH = 1920;

    /**
     * AVIF quality (1–100). Loaded from saved option, defaults to 50.
     *
     * @var int
     */
    private int $avif_quality;

    /**
     * WebP quality (1–100). Loaded from saved option, defaults to 75.
     *
     * @var int
     */
    private int $webp_quality;

    /**
     * Whether the server supports AVIF encoding via GD or Imagick.
     *
     * @var bool
     */
    private bool $avif_supported;

    /**
     * Constructor — loads options, detects format support, registers hooks.
     */
    public function __construct()
    {
        $this->avif_quality   = absint(get_option('toocheke-avif-quality', 50));
        $this->webp_quality   = absint(get_option('toocheke-webp-quality', 75));
        $this->avif_supported = $this->toocheke_server_supports_avif();

        add_filter('wp_handle_upload', [$this, 'toocheke_process_uploaded_image'], 10, 2);
        add_filter('wp_generate_attachment_metadata', [$this, 'toocheke_fix_attachment_metadata'], 10, 2);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Hooked to wp_handle_upload.
     * Converts and resizes any uploaded image, then rewrites the upload data
     * so WordPress stores and registers the converted file instead.
     *
     * @param  array  $upload  { 'file' => string, 'url' => string, 'type' => string }
     * @param  string $context 'upload' | 'sideload'
     * @return array           Possibly-modified upload array.
     */
    public function toocheke_process_uploaded_image(array $upload, string $context): array
    {
        if (! isset($upload['type']) || ! str_starts_with($upload['type'], 'image/')) {
            return $upload;
        }

        $skipped_types = ['image/svg+xml', 'image/gif'];
        if (in_array($upload['type'], $skipped_types, true)) {
            return $upload;
        }

        // Normalise path separators — on Windows (LocalWP) WordPress mixes
        // backslashes and forward slashes, which breaks Imagick's writeImage().
        $upload['file'] = str_replace('\\', '/', $upload['file']);
        $source_path    = $upload['file'];

        if (! file_exists($source_path) || ! is_readable($source_path)) {
            error_log('Toocheke: Source file not found or not readable: ' . $source_path);
            return $upload;
        }

        if (filesize($source_path) === 0) {
            error_log('Toocheke: Source file is empty: ' . $source_path);
            return $upload;
        }

        $converted = $this->toocheke_convert_image($source_path);

        if ($converted === null) {
            error_log('Toocheke: Conversion returned null for: ' . $source_path);
            return $upload;
        }

        if ($converted['path'] !== $source_path) {
            @unlink($source_path);
        }

        $upload['file'] = $converted['path'];
        $upload['url']  = str_replace(
            wp_basename($source_path),
            wp_basename($converted['path']),
            $upload['url']
        );
        $upload['type'] = $converted['mime'];

        return $upload;
    }

    /**
     * Hooked to wp_generate_attachment_metadata.
     * Ensures the file path, URL, and MIME type stored in the database
     * match the converted file after WordPress has processed the upload.
     *
     * @param  array $metadata      Attachment metadata array.
     * @param  int   $attachment_id Attachment post ID.
     * @return array
     */
    public function toocheke_fix_attachment_metadata(array $metadata, int $attachment_id): array
{
    $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);

    if (empty($attached_file)) {
        return $metadata;
    }

    $upload_dir = wp_upload_dir();
    $base_dir   = str_replace('\\', '/', trailingslashit($upload_dir['basedir']));

    // Normalize to forward slashes before any comparison or concatenation.
    $attached_file = str_replace('\\', '/', $attached_file);

    // On Windows (LocalWP), _wp_attached_file may already be stored as an
    // absolute path. Strip the basedir prefix immediately so all subsequent
    // logic works with a clean relative path (e.g. 2026/03/photo.jpg).
    if (str_starts_with($attached_file, $base_dir)) {
        $attached_file = substr($attached_file, strlen($base_dir));
    }

    // Now safe to build the absolute path — $attached_file is guaranteed relative.
    $abs_path  = $base_dir . $attached_file;
    $avif_path = $this->toocheke_swap_extension($abs_path, 'avif');
    $webp_path = $this->toocheke_swap_extension($abs_path, 'webp');

    if (file_exists($avif_path)) {
        $new_ext  = 'avif';
        $new_mime = 'image/avif';
        $new_abs  = $avif_path;
    } elseif (file_exists($webp_path)) {
        $new_ext  = 'webp';
        $new_mime = 'image/webp';
        $new_abs  = $webp_path;
    } else {
        return $metadata;
    }

    // $attached_file is already relative — just swap the extension.
    $new_relative = $this->toocheke_swap_extension($attached_file, $new_ext);

    update_post_meta($attachment_id, '_wp_attached_file', $new_relative);

    wp_update_post([
        'ID'             => $attachment_id,
        'post_mime_type' => $new_mime,
    ]);

    if (isset($metadata['file'])) {
        $metadata['file'] = $new_relative;
    }

    if (isset($metadata['width'], $metadata['height'])) {
        $size = @getimagesize($new_abs);
        if ($size) {
            $metadata['width']  = $size[0];
            $metadata['height'] = $size[1];
        }
    }

    if (! empty($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size_key => $size_data) {
            if (isset($size_data['file'])) {
                $size_abs       = trailingslashit(dirname($new_abs)) . $size_data['file'];
                $size_converted = $this->toocheke_swap_extension($size_abs, $new_ext);
                if (file_exists($size_converted)) {
                    $metadata['sizes'][$size_key]['file']      = wp_basename($size_converted);
                    $metadata['sizes'][$size_key]['mime-type'] = $new_mime;
                }
            }
        }
    }

    return $metadata;
}

    // -------------------------------------------------------------------------
    // Core conversion logic
    // -------------------------------------------------------------------------

    /**
     * Resize (if necessary) and convert an image to AVIF or WebP.
     *
     * @param  string     $source_path  Absolute path to the source image.
     * @return array|null               [ 'path' => string, 'mime' => string ] or null on failure.
     */
    private function toocheke_convert_image(string $source_path): ?array
    {
        if (extension_loaded('imagick')) {
            return $this->toocheke_convert_with_imagick($source_path);
        }

        if (extension_loaded('gd')) {
            return $this->toocheke_convert_with_gd($source_path);
        }

        return null;
    }

    /**
     * Convert using the Imagick extension.
     *
     * @param  string     $source_path
     * @return array|null
     */
    private function toocheke_convert_with_imagick(string $source_path): ?array
    {
        try {
            $imagick = new \Imagick($source_path);

            // Flatten layers/frames (handles PNGs with transparency, PSDs, etc.)
            // Note: do NOT reassign — mergeImageLayers() modifies in place.
            $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            // Strip unnecessary metadata to keep file size down.
            $imagick->stripImage();

            // Proportional resize if wider than MAX_WIDTH.
            $this->toocheke_maybe_resize_imagick($imagick);

            // Attempt AVIF first if the server supports it.
            if ($this->avif_supported) {
                $output_path = $this->toocheke_swap_extension($source_path, 'avif');
                $imagick->setImageFormat('avif');
                $imagick->setImageCompressionQuality($this->avif_quality);

                $written = $imagick->writeImage($output_path);

                // Verify the file was actually written and is not empty.
                if ($written && file_exists($output_path) && filesize($output_path) > 0) {
                    $imagick->clear();
                    return ['path' => $output_path, 'mime' => 'image/avif'];
                }

                // Clean up the bad/empty file before falling through to WebP.
                if (file_exists($output_path)) {
                    @unlink($output_path);
                }
            }

            // Fallback: WebP.
            $output_path = $this->toocheke_swap_extension($source_path, 'webp');
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($this->webp_quality);

            $written = $imagick->writeImage($output_path);

            if ($written && file_exists($output_path) && filesize($output_path) > 0) {
                $imagick->clear();
                return ['path' => $output_path, 'mime' => 'image/webp'];
            }

            $imagick->clear();
            return null;

        } catch (\ImagickException $e) {
            error_log('Toocheke_Image_Optimization (Imagick): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert using the GD extension.
     * Note: GD supports WebP on most hosts; AVIF support was added in PHP 8.1 + libgd 2.3.
     *
     * @param  string     $source_path
     * @return array|null
     */
    private function toocheke_convert_with_gd(string $source_path): ?array
    {
        $image = $this->toocheke_gd_create_from_file($source_path);

        if ($image === null) {
            return null;
        }

        // Proportional resize if wider than MAX_WIDTH.
        $image = $this->toocheke_maybe_resize_gd($image);

        // Attempt AVIF first if the server supports it.
        if ($this->avif_supported && function_exists('imageavif')) {
            $output_path = $this->toocheke_swap_extension($source_path, 'avif');

            if (imageavif($image, $output_path, $this->avif_quality)
                && file_exists($output_path)
                && filesize($output_path) > 0
            ) {
                imagedestroy($image);
                return ['path' => $output_path, 'mime' => 'image/avif'];
            }

            // Clean up the bad/empty file before falling through to WebP.
            if (file_exists($output_path)) {
                @unlink($output_path);
            }
        }

        // Fallback: WebP.
        if (function_exists('imagewebp')) {
            $output_path = $this->toocheke_swap_extension($source_path, 'webp');

            if (imagewebp($image, $output_path, $this->webp_quality)
                && file_exists($output_path)
                && filesize($output_path) > 0
            ) {
                imagedestroy($image);
                return ['path' => $output_path, 'mime' => 'image/webp'];
            }
        }

        imagedestroy($image);
        return null;
    }

    // -------------------------------------------------------------------------
    // Resize helpers
    // -------------------------------------------------------------------------

    /**
     * Resize an Imagick object in-place if its width exceeds MAX_WIDTH.
     * Height is calculated proportionally; upscaling is never applied.
     *
     * @param \Imagick $imagick
     */
    private function toocheke_maybe_resize_imagick(\Imagick $imagick): void
    {
        $width  = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width <= self::MAX_WIDTH) {
            return;
        }

        $new_height = (int) round($height * (self::MAX_WIDTH / $width));

        // LANCZOS gives the best downscale quality.
        $imagick->resizeImage(self::MAX_WIDTH, $new_height, \Imagick::FILTER_LANCZOS, 1);
    }

    /**
     * Resize a GD image resource if its width exceeds MAX_WIDTH.
     *
     * @param  \GdImage  $image
     * @return \GdImage         Original or newly resampled resource.
     */
    private function toocheke_maybe_resize_gd(\GdImage $image): \GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);

        if ($width <= self::MAX_WIDTH) {
            return $image;
        }

        $new_height = (int) round($height * (self::MAX_WIDTH / $width));
        $resized    = imagecreatetruecolor(self::MAX_WIDTH, $new_height);

        // Preserve alpha channel for PNG sources.
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized, $image,
            0, 0, 0, 0,
            self::MAX_WIDTH, $new_height,
            $width, $height
        );

        imagedestroy($image);

        return $resized;
    }

    // -------------------------------------------------------------------------
    // GD factory
    // -------------------------------------------------------------------------

    /**
     * Create a GD image resource from any supported file type.
     *
     * @param  string         $path  Absolute file path.
     * @return \GdImage|null
     */
    private function toocheke_gd_create_from_file(string $path): ?\GdImage
    {
        $mime = mime_content_type($path);

        $map = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            'image/avif' => 'imagecreatefromavif',
            'image/bmp'  => 'imagecreatefrombmp',
        ];

        if (! isset($map[$mime]) || ! function_exists($map[$mime])) {
            return null;
        }

        $image = @$map[$mime]($path);

        return ($image instanceof \GdImage) ? $image : null;
    }

    // -------------------------------------------------------------------------
    // Server capability detection
    // -------------------------------------------------------------------------

    /**
     * Determine whether the server can encode AVIF images.
     *
     * Imagick: checks that the 'avif' format is in the list of supported formats.
     * GD:      checks for the imageavif() function (PHP 8.1+ with libgd 2.3+).
     *
     * @return bool
     */
    private function toocheke_server_supports_avif() : bool
    {
        if (extension_loaded('imagick')) {
            try {
                $formats = \Imagick::queryFormats('AVIF');
                return ! empty($formats);
            } catch (\ImagickException $e) {
                return false;
            }
        }

        if (extension_loaded('gd')) {
            return function_exists('imageavif');
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Path utilities
    // -------------------------------------------------------------------------

    /**
     * Replace the extension of a file path with a new one.
     *
     * @param  string $path       e.g. /var/www/uploads/photo.jpg
     * @param  string $extension  e.g. 'avif'
     * @return string             e.g. /var/www/uploads/photo.avif
     */
    private function toocheke_swap_extension(string $path, string $extension): string
    {
        // Normalise to forward slashes before processing — critical on Windows.
        $path     = str_replace('\\', '/', $path);
        $dir      = dirname($path);
        $basename = pathinfo($path, PATHINFO_FILENAME);

        return $dir . '/' . $basename . '.' . $extension;
    }
}
