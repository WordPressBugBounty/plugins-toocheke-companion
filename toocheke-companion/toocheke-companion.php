<?php
/*
Plugin Name: Toocheke Companion
Description: Theme specific functions for the Toocheke WordPress theme.
 */

/**
 * Toocheke Companion
 *
 * Plugin Name: Toocheke Companion
 * Plugin URI:  https://wordpress.org/plugins/toocheke-companion/
 * Description: Enables posting of comics on your WordPress website. Specifically with the Toocheke WordPress Theme.
 * Version:     2.2
 * Author:      Leetoo
 * Author URI:  https://leetoo.net
 * License:     GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: toocheke-companion
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 3, as published by the Free Software Foundation, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('TOOCHEKE_COMPANION_VERSION')) {
    define('TOOCHEKE_COMPANION_VERSION', '2.2');
}

/**
 * These files must be loaded before the class below, since PHP requires a
 * trait to already be defined at the point a class's `use` statement is
 * evaluated. Each file defines one trait that groups together methods for
 * a related area of functionality; see the file header in each for details.
 */
require_once __DIR__ . '/inc/class-toocheke-companion-cpt-taxonomy.php';
require_once __DIR__ . '/inc/class-toocheke-companion-settings-page.php';
require_once __DIR__ . '/inc/class-toocheke-companion-metaboxes.php';
require_once __DIR__ . '/inc/class-toocheke-companion-taxonomy-terms.php';
require_once __DIR__ . '/inc/class-toocheke-companion-admin-columns.php';
require_once __DIR__ . '/inc/class-toocheke-companion-shortcodes.php';
require_once __DIR__ . '/inc/class-toocheke-companion-rss-feeds.php';
require_once __DIR__ . '/inc/class-toocheke-companion-likes.php';
require_once __DIR__ . '/inc/class-toocheke-companion-manga-sort-filter.php';
require_once __DIR__ . '/inc/class-toocheke-companion-comic-sort-filter.php';
require_once __DIR__ . '/inc/class-toocheke-companion-quick-bulk-edit.php';
require_once __DIR__ . '/inc/class-toocheke-companion-frontend-display.php';

class Toocheke_Companion_Comic_Features
{
    /**
     * Methods are grouped by area into the traits below; see /inc for each
     * trait's file. This main class only keeps construction, the central
     * init() that wires up all hooks/filters, and the two methods used on
     * the plugin activation hook.
     */
    use Toocheke_Companion_CPT_Taxonomy;
    use Toocheke_Companion_Settings_Page;
    use Toocheke_Companion_Metaboxes;
    use Toocheke_Companion_Taxonomy_Terms;
    use Toocheke_Companion_Admin_Columns;
    use Toocheke_Companion_Shortcodes;
    use Toocheke_Companion_RSS_Feeds;
    use Toocheke_Companion_Likes;
    use Toocheke_Companion_Manga_Sort_Filter;
    use Toocheke_Companion_Comic_Sort_Filter;
    use Toocheke_Companion_Quick_Bulk_Edit;
    use Toocheke_Companion_Frontend_Display;

    public function __construct()
    {
        //
    }

