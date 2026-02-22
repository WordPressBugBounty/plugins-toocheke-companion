<?php
/**
 * Register all Toocheke Companion Blocks.
 *
 * @package Toocheke_Companion
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * IMPORTANT: You must define the TOOCHEKE_COMPANION_PLUGIN_DIR constant
 * in your main plugin file (e.g., toocheke-companion.php) before including this file.
 * This ensures paths are correctly resolved from the plugin root.
 *
 * Example in toocheke-companion.php:
 * define( 'TOOCHEKE_COMPANION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
 */
if ( ! defined( 'TOOCHEKE_COMPANION_PLUGIN_DIR' ) ) {
    // Fallback or error if the constant is not defined.
    // Ideally, this file is only included after the constant is set in the main plugin file.
    return;
}


/**
 * Enqueue block editor assets for Toocheke Companion blocks.
 */
function toocheke_companion_blocks_editor_assets() {
    // Enqueue the JavaScript file for the blocks.
    wp_enqueue_script(
        'toocheke-companion-blocks-editor-script',
        plugins_url( 'build/index.js', TOOCHEKE_COMPANION_PLUGIN_DIR . 'toocheke-companion.php' ), // Use directory constant with main plugin file for URL
        array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-i18n', 'wp-components', 'wp-data' ), // Added 'wp-data' explicitly
        filemtime( TOOCHEKE_COMPANION_PLUGIN_DIR . 'build/index.js' ), // Use directory constant for file path
        true
    );

    // Enqueue the editor-specific stylesheet.
    wp_enqueue_style(
        'toocheke-companion-blocks-editor-style',
        plugins_url( 'build/index.css', TOOCHEKE_COMPANION_PLUGIN_DIR . 'toocheke-companion.php' ), // Corrected typo here
        array( 'wp-editor' ),
        filemtime( TOOCHEKE_COMPANION_PLUGIN_DIR . 'build/index.css' ) // Corrected typo here
    );
}
add_action( 'enqueue_block_editor_assets', 'toocheke_companion_blocks_editor_assets' );

/**
 * Helper function to register a single Toocheke Companion Block.
 *
 * This function is now simplified as the render_callback will be an anonymous function
 * directly containing the logic to call the class method.
 *
 * @param string   $block_name The unique name for the block (e.g., 'all-series-block').
 * @param callable $render_callback The PHP callable to render the block.
 * @param array    $attributes An associative array of block attributes.
 */
function toocheke_companion_register_block( $block_name, $render_callback, $attributes = array() ) {
    register_block_type( 'toocheke-companion/' . $block_name, array(
        'editor_script'   => 'toocheke-companion-blocks-editor-script',
        'editor_style'    => 'toocheke-companion-blocks-editor-style',
        'render_callback' => $render_callback,
        'attributes'      => $attributes,
    ) );
}

/**
 * Register all Toocheke Companion Blocks.
 */