    /**
     * Initialize the class and start calling our hooks and filters
     */
    public function init()
    {
        $theme = wp_get_theme();
        // Actions and Filters
        add_filter('use_block_editor_for_post_type', [$this, 'toocheke_companion_disable_block_editor_for_post_types'], 10, 2);
        add_action('init', [$this, 'toocheke_companion_create_series_custom_post_type'], 0);
        add_action('init', [$this, 'toocheke_companion_create_manga_series_custom_post_type'], 0);
        add_action('init', [$this, 'toocheke_companion_create_manga_volume_custom_post_type'], 0);
        add_action('init', [$this, 'toocheke_companion_create_manga_chapter_custom_post_type'], 0);
        // REST API
        add_action('rest_api_init', [$this, 'toocheke_register_manga_series_rest_route']);

        add_action('init', [$this, 'toocheke_companion_create_comic_custom_post_type'], 0);
        register_activation_hook(__FILE__, [$this, 'toocheke_rewrite_flush']);
        register_activation_hook(__FILE__, [$this, 'toocheke_set_default_options']);

        // Keep the cached comic-id list used by the "all chapters" shortcode
        // (see toocheke_get_chapter_comic_ids() in inc/toocheke-companion-template-functions.php)
        // in sync whenever a comic is added, edited, trashed, restored, or deleted.
        add_action('save_post_comic', 'toocheke_invalidate_chapter_comic_ids_cache');
        add_action('trashed_post', 'toocheke_invalidate_chapter_comic_ids_cache');
        add_action('untrashed_post', 'toocheke_invalidate_chapter_comic_ids_cache');
        add_action('before_delete_post', 'toocheke_invalidate_chapter_comic_ids_cache');

        if (is_admin()) { add_action('admin_menu', [$this, 'toocheke_add_plugin_main_menu'], 0); }
        if (is_admin()) { add_action('admin_head', [$this, 'toocheke_admin_menu_highlighting'], 0); }
        add_filter('custom_menu_order', '__return_true');
        add_filter('menu_order', [$this, 'toocheke_force_menu_order']);
        if (is_admin()) { add_action('admin_head-post-new.php', [$this, 'toocheke_add_all_posts_button'], 0); }
        add_action('init', [$this, 'toocheke_companion_create_taxonomies'], 0);
        if (is_admin()) { add_action('collections_add_form_fields', [$this, 'toocheke_companion_add_collection_image'], 10, 2); }
        add_action('created_collections', [$this, 'toocheke_companion_save_collection_image'], 10, 2);
        if (is_admin()) { add_action('collections_edit_form_fields', [$this, 'toocheke_companion_update_collection_image'], 10, 2); }
        add_action('edited_collections', [$this, 'toocheke_companion_updated_collection_image'], 10, 2);
        if (is_admin()) { add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_collection_load_media']); }
        if (is_admin()) { add_action('admin_footer', [$this, 'toocheke_companion_collection_add_script']); }
        if (is_admin()) { add_filter('manage_edit-series_columns', [$this, 'toocheke_companion_add_series_columns']); }
        if (is_admin()) { add_filter('manage_edit-comic_columns', [$this, 'toocheke_companion_add_comic_columns']); }
        if (is_admin()) { add_filter('manage_posts_custom_column', [$this, 'toocheke_companion_add_comic_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_pages_custom_column', [$this, 'toocheke_companion_add_series_column_content'], 10, 3); }
        if (is_admin()) { add_action('manage_manga_series_posts_custom_column', [$this, 'toocheke_companion_render_manga_series_columns'], 10, 2); }
        if (is_admin()) { add_action('manage_manga_volume_posts_custom_column', [$this, 'toocheke_companion_render_manga_volume_columns'], 10, 2); }
        if (is_admin()) { add_action('manage_manga_chapter_posts_custom_column', [$this, 'toocheke_companion_render_manga_chapter_columns'], 10, 2); }
        if (is_admin()) { add_action('collections_add_form_fields', [$this, 'toocheke_companion_collection_add_order_field'], 10, 2); }
        add_action('created_collections', [$this, 'toocheke_companion_collection_save_order_meta'], 10, 2);
        if (is_admin()) { add_action('collections_edit_form_fields', [$this, 'toocheke_companion_collection_edit_order_field'], 10, 2); }
        add_action('edited_collections', [$this, 'toocheke_companion_collection_update_order_meta'], 10, 2);
        if (is_admin()) { add_filter('manage_edit-collections_columns', [$this, 'toocheke_companion_collection_add_order_column']); }
        if (is_admin()) { add_filter('manage_collections_custom_column', [$this, 'toocheke_companion_add_collection_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_edit-collections_sortable_columns', [$this, 'toocheke_companion_collection_add_order_column_sortable']); }
        add_filter('pre_get_terms', [$this, 'toocheke_companion_collection_sort_by_collection_order']);
        if (is_admin()) { add_filter('manage_edit-collections_columns', [$this, 'toocheke_companion_collection_add_image_column']); }
        if (is_admin()) { add_filter('manage_collections_custom_column', [$this, 'toocheke_companion_add_collection_image_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_edit-collections_sortable_columns', [$this, 'toocheke_companion_collection_add_image_column_sortable']); }
        add_action('init', [$this, 'toocheke_companion_create_collection_page_on_theme_activation']);
        if (is_admin()) { add_action('chapters_add_form_fields', [$this, 'toocheke_companion_add_chapter_image'], 10, 2); }
        add_action('created_chapters', [$this, 'toocheke_companion_save_chapter_image'], 10, 2);
        if (is_admin()) { add_action('chapters_edit_form_fields', [$this, 'toocheke_companion_update_chapter_image'], 10, 2); }
        add_action('edited_chapters', [$this, 'toocheke_companion_updated_chapter_image'], 10, 2);
        if (is_admin()) { add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_chapter_load_media']); }
        if (is_admin()) { add_action('admin_footer', [$this, 'toocheke_companion_chapter_add_script']); }
        if (is_admin()) { add_action('chapters_add_form_fields', [$this, 'toocheke_companion_chapter_add_order_field'], 10, 2); }
        add_action('created_chapters', [$this, 'toocheke_companion_chapter_save_order_meta'], 10, 2);
        if (is_admin()) { add_action('chapters_edit_form_fields', [$this, 'toocheke_companion_chapter_edit_order_field'], 10, 2); }
        add_action('edited_chapters', [$this, 'toocheke_companion_chapter_update_order_meta'], 10, 2);
        if (is_admin()) { add_filter('manage_edit-chapters_columns', [$this, 'toocheke_companion_chapter_add_order_column']); }
        if (is_admin()) { add_filter('manage_chapters_custom_column', [$this, 'toocheke_companion_add_chapter_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_edit-chapters_sortable_columns', [$this, 'toocheke_companion_chapter_add_order_column_sortable']); }
        add_filter('pre_get_terms', [$this, 'toocheke_companion_chapter_sort_by_chapter_order']);
        if (is_admin()) { add_filter('manage_edit-chapters_columns', [$this, 'toocheke_companion_chapter_add_image_column']); }
        if (is_admin()) { add_filter('manage_chapters_custom_column', [$this, 'toocheke_companion_add_chapter_image_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_edit-chapters_sortable_columns', [$this, 'toocheke_companion_chapter_add_image_column_sortable']); }
        add_action('init', [$this, 'toocheke_companion_create_chapter_page_on_theme_activation']);
        add_filter('pre_get_posts', [$this, 'toocheke_companion_add_comics_to_defalt_tax_archive']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_init_option_fields']); }
        add_action('toocheke_get_sharing_buttons', [$this, 'toocheke_add_sharing_icons']);
        add_action('toocheke_get_support_buttons', [$this, 'toocheke_add_support_icons']);
        add_action('after_setup_theme', [$this, 'toocheke_default_image_settings']);
        add_action('publish_post', [$this, 'toocheke_update_comic_post_numbers'], 11);
        add_action('deleted_post', [$this, 'toocheke_update_comic_post_numbers']);
        add_action('edit_post', [$this, 'toocheke_update_comic_post_numbers']);
        add_action('save_post', [$this, 'toocheke_desktop_comic_editor_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_desktop_comic_editor_meta_box']); }
        add_action('save_post', [$this, 'toocheke_comic_blog_post_editor_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_comic_blog_post_editor_meta_box']); }
        /* Alt hover text  metabox */
        add_action('save_post', [$this, 'toocheke_comic_alt_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_comic_alt_meta_box']); }
        /* Transcript  metabox */
        add_action('save_post', [$this, 'toocheke_comic_transcript_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_comic_transcript_meta_box']); }

        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_audio_meta_box']); }
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_add_comic_series_meta_box']); }
        // Actions
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_comicscout_image_add_metabox'], 11); }
        add_action('save_post_comic', [$this, 'toocheke_comicscout_image_save']);
        add_action('save_post_manga_chapter', [$this, 'toocheke_comicscout_image_save']);

        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_comicscout_social_share_image_add_metabox'], 12); }
        add_action('save_post_comic', [$this, 'toocheke_comicscout_social_share_image_save']);
        add_action('save_post_manga_chapter', [$this, 'toocheke_comicscout_social_share_image_save']);
        add_action('save_post', [$this, 'toocheke_comic_audio_save_postdata']);
        if (is_admin()) { add_action('post_edit_form_tag', [$this, 'toocheke_update_edit_form']); }
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_replace_term_description_field']); }
        if (is_admin()) { add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_character_load_media']); }
        if (is_admin()) { add_action('admin_footer', [$this, 'toocheke_companion_character_add_script']); }
        add_action('init', [$this, 'toocheke_companion_create_character_page_on_theme_activation']);
        if (is_admin()) { add_action('comic_characters_add_form_fields', [$this, 'toocheke_companion_add_character_image'], 10, 2); }
        add_action('created_comic_characters', [$this, 'toocheke_companion_save_character_image'], 10, 2);
        if (is_admin()) { add_action('comic_characters_edit_form_fields', [$this, 'toocheke_companion_update_character_image'], 10, 2); }
        add_action('edited_comic_characters', [$this, 'toocheke_companion_updated_character_image'], 10, 2);
        if (is_admin()) { add_filter('manage_edit-comic_characters_columns', [$this, 'toocheke_companion_character_add_image_column']); }
        if (is_admin()) { add_filter('manage_comic_characters_custom_column', [$this, 'toocheke_companion_add_character_image_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_edit-comic_characters_sortable_columns', [$this, 'toocheke_companion_character_add_image_column_sortable']); }
        if (is_admin()) { add_action('comic_characters_add_form_fields', [$this, 'toocheke_companion_character_add_order_field'], 10, 2); }
        if (is_admin()) { add_action('comic_characters_edit_form_fields', [$this, 'toocheke_companion_character_edit_order_field'], 10, 2); }
        add_action('edited_comic_characters', [$this, 'toocheke_companion_character_update_order_meta'], 10, 2);
        if (is_admin()) { add_filter('manage_edit-comic_characters_columns', [$this, 'toocheke_companion_character_add_order_column']); }
        add_action('created_comic_characters', [$this, 'toocheke_companion_character_save_order_meta'], 10, 2);
        if (is_admin()) { add_filter('manage_comic_characters_custom_column', [$this, 'toocheke_companion_add_character_order_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_edit-comic_characters_sortable_columns', [$this, 'toocheke_companion_character_add_order_column_sortable']); }
        add_filter('pre_get_terms', [$this, 'toocheke_companion_character_sort_by_character_order']);
        if (is_admin()) { add_action('genres_add_form_fields', [$this, 'toocheke_companion_add_genre_image'], 10, 2); }
        if (is_admin()) { add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_genre_load_media']); }
        add_action('created_genres', [$this, 'toocheke_companion_save_genre_image'], 10, 2);
        if (is_admin()) { add_action('genres_edit_form_fields', [$this, 'toocheke_companion_update_genre_image'], 10, 2); }
        add_action('edited_genres', [$this, 'toocheke_companion_updated_genre_image'], 10, 2);
        if (is_admin()) { add_action('admin_footer', [$this, 'toocheke_companion_genre_add_script']); }
        if (is_admin()) { add_filter('manage_edit-genres_columns', [$this, 'toocheke_companion_genre_add_image_column']); }
        if (is_admin()) { add_filter('manage_genres_custom_column', [$this, 'toocheke_companion_add_genre_image_column_content'], 10, 3); }
        if (is_admin()) { add_filter('manage_edit-genres_sortable_columns', [$this, 'toocheke_companion_genre_add_image_column_sortable']); }
        add_action('init', [$this, 'toocheke_companion_create_genre_page_on_theme_activation']);
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_move_comic_featured_image_metabox'], 10); }
        add_action('delete_post', [$this, 'toocheke_delete_series_comics']);
        add_filter('excerpt_length', [$this, 'toocheke_excerpt_length'], 999);
        add_filter('excerpt_length', [$this, 'toocheke_universal_excerpt_length'], 999);
        if (is_admin()) { add_action('admin_print_styles', [$this, 'toocheke_admin_styles_and_scripts']); }
        add_action('wp_enqueue_scripts', [$this, 'toocheke_frontend_styles_and_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'toocheke_enqueue_reader_libraries']);
        add_filter('comment_post_redirect', [$this, 'toocheke_redirect_comments'], 10, 2);
        add_action('comment_form_logged_in', [$this, 'toocheke_add_logged_in_fields']);
        add_filter('the_content', [$this, 'toocheke_remove_autop_for_comic']);
        add_action('init', [$this, 'toocheke_make_post_parent_public']);
        if (is_admin()) { add_action('quick_edit_custom_box', [$this, 'toocheke_quick_edit_fields'], 10, 2); }
        add_action('save_post', [$this, 'toocheke_quick_edit_save']);
        if (is_admin()) { add_action('bulk_edit_custom_box', [$this, 'toocheke_quick_edit_fields'], 10, 2); }
        if (is_admin()) { add_action('wp_ajax_toocheke_companion_save_bulk', [$this, 'toocheke_save_bulk_edit_hook']); }
        add_action('wp_footer', [$this, 'toocheke_verify_age_popup']);
        add_action('template_redirect', [$this, 'toocheke_enqueue_age_verification_assets']);
        if (is_admin()) { add_action('wp_ajax_toocheke_set_age_verification_cookie', [$this, 'toocheke_set_age_verification_cookie']); }
        if (is_admin()) { add_action('wp_ajax_nopriv_toocheke_set_age_verification_cookie', [$this, 'toocheke_set_age_verification_cookie']); }
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_remove_image_link'], 10); }
        add_filter('the_content', [$this, 'toocheke_attachment_image_link_remove_filter']);
        add_filter('the_content', [$this, 'toocheke_add_comic_hovertext_to_content'], 999);
        add_filter('get_post_metadata', [$this, 'toocheke_add_hovertext_to_desktop_comic_editor_meta'], 10, 4);

        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_move_series_featured_image_metabox'], 10); }
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_series_hero_image_add_metabox'], 11); }
        add_action('save_post_series', [$this, 'toocheke_series_hero_image_save']);
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_series_mobile_hero_image_add_metabox'], 12); }
        add_action('save_post_series', [$this, 'toocheke_series_mobile_hero_image_save']);
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_series_bg_image_add_metabox'], 13); }
        add_action('save_post_series', [$this, 'toocheke_series_bg_image_save']);
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_series_bg_color_add_metabox'], 14); }
        add_action('save_post_series', [$this, 'toocheke_series_bg_color_save']);
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_series_sidebar_content_meta_box'], 15); }
        add_action('save_post_series', [$this, 'toocheke_series_sidebar_content_save_postdata']);
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_series_comic_order_add_metabox'], 16); }
        add_action('save_post_series', [$this, 'toocheke_series_comic_order_save']);

        add_filter('pre_get_posts', [$this, 'toocheke_companion_comics_sort']);

        //filter for comics
        if (is_admin()) { add_action('restrict_manage_posts', [$this, 'toocheke_comic_filter_dropdown']); }
        add_filter('pre_get_posts', [$this, 'toocheke_comic_filter_query']);
        /* patreon functions */

        // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (is_plugin_active('patreon-connect/patreon.php')) {
            if (is_admin()) { add_filter('manage_edit-comic_columns', [$this, 'toocheke_companion_add_patreon_level_column']); }
            if (is_admin()) { add_filter('manage_edit-manga_series_columns', [$this, 'toocheke_companion_add_patreon_level_column']); }
            if (is_admin()) { add_filter('manage_edit-manga_volume_columns', [$this, 'toocheke_companion_add_patreon_level_column']); }
            if (is_admin()) { add_filter('manage_edit-manga_chapter_columns', [$this, 'toocheke_companion_add_patreon_level_column']); }
            add_filter('parse_query', [$this, 'toocheke_filter_patreon_levels']);
        }

        //add_filter('post_type_link', array($this, 'toocheke_rewrite_series_comic_permalink'), 10, 3);
        /* Like functions */
        if (is_admin()) { add_action('wp_ajax_nopriv_toocheke_process_like', [$this, 'toocheke_process_like']); }
        if (is_admin()) { add_action('wp_ajax_toocheke_process_like', [$this, 'toocheke_process_like']); }
        if (is_admin()) { add_action('show_user_profile', [$this, 'toocheke_show_user_likes']); }
        if (is_admin()) { add_action('edit_user_profile', [$this, 'toocheke_show_user_likes']); }
        add_shortcode('toocheke-like-button', [$this, 'toocheke_like_short_code']);
        if (is_admin()) { add_filter('manage_edit-comic_sortable_columns', [$this, 'toocheke_comic_sortable_columns']); }

        /* Bilingual metaboxes */
        add_action('save_post', [$this, 'toocheke_comic_title_2nd_language_display_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_comic_title_2nd_language_meta_box']); }
        add_action('save_post', [$this, 'toocheke_2nd_language_mobile_comic_editor_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_2nd_language_mobile_comic_editor_meta_box']); }
        add_action('save_post', [$this, 'toocheke_2nd_language_desktop_comic_editor_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_2nd_language_desktop_comic_editor_meta_box']); }
        add_action('save_post', [$this, 'toocheke_2nd_language_comic_blog_post_editor_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_2nd_language_comic_blog_post_editor_meta_box']); }

        /* Default (per-user, until they customize Screen Options) visibility for the
           bilingual/dual-layout metaboxes above, driven by the site-wide options. */
        if (is_admin()) { add_filter('default_hidden_meta_boxes', [$this, 'toocheke_bilingual_layout_default_hidden_meta_boxes'], 10, 2); }

        /* Age metaboxes */
        add_action('save_post', [$this, 'toocheke_age_verification_save_postdata']);
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_age_verification_meta_box'], 99); }

        /* Add bookmark nav */
        add_filter('wp_nav_menu_items', [$this, 'toocheke_add_bookmark_nav_item'], 10, 2);
        /* Universal shortcodes*/
        add_action('init', [$this, 'toocheke_register_universal_shortcodes']);
        /*Universal template*/
        add_filter('single_template', [$this, 'toocheke_single_comic_template']);
        add_filter('single_template', [$this, 'toocheke_single_manga_templates']);
        add_action('generate_rewrite_rules', [$this, 'toocheke_universal_rewrite_rules']);
        add_filter('archive_template', [$this, 'toocheke_comic_archive_template'], 9999);

        /* Page View Count */
        add_action('wp_enqueue_scripts', [$this, 'toocheke_universal_set_post_views']);
        if (is_admin()) { add_action('wp_ajax_toocheke_record_post_view', [$this, 'toocheke_ajax_record_post_view']); }
        if (is_admin()) { add_action('wp_ajax_nopriv_toocheke_record_post_view', [$this, 'toocheke_ajax_record_post_view']); }
        // Remove issues with prefetching adding extra views
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);

        /*Toocheke Premium */
        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
            add_action('init', [$this, 'toocheke_companion_create_original_art_page_on_theme_activation']);
            add_action('init', [$this, 'toocheke_companion_create_print_page_on_theme_activation']);
        }
        if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
            add_action('init', [$this, 'toocheke_random_add_rewrite']);
            add_action('template_redirect', [$this, 'toocheke_random_template']);
        }
        add_filter('posts_search', [$this, 'toocheke_extend_search'], 10, 2);

        //RSS
        add_filter('request', [$this, 'toocheke_feed_request']);
        add_filter('pre_get_posts', [$this, 'toocheke_feed_post_status']);
        add_filter('the_excerpt_rss', [$this, 'toocheke_add_metadata_to_rss']);
        add_filter('the_content_feed', [$this, 'toocheke_add_metadata_to_rss']);
        add_action('rss2_item',  [$this, 'toocheke_add_comic_images_to_rss']);
        add_action('rss2_ns', [$this, 'toocheke_add_rss_namespaces']);
        add_filter('the_permalink_rss', [$this, 'toocheke_add_series_id_to_rss_permalink']);
        //SERIES RSS
        add_action('init', [$this, 'toocheke_series_feed_rewrite_rule']);
        add_filter('query_vars', [$this, 'toocheke_series_feed_query_vars']);
        add_action('template_redirect', [$this, 'toocheke_series_feed_redirect']);

        //MANGA SERIES RSS
        add_action('init', [$this, 'toocheke_manga_series_feed_rewrite_rule']);
        add_filter('query_vars', [$this, 'toocheke_manga_series_feed_query_vars']);
        add_action('template_redirect', [$this, 'toocheke_manga_series_feed_redirect']);

        //Manga functions
        if (is_admin()) { add_filter('manage_edit-manga_series_columns', [$this, 'toocheke_companion_add_manga_series_columns']); }
        if (is_admin()) { add_filter('manage_edit-manga_volume_columns', [$this, 'toocheke_companion_add_manga_volume_columns']); }
        if (is_admin()) { add_filter('manage_edit-manga_chapter_columns', [$this, 'toocheke_companion_add_manga_chapter_columns']); }

        //metaboxes
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_manga_series_meta_boxes']); }
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_add_manga_hero_metaboxes']); }
        add_action('save_post_manga_series', [$this, 'toocheke_manga_series_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_manga_volume_meta_boxes']); }
        add_action('save_post_manga_volume', [$this, 'toocheke_manga_volume_save_postdata']);
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_manga_chapter_meta_boxes']); }
        add_action('save_post_manga_chapter', [$this, 'toocheke_manga_chapter_save_postdata']);
        add_action('save_post', [$this, 'toocheke_save_manga_hero_images']);

        add_filter('pre_get_posts', [$this, 'toocheke_manga_filters']);
        if (is_admin()) { add_action('do_meta_boxes', [$this, 'toocheke_manga_reorder_metaboxes']); }

        //Manga body class
        add_filter('body_class', [$this, 'toocheke_add_manga_reader_body_class']);

        //Manga sortable columns
        add_action('pre_get_posts', [$this, 'toocheke_manga_series_sort']);
        add_action('pre_get_posts', [$this, 'toocheke_manga_volume_sort']);
        add_action('pre_get_posts', [$this, 'toocheke_manga_chapter_sort']);
        if (is_admin()) { add_filter('manage_edit-manga_series_sortable_columns', [$this, 'toocheke_manga_series_sortable_columns']); }
        if (is_admin()) { add_filter('manage_edit-manga_volume_sortable_columns', [$this, 'toocheke_manga_volume_sortable_columns']); }
        if (is_admin()) { add_filter('manage_edit-manga_chapter_sortable_columns', [$this, 'toocheke_manga_chapter_sortable_columns']); }

        //Manga RSS
        add_action('template_redirect', [$this, 'toocheke_block_manga_chapter_archive']);

        //Upgrade functions
        if (is_admin()) { add_action('admin_init', [$this, 'toocheke_companion_upgrade_check']); }

        //Premium metaboxes
        if (is_admin()) { add_action('add_meta_boxes', [$this, 'toocheke_add_buy_comic_metaboxes']); }
        add_action('save_post_comic', [$this, 'toocheke_save_comic_pricing_metabox']);

         //Manga filters
        if (is_admin()) { add_action('restrict_manage_posts', [$this, 'toocheke_manga_filter_dropdowns']); }
        add_filter('pre_get_posts', [$this, 'toocheke_manga_filter_query']);
        // Manga admin AJAX
        if (is_admin()) { add_action('wp_ajax_toocheke_get_volumes_by_series', [$this, 'toocheke_get_volumes_by_series']); }

        //Admin Dismiss
        if (is_admin()) { add_action('wp_ajax_toocheke_dismiss_notice', [$this, 'toocheke_dismiss_notice_handler']); }
    }

    /* Set default options */
    public function toocheke_set_default_options(){
        if (get_option('toocheke-global-buy-comic') === false) {
            add_option('toocheke-global-buy-comic', 1);
        }
    }

    /* Rewrite Functions */
    public function toocheke_rewrite_flush()
    {
        // First, we "add" the custom post type via the above written function.
        // Note: "add" is written with quotes, as CPTs don't get added to the DB,
        // They are only referenced in the post_type column with a post entry,
        // when you add a post of this CPT.
        $this->toocheke_companion_create_series_custom_post_type();
        $this->toocheke_companion_create_comic_custom_post_type();
        $this->toocheke_companion_create_manga_series_custom_post_type();
        $this->toocheke_companion_create_manga_volume_custom_post_type();
        $this->toocheke_companion_create_manga_chapter_custom_post_type();

        // ATTENTION: This is *only* done during plugin activation hook in this example!
        // You should *NEVER EVER* do this on every page load!!
        flush_rewrite_rules();
    }

}

/**
 * Loads the remaining files that don't need to be available before the
 * class above is declared: the front-end template loader, the template
 * helper functions used by theme/plugin templates, and the image
 * protection/optimization classes (each only instantiated if its
 * corresponding option is enabled).
 */
define('TOOCHEKE_COMPANION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TOOCHEKE_COMPANION_PLUGIN_URL', plugin_dir_url(__FILE__));
if (! class_exists('Gamajo_Template_Loader')) {
    require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/class-gamajo-template-loader.php';
}
require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/class-toocheke-companion-template-loader.php';
require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/toocheke-companion-template-functions.php';
require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/class-toocheke-image-access-protection.php';
require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/class-toocheke-image-optimization.php';
require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/class-toocheke-future-comic-image-protection.php';

$Toocheke_Companion_Comic_Features = new Toocheke_Companion_Comic_Features();
$Toocheke_Companion_Comic_Features->init();

// Only initialize image protection if the option is enabled
if (get_option('toocheke-image-protection') == 1) {
    Toocheke_Image_Access_Protection::get_instance();
}
if (get_option('toocheke-future-post-image-protection') == 1) {
    Toocheke_Future_Comic_Image_Protection::get_instance();
}
// Initialize the optimizer when the option is active.
if ( get_option( 'toocheke-image-optimization' ) ) {
    new Toocheke_Image_Optimization();
}

require_once TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/toocheke-companion-blocks.php';