function toocheke_companion_register_all_blocks() {
    // We remove the 'global' declaration here and move it into individual render callbacks
    // to ensure the instance is fetched at render time, not registration time.

    // 1. Toocheke All Series Block
    toocheke_companion_register_block(
        'all-series-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features; // Access global instance at render time
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for All Series block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'comics_order' => isset( $attributes['comicsOrder'] ) ? $attributes['comicsOrder'] : null,
                'link_to'      => isset( $attributes['linkTo'] ) ? $attributes['linkTo'] : 'comic',
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_all_series_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_all_series_shortcode( $atts ) : '<p>Error: Toocheke All Series shortcode method not found.</p>';
        },
        array(
            'comicsOrder' => array( 'type' => 'string', 'default' => '' ),
            'linkTo'      => array( 'type' => 'string', 'default' => 'comic' ),
        )
    );

    // 2. Toocheke All Chapters Block
    toocheke_companion_register_block(
        'all-chapters-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for All Chapters block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'sid' => isset( $attributes['sid'] ) ? (int) $attributes['sid'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_all_chapters_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_all_chapters_shortcode( $atts ) : '<p>Error: Toocheke All Chapters shortcode method not found.</p>';
        },
        array(
            'sid' => array( 'type' => 'number', 'default' => null ),
        )
    );

    // 3. Toocheke Latest Chapters Block
    toocheke_companion_register_block(
        'latest-chapters-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Latest Chapters block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'sid' => isset( $attributes['sid'] ) ? (int) $attributes['sid'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_latest_chapters_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_latest_chapters_shortcode( $atts ) : '<p>Error: Toocheke Latest Chapters shortcode method not found.</p>';
        },
        array(
            'sid' => array( 'type' => 'number', 'default' => null ),
        )
    );

    // 4. Toocheke First Comic Block
    toocheke_companion_register_block(
        'first-comic-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for First Comic block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'sid' => isset( $attributes['sid'] ) ? (int) $attributes['sid'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_first_comic_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_first_comic_shortcode( $atts ) : '<p>Error: Toocheke First Comic shortcode method not found.</p>';
        },
        array(
            'sid' => array( 'type' => 'number', 'default' => null ),
        )
    );

    // 5. Toocheke Latest Comic Block
    toocheke_companion_register_block(
        'latest-comic-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Latest Comic block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'sid' => isset( $attributes['sid'] ) ? (int) $attributes['sid'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_latest_comic_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_latest_comic_shortcode( $atts ) : '<p>Error: Toocheke Latest Comic shortcode method not found.</p>';
        },
        array(
            'sid' => array( 'type' => 'number', 'default' => null ),
        )
    );

    // 6. Toocheke Latest Comics Block
    toocheke_companion_register_block(
        'latest-comics-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Latest Comics block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'sid'   => isset( $attributes['sid'] ) ? (int) $attributes['sid'] : null,
                'limit' => isset( $attributes['limit'] ) ? (int) $attributes['limit'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_latest_comics_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_latest_comics_shortcode( $atts ) : '<p>Error: Toocheke Latest Comics shortcode method not found.</p>';
        },
        array(
            'sid'   => array( 'type' => 'number', 'default' => null ),
            'limit' => array( 'type' => 'number', 'default' => null ),
        )
    );

    // 7. Toocheke Top Ten Comics Block
    toocheke_companion_register_block(
        'top-ten-comics-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Top Ten Comics block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_top_ten_comics_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_top_ten_comics_shortcode( $attributes ) : '<p>Error: Toocheke Top Ten Comics shortcode method not found.</p>';
        }
    );

    // 8. Toocheke Scheduled Comics Block
    toocheke_companion_register_block(
        'scheduled-comics-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Scheduled Comics block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_scheduled_comics_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_scheduled_comics_shortcode( $attributes ) : '<p>Error: Toocheke Scheduled Comics shortcode method not found.</p>';
        }
    );

    // 9. Toocheke Comic Archive Block
    toocheke_companion_register_block(
        'comic-archive-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Comic Archive block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'sid' => isset( $attributes['sid'] ) ? (int) $attributes['sid'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_comic_archive_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_comic_archive_shortcode( $atts ) : '<p>Error: Toocheke Comic Archive shortcode method not found.</p>';
        },
        array(
            'sid' => array( 'type' => 'number', 'default' => null ),
        )
    );

    // 10. Toocheke Collection Archive Block
    toocheke_companion_register_block(
        'collection-archive-block',
        function( $attributes, $content ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Collection Archive block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'term' => isset( $attributes['term'] ) ? $attributes['term'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_taxonomy_archive_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_taxonomy_archive_shortcode( $atts, '', 'toocheke-collection-archive' ) : '<p>Error: Toocheke Collection Archive shortcode method not found.</p>';
        },
        array(
            'term' => array( 'type' => 'string', 'default' => '' ),
        )
    );

    // 11. Toocheke Chapter Archive Block
    toocheke_companion_register_block(
        'chapter-archive-block',
        function( $attributes, $content ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Chapter Archive block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'term' => isset( $attributes['term'] ) ? $attributes['term'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_taxonomy_archive_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_taxonomy_archive_shortcode( $atts, '', 'toocheke-chapter-archive' ) : '<p>Error: Toocheke Chapter Archive shortcode method not found.</p>';
        },
        array(
            'term' => array( 'type' => 'string', 'default' => '' ),
        )
    );

    // 12. Toocheke Tag Archive Block
    toocheke_companion_register_block(
        'tag-archive-block',
        function( $attributes, $content ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Tag Archive block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'term' => isset( $attributes['term'] ) ? $attributes['term'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_taxonomy_archive_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_taxonomy_archive_shortcode( $atts, '', 'toocheke-tag-archive' ) : '<p>Error: Toocheke Tag Archive shortcode method not found.</p>';
        },
        array(
            'term' => array( 'type' => 'string', 'default' => '' ),
        )
    );

    // 13. Toocheke Location Archive Block
    toocheke_companion_register_block(
        'location-archive-block',
        function( $attributes, $content ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Location Archive block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'term' => isset( $attributes['term'] ) ? $attributes['term'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_taxonomy_archive_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_taxonomy_archive_shortcode( $atts, '', 'toocheke-location-archive' ) : '<p>Error: Toocheke Location Archive shortcode method not found.</p>';
        },
        array(
            'term' => array( 'type' => 'string', 'default' => '' ),
        )
    );

    // 14. Toocheke Character Archive Block
    toocheke_companion_register_block(
        'character-archive-block',
        function( $attributes, $content ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Character Archive block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            $atts = array(
                'term' => isset( $attributes['term'] ) ? $attributes['term'] : null,
            );
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_taxonomy_archive_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_taxonomy_archive_shortcode( $atts, '', 'toocheke-character-archive' ) : '<p>Error: Toocheke Character Archive shortcode method not found.</p>';
        },
        array(
            'term' => array( 'type' => 'string', 'default' => '' ),
        )
    );

    // 15. Toocheke Characters Block
    toocheke_companion_register_block(
        'characters-block',
        function( $attributes ) {
            global $Toocheke_Companion_Comic_Features;
            if ( ! ( $Toocheke_Companion_Comic_Features instanceof Toocheke_Companion_Comic_Features ) ) {
                error_log( 'Toocheke_Companion_Comic_Features instance not available for Characters block render.' );
                return '<p>Error: Comic Features class not available.</p>';
            }
            return method_exists( $Toocheke_Companion_Comic_Features, 'toocheke_characters_shortcode' ) ? $Toocheke_Companion_Comic_Features->toocheke_characters_shortcode( $attributes ) : '<p>Error: Toocheke Characters shortcode method not found.</p>';
        }
    );
}
add_action( 'init', 'toocheke_companion_register_all_blocks' );
