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
 * Version:     1.184
 * Author:      Leetoo
 * Author URI:  https://leetoo.net
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: toocheke-companion
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('TOOCHEKE_COMPANION_VERSION')) {
    define('TOOCHEKE_COMPANION_VERSION', '1.184');
}
class Toocheke_Companion_Comic_Features
{

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

        add_action('init', [$this, 'toocheke_companion_create_comic_custom_post_type'], 0);
        register_activation_hook(__FILE__, [$this, 'toocheke_rewrite_flush']);
        add_action('admin_menu', [$this, 'toocheke_add_plugin_main_menu'], 0);
        add_action('admin_head', [$this, 'toocheke_manga_menu_highlighting'], 0);
        add_action('admin_head-post-new.php', [$this, 'toocheke_add_all_posts_button'], 0);
        add_action('init', [$this, 'toocheke_companion_create_taxonomies'], 0);
        add_action('collections_add_form_fields', [$this, 'toocheke_companion_add_collection_image'], 10, 2);
        add_action('created_collections', [$this, 'toocheke_companion_save_collection_image'], 10, 2);
        add_action('collections_edit_form_fields', [$this, 'toocheke_companion_update_collection_image'], 10, 2);
        add_action('edited_collections', [$this, 'toocheke_companion_updated_collection_image'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_collection_load_media']);
        add_action('admin_footer', [$this, 'toocheke_companion_collection_add_script']);
        add_filter('manage_edit-series_columns', [$this, 'toocheke_companion_add_series_columns']);
        add_filter('manage_edit-comic_columns', [$this, 'toocheke_companion_add_comic_columns']);
        add_filter('manage_posts_custom_column', [$this, 'toocheke_companion_add_comic_column_content'], 10, 3);
        add_filter('manage_pages_custom_column', [$this, 'toocheke_companion_add_series_column_content'], 10, 3);
        add_action('manage_manga_series_posts_custom_column', [$this, 'toocheke_companion_render_manga_series_columns'], 10, 2);
        add_action('manage_manga_volume_posts_custom_column', [$this, 'toocheke_companion_render_manga_volume_columns'], 10, 2);
        add_action('manage_manga_chapter_posts_custom_column', [$this, 'toocheke_companion_render_manga_chapter_columns'], 10, 2);
        add_action('collections_add_form_fields', [$this, 'toocheke_companion_collection_add_order_field'], 10, 2);
        add_action('created_collections', [$this, 'toocheke_companion_collection_save_order_meta'], 10, 2);
        add_action('collections_edit_form_fields', [$this, 'toocheke_companion_collection_edit_order_field'], 10, 2);
        add_action('edited_collections', [$this, 'toocheke_companion_collection_update_order_meta'], 10, 2);
        add_filter('manage_edit-collections_columns', [$this, 'toocheke_companion_collection_add_order_column']);
        add_filter('manage_collections_custom_column', [$this, 'toocheke_companion_add_collection_column_content'], 10, 3);
        add_filter('manage_edit-collections_sortable_columns', [$this, 'toocheke_companion_collection_add_order_column_sortable']);
        add_filter('pre_get_terms', [$this, 'toocheke_companion_collection_sort_by_collection_order']);
        add_filter('manage_edit-collections_columns', [$this, 'toocheke_companion_collection_add_image_column']);
        add_filter('manage_collections_custom_column', [$this, 'toocheke_companion_add_collection_image_column_content'], 10, 3);
        add_filter('manage_edit-collections_sortable_columns', [$this, 'toocheke_companion_collection_add_image_column_sortable']);
        add_action('init', [$this, 'toocheke_companion_create_collection_page_on_theme_activation']);
        add_action('chapters_add_form_fields', [$this, 'toocheke_companion_add_chapter_image'], 10, 2);
        add_action('created_chapters', [$this, 'toocheke_companion_save_chapter_image'], 10, 2);
        add_action('chapters_edit_form_fields', [$this, 'toocheke_companion_update_chapter_image'], 10, 2);
        add_action('edited_chapters', [$this, 'toocheke_companion_updated_chapter_image'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_chapter_load_media']);
        add_action('admin_footer', [$this, 'toocheke_companion_chapter_add_script']);
        add_action('chapters_add_form_fields', [$this, 'toocheke_companion_chapter_add_order_field'], 10, 2);
        add_action('created_chapters', [$this, 'toocheke_companion_chapter_save_order_meta'], 10, 2);
        add_action('chapters_edit_form_fields', [$this, 'toocheke_companion_chapter_edit_order_field'], 10, 2);
        add_action('edited_chapters', [$this, 'toocheke_companion_chapter_update_order_meta'], 10, 2);
        add_filter('manage_edit-chapters_columns', [$this, 'toocheke_companion_chapter_add_order_column']);
        add_filter('manage_chapters_custom_column', [$this, 'toocheke_companion_add_chapter_column_content'], 10, 3);
        add_filter('manage_edit-chapters_sortable_columns', [$this, 'toocheke_companion_chapter_add_order_column_sortable']);
        add_filter('pre_get_terms', [$this, 'toocheke_companion_chapter_sort_by_chapter_order']);
        add_filter('manage_edit-chapters_columns', [$this, 'toocheke_companion_chapter_add_image_column']);
        add_filter('manage_chapters_custom_column', [$this, 'toocheke_companion_add_chapter_image_column_content'], 10, 3);
        add_filter('manage_edit-chapters_sortable_columns', [$this, 'toocheke_companion_chapter_add_image_column_sortable']);
        add_action('init', [$this, 'toocheke_companion_create_chapter_page_on_theme_activation']);
        add_filter('pre_get_posts', [$this, 'toocheke_companion_add_comics_to_defalt_tax_archive']);
        add_action('admin_init', [$this, 'toocheke_init_option_fields']);
        add_action('toocheke_get_sharing_buttons', [$this, 'toocheke_add_sharing_icons']);
        add_action('toocheke_get_support_buttons', [$this, 'toocheke_add_support_icons']);
        add_action('after_setup_theme', [$this, 'toocheke_default_image_settings']);
        add_action('publish_post', [$this, 'toocheke_update_comic_post_numbers'], 11);
        add_action('deleted_post', [$this, 'toocheke_update_comic_post_numbers']);
        add_action('edit_post', [$this, 'toocheke_update_comic_post_numbers']);
        add_action('save_post', [$this, 'toocheke_desktop_comic_editor_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_desktop_comic_editor_meta_box']);
        add_action('save_post', [$this, 'toocheke_comic_blog_post_editor_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_comic_blog_post_editor_meta_box']);
        /* Alt hover text  metabox */
        add_action('save_post', [$this, 'toocheke_comic_alt_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_comic_alt_meta_box']);
        /* Transcript  metabox */
        add_action('save_post', [$this, 'toocheke_comic_transcript_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_comic_transcript_meta_box']);

        add_action('admin_init', [$this, 'toocheke_audio_meta_box']);
        add_action('admin_init', [$this, 'toocheke_add_comic_series_meta_box']);
        add_action('save_post', [$this, 'toocheke_comic_audio_save_postdata']);
        add_action('post_edit_form_tag', [$this, 'toocheke_update_edit_form']);
        add_action('admin_init', [$this, 'toocheke_replace_term_description_field']);
        add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_character_load_media']);
        add_action('admin_footer', [$this, 'toocheke_companion_character_add_script']);
        add_action('init', [$this, 'toocheke_companion_create_character_page_on_theme_activation']);
        add_action('comic_characters_add_form_fields', [$this, 'toocheke_companion_add_character_image'], 10, 2);
        add_action('created_comic_characters', [$this, 'toocheke_companion_save_character_image'], 10, 2);
        add_action('comic_characters_edit_form_fields', [$this, 'toocheke_companion_update_character_image'], 10, 2);
        add_action('edited_comic_characters', [$this, 'toocheke_companion_updated_character_image'], 10, 2);
        add_filter('manage_edit-comic_characters_columns', [$this, 'toocheke_companion_character_add_image_column']);
        add_filter('manage_comic_characters_custom_column', [$this, 'toocheke_companion_add_character_image_column_content'], 10, 3);
        add_filter('manage_edit-comic_characters_sortable_columns', [$this, 'toocheke_companion_character_add_image_column_sortable']);
        add_action('comic_characters_add_form_fields', [$this, 'toocheke_companion_character_add_order_field'], 10, 2);
        add_action('comic_characters_edit_form_fields', [$this, 'toocheke_companion_character_edit_order_field'], 10, 2);
        add_action('edited_comic_characters', [$this, 'toocheke_companion_character_update_order_meta'], 10, 2);
        add_filter('manage_edit-comic_characters_columns', [$this, 'toocheke_companion_character_add_order_column']);
        add_action('created_comic_characters', [$this, 'toocheke_companion_character_save_order_meta'], 10, 2);
        add_filter('manage_comic_characters_custom_column', [$this, 'toocheke_companion_add_character_order_column_content'], 10, 3);
        add_filter('manage_edit-comic_characters_sortable_columns', [$this, 'toocheke_companion_character_add_order_column_sortable']);
        add_filter('pre_get_terms', [$this, 'toocheke_companion_character_sort_by_character_order']);
        add_action('genres_add_form_fields', [$this, 'toocheke_companion_add_genre_image'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'toocheke_companion_genre_load_media']);
        add_action('created_genres', [$this, 'toocheke_companion_save_genre_image'], 10, 2);
        add_action('genres_edit_form_fields', [$this, 'toocheke_companion_update_genre_image'], 10, 2);
        add_action('edited_genres', [$this, 'toocheke_companion_updated_genre_image'], 10, 2);
        add_action('admin_footer', [$this, 'toocheke_companion_genre_add_script']);
        add_filter('manage_edit-genres_columns', [$this, 'toocheke_companion_genre_add_image_column']);
        add_filter('manage_genres_custom_column', [$this, 'toocheke_companion_add_genre_image_column_content'], 10, 3);
        add_filter('manage_edit-genres_sortable_columns', [$this, 'toocheke_companion_genre_add_image_column_sortable']);
        add_action('init', [$this, 'toocheke_companion_create_genre_page_on_theme_activation']);
        add_action('do_meta_boxes', [$this, 'toocheke_move_comic_featured_image_metabox']);
        add_action('delete_post', [$this, 'toocheke_delete_series_comics']);
        add_filter('excerpt_length', [$this, 'toocheke_excerpt_length'], 999);
        add_filter('excerpt_length', [$this, 'toocheke_universal_excerpt_length'], 999);
        add_action('admin_print_styles', [$this, 'toocheke_admin_styles_and_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'toocheke_frontend_styles_and_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'toocheke_enqueue_reader_libraries']);
        add_filter('comment_post_redirect', [$this, 'toocheke_redirect_comments'], 10, 2);
        add_action('comment_form_logged_in', [$this, 'toocheke_add_logged_in_fields']);
        add_filter('the_content', [$this, 'toocheke_remove_autop_for_comic']);
        add_action('init', [$this, 'toocheke_make_post_parent_public']);
        add_action('quick_edit_custom_box', [$this, 'toocheke_quick_edit_fields'], 10, 2);
        add_action('save_post', [$this, 'toocheke_quick_edit_save']);
        add_action('bulk_edit_custom_box', [$this, 'toocheke_quick_edit_fields'], 10, 2);
        add_action('wp_ajax_toocheke_companion_save_bulk', [$this, 'toocheke_save_bulk_edit_hook']);
        add_action('wp_footer', [$this, 'toocheke_verify_age_popup']);
        add_action('template_redirect', [$this, 'toocheke_enqueue_age_verification_assets']);
        add_action('wp_ajax_toocheke_set_age_verification_cookie', [$this, 'toocheke_set_age_verification_cookie']);
        add_action('wp_ajax_nopriv_toocheke_set_age_verification_cookie', [$this, 'toocheke_set_age_verification_cookie']);
        add_action('admin_init', [$this, 'toocheke_remove_image_link'], 10);
        add_filter('the_content', [$this, 'toocheke_attachment_image_link_remove_filter']);
        add_filter('the_content', [$this, 'toocheke_add_comic_hovertext_to_content'], 999);
        add_filter('get_post_metadata', [$this, 'toocheke_add_hovertext_to_desktop_comic_editor_meta'], 10, 4);

        add_action('do_meta_boxes', [$this, 'toocheke_move_series_featured_image_metabox']);
        add_action('admin_init', [$this, 'toocheke_series_hero_image_add_metabox']);
        add_action('save_post', [$this, 'toocheke_series_hero_image_save']);
        add_action('admin_init', [$this, 'toocheke_series_mobile_hero_image_add_metabox']);
        add_action('save_post', [$this, 'toocheke_series_mobile_hero_image_save']);
        add_action('admin_init', [$this, 'toocheke_series_bg_image_add_metabox']);
        add_action('save_post', [$this, 'toocheke_series_bg_image_save']);
        add_action('admin_init', [$this, 'toocheke_series_bg_color_add_metabox']);
        add_action('save_post', [$this, 'toocheke_series_bg_color_save']);
        add_action('admin_init', [$this, 'toocheke_series_sidebar_content_meta_box']);
        add_action('save_post', [$this, 'toocheke_series_sidebar_content_save_postdata']);

        add_filter('pre_get_posts', [$this, 'toocheke_companion_comics_sort']);
        /* patreon functions */

        // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (is_plugin_active('patreon-connect/patreon.php')) {
            add_filter('manage_edit-comic_columns', [$this, 'toocheke_companion_add_patreon_level_column']);
            add_filter('manage_edit-manga_series_columns', [$this, 'toocheke_companion_add_patreon_level_column']);
            add_filter('manage_edit-manga_volume_columns', [$this, 'toocheke_companion_add_patreon_level_column']);
            add_filter('manage_edit-manga_chapter_columns', [$this, 'toocheke_companion_add_patreon_level_column']);
            add_filter('parse_query', [$this, 'toocheke_filter_patreon_levels']);
        }

        //add_filter('post_type_link', array($this, 'toocheke_rewrite_series_comic_permalink'), 10, 3);
        /* Like functions */
        add_action('wp_ajax_nopriv_toocheke_process_like', [$this, 'toocheke_process_like']);
        add_action('wp_ajax_toocheke_process_like', [$this, 'toocheke_process_like']);
        add_action('show_user_profile', [$this, 'toocheke_show_user_likes']);
        add_action('edit_user_profile', [$this, 'toocheke_show_user_likes']);
        add_shortcode('toocheke-like-button', [$this, 'toocheke_like_short_code']);
        add_filter('manage_edit-comic_sortable_columns', [$this, 'toocheke_comic_sortable_columns']);

        /* Bilingual metaboxes */
        add_action('save_post', [$this, 'toocheke_comic_title_2nd_language_display_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_comic_title_2nd_language_meta_box']);
        add_action('save_post', [$this, 'toocheke_2nd_language_mobile_comic_editor_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_2nd_language_mobile_comic_editor_meta_box']);
        add_action('save_post', [$this, 'toocheke_2nd_language_desktop_comic_editor_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_2nd_language_desktop_comic_editor_meta_box']);
        add_action('save_post', [$this, 'toocheke_2nd_language_comic_blog_post_editor_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_2nd_language_comic_blog_post_editor_meta_box']);

        /* Age metaboxes */
        add_action('save_post', [$this, 'toocheke_age_verification_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_age_verification_meta_box']);

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
        add_filter('template_redirect', [$this, 'toocheke_universal_set_post_views']);
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

        //Manga functions
        add_filter('manage_edit-manga_series_columns', [$this, 'toocheke_companion_add_manga_series_columns']);
        add_filter('manage_edit-manga_volume_columns', [$this, 'toocheke_companion_add_manga_volume_columns']);
        add_filter('manage_edit-manga_chapter_columns', [$this, 'toocheke_companion_add_manga_chapter_columns']);

        //metaboxes
        add_action('admin_init', [$this, 'toocheke_manga_series_meta_boxes']);
        add_action('admin_init', [$this, 'toocheke_add_manga_hero_metaboxes']);
        add_action('save_post_manga_series', [$this, 'toocheke_manga_series_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_manga_volume_meta_boxes']);
        add_action('save_post_manga_volume', [$this, 'toocheke_manga_volume_save_postdata']);
        add_action('admin_init', [$this, 'toocheke_manga_chapter_meta_boxes']);
        add_action('save_post_manga_chapter', [$this, 'toocheke_manga_chapter_save_postdata']);
        add_action('save_post', [$this, 'toocheke_save_manga_hero_images']);

        add_filter('pre_get_posts', [$this, 'toocheke_manga_filters']);
        add_action('do_meta_boxes', [$this, 'toocheke_manga_reorder_metaboxes']);

        //Manga body class
        add_filter('body_class', [$this, 'toocheke_add_manga_reader_body_class']);

        //Manga sortable columns
        add_action('pre_get_posts', [$this, 'toocheke_manga_series_sort']);
        add_action('pre_get_posts', [$this, 'toocheke_manga_volume_sort']);
        add_action('pre_get_posts', [$this, 'toocheke_manga_chapter_sort']);
        add_filter('manage_edit-manga_series_sortable_columns', [$this, 'toocheke_manga_series_sortable_columns']);
        add_filter('manage_edit-manga_volume_sortable_columns', [$this, 'toocheke_manga_volume_sortable_columns']);
        add_filter('manage_edit-manga_chapter_sortable_columns', [$this, 'toocheke_manga_chapter_sortable_columns']);
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
    /* Series CPT Functions */
    public function toocheke_companion_create_series_custom_post_type()
    {

        // Set UI labels for Custom Post Type
        $labels = [
            'name'               => _x('Series', 'Post Type General Name', 'toocheke-companion'),
            'singular_name'      => _x('Series', 'Post Type Singular Name', 'toocheke-companion'),
            'menu_name'          => __('Series', 'toocheke-companion'),
            'parent_item_colon'  => __('Parent Series', 'toocheke-companion'),
            'all_items'          => __('All Series', 'toocheke-companion'),
            'view_item'          => __('View Series', 'toocheke-companion'),
            'add_new_item'       => __('Add New Series', 'toocheke-companion'),
            'add_new'            => __('Add New', 'toocheke-companion'),
            'edit_item'          => __('Edit Series', 'toocheke-companion'),
            'update_item'        => __('Update Series', 'toocheke-companion'),
            'search_items'       => __('Search Series', 'toocheke-companion'),
            'not_found'          => __('No Series found', 'toocheke-companion'),
            'not_found_in_trash' => __('No Series found in Trash', 'toocheke-companion'),
        ];

        // Set other options for Custom Post Type

        $args = [
            'label'               => __('series', 'toocheke-companion'),
            'description'         => __('Series posts', 'toocheke-companion'),
            'labels'              => $labels,
            // Features this CPT supports in Post Editor
            'supports'            => ['title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'trackbacks', 'shortlinks'],
            'taxonomies'          => ['chapters', 'series-tag'],
            'hierarchical'        => true,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => true,
            'menu_position'       => 2,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-toocheke-companion',
        ];

        // Registering your Custom Post Type
        register_post_type('series', $args);
    }

    /* Comic CPT Functions */
    public function toocheke_companion_create_comic_custom_post_type()
    {

        // Set UI labels for Custom Post Type
        $labels = [
            'name'               => _x('Comics', 'Post Type General Name', 'toocheke-companion'),
            'singular_name'      => _x('Comic', 'Post Type Singular Name', 'toocheke-companion'),
            'menu_name'          => __('Comics', 'toocheke-companion'),
            'parent_item_colon'  => __('Parent Comic', 'toocheke-companion'),
            'all_items'          => __('All Comics', 'toocheke-companion'),
            'view_item'          => __('View Comic', 'toocheke-companion'),
            'add_new_item'       => __('Add New Comic', 'toocheke-companion'),
            'add_new'            => __('Add New', 'toocheke-companion'),
            'edit_item'          => __('Edit Comic', 'toocheke-companion'),
            'update_item'        => __('Update Comic', 'toocheke-companion'),
            'search_items'       => __('Search Comic', 'toocheke-companion'),
            'not_found'          => __('No Comics found', 'toocheke-companion'),
            'not_found_in_trash' => __('No Comics found in Trash', 'toocheke-companion'),
        ];

        // Set other options for Custom Post Type

        $args = [
            'label'               => __('comics', 'toocheke-companion'),
            'description'         => __('Comic posts', 'toocheke-companion'),
            'labels'              => $labels,
            // Features this CPT supports in Post Editor
            'supports'            => ['title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'trackbacks', 'shortlinks', 'publicize'],
            'taxonomies'          => ['collection', 'chapter', 'comic-tag', 'comic-location', 'comic-character'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'menu_position'       => 5,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-toocheke-companion',

        ];

        // Registering your Custom Post Type
        register_post_type('comic', $args);
    }

    /* Manga Series CPT Functions */
    public function toocheke_companion_create_manga_series_custom_post_type()
    {

        // Set UI labels for Custom Post Type
        $labels = [
            'name'               => _x('Manga Series', 'Post Type General Name', 'toocheke-companion'),
            'singular_name'      => _x('Manga Series', 'Post Type Singular Name', 'toocheke-companion'),
            'menu_name'          => __('Manga Series', 'toocheke-companion'),
            'parent_item_colon'  => __('Parent Manga Series', 'toocheke-companion'),
            'all_items'          => __('All Manga Series', 'toocheke-companion'),
            'view_item'          => __('View Manga Series', 'toocheke-companion'),
            'add_new_item'       => __('Add New Manga Series', 'toocheke-companion'),
            'add_new'            => __('Add New', 'toocheke-companion'),
            'edit_item'          => __('Edit Manga Series', 'toocheke-companion'),
            'update_item'        => __('Update Manga Series', 'toocheke-companion'),
            'search_items'       => __('Search Manga Series', 'toocheke-companion'),
            'not_found'          => __('No Manga Series found', 'toocheke-companion'),
            'not_found_in_trash' => __('No Manga Series found in Trash', 'toocheke-companion'),
        ];

        // Set other options for Custom Post Type

        $args = [
            'label'               => __('manga series', 'toocheke-companion'),
            'description'         => __('Manga Series posts', 'toocheke-companion'),
            'labels'              => $labels,
            // Features this CPT supports in Post Editor
            'supports'            => ['title', 'author', 'editor', 'thumbnail'],
            'taxonomies'          => ['manga-genre', 'manga-publisher'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => false,
            'menu_position'       => 7,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-toocheke-companion',
        ];

        // Registering your Custom Post Type
        register_post_type('manga_series', $args);
    }

    /* Manga Volume CPT Functions */
    public function toocheke_companion_create_manga_volume_custom_post_type()
    {

        // Set UI labels for Custom Post Type
        $labels = [
            'name'               => _x('Manga Volumes', 'Post Type General Name', 'toocheke-companion'),
            'singular_name'      => _x('Manga Volume', 'Post Type Singular Name', 'toocheke-companion'),
            'menu_name'          => __('Manga Volumes', 'toocheke-companion'),
            'parent_item_colon'  => __('Parent Manga Volume', 'toocheke-companion'),
            'all_items'          => __('All Manga Volumes', 'toocheke-companion'),
            'view_item'          => __('View Manga Volume', 'toocheke-companion'),
            'add_new_item'       => __('Add New Manga Volume', 'toocheke-companion'),
            'add_new'            => __('Add New', 'toocheke-companion'),
            'edit_item'          => __('Edit Manga Volume', 'toocheke-companion'),
            'update_item'        => __('Update Manga Volume', 'toocheke-companion'),
            'search_items'       => __('Search Manga Volume', 'toocheke-companion'),
            'not_found'          => __('No Manga Volumes found', 'toocheke-companion'),
            'not_found_in_trash' => __('No Manga Volumes found in Trash', 'toocheke-companion'),
        ];

        // Set other options for Custom Post Type

        $args = [
            'label'               => __('manga volume', 'toocheke-companion'),
            'description'         => __('Manga Volume posts', 'toocheke-companion'),
            'labels'              => $labels,
            // Features this CPT supports in Post Editor
            'supports'            => ['title', 'author', 'editor', 'thumbnail'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => false,
            'menu_position'       => 7,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-toocheke-companion',
        ];

        // Registering your Custom Post Type
        register_post_type('manga_volume', $args);
    }
    /* Manga Chapter CPT Functions */
    public function toocheke_companion_create_manga_chapter_custom_post_type()
    {

        // Set UI labels for Custom Post Type
        $labels = [
            'name'               => _x('Manga Chapters', 'Post Type General Name', 'toocheke-companion'),
            'singular_name'      => _x('Manga Chapter', 'Post Type Singular Name', 'toocheke-companion'),
            'menu_name'          => __('Manga Chapters', 'toocheke-companion'),
            'parent_item_colon'  => __('Parent Manga Chapter', 'toocheke-companion'),
            'all_items'          => __('All Manga Chapters', 'toocheke-companion'),
            'view_item'          => __('View Manga Chapter', 'toocheke-companion'),
            'add_new_item'       => __('Add New Manga Chapter', 'toocheke-companion'),
            'add_new'            => __('Add New', 'toocheke-companion'),
            'edit_item'          => __('Edit Manga Chapter', 'toocheke-companion'),
            'update_item'        => __('Update Manga Chapter', 'toocheke-companion'),
            'search_items'       => __('Search Manga Chapter', 'toocheke-companion'),
            'not_found'          => __('No Manga Chapters found', 'toocheke-companion'),
            'not_found_in_trash' => __('No Manga Chapters found in Trash', 'toocheke-companion'),
        ];

        // Set other options for Custom Post Type

        $args = [
            'label'               => __('manga chapter', 'toocheke-companion'),
            'description'         => __('Manga Chapter posts', 'toocheke-companion'),
            'labels'              => $labels,
            // Features this CPT supports in Post Editor
            'supports'            => ['title', 'thumbnail', 'author'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => false,
            'menu_position'       => 7,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-toocheke-companion',
        ];

        // Registering your Custom Post Type
        register_post_type('manga_chapter', $args);
    }
    /* Functions for Toocheke Taxonomies */
    public function toocheke_companion_create_taxonomies()
    {
        /* Functions for Series Taxonomies */
        //genres
        $genre_labels = [
            'name'              => _x('Genres', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'     => _x('Genre', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'      => __('Search Genres', 'toocheke-companion'),
            'all_items'         => __('All Genres', 'toocheke-companion'),
            'parent_item'       => __('Parent Genre', 'toocheke-companion'),
            'parent_item_colon' => __('Parent Genre:', 'toocheke-companion'),
            'edit_item'         => __('Edit Genre', 'toocheke-companion'),
            'update_item'       => __('Update Genre', 'toocheke-companion'),
            'add_new_item'      => __('Add New Genre', 'toocheke-companion'),
            'new_item_name'     => __('New Genre Name', 'toocheke-companion'),
            'menu_name'         => __('Genres', 'toocheke-companion'),
            'back_to_items'     => __('← Back to genres', 'toocheke-companion'),
        ];

        // Now register the taxonomy

        register_taxonomy('genres', 'series', [
            'hierarchical'      => true,
            'labels'            => $genre_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'genre'],
        ]);

        // Register tag for series

        //
        $series_tags_labels = [
            'name'                       => _x('Tags', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'              => _x('Tag', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'               => __('Search Tags', 'toocheke-companion'),
            'popular_items'              => __('Popular Tags', 'toocheke-companion'),
            'all_items'                  => __('All Tags', 'toocheke-companion'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Tag', 'toocheke-companion'),
            'update_item'                => __('Update Tag', 'toocheke-companion'),
            'add_new_item'               => __('Add New Tag', 'toocheke-companion'),
            'new_item_name'              => __('New Tag Name', 'toocheke-companion'),
            'separate_items_with_commas' => __('Separate Tags with commas', 'toocheke-companion'),
            'add_or_remove_items'        => __('Add or remove Tags', 'toocheke-companion'),
            'choose_from_most_used'      => __('Choose from the most used Tags', 'toocheke-companion'),
            'menu_name'                  => __('Tags', 'toocheke-companion'),
        ];
        $series_tags_args = [
            'labels'       => $series_tags_labels,
            'hierarchical' => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'query_var'    => true,
            'rewrite'      => ['slug' => 'series-tag'],
        ];
        register_taxonomy('series_tags', 'series', $series_tags_args);

        // Add new taxonomy, make it hierarchical like categories
        //first do the translations part for GUI

        //collections
        $collections_labels = [
            'name'              => _x('Collections', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'     => _x('Collection', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'      => __('Search Collections', 'toocheke-companion'),
            'all_items'         => __('All Collections', 'toocheke-companion'),
            'parent_item'       => __('Parent Collection', 'toocheke-companion'),
            'parent_item_colon' => __('Parent Collection:', 'toocheke-companion'),
            'edit_item'         => __('Edit Collection', 'toocheke-companion'),
            'update_item'       => __('Update Collection', 'toocheke-companion'),
            'add_new_item'      => __('Add New Collection', 'toocheke-companion'),
            'new_item_name'     => __('New Collection Name', 'toocheke-companion'),
            'menu_name'         => __('Collections', 'toocheke-companion'),
            'back_to_items'     => __('? Back to collections', 'toocheke-companion'),
        ];

        // Now register the taxonomy

        register_taxonomy('collections', 'comic', [
            'hierarchical'      => true,
            'labels'            => $collections_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'collection'],
        ]);
        //chapters
        $chapter_labels = [
            'name'              => _x('Chapters', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'     => _x('Chapter', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'      => __('Search Chapters', 'toocheke-companion'),
            'all_items'         => __('All Chapters', 'toocheke-companion'),
            'parent_item'       => __('Parent Chapter', 'toocheke-companion'),
            'parent_item_colon' => __('Parent Chapter:', 'toocheke-companion'),
            'edit_item'         => __('Edit Chapter', 'toocheke-companion'),
            'update_item'       => __('Update Chapter', 'toocheke-companion'),
            'add_new_item'      => __('Add New Chapter', 'toocheke-companion'),
            'new_item_name'     => __('New Chapter Name', 'toocheke-companion'),
            'menu_name'         => __('Chapters', 'toocheke-companion'),
            'back_to_items'     => __('← Back to chapters', 'toocheke-companion'),
        ];

        // Now register the taxonomy

        register_taxonomy('chapters', 'comic', [
            'hierarchical'      => true,
            'labels'            => $chapter_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'chapter'],
        ]);

        // Register tag for comics

        //
        $comic_tags_labels = [
            'name'                       => _x('Comic Tags', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'              => _x('Tag', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'               => __('Search Tags', 'toocheke-companion'),
            'popular_items'              => __('Popular Tags', 'toocheke-companion'),
            'all_items'                  => __('All Tags', 'toocheke-companion'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Tag', 'toocheke-companion'),
            'update_item'                => __('Update Tag', 'toocheke-companion'),
            'add_new_item'               => __('Add New Tag', 'toocheke-companion'),
            'new_item_name'              => __('New Tag Name', 'toocheke-companion'),
            'separate_items_with_commas' => __('Separate Tags with commas', 'toocheke-companion'),
            'add_or_remove_items'        => __('Add or remove Tags', 'toocheke-companion'),
            'choose_from_most_used'      => __('Choose from the most used Tags', 'toocheke-companion'),
            'menu_name'                  => __('Tags', 'toocheke-companion'),
            'back_to_items'              => __('← Back to comic tags', 'toocheke-companion'),
        ];
        $comic_tags_args = [
            'labels'       => $comic_tags_labels,
            'hierarchical' => false,
            'show_in_rest' => true,
            'show_ui'      => true,
            'query_var'    => true,
            'rewrite'      => ['slug' => 'comic-tag'],
        ];
        register_taxonomy('comic_tags', 'comic', $comic_tags_args);

        $comic_locations_labels = [
            'name'                       => _x('Locations', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'              => _x('Location', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'               => __('Search Locations', 'toocheke-companion'),
            'popular_items'              => __('Popular Locations', 'toocheke-companion'),
            'all_items'                  => __('All Locations', 'toocheke-companion'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Location', 'toocheke-companion'),
            'update_item'                => __('Update Location', 'toocheke-companion'),
            'add_new_item'               => __('Add New Location', 'toocheke-companion'),
            'new_item_name'              => __('New Location Name', 'toocheke-companion'),
            'separate_items_with_commas' => __('Separate Locations with commas', 'toocheke-companion'),
            'add_or_remove_items'        => __('Add or remove Locations', 'toocheke-companion'),
            'choose_from_most_used'      => __('Choose from the most used Locations', 'toocheke-companion'),
            'menu_name'                  => __('Locations', 'toocheke-companion'),
            'back_to_items'              => __('← Back to locations', 'toocheke-companion'),
        ];
        $comic_locations_args = [
            'labels'       => $comic_locations_labels,
            'hierarchical' => false,
            'show_in_rest' => true,
            'show_ui'      => true,
            'query_var'    => true,
            'rewrite'      => ['slug' => 'comic-location'],
        ];
        register_taxonomy('comic_locations', 'comic', $comic_locations_args);

        $comic_characters_labels = [
            'name'                       => _x('Characters', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'              => _x('Character', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'               => __('Search Characters', 'toocheke-companion'),
            'popular_items'              => __('Popular Characters', 'toocheke-companion'),
            'all_items'                  => __('All Characters', 'toocheke-companion'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Character', 'toocheke-companion'),
            'update_item'                => __('Update Character', 'toocheke-companion'),
            'add_new_item'               => __('Add New Character', 'toocheke-companion'),
            'new_item_name'              => __('New Character Name', 'toocheke-companion'),
            'separate_items_with_commas' => __('Separate Characters with commas', 'toocheke-companion'),
            'add_or_remove_items'        => __('Add or remove Characters', 'toocheke-companion'),
            'choose_from_most_used'      => __('Choose from the most used Characters', 'toocheke-companion'),
            'menu_name'                  => __('Characters', 'toocheke-companion'),
            'back_to_items'              => __('← Back to characters', 'toocheke-companion'),
        ];
        $comic_characters_args = [
            'labels'       => $comic_characters_labels,
            'hierarchical' => false,
            'show_in_rest' => true,
            'show_ui'      => true,
            'query_var'    => true,
            'rewrite'      => ['slug' => 'comic-character'],
        ];
        register_taxonomy('comic_characters', 'comic', $comic_characters_args);

        //genres
        $series_genre_labels = [
            'name'                       => _x('Genres', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'              => _x('Genre', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'               => __('Search Genres', 'toocheke-companion'),
            'all_items'                  => __('All Genres', 'toocheke-companion'),
            'parent_item'                => __('Parent Genre', 'toocheke-companion'),
            'parent_item_colon'          => __('Parent Genre:', 'toocheke-companion'),
            'edit_item'                  => __('Edit Genre', 'toocheke-companion'),
            'update_item'                => __('Update Genre', 'toocheke-companion'),
            'add_new_item'               => __('Add New Genre', 'toocheke-companion'),
            'new_item_name'              => __('New Genre Name', 'toocheke-companion'),
            'menu_name'                  => __('Genres', 'toocheke-companion'),
            'back_to_items'              => __('← Back to genres', 'toocheke-companion'),
            'popular_items'              => __('Popular Genres', 'toocheke-companion'),
            'separate_items_with_commas' => __('Separate Genres with commas', 'toocheke-companion'),
            'add_or_remove_items'        => __('Add or remove Genres', 'toocheke-companion'),
            'choose_from_most_used'      => __('Choose from the most used Genres', 'toocheke-companion'),
        ];

        // Now register the taxonomy

        register_taxonomy('manga_genre', 'manga_series', [
            'hierarchical' => false,
            'labels'       => $series_genre_labels,
            'show_in_rest' => true,
            'show_ui'      => true,
            'query_var'    => true,
            'rewrite'      => ['slug' => 'manga-genre'],
        ]);

        //publishers
        $series_publisher_labels = [
            'name'                       => _x('Publishers', 'taxonomy general name', 'toocheke-companion'),
            'singular_name'              => _x('Publisher', 'taxonomy singular name', 'toocheke-companion'),
            'search_items'               => __('Search Publishers', 'toocheke-companion'),
            'all_items'                  => __('All Publishers', 'toocheke-companion'),
            'parent_item'                => __('Parent Publisher', 'toocheke-companion'),
            'parent_item_colon'          => __('Parent Publisher:', 'toocheke-companion'),
            'edit_item'                  => __('Edit Publisher', 'toocheke-companion'),
            'update_item'                => __('Update Publisher', 'toocheke-companion'),
            'add_new_item'               => __('Add New Publisher', 'toocheke-companion'),
            'new_item_name'              => __('New Publisher Name', 'toocheke-companion'),
            'menu_name'                  => __('Publishers', 'toocheke-companion'),
            'back_to_items'              => __('← Back to publishers', 'toocheke-companion'),
            'popular_items'              => __('Popular Publishers', 'toocheke-companion'),
            'separate_items_with_commas' => __('Separate Publishers with commas', 'toocheke-companion'),
            'add_or_remove_items'        => __('Add or remove Publishers', 'toocheke-companion'),
            'choose_from_most_used'      => __('Choose from the most used Publishers', 'toocheke-companion'),
        ];

        // Now register the taxonomy

        register_taxonomy('manga_publisher', 'manga_series', [
            'hierarchical' => false,
            'labels'       => $series_publisher_labels,
            'show_in_rest' => true,
            'show_ui'      => true,
            'query_var'    => true,
            'rewrite'      => ['slug' => 'manga-publisher'],
        ]);
    }
    /* Functions for registering meta term for the toocheke taxonamies*/
    /*
     * Displaying the patreon column
     */

    public function toocheke_companion_add_patreon_level_column($columns)
    {

        $new_columns = [];

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $columns[$key];
            if ($key === 'author') {
                $new_columns['patreon_level'] = __('Patreon Level', 'toocheke-companion');
            }
        }

        return $new_columns;
    }
    /*
 * Add comics columns
 */

    public function toocheke_companion_add_comic_columns($columns)
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            // Insert comic_series after patreon_level (if Patreon plugin active) OR after author
            if ($key === 'patreon_level' && is_plugin_active('patreon-connect/patreon.php')) {
                $new_columns['comic_series'] = __('Series', 'toocheke-companion');
            } elseif ($key === 'author' && ! isset($new_columns['comic_series'])) {
                $new_columns['comic_series'] = __('Series', 'toocheke-companion');
            }

            // Insert tags, characters, and locations after taxonomy-chapters
            if ($key === 'taxonomy-chapters') {
                $new_columns['comic_tags']       = __('Tag', 'toocheke-companion');
                $new_columns['comic_characters'] = __('Character', 'toocheke-companion');
                $new_columns['comic_locations']  = __('Location', 'toocheke-companion');
            }

            // Insert likes, views, and thumbnail after comments
            if ($key === 'comments') {
                $new_columns['comic_likes']     = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
                $new_columns['comic_views']     = __('<span class="dashicons dashicons-visibility"></span>', 'toocheke-companion');
                $new_columns['comic_thumbnail'] = __('Thumbnail', 'toocheke-companion');
            }
        }

        return $new_columns;
    }

    /*
 * Add Manga Series columns
 */

    public function toocheke_companion_add_manga_series_columns($columns)
    {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // Insert custom columns after the title
            if ($key === 'title') {
                $new_columns['manga_series_genres']     = __('Genres', 'toocheke-companion');
                $new_columns['manga_series_publishers'] = __('Publishers', 'toocheke-companion');
                $new_columns['manga_series_likes']      = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
                $new_columns['manga_series_thumbnail']  = __('Thumbnail', 'toocheke-companion');
            }
        }

        return $new_columns;
    }

    public function toocheke_companion_add_manga_volume_columns($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'title') {
                $new_columns['manga_volume_thumbnail'] = __('Thumbnail', 'toocheke-companion');
                $new_columns['manga_volume_series']    = __('Series', 'toocheke-companion');
                $new_columns['manga_volume_likes']     = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
            }
        }
        return $new_columns;
    }

    public function toocheke_companion_add_manga_chapter_columns($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'title') {
                $new_columns['manga_chapter_thumbnail'] = __('Thumbnail', 'toocheke-companion');
                $new_columns['manga_chapter_series']    = __('Series', 'toocheke-companion');
                $new_columns['manga_chapter_volume']    = __('Volume', 'toocheke-companion');
                $new_columns['manga_chapter_likes']     = __('<span class="dashicons dashicons-heart"></span>', 'toocheke-companion');
                $new_columns['manga_chapter_views']     = __('<span class="dashicons dashicons-visibility"></span>', 'toocheke-companion');
            }
        }
        return $new_columns;
    }
    /*
     * Adding series columns
     */

    public function toocheke_companion_add_series_columns($columns)
    {
        // Define the new columns in order
        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            // Insert after taxonomy-genres
            if ($key === 'taxonomy-genres') {
                $new_columns['series_tags'] = __('Tag', 'toocheke-companion');
            }

            // Insert after series_tags
            if ($key === 'taxonomy-genres') { // <-- rely on the known order
                $new_columns['series_thumbnail']   = __('Thumbnail', 'toocheke-companion');
                $new_columns['series_hero']        = __('Hero - Desktop', 'toocheke-companion');
                $new_columns['series_mobile_hero'] = __('Hero - Mobile', 'toocheke-companion');
                $new_columns['series_bg_img']      = __('Background<br/> Image', 'toocheke-companion');
                $new_columns['series_bg_color']    = __('Background<br/> Color', 'toocheke-companion');
            }
        }

        return $new_columns;
    }
    /* Adding content to columns */

    public function toocheke_companion_add_series_column_content($column_name, $id)
    {
        global $wpdb;
        switch ($column_name) {
            case 'series_tags':
                $terms_list = get_the_terms($id, 'series_tags');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $tags_list[] = '<a href="' . admin_url('/edit.php?post_type=series&series_tags=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $tags_list);
                }
                break;
            case 'series_thumbnail':
                $post_thumbnail_id = get_post_thumbnail_id($id);
                if ($post_thumbnail_id) {
                    $post_thumbnail_img     = wp_get_attachment_image_src($post_thumbnail_id, 'featured_preview');
                    $post_thumbnail_img_src = $post_thumbnail_img[0];
                    if ($post_thumbnail_img_src) {
                        echo '<img src="' . $post_thumbnail_img_src . '" class="series-thumbnail" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-thumbnail" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-thumbnail" />';
                }
                break;
            case 'series_hero':
                $post_hero_id = get_post_meta($id, 'series_hero_image_id', true);
                get_post_thumbnail_id($id);
                if ($post_hero_id) {
                    $post_hero_img     = wp_get_attachment_image_src($post_hero_id, 'featured_preview');
                    $post_hero_img_src = $post_hero_img[0];
                    if ($post_hero_img_src) {
                        echo '<img src="' . $post_hero_img_src . '" class="series-hero" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                }
                break;
            case 'series_mobile_hero':
                $post_hero_id = get_post_meta($id, 'series_mobile_hero_image_id', true);
                get_post_thumbnail_id($id);
                if ($post_hero_id) {
                    $post_hero_img     = wp_get_attachment_image_src($post_hero_id, 'featured_preview');
                    $post_hero_img_src = $post_hero_img[0];
                    if ($post_hero_img_src) {
                        echo '<img src="' . $post_hero_img_src . '" class="series-hero" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-hero" />';
                }
                break;
            case 'series_bg_img':
                $post_bg_id = get_post_meta($id, 'series_bg_image_id', true);
                get_post_thumbnail_id($id);
                if ($post_bg_id) {
                    $post_bg_img     = wp_get_attachment_image_src($post_bg_id, 'featured_preview');
                    $post_bg_img_src = $post_bg_img[0];
                    if ($post_bg_img_src) {
                        echo '<img src="' . $post_bg_img_src . '" class="series-bg" />';
                    } else {
                        echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-bg" />';
                    }
                } else {
                    echo '<img src="' . plugins_url('toocheke-companion' . '/img/no-image.png') . '" class="series-bg" />';
                }
                break;
            case 'series_bg_color':
                $bg_color = get_post_meta($id, 'series_bg_color');
                if (! empty($bg_color) && ! isset($bg_color->errors)) {
                    $color_box = '<div class="color-box" style="background-color: ' . $bg_color[0] . '"></div>';
                    echo $color_box;
                }
                break;

            default:
                break;
        } // end switch
    }
    public function toocheke_companion_render_manga_series_columns($column_name, $id)
    {
        global $wpdb;
        switch ($column_name) {
            case 'manga_series_genres':
                $terms_list = get_the_terms($id, 'manga_genre');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $genres_list[] = '<a href="' . admin_url('edit.php?post_type=manga_series&manga_genre=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $genres_list);
                }
                break;
            case 'manga_series_publishers':
                $terms_list = get_the_terms($id, 'manga_publisher');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $publishers_list[] = '<a href="' . admin_url('/edit.php?post_type=manga_series&manga_publisher=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $publishers_list);
                }
                break;
            case 'manga_series_likes':
                if (get_post_meta($id, "_post_like_count", true)) {
                    echo get_post_meta($id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }
                break;
            case 'manga_series_thumbnail':
                $this->toocheke_render_thumbnail($id);
                break;

            default:
                break;
        } // end switch
    }
    public function toocheke_companion_render_manga_volume_columns($column_name, $post_id)
    {
        global $wpdb;
        switch ($column_name) {

            case 'manga_volume_thumbnail':
                $this->toocheke_render_thumbnail($post_id);
                break;
            case 'manga_volume_series':
                $series_id = get_post_meta($post_id, 'series_id', true);
                if ($series_id) {
                    $series_title = get_the_title($series_id);
                    $link         = add_query_arg([
                        'post_type' => 'manga_volume',
                        'series_id' => $series_id,
                    ], admin_url('edit.php'));
                    echo '<a href="' . esc_url($link) . '">' . esc_html($series_title) . '</a>';
                } else {
                    echo '-';
                }
                break;
            case 'manga_volume_likes':
                if (get_post_meta($post_id, "_post_like_count", true)) {
                    echo get_post_meta($post_id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }
                break;
            default:
                break;
        } // end switch
    }
    public function toocheke_companion_render_manga_chapter_columns($column_name, $post_id)
    {
        switch ($column_name) {

            case 'manga_chapter_thumbnail':
                $this->toocheke_render_thumbnail($post_id);
                break;

            case 'manga_chapter_series':
                $series_id = get_post_meta($post_id, 'series_id', true);
                if ($series_id) {
                    $series_title = get_the_title($series_id);
                    $link         = add_query_arg([
                        'post_type' => 'manga_chapter',
                        'series_id' => $series_id,
                    ], admin_url('edit.php'));
                    echo '<a href="' . esc_url($link) . '">' . esc_html($series_title) . '</a>';
                } else {
                    echo '-';
                }
                break;

            case 'manga_chapter_volume':
                $volume_id = get_post_meta($post_id, 'volume_id', true);
                if ($volume_id) {
                    $volume_title = get_the_title($volume_id);
                    $link         = add_query_arg([
                        'post_type' => 'manga_chapter',
                        'volume_id' => $volume_id,
                    ], admin_url('edit.php'));
                    echo '<a href="' . esc_url($link) . '">' . esc_html($volume_title) . '</a>';
                } else {
                    echo '-';
                }
                break;
            case 'manga_chapter_likes':
                if (get_post_meta($post_id, "_post_like_count", true)) {
                    echo get_post_meta($post_id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }
                break;
            case 'manga_chapter_views':
                if (get_post_meta($post_id, "post_views_count", true)) {
                    echo get_post_meta($post_id, "post_views_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }

                break;
            default:
                break;
        }
    }

    public function toocheke_companion_add_comic_column_content($column_name, $id)
    {
        global $wpdb;
        switch ($column_name) {
            case 'comic_characters':
                $terms_list = get_the_terms($id, 'comic_characters');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $character_list[] = '<a href="' . admin_url('edit.php?post_type=comic&comic_characters=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $character_list);
                }
                break;
            case 'comic_locations':
                $terms_list = get_the_terms($id, 'comic_locations');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $locations_list[] = '<a href="' . admin_url('/edit.php?post_type=comic&comic_locations=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $locations_list);
                }
                break;
            case 'comic_thumbnail':
                $this->toocheke_render_thumbnail($id);
                break;
            case 'comic_likes':
                if (get_post_meta($id, "_post_like_count", true)) {
                    echo get_post_meta($id, "_post_like_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }

                break;
            case 'comic_views':
                if (get_post_meta($id, "post_views_count", true)) {
                    echo get_post_meta($id, "post_views_count", true);
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }

                break;
            case 'comic_tags':
                $terms_list = get_the_terms($id, 'comic_tags');
                if (! empty($terms_list) && ! isset($terms_list->errors)) {
                    foreach ($terms_list as $term) {
                        $tags_list[] = '<a href="' . admin_url('/edit.php?post_type=comic&comic_tags=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                    echo join(', ', $tags_list);
                }
                break;
            case 'comic_series':
                $ancestors     = get_ancestors($id, 'series');
                $post_ancestor = end($ancestors);
                if ($post_ancestor != 0) {
                    echo '<a href="' . admin_url('/edit.php?post_type=comic&post_parent=' . $post_ancestor) . '">' . get_the_title($post_ancestor) . '</a>';
                } else {
                    echo '—';
                }
                break;
            case 'patreon_level':
                // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude.FileIncludeFound
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
                if (is_plugin_active('patreon-connect/patreon.php')) {
                    $patreon_level = get_post_meta($id, 'patreon-level', true);
                    //echo $this->toocheke_get_patreon_level_label($patreon_level) ;
                    echo '<a href="' . admin_url('/edit.php?post_type=comic&patreon_level=' . $patreon_level) . '">' . $this->toocheke_get_patreon_level_label($patreon_level) . '</a>';
                }
                break;
            default:
                break;
        } // end switch
    }
    /* Get Patreon Level */
    public function toocheke_get_patreon_level_label($patreon_level)
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (is_plugin_active('patreon-connect/patreon.php')) {
            $label         = PATREON_TEXT_EVERYONE;
            $creator_tiers = get_option('patreon-creator-tiers', false);

            if (is_array($creator_tiers['included'])) {

                $tier_count = 1;

                // Flag for determining if the matching tier was found during iteration of tiers
                $matching_level_found = false;

                foreach ($creator_tiers['included'] as $key => $value) {

                    // If its not a reward element, continue, just to make sure

                    if (
                        ! isset($creator_tiers['included'][$key]['type'])
                        or ($creator_tiers['included'][$key]['type'] != 'reward' and $creator_tiers['included'][$key]['type'] != 'tier')
                    ) {
                        continue;
                    }

                    $reward = $creator_tiers['included'][$key];

                    // Special conditions for label for element 0, which is 'everyone' and '1, which is 'patron only'

                    if ($reward['id'] == -1) {
                        $label = PATREON_TEXT_EVERYONE;
                    }
                    if ($reward['id'] == 0) {
                        $label = PATREON_TEXT_ANY_PATRON;
                    }

                    // Use title if exists, and cents amount converted to dollar for any other reward level
                    if ($reward['id'] > 0) {

                        $tier_title = 'Tier ' . $tier_count;

                        $tier_count++;

                        if ($reward['attributes']['title'] != '') {

                            $tier_title = $reward['attributes']['title'];

                            // If the title is too long, snip it
                            if (strlen($tier_title) > 23) {
                                $tier_title = substr($tier_title, 0, 23) . '...';
                            }
                        }

                        $label = $tier_title . ' - $' . ($reward['attributes']['amount_cents'] / 100);
                        if (($reward['attributes']['amount_cents'] / 100) == $patreon_level) {
                            break;
                        }
                    }

                    if (($reward['attributes']['amount_cents'] / 100) >= $patreon_level and ! $matching_level_found) {

                        $matching_level_found = true;

                        // Check if a precise amount is set for this content. If so, add the actual locking amount in parantheses

                        if (($reward['attributes']['amount_cents'] / 100) != $patreon_level) {

                            $label .= ' ($' . $patreon_level . ' exact)';
                        }
                        break;
                    }
                }
            }
            return $label;
        }
    }
    public function toocheke_filter_patreon_levels($query)
    {
        if (! is_admin() || 'comic' != $query->query['post_type'] || ! isset($_GET['patreon_level'])) {
            return;
        }

        $query->set('meta_key', 'patreon-level');
        $query->set('meta_value', $_GET['patreon_level']);
    }
    /* Genre specific functions and terms */
    public function toocheke_companion_genre_load_media()
    {
        if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'genres') {
            return;
        }
        wp_enqueue_media();
    }

    /**
     * Add a form field in the new category page
     * @since 1.0.0
     */

    public function toocheke_companion_add_genre_image($taxonomy)
    { ?>
        <div class="form-field term-genre">
            <label for="genre-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            <input type="hidden" id="genre-image-id" name="genre-image-id" class="custom_media_url" value="">
            <div id="genre-image-wrapper"></div>
            <p>
                <input type="button" class="button button-secondary genres_tax_media_button" id="genres_tax_media_button"
                    name="genres_tax_media_button" value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                <input type="button" class="button button-secondary genres_tax_media_remove" id="genres_tax_media_remove"
                    name="genres_tax_media_remove" value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
            </p>
            <p>This is the featured image for the genre.</p>
        </div>
    <?php }

    /**
     * Save the form field
     * @since 1.0.0
     */
    public function toocheke_companion_save_genre_image($term_id, $tt_id)
    {
        if (isset($_POST['genre-image-id']) && '' !== $_POST['genre-image-id']) {
            add_term_meta($term_id, 'genre-image-id', absint(sanitize_title($_POST['genre-image-id'])), true);
        }
    }

    /**
     * Edit the form field
     * @since 1.0.0
     */
    public function toocheke_companion_update_genre_image($term, $taxonomy)
    { ?>
        <tr class="form-field term-genre-wrap">
            <th scope="row">
                <label for="genre-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            </th>
            <td>
                <?php $image_id = get_term_meta($term->term_id, 'genre-image-id', true); ?>
                <input type="hidden" id="genre-image-id" name="genre-image-id" value="<?php echo esc_attr($image_id); ?>">
                <div id="genre-image-wrapper">
                    <?php if ($image_id) { ?>
                        <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                    <?php } ?>
                </div>
                <p>
                    <input type="button" class="button button-secondary genres_tax_media_button" id="genres_tax_media_button"
                        name="genres_tax_media_button" value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                    <input type="button" class="button button-secondary genres_tax_media_remove" id="genres_tax_media_remove"
                        name="genres_tax_media_remove" value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
                </p>
                <p>This is the featured image for the genre.</p>
            </td>
        </tr>
    <?php }

    /**
     * Update the form field value
     * @since 1.0.0
     */
    public function toocheke_companion_updated_genre_image($term_id, $tt_id)
    {
        if (isset($_POST['genre-image-id']) && '' !== $_POST['genre-image-id']) {
            update_term_meta($term_id, 'genre-image-id', absint(sanitize_title($_POST['genre-image-id'])));
        } else {
            update_term_meta($term_id, 'genre-image-id', '');
        }
    }

    /**
     * Enqueue styles and scripts
     * @since 1.0.0
     */
    public function toocheke_companion_genre_add_script()
    {
        if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'genres') {
            return;
        } ?>
        <script>
            jQuery(document).ready(function($) {

                _wpMediaViewsL10n.insertIntoPost = '<?php _e("Insert", 'toocheke-companion'); ?>';

                function ct_media_upload(button_class) {
                    var _custom_media = true,
                        _orig_send_attachment = wp.media.editor.send.attachment;

                    $('body').on('click', button_class, function(e) {
                        var button_id = '#' + $(this).attr('id');
                        var send_attachment_bkp = wp.media.editor.send.attachment;
                        var button = $(button_id);
                        _custom_media = true;

                        wp.media.editor.send.attachment = function(props, attachment) {
                            if (_custom_media) {
                                $('#genre-image-id').val(attachment.id);
                                $('#genre-image-wrapper').html(
                                    '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                                );
                                $('#genre-image-wrapper .custom_media_image').attr('src', attachment.url).css(
                                    'display', 'block');
                            } else {
                                return _orig_send_attachment.apply(button_id, [props, attachment]);
                            }
                        }
                        wp.media.editor.open(button);
                        return false;
                    });
                }
                ct_media_upload('.genres_tax_media_button.button');
                $('body').on('click', '.genres_tax_media_remove', function() {
                    $('#genre-image-id').val('');
                    $('#genre-image-wrapper').html(
                        '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                    );
                });
                // Thanks: http://stackoverflow.com/questions/15281995/wordpress-create-category-ajax-response
                $(document).ajaxComplete(function(event, xhr, settings) {
                    var queryStringArr = settings.data.split('&');

                    if ($.inArray('action=add-tag', queryStringArr) !== -1) {
                        var xml = xhr.responseXML;
                        $response = $(xml).find('term_id').text();
                        if ($response != "") {
                            // Clear the thumb image
                            $('#genre-image-wrapper').html('');
                            $('#genre-image-id').val('');
                        }
                    }
                });
            });
        </script>
    <?php
    }
    /* Functions for Comic Genre */
    /* Displaying image meta data in column */
    public function toocheke_companion_genre_add_image_column($columns)
    {
        $columns['genre_image'] = __('Image', 'toocheke-companion');
        return $columns;
    }
    /* Add content into column */
    public function toocheke_companion_add_genre_image_column_content($content, $column_name, $term_id)
    {

        if ($column_name !== 'genre_image') {
            return $content;
        }

        $term_id  = absint($term_id);
        $thumb_id = get_term_meta($term_id, 'genre-image-id', true);

        if (! empty($thumb_id)) {
            $term_img = wp_get_attachment_url($thumb_id);
            $content .= "<img src=\"$term_img\" width=\"100%\" height=\"auto\"/>";
        }

        return $content;
    }

    /* Make column sortable */

    public function toocheke_companion_genre_add_image_column_sortable($sortable)
    {
        $sortable['genre_image'] = 'genre_image';
        return $sortable;
    }

    //Genres page
    public function toocheke_companion_create_genre_page_on_theme_activation()
    {

        // Set the title, template, etc
        $new_page_title    = __('Genres', 'toocheke-companion'); // Page's title
        $new_page_content  = '';                                 // Content goes here
        $new_page_template = 'page-templates/series-genres.php'; // The template to use for the page
        $page_check_query  = new WP_Query(
            [
                'post_type'              => 'page',
                'title'                  => $new_page_title,
                'post_status'            => 'all',
                'posts_per_page'         => 1,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'orderby'                => 'post_date ID',
                'order'                  => 'ASC',
            ]
        );

        if (! empty($page_check_query->post)) {
            $page_check = $page_check_query->post;
        } else {
            $page_check = null;
        }

        //$page_check = get_page_by_title($new_page_title); // Check if the page already exists --Deprecated in Wordpress 6.2
        // Store the above data in an array
        $new_page = [
            'post_type'    => 'page',
            'post_title'   => $new_page_title,
            'post_content' => $new_page_content,
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_slug'    => 'genres',
        ];
        // If the page doesn't already exist, create it
        if (! isset($page_check->ID)) {
            $new_page_id = wp_insert_post($new_page);
            if (! empty($new_page_template)) {
                update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
            }
        }
    }
    /* Chapter specific functions and terms */
    /*Add new Term*/
    public function toocheke_companion_chapter_add_order_field($taxonomy)
    {
    ?><div class="form-field term-chapter">
            <label for="chapter-order"><?php _e('Order', 'toocheke-companion'); ?></label>
            <input type="number" min="1" name="chapter-order" id="chapter-order" value="1" class="chapter-order-field"
                aria-required="true" required />
            <p>This determines what order the chapter is in.</p>

        </div><?php
            }
            /*Save new Term*/
            public function toocheke_companion_chapter_save_order_meta($term_id, $tt_id)
            {
                if (isset($_POST['chapter-order']) && ! empty($_POST['chapter-order'])) {
                    $order = absint(sanitize_title($_POST['chapter-order']));
                    add_term_meta($term_id, 'chapter-order', $order, true);
                } else {
                    add_term_meta($term_id, 'chapter-order', 1, true);
                }
            }
            /*Updating Term*/
            public function toocheke_companion_chapter_edit_order_field($term, $taxonomy)
            {

                // get current order
                $current_order = get_term_meta($term->term_id, 'chapter-order', true);

                ?><tr class="form-field term-order-wrap">
            <th scope="row"><label for="chapter-order"><?php _e('Order', 'toocheke-companion'); ?></label></th>
            <td>
                <input type="number" min="1" name="chapter-order" id="chapter-order"
                    value="<?php echo esc_attr($current_order); ?>" class="chapter-order-field" aria-required="true" required />
                <p>This determines what order the chapter is in.</p>
            </td>
        </tr><?php
            }
            /*Save Data*/
            public function toocheke_companion_chapter_update_order_meta($term_id, $tt_id)
            {

                if (isset($_POST['chapter-order']) && ! empty($_POST['chapter-order'])) {
                    $order = absint(sanitize_title($_POST['chapter-order']));
                    update_term_meta($term_id, 'chapter-order', $order);
                }
            }
            /*
     * Displaying the order column
     */
            public function toocheke_companion_chapter_add_order_column($columns)
            {
                $columns['chapter_order'] = __('Order', 'toocheke-companion');
                return $columns;
            }
            /* Add content into column */
            public function toocheke_companion_add_chapter_column_content($content, $column_name, $term_id)
            {

                if ($column_name !== 'chapter_order') {
                    return $content;
                }

                $term_id = absint($term_id);
                $order   = get_term_meta($term_id, 'chapter-order', true);

                if (! empty($order)) {
                    $content .= esc_attr($order);
                }

                return $content;
            }

            /* Make column sortable */
            public function toocheke_companion_chapter_add_order_column_sortable($sortable)
            {
                $sortable['chapter_order'] = 'chapter_order';
                return $sortable;
            }

            /* Sort columns */
            public function toocheke_companion_chapter_sort_by_chapter_order($term_query)
            {
                global $pagenow;
                if (! is_admin()) {
                    return $term_query;
                }
                // WP_Term_Query does not define a get() or a set() method so the query_vars member must
                // be manipulated directly
                if (is_admin() && $pagenow == 'edit-tags.php' && $term_query->query_vars['taxonomy'][0] == 'chapters' && (! isset($_GET['orderby']) || $_GET['orderby'] == 'chapter_order')) {
                    // set orderby to the named clause in the meta_query
                    $term_query->query_vars['orderby'] = 'order_clause';
                    $term_query->query_vars['order']   = isset($_GET['order']) ? sanitize_title($_GET['order']) : "DESC";
                    // the OR relation and the NOT EXISTS clause allow for terms without a meta_value at all
                    $args = [
                        'relation' => 'OR',
                        'order_clause'      => [
                            'key'  => 'chapter-order',
                            'type' => 'NUMERIC',
                        ],
                        [
                            'key'     => 'chapter-order',
                            'compare' => 'NOT EXISTS',
                        ],
                    ];
                    $term_query->meta_query = new WP_Meta_Query($args);
                }
                return $term_query;
            }

            /* Chapter Image Functions */
            public function toocheke_companion_chapter_load_media()
            {
                if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'chapters') {
                    return;
                }
                wp_enqueue_media();
            }

            /**
             * Add a form field in the new category page
             * @since 1.0.0
             */

            public function toocheke_companion_add_chapter_image($taxonomy)
            { ?>
        <div class="form-field term-chapter">
            <label for="chapter-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            <input type="hidden" id="chapter-image-id" name="chapter-image-id" class="custom_media_url" value="">
            <div id="chapter-image-wrapper"></div>
            <p>
                <input type="button" class="button button-secondary chapters_tax_media_button" id="chapters_tax_media_button"
                    name="chapters_tax_media_button" value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                <input type="button" class="button button-secondary chapters_tax_media_remove" id="chapters_tax_media_remove"
                    name="chapters_tax_media_remove" value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
            </p>
            <p>This is the featured image for the chapter.</p>
        </div>
    <?php }

            /**
             * Save the form field
             * @since 1.0.0
             */
            public function toocheke_companion_save_chapter_image($term_id, $tt_id)
            {
                if (isset($_POST['chapter-image-id']) && '' !== $_POST['chapter-image-id']) {
                    add_term_meta($term_id, 'chapter-image-id', absint(sanitize_title($_POST['chapter-image-id'])), true);
                }
            }

            /**
             * Edit the form field
             * @since 1.0.0
             */
            public function toocheke_companion_update_chapter_image($term, $taxonomy)
            { ?>
        <tr class="form-field term-chapter-wrap">
            <th scope="row">
                <label for="chapter-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            </th>
            <td>
                <?php $image_id = get_term_meta($term->term_id, 'chapter-image-id', true); ?>
                <input type="hidden" id="chapter-image-id" name="chapter-image-id" value="<?php echo esc_attr($image_id); ?>">
                <div id="chapter-image-wrapper">
                    <?php if ($image_id) { ?>
                        <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                    <?php } ?>
                </div>
                <p>
                    <input type="button" class="button button-secondary chapters_tax_media_button"
                        id="chapters_tax_media_button" name="chapters_tax_media_button"
                        value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                    <input type="button" class="button button-secondary chapters_tax_media_remove"
                        id="chapters_tax_media_remove" name="chapters_tax_media_remove"
                        value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
                </p>
                <p>This is the featured image for the chapter.</p>
            </td>
        </tr>
    <?php }

            /**
             * Update the form field value
             * @since 1.0.0
             */
            public function toocheke_companion_updated_chapter_image($term_id, $tt_id)
            {
                if (isset($_POST['chapter-image-id']) && '' !== $_POST['chapter-image-id']) {
                    update_term_meta($term_id, 'chapter-image-id', absint(sanitize_title($_POST['chapter-image-id'])));
                } else {
                    update_term_meta($term_id, 'chapter-image-id', '');
                }
            }

            /**
             * Enqueue styles and scripts
             * @since 1.0.0
             */
            public function toocheke_companion_chapter_add_script()
            {
                if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'chapters') {
                    return;
                } ?>
        <script>
            jQuery(document).ready(function($) {

                _wpMediaViewsL10n.insertIntoPost = '<?php _e("Insert", 'toocheke-companion'); ?>';

                function ct_media_upload(button_class) {
                    var _custom_media = true,
                        _orig_send_attachment = wp.media.editor.send.attachment;

                    $('body').on('click', button_class, function(e) {
                        var button_id = '#' + $(this).attr('id');
                        var send_attachment_bkp = wp.media.editor.send.attachment;
                        var button = $(button_id);
                        _custom_media = true;

                        wp.media.editor.send.attachment = function(props, attachment) {
                            if (_custom_media) {
                                $('#chapter-image-id').val(attachment.id);
                                $('#chapter-image-wrapper').html(
                                    '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                                );
                                $('#chapter-image-wrapper .custom_media_image').attr('src', attachment.url).css(
                                    'display', 'block');
                            } else {
                                return _orig_send_attachment.apply(button_id, [props, attachment]);
                            }
                        }
                        wp.media.editor.open(button);
                        return false;
                    });
                }
                ct_media_upload('.chapters_tax_media_button.button');
                $('body').on('click', '.chapters_tax_media_remove', function() {
                    $('#chapter-image-id').val('');
                    $('#chapter-image-wrapper').html(
                        '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                    );
                });
                // Thanks: http://stackoverflow.com/questions/15281995/wordpress-create-category-ajax-response
                $(document).ajaxComplete(function(event, xhr, settings) {
                    var queryStringArr = settings.data.split('&');

                    if ($.inArray('action=add-tag', queryStringArr) !== -1) {
                        var xml = xhr.responseXML;
                        $response = $(xml).find('term_id').text();
                        if ($response != "") {
                            // Clear the thumb image
                            $('#chapter-image-wrapper').html('');
                            $('#chapter-image-id').val('');
                        }
                    }
                });
            });
        </script>
    <?php
            }
            /* Functions for Comic Chapter */
            /* Displaying image meta data in column */
            public function toocheke_companion_chapter_add_image_column($columns)
            {
                $columns['chapter_image'] = __('Image', 'toocheke-companion');
                return $columns;
            }
            /* Add content into column */
            public function toocheke_companion_add_chapter_image_column_content($content, $column_name, $term_id)
            {

                if ($column_name !== 'chapter_image') {
                    return $content;
                }

                $term_id  = absint($term_id);
                $thumb_id = get_term_meta($term_id, 'chapter-image-id', true);

                if (! empty($thumb_id)) {
                    $term_img = wp_get_attachment_url($thumb_id);
                    $content .= "<img src=\"$term_img\" width=\"100%\" height=\"auto\"/>";
                }

                return $content;
            }

            /* Make column sortable */

            public function toocheke_companion_chapter_add_image_column_sortable($sortable)
            {
                $sortable['chapter_image'] = 'chapter_image';
                return $sortable;
            }

            //Chapters page
            public function toocheke_companion_create_chapter_page_on_theme_activation()
            {

                // Set the title, template, etc
                $new_page_title    = __('Chapters', 'toocheke-companion'); // Page's title
                $new_page_content  = '';                                   // Content goes here
                $new_page_template = 'page-templates/comic-chapters.php';  // The template to use for the page
                $page_check_query  = new WP_Query(
                    [
                        'post_type'              => 'page',
                        'title'                  => $new_page_title,
                        'post_status'            => 'all',
                        'posts_per_page'         => 1,
                        'no_found_rows'          => true,
                        'ignore_sticky_posts'    => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                        'orderby'                => 'post_date ID',
                        'order'                  => 'ASC',
                    ]
                );

                if (! empty($page_check_query->post)) {
                    $page_check = $page_check_query->post;
                } else {
                    $page_check = null;
                }

                //$page_check = get_page_by_title($new_page_title); // Check if the page already exists --Deprecated in Wordpress 6.2
                // Store the above data in an array
                $new_page = [
                    'post_type'    => 'page',
                    'post_title'   => $new_page_title,
                    'post_content' => $new_page_content,
                    'post_status'  => 'publish',
                    'post_author'  => 1,
                    'post_slug'    => 'chapters',
                ];
                // If the page doesn't already exist, create it
                if (! isset($page_check->ID)) {
                    $new_page_id = wp_insert_post($new_page);
                    if (! empty($new_page_template)) {
                        update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
                    }
                }
            }
            /* Collection specific functions and terms */
            /*Add new Term*/
            public function toocheke_companion_collection_add_order_field($taxonomy)
            {
    ?><div class="form-field term-collection">
            <label for="collection-order"><?php _e('Order', 'toocheke-companion'); ?></label>
            <input type="number" min="1" name="collection-order" id="collection-order" value="1" class="collection-order-field"
                aria-required="true" required />
            <p>This determines what order the collection is in.</p>

        </div><?php
            }
            /*Save new Term*/
            public function toocheke_companion_collection_save_order_meta($term_id, $tt_id)
            {
                if (isset($_POST['collection-order']) && ! empty($_POST['collection-order'])) {
                    $order = absint(sanitize_title($_POST['collection-order']));
                    add_term_meta($term_id, 'collection-order', $order, true);
                } else {
                    add_term_meta($term_id, 'collection-order', 1, true);
                }
            }
            /*Updating Term*/
            public function toocheke_companion_collection_edit_order_field($term, $taxonomy)
            {

                // get current order
                $current_order = get_term_meta($term->term_id, 'collection-order', true);

                ?><tr class="form-field term-order-wrap">
            <th scope="row"><label for="collection-order"><?php _e('Order', 'toocheke-companion'); ?></label></th>
            <td>
                <input type="number" min="1" name="collection-order" id="collection-order"
                    value="<?php echo esc_attr($current_order); ?>" class="collection-order-field" aria-required="true"
                    required />
                <p>This determines what order the collection is in.</p>
            </td>
        </tr><?php
            }
            /*Save Data*/
            public function toocheke_companion_collection_update_order_meta($term_id, $tt_id)
            {

                if (isset($_POST['collection-order']) && ! empty($_POST['collection-order'])) {
                    $order = absint(sanitize_title($_POST['collection-order']));
                    update_term_meta($term_id, 'collection-order', $order);
                }
            }
            /*
 * Displaying the order column
 */
            public function toocheke_companion_collection_add_order_column($columns)
            {
                $columns['collection_order'] = __('Order', 'toocheke-companion');
                return $columns;
            }
            /* Add content into column */
            public function toocheke_companion_add_collection_column_content($content, $column_name, $term_id)
            {

                if ($column_name !== 'collection_order') {
                    return $content;
                }

                $term_id = absint($term_id);
                $order   = get_term_meta($term_id, 'collection-order', true);

                if (! empty($order)) {
                    $content .= esc_attr($order);
                }

                return $content;
            }

            /* Make column sortable */
            public function toocheke_companion_collection_add_order_column_sortable($sortable)
            {
                $sortable['collection_order'] = 'collection_order';
                return $sortable;
            }

            /* Sort columns */
            public function toocheke_companion_collection_sort_by_collection_order($term_query)
            {
                global $pagenow;
                if (! is_admin()) {
                    return $term_query;
                }
                // WP_Term_Query does not define a get() or a set() method so the query_vars member must
                // be manipulated directly
                if (is_admin() && $pagenow == 'edit-tags.php' && $term_query->query_vars['taxonomy'][0] == 'collections' && (! isset($_GET['orderby']) || $_GET['orderby'] == 'collection_order')) {
                    // set orderby to the named clause in the meta_query
                    $term_query->query_vars['orderby'] = 'order_clause';
                    $term_query->query_vars['order']   = isset($_GET['order']) ? sanitize_title($_GET['order']) : "DESC";
                    // the OR relation and the NOT EXISTS clause allow for terms without a meta_value at all
                    $args = [
                        'relation' => 'OR',
                        'order_clause'      => [
                            'key'  => 'collection-order',
                            'type' => 'NUMERIC',
                        ],
                        [
                            'key'     => 'collection-order',
                            'compare' => 'NOT EXISTS',
                        ],
                    ];
                    $term_query->meta_query = new WP_Meta_Query($args);
                }
                return $term_query;
            }

            /* Image Functions */
            public function toocheke_companion_collection_load_media()
            {
                if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'collections') {
                    return;
                }
                wp_enqueue_media();
            }

            /**
             * Add a form field in the new category page
             * @since 1.0.0
             */

            public function toocheke_companion_add_collection_image($taxonomy)
            { ?>
        <div class="form-field term-collection">
            <label for="collection-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            <input type="hidden" id="collection-image-id" name="collection-image-id" class="custom_media_url" value="">
            <div id="collection-image-wrapper"></div>
            <p>
                <input type="button" class="button button-secondary collections_tax_media_button"
                    id="collections_tax_media_button" name="collections_tax_media_button"
                    value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                <input type="button" class="button button-secondary collections_tax_media_remove"
                    id="collections_tax_media_remove" name="collections_tax_media_remove"
                    value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
            </p>
            <p>This is the featured image for the collection.</p>
        </div>
    <?php }

            /**
             * Save the form field
             * @since 1.0.0
             */
            public function toocheke_companion_save_collection_image($term_id, $tt_id)
            {
                if (isset($_POST['collection-image-id']) && '' !== $_POST['collection-image-id']) {
                    add_term_meta($term_id, 'collection-image-id', absint(sanitize_title($_POST['collection-image-id'])), true);
                }
            }

            /**
             * Edit the form field
             * @since 1.0.0
             */
            public function toocheke_companion_update_collection_image($term, $taxonomy)
            { ?>
        <tr class="form-field term-collection-wrap">
            <th scope="row">
                <label for="collection-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            </th>
            <td>
                <?php $image_id = get_term_meta($term->term_id, 'collection-image-id', true); ?>
                <input type="hidden" id="collection-image-id" name="collection-image-id"
                    value="<?php echo esc_attr($image_id); ?>">
                <div id="collection-image-wrapper">
                    <?php if ($image_id) { ?>
                        <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                    <?php } ?>
                </div>
                <p>
                    <input type="button" class="button button-secondary collections_tax_media_button"
                        id="collections_tax_media_button" name="collections_tax_media_button"
                        value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                    <input type="button" class="button button-secondary collections_tax_media_remove"
                        id="collections_tax_media_remove" name="collections_tax_media_remove"
                        value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
                </p>
                <p>This is the featured image for the collection.</p>
            </td>
        </tr>
    <?php }

            /**
             * Update the form field value
             * @since 1.0.0
             */
            public function toocheke_companion_updated_collection_image($term_id, $tt_id)
            {
                if (isset($_POST['collection-image-id']) && '' !== $_POST['collection-image-id']) {
                    update_term_meta($term_id, 'collection-image-id', absint(sanitize_title($_POST['collection-image-id'])));
                } else {
                    update_term_meta($term_id, 'collection-image-id', '');
                }
            }

            /**
             * Enqueue styles and scripts
             * @since 1.0.0
             */
            public function toocheke_companion_collection_add_script()
            {
                if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'collections') {
                    return;
                } ?>
        <script>
            jQuery(document).ready(function($) {

                _wpMediaViewsL10n.insertIntoPost = '<?php _e("Insert", 'toocheke-companion'); ?>';

                function ct_media_upload(button_class) {
                    var _custom_media = true,
                        _orig_send_attachment = wp.media.editor.send.attachment;
                    $('body').on('click', button_class, function(e) {
                        var button_id = '#' + $(this).attr('id');
                        var send_attachment_bkp = wp.media.editor.send.attachment;
                        var button = $(button_id);
                        _custom_media = true;

                        wp.media.editor.send.attachment = function(props, attachment) {
                            if (_custom_media) {
                                $('#collection-image-id').val(attachment.id);
                                $('#collection-image-wrapper').html(
                                    '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                                );
                                $('#collection-image-wrapper .custom_media_image').attr('src', attachment.url)
                                    .css('display', 'block');
                            } else {
                                return _orig_send_attachment.apply(button_id, [props, attachment]);
                            }
                        }
                        wp.media.editor.open(button);
                        return false;
                    });
                }
                ct_media_upload('.collections_tax_media_button.button');
                $('body').on('click', '.collections_tax_media_remove', function() {
                    $('#collection-image-id').val('');
                    $('#collection-image-wrapper').html(
                        '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                    );
                });
                // Thanks: http://stackoverflow.com/questions/15281995/wordpress-create-category-ajax-response
                $(document).ajaxComplete(function(event, xhr, settings) {
                    var queryStringArr = settings.data.split('&');

                    if ($.inArray('action=add-tag', queryStringArr) !== -1) {
                        var xml = xhr.responseXML;
                        $response = $(xml).find('term_id').text();
                        if ($response != "") {
                            // Clear the thumb image
                            $('#collection-image-wrapper').html('');
                            $('#collection-image-id').val('');
                        }
                    }
                });
            });
        </script>
    <?php
            }
            /* Functions for Comic Collection */
            /* Displaying image meta data in column */
            public function toocheke_companion_collection_add_image_column($columns)
            {
                $columns['collection_image'] = __('Image', 'toocheke-companion');
                return $columns;
            }
            /* Add content into column */
            public function toocheke_companion_add_collection_image_column_content($content, $column_name, $term_id)
            {

                if ($column_name !== 'collection_image') {
                    return $content;
                }

                $term_id  = absint($term_id);
                $thumb_id = get_term_meta($term_id, 'collection-image-id', true);

                if (! empty($thumb_id)) {
                    $term_img = wp_get_attachment_url($thumb_id);
                    $content .= "<img src=\"$term_img\" width=\"100%\" height=\"auto\"/>";
                }

                return $content;
            }

            /* Make column sortable */

            public function toocheke_companion_collection_add_image_column_sortable($sortable)
            {
                $sortable['collection_image'] = 'collection_image';
                return $sortable;
            }

            //Collections page
            public function toocheke_companion_create_collection_page_on_theme_activation()
            {

                // Set the title, template, etc
                $new_page_title    = __('Collections', 'toocheke-companion'); // Page's title
                $new_page_content  = '';                                      // Content goes here
                $new_page_template = 'page-templates/comic-collections.php';  // The template to use for the page
                $page_check_query  = new WP_Query(
                    [
                        'post_type'              => 'page',
                        'title'                  => $new_page_title,
                        'post_status'            => 'all',
                        'posts_per_page'         => 1,
                        'no_found_rows'          => true,
                        'ignore_sticky_posts'    => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                        'orderby'                => 'post_date ID',
                        'order'                  => 'ASC',
                    ]
                );

                if (! empty($page_check_query->post)) {
                    $page_check = $page_check_query->post;
                } else {
                    $page_check = null;
                }

                //$page_check = get_page_by_title($new_page_title); // Check if the page already exists --Deprecated in Wordpress 6.2
                // Store the above data in an array
                $new_page = [
                    'post_type'    => 'page',
                    'post_title'   => $new_page_title,
                    'post_content' => $new_page_content,
                    'post_status'  => 'publish',
                    'post_author'  => 1,
                    'post_slug'    => 'collections',
                ];
                // If the page doesn't already exist, create it
                if (! isset($page_check->ID)) {
                    $new_page_id = wp_insert_post($new_page);
                    if (! empty($new_page_template)) {
                        update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
                    }
                }
            }

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

            /* Social Sharing Functions */

            public function toocheke_add_sharing_icons()
            {
                $allowed_tags = [
                    'a'    => [
                        'title' => [],
                        'href'  => [],
                    ],
                    'i'    => [
                        'class' => [],
                    ],
                    'img'  => [
                        'class' => [],
                        'src'   => [],
                    ],
                    'svg'  => [
                        'xmlns'       => [],
                        'fill'        => [],
                        'viewbox'     => [],
                        'role'        => [],
                        'aria-hidden' => [],
                        'focusable'   => [],
                    ],
                    'path' => [
                        'd'    => [],
                        'fill' => [],
                    ],
                ];
                //custom button icons
                $display_default_button = get_option('toocheke-comics-navigation') && 1 == get_option('toocheke-comics-navigation');

                $facebook_image_button_url = get_option('toocheke-facebook-button');
                $facebook_button           = $display_default_button ? '<i class="fab fa-lg fa-facebook-f" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($facebook_image_button_url) . '" />';
                $twitter_image_button_url  = get_option('toocheke-twitter-button');
                $twitter_button            = $display_default_button ? '<i class="fab fa-lg fa-x-twitter" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($twitter_image_button_url) . '" />';
                $tumblr_image_button_url   = get_option('toocheke-tumblr-button');
                $tumblr_button             = $display_default_button ? '<i class="fab fa-lg fa-tumblr" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($tumblr_image_button_url) . '" />';
                $reddit_image_button_url   = get_option('toocheke-reddit-button');
                $reddit_button             = $display_default_button ? '<i class="fab fa-lg fa-reddit-alien" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($reddit_image_button_url) . '" />';
                $copy_image_button_url     = get_option('toocheke-copy-button');
                $copy_button               = $display_default_button ? '<i class="fas fa-lg fa-copy" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($copy_image_button_url) . '" />';
                $threads_image_button_url  = get_option('toocheke-threads-button');
                $threads_button            = $display_default_button ? '<i class="fab fa-lg fa-threads" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($threads_image_button_url) . '" />';
                $bluesky_image_button_url  = get_option('toocheke-bluesky-button');
                $bluesky_button            = $display_default_button ? '<i class="fab fa-lg fa-bluesky" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($bluesky_image_button_url) . '" />';
                $whatsapp_image_button_url = get_option('toocheke-whatsapp-button');
                $whatsapp_button           = $display_default_button ? '<i class="fab fa-lg fa-whatsapp" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($whatsapp_image_button_url) . '" />';
                $linkedin_image_button_url = get_option('toocheke-linkedin-button');
                $linkedin_button           = $display_default_button ? '<i class="fab fa-lg fa-linkedin" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($linkedin_image_button_url) . '" />';

                $html         = "";
                $comic_url    = home_url($_SERVER['REQUEST_URI']);
                $social_url   = urlencode(home_url($_SERVER['REQUEST_URI']));
                $social_title = urlencode(html_entity_decode(get_the_title(), ENT_COMPAT, 'UTF-8'));
                $social_media = urlencode(get_the_post_thumbnail_url(get_the_ID(), 'full'));
                $facebook_url = 'https://www.facebook.com/sharer?u=' . $social_url . '&amp;t=' . $social_title;
                $twitter_url  = 'https://twitter.com/intent/tweet?text=' . $social_title . '&amp;url=' . $social_url;
                $tumblr_url   = 'https://tumblr.com/widgets/share/tool?canonicalUrl=' . $social_url;
                $reddit_url   = 'https://www.reddit.com/submit?url=' . $social_url . '&amp;title=' . $social_title;
                $threads_url  = 'https://threads.net/intent/post?text=' . $social_title . ' ' . $social_url;
                $bluesky_url  = 'https://bsky.app/intent/compose?text=' . $social_title . ' ' . $social_url;
                $whatsapp_url = 'https://api.whatsapp.com/send?text=' . $social_title . ' ' . $social_url;
                $linkedin_url = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $social_url;

                if (get_option("toocheke-social-share-facebook") == 1) {
                    $html = $html . "<a href='" . esc_url($facebook_url) . "' title='Share on Facebook' target='_blank'>" . wp_kses($facebook_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-twitter") == 1) {
                    $html = $html . "<a href='" . esc_url($twitter_url) . "' title='Share on Twitter' target='_blank'>" . wp_kses($twitter_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-tumblr") == 1) {
                    $html = $html . "<a href='" . esc_url($tumblr_url) . "' title='Share on Tumblr' target='_blank'>" . wp_kses($tumblr_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-reddit") == 1) {
                    $html = $html . "<a href='" . esc_url($reddit_url) . "' title='Share on Reddit' target='_blank'>" . wp_kses($reddit_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-threads") == 1) {
                    $html = $html . "<a href='" . esc_url($threads_url) . "' title='Share on Threads' target='_blank'>" . wp_kses($threads_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-bluesky") == 1) {
                    $html = $html . "<a href='" . esc_url($bluesky_url) . "' title='Share on Bluesky' target='_blank'>" . wp_kses($bluesky_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-whatsapp") == 1) {
                    $html = $html . "<a href='" . esc_url($whatsapp_url) . "' title='Share on WhatsApp' target='_blank'>" . wp_kses($whatsapp_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-linkedin") == 1) {
                    $html = $html . "<a href='" . esc_url($linkedin_url) . "' title='Share on LinkedIn' target='_blank'>" . wp_kses($linkedin_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-copy") == 1) {
                    $html = $html . "<a id='copy-link' data-url='" . esc_url($comic_url) . "' href='javascript:;' title='Copy link'>" . wp_kses($copy_button, $allowed_tags) . "</a>";
                }

                echo $html;
            }
            /* Support Link Functions */
            public function toocheke_add_support_icons()
            {
                $allowed_tags = [
                    'a'    => [
                        'title' => [],
                        'href'  => [],
                    ],
                    'i'    => [
                        'class' => [],
                    ],
                    'img'  => [
                        'class' => [],
                        'src'   => [],
                    ],
                    'svg'  => [
                        'xmlns'       => [],
                        'fill'        => [],
                        'viewbox'     => [],
                        'role'        => [],
                        'aria-hidden' => [],
                        'focusable'   => [],
                    ],
                    'path' => [
                        'd'    => [],
                        'fill' => [],
                    ],
                ];
                //custom button icons
                $display_default_button = get_option('toocheke-comics-navigation') && 1 == get_option('toocheke-comics-navigation');

                $buymeacoffee_image_button_url = get_option('toocheke-buymeacoffee-button');
                $buymeacoffee_button           = $display_default_button ? '<svg fill="#000000" width="800px" height="800px" viewBox="0 0 24 24" role="img" xmlns="http://www.w3.org/2000/svg"><path d="m20.216 6.415-.132-.666c-.119-.598-.388-1.163-1.001-1.379-.197-.069-.42-.098-.57-.241-.152-.143-.196-.366-.231-.572-.065-.378-.125-.756-.192-1.133-.057-.325-.102-.69-.25-.987-.195-.4-.597-.634-.996-.788a5.723 5.723 0 0 0-.626-.194c-1-.263-2.05-.36-3.077-.416a25.834 25.834 0 0 0-3.7.062c-.915.083-1.88.184-2.75.5-.318.116-.646.256-.888.501-.297.302-.393.77-.177 1.146.154.267.415.456.692.58.36.162.737.284 1.123.366 1.075.238 2.189.331 3.287.37 1.218.05 2.437.01 3.65-.118.299-.033.598-.073.896-.119.352-.054.578-.513.474-.834-.124-.383-.457-.531-.834-.473-.466.074-.96.108-1.382.146-1.177.08-2.358.082-3.536.006a22.228 22.228 0 0 1-1.157-.107c-.086-.01-.18-.025-.258-.036-.243-.036-.484-.08-.724-.13-.111-.027-.111-.185 0-.212h.005c.277-.06.557-.108.838-.147h.002c.131-.009.263-.032.394-.048a25.076 25.076 0 0 1 3.426-.12c.674.019 1.347.067 2.017.144l.228.031c.267.04.533.088.798.145.392.085.895.113 1.07.542.055.137.08.288.111.431l.319 1.484a.237.237 0 0 1-.199.284h-.003c-.037.006-.075.01-.112.015a36.704 36.704 0 0 1-4.743.295 37.059 37.059 0 0 1-4.699-.304c-.14-.017-.293-.042-.417-.06-.326-.048-.649-.108-.973-.161-.393-.065-.768-.032-1.123.161-.29.16-.527.404-.675.701-.154.316-.199.66-.267 1-.069.34-.176.707-.135 1.056.087.753.613 1.365 1.37 1.502a39.69 39.69 0 0 0 11.343.376.483.483 0 0 1 .535.53l-.071.697-1.018 9.907c-.041.41-.047.832-.125 1.237-.122.637-.553 1.028-1.182 1.171-.577.131-1.165.2-1.756.205-.656.004-1.31-.025-1.966-.022-.699.004-1.556-.06-2.095-.58-.475-.458-.54-1.174-.605-1.793l-.731-7.013-.322-3.094c-.037-.351-.286-.695-.678-.678-.336.015-.718.3-.678.679l.228 2.185.949 9.112c.147 1.344 1.174 2.068 2.446 2.272.742.12 1.503.144 2.257.156.966.016 1.942.053 2.892-.122 1.408-.258 2.465-1.198 2.616-2.657.34-3.332.683-6.663 1.024-9.995l.215-2.087a.484.484 0 0 1 .39-.426c.402-.078.787-.212 1.074-.518.455-.488.546-1.124.385-1.766zm-1.478.772c-.145.137-.363.201-.578.233-2.416.359-4.866.54-7.308.46-1.748-.06-3.477-.254-5.207-.498-.17-.024-.353-.055-.47-.18-.22-.236-.111-.71-.054-.995.052-.26.152-.609.463-.646.484-.057 1.046.148 1.526.22.577.088 1.156.159 1.737.212 2.48.226 5.002.19 7.472-.14.45-.06.899-.13 1.345-.21.399-.072.84-.206 1.08.206.166.281.188.657.162.974a.544.544 0 0 1-.169.364zm-6.159 3.9c-.862.37-1.84.788-3.109.788a5.884 5.884 0 0 1-1.569-.217l.877 9.004c.065.78.717 1.38 1.5 1.38 0 0 1.243.065 1.658.065.447 0 1.786-.065 1.786-.065.783 0 1.434-.6 1.499-1.38l.94-9.95a3.996 3.996 0 0 0-1.322-.238c-.826 0-1.491.284-2.26.613z"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($buymeacoffee_image_button_url) . '" />';
                $gumroad_image_button_url      = get_option('toocheke-gumroad-button');
                $gumroad_button                = $display_default_button ? '<svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><path d="m48 24c0 13.255-10.745 24-24 24s-24-10.745-24-24 10.745-24 24-24 24 10.745 24 24zm-37.25-0.5577c0 7.0142 4.1354 12.653 11.166 12.653 7.0304 0 8.8223-5.6388 9.2358-8.5269v7.9768h5.7105v-12.653h-13.016v2.4754h6.2033c-0.6893 3.0258-2.3435 6.0515-6.2033 6.0515-4.2733 0-7.0303-3.7134-7.0303-8.252 0-4.5385 2.757-8.2519 7.0303-8.2519 3.9977 0 5.6519 2.8882 5.7897 4.8136h6.4788c-0.1378-3.4383-3.1705-9.4896-12.131-9.4896-8.6844 0-13.234 5.9139-13.234 13.203z" clip-rule="evenodd" fill="#fff" fill-rule="evenodd"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($gumroad_image_button_url) . '" />';
                $indiegogo_image_button_url    = get_option('toocheke-indiegogo-button');
                $indiegogo_button              = $display_default_button ? '<svg xmlns="http://www.w3.org/2000/svg" width="321.547" height="186.016" viewBox="0 0 321.547 186.016" xml:space="preserve"><g fill="#FFF"><path d="M242.411 0c-67.313 0-79.365 44.115-79.365 92.779 0 48.438 12.053 92.553 79.365 92.553 67.082 0 79.135-44.115 79.135-92.553C321.546 44.342 309.95 0 242.411 0zm0 142.809c-26.607 0-30.246-18.42-30.246-48.209 0-29.563 3.639-47.983 30.246-47.983 26.377 0 30.016 18.42 30.016 47.983 0 29.789-3.639 48.209-30.016 48.209zM70.267 113.473h31.154v3.186c0 20.012.682 28.881-23.195 28.881-22.285 0-29.79-10.234-29.79-50.939 0-42.524 12.734-47.526 31.837-47.526 14.1 0 24.104 3.639 32.52 8.414l25.924-40.25C125.069 6.369 105.06.684 82.547.684 20.012.684 0 30.701 0 93.463c0 54.805 14.554 92.553 73.905 92.553 10.46 0 19.558-.682 27.287-2.273h44.572V74.135h-70.04l-5.457 39.338z"/></g></svg>' : '<img class="comic-image-nav" src="' . esc_attr($indiegogo_image_button_url) . '" />';
                $kickstarter_image_button_url  = get_option('toocheke-kickstarter-button');
                $kickstarter_button            = $display_default_button ? '<i class="fab fa-lg fa-kickstarter" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($kickstarter_image_button_url) . '" />';
                $kofi_image_button_url         = get_option('toocheke-kofi-button');
                $kofi_button                   = $display_default_button ? '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M23.881 8.948c-.773-4.085-4.859-4.593-4.859-4.593H.723c-.604 0-.679.798-.679.798s-.082 7.324-.022 11.822c.164 2.424 2.586 2.672 2.586 2.672s8.267-.023 11.966-.049c2.438-.426 2.683-2.566 2.658-3.734 4.352.24 7.422-2.831 6.649-6.916zm-11.062 3.511c-1.246 1.453-4.011 3.976-4.011 3.976s-.121.119-.31.023c-.076-.057-.108-.09-.108-.09-.443-.441-3.368-3.049-4.034-3.954-.709-.965-1.041-2.7-.091-3.71.951-1.01 3.005-1.086 4.363.407 0 0 1.565-1.782 3.468-.963 1.904.82 1.832 3.011.723 4.311zm6.173.478c-.928.116-1.682.028-1.682.028V7.284h1.77s1.971.551 1.971 2.638c0 1.913-.985 2.667-2.059 3.015z"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($kofi_image_button_url) . '" />';
                $liberapay_image_button_url    = get_option('toocheke-liberapay-button');
                $liberapay_button              = $display_default_button ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80"><g fill="#1a171b"><path d="M25.91 63.04c-3.571 0-6.373-.466-8.41-1.396-2.037-.93-3.495-2.199-4.375-3.809-.88-1.609-1.308-3.457-1.282-5.544.025-2.086.313-4.311.868-6.675l9.579-40.05 11.69-1.81-10.484 43.44a13.563 13.563 0 0 0-.339 2.489c-.026.754.113 1.421.415 1.999.302.579.817 1.044 1.546 1.395.729.353 1.747.579 3.055.679l-2.263 9.278M68.15 38.08c0 3.671-.604 7.03-1.811 10.07-1.207 3.043-2.879 5.669-5.01 7.881-2.138 2.213-4.702 3.935-7.693 5.167-2.992 1.231-6.248 1.848-9.767 1.848-1.71 0-3.42-.151-5.129-.453l-3.394 13.651H24.184l12.52-52.19c2.01-.603 4.311-1.143 6.901-1.622 2.589-.477 5.393-.716 8.41-.716 2.815 0 5.242.428 7.278 1.282 2.037.855 3.708 2.024 5.02 3.507 1.307 1.484 2.274 3.219 2.904 5.205.627 1.987.942 4.11.942 6.373M40.781 53.544c.854.202 1.91.302 3.167.302 1.961 0 3.746-.364 5.355-1.094a11.799 11.799 0 0 0 4.111-3.055c1.131-1.307 2.01-2.877 2.64-4.714.628-1.835.943-3.858.943-6.071 0-2.161-.479-3.998-1.433-5.506-.956-1.508-2.615-2.263-4.978-2.263-1.61 0-3.118.151-4.525.453l-5.28 21.948"/></g></svg>' : '<img class="comic-image-nav" src="' . esc_attr($liberapay_image_button_url) . '" />';
                $patreon_image_button_url      = get_option('toocheke-patreon-button');
                $patreon_button                = $display_default_button ? '<i class="fab fa-lg fa-patreon" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($patreon_image_button_url) . '" />';
                $paypal_image_button_url       = get_option('toocheke-paypal-button');
                $paypal_button                 = $display_default_button ? '<i class="fab fa-lg fa-paypal" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($paypal_image_button_url) . '" />';
                $substack_image_button_url     = get_option('toocheke-substack-button');
                $substack_button               = $display_default_button ? '<svg xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 448 511.471"><path fill="#FF681A" d="M0 0h448v62.804H0V0zm0 229.083h448v282.388L223.954 385.808 0 511.471V229.083zm0-114.542h448v62.804H0v-62.804z"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($substack_image_button_url) . '" />';
                $tipeee_image_button_url       = get_option('toocheke-tipeee-button');
                $tipeee_button                 = $display_default_button ? '<svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" version="1.1" id="Layer_1" x="0px" y="0px" width="888.00201" height="335.15942" viewBox="0 0 888.00201 335.15942" enable-background="new 0 0 1009 472" xml:space="preserve" sodipodi:docname="Tipeee_logo_.svg" inkscape:version="0.92.1 r15371"><metadata id="metadata41"><rdf:RDF><cc:Work rdf:about=""><dc:format></dc:format><dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage" /><dc:title></dc:title></cc:Work></rdf:RDF></metadata><defs id="defs39" /><sodipodi:namedview pagecolor="#ffffff" bordercolor="#666666" borderopacity="1" objecttolerance="10" gridtolerance="10" guidetolerance="10" inkscape:pageopacity="0" inkscape:pageshadow="2" inkscape:window-width="1920" inkscape:window-height="1017" id="namedview37" showgrid="false" fit-margin-top="0" fit-margin-left="0" fit-margin-right="0" fit-margin-bottom="0" inkscape:zoom="0.70564916" inkscape:cx="301.30419" inkscape:cy="163.71331" inkscape:window-x="-8" inkscape:window-y="-8" inkscape:window-maximized="1" inkscape:current-layer="Layer_1" /><path d="m 888.002,134.4 c -0.808,1.266 -0.516,2.772 -0.922,4.146 -2.004,6.776 -5.498,12.746 -9.693,18.348 -7.275,9.714 -15.759,18.295 -25.001,26.111 -24.586,20.791 -51.223,38.508 -80.07,52.844 -17.634,8.763 -35.871,16.008 -54.891,21.184 -9.907,2.696 -19.967,4.514 -30.292,3.996 -11.321,-0.566 -21.675,-3.788 -30.269,-11.57 -2.327,-2.107 -4.334,-4.483 -6.127,-7.044 -1.036,-1.48 -0.741,-1.7 -2.768,-0.567 -8.1,4.527 -16.448,8.52 -25.168,11.724 -9.337,3.431 -18.914,5.863 -28.812,6.909 -10.236,1.081 -20.275,0.364 -29.85,-3.799 -7.308,-3.178 -13.14,-8.147 -17.56,-14.762 -1.194,-1.787 -1.045,-1.589 -2.743,-0.626 -9.887,5.608 -20.108,10.485 -30.848,14.252 -8.584,3.011 -17.401,4.965 -26.494,4.968 -18.408,0.006 -32.272,-7.824 -40.765,-24.471 -0.252,-0.494 -0.524,-0.979 -0.797,-1.464 -0.035,-0.063 -0.126,-0.096 -0.227,-0.167 -3.169,1.447 -6.352,2.919 -9.551,4.354 -2.694,1.208 -5.523,1.681 -8.424,1.064 -5.737,-1.218 -9.247,-7.016 -7.993,-13.085 0.727,-3.519 2.583,-6.049 6.032,-7.477 4.499,-1.861 8.899,-3.957 13.24,-6.176 1.009,-0.517 1.357,-1.134 1.269,-2.24 -0.185,-2.308 -0.334,-4.624 -0.354,-6.938 -0.14,-15.692 2.429,-31.027 6.25,-46.181 3.527,-13.989 8.275,-27.534 15.057,-40.311 7.707,-14.522 17.6,-27.226 30.897,-37.098 7.085,-5.26 14.812,-9.122 23.49,-10.944 6.183,-1.297 12.361,-1.54 18.499,0.239 7.429,2.152 12.691,6.897 16.088,13.749 3.65,7.36 4.784,15.163 3.938,23.311 -1.159,11.17 -5.228,21.383 -10.352,31.208 -9.628,18.463 -21.434,35.41 -35.785,50.538 -12.108,12.764 -25.558,23.912 -40.34,33.451 -0.403,0.261 -0.795,0.54 -1.209,0.781 -0.659,0.385 -0.789,0.825 -0.486,1.582 3.493,8.741 9.256,13.74 19.354,14.239 7.398,0.366 14.451,-1.405 21.318,-3.883 10.852,-3.915 21.068,-9.189 31.074,-14.896 1.266,-0.722 1.572,-1.539 1.398,-2.886 -0.733,-5.651 -0.748,-11.34 -0.367,-17.003 0.757,-11.266 2.652,-22.363 5.617,-33.262 5.103,-18.759 12.393,-36.624 22.393,-53.313 7.249,-12.099 16.254,-22.76 27.271,-31.634 8.438,-6.796 17.99,-11.171 28.688,-13.079 15.613,-2.785 29.301,7.075 33.52,20.471 2.778,8.822 2.425,17.651 0.221,26.493 -1.672,6.711 -4.599,12.943 -7.619,19.115 -8.989,18.372 -20.758,34.861 -34.202,50.205 -14.088,16.078 -29.968,30.146 -47.496,42.365 -0.328,0.229 -0.642,0.481 -0.985,0.684 -0.728,0.429 -0.691,0.925 -0.282,1.589 1.535,2.494 3.463,4.597 6.011,6.07 3.821,2.211 7.958,3.13 12.366,3.274 8.582,0.282 16.854,-1.33 25.032,-3.681 11.449,-3.291 21.973,-8.638 32.274,-14.487 1.053,-0.599 1.468,-1.3 1.248,-2.507 -0.679,-3.711 -0.709,-7.49 -0.717,-11.229 -0.01,-4.704 0.242,-9.42 0.81,-14.107 1.678,-13.852 4.782,-27.371 9.423,-40.528 5.765,-16.34 12.854,-32.044 22.735,-46.358 8.224,-11.914 18.105,-22.143 30.725,-29.496 6.117,-3.564 12.546,-6.339 19.569,-7.542 8.423,-1.444 16.291,-0.253 23.01,5.396 5.617,4.722 9.184,10.806 10.988,17.9 2.037,8.002 1.555,16.013 -0.173,23.987 -2.271,10.486 -6.552,20.185 -11.762,29.505 -8.04,14.383 -17.938,27.412 -28.972,39.604 -14.482,16.001 -30.328,30.486 -47.944,42.994 -0.39,0.277 -0.813,0.52 -1.054,1.122 2.426,4.136 6.092,6.755 10.662,8.251 4.275,1.4 8.682,1.249 13.071,1.086 9.665,-0.358 18.927,-2.859 28.127,-5.569 12.521,-3.688 24.511,-8.746 36.249,-14.41 23.827,-11.5 46.328,-25.189 67.49,-41.068 11.222,-8.42 21.671,-17.698 30.984,-28.22 3.727,-4.209 7.033,-8.703 9.54,-13.755 0.968,-1.95 1.577,-3.993 1.798,-6.173 0.507,-4.99 4.329,-8.887 9.354,-9.473 2.308,-0.269 4.615,-0.238 6.836,0.719 2.736,1.179 4.492,3.196 5.445,5.973 0.003,2.558 0.003,5.118 0.003,7.678 z m -224.655,70.47 c 1.066,-0.177 1.524,-0.771 2.073,-1.201 10.659,-8.334 20.6,-17.46 29.987,-27.198 13.38,-13.879 25.09,-29.003 34.116,-46.089 4.32,-8.177 7.582,-16.755 8.01,-26.128 0.153,-3.341 -0.108,-6.702 -1.729,-9.784 -1.312,-2.496 -3.34,-3.713 -6.161,-3.837 -3.565,-0.157 -6.763,1.021 -9.878,2.512 -6.728,3.222 -12.306,7.983 -17.504,13.234 -9.058,9.149 -15.565,20.026 -21.168,31.491 -7.261,14.859 -12.38,30.452 -15.631,46.657 -1.335,6.659 -2.155,13.382 -2.115,20.343 z m -103.604,-0.794 c 0.846,-0.074 1.177,-0.454 1.547,-0.736 9.423,-7.182 18.313,-14.962 26.626,-23.416 14.95,-15.205 27.562,-32.133 37.733,-50.872 2.935,-5.407 5.771,-10.866 7.667,-16.756 1.459,-4.534 2.299,-9.14 1.759,-13.917 -0.668,-5.908 -4.334,-8.783 -10.223,-8.041 -0.315,0.04 -0.623,0.135 -0.937,0.194 -3.896,0.732 -7.479,2.273 -10.863,4.287 -9.443,5.621 -16.862,13.413 -23.286,22.175 -5.209,7.104 -9.545,14.763 -13.268,22.749 -7.192,15.429 -12.214,31.559 -15.258,48.295 -0.956,5.257 -1.412,10.588 -1.497,16.038 z m -98.412,-5.608 c 0.758,0.03 1.063,-0.241 1.381,-0.464 10.181,-7.147 19.467,-15.322 28.06,-24.307 11.567,-12.095 21.104,-25.682 29.388,-40.165 4.615,-8.07 8.449,-16.507 10.43,-25.661 0.764,-3.533 1.058,-7.11 0.282,-10.706 -1.098,-5.089 -3.827,-7.27 -8.972,-6.95 -3.826,0.238 -7.3,1.73 -10.606,3.521 -10.578,5.729 -18.648,14.223 -25.488,23.904 -4.858,6.875 -8.458,14.487 -11.575,22.3 -5.319,13.333 -9.088,27.106 -11.384,41.271 -0.922,5.678 -1.397,11.412 -1.516,17.257 z" id="path4" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 0,71.04 c 0.153,-0.028 0.394,-0.005 0.445,-0.093 1.374,-2.352 3.553,-2.599 6.001,-2.527 3.917,0.115 7.838,0.051 11.758,0.021 1.689,-0.013 1.82,-0.072 1.739,-1.782 -0.287,-6.073 -0.135,-12.15 -0.219,-18.224 -0.051,-3.679 -0.1,-7.36 -0.023,-11.037 0.103,-4.929 2.468,-7.79 7.172,-9.198 13.766,-4.122 27.585,-8.069 41.186,-12.728 1.127,-0.387 2.308,-0.654 3.485,-0.856 4.409,-0.756 7.554,1.577 8.08,6.022 0.14,1.183 0.055,2.394 0.055,3.592 0.002,13.998 -0.001,27.996 0.005,41.995 0.001,2.195 0.022,2.221 2.276,2.224 9.599,0.01 19.197,0.003 28.796,0.007 0.719,0 1.446,-0.035 2.156,0.058 1.87,0.244 3.178,1.599 3.404,3.479 0.057,0.474 0.036,0.958 0.036,1.438 0.002,13.678 0.008,27.356 -0.003,41.035 -0.003,3.486 -1.474,4.938 -4.956,4.985 -0.48,0.006 -0.96,0.001 -1.44,0.001 -9.119,0 -18.238,-0.008 -27.356,0.004 -3.323,0.004 -2.909,-0.317 -2.91,2.97 -0.011,22.797 -0.008,45.594 -10e-4,68.392 0,1.596 -0.013,3.188 0.337,4.771 0.936,4.224 3.448,6.624 7.705,7.493 3.961,0.81 7.802,0.111 11.63,-0.737 3.669,-0.813 7.221,-2.026 10.671,-3.524 0.293,-0.128 0.58,-0.272 0.877,-0.387 3.363,-1.296 5.284,-0.074 5.427,3.548 0.091,2.316 0.022,4.64 0.022,6.959 0,10.799 0,21.597 0,32.396 0,4.617 -1.91,7.508 -6.217,9.18 -7.152,2.776 -14.447,5.099 -21.913,6.901 -8.99,2.172 -18.082,3.348 -27.323,2.715 -10.777,-0.738 -20.479,-4.16 -28.149,-12.155 -5.874,-6.121 -9.208,-13.571 -11.084,-21.729 -1.078,-4.685 -1.539,-9.441 -1.529,-14.266 0.046,-23.598 0.026,-47.194 0.018,-70.792 -0.002,-6.479 -0.043,-12.957 -0.071,-19.435 -0.01,-2.29 -0.017,-2.298 -2.393,-2.303 -3.839,-0.007 -7.682,-0.084 -11.518,0.029 C 3.848,119.55 1.899,119.099 0.604,116.988 0.498,116.815 0.206,116.755 0,116.642 0,101.44 0,86.24 0,71.04 Z" id="path6" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d84759;fill-rule:evenodd" /><path d="m 188.161,0 c 4.22,0.528 8.272,1.563 12.077,3.564 11.113,5.844 17.558,18.132 16.395,30.657 -1.448,15.596 -15.105,29.037 -32.729,28.094 -17.141,-0.917 -28.681,-14.862 -29.471,-29.28 -0.836,-15.238 9.33,-28.896 24.068,-32.327 0.978,-0.228 2.095,0.062 2.94,-0.708 2.239,0 4.479,0 6.72,0 z" id="path8" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 317.083,74.01 c 3.69,-1.357 7.203,-2.644 10.853,-3.487 20.368,-4.706 39.551,-1.717 57.351,9.216 17.688,10.864 29.574,26.574 37.117,45.721 3.983,10.108 6.2,20.622 6.897,31.464 1.342,20.851 -2.648,40.569 -12.809,58.881 -8.914,16.065 -21.302,28.659 -37.92,36.854 -12.342,6.086 -25.404,8.436 -39.072,7.411 -6.881,-0.516 -13.566,-2.074 -20.037,-4.512 -0.722,-0.271 -1.404,-0.789 -2.244,-0.626 -0.506,0.363 -0.334,0.885 -0.334,1.326 -0.014,8.639 -0.016,17.276 -0.006,25.914 0.002,1.954 0.028,1.975 1.961,1.983 3.519,0.017 7.039,-0.008 10.558,0.009 3.3,0.016 4.827,1.448 5.074,4.734 0.042,0.557 0.015,1.119 0.015,1.679 0,12.718 -0.001,25.435 0.003,38.151 0,1.121 0.01,2.237 -0.31,3.329 -0.545,1.861 -1.888,2.968 -3.797,3.083 -0.478,0.029 -0.959,0.007 -1.439,0.007 -28.953,0 -57.907,0.001 -86.86,-0.002 -0.719,0 -1.455,0.061 -2.153,-0.068 -1.674,-0.311 -3.035,-1.901 -3.253,-3.726 -0.066,-0.553 -0.035,-1.118 -0.035,-1.678 -0.002,-13.356 -0.003,-26.714 0.002,-40.07 10e-4,-0.953 -0.094,-1.917 0.341,-2.829 0.845,-1.771 2.22,-2.632 4.19,-2.62 3.759,0.022 7.518,0.016 11.277,0.002 1.985,-0.007 1.986,-0.024 2.021,-1.913 0.007,-0.399 0.001,-0.8 0.001,-1.199 0,-52.868 0.003,-105.736 -0.006,-158.604 -0.001,-3.582 0.482,-3.179 -3.179,-3.2 -3.199,-0.019 -6.399,0.027 -9.598,-0.013 -3.587,-0.044 -5.043,-1.541 -5.046,-5.175 -0.011,-13.517 -0.011,-27.034 0,-40.55 0.003,-3.748 1.51,-5.33 5.189,-5.333 23.275,-0.019 46.549,-0.019 69.824,0 3.653,0.005 4.788,1.232 5.424,5.841 z m 59.739,90.169 c -0.021,-8.239 -1.26,-15.685 -3.907,-22.781 -2.346,-6.289 -5.65,-11.978 -10.606,-16.616 -4.109,-3.845 -8.893,-6.285 -14.541,-6.563 -5.228,-0.257 -9.869,1.603 -14.009,4.739 -5.029,3.811 -8.625,8.785 -11.144,14.478 -7.364,16.646 -7.682,33.574 -1.499,50.625 2.262,6.237 5.732,11.767 10.702,16.261 8.65,7.822 19.684,8.163 28.784,0.894 4.134,-3.302 7.178,-7.481 9.589,-12.137 4.694,-9.06 6.592,-18.77 6.631,-28.9 z" id="path10" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d84759;fill-rule:evenodd" /><path d="m 154.325,137.344 c -0.886,-0.34 -1.612,0.068 -2.366,0.293 -3.06,0.913 -6.114,1.846 -9.186,2.719 -2.521,0.716 -4.661,-0.659 -5.076,-3.242 -0.126,-0.784 -0.125,-1.593 -0.125,-2.39 -0.007,-12.479 -0.006,-24.958 -0.002,-37.437 0,-0.719 0.013,-1.44 0.075,-2.156 0.284,-3.251 1.45,-4.731 4.576,-5.672 11.924,-3.591 23.854,-7.158 35.782,-10.737 10.782,-3.235 21.563,-6.474 32.345,-9.707 0.611,-0.183 1.224,-0.401 1.852,-0.483 2.247,-0.294 4.131,1.14 4.416,3.385 0.11,0.867 0.058,1.756 0.058,2.636 0.002,43.916 0,87.832 0.006,131.748 0,3.457 -0.295,2.951 2.898,2.976 3.359,0.025 6.72,-0.022 10.079,0.015 2.985,0.033 4.573,1.621 4.578,4.63 0.019,13.919 0.02,27.838 0,41.756 -0.004,3.195 -1.554,4.558 -5.063,4.559 -25.277,0.004 -50.555,0.002 -75.833,0.002 -3.92,0 -7.839,0.019 -11.759,-0.007 -3.161,-0.021 -4.69,-1.536 -4.694,-4.723 -0.019,-13.839 -0.02,-27.678 0.001,-41.517 0.005,-3.105 1.631,-4.679 4.773,-4.703 3.36,-0.026 6.72,0.005 10.079,-0.01 3.057,-0.013 2.741,0.321 2.748,-2.639 0.014,-5.76 0.004,-11.519 0.004,-17.278 0,-16.479 0.002,-32.957 -0.005,-49.436 -0.004,-0.867 0.115,-1.751 -0.161,-2.582 z" id="path12" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d84759;fill-rule:evenodd" /><path d="m 822.01,291.137 c 3.731,-4.199 7.707,-7.141 13.269,-6.765 5.153,0.348 9.708,1.923 12.644,6.539 1.042,-0.377 1.522,-1.156 2.14,-1.749 6.37,-6.127 15.79,-5.729 21.668,-2.641 3.534,1.858 5.53,4.979 6.719,8.683 1.236,3.854 1.466,7.837 1.467,11.846 10e-4,3.84 -0.017,7.681 0.009,11.52 0.012,1.715 0.051,1.715 1.741,1.786 2.977,0.124 2.977,0.124 2.977,3.173 10e-4,2.64 0.014,5.28 -0.004,7.92 -0.014,1.903 -0.032,1.95 -1.783,1.955 -6.96,0.02 -13.92,0.022 -20.88,-0.004 -1.684,-0.006 -1.731,-0.067 -1.731,-1.793 -0.003,-8.399 0.031,-16.799 0.027,-25.198 -10e-4,-1.749 0.082,-3.507 -0.395,-5.231 -0.443,-1.604 -1.297,-2.754 -3.038,-2.975 -1.71,-0.217 -3.524,0.844 -4.336,2.434 -0.487,0.955 -0.649,1.99 -0.657,3.024 -0.039,5.119 -0.01,10.239 -0.047,15.358 -0.008,1.105 0.352,1.635 1.521,1.56 0.956,-0.062 1.92,0.015 2.879,-0.01 0.795,-0.021 1.162,0.291 1.157,1.12 -0.018,3.52 -0.013,7.039 0.002,10.56 0.003,0.802 -0.352,1.168 -1.148,1.158 -0.88,-0.012 -1.76,0.005 -2.64,0.006 -6.319,0 -12.64,0.011 -18.959,-0.007 -1.835,-0.006 -1.871,-0.044 -1.875,-1.863 -0.016,-7.68 -0.025,-15.359 0.004,-23.039 0.01,-2.325 -0.123,-4.644 -0.48,-6.928 -0.451,-2.88 -2.993,-4.178 -5.499,-2.938 -1.441,0.713 -2.011,2.024 -2.118,3.526 -0.098,1.354 -0.067,2.718 -0.069,4.077 -0.007,4.239 -0.017,8.479 0.005,12.72 0.009,1.699 0.046,1.681 1.715,1.76 1.188,0.057 2.653,-0.496 3.494,0.263 0.966,0.873 0.372,2.427 0.406,3.68 0.062,2.238 0.035,4.479 0.012,6.72 -0.02,1.983 -0.039,2.019 -2.001,2.023 -6.72,0.018 -13.44,0.007 -20.16,0.007 -1.6,0 -3.2,-0.027 -4.8,0.006 -0.993,0.021 -1.465,-0.35 -1.455,-1.395 0.029,-3.279 0.038,-6.561 -0.004,-9.84 -0.015,-1.178 0.509,-1.579 1.605,-1.475 0.396,0.038 0.801,-0.02 1.199,0.005 0.92,0.059 1.27,-0.353 1.267,-1.273 -0.021,-6.561 -0.021,-13.12 0,-19.68 0.003,-0.924 -0.351,-1.332 -1.27,-1.271 -0.318,0.021 -0.64,0.01 -0.959,-0.003 -1.751,-0.07 -1.802,-0.072 -1.819,-1.709 -0.036,-3.199 0.015,-6.399 -0.022,-9.6 -0.013,-1.139 0.436,-1.531 1.57,-1.521 5.04,0.043 10.08,0.041 15.12,-0.001 1.038,-0.009 1.609,0.31 1.929,1.334 0.399,1.289 0.967,2.527 1.603,4.146 z" id="path14" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 771.929,284.634 c 4.882,-0.165 9.529,0.805 13.86,3.088 7.266,3.829 11.206,9.983 12.411,17.995 0.564,3.753 0.454,7.5 -0.507,11.176 -1.902,7.287 -6.343,12.521 -13.299,15.335 -9.175,3.712 -18.288,3.34 -26.994,-1.519 -6.144,-3.429 -9.838,-8.775 -11.076,-15.71 -1.158,-6.483 -0.608,-12.774 2.931,-18.499 4.534,-7.336 11.405,-10.912 19.8,-11.853 0.946,-0.105 1.916,-0.013 2.874,-0.013 z m -6.391,24.775 c -0.107,3.785 0.474,7.305 2.617,10.359 2.446,3.488 6.324,3.311 8.541,-0.314 0.371,-0.606 0.68,-1.269 0.908,-1.941 1.659,-4.902 1.658,-9.853 0.27,-14.805 -0.41,-1.46 -1.109,-2.816 -2.18,-3.938 -2.351,-2.46 -5.304,-2.33 -7.375,0.345 -0.585,0.755 -1.045,1.584 -1.417,2.479 -1.061,2.548 -1.489,5.192 -1.364,7.815 z" id="path16" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 729.656,289.509 c 0.584,-0.451 0.601,-1.232 0.859,-1.868 0.818,-2.012 0.785,-2.032 2.81,-2.038 2.559,-0.008 5.117,0.032 7.674,-0.018 1.063,-0.02 1.596,0.204 1.586,1.44 -0.052,6.235 -0.043,12.472 -0.006,18.707 0.007,1.103 -0.301,1.579 -1.483,1.563 -3.756,-0.054 -7.515,-0.046 -11.271,-0.004 -1.031,0.011 -1.438,-0.424 -1.492,-1.392 -0.084,-1.523 -0.395,-3.006 -0.914,-4.442 -1.956,-5.411 -6.595,-4.962 -9.705,-2.203 -0.355,0.314 -0.685,0.688 -0.93,1.094 -2.861,4.745 -3.563,9.704 -1.201,14.846 1.626,3.538 4.556,5.237 8.396,5.365 3.974,0.132 7.304,-1.371 10.151,-4.092 1.373,-1.31 1.423,-1.286 2.68,0.149 1.895,2.164 3.764,4.352 5.647,6.524 1.602,1.848 1.626,1.868 -0.294,3.557 -4.105,3.609 -8.873,5.972 -14.185,7.147 -6.294,1.394 -12.559,1.24 -18.607,-1.169 -8.834,-3.52 -13.651,-10.162 -14.835,-19.532 -0.559,-4.418 -0.324,-8.794 1.039,-13.034 2.766,-8.596 8.829,-13.423 17.606,-14.972 2.042,-0.359 4.108,-0.59 6.202,-0.336 3.434,0.416 6.469,1.683 9.041,4.019 0.336,0.306 0.596,0.774 1.232,0.689 z" id="path18" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 681.92,333.677 c -6.156,0.305 -10.938,-5.175 -10.884,-10.395 0.062,-5.965 4.89,-10.776 10.845,-10.808 6.384,-0.033 10.988,5.186 11.006,10.937 0.022,7.141 -5.384,10.556 -10.967,10.266 z" id="path20" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /></svg>' : '<img class="comic-image-nav" src="' . esc_attr($tipeee_image_button_url) . '" />';

                $html             = "";
                $buymeacoffee_url = get_option("toocheke-support-link-buymeacoffee");
                $gumroad_url      = get_option("toocheke-support-link-gumroad");
                $indiegogo_url    = get_option("toocheke-support-link-indiegogo");
                $kickstarter_url  = get_option("toocheke-support-link-kickstarter");
                $kofi_url         = get_option("toocheke-support-link-kofi");
                $liberapay_url    = get_option("toocheke-support-link-liberapay");
                $patreon_url      = get_option("toocheke-support-link-patreon");
                $paypal_url       = get_option("toocheke-support-link-paypal");
                $substack_url     = get_option("toocheke-support-link-substack");
                $tipeee_url       = get_option("toocheke-support-link-tipeee");

                if (! empty($buymeacoffee_url)) {
                    $html = $html . "<a href='" . esc_url($buymeacoffee_url) . "' title='Support with Buy me a coffee' target='_blank'>" . wp_kses($buymeacoffee_button, $allowed_tags) . "</a>";
                }

                if (! empty($gumroad_url)) {
                    $html = $html . "<a href='" . esc_url($gumroad_url) . "' title='Support with Gumroad' target='_blank'>" . wp_kses($gumroad_button, $allowed_tags) . "</a>";
                }

                if (! empty($indiegogo_url)) {
                    $html = $html . "<a href='" . esc_url($indiegogo_url) . "' title='Support with Indiegogo' target='_blank'>" . wp_kses($indiegogo_button, $allowed_tags) . "</a>";
                }

                if (! empty($kickstarter_url)) {
                    $html = $html . "<a href='" . esc_url($kickstarter_url) . "' title='Support with Kickstarter' target='_blank'>" . wp_kses($kickstarter_button, $allowed_tags) . "</a>";
                }
                if (! empty($kofi_url)) {
                    $html = $html . "<a href='" . esc_url($kofi_url) . "' title='Support with Ko-fi' target='_blank'>" . wp_kses($kofi_button, $allowed_tags) . "</a>";
                }

                if (! empty($liberapay_url)) {
                    $html = $html . "<a href='" . esc_url($liberapay_url) . "' title='Support with Liberapay' target='_blank'>" . wp_kses($liberapay_button, $allowed_tags) . "</a>";
                }

                if (! empty($patreon_url)) {
                    $html = $html . "<a href='" . esc_url($patreon_url) . "' title='Support with Patreon' target='_blank'>" . wp_kses($patreon_button, $allowed_tags) . "</a>";
                }

                if (! empty($paypal_url)) {
                    $html = $html . "<a href='" . esc_url($paypal_url) . "' title='Support with PayPal' target='_blank'>" . wp_kses($paypal_button, $allowed_tags) . "</a>";
                }

                if (! empty($substack_url)) {
                    $html = $html . "<a href='" . esc_url($substack_url) . "' title='Support with Substack' target='_blank'>" . wp_kses($substack_button, $allowed_tags) . "</a>";
                }
                if (! empty($tipeee_url)) {
                    $html = $html . "<a href='" . esc_url($tipeee_url) . "' title='Support with Tipeee' target='_blank'>" . wp_kses($tipeee_button, $allowed_tags) . "</a>";
                }
                echo $html;
            }
            /**
             * Toocheke Options
             */

            public function toocheke_display_options_page()
            {
                $theme = wp_get_theme(); // gets the current theme
    ?>
        <div class="wrap">
            <h1>Toocheke Options</h1>
            <?php
                $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'comic_display_options';
            ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=toocheke-options-page&tab=comic_display_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo $active_tab == 'comic_display_options' ? 'nav-tab-active' : ''; ?>">Display</a>
                <a href="?page=toocheke-options-page&tab=ordering_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo $active_tab == 'ordering_options' ? 'nav-tab-active' : ''; ?>">Ordering</a>
                <a href="?page=toocheke-options-page&tab=comic_archive_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo $active_tab == 'comic_archive_options' ? 'nav-tab-active' : ''; ?>">Archive</a>
                <a href="?page=toocheke-options-page&tab=navigation_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo $active_tab == 'navigation_options' ? 'nav-tab-active' : ''; ?>">Navigation</a>
                <a href="?page=toocheke-options-page&tab=social_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo $active_tab == 'social_options' ? 'nav-tab-active' : ''; ?>">Social Sharing</a>
                <a href="?page=toocheke-options-page&tab=support_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $active_tab == 'support_options' ? 'nav-tab-active' : ''; ?>">Support Links</a>
                <a href="?page=toocheke-options-page&tab=analytics_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo $active_tab == 'analytics_options' ? 'nav-tab-active' : ''; ?>">Analytics</a>
                <a href="?page=toocheke-options-page&tab=top_ten_comics_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo $active_tab == 'top_ten_comics_options' ? 'nav-tab-active' : ''; ?>">Top 10</a>
                <a href="?page=toocheke-options-page&tab=series_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo $active_tab == 'series_options' ? 'nav-tab-active' : ''; ?>">Series</a>
                <a href="?page=toocheke-options-page&tab=comic_discussion_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo $active_tab == 'comic_discussion_options' ? 'nav-tab-active' : ''; ?>">Discussion</a>
                <a href="?page=toocheke-options-page&tab=blog_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo $active_tab == 'blog_options' ? 'nav-tab-active' : ''; ?>">Blog</a>
                <a href="?page=toocheke-options-page&tab=age_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo $active_tab == 'age_options' ? 'nav-tab-active' : ''; ?>">Age</a>
                <a href="?page=toocheke-options-page&tab=language_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo $active_tab == 'language_options' ? 'nav-tab-active' : ''; ?>">Language</a>
                <a href="?page=toocheke-options-page&tab=comic_images_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo $active_tab == 'comic_images_options' ? 'nav-tab-active' : ''; ?>">Images</a>
                <a href="?page=toocheke-options-page&tab=rss_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo $active_tab == 'rss_options' ? 'nav-tab-active' : ''; ?>">RSS</a>
                <?php if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme): ?>
                    <a href="?page=toocheke-options-page&tab=buy_options" class="nav-tab<?php echo $active_tab == 'buy_options' ? 'nav-tab-active' : ''; ?>">Buy Comic</a>
                    <a href="?page=toocheke-options-page&tab=sponsor_options" class="nav-tab                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $active_tab == 'sponsor_options' ? 'nav-tab-active' : ''; ?>">Sponsor Comic</a>
                <?php endif; ?>
            </h2>
            <form method="post" action="<?php echo esc_url(add_query_arg('tab', $active_tab, admin_url('options.php'))); ?>">
                <?php
                // Option for display desktop and mobile versions of comic

                do_settings_sections("toocheke-options-page");
                settings_fields("toocheke-settings");

                submit_button();
                ?>
            </form>
        </div>
    <?php
            }

            public function toocheke_init_option_fields()
            {
                $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'comic_display_options';
                $theme      = wp_get_theme();
                switch ($active_tab) {
                    case 'comic_display_options':
                        //Option for determining whether to show both a desktop and mobile version of the comic
                        add_settings_section("toocheke_comic_devices_layout_section", "Comic Display", [$this, 'toocheke_display_comics_layout_message'], "toocheke-options-page");
                        add_settings_field("toocheke-comic-layout-devices", "Do you want to have two versions of your comic on each post(one for desktop users one for mobile device users)?", [$this, 'toocheke_options_devices_checkbox'], "toocheke-options-page", "toocheke_comic_devices_layout_section");
                        register_setting("toocheke-settings", "toocheke-comic-layout-devices");
                        break;
                    case 'ordering_options':
                        //Option for setting the order of the comics
                        add_settings_section("toocheke_comics_order_section", "Comics Ordering", [$this, 'toocheke_display_comics_order_message'], "toocheke-options-page");
                        add_settings_field("toocheke-comics-order", "How would you like to order your comics?", [$this, 'toocheke_comics_order_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-comics-order");

                        add_settings_field("toocheke-comics-slider-order", "How would you like to order your comics in the slider navigation on the comic page?", [$this, 'toocheke_comics_slider_order_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-comics-slider-order");

                        //Option for setting the which comics is displayed on clicking chapter thumbnail
                        add_settings_field("toocheke-chapter-first-comic", "On clicking a chapter, which comic would you like to navigate to first?", [$this, 'toocheke_chapter_first_comic_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-chapter-first-comic");

                        //Option for setting the which comics is displayed on clicking collection thumbnail
                        add_settings_field("toocheke-collection-first-comic", "On clicking a collection, which comic would you like to navigate to first?", [$this, 'toocheke_collection_first_comic_radio'], "toocheke-options-page", "toocheke_comics_order_section");
                        register_setting("toocheke-settings", "toocheke-collection-first-comic");

                        //Option for setting the order of the series
                        add_settings_section("toocheke_series_order_section", "Series Ordering", [$this, 'toocheke_display_series_order_message'], "toocheke-options-page");
                        add_settings_field("toocheke-series-order", "How would you like to order your series?", [$this, 'toocheke_series_order_radio'], "toocheke-options-page", "toocheke_series_order_section");
                        register_setting("toocheke-settings", "toocheke-series-order");
                        break;
                    case 'comic_archive_options':
                        //Option for setting the layout for the comic archive page
                        add_settings_section("toocheke_comics_archive_section", "Comic Archive Layout", [$this, 'toocheke_display_comics_archive_message'], "toocheke-options-page");
                        add_settings_field("toocheke-comics-archive", "Select the layout for the comic archive page.", [$this, 'toocheke_comics_archive_layout_select'], "toocheke-options-page", "toocheke_comics_archive_section");
                        register_setting("toocheke-settings", "toocheke-comics-archive");
                        break;
                    case 'top_ten_comics_options':
                        //Option for setting the layout for the comic archive page
                        add_settings_section("toocheke_top_10_comics_layout_section", "Top 10 Comics Page Layout", [$this, 'toocheke_display_top_10_comics_layout_message'], "toocheke-options-page");
                        add_settings_field("toocheke-top-10-comics-layout", "Select the layout for the top 10 comic page.", [$this, 'toocheke_top_10_comics_layout_select'], "toocheke-options-page", "toocheke_top_10_comics_layout_section");
                        register_setting("toocheke-settings", "toocheke-top-10-comics-layout");
                        break;
                    case 'navigation_options':
                        $theme = wp_get_theme(); // gets the current theme
                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            //Option for determining whether enable swipe navigation
                            add_settings_section("toocheke_comic_panel_swipe_navigation_section", "Comic Panel Swipe Navigation", [$this, 'toocheke_comic_panel_swipe_navigation_message'], "toocheke-options-page");
                            add_settings_field("toocheke-comic-panel-swipe-navigation", "Do you want to enable the ability to swipe through the comic, panel-by-panel, similar to Instgram's swipe navigation?", [$this, 'toocheke_comic_panel_swipe_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_panel_swipe_navigation_section");
                            register_setting("toocheke-settings", "toocheke-comic-panel-swipe-navigation");
                        }
                        //navigation buttons settings
                        add_settings_section("toocheke_comic_navigation_options_section", "Comic Navigation", [$this, 'toocheke_display_comic_navigation_message'], "toocheke-options-page");
                        add_settings_section("toocheke_custom_comic_navigation_section", "", "", "toocheke-options-page");
                        //initialize  navigation options
                        if (! get_option('toocheke-random-navigation')) {
                            add_option('toocheke-random-navigation', 1);
                        }

                        if (! get_option('toocheke-comics-navigation')) {
                            add_option('toocheke-comics-navigation', 1);
                        }

                        if (! get_option('toocheke-first-button')) {
                            add_option('toocheke-first-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-previous-button')) {
                            add_option('toocheke-previous-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-random-button')) {
                            add_option('toocheke-random-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-next-button')) {
                            add_option('toocheke-next-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-latest-button')) {
                            add_option('toocheke-latest-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-facebook-button')) {
                            add_option('toocheke-facebook-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-twitter-button')) {
                            add_option('toocheke-twitter-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-tumblr-button')) {
                            add_option('toocheke-tumblr-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-reddit-button')) {
                            add_option('toocheke-reddit-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-threads-button')) {
                            add_option('toocheke-threads-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-bluesky-button')) {
                            add_option('toocheke-bluesky-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-copy-button')) {
                            add_option('toocheke-copy-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }

                        if (! get_option('toocheke-buymeacoffee-button')) {
                            add_option('toocheke-buymeacoffee-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-gumroad-button')) {
                            add_option('toocheke-gumroad-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-indiegogo-button')) {
                            add_option('toocheke-indiegogo-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-kickstarter-button')) {
                            add_option('toocheke-kickstarter-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-kofi-button')) {
                            add_option('toocheke-kofi-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-liberapay-button')) {
                            add_option('toocheke-liberapay-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-patreon-button')) {
                            add_option('toocheke-patreon-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-paypal-button')) {
                            add_option('toocheke-paypal-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-substack-button')) {
                            add_option('toocheke-substack-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        if (! get_option('toocheke-tipeee-button')) {
                            add_option('toocheke-tipeee-button', plugins_url('toocheke-companion' . '/img/no-image.png'));
                        }
                        //Option for determining whether to display infinite scroll of comics on the home page.
                        add_settings_field("toocheke-infinite-scroll", "Do you want to display your comic archive as an infinite scroll(no previous/next buttons) on the homepage?", [$this, 'toocheke_infinite_scroll_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-infinite-scroll");

                        //Option for determining whether to display random navigation button
                        add_settings_field("toocheke-random-navigation", "Do you want to display the random button?", [$this, 'toocheke_random_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-random-navigation");

                        //Option for determining whether to display comic archive navigation button
                        add_settings_field("toocheke-comic-archive-navigation", "Do you want to display the archive button?", [$this, 'toocheke_comic_archive_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-comic-archive-navigation");

                        //Option for determining whether to display comic bookmark button
                        add_settings_field("toocheke-comic-bookmark", "Do you want to display a bookmark button?", [$this, 'toocheke_comic_bookmark_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-comic-bookmark");

                        //Option for determining whether to go the next comic on clicking current comic image
                        add_settings_field("toocheke-click-comic-next", "Do you want to go to the next comic on clicking a comic(only works with images in the post)?", [$this, 'toocheke_click_comic_next_navigation_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-click-comic-next");

                        //Option for determining whether to display comic navigation above comic
                        add_settings_field("toocheke-comic-nav-above-comic", "Do you want to display comic navigation buttons above the comic(only applies to traditional page layouts)", [$this, 'toocheke_comic_nav_above_comic_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-comic-nav-above-comic");

                        //Option for determining whether to display chapter navigation
                        add_settings_field("toocheke-chapter-navigation-buttons", "Do you want to display chapter navigation buttons?", [$this, 'toocheke_chapter_navigation_buttons_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-chapter-navigation-buttons");

                        //Option for determining whether to display chapter dropdown below comic navigation
                        add_settings_field("toocheke-chapter-dropdown", "Do you want to display a dropdown of the Chapters below the comic navigation?", [$this, 'toocheke_chapter_dropdown_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-chapter-dropdown");

                        //Option for determining whether to disable keyboard comic navigation
                        add_settings_field("toocheke-keyboard", "Do you want to disable keyboard comic navigation?", [$this, 'toocheke_keyboard_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-keyboard");

                        //Option for determining whether to scroll past header
                        add_settings_field("toocheke-scroll-past-header", "Do you want readers to scroll past the header when navigating through the comics?", [$this, 'toocheke_scroll_past_header_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-scroll-past-header");

                        //Option for determining whether to always display comic navigation buttons(first, previous, next, last)
                        add_settings_field("toocheke-always-show-nav-buttons", "Always show all comic navigation buttons, even if there isn’t a first, previous, next, or last comic available?", [$this, 'toocheke_always_show_nav_buttons_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                        register_setting("toocheke-settings", "toocheke-always-show-nav-buttons");

                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            //Option for determining whether to enable navigation to early access comics
                            add_settings_field("toocheke-early-access", "Do you want to enable navigation to comics scheduled in the future?", [$this, 'toocheke_early_access_checkbox'], "toocheke-options-page", "toocheke_comic_navigation_options_section");
                            register_setting("toocheke-settings", "toocheke-early-access");
                        }

                        //Option for determining whether to use the defaul comic navigation buttons
                        add_settings_field("toocheke-comics-navigation", "Do you want to use the default navigation buttons?", [$this, 'toocheke_comics_navigation_checkbox'], "toocheke-options-page", "toocheke_custom_comic_navigation_section");
                        register_setting("toocheke-settings", "toocheke-comics-navigation");

                        add_settings_field('toocheke-first-preview', 'Current first button', [$this, 'toocheke_first_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-first-button', 'Replace first button', [$this, 'toocheke_first_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-first-button');
                        add_settings_field('toocheke-previous-preview', 'Current previous button', [$this, 'toocheke_previous_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-previous-button', 'Replace previous button', [$this, 'toocheke_previous_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-previous-button');
                        add_settings_field('toocheke-random-preview', 'Current random button', [$this, 'toocheke_random_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-random-button', 'Replace random button', [$this, 'toocheke_random_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-random-button');
                        add_settings_field('toocheke-comic-archive-preview', 'Current comic archive button', [$this, 'toocheke_comic_archive_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-comic-archive-button', 'Replace comic archive button', [$this, 'toocheke_comic_archive_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-comic-archive-button');
                        add_settings_field('toocheke-next-preview', 'Current next button', [$this, 'toocheke_next_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-next-button', 'Replace next button', [$this, 'toocheke_next_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-next-button');
                        add_settings_field('toocheke-latest-preview', 'Current latest button', [$this, 'toocheke_latest_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-latest-button', 'Replace latest button', [$this, 'toocheke_latest_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-latest-button');
                        add_settings_field('toocheke-next-chapter-preview', 'Current next chapter button', [$this, 'toocheke_next_chapter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-next-chapter-button', 'Replace next chapter button', [$this, 'toocheke_next_chapter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-next-chapter-button');
                        add_settings_field('toocheke-previous-chapter-preview', 'Current previous chapter button', [$this, 'toocheke_previous_chapter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-previous-chapter-button', 'Replace previous chapter button', [$this, 'toocheke_previous_chapter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-previous-chapter-button');
                        add_settings_field('toocheke-facebook-preview', 'Current Facebook button', [$this, 'toocheke_facebook_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-facebook-button', 'Replace Facebook button', [$this, 'toocheke_facebook_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-facebook-button');
                        add_settings_field('toocheke-twitter-preview', 'Current Twitter button', [$this, 'toocheke_twitter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-twitter-button', 'Replace Twitter button', [$this, 'toocheke_twitter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-twitter-button');
                        add_settings_field('toocheke-tumblr-preview', 'Current Tumblr button', [$this, 'toocheke_tumblr_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-tumblr-button', 'Replace Tumblr button', [$this, 'toocheke_tumblr_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-tumblr-button');
                        add_settings_field('toocheke-reddit-preview', 'Current Reddit button', [$this, 'toocheke_reddit_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-reddit-button', 'Replace Reddit button', [$this, 'toocheke_reddit_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-reddit-button');
                        add_settings_field('toocheke-threads-preview', 'Current Threads button', [$this, 'toocheke_threads_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-threads-button', 'Replace Threads button', [$this, 'toocheke_threads_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-threads-button');
                        add_settings_field('toocheke-bluesky-preview', 'Current Bluesky button', [$this, 'toocheke_bluesky_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-bluesky-button', 'Replace Bluesky button', [$this, 'toocheke_bluesky_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-bluesky-button');
                        add_settings_field('toocheke-whatsapp-preview', 'Current WhatsApp button', [$this, 'toocheke_whatsapp_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-whatsapp-button', 'Replace WhatsApp button', [$this, 'toocheke_whatsapp_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-whatsapp-button');
                        add_settings_field('toocheke-linkedin-preview', 'Current LinkedIn button', [$this, 'toocheke_linkedin_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-linkedin-button', 'Replace LinkedIn button', [$this, 'toocheke_linkedin_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-linkedin-button');
                        add_settings_field('toocheke-copy-preview', 'Current copy button', [$this, 'toocheke_copy_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-copy-button', 'Replace copy button', [$this, 'toocheke_copy_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-copy-button');
                        add_settings_field('toocheke-buymeacoffee-preview', 'Current Buy me a coffee button', [$this, 'toocheke_buymeacoffee_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-buymeacoffee-button', 'Replace Buy me a coffee button', [$this, 'toocheke_buymeacoffee_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-buymeacoffee-button');
                        add_settings_field('toocheke-gumroad-preview', 'Current Gumroad button', [$this, 'toocheke_gumroad_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-gumroad-button', 'Replace Gumroad button', [$this, 'toocheke_gumroad_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-gumroad-button');
                        add_settings_field('toocheke-indiegogo-preview', 'Current Indiegogo button', [$this, 'toocheke_indiegogo_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-indiegogo-button', 'Replace Indiegogo button', [$this, 'toocheke_indiegogo_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-indiegogo-button');
                        add_settings_field('toocheke-kickstarter-preview', 'Current Kickstarter button', [$this, 'toocheke_kickstarter_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-kickstarter-button', 'Replace Kickstarter button', [$this, 'toocheke_kickstarter_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-kickstarter-button');
                        add_settings_field('toocheke-kofi-preview', 'Current Ko-fi button', [$this, 'toocheke_kofi_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-kofi-button', 'Replace Ko-fi button', [$this, 'toocheke_kofi_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-kofi-button');
                        add_settings_field('toocheke-liberapay-preview', 'Current Liberapay button', [$this, 'toocheke_liberapay_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-liberapay-button', 'Replace Liberapay button', [$this, 'toocheke_liberapay_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-liberapay-button');
                        add_settings_field('toocheke-patreon-preview', 'Current Patreon button', [$this, 'toocheke_patreon_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-patreon-button', 'Replace Patreon button', [$this, 'toocheke_patreon_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-patreon-button');
                        add_settings_field('toocheke-paypal-preview', 'Current PayPal button', [$this, 'toocheke_paypal_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-paypal-button', 'Replace PayPal button', [$this, 'toocheke_paypal_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-paypal-button');
                        add_settings_field('toocheke-substack-preview', 'Current Substack button', [$this, 'toocheke_substack_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-substack-button', 'Replace Substack button', [$this, 'toocheke_substack_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-substack-button');
                        add_settings_field('toocheke-tipeee-preview', 'Current Tipeee button', [$this, 'toocheke_tipeee_button_preview'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        add_settings_field('toocheke-tipeee-button', 'Replace Tipeee button', [$this, 'toocheke_tipeee_button_upload'], 'toocheke-options-page', 'toocheke_custom_comic_navigation_section');
                        register_setting('toocheke-settings', 'toocheke-tipeee-button');
                        break;
                    case 'social_options':
                        //social share settings
                        add_settings_section("toocheke_social_share_config_section", "Social Sharing Buttons", [$this, 'toocheke_display_social_sharing_message'], "toocheke-options-page");

                        add_settings_field("toocheke-social-share-facebook", "Do you want to display a Facebook share button?", [$this, 'toocheke_social_share_facebook_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-twitter", "Do you want to display a Twitter share button?", [$this, 'toocheke_social_share_twitter_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-tumblr", "Do you want to display a Tumblr share button?", [$this, 'toocheke_social_share_tumblr_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-reddit", "Do you want to display a Reddit share button?", [$this, 'toocheke_social_share_reddit_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-threads", "Do you want to display a Threads share button?", [$this, 'toocheke_social_share_threads_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-bluesky", "Do you want to display a Bluesky share button?", [$this, 'toocheke_social_share_bluesky_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-whatsapp", "Do you want to display a WhatsApp share button?", [$this, 'toocheke_social_share_whatsapp_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-linkedin", "Do you want to display a LinkedIn share button?", [$this, 'toocheke_social_share_linkedin_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");
                        add_settings_field("toocheke-social-share-copy", "Do you want to display a copy button?", [$this, 'toocheke_social_share_copy_checkbox'], "toocheke-options-page", "toocheke_social_share_config_section");

                        register_setting("toocheke-settings", "toocheke-social-share-facebook");
                        register_setting("toocheke-settings", "toocheke-social-share-twitter");
                        register_setting("toocheke-settings", "toocheke-social-share-tumblr");
                        register_setting("toocheke-settings", "toocheke-social-share-reddit");
                        register_setting("toocheke-settings", "toocheke-social-share-threads");
                        register_setting("toocheke-settings", "toocheke-social-share-bluesky");
                        register_setting("toocheke-settings", "toocheke-social-share-whatsapp");
                        register_setting("toocheke-settings", "toocheke-social-share-linkedin");
                        register_setting("toocheke-settings", "toocheke-social-share-copy");
                        break;
                    case 'support_options':
                        //support settings
                        add_settings_section("toocheke_support_links_config_section", "Support Links", [$this, 'toocheke_display_support_links_message'], "toocheke-options-page");

                        add_settings_field("toocheke-support-link-buymeacoffee", "Buy me a coffee", [$this, 'toocheke_support_link_buymeacoffee_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-gumroad", "Gumroad", [$this, 'toocheke_support_link_gumroad_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-indiegogo", "Indiegogo", [$this, 'toocheke_support_link_indiegogo_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-kickstarter", "Kickstarter", [$this, 'toocheke_support_link_kickstarter_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-kofi", "Ko-fi", [$this, 'toocheke_support_link_kofi_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-liberapay", "Liberapay", [$this, 'toocheke_support_link_liberapay_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-patreon", "Patreon", [$this, 'toocheke_support_link_patreon_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-paypal", "PayPal", [$this, 'toocheke_support_link_paypal_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-substack", "Substack", [$this, 'toocheke_support_link_substack_url'], "toocheke-options-page", "toocheke_support_links_config_section");
                        add_settings_field("toocheke-support-link-tipeee", "Tipeee", [$this, 'toocheke_support_link_tipeee_url'], "toocheke-options-page", "toocheke_support_links_config_section");

                        register_setting("toocheke-settings", "toocheke-support-link-buymeacoffee");
                        register_setting("toocheke-settings", "toocheke-support-link-gumroad");
                        register_setting("toocheke-settings", "toocheke-support-link-indiegogo");
                        register_setting("toocheke-settings", "toocheke-support-link-kickstarter");
                        register_setting("toocheke-settings", "toocheke-support-link-kofi");
                        register_setting("toocheke-settings", "toocheke-support-link-liberapay");
                        register_setting("toocheke-settings", "toocheke-support-link-patreon");
                        register_setting("toocheke-settings", "toocheke-support-link-paypal");
                        register_setting("toocheke-settings", "toocheke-support-link-substack");
                        register_setting("toocheke-settings", "toocheke-support-link-tipeee");
                        break;
                    case 'analytics_options':
                        //Option for determining whether to comic analytics
                        add_settings_section("toocheke_comic_analytics_section", "Comic Analytics", [$this, 'toocheke_comic_analytics_message'], "toocheke-options-page");
                        add_settings_field("toocheke-comic-likes", "Do you want to display comic likes?", [$this, 'toocheke_comic_likes_checkbox'], "toocheke-options-page", "toocheke_comic_analytics_section");
                        register_setting("toocheke-settings", "toocheke-comic-likes");
                        add_settings_field("toocheke-comic-no-of-comments", "Do you want to display number of comments for comic?", [$this, 'toocheke_comic_no_of_comments_checkbox'], "toocheke-options-page", "toocheke_comic_analytics_section");
                        register_setting("toocheke-settings", "toocheke-comic-no-of-comments");
                        add_settings_field("toocheke-comic-no-of-views", "Do you want to display number of views for comic?", [$this, 'toocheke_comic_no_of_views_checkbox'], "toocheke-options-page", "toocheke_comic_analytics_section");
                        register_setting("toocheke-settings", "toocheke-comic-no-of-views");
                        break;
                    case 'series_options':
                        //Option for determining whether to show a single comic series or multiple comic series
                        add_settings_section("toocheke_multiple_series_display_section", "Publish Multiple Comic Series", [$this, 'toocheke_multiple_series_display_message'], "toocheke-options-page");
                        add_settings_field("toocheke-display-multiple-series", "Do you want to publish more than one comic series?", [$this, 'toocheke_series_publish_options_checkbox'], "toocheke-options-page", "toocheke_multiple_series_display_section");
                        register_setting("toocheke-settings", "toocheke-display-multiple-series");

                        //Option for determining whether to show latest comic listings on a multiple series home page
                        add_settings_field("toocheke-display-latest-comics-of-all-multiple-series", "Do you want to display a list of ALL comics(for multiple series) on the home page?", [$this, 'toocheke_display_latest_comics_of_all_multiple_series_checkbox'], "toocheke-options-page", "toocheke_multiple_series_display_section");
                        register_setting("toocheke-settings", "toocheke-display-latest-comics-of-all-multiple-series");

                        //Option for displaying regular blog posts on series landing page
                        add_settings_section("toocheke_series_landing_blog_section", "Blog Posts on Series Landing Page", [$this, 'toocheke_series_landing_blog_display_message'], "toocheke-options-page");
                        add_settings_field("toocheke-series-landing-blog", "Do you want to display list of regular blog posts on the series landing page(applies to webtoon layouts)?", [$this, 'toocheke_series_landing_blog_checkbox'], "toocheke-options-page", "toocheke_series_landing_blog_section");
                        register_setting("toocheke-settings", "toocheke-series-landing-blog");

                        //Option for determining which series to display on home page(applies to only traditional layouts.)
                        add_settings_section("toocheke_traditional_home_series", "Series to Display on Home Page", [$this, 'toocheke_traditional_home_series_display_message'], "toocheke-options-page");
                        add_settings_field("toocheke-traditional-home-series", "Select the series you would like displayed on the home page", [$this, 'toocheke_traditional_home_series_dropdown'], "toocheke-options-page", "toocheke_traditional_home_series");
                        register_setting("toocheke-settings", "toocheke-traditional-home-series");

                        break;
                    case 'comic_discussion_options':
                        //Option for determining whether to allow discussion on comic posts on the home page
                        add_settings_section("toocheke_comic_discussion_section", "Comic Discussion", [$this, 'toocheke_comic_discussion_message'], "toocheke-options-page");
                        add_settings_field("toocheke-comic-discussion", "Do you want to allow commenting on comic posts on the home page?", [$this, 'toocheke_comic_discussion_checkbox'], "toocheke-options-page", "toocheke_comic_discussion_section");
                        register_setting("toocheke-settings", "toocheke-comic-discussion");
                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            //Option for determining whether to allow discussion on comic posts on the home page
                            add_settings_section("toocheke_paywalled_discussion_section", "Paywalled Discussion", [$this, 'toocheke_paywalled_discussion_message'], "toocheke-options-page");
                            add_settings_field("toocheke-paywalled-discussion", "Do you want to allow commenting only for patrons?", [$this, 'toocheke_paywalled_discussion_checkbox'], "toocheke-options-page", "toocheke_paywalled_discussion_section");
                            register_setting("toocheke-settings", "toocheke-paywalled-discussion");
                            break;
                        }
                        break;

                    case 'blog_options':
                        //Option for determining whether to show hide blog posts
                        add_settings_section("toocheke_hide_blog_section", "Hide Blogs", [$this, 'toocheke_hide_blog_message'], "toocheke-options-page");
                        add_settings_field("toocheke-hide-blog", "Do you want to hide blog posts on your comics homepage/landing page?", [$this, 'toocheke_hide_blog_checkbox'], "toocheke-options-page", "toocheke_hide_blog_section");
                        register_setting("toocheke-settings", "toocheke-hide-blog");

                        //Option for determining whether to show blog post on webtoon layout
                        add_settings_section("toocheke_display_blog_on_webtoon", "Display Comic's Blog Post on Webtoon Layouts", [$this, 'toocheke_display_blog_on_webtoon_message'], "toocheke-options-page");
                        add_settings_field("toocheke-dspay-blog-on-webtoon", "Do you want to display the blog post on webtoon layout?", [$this, 'toocheke_display_blog_webtoon_checkbox'], "toocheke-options-page", "toocheke_display_blog_on_webtoon");
                        register_setting("toocheke-settings", "toocheke-dspay-blog-on-webtoon");

                        break;
                    case 'age_options':
                        //Option for determining whether to display popup for mature audiences
                        add_settings_section("toocheke_age_verification_section", "Age Verification", [$this, 'toocheke_age_verification_message'], "toocheke-options-page");
                        add_settings_field("toocheke-age-verification", "Do you want to add a pop-up window to your website and verify the age of the visitor?", [$this, 'toocheke_age_verification_checkbox'], "toocheke-options-page", "toocheke_age_verification_section");
                        register_setting("toocheke-settings", "toocheke-age-verification");
                        break;
                    case 'language_options':
                        //Option for determining whether to display a bilingual language comic
                        add_settings_section("toocheke_bilingual_display_section", "Bilingual Display", [$this, 'toocheke_bilingual_display_message'], "toocheke-options-page");
                        add_settings_field("toocheke-bilingual-display", "Do you want to publish a bilingual comic?", [$this, 'toocheke_bilingual_display_checkbox'], "toocheke-options-page", "toocheke_bilingual_display_section");
                        register_setting("toocheke-settings", "toocheke-bilingual-display");
                        break;
                    case 'comic_images_options':
                        //Option for determining whether to allow the click to enlarge behavior for images
                        add_settings_section("toocheke_image_click_section", "Allow Click to Enlarge for Images", [$this, 'toocheke_image_click_message'], "toocheke-options-page");
                        add_settings_field("toocheke-image-click", "Do you want to enable the click to enlarge feature for comic images?", [$this, 'toocheke_image_click_checkbox'], "toocheke-options-page", "toocheke_image_click_section");
                        register_setting("toocheke-settings", "toocheke-image-click");
                        break;
                    case 'rss_options':
                        //Option for determining whether to add comics to main RSS feed
                        add_settings_section("toocheke_comics_to_main_rss_section", "Add comic posts to main feed?", [$this, 'toocheke_rss_message'], "toocheke-options-page");
                        add_settings_field("toocheke-comics-to-main-rss", "Do you want to add comic posts to the main feed?", [$this, 'toocheke_comics_to_main_rss_checkbox'], "toocheke-options-page", "toocheke_comics_to_main_rss_section");
                        register_setting("toocheke-settings", "toocheke-comics-to-main-rss");
                        break;
                    //Options for sponsoring a comic
                    case 'sponsor_options':
                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            add_settings_section("toocheke_sponsor_comic_info_section", "Sponsor Comic", [$this, 'toocheke_sponsor_comic_display_message'], "toocheke-options-page");
                            //PayPal Fields section
                            add_settings_section("toocheke_sponsor_comic_paypal_settings_section", "PayPal Settings", [$this, 'toocheke_paypal_settings_display_message'], "toocheke-options-page");
                            add_settings_field(
                                'toocheke-paypal-client-id',
                                'Client ID',
                                [$this, 'toocheke_display_input_text'],
                                "toocheke-options-page",
                                'toocheke_sponsor_comic_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-paypal-client-id',
                                    'class'     => 'toocheke-companion',        // for <tr> element
                                    'name'      => 'toocheke-paypal-client-id', // pass any custom parameters
                                ]
                            );

                            add_settings_field(
                                'toocheke-paypal-client-secret',
                                'Client Secret',
                                [$this, 'toocheke_display_input_text'],
                                "toocheke-options-page",
                                'toocheke_sponsor_comic_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-paypal-client-secret',
                                    'class'     => 'toocheke-companion',            // for <tr> element
                                    'name'      => 'toocheke-paypal-client-secret', // pass any custom parameters
                                ]
                            );

                            add_settings_field("toocheke-enable-paypal-sandbox", "Sandbox Mode?", [$this, 'toocheke_enable_paypal_sandbox_checkbox'], "toocheke-options-page", "toocheke_sponsor_comic_paypal_settings_section");

                            register_setting("toocheke-settings", "toocheke-paypal-client-id");
                            register_setting("toocheke-settings", "toocheke-paypal-client-secret");
                            register_setting("toocheke-settings", "toocheke-enable-paypal-sandbox");

                            add_settings_section("toocheke_comic_sponsor_pricing_section", "Pricing", [$this, 'toocheke_comic_sponsor_pricing_display_message'], "toocheke-options-page");
                            //Currency field
                            $currencies = $this->toocheke_get_paypal_currencies();
                            foreach ($currencies as $currency_code => $currency_label) {
                                $currency_list[$currency_code] = sprintf(
                                    '%s (%s)',
                                    $currency_label,
                                    $this->toocheke_get_currency_symbol($currency_code)
                                );
                            }
                            $toocheke_paypal_currency_args = [
                                'option'         => 'toocheke_paypal_currency',
                                'label'          => 'Currency',
                                'desc'           => null,
                                'select_options' => $currency_list,
                            ];
                            add_settings_field("toocheke_paypal_currency", "Currency", [$this, 'toocheke_select_dropdown_generator'], 'toocheke-options-page', "toocheke_comic_sponsor_pricing_section", $toocheke_paypal_currency_args);
                            register_setting("toocheke-settings", "toocheke_paypal_currency", ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
                            //Price field
                            add_settings_field(
                                'toocheke-comic-sponsorship-price',
                                'Price per day',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_comic_sponsor_pricing_section',
                                [
                                    'label_for' => 'toocheke-comic-sponsorship-price',
                                    'class'     => 'toocheke-companion',               // for <tr> element
                                    'name'      => 'toocheke-comic-sponsorship-price', // pass any custom parameters
                                ]
                            );

                            register_setting("toocheke-settings", 'toocheke-comic-sponsorship-price', 'absint');
                        }
                        break;
                    //Options for buying a comic
                    case 'buy_options':

                        if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                            add_settings_section("toocheke_buy_comic_info_section", "Buy Comic", [$this, 'toocheke_buy_comic_display_message'], "toocheke-options-page");
                            add_settings_section("toocheke_buy_comic_options_section", "Options to enable", [$this, 'toocheke_buy_comic_options_display_message'], "toocheke-options-page");
                            add_settings_field("toocheke-original-art", "Do you wish to offer sales of the original art for each comic?", [$this, 'toocheke_buy_original_checkbox'], "toocheke-options-page", "toocheke_buy_comic_options_section");
                            add_settings_field("toocheke-print", "Do you wish to offer sales of the print for each comic?", [$this, 'toocheke_buy_print_checkbox'], "toocheke-options-page", "toocheke_buy_comic_options_section");
                            register_setting("toocheke-settings", "toocheke-original-art");
                            register_setting("toocheke-settings", "toocheke-print");
                            //PayPal Fields section
                            add_settings_section("toocheke_paypal_settings_section", "PayPal Settings", [$this, 'toocheke_paypal_settings_display_message'], "toocheke-options-page");
                            add_settings_field(
                                'toocheke-paypal-email',
                                'Email address for your PayPal account',
                                [$this, 'toocheke_display_input_email'],
                                "toocheke-options-page",
                                'toocheke_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-paypal-email',
                                    'class'     => 'toocheke-companion',    // for <tr> element
                                    'name'      => 'toocheke-paypal-email', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-comic-title',
                                'The name of your comic',
                                [$this, 'toocheke_display_input_text'],
                                "toocheke-options-page",
                                'toocheke_paypal_settings_section',
                                [
                                    'label_for' => 'toocheke-comic-title',
                                    'class'     => 'toocheke-companion',   // for <tr> element
                                    'name'      => 'toocheke-comic-title', // pass any custom parameters
                                ]
                            );

                            register_setting("toocheke-settings", "toocheke-paypal-email");
                            register_setting("toocheke-settings", "toocheke-comic-title");
                            //Original art section
                            register_setting("toocheke-settings", "toocheke-original-description");
                            register_setting("toocheke-settings", 'toocheke-original-us-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-us-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-canada-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-canada-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-international-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-original-international-shipping', 'absint');
                            add_settings_section("toocheke_original_art_info_section", "Original art information", [$this, 'toocheke_original_art_fields_display_message'], "toocheke-options-page");
                            add_settings_field(
                                'toocheke-original-description',
                                'Description of original art',
                                [$this, 'toocheke_display_input_WYSIWYG'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-description',
                                    'class'     => 'toocheke-companion',            // for <tr> element
                                    'name'      => 'toocheke-original-description', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-us-price',
                                'US Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-us-price',
                                    'class'     => 'toocheke-companion',         // for <tr> element
                                    'name'      => 'toocheke-original-us-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-us-shipping',
                                'US Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-us-shipping',
                                    'class'     => 'toocheke-companion',            // for <tr> element
                                    'name'      => 'toocheke-original-us-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-canada-price',
                                'Canada Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-canada-price',
                                    'class'     => 'toocheke-companion',             // for <tr> element
                                    'name'      => 'toocheke-original-canada-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-canada-shipping',
                                'Canada Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-canada-shipping',
                                    'class'     => 'toocheke-companion',                // for <tr> element
                                    'name'      => 'toocheke-original-canada-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-international-price',
                                'International Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-international-price',
                                    'class'     => 'toocheke-companion',                    // for <tr> element
                                    'name'      => 'toocheke-original-international-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-original-international-shipping',
                                'International Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_original_art_info_section',
                                [
                                    'label_for' => 'toocheke-original-international-shipping',
                                    'class'     => 'toocheke-companion',                       // for <tr> element
                                    'name'      => 'toocheke-original-international-shipping', // pass any custom parameters
                                ]
                            );
                            //Print section
                            register_setting("toocheke-settings", "toocheke-print-description");
                            register_setting("toocheke-settings", 'toocheke-print-us-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-us-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-canada-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-canada-shipping', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-international-price', 'absint');
                            register_setting("toocheke-settings", 'toocheke-print-international-shipping', 'absint');
                            add_settings_field(
                                'toocheke-print-description',
                                'Description of prints',
                                [$this, 'toocheke_display_input_WYSIWYG'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-description',
                                    'class'     => 'toocheke-companion',         // for <tr> element
                                    'name'      => 'toocheke-print-description', // pass any custom parameters
                                ]
                            );
                            add_settings_section("toocheke_print_info_section", "Print information", [$this, 'toocheke_print_fields_display_message'], "toocheke-options-page");
                            add_settings_field(
                                'toocheke-print-us-price',
                                'US Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-us-price',
                                    'class'     => 'toocheke-companion',      // for <tr> element
                                    'name'      => 'toocheke-print-us-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-us-shipping',
                                'US Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-us-shipping',
                                    'class'     => 'toocheke-companion',         // for <tr> element
                                    'name'      => 'toocheke-print-us-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-canada-price',
                                'Canada Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-canada-price',
                                    'class'     => 'toocheke-companion',          // for <tr> element
                                    'name'      => 'toocheke-print-canada-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-canada-shipping',
                                'Canada Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-canada-shipping',
                                    'class'     => 'toocheke-companion',             // for <tr> element
                                    'name'      => 'toocheke-print-canada-shipping', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-international-price',
                                'International Price',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-international-price',
                                    'class'     => 'toocheke-companion',                 // for <tr> element
                                    'name'      => 'toocheke-print-international-price', // pass any custom parameters
                                ]
                            );
                            add_settings_field(
                                'toocheke-print-international-shipping',
                                'International Shipping',
                                [$this, 'toocheke_display_input_number'],
                                'toocheke-options-page',
                                'toocheke_print_info_section',
                                [
                                    'label_for' => 'toocheke-print-international-shipping',
                                    'class'     => 'toocheke-companion',                    // for <tr> element
                                    'name'      => 'toocheke-print-international-shipping', // pass any custom parameters
                                ]
                            );
                        }

                        break;
                }
            }
            public function toocheke_display_comic_navigation_message()
            {
                echo 'Customize your comic\'s navigation options. You can upload your own navigation button images to replace the default buttons.';
            }
            public function toocheke_multiple_series_display_message()
            {
                echo 'This sets whether you will display a single or multiple comic series on your website.';
            }
            public function toocheke_series_landing_blog_display_message()
            {
                echo 'This sets whether you will display blog post listing on the series landing page.';
            }
            public function toocheke_traditional_home_series_display_message()
            {
                echo 'This sets which series will be displayed on the home page. Please note that this only applies to the traditional layouts for the home page.';
            }
            public function toocheke_display_comics_layout_message()
            {
                echo 'This determines whether two image versions of the comic will be displayed depending on device(desktop or mobile).';
            }
            public function toocheke_comic_discussion_message()
            {
                echo 'This determines whether to allow commenting on comic posts on the home page.';
            }
            public function toocheke_paywalled_discussion_message()
            {
                echo 'This determines whether to allow commenting only for paying patrons.';
            }
            public function toocheke_hide_blog_message()
            {
                echo 'This determines whether or not to display the latest blog posts section in the landing/home pages).';
            }
            public function toocheke_display_blog_on_webtoon_message()
            {
                echo 'This determines whether or not to display the accompanying blog post for a comic in the webtoon layouts.';
            }
            public function toocheke_age_verification_message()
            {
                echo 'This determines whether a browser cookie gets saved for 30 days when the user selects the "Yes" button. They will not be able to access the content on your website if they click "No".';
            }
            public function toocheke_bilingual_display_message()
            {
                echo 'This determines whether to display a bilingual version of your comic';
            }
            public function toocheke_buy_comic_display_message()
            {
                echo '<div class="notice notice-info inline"><p>To use the \'Buy Comic\' features, you will require a PayPal business account. Within your PayPal account, you will need to enter the <b>Instant Payment Notification(IPN)</b> setting. This is what PayPal uses to notify the website that a purchase has been made. Make sure to enter the <b style="color: #ff0000;">Notification URL</b>.</p><p> <a href="https://developer.paypal.com/api/nvp-soap/ipn/IPNSetup/" target="_blank">Here</a> is a tutorial on how to access the setting. <a href="https://www.paypal.com/merchantnotification/ipn/preference/edit" target="_blank">This link</a> should take you directly to where you need to update the URL(once you are logged in). Make sure to enter the following URL:<p><b style="color:#0000ff">' . get_site_url() . '/?action=IPN_Handler</b></p></div><hr/>';
            }
            public function toocheke_sponsor_comic_display_message()
            {
                echo '<div class="notice notice-info inline"><p>To use the \'Sponsor Comic\' features, you will require a PayPal business account. You will also need to obtain a <b>CLIENT_ID</b> and <b>CLIENT_SECRET </b>, that you will copy and paste into the corresponding fields on this page. This is what PayPal uses to process your payments.</p><p><a href="https://developer.paypal.com/docs/api/overview/" target="_blank">Here is a detailed guide</a> showing you how to get the API credentials you will need</p><p>Only enable the <b>sandbox mode</b> if you are testing. Otherwise leave this option unchecked. <p><b style="color: #ff0000;">NOTE:</b> If you are going to enable sandbox mode, you will need to use a different <b>CLIENT_ID</b> and <b>CLIENT_SECRET</b> that you can get from your sandbox account.</p><a href="https://developer.paypal.com/tools/sandbox/" target="_blank">Here is a helpful guide</a> on using Sandbox accounts in PayPal</p><p><a href="https://developer.paypal.com/docs/multiparty/create-account/#create-your-sandbox-api-caller-account" target="_blank">Here is a helpful guide</a> for obtaining the <b>CLIENT_ID</b> and <b>CLIENT_SECRET</b> from your sandbox account</p></div><hr/>';
            }
            public function toocheke_buy_comic_options_display_message()
            {
                echo 'Choose which purchase options you would like to enable for all comics.';
            }
            public function toocheke_original_art_fields_display_message()
            {
                echo 'Enter the following fields for selling a comic\'s original.';
            }
            public function toocheke_comic_sponsor_pricing_display_message()
            {
                echo 'Enter the cost per day you wish to charge your sponsors.';
            }
            public function toocheke_print_fields_display_message()
            {
                echo 'Enter the following fields for selling a comic\'s print.';
            }
            public function toocheke_paypal_settings_display_message()
            {
                echo '  Enter the following settings for your PayPal account.';
            }
            public function toocheke_image_click_message()
            {
                echo 'This determines whether you can click an image to enlarge on comic pages';
            }
            public function toocheke_rss_message()
            {
                echo 'This determines whether the comic posts will be added to the main feed: ' . esc_url(get_bloginfo('url') . '/feed');
            }
            public function toocheke_comic_analytics_message()
            {
                echo 'This determines which comic analytics your want to display for each comic.';
            }
            public function toocheke_comic_panel_swipe_navigation_message()
            {
                echo 'This determines whether a panel-by-panel swipe navigation(similar to Instagram) will be added to the comic page.';
            }
            public function toocheke_display_comics_order_message()
            {
                echo 'This sets the sorting order for comics.';
            }
            public function toocheke_display_series_order_message()
            {
                echo 'This sets the sorting order for series listing on the home page.';
            }
            public function toocheke_display_comics_archive_message()
            {
                echo 'This sets the layout for the comic archive page. <h4 style="color: #2271b1;">To change the number of items shown per page on the archive page, <a href="' . esc_url(admin_url('options-reading.php')) . '" style="font-weight: bold">click here</a> and update the number for the "Blog pages show at most" field.</h4>';
            }
            public function toocheke_display_top_10_comics_layout_message()
            {
                echo 'This sets the layout for the top 10 comics page.';
            }
            public function toocheke_display_social_sharing_message()
            {
                echo 'This will determine which social sharing buttons will appear for every comic.';
            }
            public function toocheke_display_support_links_message()
            {
                echo 'Enter what you would like displayed for your supporter/patron/donation links.';
            }
            public function toocheke_allowed_html()
            {

                $allowed_atts = [
                    'align'      => [],
                    'class'      => [],
                    'type'       => [],
                    'id'         => [],
                    'dir'        => [],
                    'lang'       => [],
                    'style'      => [],
                    'xml:lang'   => [],
                    'src'        => [],
                    'alt'        => [],
                    'href'       => [],
                    'rel'        => [],
                    'rev'        => [],
                    'target'     => [],
                    'novalidate' => [],
                    'type'       => [],
                    'value'      => [],
                    'name'       => [],
                    'tabindex'   => [],
                    'action'     => [],
                    'method'     => [],
                    'for'        => [],
                    'width'      => [],
                    'height'     => [],
                    'data'       => [],
                    'title'      => [],
                    'selected'   => [],
                    'disabled'   => [],
                    'checked'    => [],
                ];
                $allowed_tags['form']     = $allowed_atts;
                $allowed_tags['label']    = $allowed_atts;
                $allowed_tags['select']   = $allowed_atts;
                $allowed_tags['option']   = $allowed_atts;
                $allowed_tags['input']    = $allowed_atts;
                $allowed_tags['textarea'] = $allowed_atts;
                $allowed_tags['iframe']   = $allowed_atts;
                $allowed_tags['script']   = $allowed_atts;
                $allowed_tags['style']    = $allowed_atts;
                $allowed_tags['strong']   = $allowed_atts;
                $allowed_tags['small']    = $allowed_atts;
                $allowed_tags['table']    = $allowed_atts;
                $allowed_tags['span']     = $allowed_atts;
                $allowed_tags['abbr']     = $allowed_atts;
                $allowed_tags['code']     = $allowed_atts;
                $allowed_tags['pre']      = $allowed_atts;
                $allowed_tags['div']      = $allowed_atts;
                $allowed_tags['img']      = $allowed_atts;
                $allowed_tags['h1']       = $allowed_atts;
                $allowed_tags['h2']       = $allowed_atts;
                $allowed_tags['h3']       = $allowed_atts;
                $allowed_tags['h4']       = $allowed_atts;
                $allowed_tags['h5']       = $allowed_atts;
                $allowed_tags['h6']       = $allowed_atts;
                $allowed_tags['ol']       = $allowed_atts;
                $allowed_tags['ul']       = $allowed_atts;
                $allowed_tags['li']       = $allowed_atts;
                $allowed_tags['em']       = $allowed_atts;
                $allowed_tags['hr']       = $allowed_atts;
                $allowed_tags['br']       = $allowed_atts;
                $allowed_tags['tr']       = $allowed_atts;
                $allowed_tags['td']       = $allowed_atts;
                $allowed_tags['p']        = $allowed_atts;
                $allowed_tags['a']        = $allowed_atts;
                $allowed_tags['b']        = $allowed_atts;
                $allowed_tags['i']        = $allowed_atts;

                return $allowed_tags;
            }
            public function toocheke_select_dropdown_generator(array $args)
            {
                $html           = '';
                $option         = $args['option'];
                $desc           = $args['desc'];
                $value          = get_option($option) ? get_option($option) : [];
                $select_options = $args['select_options'];

                $html .= '<select name="' . esc_attr($option) . '" id="' . esc_attr($option) . '">';
                foreach ($select_options as $k => $v) {
                    $selected = false;
                    if ($k == $value) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                $html .= $desc ? ' <p><em>' . esc_html($desc) . '</em></p>' : '';
                echo wp_kses($html, $this->toocheke_allowed_html());
            }
            public function toocheke_display_input_number($args)
            {
                printf(
                    '<input type="number" id="%s" name="%s" value="%d" />',
                    $args['name'],
                    $args['name'],
                    get_option($args['name'], 0) // 2 is the default number of slides
                );
            }
            public function toocheke_display_input_text($args)
            {
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" />',
                    $args['name'],
                    $args['name'],
                    get_option($args['name'], '') // 2 is the default number of slides
                );
            }
            public function toocheke_display_input_email($args)
            {
                printf(
                    '<input placeholder="Email address" type="email" id="%s" name="%s" value="%s" />',
                    $args['name'],
                    $args['name'],
                    get_option($args['name'], '') // 2 is the default number of slides
                );
            }
            public function toocheke_display_input_WYSIWYG($args)
            {
                printf(
                    wp_editor(
                        get_option($args['name'], ''),
                        $args['name'],
                        ['textarea_name' => $args['name']]
                    )
                );
            }
            public function toocheke_series_publish_options_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-display-multiple-series" value="1"
            <?php checked(1, get_option('toocheke-display-multiple-series'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_display_latest_comics_of_all_multiple_series_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-display-latest-comics-of-all-multiple-series" value="1"
            <?php checked(1, get_option('toocheke-display-latest-comics-of-all-multiple-series'), true); ?> /> Check for Yes
    <?php
            }

            public function toocheke_series_landing_blog_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-series-landing-blog" value="1"
            <?php checked(1, get_option('toocheke-series-landing-blog'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_traditional_home_series_dropdown($args)
            {
                $selected_series_id = get_option('toocheke-traditional-home-series', 0);
                $options_markup     = '<option value="0" ' . selected($selected_series_id, 0, false) . '>No series</option>';
                if (post_type_exists('series')):
                    $series_args = [
                        'post_type'   => 'series',
                        'post_status' => 'publish',
                        'nopaging'    => true,
                        'orderby'     => 'title',
                        'order'       => 'ASC',
                    ];
                    $series_query = new WP_Query($series_args);
                    if ($series_query->have_posts()):
                        while ($series_query->have_posts()): $series_query->the_post();
                            $post_id = get_the_ID();
                            $title   = get_the_title();
                            $options_markup .= sprintf('<option value="%s" %s>%s</option>', $post_id, selected($selected_series_id, $post_id, false), $title);
                        endwhile;
                        $series_query = null;
                        wp_reset_postdata();
                    endif;
                endif;
                printf('<select name="toocheke-traditional-home-series" id="toocheke-traditional-home-series">%1$s</select>', $options_markup);
            }

            public function toocheke_options_devices_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-comic-layout-devices" value="1"
            <?php checked(1, get_option('toocheke-comic-layout-devices'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comic_discussion_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-comic-discussion" value="1"
            <?php checked(1, get_option('toocheke-comic-discussion'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_paywalled_discussion_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-paywalled-discussion" value="1"
            <?php checked(1, get_option('toocheke-paywalled-discussion'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_hide_blog_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-hide-blog" value="1"
            <?php checked(1, get_option('toocheke-hide-blog'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_display_blog_webtoon_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-dspay-blog-on-webtoon" value="1"
            <?php checked(1, get_option('toocheke-dspay-blog-on-webtoon'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_age_verification_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-age-verification" value="1"
            <?php checked(1, get_option('toocheke-age-verification'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_bilingual_display_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-bilingual-display" value="1"
            <?php checked(1, get_option('toocheke-bilingual-display'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_enable_paypal_sandbox_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-enable-paypal-sandbox" value="1"
            <?php checked(1, get_option('toocheke-enable-paypal-sandbox'), true); ?> /> Enable
    <?php
            }
            public function toocheke_buy_original_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-original-art" value="1"
            <?php checked(1, get_option('toocheke-original-art'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_buy_print_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-print" value="1"
            <?php checked(1, get_option('toocheke-print'), true); ?> /> Check for Yes
    <?php
            }

            public function toocheke_image_click_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-image-click" value="1"
            <?php checked(1, get_option('toocheke-image-click'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comics_to_main_rss_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-comics-to-main-rss" value="1"
            <?php checked(1, get_option('toocheke-comics-to-main-rss'), true); ?> /> Check for Yes
    <?php
            }

            public function toocheke_comic_likes_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-comic-likes" value="1"
            <?php checked(1, get_option('toocheke-comic-likes'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comic_no_of_comments_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-comic-no-of-comments" value="1"
            <?php checked(1, get_option('toocheke-comic-no-of-comments'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comic_no_of_views_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-comic-no-of-views" value="1"
            <?php checked(1, get_option('toocheke-comic-no-of-views'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comic_panel_swipe_navigation_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-comic-panel-swipe-navigation" value="1"
            <?php checked(1, get_option('toocheke-comic-panel-swipe-navigation'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_social_share_facebook_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-facebook" value="1"
            <?php checked(1, get_option('toocheke-social-share-facebook'), true); ?> /> Check for Yes
    <?php
            }

            public function toocheke_social_share_twitter_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-twitter" value="1"
            <?php checked(1, get_option('toocheke-social-share-twitter'), true); ?> /> Check for Yes
    <?php
            }

            public function toocheke_social_share_tumblr_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-tumblr" value="1"
            <?php checked(1, get_option('toocheke-social-share-tumblr'), true); ?> /> Check for Yes
    <?php
            }

            public function toocheke_social_share_reddit_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-reddit" value="1"
            <?php checked(1, get_option('toocheke-social-share-reddit'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_social_share_threads_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-threads" value="1"
            <?php checked(1, get_option('toocheke-social-share-threads'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_social_share_bluesky_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-bluesky" value="1"
            <?php checked(1, get_option('toocheke-social-share-bluesky'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_social_share_whatsapp_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-whatsapp" value="1"
            <?php checked(1, get_option('toocheke-social-share-whatsapp'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_social_share_linkedin_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-linkedin" value="1"
            <?php checked(1, get_option('toocheke-social-share-linkedin'), true); ?> /> Check for Yes
    <?php
            }

            public function toocheke_social_share_copy_checkbox()
            {
    ?>
        <input type="checkbox" name="toocheke-social-share-copy" value="1"
            <?php checked(1, get_option('toocheke-social-share-copy'), true); ?> /> Check for Yes
    <?php
            }
            public function toocheke_support_link_buymeacoffee_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-buymeacoffee" value="<?php echo get_option('toocheke-support-link-buymeacoffee'); ?>" />
    <?php
            }
            public function toocheke_support_link_gumroad_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-gumroad" value="<?php echo get_option('toocheke-support-link-gumroad'); ?>" />
    <?php
            }
            public function toocheke_support_link_indiegogo_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-indiegogo" value="<?php echo get_option('toocheke-support-link-indiegogo'); ?>" />
    <?php
            }
            public function toocheke_support_link_kickstarter_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-kickstarter" value="<?php echo get_option('toocheke-support-link-kickstarter'); ?>" />
    <?php
            }
            public function toocheke_support_link_kofi_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-kofi" value="<?php echo get_option('toocheke-support-link-kofi'); ?>" />
    <?php
            }
            public function toocheke_support_link_liberapay_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-liberapay" value="<?php echo get_option('toocheke-support-link-liberapay'); ?>" />
    <?php
            }
            public function toocheke_support_link_patreon_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-patreon" value="<?php echo get_option('toocheke-support-link-patreon'); ?>" />
    <?php
            }
            public function toocheke_support_link_paypal_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-paypal" value="<?php echo get_option('toocheke-support-link-paypal'); ?>" />
    <?php
            }

            public function toocheke_support_link_substack_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-substack" value="<?php echo get_option('toocheke-support-link-substack'); ?>" />
    <?php
            }
            public function toocheke_support_link_tipeee_url()
            {
    ?>
        <input type="url" name="toocheke-support-link-tipeee" value="<?php echo get_option('toocheke-support-link-tipeee'); ?>" />
    <?php
            }
            public function toocheke_infinite_scroll_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-infinite-scroll" name="toocheke-infinite-scroll" value="1"
            <?php checked(1, get_option('toocheke-infinite-scroll'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_random_navigation_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-random-navigation" name="toocheke-random-navigation" value="1"
            <?php checked(1, get_option('toocheke-random-navigation'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comic_archive_navigation_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-comic-archive-navigation" name="toocheke-comic-archive-navigation" value="1"
            <?php checked(1, get_option('toocheke-comic-archive-navigation'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_click_comic_next_navigation_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-click-comic-next" name="toocheke-click-comic-next" value="1"
            <?php checked(1, get_option('toocheke-click-comic-next'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comic_nav_above_comic_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-comic-nav-above-comic" name="toocheke-comic-nav-above-comic" value="1"
            <?php checked(1, get_option('toocheke-comic-nav-above-comic'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_chapter_navigation_buttons_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-chapter-navigation-buttons" name="toocheke-chapter-navigation-buttons" value="1"
            <?php checked(1, get_option('toocheke-chapter-navigation-buttons'), 1); ?> /> Check for Yes
    <?php
            }

            public function toocheke_chapter_dropdown_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-chapter-dropdown" name="toocheke-chapter-dropdown" value="1"
            <?php checked(1, get_option('toocheke-chapter-dropdown'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_keyboard_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-keyboard" name="toocheke-keyboard" value="1"
            <?php checked(1, get_option('toocheke-keyboard'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_scroll_past_header_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-scroll-past-header" name="toocheke-scroll-past-header" value="1"
            <?php checked(1, get_option('toocheke-scroll-past-header'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_always_show_nav_buttons_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-always-show-nav-buttons" name="toocheke-always-show-nav-buttons" value="1"
            <?php checked(1, get_option('toocheke-always-show-nav-buttons'), 1); ?> /> Check for Yes
    <?php
            }

            public function toocheke_early_access_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-early-access" name="toocheke-early-access" value="1"
            <?php checked(1, get_option('toocheke-early-access'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comic_bookmark_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-comic-bookmark" name="toocheke-comic-bookmark" value="1"
            <?php checked(1, get_option('toocheke-comic-bookmark'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comics_navigation_checkbox()
            {
    ?>
        <input type="checkbox" id="toocheke-comics-navigation" name="toocheke-comics-navigation" value="1"
            <?php checked(1, get_option('toocheke-comics-navigation'), 1); ?> /> Check for Yes
    <?php
            }
            public function toocheke_comics_order_radio()
            {
    ?>
        <input type="radio" name="toocheke-comics-order" value="ASC"
            <?php checked('ASC', get_option('toocheke-comics-order'), true); ?>> Ascending
        <input type="radio" name="toocheke-comics-order" value="DESC"
            <?php checked('DESC', get_option('toocheke-comics-order'), true); ?>> Descending
    <?php
            }
            public function toocheke_comics_slider_order_radio()
            {
    ?>
        <input type="radio" name="toocheke-comics-slider-order" value="ASC"
            <?php checked('ASC', get_option('toocheke-comics-slider-order'), true); ?>> Ascending
        <input type="radio" name="toocheke-comics-slider-order" value="DESC"
            <?php checked('DESC', get_option('toocheke-comics-slider-order'), true); ?>> Descending
    <?php
            }
            public function toocheke_chapter_first_comic_radio()
            {
    ?>
        <input type="radio" name="toocheke-chapter-first-comic" value="ASC"
            <?php checked('ASC', get_option('toocheke-chapter-first-comic'), true); ?>> Oldest
        <input type="radio" name="toocheke-chapter-first-comic" value="DESC"
            <?php checked('DESC', get_option('toocheke-chapter-first-comic'), true); ?>> Latest
    <?php
            }
            public function toocheke_collection_first_comic_radio()
            {
    ?>
        <input type="radio" name="toocheke-collection-first-comic" value="ASC"
            <?php checked('ASC', get_option('toocheke-collection-first-comic'), true); ?>> Oldest
        <input type="radio" name="toocheke-collection-first-comic" value="DESC"
            <?php checked('DESC', get_option('toocheke-collection-first-comic'), true); ?>> Latest
    <?php
            }
            public function toocheke_series_order_radio()
            {
    ?>
        <input type="radio" name="toocheke-series-order" value="ASC"
            <?php checked('ASC', get_option('toocheke-series-order'), true); ?>> Ascending
        <input type="radio" name="toocheke-series-order" value="DESC"
            <?php checked('DESC', get_option('toocheke-series-order'), true); ?>> Descending
    <?php
            }

            public function toocheke_comics_archive_layout_select()
            {
                $options = get_option('toocheke-comics-archive');
    ?>
        <select name="toocheke-comics-archive[layout_type]">
            <option value="" disabled selected>Select your option</option>
            <option value="thumbnail-list"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "thumbnail-list"); ?>>Thumbnail
                List</option>
            <option value="plain-text-list"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "plain-text-list"); ?>>Plain Text
                List
            </option>
            <option value="calendar"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "calendar"); ?>>Calendar</option>
            <option value="gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "gallery"); ?>>
                Gallery/Grid</option>
            <option value="chapters-plain-text-list" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "chapters-plain-text-list"); ?>>
                Segmented By Chapters - Plain Text List</option>
            <option value="chapters-gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "chapters-gallery"); ?>>
                Segmented By Chapters - Gallery/Grid</option>
            <option value="series-plain-text-list" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "series-plain-text-list"); ?>>
                Segmented By Series - Plain Text List</option>
            <option value="series-gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "series-gallery"); ?>>
                Segmented By Series - Gallery/Grid</option>
            <option value="yearly-plain-text-list" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "yearly-plain-text-list"); ?>>
                Segmented By Year - Plain Text List</option>
            <option value="yearly-gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "yearly-gallery"); ?>>
                Segmented By Year - Gallery/Grid</option>
        </select>
    <?php
            }
            public function toocheke_top_10_comics_layout_select()
            {
                $options = get_option('toocheke-top-10-comics-layout');
    ?>
        <select name="toocheke-top-10-comics-layout[layout_type]">
            <option value="" disabled selected>Select your option</option>
            <option value="thumbnail-list"
                <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "thumbnail-list"); ?>>Thumbnail
                List</option>
            <option value="gallery" <?php selected(isset($options['layout_type']) ? $options['layout_type'] : '', "gallery"); ?>>
                Gallery/Grid</option>
        </select>
    <?php
            }
            public function toocheke_first_button_upload()
            {
                $first_button = esc_attr(get_option('toocheke-first-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-first-button"
            data-hidden="toocheke-first-button" data-image="first-image">
        <input type="hidden" id="toocheke-first-button" name="toocheke-first-button" value="<?php echo $first_button ?>" />
    <?php
            }
            public function toocheke_previous_button_upload()
            {
                $previous_button = esc_attr(get_option('toocheke-previous-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-previous-button"
            data-hidden="toocheke-previous-button" data-image="previous-image">
        <input type="hidden" id="toocheke-previous-button" name="toocheke-previous-button"
            value="<?php echo $previous_button ?>" />
    <?php
            }
            public function toocheke_random_button_upload()
            {
                $random_button = esc_attr(get_option('toocheke-random-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-random-button"
            data-hidden="toocheke-random-button" data-image="random-image">
        <input type="hidden" id="toocheke-random-button" name="toocheke-random-button" value="<?php echo $random_button ?>" />
    <?php
            }
            public function toocheke_comic_archive_button_upload()
            {
                $comic_archive_button = esc_attr(get_option('toocheke-comic-archive-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-comic-archive-button"
            data-hidden="toocheke-comic-archive-button" data-image="comic-archive-image">
        <input type="hidden" id="toocheke-comic-archive-button" name="toocheke-comic-archive-button" value="<?php echo $comic_archive_button ?>" />
    <?php
            }
            public function toocheke_next_button_upload()
            {
                $next_button = esc_attr(get_option('toocheke-next-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-next-button"
            data-hidden="toocheke-next-button" data-image="next-image">
        <input type="hidden" id="toocheke-next-button" name="toocheke-next-button" value="<?php echo $next_button ?>" />
    <?php
            }
            public function toocheke_latest_button_upload()
            {
                $latest_button = esc_attr(get_option('toocheke-latest-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-latest-button"
            data-hidden="toocheke-latest-button" data-image="latest-image">
        <input type="hidden" id="toocheke-latest-button" name="toocheke-latest-button" value="<?php echo $latest_button ?>" />
    <?php
            }
            public function toocheke_next_chapter_button_upload()
            {
                $next_chapter_button = esc_attr(get_option('toocheke-next-chapter-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-next-chapter-button"
            data-hidden="toocheke-next-chapter-button" data-image="next-chapter-image">
        <input type="hidden" id="toocheke-next-chapter-button" name="toocheke-next-chapter-button" value="<?php echo $next_chapter_button ?>" />
    <?php
            }
            public function toocheke_previous_chapter_button_upload()
            {
                $previous_chapter_button = esc_attr(get_option('toocheke-previous-chapter-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-previous-chapter-button"
            data-hidden="toocheke-previous-chapter-button" data-image="previous-chapter-image">
        <input type="hidden" id="toocheke-previous-chapter-button" name="toocheke-previous-chapter-button" value="<?php echo $previous_chapter_button ?>" />
    <?php
            }

            public function toocheke_facebook_button_upload()
            {
                $facebook_button = esc_attr(get_option('toocheke-facebook-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-facebook-button"
            data-hidden="toocheke-facebook-button" data-image="facebook-image">
        <input type="hidden" id="toocheke-facebook-button" name="toocheke-facebook-button"
            value="<?php echo $facebook_button ?>" />
    <?php
            }
            public function toocheke_twitter_button_upload()
            {
                $twitter_button = esc_attr(get_option('toocheke-twitter-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-twitter-button"
            data-hidden="toocheke-twitter-button" data-image="twitter-image">
        <input type="hidden" id="toocheke-twitter-button" name="toocheke-twitter-button"
            value="<?php echo $twitter_button ?>" />
    <?php
            }
            public function toocheke_tumblr_button_upload()
            {
                $tumblr_button = esc_attr(get_option('toocheke-tumblr-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-tumblr-button"
            data-hidden="toocheke-tumblr-button" data-image="tumblr-image">
        <input type="hidden" id="toocheke-tumblr-button" name="toocheke-tumblr-button" value="<?php echo $tumblr_button ?>" />
    <?php
            }
            public function toocheke_reddit_button_upload()
            {
                $reddit_button = esc_attr(get_option('toocheke-reddit-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-reddit-button"
            data-hidden="toocheke-reddit-button" data-image="reddit-image">
        <input type="hidden" id="toocheke-reddit-button" name="toocheke-reddit-button" value="<?php echo $reddit_button ?>" />
    <?php
            }
            public function toocheke_threads_button_upload()
            {
                $threads_button = esc_attr(get_option('toocheke-threads-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-threads-button"
            data-hidden="toocheke-threads-button" data-image="threads-image">
        <input type="hidden" id="toocheke-threads-button" name="toocheke-threads-button" value="<?php echo $threads_button ?>" />
    <?php
            }
            public function toocheke_bluesky_button_upload()
            {
                $bluesky_button = esc_attr(get_option('toocheke-bluesky-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-bluesky-button"
            data-hidden="toocheke-bluesky-button" data-image="bluesky-image">
        <input type="hidden" id="toocheke-bluesky-button" name="toocheke-bluesky-button" value="<?php echo $bluesky_button ?>" />
    <?php
            }
            public function toocheke_whatsapp_button_upload()
            {
                $whatsapp_button = esc_attr(get_option('toocheke-whatsapp-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-whatsapp-button"
            data-hidden="toocheke-whatsapp-button" data-image="whatsapp-image">
        <input type="hidden" id="toocheke-whatsapp-button" name="toocheke-whatsapp-button" value="<?php echo $whatsapp_button ?>" />
    <?php
            }
            public function toocheke_linkedin_button_upload()
            {
                $linkedin_button = esc_attr(get_option('toocheke-linkedin-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-linkedin-button"
            data-hidden="toocheke-linkedin-button" data-image="linkedin-image">
        <input type="hidden" id="toocheke-linkedin-button" name="toocheke-linkedin-button" value="<?php echo $linkedin_button ?>" />
    <?php
            }

            public function toocheke_copy_button_upload()
            {
                $copy_button = esc_attr(get_option('toocheke-copy-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-copy-button"
            data-hidden="toocheke-copy-button" data-image="copy-image">
        <input type="hidden" id="toocheke-copy-button" name="toocheke-copy-button" value="<?php echo $copy_button ?>" />
    <?php
            }
            public function toocheke_buymeacoffee_button_upload()
            {
                $buymeacoffee_button = esc_attr(get_option('toocheke-buymeacoffee-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-buymeacoffee-button"
            data-hidden="toocheke-buymeacoffee-button" data-image="buymeacoffee-image">
        <input type="hidden" id="toocheke-buymeacoffee-button" name="toocheke-buymeacoffee-button" value="<?php echo $buymeacoffee_button ?>" />
    <?php
            }
            public function toocheke_gumroad_button_upload()
            {
                $gumroad_button = esc_attr(get_option('toocheke-gumroad-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-gumroad-button"
            data-hidden="toocheke-gumroad-button" data-image="gumroad-image">
        <input type="hidden" id="toocheke-gumroad-button" name="toocheke-gumroad-button" value="<?php echo $gumroad_button ?>" />
    <?php
            }
            public function toocheke_indiegogo_button_upload()
            {
                $indiegogo_button = esc_attr(get_option('toocheke-indiegogo-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-indiegogo-button"
            data-hidden="toocheke-indiegogo-button" data-image="indiegogo-image">
        <input type="hidden" id="toocheke-indiegogo-button" name="toocheke-indiegogo-button" value="<?php echo $indiegogo_button ?>" />
    <?php
            }
            public function toocheke_kickstarter_button_upload()
            {
                $kickstarter_button = esc_attr(get_option('toocheke-kickstarter-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-kickstarter-button"
            data-hidden="toocheke-kickstarter-button" data-image="kickstarter-image">
        <input type="hidden" id="toocheke-kickstarter-button" name="toocheke-kickstarter-button" value="<?php echo $kickstarter_button ?>" />
    <?php
            }
            public function toocheke_kofi_button_upload()
            {
                $kofi_button = esc_attr(get_option('toocheke-kofi-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-kofi-button"
            data-hidden="toocheke-kofi-button" data-image="kofi-image">
        <input type="hidden" id="toocheke-kofi-button" name="toocheke-kofi-button" value="<?php echo $kofi_button ?>" />
    <?php
            }
            public function toocheke_liberapay_button_upload()
            {
                $liberapay_button = esc_attr(get_option('toocheke-liberapay-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-liberapay-button"
            data-hidden="toocheke-liberapay-button" data-image="liberapay-image">
        <input type="hidden" id="toocheke-liberapay-button" name="toocheke-liberapay-button" value="<?php echo $liberapay_button ?>" />
    <?php
            }
            public function toocheke_patreon_button_upload()
            {
                $patreon_button = esc_attr(get_option('toocheke-patreon-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-patreon-button"
            data-hidden="toocheke-patreon-button" data-image="patreon-image">
        <input type="hidden" id="toocheke-patreon-button" name="toocheke-patreon-button" value="<?php echo $patreon_button ?>" />
    <?php
            }
            public function toocheke_paypal_button_upload()
            {
                $paypal_button = esc_attr(get_option('toocheke-paypal-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-paypal-button"
            data-hidden="toocheke-paypal-button" data-image="paypal-image">
        <input type="hidden" id="toocheke-paypal-button" name="toocheke-paypal-button" value="<?php echo $paypal_button ?>" />
    <?php
            }
            public function toocheke_substack_button_upload()
            {
                $substack_button = esc_attr(get_option('toocheke-substack-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-substack-button"
            data-hidden="toocheke-substack-button" data-image="substack-image">
        <input type="hidden" id="toocheke-substack-button" name="toocheke-substack-button" value="<?php echo $substack_button ?>" />
    <?php
            }
            public function toocheke_tipeee_button_upload()
            {
                $tipeee_button = esc_attr(get_option('toocheke-tipeee-button'));
    ?>
        <input class="upload-custom-button" type="button" value="Choose an image" id="upload-tipeee-button"
            data-hidden="toocheke-tipeee-button" data-image="tipeee-image">
        <input type="hidden" id="toocheke-tipeee-button" name="toocheke-tipeee-button" value="<?php echo $tipeee_button ?>" />
    <?php
            }

            public function toocheke_first_button_preview()
            {
                $first_button = esc_attr(get_option('toocheke-first-button'));
    ?>
        <div id="first_button_preview">
            <img id="first-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($first_button) ? $first_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_previous_button_preview()
            {
                $previous_button = esc_attr(get_option('toocheke-previous-button'));
    ?>
        <div id="previous_button_preview">
            <img id="previous-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($previous_button) ? $previous_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_random_button_preview()
            {
                $random_button = esc_attr(get_option('toocheke-random-button'));
    ?>
        <div id="random_button_preview">
            <img id="random-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($random_button) ? $random_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_comic_archive_button_preview()
            {
                $comic_archive_button = esc_attr(get_option('toocheke-comic-archive-button'));
    ?>
        <div id="comic_archive_button_preview">
            <img id="comic-archive-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($comic_archive_button) ? $comic_archive_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_next_button_preview()
            {
                $next_button = esc_attr(get_option('toocheke-next-button'));
    ?>
        <div id="next_button_preview">
            <img id="next-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($next_button) ? $next_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_next_chapter_button_preview()
            {
                $next_chapter_button = esc_attr(get_option('toocheke-next-chapter-button'));
    ?>
        <div id="next_chapter_button_preview">
            <img id="next-chapter-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($next_chapter_button) ? $next_chapter_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_latest_button_preview()
            {
                $latest_button = esc_attr(get_option('toocheke-latest-button'));
    ?>
        <div id="latest_button_preview">
            <img id="latest-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($latest_button) ? $latest_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_previous_chapter_button_preview()
            {
                $previous_chapter_button = esc_attr(get_option('toocheke-previous-chapter-button'));
    ?>
        <div id="previous_chapter_button_preview">
            <img id="previous-chapter-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($previous_chapter_button) ? $previous_chapter_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_facebook_button_preview()
            {
                $facebook_button = esc_attr(get_option('toocheke-facebook-button'));
    ?>
        <div id="facebook_button_preview">
            <img id="facebook-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($facebook_button) ? $facebook_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_twitter_button_preview()
            {
                $twitter_button = esc_attr(get_option('toocheke-twitter-button'));
    ?>
        <div id="twitter_button_preview">
            <img id="twitter-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($twitter_button) ? $twitter_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_tumblr_button_preview()
            {
                $tumblr_button = esc_attr(get_option('toocheke-tumblr-button'));
    ?>
        <div id="tumblr_button_preview">
            <img id="tumblr-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($tumblr_button) ? $tumblr_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_reddit_button_preview()
            {
                $reddit_button = esc_attr(get_option('toocheke-reddit-button'));
    ?>
        <div id="reddit_button_preview">
            <img id="reddit-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($reddit_button) ? $reddit_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_threads_button_preview()
            {
                $threads_button = esc_attr(get_option('toocheke-threads-button'));
    ?>
        <div id="threads_button_preview">
            <img id="threads-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($threads_button) ? $threads_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_bluesky_button_preview()
            {
                $bluesky_button = esc_attr(get_option('toocheke-bluesky-button'));
    ?>
        <div id="bluesky_button_preview">
            <img id="bluesky-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($bluesky_button) ? $bluesky_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_whatsapp_button_preview()
            {
                $whatsapp_button = esc_attr(get_option('toocheke-whatsapp-button'));
    ?>
        <div id="whatsapp_button_preview">
            <img id="whatsapp-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($whatsapp_button) ? $whatsapp_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_linkedin_button_preview()
            {
                $linkedin_button = esc_attr(get_option('toocheke-linkedin-button'));
    ?>
        <div id="linkedin_button_preview">
            <img id="linkedin-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($linkedin_button) ? $linkedin_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }

            public function toocheke_copy_button_preview()
            {
                $copy_button = esc_attr(get_option('toocheke-copy-button'));
    ?>
        <div id="copy_button_preview">
            <img id="copy-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($copy_button) ? $copy_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_buymeacoffee_button_preview()
            {
                $buymeacoffee_button = esc_attr(get_option('toocheke-buymeacoffee-button'));
    ?>
        <div id="buymeacoffee_button_preview">
            <img id="buymeacoffee-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($buymeacoffee_button) ? $buymeacoffee_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_gumroad_button_preview()
            {
                $gumroad_button = esc_attr(get_option('toocheke-gumroad-button'));
    ?>
        <div id="gumroad_button_preview">
            <img id="gumroad-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($gumroad_button) ? $gumroad_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_indiegogo_button_preview()
            {
                $indiegogo_button = esc_attr(get_option('toocheke-indiegogo-button'));
    ?>
        <div id="indiegogo_button_preview">
            <img id="indiegogo-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($indiegogo_button) ? $indiegogo_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_kickstarter_button_preview()
            {
                $kickstarter_button = esc_attr(get_option('toocheke-kickstarter-button'));
    ?>
        <div id="kickstarter_button_preview">
            <img id="kickstarter-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($kickstarter_button) ? $kickstarter_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_kofi_button_preview()
            {
                $kofi_button = esc_attr(get_option('toocheke-kofi-button'));
    ?>
        <div id="kofi_button_preview">
            <img id="kofi-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($kofi_button) ? $kofi_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_liberapay_button_preview()
            {
                $liberapay_button = esc_attr(get_option('toocheke-liberapay-button'));
    ?>
        <div id="liberapay_button_preview">
            <img id="liberapay-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($liberapay_button) ? $liberapay_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_patreon_button_preview()
            {
                $patreon_button = esc_attr(get_option('toocheke-patreon-button'));
    ?>
        <div id="patreon_button_preview">
            <img id="patreon-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($patreon_button) ? $patreon_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_paypal_button_preview()
            {
                $paypal_button = esc_attr(get_option('toocheke-paypal-button'));
    ?>
        <div id="paypal_button_preview">
            <img id="paypal-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($paypal_button) ? $paypal_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_substack_button_preview()
            {
                $substack_button = esc_attr(get_option('toocheke-substack-button'));
    ?>
        <div id="substack_button_preview">
            <img id="substack-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($substack_button) ? $substack_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }
            public function toocheke_tipeee_button_preview()
            {
                $tipeee_button = esc_attr(get_option('toocheke-tipeee-button'));
    ?>
        <div id="tipeee_button_preview">
            <img id="tipeee-image" style="max-height:35px"
                src="<?php echo esc_url((! empty($tipeee_button) ? $tipeee_button : plugins_url('toocheke-companion' . '/img/no-image.png'))); ?>" />
        </div>
    <?php
            }

            /**
             * Implement default image link.
             */
            public function toocheke_default_image_settings()
            {
                update_option('image_default_align', 'none');
                update_option('image_default_size', 'full');
                update_option('image_default_link_type', 'none');
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
             * Add Admin Menus.
             */
            //showing the import comic easel page
            public function toocheke_include_import_comic_easel_page()
            {
                require_once 'inc/toocheke-companion-import-comic-easel.php';
            }
            //showing the import webcomic page
            public function toocheke_include_import_webcomic_page()
            {
                require_once 'inc/toocheke-companion-import-webcomic.php';
            }

            /**
             * Custom Title
             */
            public function toocheke_comic_title_2nd_language_meta_box()
            {
                add_meta_box(
                    'comic-title-2nd-language',
                    __('Comic Title for 2nd Language', 'toocheke-companion'),
                    [$this, 'toocheke_comic_title_2nd_language_display'],
                    'comic',
                    "normal",
                    "high"
                );
            }
            //Displaying the meta box
            public function toocheke_comic_title_2nd_language_display($post)
            { ?>

        <?php
                $content = get_post_meta($post->ID, 'comic-title-2nd-language-display', true);
        ?>
        <h4 style='color: #2271b1;'>If you have a bilingual comic, add the title of the comic in the 2nd language here.</h4>
        <p>
            <input class="widefat" type="text" name="comic-title-2nd-language-display" id="comic-title-2nd-language-display"
                value="<?php echo esc_attr($content); ?>" size="30" />
        </p>
    <?php }
            //This function saves the data you put in the meta box
            public function toocheke_comic_title_2nd_language_display_save_postdata($post_id)
            {
                if (isset($_POST['toocheke_comic_title_2nd_language_display_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['toocheke_comic_title_2nd_language_display_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }

                /* Get the posted data and sanitize it for use as an HTML class. */
                if (isset($_POST['comic-title-2nd-language-display'])) {
                    $data = $_POST['comic-title-2nd-language-display'];
                    update_post_meta($post_id, 'comic-title-2nd-language-display', $data);
                }
            }
            /**
             * Extra WYSIWYG meta boxy editor for comics
             */
            //This function initializes the meta box.
            public function toocheke_desktop_comic_editor_meta_box()
            {
                add_meta_box(
                    'desktop-comic-editor',
                    __('Desktop Comic Editor', 'toocheke-companion'),
                    [$this, 'toocheke_desktop_comic_editor'],
                    'comic',
                    "normal",
                    "high"
                );
            }

            //Displaying the meta box
            public function toocheke_desktop_comic_editor($post)
            {
                echo "<h4 style='color: #2271b1;'>Add your unsliced comic image for desktop users here</h4>";
                $content = get_post_meta($post->ID, 'desktop_comic_editor', true);

                //This function adds the WYSIWYG Editor
                wp_editor(
                    $content,
                    'desktop_comic_editor',
                    ["media_buttons" => true]
                );
            }

            //This function saves the data you put in the meta box
            public function toocheke_desktop_comic_editor_save_postdata($post_id)
            {

                if (isset($_POST['desktop_comic_editor_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['desktop_comic_editor_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }
                if (isset($_POST['desktop_comic_editor'])) {
                    $data = wp_kses_post($_POST['desktop_comic_editor']);
                    update_post_meta($post_id, 'desktop_comic_editor', $data);
                }
            }

            /**
             * Extra WYSIWYG meta box editor for comic's blog post
             */
            //This function initializes the meta box.
            public function toocheke_comic_blog_post_editor_meta_box()
            {
                add_meta_box(
                    'comic-blog-post-editor',
                    __("Comic's Blog Post Editor", 'toocheke-companion'),
                    [$this, 'toocheke_comic_blog_post_editor'],
                    'comic',
                    "normal",
                    "high"
                );
            }

            //Displaying the meta box
            public function toocheke_comic_blog_post_editor($post)
            {
                echo "<h4 style='color: #2271b1;'>Add the blog post for your comic here.</h4>";
                $content = get_post_meta($post->ID, 'comic_blog_post_editor', true);

                //This function adds the WYSIWYG Editor
                wp_editor(
                    $content,
                    'comic_blog_post_editor',
                    ["media_buttons" => true, 'wpautop' => false]
                );
            }

            //This function saves the data you put in the meta box
            public function toocheke_comic_blog_post_editor_save_postdata($post_id)
            {

                if (isset($_POST['comic_blog_post_editor_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['comic_blog_post_editor_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }
                if (isset($_POST['comic_blog_post_editor'])) {
                    $data = wp_kses_post($_POST['comic_blog_post_editor']);
                    update_post_meta($post_id, 'comic_blog_post_editor', $data);
                }
            }
            /**
             * Extra WYSIWYG meta boxy editor for comics
             */
            //This function initializes the meta box.
            public function toocheke_2nd_language_mobile_comic_editor_meta_box()
            {
                add_meta_box(
                    'mobile-comic-2nd-language-editor',
                    __('Mobile Comic Editor for 2nd Language', 'toocheke-companion'),
                    [$this, 'toocheke_2nd_language_mobile_comic_editor'],
                    'comic',
                    "normal",
                    "high"
                );
            }

            //Displaying the meta box
            public function toocheke_2nd_language_mobile_comic_editor($post)
            {
                echo "<h4 style='color: #2271b1;'>If you have a bilingual comic, add your mobile-friendly, sliced comic, images for mobile users here.</h4>";
                $content = get_post_meta($post->ID, 'mobile_comic_2nd_language_editor', true);

                //This function adds the WYSIWYG Editor
                wp_editor(
                    $content,
                    'mobile_comic_2nd_language_editor',
                    ["media_buttons" => true]
                );
            }

            //This function saves the data you put in the meta box
            public function toocheke_2nd_language_mobile_comic_editor_save_postdata($post_id)
            {

                if (isset($_POST['mobile_comic_2nd_language_editor_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['mobile_comic_2nd_language_editor_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }
                if (isset($_POST['mobile_comic_2nd_language_editor'])) {
                    $data = wp_kses_post($_POST['mobile_comic_2nd_language_editor']);
                    update_post_meta($post_id, 'mobile_comic_2nd_language_editor', $data);
                }
            }

            /**
             * Extra WYSIWYG meta box editor for comic's blog post
             */
            //This function initializes the meta box.
            public function toocheke_2nd_language_comic_blog_post_editor_meta_box()
            {
                add_meta_box(
                    'comic-2nd-language-blog-post-editor',
                    __("Comic's Blog Post Editor for 2nd Language", 'toocheke-companion'),
                    [$this, 'toocheke_2nd_language_comic_blog_post_editor'],
                    'comic',
                    "normal",
                    "high"
                );
            }

            //Displaying the meta box
            public function toocheke_2nd_language_comic_blog_post_editor($post)
            {
                echo "<h4 style='color: #2271b1;'>If you have a bilingual comic, add the blog post for the comic in the 2nd language here.</h4>";
                $content = get_post_meta($post->ID, 'comic_2nd_language_blog_post_editor', true);

                //This function adds the WYSIWYG Editor
                wp_editor(
                    $content,
                    'comic_2nd_language_blog_post_editor',
                    ["media_buttons" => true, 'wpautop' => false]
                );
            }

            //This function saves the data you put in the meta box
            public function toocheke_2nd_language_comic_blog_post_editor_save_postdata($post_id)
            {

                if (isset($_POST['comic_2nd_language_blog_post_editor_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['comic_2nd_language_blog_post_editor_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }
                if (isset($_POST['comic_2nd_language_blog_post_editor'])) {
                    $data = wp_kses_post($_POST['comic_2nd_language_blog_post_editor']);
                    update_post_meta($post_id, 'comic_2nd_language_blog_post_editor', $data);
                }
            }
            /**
             * Extra WYSIWYG meta boxy editor for comics
             */
            //This function initializes the meta box.
            public function toocheke_2nd_language_desktop_comic_editor_meta_box()
            {
                add_meta_box(
                    'desktop-comic-2nd-language-editor',
                    __('Desktop Comic Editor for 2nd Language', 'toocheke-companion'),
                    [$this, 'toocheke_2nd_language_desktop_comic_editor'],
                    'comic',
                    "normal",
                    "high"
                );
            }

            //Displaying the meta box
            public function toocheke_2nd_language_desktop_comic_editor($post)
            {
                echo "<h4 style='color: #2271b1;'>If you have a bilingual comic, add your unsliced comic image for desktop users here.</h4>";
                $content = get_post_meta($post->ID, 'desktop_comic_2nd_language_editor', true);

                //This function adds the WYSIWYG Editor
                wp_editor(
                    $content,
                    'desktop_comic_2nd_language_editor',
                    ["media_buttons" => true]
                );
            }

            //This function saves the data you put in the meta box
            public function toocheke_2nd_language_desktop_comic_editor_save_postdata($post_id)
            {

                if (isset($_POST['desktop_comic_2nd_language_editor_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['desktop_comic_2nd_language_editor_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }
                if (isset($_POST['desktop_comic_2nd_language_editor'])) {
                    $data = wp_kses_post($_POST['desktop_comic_2nd_language_editor']);
                    update_post_meta($post_id, 'desktop_comic_2nd_language_editor', $data);
                }
            }
            /**
             * Alt hover text
             */
            public function toocheke_comic_alt_meta_box()
            {
                add_meta_box(
                    'comic-alt',
                    __('Alt (Hover) Text', 'toocheke-companion'),
                    [$this, 'toocheke_comic_alt_display'],
                    'comic',
                    "normal",
                    "core"
                );
            }
            //Displaying the meta box
            public function toocheke_comic_alt_display($post)
            { ?>

        <?php
                $content = get_post_meta($post->ID, 'comic-hovertext', true);
        ?>
        <h4 style='color: #2271b1;'>Enter the text that will appear will appear when you mouse over the comic here.</h4>
        <p>
            <textarea class="widefat" type="text" name="comic-hovertext" id="comic-hovertext" style="width:100%">
<?php echo esc_attr($content); ?>
</textarea>

        </p>
    <?php }
            //This function saves the data you put in the meta box
            public function toocheke_comic_alt_save_postdata($post_id)
            {
                if (isset($_POST['toocheke_comic_alt_display_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['toocheke_comic_alt_display_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }

                /* Get the posted data and sanitize it for use as an HTML class. */
                if (isset($_POST['comic-hovertext'])) {
                    $data = $_POST['comic-hovertext'];
                    update_post_meta($post_id, 'comic-hovertext', $data);
                }
            }
            /**
             * Display Transcript
             */

            public function toocheke_comic_transcript_meta_box()
            {
                add_meta_box(
                    'comic-transcript',
                    __('Transcript', 'toocheke-companion'),
                    [$this, 'toocheke_comic_transcript_display'],
                    'comic',
                    "normal",
                    "core"
                );
            }
            //Displaying the meta box
            public function toocheke_comic_transcript_display($post)
            { ?>

        <?php
                $content = get_post_meta($post->ID, 'transcript', true);
        ?>
        <h4 style='color: #2271b1;'>Enter the text that will appear as the comic's transcript here.</h4>
        <p>
            <textarea class="widefat" type="text" name="comic-transcript" id="comic-transcript" rows="10">
<?php echo esc_attr($content); ?>
</textarea>

        </p>
    <?php }
            //This function saves the data you put in the meta box
            public function toocheke_comic_transcript_save_postdata($post_id)
            {
                if (isset($_POST['toocheke_comic_transcript_display_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['toocheke_comic_transcript_display_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }

                /* Get the posted data and sanitize it for use as an HTML class. */
                if (isset($_POST['comic-transcript'])) {
                    $data = $_POST['comic-transcript'];
                    update_post_meta($post_id, 'transcript', $data);
                }
            }
            /**
             * Display Age verification metabox
             */
            public function toocheke_age_verification_meta_box()
            {
                add_meta_box(
                    'age-verification',
                    __('Age Verification', 'toocheke-companion'),
                    [$this, 'toocheke_age_verification_display'],
                    ['comic', 'series'],
                    "side",
                    "core"
                );
            }
            //Displaying the meta box
            public function toocheke_age_verification_display($post)
            { ?>

        <?php
                wp_nonce_field(plugin_basename(__FILE__), 'toocheke_age_verification_nonce');

                $age_verification = get_post_meta($post->ID, 'age-verification', true);
                printf(
                    "<h4 style='color: #2271b1;'>Enable this option if you want to verify the age for this %s.</h4>",
                    esc_html($post->post_type)
                );
        ?>

        <p>
            <input value="on" type="checkbox" id="age-verification" name="age-verification" <?php if ($age_verification == "on"): echo " checked";
                                                                                            endif ?>>Verify age?<br />

        </p>
    <?php }
            //This function saves the data you put in the meta box
            public function toocheke_age_verification_save_postdata($post_id)
            {
                if (isset($_POST['toocheke_age_verification_nonce']) && (isset($_POST['comic']) || isset($_POST['series']))) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['toocheke_age_verification_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic'] || 'post' == $_POST['series']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }

                if (! empty($_POST['age-verification'])) {
                    /* Get the posted data and sanitize it for use as an HTML class. */
                    $data = sanitize_text_field(wp_unslash($_POST['age-verification']));
                    if ($_POST["age-verification"] == "on") {
                        $age_verification_checked = "on";
                    } else {
                        $age_verification_checked = "off";
                    }
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
                    update_post_meta($post_id, 'age-verification', $age_verification_checked);
                } else {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
                    delete_post_meta($post_id, 'age-verification');
                }
            }
            /**
             * Audio meta box field for comic
             */
            public function toocheke_audio_meta_box()
            {
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                    add_meta_box(
                        'comic-audio-meta-box',
                        __("Comic's Audio", 'toocheke-companion'),
                        [$this, 'toocheke_display_audio_meta_box'],
                        'comic',
                        'side'
                    );
                }
            }

            //Displaying the meta box
            public function toocheke_display_audio_meta_box($post)
            {
                wp_nonce_field(plugin_basename(__FILE__), 'comic_audio_metabox_nonce');
                $html = '<p class="description">Upload your MP3 file here.</p>';
                $html .= '<input id="comic_audio" name="comic_audio" size="25" type="file" value="" />';

                $filearray = get_post_meta(get_the_ID(), 'comic_audio', true);
                if (! empty($filearray['url'])) {
                    $this_file = $filearray['url'];
                    if ($this_file != '') {
                        $html .= '<div>
        <p><b>Current file:</b><br /> <small style="color: green;">' . $this_file . '</small></p>
        <p><b>Remove this file?</b> <input type="checkbox" id="remove_comic_audio" name="remove_comic_audio" value="1"></p>
    </div>';
                    }
                }

                echo $html;
            }
            //Save the comic audio
            public function toocheke_comic_audio_save_postdata($post_id)
            {

                if (isset($_POST['comic_audio_metabox_nonce']) && isset($_POST['comic'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['comic_audio_metabox_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['comic']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }

                if (! empty($_FILES['comic_audio']['name'])) {
                    $supported_types = ['audio/mpeg'];
                    $arr_file_type   = wp_check_filetype(basename($_FILES['comic_audio']['name']));
                    $uploaded_type   = $arr_file_type['type'];

                    if (in_array($uploaded_type, $supported_types)) {
                        $upload = wp_upload_bits($_FILES['comic_audio']['name'], null, file_get_contents($_FILES['comic_audio']['tmp_name']));
                        if (isset($upload['error']) && $upload['error'] != 0) {
                            wp_die('There was an error uploading your file. The error is: ' . $upload['error']);
                        } else {
                            add_post_meta($post_id, 'comic_audio', $upload);
                            update_post_meta($post_id, 'comic_audio', $upload);
                        }
                    } else {
                        wp_die("The file type that you've uploaded is not MP3.");
                    }
                }
                if (isset($_POST['remove_comic_audio'])) {
                    delete_post_meta($post_id, 'comic_audio');
                }
            }
            public function toocheke_update_edit_form()
            {
                echo ' enctype="multipart/form-data"';
            }
            /**
             * Parent series meta box field for comic
             */
            public function toocheke_add_comic_series_meta_box()
            {
                add_meta_box('comic-parent', 'Series', [$this, 'toocheke_display_series_meta_box'], 'comic', 'side', 'high');
            }

            public function toocheke_display_series_meta_box($post)
            {
                $post_type_object = get_post_type_object($post->post_type);
                $pages            = wp_dropdown_pages([
                    'post_type' => 'series',
                    'selected'                                   => $post->post_parent,
                    'name'       => 'parent_id',
                    'show_option_none'                                 => __('(No Series)', 'toocheke-companion'),
                    'sort_column' => 'menu_order, post_title',
                    'echo' => 0
                ]);
                if (! empty($pages)) {
                    echo $pages;
                }
            }
            /**
             * Series Hero Metabox
             */
            public function toocheke_series_hero_image_add_metabox()
            {
                add_meta_box('series-hero-metabox', __('Series Hero Image(Desktop)', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_hero_image_display_metabox'
                ], 'series', 'side', 'high');
            }

            public function toocheke_series_hero_image_display_metabox($post)
            {
                global $content_width, $_wp_additional_image_sizes;

                $image_id = get_post_meta($post->ID, 'series_hero_image_id', true);

                $old_content_width = $content_width;
                $content_width     = 254;

                if ($image_id && get_post($image_id)) {

                    if (! isset($_wp_additional_image_sizes['post-thumbnail'])) {
                        $thumbnail_html = wp_get_attachment_image($image_id, [$content_width, $content_width]);
                    } else {
                        $thumbnail_html = wp_get_attachment_image($image_id, 'post-thumbnail');
                    }

                    if (! empty($thumbnail_html)) {
                        $content = $thumbnail_html;
                        $content .= '<p class="hide-if-no-js"><a href="javascript:;" id="remove_series_hero_image_button">' . esc_html__('Remove
        series hero image', 'toocheke-companion') . '</a></p>';
                        $content .= '<input type="hidden" id="upload_series_hero_image" name="series_hero_image"
    value="' . esc_attr($image_id) . '" />';
                    }

                    $content_width = $old_content_width;
                } else {

                    $content = '<img src="" style="width:' . esc_attr($content_width) . 'px;height:auto;border:0;display:none;" />';
                    $content .= '<p class="hide-if-no-js"><a title="' . esc_attr__('Set series hero image', 'toocheke-companion') . '"
        href="javascript:;" id="upload_series_hero_image_button"
        data-uploader_title="' . esc_attr__('Choose an image', 'toocheke-companion') . '"
        data-uploader_button_text="' . esc_attr__('Set series hero image', 'toocheke-companion') . '">' .
                        esc_html__('Set series hero image', 'toocheke-companion') . '</a></p>';
                    $content .= '<input type="hidden" id="upload_series_hero_image" name="series_hero_image" value="" />';
                }

                echo $content;
            }
            public function toocheke_series_hero_image_save($post_id)
            {
                if (isset($_POST['series_hero_image'])) {
                    $image_id = (int) $_POST['series_hero_image'];
                    update_post_meta($post_id, 'series_hero_image_id', $image_id);
                }
            }
            /**
             * Series Mobile Hero Metabox
             */
            public function toocheke_series_mobile_hero_image_add_metabox()
            {
                add_meta_box('series-mobile-hero-metabox', __('Series Hero Image(Mobile)', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_mobile_hero_image_display_metabox'
                ], 'series', 'side', 'high', 90);
            }

            public function toocheke_series_mobile_hero_image_display_metabox($post)
            {
                global $content_width, $_wp_additional_image_sizes;

                $image_id = get_post_meta($post->ID, 'series_mobile_hero_image_id', true);

                $old_content_width = $content_width;
                $content_width     = 254;

                if ($image_id && get_post($image_id)) {

                    if (! isset($_wp_additional_image_sizes['post-thumbnail'])) {
                        $thumbnail_html = wp_get_attachment_image($image_id, [$content_width, $content_width]);
                    } else {
                        $thumbnail_html = wp_get_attachment_image($image_id, 'post-thumbnail');
                    }

                    if (! empty($thumbnail_html)) {
                        $content = $thumbnail_html;
                        $content .= '<p class="hide-if-no-js"><a href="javascript:;" id="remove_series_mobile_hero_image_button">' . esc_html__('Remove
    series hero image', 'toocheke-companion') . '</a></p>';
                        $content .= '<input type="hidden" id="upload_series_mobile_hero_image" name="series_mobile_hero_image"
value="' . esc_attr($image_id) . '" />';
                    }

                    $content_width = $old_content_width;
                } else {

                    $content = '<img src="" style="width:' . esc_attr($content_width) . 'px;height:auto;border:0;display:none;" />';
                    $content .= '<p class="hide-if-no-js"><a title="' . esc_attr__('Set series hero image', 'toocheke-companion') . '"
    href="javascript:;" id="upload_series_mobile_hero_image_button"
    data-uploader_title="' . esc_attr__('Choose an image', 'toocheke-companion') . '"
    data-uploader_button_text="' . esc_attr__('Set series hero image', 'toocheke-companion') . '">' .
                        esc_html__('Set series hero image', 'toocheke-companion') . '</a></p>';
                    $content .= '<input type="hidden" id="upload_series_mobile_hero_image" name="series_mobile_hero_image" value="" />';
                }

                echo $content;
            }
            public function toocheke_series_mobile_hero_image_save($post_id)
            {
                if (isset($_POST['series_mobile_hero_image'])) {
                    $image_id = (int) $_POST['series_mobile_hero_image'];
                    update_post_meta($post_id, 'series_mobile_hero_image_id', $image_id);
                }
            }

            /**
             * Series Background Image Metabox
             */
            public function toocheke_series_bg_image_add_metabox()
            {
                add_meta_box('series-bg-image-metabox', __('Series Background Image', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_bg_image_display_metabox'
                ], 'series', 'side', 'high');
            }

            public function toocheke_series_bg_image_display_metabox($post)
            {
                global $content_width, $_wp_additional_image_sizes;

                $image_id = get_post_meta($post->ID, 'series_bg_image_id', true);

                $old_content_width = $content_width;
                $content_width     = 254;

                if ($image_id && get_post($image_id)) {

                    if (! isset($_wp_additional_image_sizes['post-thumbnail'])) {
                        $thumbnail_html = wp_get_attachment_image($image_id, [$content_width, $content_width]);
                    } else {
                        $thumbnail_html = wp_get_attachment_image($image_id, 'post-thumbnail');
                    }

                    if (! empty($thumbnail_html)) {
                        $content = $thumbnail_html;
                        $content .= '<p class="hide-if-no-js"><a href="javascript:;" id="remove_series_bg_image_button">' . esc_html__('Remove
        series background image', 'toocheke-companion') . '</a></p>';
                        $content .= '<input type="hidden" id="upload_series_bg_image" name="series_bg_image"
    value="' . esc_attr($image_id) . '" />';
                    }

                    $content_width = $old_content_width;
                } else {

                    $content = '<img src="" style="width:' . esc_attr($content_width) . 'px;height:auto;border:0;display:none;" />';
                    $content .= '<p class="hide-if-no-js"><a title="' . esc_attr__('Set series background image', 'toocheke-companion') . '"
        href="javascript:;" id="upload_series_bg_image_button"
        data-uploader_title="' . esc_attr__('Choose an image', 'toocheke-companion') . '"
        data-uploader_button_text="' . esc_attr__('Set series background image', 'toocheke-companion') . '">' .
                        esc_html__('Set series background image', 'toocheke-companion') . '</a></p>';
                    $content .= '<input type="hidden" id="upload_series_bg_image" name="series_bg_image" value="" />';
                }

                echo $content;
            }
            public function toocheke_series_bg_image_save($post_id)
            {
                if (isset($_POST['series_bg_image'])) {
                    $image_id = (int) $_POST['series_bg_image'];
                    update_post_meta($post_id, 'series_bg_image_id', $image_id);
                }
            }
            /**
             * Series Background Color Metabox
             */

            public function toocheke_series_bg_color_add_metabox()
            {
                add_meta_box('series-bg-color-metabox', esc_html__('Series Background Color', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_bg_color_display_metabox'
                ], 'series', 'side', 'high');
            }
            public function toocheke_series_bg_color_display_metabox($post)
            {
                $custom          = get_post_custom($post->ID);
                $series_bg_color = (isset($custom['series_bg_color'][0])) ? $custom['series_bg_color'][0] : '';
                wp_nonce_field('toocheke_series_bg_color_meta_box', 'toocheke_series_bg_color_meta_box_nonce');
    ?>
        <script>
            jQuery(document).ready(function($) {
                $('.color_field').each(function() {
                    $(this).wpColorPicker();
                });
            });
        </script>
        <div class="pagebox">
            <p><?php esc_attr_e('Choose the background color for the series page.', 'toocheke-companion'); ?></p>
            <input class="color_field" type="hidden" name="series_bg_color" value="<?php echo esc_attr($series_bg_color); ?>" />
        </div>
    <?php
            }

            public function toocheke_series_bg_color_save($post_id)
            {
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }

                if (! isset($_POST['series_bg_color']) || ! wp_verify_nonce($_POST['toocheke_series_bg_color_meta_box_nonce'], 'toocheke_series_bg_color_meta_box')) {
                    return;
                }
                $series_bg_color = (isset($_POST['series_bg_color']) && $_POST['series_bg_color'] != '') ? $_POST['series_bg_color'] : '';
                update_post_meta($post_id, 'series_bg_color', $series_bg_color);
            }
            /**
             * Extra WYSIWYG meta box editor for series' sidebar content
             */
            //This function initializes the meta box.
            public function toocheke_series_sidebar_content_meta_box()
            {
                add_meta_box(
                    'series-sidebar-content',
                    __("Series Sidebar Content", 'toocheke-companion'),
                    [$this, 'toocheke_series_sidebar_content'],
                    'series',
                    "side",
                    "high"
                );
            }

            //Displaying the meta box
            public function toocheke_series_sidebar_content($post)
            {
                echo "<h4 style='color: #2271b1;'>Override the global sidebar here.</h4>";
                $content = get_post_meta($post->ID, 'series_sidebar_content', true);

                //This function adds the WYSIWYG Editor
                wp_editor(
                    $content,
                    'series_sidebar_content',
                    ["media_buttons" => true, 'wpautop' => true]
                );
            }

            //This function saves the data you put in the meta box
            public function toocheke_series_sidebar_content_save_postdata($post_id)
            {

                if (isset($_POST['series_sidebar_content_nonce']) && isset($_POST['series'])) {

                    //Not save if the user hasn't submitted changes
                    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                        return;
                    }

                    // Verifying whether input is coming from the proper form
                    if (! wp_verify_nonce($_POST['series_sidebar_content_nonce'])) {
                        return;
                    }

                    // Making sure the user has permission
                    if ('post' == $_POST['series']) {
                        if (! current_user_can('edit_post', $post_id)) {
                            return;
                        }
                    }
                }
                if (isset($_POST['series_sidebar_content'])) {
                    $data = $_POST['series_sidebar_content'];
                    update_post_meta($post_id, 'series_sidebar_content', $data);
                }
            }
            /*Replace description text field with WYSIWYG*/
            public function toocheke_description_wysiwyg($term, $taxonomy)
            {
    ?>
        <tr valign="top">
            <th scope="row">Description</th>
            <td>
                <?php wp_editor(html_entity_decode($term->description), 'description', ['media_buttons' => false]); ?>
                <script>
                    jQuery(window).ready(function() {
                        jQuery('label[for=description]').parent().parent().remove();
                    });
                </script>
            </td>
        </tr>
    <?php
            }
            public function toocheke_replace_term_description_field()
            {
                $taxonomies = get_taxonomies(['public' => true]);
                foreach ($taxonomies as $taxonomy) {
                    //add_action( $taxonomy . '_add_form_fields', array($this, 'toocheke_wysiwyg_term_description'), 10, 2 );
                    add_action($taxonomy . '_edit_form_fields', [$this, 'toocheke_wysiwyg_term_description'], 10, 2);
                }
            }
            public function toocheke_wysiwyg_term_description($term, $taxonomy)
            {
                // Remove the default description field and replace it with wp_editor
    ?>
        <tr class="form-field">
            <th valign="top" scope="row">
                <label for="wysiwyg-description"><?php _e('Description', 'toocheke-companion'); ?></label>
            </th>
            <td>
                <script type="text/javascript">
                    var description = document.getElementById("description");
                    if (description !== undefined && description.type == "textarea") {
                        description.parentElement.parentElement.remove();
                    } else {
                        var description = document.getElementById("tag-description");
                        if (description !== undefined && description.type == "textarea") {
                            description.parentElement.remove();
                        }
                    }
                </script>
                <?php wp_editor(htmlspecialchars_decode($term->description), "wysiwyg-description", ['textarea_name' => 'description']); ?>
            </td>
        </tr>
    <?php
            }

            /* Character specific functions and terms */

            /* Image Functions */
            public function toocheke_companion_character_load_media()
            {
                if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'comic_characters') {
                    return;
                }
                wp_enqueue_media();
            }

            /**
             * Add a form field in the new category page
             * @since 1.0.0
             */

            public function toocheke_companion_add_character_image($taxonomy)
            { ?>
        <div class="form-field term-character">
            <label for="character-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            <input type="hidden" id="character-image-id" name="character-image-id" class="custom_media_url" value="">
            <div id="character-image-wrapper"></div>
            <p>
                <input type="button" class="button button-secondary characters_tax_media_button"
                    id="characters_tax_media_button" name="characters_tax_media_button"
                    value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                <input type="button" class="button button-secondary characters_tax_media_remove"
                    id="characters_tax_media_remove" name="characters_tax_media_remove"
                    value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
            </p>
            <p>This is the featured image for the character.</p>
        </div>
    <?php }

            /**
             * Save the form field
             * @since 1.0.0
             */
            public function toocheke_companion_save_character_image($term_id, $tt_id)
            {
                if (isset($_POST['character-image-id']) && '' !== $_POST['character-image-id']) {
                    add_term_meta($term_id, 'character-image-id', absint(sanitize_title($_POST['character-image-id'])), true);
                }
            }

            /**
             * Edit the form field
             * @since 1.0.0
             */
            public function toocheke_companion_update_character_image($term, $taxonomy)
            { ?>
        <tr class="form-field term-character-wrap">
            <th scope="row">
                <label for="character-image-id"><?php _e('Image', 'toocheke-companion'); ?></label>
            </th>
            <td>
                <?php $image_id = get_term_meta($term->term_id, 'character-image-id', true); ?>
                <input type="hidden" id="character-image-id" name="character-image-id"
                    value="<?php echo esc_attr($image_id); ?>">
                <div id="character-image-wrapper">
                    <?php if ($image_id) { ?>
                        <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                    <?php } ?>
                </div>
                <p>
                    <input type="button" class="button button-secondary characters_tax_media_button"
                        id="characters_tax_media_button" name="characters_tax_media_button"
                        value="<?php _e('Add Image', 'toocheke-companion'); ?>" />
                    <input type="button" class="button button-secondary characters_tax_media_remove"
                        id="characters_tax_media_remove" name="characters_tax_media_remove"
                        value="<?php _e('Remove Image', 'toocheke-companion'); ?>" />
                </p>
                <p>This is the featured image for the character.</p>
            </td>
        </tr>
    <?php }

            /**
             * Update the form field value
             * @since 1.0.0
             */
            public function toocheke_companion_updated_character_image($term_id, $tt_id)
            {
                if (isset($_POST['character-image-id']) && '' !== $_POST['character-image-id']) {
                    update_term_meta($term_id, 'character-image-id', absint(sanitize_title($_POST['character-image-id'])));
                } else {
                    update_term_meta($term_id, 'character-image-id', '');
                }
            }

            /**
             * Enqueue styles and scripts
             * @since 1.0.0
             */
            public function toocheke_companion_character_add_script()
            {
                if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != 'comic_characters') {
                    return;
                } ?>
        <script>
            jQuery(document).ready(function($) {

                _wpMediaViewsL10n.insertIntoPost = '<?php _e("Insert", 'toocheke-companion'); ?>';

                function ct_media_upload(button_class) {
                    var _custom_media = true,
                        _orig_send_attachment = wp.media.editor.send.attachment;
                    $('body').on('click', button_class, function(e) {
                        var button_id = '#' + $(this).attr('id');
                        var send_attachment_bkp = wp.media.editor.send.attachment;
                        var button = $(button_id);
                        _custom_media = true;

                        wp.media.editor.send.attachment = function(props, attachment) {
                            if (_custom_media) {
                                $('#character-image-id').val(attachment.id);
                                $('#character-image-wrapper').html(
                                    '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                                );
                                $('#character-image-wrapper .custom_media_image').attr('src', attachment.url)
                                    .css('display', 'block');
                            } else {
                                return _orig_send_attachment.apply(button_id, [props, attachment]);
                            }
                        }
                        wp.media.editor.open(button);
                        return false;
                    });
                }
                ct_media_upload('.characters_tax_media_button.button');
                $('body').on('click', '.characters_tax_media_remove', function() {
                    $('#character-image-id').val('');
                    $('#character-image-wrapper').html(
                        '<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />'
                    );
                });
                // Thanks: http://stackoverflow.com/questions/15281995/wordpress-create-category-ajax-response
                $(document).ajaxComplete(function(event, xhr, settings) {
                    var queryStringArr = settings.data.split('&');

                    if ($.inArray('action=add-tag', queryStringArr) !== -1) {
                        var xml = xhr.responseXML;
                        $response = $(xml).find('term_id').text();
                        if ($response != "") {
                            // Clear the thumb image
                            $('#character-image-wrapper').html('');
                            $('#character-image-id').val('');
                        }
                    }
                });
            });
        </script>
    <?php
            }
            /* Comic Character specific functions and terms */
            /*Add new Term*/
            public function toocheke_companion_character_add_order_field($taxonomy)
            {
    ?><div class="form-field term-character">
            <label for="character-order"><?php _e('Order', 'toocheke-companion'); ?></label>
            <input type="number" min="1" name="character-order" id="character-order" value="1" class="character-order-field"
                aria-required="true" required />
            <p>This determines what order the character is in.</p>

        </div><?php
            }
            /*Save new Term*/
            public function toocheke_companion_character_save_order_meta($term_id, $tt_id)
            {
                if (isset($_POST['character-order']) && ! empty($_POST['character-order'])) {
                    $order = absint(sanitize_title($_POST['character-order']));
                    add_term_meta($term_id, 'character-order', $order, true);
                } else {
                    add_term_meta($term_id, 'character-order', 1, true);
                }
            }
            /*Updating Term*/
            public function toocheke_companion_character_edit_order_field($term, $taxonomy)
            {

                // get current order
                $current_order = get_term_meta($term->term_id, 'character-order', true);

                ?><tr class="form-field term-order-wrap">
            <th scope="row"><label for="character-order"><?php _e('Order', 'toocheke-companion'); ?></label></th>
            <td>
                <input type="number" min="1" name="character-order" id="character-order"
                    value="<?php echo esc_attr($current_order); ?>" class="character-order-field" aria-required="true" required />
                <p>This determines what order the character is in.</p>
            </td>
        </tr><?php
            }
            /*Save Data*/
            public function toocheke_companion_character_update_order_meta($term_id, $tt_id)
            {

                if (isset($_POST['character-order']) && ! empty($_POST['character-order'])) {
                    $order = absint(sanitize_title($_POST['character-order']));
                    update_term_meta($term_id, 'character-order', $order);
                }
            }
            /*
     * Displaying the order column
     */
            public function toocheke_companion_character_add_order_column($columns)
            {
                $columns['character_order'] = __('Order', 'toocheke-companion');
                return $columns;
            }
            /* Add content into column */
            public function toocheke_companion_add_character_order_column_content($content, $column_name, $term_id)
            {

                if ($column_name !== 'character_order') {
                    return $content;
                }

                $term_id = absint($term_id);
                $order   = get_term_meta($term_id, 'character-order', true);

                if (! empty($order)) {
                    $content .= esc_attr($order);
                }

                return $content;
            }

            /* Make column sortable */
            public function toocheke_companion_character_add_order_column_sortable($sortable)
            {
                $sortable['character_order'] = 'character_order';
                return $sortable;
            }

            /* Sort columns */
            public function toocheke_companion_character_sort_by_character_order($term_query)
            {
                global $pagenow;
                if (! is_admin()) {
                    return $term_query;
                }
                // WP_Term_Query does not define a get() or a set() method so the query_vars member must
                // be manipulated directly
                if (is_admin() && $pagenow == 'edit-tags.php' && $term_query->query_vars['taxonomy'][0] == 'comic_characters' && (! isset($_GET['orderby']) || $_GET['orderby'] == 'character_order')) {
                    // set orderby to the named clause in the meta_query
                    $term_query->query_vars['orderby'] = 'order_clause';
                    $term_query->query_vars['order']   = isset($_GET['order']) ? sanitize_title($_GET['order']) : "DESC";
                    // the OR relation and the NOT EXISTS clause allow for terms without a meta_value at all
                    $args = [
                        'relation' => 'OR',
                        'order_clause'      => [
                            'key'  => 'character-order',
                            'type' => 'NUMERIC',
                        ],
                        [
                            'key'     => 'character-order',
                            'compare' => 'NOT EXISTS',
                        ],
                    ];
                    $term_query->meta_query = new WP_Meta_Query($args);
                }
                return $term_query;
            }

            /* Displaying image meta data in column */
            public function toocheke_companion_character_add_image_column($columns)
            {
                $columns['character_image'] = __('Image', 'toocheke-companion');
                return $columns;
            }
            /* Add content into column */
            public function toocheke_companion_add_character_image_column_content($content, $column_name, $term_id)
            {

                if ($column_name !== 'character_image') {
                    return $content;
                }

                $term_id  = absint($term_id);
                $thumb_id = get_term_meta($term_id, 'character-image-id', true);

                if (! empty($thumb_id)) {
                    $term_img = wp_get_attachment_url($thumb_id);
                    $content .= "<img src=\"$term_img\" width=\"100%\" height=\"auto\"/>";
                }

                return $content;
            }

            /* Make column sortable */

            public function toocheke_companion_character_add_image_column_sortable($sortable)
            {
                $sortable['character_image'] = 'character_image';
                return $sortable;
            }

            //Characters page
            public function toocheke_companion_create_character_page_on_theme_activation()
            {

                // Set the title, template, etc
                $new_page_title    = __('Characters', 'toocheke-companion'); // Page's title
                $new_page_content  = '';                                     // Content goes here
                $new_page_template = 'page-templates/comic-characters.php';  // The template to use for the page
                $page_check_query  = new WP_Query(
                    [
                        'post_type'              => 'page',
                        'title'                  => $new_page_title,
                        'post_status'            => 'all',
                        'posts_per_page'         => 1,
                        'no_found_rows'          => true,
                        'ignore_sticky_posts'    => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                        'orderby'                => 'post_date ID',
                        'order'                  => 'ASC',
                    ]
                );

                if (! empty($page_check_query->post)) {
                    $page_check = $page_check_query->post;
                } else {
                    $page_check = null;
                }

                //$page_check = get_page_by_title($new_page_title); // Check if the page already exists --Deprecated in Wordpress 6.2
                // Store the above data in an array
                $new_page = [
                    'post_type'    => 'page',
                    'post_title'   => $new_page_title,
                    'post_content' => $new_page_content,
                    'post_status'  => 'publish',
                    'post_author'  => 1,
                    'post_slug'    => 'characters',
                ];
                // If the page doesn't already exist, create it
                if (! isset($page_check->ID)) {
                    $new_page_id = wp_insert_post($new_page);
                    if (! empty($new_page_template)) {
                        update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
                    }
                }
            }

            /**
             * Move Featured Image Metabox on 'comic' post type
             */
            public function toocheke_move_comic_featured_image_metabox()
            {
                remove_meta_box('postimagediv', 'comic', 'side');
                add_meta_box('postimagediv', __('Comic Thumbnail', 'toocheke-companion'), 'post_thumbnail_meta_box', 'comic', 'side', 'high');
            }
            /**
             * Move Featured Image Metabox on 'series' post type
             */
            public function toocheke_move_series_featured_image_metabox()
            {
                remove_meta_box('postimagediv', 'series', 'side');
                add_meta_box('postimagediv', __('Series Thumbnail', 'toocheke-companion'), 'post_thumbnail_meta_box', 'series', 'side', 'high');
            }
            /**
             * Move Featured Image and publish metaboxes in manga CPT's
             */
            public function toocheke_manga_reorder_metaboxes()
            {
                // Only apply this code for the custom post types 'manga_series', 'manga_volume', 'manga_chapter'
                $post_types = ['manga_series', 'manga_volume', 'manga_chapter'];

                // Check if we're on the desired post type screen
                if (in_array(get_post_type(), $post_types)) {
                    // Remove default Featured Image and Publish Metaboxes
                    remove_meta_box('postimagediv', get_post_type(), 'side'); // Featured Image
                    remove_meta_box('submitdiv', get_post_type(), 'side');    // Publish
                    $thumbnail_label = '';
                    switch (get_post_type()) {
                        case "manga_series":
                            $thumbnail_label = 'Series Thumbnail';
                            break;
                        case "manga_volume":
                            $thumbnail_label = 'Volume Thumbnail';
                            break;
                        case "manga_chapter":
                            $thumbnail_label = 'Chapter Thumbnail';
                            break;
                        default:
                            break;
                    }

                    // Add them back in the desired order
                    add_meta_box('postimagediv', $thumbnail_label, 'post_thumbnail_meta_box', get_post_type(), 'side', 'high'); // Featured Image (Highest)
                    add_meta_box('submitdiv', __('Publish'), 'post_submit_meta_box', get_post_type(), 'side', 'high');          // Publish (Second Highest)
                }
            }
            /**
             * Manga metaboxes
             */
            public function toocheke_add_manga_hero_metaboxes()
            {
                $cpts = ['manga_series', 'manga_volume'];

                foreach ($cpts as $cpt) {
                    add_meta_box(
                        "{$cpt}-hero-metabox",
                        __('Hero Image', 'toocheke-companion'),
                        [$this, 'toocheke_render_manga_hero_metabox'],
                        $cpt,
                        'side',
                        'core',
                        ['meta_key' => "{$cpt}_hero_image_id"]
                    );
                }
            }
            public function toocheke_render_manga_hero_metabox($post, $metabox)
            {
                global $content_width, $_wp_additional_image_sizes;

                $meta_key      = $metabox['args']['meta_key'];
                $image_id      = get_post_meta($post->ID, $meta_key, true);
                $old_width     = $content_width;
                $content_width = 254;

                echo '<div class="hero-image-metabox" id="' . esc_attr($meta_key) . '_wrapper">';

                if ($image_id && get_post($image_id)) {
                    $thumbnail_html = wp_get_attachment_image($image_id, [$content_width, $content_width]);
                    echo $thumbnail_html;
                    echo '<p class="hide-if-no-js"><a href="#" class="upload-hero-image button" data-field="' . esc_attr($meta_key) . '">' . __('Change image', 'toocheke-companion') . '</a></p>';
                    echo '<p class="hide-if-no-js"><a href="#" class="remove-hero-image">' . __('Remove image', 'toocheke-companion') . '</a></p>';
                    echo '<input type="hidden" name="' . esc_attr($meta_key) . '" value="' . esc_attr($image_id) . '">';
                } else {
                    echo '<img src="" style="width:' . esc_attr($content_width) . 'px;height:auto;display:none;" />';
                    echo '<p class="hide-if-no-js"><a href="#" class="upload-hero-image button" data-field="' . esc_attr($meta_key) . '">' . __('Set hero image', 'toocheke-companion') . '</a></p>';
                    echo '<input type="hidden" name="' . esc_attr($meta_key) . '" value="">';
                }

                echo '</div>';

                $content_width = $old_width;
            }
            public function toocheke_save_manga_hero_images($post_id)
            {
                $cpts = ['manga_series', 'manga_volume', 'manga_chapter'];
                foreach ($cpts as $cpt) {
                    $meta_key = "{$cpt}_hero_image_id";
                    if (isset($_POST[$meta_key])) {
                        update_post_meta($post_id, $meta_key, (int) $_POST[$meta_key]);
                    }
                }
            }

            public function toocheke_manga_series_meta_boxes()
            {
                add_meta_box(
                    'manga_series_details',
                    __('Series Details', 'toocheke-companion'),
                    [$this, 'toocheke_manga_series_details_display'],
                    'manga_series',
                    'normal',
                    'default'
                );
            }

            //Displaying the meta box
            public function toocheke_manga_series_details_display($post)
            { ?>

        <?php
                // Nonce field
                wp_nonce_field('manga_series_save_meta', 'manga_series_nonce');
                $creator      = get_post_meta($post->ID, 'manga_creator', true);
                $status       = get_post_meta($post->ID, 'manga_status', true);
                $release_year = get_post_meta($post->ID, 'manga_release_year', true);
                $rating       = get_post_meta($post->ID, 'manga_rating', true);

        ?>
        <p>
            <label><?php _e('Creator', 'toocheke-companion'); ?>:</label>
            <input type="text" name="manga_creator" value="<?php echo esc_attr($creator); ?>" style="width:100%" />
        </p>

        <p>
            <label><?php _e('Status', 'toocheke-companion'); ?>:</label>
            <select name="manga_status">
                <option value="Ongoing" <?php selected($status, 'Ongoing'); ?>>Ongoing</option>
                <option value="Completed" <?php selected($status, 'Completed'); ?>>Completed</option>
                <option value="Hiatus" <?php selected($status, 'Hiatus'); ?>>Hiatus</option>
            </select>
        </p>
        <p>
            <label><?php _e('Release Year', 'toocheke-companion'); ?>:</label>
            <input type="number" name="manga_release_year" value="<?php echo esc_attr($release_year); ?>" />
        </p>
        <p>
            <label>Rating:</label>
            <input type="text" name="manga_rating" value="<?php echo esc_attr($rating); ?>" />
        </p>
    <?php }

            //This function saves the data you put in the meta box
            public function toocheke_manga_series_save_postdata($post_id)
            {
                // Check nonce
                if (! isset($_POST['manga_series_nonce'])) {
                    return;
                }

                if (! wp_verify_nonce($_POST['manga_series_nonce'], 'manga_series_save_meta')) {
                    return;
                }

                //Not save if the user hasn't submitted changes
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }

                // Making sure the user has permission
                if ('post' == $_POST['manga_series']) {
                    if (! current_user_can('edit_post', $post_id)) {
                        return;
                    }
                }

                /* Get the posted data and sanitize it for use as an HTML class. */
                if (isset($_POST['manga_creator'])) {
                    update_post_meta($post_id, 'manga_creator', sanitize_text_field($_POST['manga_creator']));
                }

                if (isset($_POST['manga_status'])) {
                    update_post_meta($post_id, 'manga_status', sanitize_text_field($_POST['manga_status']));
                }
                if (isset($_POST['manga_release_year'])) {
                    update_post_meta($post_id, 'manga_release_year', intval($_POST['manga_release_year']));
                }
                if (isset($_POST['manga_rating'])) {
                    update_post_meta($post_id, 'manga_rating', sanitize_textarea_field($_POST['manga_rating']));
                }
            }
            public function toocheke_manga_volume_meta_boxes()
            {
                add_meta_box(
                    'manga_volume_details',
                    __('Volume Details', 'toocheke-companion'),
                    [$this, 'toocheke_manga_volume_details_display'],
                    'manga_volume',
                    'normal',
                    'default'
                );
            }
            //Displaying the meta box
            public function toocheke_manga_volume_details_display($post)
            {
                // Nonce field
                wp_nonce_field('manga_volume_save_meta', 'manga_volume_nonce');

                $series_id       = get_post_meta($post->ID, 'series_id', true);
                $volume_number   = get_post_meta($post->ID, 'volume_number', true);
                $release_date    = get_post_meta($post->ID, 'release_date', true);
                $isbn            = get_post_meta($post->ID, 'isbn', true);
                $rating          = get_post_meta($post->ID, 'rating', true);
                $pages           = get_post_meta($post->ID, 'pages', true);
                $buy_digital_url = get_post_meta($post->ID, 'buy_digital_url', true);
                $buy_print_url   = get_post_meta($post->ID, 'buy_print_url', true);

                // Get all Manga Series
                $series_posts = get_posts([
                    'post_type'      => 'manga_series',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);
    ?>
        <p>
            <label>Series:</label>
            <select name="series_id">
                <option value="">-- Select Series --</option>
                <?php foreach ($series_posts as $series): ?>
                    <option value="<?php echo esc_attr($series->ID); ?>" <?php selected($series_id, $series->ID); ?>>
                        <?php echo esc_html($series->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Volume Number:</label>
            <input type="number" name="volume_number" value="<?php echo esc_attr($volume_number); ?>" />
        </p>
        <p>
            <label>Release Date:</label>
            <input type="date" name="release_date" value="<?php echo esc_attr($release_date); ?>" />
        </p>
        <p>
            <label>ISBN:</label>
            <input type="text" name="isbn" value="<?php echo esc_attr($isbn); ?>" />
        </p>
        <p>
            <label>Rating:</label>
            <input type="text" name="rating" value="<?php echo esc_attr($rating); ?>" />
        </p>
        <p>
            <label>Pages:</label>
            <input type="number" name="pages" value="<?php echo esc_attr($pages); ?>" />
        </p>
        <p>
            <label>Buy Digital URL:</label>
            <input type="url" name="buy_digital_url" value="<?php echo esc_attr($buy_digital_url); ?>" />
        </p>
        <p>
            <label>Buy Print URL:</label>
            <input type="url" name="buy_print_url" value="<?php echo esc_attr($buy_print_url); ?>" />
        </p>
    <?php
            }

            //This function saves the data you put in the meta box
            public function toocheke_manga_volume_save_postdata($post_id)
            {
                // Check nonce
                if (! isset($_POST['manga_volume_nonce'])) {
                    return;
                }

                if (! wp_verify_nonce($_POST['manga_volume_nonce'], 'manga_volume_save_meta')) {
                    return;
                }

                //Not save if the user hasn't submitted changes
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }

                // Making sure the user has permission
                if ('post' == $_POST['manga_volume']) {
                    if (! current_user_can('edit_post', $post_id)) {
                        return;
                    }
                }

                /* Get the posted data and sanitize it for use as an HTML class. */
                if (isset($_POST['series_id'])) {
                    update_post_meta($post_id, 'series_id', intval($_POST['series_id']));
                }

                if (isset($_POST['volume_number'])) {
                    update_post_meta($post_id, 'volume_number', intval($_POST['volume_number']));
                }

                if (isset($_POST['release_date'])) {
                    update_post_meta($post_id, 'release_date', sanitize_text_field($_POST['release_date']));
                }

                if (isset($_POST['isbn'])) {
                    update_post_meta($post_id, 'isbn', sanitize_text_field($_POST['isbn']));
                }

                if (isset($_POST['rating'])) {
                    update_post_meta($post_id, 'rating', sanitize_textarea_field($_POST['rating']));
                }
                if (isset($_POST['pages'])) {
                    update_post_meta($post_id, 'pages', sanitize_textarea_field($_POST['pages']));
                }
                if (isset($_POST['buy_digital_url'])) {
                    update_post_meta($post_id, 'buy_digital_url', sanitize_textarea_field($_POST['buy_digital_url']));
                }
                if (isset($_POST['buy_print_url'])) {
                    update_post_meta($post_id, 'buy_print_url', sanitize_textarea_field($_POST['buy_print_url']));
                }
            }

            public function toocheke_manga_chapter_meta_boxes()
            {
                add_meta_box(
                    'manga_chapter_pages',
                    __('Chapter Pages', 'toocheke-companion'),
                    [$this, 'toocheke_manga_chapter_pages_display'],
                    'manga_chapter', // ✅ only for manga_chapter CPT
                    'normal',
                    'default'
                );
                add_meta_box(
                    'manga_chapter_details',
                    __('Chapter Details', 'toocheke-companion'),
                    [$this, 'toocheke_manga_chapter_details_display'],
                    'manga_chapter',
                    'normal',
                    'default'
                );
            }
            //Displaying the meta box
            public function toocheke_manga_chapter_pages_display($post)
            {
                // Get saved images
                $page_ids = get_post_meta($post->ID, 'manga_chapter_pages', true);
                // Normalize to array
                if (! is_array($page_ids)) {
                    $page_ids = $page_ids ? array_filter(explode(',', $page_ids)) : [];
                }

    ?>
        <ul class="manga-chapter-gallery chama-gallery-input">
            <?php foreach ($page_ids as $i => $id):
                    $url = wp_get_attachment_image_url($id, [140, 140]);
                    if ($url): ?>
                    <li data-id="<?php echo esc_attr($id); ?>">
                        <span class="manga-chapter-page" style="background-image:url('<?php echo esc_url($url); ?>')"></span>
                        <a class="button manga-chapter-gallery-remove" href="#"
                            data-hidden="manga_chapter_pages"
                            data-id="<?php echo esc_attr($id); ?>">
                            <?php _e('Remove', 'toocheke-companion'); ?>
                        </a>
                    </li>
            <?php endif;
                endforeach; ?>
        </ul>

        <input type="hidden" id="manga_chapter_pages"
            name="manga_chapter_pages"
            value="<?php echo esc_attr(join(',', $page_ids)); ?>" />

        <a href="#" class="button manga-page-upload-button">
            <?php _e('Add Images', 'toocheke-companion'); ?>
        </a>
    <?php
            }
            public function toocheke_manga_chapter_details_display($post)
            {
                // Nonce field
                wp_nonce_field('manga_chapter_save_meta', 'manga_chapter_nonce');

                $series_id      = get_post_meta($post->ID, 'series_id', true);
                $volume_id      = get_post_meta($post->ID, 'volume_id', true);
                $chapter_number = get_post_meta($post->ID, 'chapter_number', true);
                $release_date   = get_post_meta($post->ID, 'release_date', true);
                $notes          = get_post_meta($post->ID, 'notes', true);
                $pages          = get_post_meta($post->ID, 'pages', true);

                // Get all Manga Series
                $series_posts = get_posts([
                    'post_type'      => 'manga_series',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);

                // Get all Volumes
                $volume_posts = get_posts([
                    'post_type'      => 'manga_volume',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);
    ?>
        <p>
            <label>Series:</label>
            <select name="series_id">
                <option value="">-- Select Series --</option>
                <?php foreach ($series_posts as $series): ?>
                    <option value="<?php echo esc_attr($series->ID); ?>" <?php selected($series_id, $series->ID); ?>>
                        <?php echo esc_html($series->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Volume:</label>
            <select name="volume_id">
                <option value="">-- Select Volume --</option>
                <?php foreach ($volume_posts as $volume): ?>
                    <option value="<?php echo esc_attr($volume->ID); ?>" <?php selected($volume_id, $volume->ID); ?>>
                        <?php echo esc_html($volume->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Chapter Number:</label>
            <input type="number" name="chapter_number" value="<?php echo esc_attr($chapter_number); ?>" />
        </p>
        <p>
            <label>Release Date:</label>
            <input type="date" name="release_date" value="<?php echo esc_attr($release_date); ?>" />
        </p>
        <p>
            <label>Pages:</label>
            <input type="number" name="pages" value="<?php echo esc_attr($pages); ?>" />
        </p>
        <p>
            <label>Notes:</label>
            <textarea name="notes" style="width:100%"><?php echo esc_textarea($notes); ?></textarea>
        </p>

        <?php
            }

            //This function saves the data you put in the meta box
            public function toocheke_manga_chapter_save_postdata($post_id)
            {
                // Check nonce
                if (! isset($_POST['manga_chapter_nonce'])) {
                    return;
                }

                if (! wp_verify_nonce($_POST['manga_chapter_nonce'], 'manga_chapter_save_meta')) {
                    return;
                }

                //Not save if the user hasn't submitted changes
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }

                // Making sure the user has permission
                if ('post' == $_POST['manga_chapter']) {
                    if (! current_user_can('edit_post', $post_id)) {
                        return;
                    }
                }

                /* Get the posted data and sanitize it for use as an HTML class. */
                if (isset($_POST['series_id'])) {
                    update_post_meta($post_id, 'series_id', intval($_POST['series_id']));
                }

                if (isset($_POST['volume_id'])) {
                    update_post_meta($post_id, 'volume_id', intval($_POST['volume_id']));
                }

                if (isset($_POST['chapter_number'])) {
                    update_post_meta($post_id, 'chapter_number', intval($_POST['chapter_number']));
                }

                if (isset($_POST['release_date'])) {
                    update_post_meta($post_id, 'release_date', sanitize_text_field($_POST['release_date']));
                }

                if (isset($_POST['pages'])) {
                    update_post_meta($post_id, 'pages', sanitize_textarea_field($_POST['pages']));
                }

                if (isset($_POST['notes'])) {
                    update_post_meta($post_id, 'notes', sanitize_textarea_field($_POST['notes']));
                }

                if (isset($_POST['manga_chapter_pages'])) {
                    $images = $_POST['manga_chapter_pages'];

                    // Normalize into array
                    if (! is_array($images)) {
                        // Convert comma-separated string to array
                        $images = explode(',', $images);
                    }

                    // Clean the array
                    $images = array_filter(array_map('intval', $images));

                    // Save as array (WP will serialize automatically)
                    update_post_meta($post_id, 'manga_chapter_pages', $images);
                }
            }
            public function toocheke_manga_filters($query)
            {
                if (! is_admin() || ! $query->is_main_query()) {
                    return;
                }

                $post_type = $query->get('post_type');
                if (! in_array($post_type, ['manga_volume', 'manga_chapter'])) {
                    return;
                }

                $meta_query = $query->get('meta_query') ?: [];

                // Filter by series_id (works for both)
                if (! empty($_GET['series_id'])) {
                    $meta_query[] = [
                        'key'     => 'series_id',
                        'value'   => intval($_GET['series_id']),
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ];
                }

                // Filter by volume_id (only manga_chapter)
                if ($post_type === 'manga_chapter' && ! empty($_GET['volume_id'])) {
                    $meta_query[] = [
                        'key'     => 'volume_id',
                        'value'   => intval($_GET['volume_id']),
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ];
                }

                if (! empty($meta_query)) {
                    $query->set('meta_query', $meta_query);
                }
            }
            /* Enqueue Styles and scripts*/
            public function toocheke_admin_styles_and_scripts()
            {
                wp_register_style('toocheke-companion-dashicons', plugins_url('toocheke-companion' . '/css/toocheke.css'), array(), TOOCHEKE_COMPANION_VERSION);
                wp_enqueue_style('toocheke-companion-dashicons');

                //enqueue wordpress js media library.
                wp_enqueue_media();
                wp_enqueue_script('toocheke-media-library-script', plugins_url('toocheke-companion' . '/js/media.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                wp_enqueue_script('toocheke-media-library-script');

                $screen = get_current_screen();
                // Check we're only on the edit-tags page in the plugin
                if ('edit-tags' === $screen->base && ('series' === $screen->post_type || 'comic' === $screen->post_type)) {
                    wp_enqueue_script('toocheke-tags-script', plugins_url('toocheke-companion' . '/js/handle-tags-menu.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                    wp_enqueue_script('toocheke-tags-script');
                }

                if ('edit' === $screen->base && 'comic' === $screen->post_type) {
                    wp_enqueue_script('toocheke-populate-script', plugins_url('toocheke-companion' . '/js/populate.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                    wp_enqueue_script('toocheke-populate-script');
                }
                //color picker
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
            }
            public function toocheke_frontend_styles_and_scripts()
            {
                //enqueue keyboard nav js.
                $disable_keyboard = get_option('toocheke-keyboard') && 1 == get_option('toocheke-keyboard');
                if (! $disable_keyboard):
                    wp_enqueue_script('toocheke-keyboard-script', plugins_url('toocheke-companion' . '/js/keyboard.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                    wp_enqueue_script('toocheke-keyboard-script');
                endif;
                //bookmark
                wp_enqueue_script('toocheke-bookmark-script', plugins_url('toocheke-companion' . '/js/bookmark.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                wp_enqueue_script('toocheke-bookmark-script');
                //likes
                wp_enqueue_script('toocheke-likes', plugins_url('toocheke-companion' . '/js/likes.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                wp_localize_script('toocheke-likes', 'toochekeLikes', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'like'    => __('Like', 'toocheke-companion'),
                    'unlike'  => __('Unlike', 'toocheke-companion'),
                ]);
                wp_register_style('toocheke-companion-likes', plugins_url('toocheke-companion' . '/css/toocheke-likes.css'));
                wp_enqueue_style('toocheke-companion-likes');

                //optional Font-awesome
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    wp_register_style('toocheke-font-awesome', plugins_url('toocheke-companion/fonts/font-awesome/css/all.min.css'));
                    wp_enqueue_style('toocheke-font-awesome');
                    wp_register_style('toocheke-universal-styles', plugins_url('toocheke-companion/css/universal.css'), [], TOOCHEKE_COMPANION_VERSION);
                    wp_enqueue_style('toocheke-universal-styles');
                    wp_register_style('toocheke-manga-styles', plugins_url('toocheke-companion/css/manga.css'), [], TOOCHEKE_COMPANION_VERSION);
                    wp_enqueue_style('toocheke-manga-styles');
                }
            }
            /**
             * Conditionally enqueue Swiper & Fullscreen libraries
             */
            public function toocheke_enqueue_reader_libraries()
            {
                global $post;

                if (! is_singular()) {
                    return;
                }

                if (! $post instanceof WP_Post) {
                    return;
                }

                $post_type = get_post_type($post);

                // Check if it's manga_volume with ?reader param
                if ($post_type === 'manga_volume' && isset($_GET['reader'])) {
                    $this->toocheke_enqueue_swiper_and_fullscreen();
                }

                // Always enqueue for manga_chapter
                if ($post_type === 'manga_chapter') {
                    $this->toocheke_enqueue_swiper_and_fullscreen();
                }
            }
            /**
             * Helper function to enqueue Swiper & Fullscreen
             */
            private function toocheke_enqueue_swiper_and_fullscreen()
            {
                $plugin_url = plugin_dir_url(__FILE__);

                // Swiper CSS
                wp_enqueue_style(
                    'toocheke-swiper',
                    $plugin_url . 'assets/swiper/swiper-bundle.min.css',
                    [],
                    TOOCHEKE_COMPANION_VERSION
                );

                // Swiper JS
                wp_enqueue_script(
                    'toocheke-swiper',
                    $plugin_url . 'assets/swiper/swiper-element-bundle.min.js',
                    [],
                    TOOCHEKE_COMPANION_VERSION,
                    true
                );

                // Fullscreen JS
                wp_enqueue_script(
                    'toocheke-fullscreen',
                    $plugin_url . 'assets/fullscreen/jquery.fullscreen.min.js',
                    ['jquery'],
                    TOOCHEKE_COMPANION_VERSION,
                    true
                );

                // Fullscreen JS
                wp_enqueue_script(
                    'manga',
                    $plugin_url . 'js/manga.js',
                    ['jquery'],
                    TOOCHEKE_COMPANION_VERSION,
                    true
                );
            }

            public function toocheke_enqueue_age_verification_assets()
            {

                $verify_age = get_option('toocheke-age-verification') && 1 == get_option('toocheke-age-verification');
                $series_id  = $comic_id  = 0;
                if (is_singular('comic') || get_post_type() === 'comic' || is_home()) {

                    if (is_singular('comic')) {
                        global $post;
                        $comic_id = get_the_ID();
                        if ($post->post_parent) {
                            $series_id = $post->post_parent;
                        }
                    }
                    if (is_home()) {
                        $home_layout     = get_theme_mod('home_layout_setting', 'default');
                        $webtoon_layouts = ["default", "alt-3", "alt-5"];
                        if (! in_array($home_layout, $webtoon_layouts)) {
                            $comic_order        = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
                            $single_comics_args = [
                                'post_type'      => 'comic',
                                'post_status'    => 'publish',
                                'posts_per_page' => 1,
                                'orderby'        => 'post_date',
                                'order'          => $comic_order,
                            ];
                            $single_comic_query = new WP_Query($single_comics_args);
                            while ($single_comic_query->have_posts()): $single_comic_query->the_post();
                                $comic_id  = get_the_ID();
                                $series_id = wp_get_post_parent_id($comic_id);
                            endwhile;
                            wp_reset_postdata();
                        }
                    }
                }
                if ($comic_id) {
                    $verify_age = get_post_meta($comic_id, 'age-verification', true) ? 1 : $verify_age;
                }

                if ($series_id) {
                    $verify_age = get_post_meta($series_id, 'age-verification', true) ? 1 : $verify_age;
                }

                if ($verify_age) {
                    wp_enqueue_script('toocheke-age-verify-script', plugins_url('toocheke-companion' . '/js/age-verify.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);

                    $nonce = wp_create_nonce('toocheke-verify-age');
                    wp_localize_script('toocheke-age-verify-script', 'toocheke_ajax_obj', [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce'    => $nonce,
                    ]);
                }
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
            /**
             * Add Series ID for comments if it exits
             */
            public function toocheke_redirect_comments($location, $commentdata)
            {
                if ((isset($_REQUEST['series_id'])) && ($_REQUEST['series_id'] != '')) {
                    $location = add_query_arg("sid", $_REQUEST['series_id'], $location);
                }
                return $location;
            }

            public function toocheke_rewrite_series_comic_permalink($permalink, $post, $leavename)
            {
                $post_id = $post->ID;

                if ($post->post_type != 'comic' || empty($permalink) || in_array($post->post_status, ['draft', 'pending', 'auto-draft']) || ! isset($_GET['sid'])) {
                    return $permalink;
                }

                $parent      = $post->post_parent;
                $parent_post = get_post($parent);
                //$permalink =  home_url( 'series/' . $parent_post->post_name . '/comic/' . $post->post_name );
                //$permalink = str_replace('comic', 'series', $permalink);

                return $permalink;
            }
            /**
             * Add fields for logged in commenter
             */
            public function toocheke_add_logged_in_fields()
            {
                if (is_user_logged_in()) {
                    $series_id = null;
                    $series_id = isset($_GET['sid']) ? (int) $_GET['sid'] : null;
                    if ($series_id != null) {
                        echo '<input id="series_id" name="series_id" type="hidden" value="' . esc_attr($series_id) . '" />';
                    }
                }
            }
            /**
             * Add Toocheke Menu
             */
            public function toocheke_add_plugin_main_menu()
            {
                $theme = wp_get_theme(); // gets the current theme
                add_menu_page('Toocheke', 'Toocheke', 'edit_posts', 'toocheke-menu', [$this, 'toocheke_dashboard_hub_page'], 'dashicons-toocheke-companion', 2);
                add_submenu_page('toocheke-menu', 'Dashboard', 'Dashboard', 'edit_posts', 'toocheke-menu', [$this, 'toocheke_dashboard_hub_page'], 1);
                add_submenu_page('toocheke-menu', 'All Series', 'All Series', 'edit_posts', 'edit.php?post_type=series', null, 2);
                add_submenu_page('toocheke-menu', 'Add New Series', 'Add New Series', 'edit_posts', 'post-new.php?post_type=series', null, 3);
                add_submenu_page('toocheke-menu', 'Series Hub', 'Series Hub', 'edit_posts', 'toocheke-series-hub', [$this, 'toocheke_series_hub_page'], 4);
                add_submenu_page('toocheke-menu', 'All Comics', 'All Comics', 'edit_posts', 'edit.php?post_type=comic', null, 5);
                add_submenu_page('toocheke-menu', 'Add New Comic', 'Add New Comic', 'edit_posts', 'post-new.php?post_type=comic', null, 6);
                add_submenu_page('toocheke-menu', 'Comics Hub', 'Comics Hub', 'edit_posts', 'toocheke-comics-hub', [$this, 'toocheke_comics_hub_page'], 7);
                add_submenu_page('toocheke-menu', 'All Manga Series', 'All Manga Series', 'edit_posts', 'edit.php?post_type=manga_series', null, 8);
                add_submenu_page('toocheke-menu', 'Add New Manga Series', 'Add New Manga Series', 'edit_posts', 'post-new.php?post_type=manga_series', null, 9);
                add_submenu_page('toocheke-menu', 'All Manga Volumes', 'All Manga Volumes', 'edit_posts', 'edit.php?post_type=manga_volume', null, 10);
                add_submenu_page('toocheke-menu', 'Add New Manga Volume', 'Add New Manga Volume', 'edit_posts', 'post-new.php?post_type=manga_volume', null, 11);
                add_submenu_page('toocheke-menu', 'All Manga Chapters', 'All Manga Chapters', 'edit_posts', 'edit.php?post_type=manga_chapter', null, 12);
                add_submenu_page('toocheke-menu', 'Add New Manga Chapter', 'Add New Manga Chapter', 'edit_posts', 'post-new.php?post_type=manga_chapter', null, 13);
                add_submenu_page('toocheke-menu', 'Manga Hub', 'Manga Hub', 'edit_posts', 'toocheke-manga-hub', [$this, 'toocheke_manga_hub_page'], 14);

                if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme) {
                    add_submenu_page('toocheke-menu', 'Premium', 'Premium', 'manage_options', 'toocheke-premium-hub', [$this, 'toocheke_premium_hub_page'], 15);
                }
                add_submenu_page('toocheke-menu', 'Options', 'Options', 'edit_posts', 'toocheke-options-page', [$this, 'toocheke_display_options_page'], 16);
                add_submenu_page('toocheke-menu', 'Import From Comic Easel', 'Import From Comic Easel', 'edit_posts', 'toocheke-import-comic-easel', [$this, 'toocheke_include_import_comic_easel_page'], 17);
                add_submenu_page('toocheke-menu', 'Import From Webcomic', 'Import From Webcomic', 'edit_posts', 'toocheke-import-webcomic', [$this, 'toocheke_include_import_webcomic_page'], 18);
            }

            // Include hub methods here
            public function toocheke_dashboard_hub_page()
            {
                include plugin_dir_path(__FILE__) . 'inc/hubs/hub-dashboard.php';
            }
            public function toocheke_series_hub_page()
            {
                include plugin_dir_path(__FILE__) . 'inc/hubs/hub-series.php';
            }
            public function toocheke_comics_hub_page()
            {
                include plugin_dir_path(__FILE__) . 'inc/hubs/hub-comics.php';
            }
            public function toocheke_manga_hub_page()
            {
                include plugin_dir_path(__FILE__) . 'inc/hubs/hub-manga.php';
            }
            public function toocheke_premium_hub_page()
            {
                include plugin_dir_path(__FILE__) . 'inc/hubs/hub-premium.php';
            }
            public function toocheke_tools_hub_page()
            {
                include plugin_dir_path(__FILE__) . 'inc/hubs/hub-tools.php';
            }

            public function toocheke_manga_menu_highlighting()
            {
                global $parent_file, $submenu_file, $pagenow;

                // Manga Series CPT pages
                if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_series') {
                    $parent_file  = 'toocheke-menu';
                    $submenu_file = 'edit.php?post_type=manga_series';
                }
                if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'manga_series') {
                    $parent_file  = 'toocheke-menu';
                    $submenu_file = 'post-new.php?post_type=manga_series';
                }

                // Manga Series taxonomies
                if ($pagenow === 'edit-tags.php' && isset($_GET['taxonomy'], $_GET['post_type']) && $_GET['post_type'] === 'manga_series') {
                    if (in_array($_GET['taxonomy'], ['manga_genre', 'manga_publisher'])) {
                        $parent_file  = 'toocheke-menu';
                        $submenu_file = 'edit-tags.php?taxonomy=' . $_GET['taxonomy'] . '&post_type=manga_series';
                    }
                }
            }
            /**
             * Add "All [Post Type]" button to Add New CPT pages
             */
            public function toocheke_add_all_posts_button()
            {
                global $pagenow, $post_type;

                // Only run on "Add New" pages
                if ($pagenow !== 'post-new.php') {
                    return;
                }

                // List of post types you want this for
                $cpt_list = ['series', 'comic', 'manga_series', 'manga_volume', 'manga_chapter'];

                if (in_array($post_type, $cpt_list)) {
                    $post_type_obj = get_post_type_object($post_type);
                    if ($post_type_obj) {
                        $all_posts_url = admin_url('edit.php?post_type=' . $post_type);
                        $button_label  = 'All ' . $post_type_obj->labels->name;

                        echo '<style>
                .toocheke-all-posts-button {
                    margin-left: 10px;
                }
            </style>';

                        echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    $(".wrap h1").after("<a href=\'' . esc_url($all_posts_url) . '\' class=\'page-title-action toocheke-all-posts-button\'>' . esc_html($button_label) . '</a>");
                });
            </script>';
                    }
                }
            }

            /**
             * Modify excerpt length
             */
            public function toocheke_excerpt_length($length)
            {
                return 40;
            }
            /**
             * Remove paragraphs from  comic post types
             */
            public function toocheke_remove_autop_for_comic($content)
            {
                'comic' === get_post_type() && remove_filter('the_content', 'wpautop');
                return $content;
            }

            /**
             * Make post parent public
             */
            public function toocheke_make_post_parent_public()
            {
                if (is_admin()) {
                    $GLOBALS['wp']->add_query_var('post_parent');
                }
            }
            /**
             * Add custom quick edit fields.
             */
            public function toocheke_quick_edit_fields($column_name, $post_type)
            {
                global $post;
                // you can check post type as well but is seems not required because your columns are added for specific CPT anyway
                $post_type_object = get_post_type_object($post_type);
                switch ($column_name):
                    case 'comic_series': {

                            $series = wp_dropdown_pages(['post_type' => 'series', 'selected' => $post->post_parent, 'name' => 'parent_id', 'show_option_none' => __('(No Series)', 'toocheke-companion'), 'sort_column' => 'menu_order, post_title', 'echo' => 0]);

                            wp_nonce_field('toocheke_companion_quick_edit_nonce', 'toocheke_companion_nonce');

                            echo '<fieldset class="inline-edit-col-left clear">';
                            echo '<div class="inline-edit-group wp-clearfix">';

                            echo '<label class="alignleft">
                        <span class="title">Series</span>
                        <span class="input-text-wrap">' . (! empty($series) ? $series : '') . '</span>
                    </label>';
                            echo '</div>';
                            echo '</fieldset>';

                            break;
                        }
                    case 'patreon_level': {
                            include_once ABSPATH . 'wp-admin/includes/plugin.php';
                            if (is_plugin_active('patreon-connect/patreon.php')) {
                                $tiers = '<select id="patreon_level" name="patreon_level"><option>' . Patreon_Wordpress::make_tiers_select($post) . '</option></select>';
                                wp_nonce_field('toocheke_companion_quick_edit_nonce', 'toocheke_companion_nonce');

                                echo '<fieldset class="inline-edit-col-center">';
                                echo '<div class="inline-edit-group wp-clearfix">';

                                echo '<label class="alignleft">
                    <span class="title">Patreon Level</span>
                    <span class="input-text-wrap">' . (! empty($tiers) ? $tiers : '') . '</span>
                </label>';
                                echo '</div>';
                                echo '</fieldset>';
                            }

                            break;
                        }

                endswitch;
            }
            /**
             * Quick Edit Save.
             */
            public function toocheke_quick_edit_save($post_id)
            {
                // check user capabilities
                if (! current_user_can('edit_post', $post_id)) {
                    return;
                }
                // check nonce
                if (isset($_POST['toocheke_companion_nonce']) && ! wp_verify_nonce($_POST['toocheke_companion_nonce'], 'toocheke_companion_quick_edit_nonce')) {
                    return;
                }

                // update the series for the comic
                if (isset($_POST['parent_id'])) {
                    update_post_meta($post_id, 'post_parent', $_POST['parent_id']);
                }
                // update patreon level
                if (isset($_REQUEST['patreon_level'])) {
                    update_post_meta($post_id, 'patreon-level', $_REQUEST['patreon_level']);
                }
            }
            /**
             * Bulk Edit Save.
             */
            public function toocheke_save_bulk_edit_hook()
            {
                if (! wp_verify_nonce($_POST['nonce'], 'toocheke_companion_quick_edit_nonce')) {
                    die();
                }

                // well, if post IDs are empty, it is nothing to do here
                if (empty($_POST['post_ids'])) {
                    die();
                }

                // for each post ID
                foreach ($_POST['post_ids'] as $id) {

                    // if series is empty,  we shouldn't change it
                    if (! empty($_POST['series'])) {
                        update_post_meta($id, 'post_parent', $_POST['series']);
                    }

                    // if patreon level empty, do nothing
                    if (! empty($_POST['patreon_level'])) {
                        update_post_meta($id, 'patreon-level', $_POST['patreon_level']);
                    }
                }

                die();
            }
            /**
             * Age Verification Popup
             */
            public function toocheke_verify_age_popup()
            {
                $verify_age = get_option('toocheke-age-verification') && 1 == get_option('toocheke-age-verification');

                $series_id = $comic_id = 0;
                if (is_singular('comic') || get_post_type() === 'comic' || is_home()) {
                    if (is_singular('comic')) {
                        global $post;
                        $comic_id = get_the_ID();
                        if ($post->post_parent) {
                            $series_id = $post->post_parent;
                        }
                    }
                    if (is_home()) {
                        $home_layout     = get_theme_mod('home_layout_setting', 'default');
                        $webtoon_layouts = ["default", "alt-3", "alt-5"];
                        if (! in_array($home_layout, $webtoon_layouts)) {
                            $comic_order        = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
                            $single_comics_args = [
                                'post_type'      => 'comic',
                                'post_status'    => 'publish',
                                'posts_per_page' => 1,
                                'orderby'        => 'post_date',
                                'order'          => $comic_order,
                            ];
                            $single_comic_query = new WP_Query($single_comics_args);
                            while ($single_comic_query->have_posts()): $single_comic_query->the_post();
                                $comic_id  = get_the_ID();
                                $series_id = wp_get_post_parent_id($comic_id);
                            endwhile;
                            wp_reset_postdata();
                        }
                    }
                }
                if ($comic_id) {
                    $verify_age = get_post_meta($comic_id, 'age-verification', true) ? 1 : $verify_age;
                }
                if ($series_id) {
                    $verify_age = get_post_meta($series_id, 'age-verification', true) ? 1 : $verify_age;
                }

                if ($verify_age) {
                    if (! isset($_COOKIE['toocheke_age_verification'])) { ?>
                <div class="modal" id="age-verification-modal" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">


                            <!-- Modal body -->
                            <div class="modal-body p-4">

                                <h3 class="text-center">Verify Your Age</h3>
                                <hr />
                                <p class="text-center">You must be <b class="text-danger">18 years</b> or older to continue
                                    browsing this website. Are you at least 18 years old?</p>
                                <p class="text-center">
                                    <button id="btn-18-yes" type="button" class="btn btn-success">Yes</button>
                                    <button id="btn-18-no" type="button" class="btn btn-secondary">Close</button>
                                </p>

                            </div>


                        </div>
                    </div>
                </div>
                <style>
                    .modal-backdrop {
                        opacity: 0.9;
                    }
                </style>
        <?php }
                }
            }
            public function toocheke_set_age_verification_cookie()
            {
                check_ajax_referer('toocheke-verify-age');

                if (defined('DOING_AJAX') && DOING_AJAX) {
                    setcookie('toocheke_age_verification', true, time() + (60 * 60 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN); //expire in 30 days
                }
                die();
            }
            public function toocheke_remove_image_link()
            {
                $image_set = get_option('image_default_link_type');

                if ($image_set !== 'none') {
                    update_option('image_default_link_type', 'none');
                }
            }
            public function toocheke_attachment_image_link_remove_filter($content)
            {
                $allow_image_click = get_option('toocheke-image-click') && 1 == get_option('toocheke-image-click');

                if (! $allow_image_click) {
                    if (is_singular('comic') || get_post_type() === 'comic') {
                        $content =
                            preg_replace(
                                [
                                    '{<a(.*?)(wp-att|wp-content\/uploads)[^>]*><img}',
                                    '{ wp-image-[0-9]*" /></a>}'
                                ],
                                ['<img', '" />'],
                                $content
                            );
                    }
                }
                return $content;
            }

            /**
             * Likes Functionality
             */
            public function toocheke_process_like()
            {
                // Check for bot User-Agent
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                if (empty($user_agent) || preg_match('/bot|crawl|spider|slurp|mediapartners/i', $user_agent)) {
                    wp_send_json_error('Bot detected');
                }

                // Security
                $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : 0;
                if (! wp_verify_nonce($nonce, 'toocheke-likes-nonce')) {
                    exit(__('Not permitted', 'toocheke-companion'));
                }
                // Test if javascript is disabled
                $disabled = (isset($_REQUEST['disabled']) && $_REQUEST['disabled'] == true) ? true : false;
                // Test if this is a comment
                $is_comment = (isset($_REQUEST['is_comment']) && $_REQUEST['is_comment'] == 1) ? 1 : 0;
                // Base variables
                $post_id    = (isset($_REQUEST['post_id']) && is_numeric($_REQUEST['post_id'])) ? $_REQUEST['post_id'] : '';
                $result     = [];
                $post_users = null;
                $like_count = 0;
                // Get plugin options
                if ($post_id != '') {
                    $count = ($is_comment == 1) ? get_comment_meta($post_id, "_comment_like_count", true) : get_post_meta($post_id, "_post_like_count", true); // like count
                    $count = (isset($count) && is_numeric($count)) ? $count : 0;
                    if (! $this->toocheke_check_if_liked($post_id, $is_comment)) { // Like the post
                        if (is_user_logged_in()) {                                    // user is logged in
                            $user_id    = get_current_user_id();
                            $post_users = $this->toocheke_post_user_likes($user_id, $post_id, $is_comment);
                            if ($is_comment == 1) {
                                // Update User & Comment
                                $user_like_count = get_user_option("_comment_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                update_user_option($user_id, "_comment_like_count", ++$user_like_count);
                                if ($post_users) {
                                    update_comment_meta($post_id, "_user_comment_liked", $post_users);
                                }
                            } else {
                                // Update User & Post
                                $user_like_count = get_user_option("_user_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                update_user_option($user_id, "_user_like_count", ++$user_like_count);
                                if ($post_users) {
                                    update_post_meta($post_id, "_user_liked", $post_users);
                                }
                            }
                        } else { // user is anonymous
                            $user_ip    = $this->toocheke_get_ip();
                            $post_users = $this->toocheke_post_ip_likes($user_ip, $post_id, $is_comment);
                            // Update Post
                            if ($post_users) {
                                if ($is_comment == 1) {
                                    update_comment_meta($post_id, "_user_comment_IP", $post_users);
                                } else {
                                    update_post_meta($post_id, "_user_IP", $post_users);
                                }
                            }
                        }
                        $like_count         = ++$count;
                        $response['status'] = "liked";
                        $response['icon']   = $this->toocheke_get_liked_icon();
                    } else {                   // Unlike the post
                        if (is_user_logged_in()) { // user is logged in
                            $user_id    = get_current_user_id();
                            $post_users = $this->toocheke_post_user_likes($user_id, $post_id, $is_comment);
                            // Update User
                            if ($is_comment == 1) {
                                $user_like_count = get_user_option("_comment_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                if ($user_like_count > 0) {
                                    update_user_option($user_id, "_comment_like_count", --$user_like_count);
                                }
                            } else {
                                $user_like_count = get_user_option("_user_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                if ($user_like_count > 0) {
                                    update_user_option($user_id, '_user_like_count', --$user_like_count);
                                }
                            }
                            // Update Post
                            if ($post_users) {
                                $uid_key = array_search($user_id, $post_users);
                                unset($post_users[$uid_key]);
                                if ($is_comment == 1) {
                                    update_comment_meta($post_id, "_user_comment_liked", $post_users);
                                } else {
                                    update_post_meta($post_id, "_user_liked", $post_users);
                                }
                            }
                        } else { // user is anonymous
                            $user_ip    = $this->toocheke_get_ip();
                            $post_users = $this->toocheke_post_ip_likes($user_ip, $post_id, $is_comment);
                            // Update Post
                            if ($post_users) {
                                $uip_key = array_search($user_ip, $post_users);
                                unset($post_users[$uip_key]);
                                if ($is_comment == 1) {
                                    update_comment_meta($post_id, "_user_comment_IP", $post_users);
                                } else {
                                    update_post_meta($post_id, "_user_IP", $post_users);
                                }
                            }
                        }
                        $like_count         = ($count > 0) ? --$count : 0; // Prevent negative number
                        $response['status'] = "unliked";
                        $response['icon']   = $this->toocheke_get_unliked_icon();
                    }
                    if ($is_comment == 1) {
                        update_comment_meta($post_id, "_comment_like_count", $like_count);
                        update_comment_meta($post_id, "_comment_like_modified", date('Y-m-d H:i:s'));
                    } else {
                        update_post_meta($post_id, "_post_like_count", $like_count);
                        update_post_meta($post_id, "_post_like_modified", date('Y-m-d H:i:s'));
                    }
                    $response['count']   = $this->toocheke_get_like_count($like_count);
                    $response['testing'] = $is_comment;
                    if ($disabled == true) {
                        if ($is_comment == 1) {
                            wp_redirect(get_permalink(get_the_ID()));
                            exit();
                        } else {
                            wp_redirect(get_permalink($post_id));
                            exit();
                        }
                    } else {
                        wp_send_json($response);
                    }
                }
            }

            /**
             * Check if the post is already liked
             */
            public function toocheke_check_if_liked($post_id, $is_comment)
            {
                $post_users = null;
                $user_id    = null;
                if (is_user_logged_in()) { // user is logged in
                    $user_id         = get_current_user_id();
                    $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_liked") : get_post_meta($post_id, "_user_liked");
                    if (count($post_meta_users) != 0) {
                        $post_users = $post_meta_users[0];
                    }
                } else { // user is anonymous
                    $user_id         = $this->toocheke_get_ip();
                    $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_IP") : get_post_meta($post_id, "_user_IP");
                    if (count($post_meta_users) != 0) { // meta exists, set up values
                        $post_users = $post_meta_users[0];
                    }
                }
                if (is_array($post_users) && in_array($user_id, $post_users)) {
                    return true;
                } else {
                    return false;
                }
            } // toocheke_check_if_liked()

            /**
             * Get like button
             */
            public function toocheke_get_like_button($post_id, $is_comment = null)
            {
                $is_comment = (null == $is_comment) ? 0 : 1;
                $output     = '';
                $nonce      = wp_create_nonce('toocheke-likes-nonce'); // Security
                if ($is_comment == 1) {
                    $post_id_class = esc_attr(' toocheke-likes-comment-button-' . $post_id);
                    $comment_class = esc_attr(' toocheke-likes-comment');
                    $like_count    = get_comment_meta($post_id, "_comment_like_count", true);
                    $like_count    = (isset($like_count) && is_numeric($like_count)) ? $like_count : 0;
                } else {
                    $post_id_class = esc_attr(' toocheke-likes-button-' . $post_id);
                    $comment_class = esc_attr('');
                    $like_count    = get_post_meta($post_id, "_post_like_count", true);
                    $like_count    = (isset($like_count) && is_numeric($like_count)) ? $like_count : 0;
                }
                $count      = $this->toocheke_get_like_count($like_count);
                $icon_empty = $this->toocheke_get_unliked_icon();
                $icon_full  = $this->toocheke_get_liked_icon();
                // Loader
                $loader = '<span id="toocheke-likes-loader"></span>';
                // Liked/Unliked Variables
                if ($this->toocheke_check_if_liked($post_id, $is_comment)) {
                    $class = esc_attr(' liked');
                    $title = __('Unlike', 'toocheke-companion');
                    $icon  = $icon_full;
                } else {
                    $class = '';
                    $title = __('Like', 'toocheke-companion');
                    $icon  = $icon_empty;
                }
                $output = '<span class="toocheke-likes-wrapper"><a href="' . admin_url('admin-ajax.php?action=toocheke_process_like' . '&post_id=' . $post_id . '&nonce=' . $nonce . '&is_comment=' . $is_comment . '&disabled=true') . '" class="toocheke-likes-button' . $post_id_class . $class . $comment_class . '" data-nonce="' . $nonce . '" data-post-id="' . $post_id . '" data-iscomment="' . $is_comment . '" title="' . $title . '">' . $icon . $count . '</a>' . $loader . '</span>';
                return $output;
            } // toocheke_get_like_button()

            /**
             * Add button shortcode
             */

            public function toocheke_like_short_code()
            {
                return $this->toocheke_get_like_button(get_the_ID(), 0);
            } // shortcode()

            /**
             * Gets post meta user likes (user id array),
             * then adds new user id to retrieved array
             */
            public function toocheke_post_user_likes($user_id, $post_id, $is_comment)
            {
                $post_users      = '';
                $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_liked") : get_post_meta($post_id, "_user_liked");
                if (count($post_meta_users) != 0) {
                    $post_users = $post_meta_users[0];
                }
                if (! is_array($post_users)) {
                    $post_users = [];
                }
                if (! in_array($user_id, $post_users)) {
                    $post_users['user-' . $user_id] = $user_id;
                }
                return $post_users;
            } // toocheke_post_user_likes()

            /**
             * Gets post meta ip likes (ip array),
             * then adds new ip to retrieved array
             */
            public function toocheke_post_ip_likes($user_ip, $post_id, $is_comment)
            {
                $post_users      = '';
                $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_IP") : get_post_meta($post_id, "_user_IP");
                // Retrieve post information
                if (count($post_meta_users) != 0) {
                    $post_users = $post_meta_users[0];
                }
                if (! is_array($post_users)) {
                    $post_users = [];
                }
                if (! in_array($user_ip, $post_users)) {
                    $post_users['ip-' . $user_ip] = $user_ip;
                }
                return $post_users;
            } // toocheke_post_ip_likes()

            /**
             * Utility to retrieve IP address
             */
            public function toocheke_get_ip()
            {
                if (isset($_SERVER['HTTP_CLIENT_IP']) && ! empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && ! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else {
                    $ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
                }
                $ip = filter_var($ip, FILTER_VALIDATE_IP);
                $ip = ($ip === false) ? '0.0.0.0' : $ip;
                return $ip;
            } // toocheke_get_ip()

            /**
             * Utility returns the button icon for "like" action
             */
            public function toocheke_get_liked_icon()
            {
                /* If already using Font Awesome with your theme, replace svg with: <i class="fa fa-heart"></i> */
                $icon = '<span class="toocheke-likes-icon"><svg role="img" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0" y="0" viewBox="0 0 128 128" enable-background="new 0 0 128 128" xml:space="preserve"><path id="heart-full" d="M124 20.4C111.5-7 73.7-4.8 64 19 54.3-4.9 16.5-7 4 20.4c-14.7 32.3 19.4 63 60 107.1C104.6 83.4 138.7 52.7 124 20.4z"/>&#9829;</svg></span>';
                return $icon;
            } // toocheke_get_liked_icon()

            /**
             * Utility returns the button icon for "unlike" action
             */
            public function toocheke_get_unliked_icon()
            {
                /* If already using Font Awesome with your theme, replace svg with: <i class="fa fa-heart-o"></i> */
                $icon = '<span class="toocheke-likes-icon"><svg role="img" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0" y="0" viewBox="0 0 128 128" enable-background="new 0 0 128 128" xml:space="preserve"><path id="heart" d="M64 127.5C17.1 79.9 3.9 62.3 1 44.4c-3.5-22 12.2-43.9 36.7-43.9 10.5 0 20 4.2 26.4 11.2 6.3-7 15.9-11.2 26.4-11.2 24.3 0 40.2 21.8 36.7 43.9C124.2 62 111.9 78.9 64 127.5zM37.6 13.4c-9.9 0-18.2 5.2-22.3 13.8C5 49.5 28.4 72 64 109.2c35.7-37.3 59-59.8 48.6-82 -4.1-8.7-12.4-13.8-22.3-13.8 -15.9 0-22.7 13-26.4 19.2C60.6 26.8 54.4 13.4 37.6 13.4z"/>&#9829;</svg></span>';
                return $icon;
            } // toocheke_get_unliked_icon()

            /**
             * Formatting like count
             * appending "K" if one thousand or greater,
             * "M" if one million or greater,
             * and "B" if one billion or greater (unlikely).
             * $precision = how many decimal points to display (1.25K)
             */
            public function toocheke_format_count($number)
            {
                $precision = 2;
                if ($number >= 1000 && $number < 1000000) {
                    $formatted = number_format($number / 1000, $precision) . 'K';
                } else if ($number >= 1000000 && $number < 1000000000) {
                    $formatted = number_format($number / 1000000, $precision) . 'M';
                } else if ($number >= 1000000000) {
                    $formatted = number_format($number / 1000000000, $precision) . 'B';
                } else {
                    $formatted = $number; // Number is less than 1000
                }
                $formatted = str_replace('.00', '', $formatted);
                return $formatted;
            } // toocheke_format_count()

            /**
             * Get like count
             */
            public function toocheke_get_like_count($like_count)
            {
                $like_text = __('Like', 'toocheke-companion');
                if (is_numeric($like_count) && $like_count > 0) {
                    $number = $this->toocheke_format_count($like_count);
                } else {
                    $number = $like_text;
                }
                $count = '<span class="toocheke-likes-count">' . $number . '</span>';
                return $count;
            } // toocheke_get_like_count()

            // User Profile List
            public function toocheke_show_user_likes($user)
            { ?>
        <table class="form-table">
            <tr>
                <th><label for="user_likes"><?php _e('You Like:', 'toocheke-companion'); ?></label></th>
                <td>
                    <?php
                    $types = get_post_types(['public' => true]);
                    $args  = [
                        'numberposts' => -1,
                        'post_type'   => $types,
                        'meta_query'  => [
                            [
                                'key'     => '_user_liked',
                                'value'   => $user->ID,
                                'compare' => 'LIKE',
                            ],
                        ]
                    ];
                    $sep        = '';
                    $like_query = new WP_Query($args);
                    if ($like_query->have_posts()): ?>
                        <p>
                            <?php while ($like_query->have_posts()): $like_query->the_post();
                                echo $sep; ?><a href="<?php the_permalink(); ?>"
                                    title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
                            <?php
                                $sep = ' &middot; ';
                            endwhile;
                            ?>
                        </p>
                    <?php else: ?>
                        <p><?php _e('You do not like anything yet.', 'toocheke-companion'); ?></p>
                    <?php
                    endif;
                    wp_reset_postdata();
                    ?>
                </td>
            </tr>
        </table>
    <?php } // toocheke_show_user_likes()
            public function toocheke_comic_sortable_columns($columns)
            {
                $columns['comic_likes'] = 'likes';
                $columns['comic_views'] = 'views';
                return $columns;
            }

            public function toocheke_add_bookmark_nav_item($items, $args)
            {
                $display_bookmark_button = get_option('toocheke-comic-bookmark') && 1 == get_option('toocheke-comic-bookmark');
                if ($display_bookmark_button) {
                    $items .= '<li class="nav-item menu-item comic-bookmark-item"> <a id="toocheke-go-to-bookmark" class="nav-link" href="javascript:;"><i class="fas fa-lg fa-bookmark"" aria-hidden="true"></i></a></li>';
                }
                return $items;
            }
            /**
             * Universal theme functions
             */
            //Register shortcodes

            public function toocheke_register_universal_shortcodes()
            {
                add_shortcode('toocheke-all-series', [$this, 'toocheke_all_series_shortcode']);
                add_shortcode('toocheke-all-chapters', [$this, 'toocheke_all_chapters_shortcode']);
                add_shortcode('toocheke-latest-chapters', [$this, 'toocheke_latest_chapters_shortcode']);
                add_shortcode('toocheke-first-comic', [$this, 'toocheke_first_comic_shortcode']);
                add_shortcode('toocheke-latest-comic', [$this, 'toocheke_latest_comic_shortcode']);
                add_shortcode('toocheke-latest-comics', [$this, 'toocheke_latest_comics_shortcode']);
                add_shortcode('toocheke-top-ten-comics', [$this, 'toocheke_top_ten_comics_shortcode']);
                add_shortcode('toocheke-scheduled-comics', [$this, 'toocheke_scheduled_comics_shortcode']);
                add_shortcode('toocheke-comic-archive', [$this, 'toocheke_comic_archive_shortcode']);
                add_shortcode('toocheke-collection-archive', [$this, 'toocheke_taxonomy_archive_shortcode']);
                add_shortcode('toocheke-chapter-archive', [$this, 'toocheke_taxonomy_archive_shortcode']);
                add_shortcode('toocheke-tag-archive', [$this, 'toocheke_taxonomy_archive_shortcode']);
                add_shortcode('toocheke-location-archive', [$this, 'toocheke_taxonomy_archive_shortcode']);
                add_shortcode('toocheke-character-archive', [$this, 'toocheke_taxonomy_archive_shortcode']);
                add_shortcode('toocheke-characters', [$this, 'toocheke_characters_shortcode']);
                add_shortcode('toocheke-current-year', 'toocheke_current_year_shortcode');
                add_shortcode('toocheke-all-manga-series', [$this, 'toocheke_all_manga_series_shortcode']);
                add_shortcode('toocheke-popular-manga-series', [$this, 'toocheke_popular_manga_series_shortcode']);
                add_shortcode('toocheke-popular-manga-volumes', [$this, 'toocheke_popular_manga_volumes_shortcode']);
                add_shortcode('toocheke-popular-manga-chapters', [$this, 'toocheke_popular_manga_chapters_shortcode']);
            }
            //Display all Series
            public function toocheke_all_series_shortcode($atts)
            {
                $default_atts = [
                    "comics_order" => null,
                    "link_to"      => 'comic',
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                if (! empty($params['comics_order'])) {
                    set_query_var('comics_order', $params['comics_order']);
                }
                if (! empty($params['link_to'])) {
                    set_query_var('link_to', $params['link_to']);
                }

                $output = $output . $templates->get_template_part('content', 'allseries');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Latest Comics
            public function toocheke_latest_comics_shortcode($atts)
            {
                $default_atts = [
                    "sid"   => null,
                    "limit" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }
                if (! empty($params['limit'])) {
                    set_query_var('limit', (int) $params['limit']);
                }

                $output = $output . $templates->get_template_part('content', 'latestcomicslist');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Latest Chapters
            public function toocheke_latest_chapters_shortcode($atts)
            {
                $default_atts = [
                    "sid" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }
                $output = $output . $templates->get_template_part('content', 'latestchapters');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display All Chapters
            public function toocheke_all_chapters_shortcode($atts)
            {
                $default_atts = [
                    "sid" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }
                $output = $output . $templates->get_template_part('content', 'allchapters');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Top Ten Comics
            public function toocheke_top_ten_comics_shortcode()
            {
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $output = $output . $templates->get_template_part('content', 'toptencomics');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Scheduled Comics
            public function toocheke_scheduled_comics_shortcode()
            {
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $output = $output . $templates->get_template_part('content', 'scheduledcomics');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Characters
            public function toocheke_characters_shortcode()
            {
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $output = $output . $templates->get_template_part('content', 'characters');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Latest Comic
            public function toocheke_latest_comic_shortcode($atts)
            {
                $default_atts = [
                    "sid" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                set_query_var('comic_order', 'DESC');
                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }
                $output = $output . $templates->get_template_part('content', 'latestcomic', ['comic_order' => 'DESC']);

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Latest Comic
            public function toocheke_first_comic_shortcode($atts)
            {
                $default_atts = [
                    "sid" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                set_query_var('comic_order', 'ASC');
                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }
                $output = $output . $templates->get_template_part('content', 'latestcomic', ['comic_order' => 'ASC']);

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Comic Archive
            public function toocheke_comic_archive_shortcode($atts)
            {
                $default_atts = [
                    "sid" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }

                $output = $output . $templates->get_template_part('content', 'comicarchive');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display Collection Archive
            public function toocheke_taxonomy_archive_shortcode($atts, $content, $shortcode_tag)
            {
                $default_atts = [
                    "term" => null,
                ];
                $params   = shortcode_atts($default_atts, $atts);
                $output   = '';
                $taxonomy = null;
                switch ($shortcode_tag) {
                    case 'toocheke-collection-archive':
                        $taxonomy = 'collections';
                        break;
                    case 'toocheke-chapter-archive':
                        $taxonomy = 'chapters';
                        break;
                    case 'toocheke-tag-archive':
                        $taxonomy = 'comic_tags';
                        break;
                    case 'toocheke-location-archive':
                        $taxonomy = 'comic_locations';
                        break;
                    case 'toocheke-character-archive':
                        $taxonomy = 'comic_characters';
                        break;
                    default:
                        $taxonomy = null;
                }
                if (empty($params['term']) || ! $taxonomy) {
                    return $output;
                }

                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                set_query_var('term', $params['term']);
                set_query_var('taxonomy', $taxonomy);
                $output = $output . $templates->get_template_part('content', 'taxonomyarchive');

                return ob_get_clean();

                // Output needs to be return
                return $output;
            }
            //Display current year
            public function toocheke_current_year_shortcode()
            {
                return date('Y');
            }

            /**
             * All Manga Series shortcode (with filter form)
             */
            public function toocheke_all_manga_series_shortcode($atts)
            {
                $templates = new Toocheke_Companion_Template_Loader;

                // Handle filters
                $selected_publisher = isset($_GET['publisher']) ? sanitize_text_field($_GET['publisher']) : '';
                $selected_genre     = isset($_GET['genre']) ? sanitize_text_field($_GET['genre']) : '';

                // Build query args
                $args_all_series = [
                    'post_type'      => 'manga_series',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ];

                // Add tax_query if filters are set
                $tax_query = [];

                if ($selected_publisher) {
                    $tax_query[] = [
                        'taxonomy' => 'manga_publisher',
                        'field'    => 'slug',
                        'terms'    => $selected_publisher,
                    ];
                }

                if ($selected_genre) {
                    $tax_query[] = [
                        'taxonomy' => 'manga_genre',
                        'field'    => 'slug',
                        'terms'    => $selected_genre,
                    ];
                }

                if ($tax_query) {
                    // Safer than using array spread (...) for PHP compatibility
                    $args_all_series['tax_query'] = array_merge(['relation' => 'AND'], $tax_query);
                }

                $query_all_series = new WP_Query($args_all_series);

                ob_start();
    ?>

        <hr class="toocheke-hr manga-hr" />
        <h2 id="manga-series-header">
            <?php echo esc_html__('Manga Series', 'toocheke-companion'); ?>
        </h2>

        <form method="get" class="manga-series-filter-form">
            <div class="filter-field">
                <label for="publisher"><?php esc_html_e('Publisher:', 'toocheke-companion'); ?></label>
                <select name="publisher" id="publisher">
                    <option value=""><?php esc_html_e('All Publishers', 'toocheke-companion'); ?></option>
                    <?php
                    $publishers = get_terms(['taxonomy' => 'manga_publisher', 'hide_empty' => true]);
                    if (! is_wp_error($publishers)) {
                        foreach ($publishers as $publisher) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($publisher->slug),
                                selected($selected_publisher, $publisher->slug, false),
                                esc_html($publisher->name)
                            );
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="genre"><?php esc_html_e('Genre:', 'toocheke-companion'); ?></label>
                <select name="genre" id="genre">
                    <option value=""><?php esc_html_e('All Genres', 'toocheke-companion'); ?></option>
                    <?php
                    $genres = get_terms(['taxonomy' => 'manga_genre', 'hide_empty' => true]);
                    if (! is_wp_error($genres)) {
                        foreach ($genres as $genre) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($genre->slug),
                                selected($selected_genre, $genre->slug, false),
                                esc_html($genre->name)
                            );
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="filter-field">
                <button type="submit" class="btn-sm btn-danger btn"><?php esc_html_e('Filter', 'toocheke-companion'); ?></button>
            </div>
        </form>

        <hr class="toocheke-hr manga-hr" />

<?php

                // Prepare args passed to template
                $template_args = [
                    'query'         => $query_all_series,
                    'section_id'    => '',
                    'section_title' => esc_html__('Manga Series', 'toocheke-companion'),
                ];

                // Call the template loader. Some implementations echo/include directly; others return the path.
                $maybe_path = $templates->get_template_part('content', 'indexmangagrid', $template_args);

                // If the loader returned a path we include it ourselves (make $args available like WP/Gamajo does)
                if (is_string($maybe_path) && file_exists($maybe_path)) {
                    // Provide both $args array and extracted variables for compatibility with templates
                    $args = $template_args;
                    extract($args); // creates $query, $section_id, $section_title for the template
                    include $maybe_path;
                }

                return ob_get_clean();
            }


            /**
             * Popular Manga Series shortcode
             */
            public function toocheke_popular_manga_series_shortcode($atts)
            {
                $templates = new Toocheke_Companion_Template_Loader;

                $query_pop_series = new WP_Query([
                    'post_type'      => 'manga_series',
                    'posts_per_page' => 4,
                    'orderby'        => 'meta_value_num',
                    'meta_key'       => '_post_like_count',
                    'order'          => 'DESC',
                ]);

                ob_start();

                $template_args = [
                    'query'         => $query_pop_series,
                    'section_id'    => 'manga-popular-series-header',
                    'section_title' => (esc_html(get_theme_mod('manga_popular_series_setting')) != "")
                        ? esc_html(get_theme_mod('manga_popular_series_setting'))
                        : esc_html__('Popular Series', 'toocheke-companion'),
                ];

                $maybe_path = $templates->get_template_part('content', 'indexmangagrid', $template_args);

                if (is_string($maybe_path) && file_exists($maybe_path)) {
                    $args = $template_args;
                    extract($args);
                    include $maybe_path;
                }

                return ob_get_clean();
            }

            /**
             * Popular Manga Volumes shortcode
             */
            public function toocheke_popular_manga_volumes_shortcode($atts)
            {
                $templates = new Toocheke_Companion_Template_Loader;

                $query_pop_volumes = new WP_Query([
                    'post_type'      => 'manga_volume',
                    'posts_per_page' => 4,
                    'orderby'        => 'meta_value_num',
                    'meta_key'       => '_post_like_count',
                    'order'          => 'DESC',
                ]);

                ob_start();

                $template_args = [
                    'query'         => $query_pop_volumes,
                    'section_id'    => 'manga-popular-volumes-header',
                    'section_title' => (esc_html(get_theme_mod('manga_popular_volumes_setting')) != "")
                        ? esc_html(get_theme_mod('manga_popular_volumes_setting'))
                        : esc_html__('Popular Volumes', 'toocheke-companion'),
                ];

                $maybe_path = $templates->get_template_part('content', 'indexmangagrid', $template_args);

                if (is_string($maybe_path) && file_exists($maybe_path)) {
                    $args = $template_args;
                    extract($args);
                    include $maybe_path;
                }

                return ob_get_clean();
            }

            /**
             * Popular Manga Chapters shortcode
             */
            public function toocheke_popular_manga_chapters_shortcode($atts)
            {
                $templates = new Toocheke_Companion_Template_Loader;

                $query_pop_chapters = new WP_Query([
                    'post_type'      => 'manga_chapter',
                    'posts_per_page' => 4,
                    'orderby'        => 'meta_value_num',
                    'meta_key'       => '_post_like_count',
                    'order'          => 'DESC',
                ]);

                ob_start();

                $template_args = [
                    'query'         => $query_pop_chapters,
                    'section_id'    => 'manga-popular-chapters-header',
                    'section_title' => (esc_html(get_theme_mod('manga_popular_chapters_setting')) != "")
                        ? esc_html(get_theme_mod('manga_popular_chapters_setting'))
                        : esc_html__('Popular Chapters', 'toocheke-companion'),
                ];

                $maybe_path = $templates->get_template_part('content', 'indexmangagrid', $template_args);

                if (is_string($maybe_path) && file_exists($maybe_path)) {
                    $args = $template_args;
                    extract($args);
                    include $maybe_path;
                }

                return ob_get_clean();
            }


            public function toocheke_single_comic_template($template)
            {
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    global $post;

                    if (get_post_type($post) !== 'comic' && ! is_single()) {
                        return $template;
                    }
                    add_filter('post_thumbnail_html', [$this, 'toocheke_disable_post_thumbnail'], 500, 2);
                    add_filter('the_content', [$this, 'toocheke_universal_single_comic_content_filter']);
                }
                return $template;
            }
            public function toocheke_disable_post_thumbnail($html, $post_id)
            {
                if (get_post_type($post_id) == 'comic') {
                    return '';
                }

                return $html;
            }
            public function toocheke_universal_single_comic_content_filter($content)
            {
                global $post;
                $templates = new Toocheke_Companion_Template_Loader;
                if (get_post_type($post) !== 'comic') {
                    return $content;
                }

                remove_filter('the_content', 'toocheke_universal_single_comic_content_filter');
                remove_filter('post_thumbnail_html', 'toocheke_disable_post_thumbnail');

                ob_start();
                require TOOCHEKE_COMPANION_PLUGIN_DIR . 'templates/content-singlecomic.php';
                $generated_content = ob_get_contents();
                ob_end_clean();
                $content = $generated_content;

                return $content;
            }
            public function toocheke_single_manga_templates($template)
            {
                $theme = wp_get_theme(); // gets the current theme
                if (
                    'Toocheke Premium' !== $theme->name &&
                    'Toocheke Premium' !== $theme->parent_theme &&
                    'Toocheke' !== $theme->name &&
                    'Toocheke' !== $theme->parent_theme
                ) {

                    global $post;

                    // Only handle our three CPTs on single pages
                    $allowed_types = ['manga_series', 'manga_volume', 'manga_chapter'];
                    if (! is_single() || ! in_array(get_post_type($post), $allowed_types, true)) {
                        return $template;
                    }

                    // Disable thumbnail and override content
                    add_filter('post_thumbnail_html', [$this, 'toocheke_disable_post_thumbnail'], 500, 2);
                    add_filter('the_content', [$this, 'toocheke_universal_single_manga_content_filter']);
                }

                return $template;
            }

            public function toocheke_universal_single_manga_content_filter($content)
            {
                global $post;

                $post_type     = get_post_type($post);
                $template_file = '';

                switch ($post_type) {
                    case 'manga_series':
                        $template_file = 'templates/content-singlemangaseries.php';
                        break;

                    case 'manga_volume':
                        if (isset($_GET['reader']) && $_GET['reader'] === 'true') {
                            $template_file = 'templates/content-singlemangavolumereader.php';
                        } else {
                            $template_file = 'templates/content-singlemangavolume.php';
                        }
                        break;

                    case 'manga_chapter':
                        $template_file = 'templates/content-singlemangachapterreader.php';
                        break;

                    default:
                        return $content; // bail if not ours
                }

                // Make sure we don’t create infinite loops
                remove_filter('the_content', [$this, 'toocheke_universal_single_manga_content_filter']);
                remove_filter('post_thumbnail_html', [$this, 'toocheke_disable_post_thumbnail']);

                // Load template output
                ob_start();
                require TOOCHEKE_COMPANION_PLUGIN_DIR . $template_file;
                $generated_content = ob_get_clean();

                return $generated_content ?: $content;
            }

            public function toocheke_universal_excerpt_length($length)
            {
                global $post;
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    if ($post->post_type == 'series') {
                        return 15;
                    } else {
                        return $length;
                    }
                }
                return $length;
            }
            public function toocheke_universal_rewrite_rules($wp_rewrite)
            {

                $theme = wp_get_theme(); // gets the current theme
                // Here we're hardcoding the CPT in, article in this case
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    $rules             = $this->toocheke_universal_generate_date_archives('comic', $wp_rewrite);
                    $wp_rewrite->rules = $rules + $wp_rewrite->rules;
                    return $wp_rewrite;
                }
                return $wp_rewrite;
            }
            /**
             * Generate date archive rewrite rules for comic
             * @param  string $cpt slug of the custom post type
             * @return rules       returns a set of rewrite rules for WordPress to handle
             */
            public function toocheke_universal_generate_date_archives($cpt, $wp_rewrite)
            {
                $rules = [];

                $post_type    = get_post_type_object($cpt);
                $slug_archive = $post_type->has_archive;
                if ($slug_archive === false) {
                    return $rules;
                }
                if ($slug_archive === true) {
                    // Here's my edit to the original function, let's pick up
                    // custom slug from the post type object if user has
                    // specified one.
                    $slug_archive = $post_type->rewrite['slug'];
                }

                $dates = [
                    [
                        'rule' => "([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})",
                        'vars' => ['year', 'monthnum', 'day'],
                    ],
                    [
                        'rule' => "([0-9]{4})/([0-9]{1,2})",
                        'vars' => ['year', 'monthnum'],
                    ],
                    [
                        'rule' => "([0-9]{4})",
                        'vars' => ['year'],
                    ],
                ];

                foreach ($dates as $data) {
                    $query = 'index.php?post_type=' . $cpt;
                    $rule  = $slug_archive . '/' . $data['rule'];

                    $i = 1;
                    foreach ($data['vars'] as $var) {
                        $query .= '&' . $var . '=' . $wp_rewrite->preg_index($i);
                        $i++;
                    }

                    $rules[$rule . "/?$"]                               = $query;
                    $rules[$rule . "/feed/(feed|rdf|rss|rss2|atom)/?$"] = $query . "&feed=" . $wp_rewrite->preg_index($i);
                    $rules[$rule . "/(feed|rdf|rss|rss2|atom)/?$"]      = $query . "&feed=" . $wp_rewrite->preg_index($i);
                    $rules[$rule . "/page/([0-9]{1,})/?$"]              = $query . "&paged=" . $wp_rewrite->preg_index($i);
                }
                return $rules;
            }
            public function toocheke_universal_get_post_views($postID)
            {
                $count_key = 'post_views_count';
                $count     = get_post_meta($postID, $count_key, true);
                if ($count == '') {
                    delete_post_meta($postID, $count_key);
                    add_post_meta($postID, $count_key, '0');
                    return "0";
                }
                return $count;
            }
            public function toocheke_universal_set_post_views()
            {
                if (!is_singular(['comic', 'manga_chapter'])) {
                    return; // Only run on comic or manga_chapter CPTs
                }

                $postID = get_the_ID();
                if (! $postID) {
                    return; // Safety check
                }

                // --- Theme exclusion ---
                $theme = wp_get_theme();
                $theme_names = ['Toocheke Premium', 'Toocheke'];
                if (in_array($theme->name, $theme_names, true) || in_array($theme->parent_theme, $theme_names, true)) {
                    return;
                }

                // --- Bot check ---
                $bots = ['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'wget', 'curl'];
                $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
                foreach ($bots as $bot) {
                    if (strpos($user_agent, $bot) !== false) {
                        return; // Detected bot — skip counting
                    }
                }

                // --- Cookie setup ---
                $cookie_name = 'toocheke_viewed_' . $postID;

                if (! isset($_COOKIE[$cookie_name])) {
                    $count_key = 'post_views_count';
                    $count     = (int) get_post_meta($postID, $count_key, true);

                    $count++;
                    update_post_meta($postID, $count_key, $count);

                    // Set cookie to expire in 7 days
                    setcookie(
                        $cookie_name,
                        '1',
                        time() + (7 * DAY_IN_SECONDS),
                        COOKIEPATH ?: '/',
                        COOKIE_DOMAIN,
                        is_ssl(),
                        true
                    );
                }
            }


            public function toocheke_comic_archive_template($template)
            {
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    global $post;
                    $output    = '';
                    $templates = new Toocheke_Companion_Template_Loader;
                    if (! is_post_type_archive('comic')) {
                        return $template;
                    }

                    $template = TOOCHEKE_COMPANION_PLUGIN_DIR . 'templates/content-comicdefaultarchive.php';

                    return $template;
                }
                return $template;
            }

            //Create original art page
            public function toocheke_companion_create_original_art_page_on_theme_activation()
            {

                // Set the title, template, etc
                $new_page_title    = __('Original Art', 'toocheke-companion'); // Page's title
                $new_page_content  = '';                                       // Content goes here
                $new_page_template = 'page-templates/comic-buy-original.php';  // The template to use for the page
                $page_check_query  = new WP_Query(
                    [
                        'post_type'              => 'page',
                        'title'                  => $new_page_title,
                        'post_status'            => 'all',
                        'posts_per_page'         => 1,
                        'no_found_rows'          => true,
                        'ignore_sticky_posts'    => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                        'orderby'                => 'post_date ID',
                        'order'                  => 'ASC',
                    ]
                );

                if (! empty($page_check_query->post)) {
                    $page_check = $page_check_query->post;
                } else {
                    $page_check = null;
                }

                //$page_check = get_page_by_title($new_page_title); // Check if the page already exists --Deprecated in Wordpress 6.2
                // Store the above data in an array
                $new_page = [
                    'post_type'    => 'page',
                    'post_title'   => $new_page_title,
                    'post_content' => $new_page_content,
                    'post_status'  => 'publish',
                    'post_author'  => 1,
                    'post_slug'    => 'original-art',
                ];
                // If the page doesn't already exist, create it
                if (! isset($page_check->ID)) {
                    $new_page_id = wp_insert_post($new_page);
                    if (! empty($new_page_template)) {
                        update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
                    }
                }
            }
            //Create print page
            public function toocheke_companion_create_print_page_on_theme_activation()
            {

                // Set the title, template, etc
                $new_page_title    = __('Print', 'toocheke-companion');    // Page's title
                $new_page_content  = '';                                   // Content goes here
                $new_page_template = 'page-templates/comic-buy-print.php'; // The template to use for the page
                $page_check_query  = new WP_Query(
                    [
                        'post_type'              => 'page',
                        'title'                  => $new_page_title,
                        'post_status'            => 'all',
                        'posts_per_page'         => 1,
                        'no_found_rows'          => true,
                        'ignore_sticky_posts'    => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                        'orderby'                => 'post_date ID',
                        'order'                  => 'ASC',
                    ]
                );

                if (! empty($page_check_query->post)) {
                    $page_check = $page_check_query->post;
                } else {
                    $page_check = null;
                }

                //$page_check = get_page_by_title($new_page_title); // Check if the page already exists --Deprecated in Wordpress 6.2
                // Store the above data in an array
                $new_page = [
                    'post_type'    => 'page',
                    'post_title'   => $new_page_title,
                    'post_content' => $new_page_content,
                    'post_status'  => 'publish',
                    'post_author'  => 1,
                    'post_slug'    => 'print',
                ];
                // If the page doesn't already exist, create it
                if (! isset($page_check->ID)) {
                    $new_page_id = wp_insert_post($new_page);
                    if (! empty($new_page_template)) {
                        update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
                    }
                }
            }
            public function toocheke_posted_on()
            {
                $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
                if (get_the_time('U') !== get_the_modified_time('U')) {
                    $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
                }

                $time_string = sprintf(
                    $time_string,
                    esc_attr(get_the_date(DATE_W3C)),
                    esc_html(get_the_date()),
                    '',
                    ''
                );

                $posted_on = sprintf(
                    /* translators: %s: post date. */
                    esc_html_x('Posted on %s', 'post date', 'toocheke-companion'),
                    '<a href="' . esc_url(get_permalink()) . '" rel="bookmark">' . $time_string . '</a>'
                );

                echo '<span class="posted-on">' . wp_kses_data($posted_on) . '</span>';
            }
            public function toocheke_posted_by()
            {
                $byline = sprintf(
                    /* translators: %s: post author. */
                    esc_html_x('by %s', 'post author', 'toocheke-companion'),
                    '<span class="author vcard"><a class="url fn n" href="' . esc_url(get_author_posts_url(get_the_author_meta('ID'))) . '">' . esc_html(get_the_author()) . '</a></span>'
                );

                echo '<span class="byline"> ' . wp_kses_data($byline) . '</span>';
            }
            public function toocheke_random_add_rewrite()
            {
                global $wp;
                $wp->add_query_var('random');
                $wp->add_query_var('sid');
                add_rewrite_rule('random/?$', 'index.php?random=1', 'top');
            }
            public function toocheke_random_template()
            {
                if (get_query_var('random') == 1) {
                    $args = [
                        'post_type'      => 'comic',
                        'orderby'        => 'rand',
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                    ];

                    $sid = absint(get_query_var('sid'));
                    if ($sid > 0) {
                        $args['post_parent'] = $sid;
                    }

                    $posts = get_posts($args);

                    if (! empty($posts)) {
                        $post = reset($posts);
                        $link = get_permalink($post);

                        // Append sid to the permalink if provided
                        if ($sid > 0) {
                            $link = add_query_arg('sid', $sid, $link);
                        }
                        if (get_option('toocheke-scroll-past-header') && 1 == get_option('toocheke-scroll-past-header')) {
                            $link = $link . '#main';
                        }

                        wp_redirect($link, 307);
                        exit;
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
            public function toocheke_extend_search($search, $query)
            {
                global $wpdb;

                if ($query->is_main_query() && ! empty($query->query['s'])) {
                    $sql = "
                or exists (
                    select * from {$wpdb->postmeta} where post_id={$wpdb->posts}.ID
                    and meta_key in ('desktop_comic_editor', 'comic_blog_post_editor', 'mobile_comic_2nd_language_editor', 'comic_2nd_language_blog_post_editor', 'desktop_comic_2nd_language_editor', 'transcript')
                    and meta_value like %s
                )
            ";
                    $like   = '%' . $wpdb->esc_like($query->query['s']) . '%';
                    $search = preg_replace(
                        "#\({$wpdb->posts}.post_title LIKE [^)]+\)\K#",
                        $wpdb->prepare($sql, $like, $like),
                        $search
                    );
                }

                return $search;
            }
            public function toocheke_feed_post_status($query)
            {
                $combine_rss = get_option('toocheke-comics-to-main-rss') && 1 == get_option('toocheke-comics-to-main-rss');
                if (! $query->is_feed() || ! $combine_rss) {
                    return; // this isn't a feed, abort!
                }
                $query->set('post_status', 'publish'); // we only want published posts, no drafts or private
            }

            public function toocheke_feed_request($qv)
            {
                $combine_rss = get_option('toocheke-comics-to-main-rss') && 1 == get_option('toocheke-comics-to-main-rss');
                if (isset($qv['feed']) && ! isset($qv['post_type']) && $combine_rss) {
                    $qv['post_type'] = ['post', 'comic'];
                }

                return $qv;
            }
            public function toocheke_add_metadata_to_rss($content)
            {
                $combine_rss = get_option('toocheke-comics-to-main-rss') && 1 == get_option('toocheke-comics-to-main-rss');
                if (is_feed() && $combine_rss) {
                    global $post;
                    $postid = $post->ID;
                    if ($postid) {
                        $custom_metadata = get_post_meta($postid, 'comic_blog_post_editor', true);
                        if ($custom_metadata !== '') {
                            // Display custom field data below content
                            $content = $content . "<br /><br /><div>" . $custom_metadata . "</div>";
                        } else {
                            $content = $content;
                        }
                    }
                }
                return $content;
            }

            public function toocheke_get_paypal_currencies()
            {
                return [
                    'AUD' => esc_html__('Australian Dollar', 'toocheke-companion'),
                    'BRL' => esc_html__('Brazilian Real', 'toocheke-companion'),
                    'CAD' => esc_html__('Canadian Dollar', 'toocheke-companion'),
                    'CZK' => esc_html__('Czech Koruna', 'toocheke-companion'),
                    'DKK' => esc_html__('Danish Krone', 'toocheke-companion'),
                    'EUR' => esc_html__('Euro', 'toocheke-companion'),
                    'HKD' => esc_html__('Hong Kong Dollar', 'toocheke-companion'),
                    'HUF' => esc_html__('Hungarian Forint', 'toocheke-companion'),
                    'INR' => esc_html__('Indian Rupee', 'toocheke-companion'),
                    'ILS' => esc_html__('Israeli New Shekel', 'toocheke-companion'),
                    'JPY' => esc_html__('Japanese Yen', 'toocheke-companion'),
                    'MYR' => esc_html__('Malaysian Ringgit', 'toocheke-companion'),
                    'MXN' => esc_html__('Mexican Peso', 'toocheke-companion'),
                    'TWD' => esc_html__('New Taiwan Dollar', 'toocheke-companion'),
                    'NZD' => esc_html__('New Zealand Dollar', 'toocheke-companion'),
                    'NOK' => esc_html__('Norwegian Krone', 'toocheke-companion'),
                    'PHP' => esc_html__('Philippine Peso', 'toocheke-companion'),
                    'PLN' => esc_html__('Polish Zloty', 'toocheke-companion'),
                    'GBP' => esc_html__('British Pound', 'toocheke-companion'),
                    'RUB' => esc_html__('Russian Ruble', 'toocheke-companion'),
                    'SGD' => esc_html__('Singapore Dollar', 'toocheke-companion'),
                    'SEK' => esc_html__('Swedish Krona', 'toocheke-companion'),
                    'CHF' => esc_html__('Swiss Franc', 'toocheke-companion'),
                    'THB' => esc_html__('Thai Baht', 'toocheke-companion'),
                    'USD' => esc_html__('United States Dollar', 'toocheke-companion'),
                    'AED' => esc_html__('United Arab Emirates Dirham', 'toocheke-companion'),
                    'CNY' => esc_html__('Chinese Yuan Renminbi', 'toocheke-companion'),
                    'ZAR' => esc_html__('South African Rand', 'toocheke-companion'),
                ];
            }
            public function toocheke_get_currencies()
            {
                $currencies = [
                    'AED' => '&#x62f;.&#x625;',
                    'AFN' => '&#x60b;',
                    'ALL' => 'L',
                    'AMD' => 'AMD',
                    'ANG' => '&fnof;',
                    'AOA' => 'Kz',
                    'ARS' => '&#36;',
                    'AUD' => '&#36;',
                    'AWG' => '&fnof;',
                    'AZN' => 'AZN',
                    'BAM' => 'KM',
                    'BBD' => '&#36;',
                    'BDT' => '&#2547;&nbsp;',
                    'BGN' => '&#1083;&#1074;.',
                    'BIF' => 'Fr',
                    'BMD' => '&#36;',
                    'BND' => '&#36;',
                    'BOB' => 'Bs.',
                    'BRL' => '&#82;&#36;',
                    'BSD' => '&#36;',
                    'BWP' => 'P',
                    'BYR' => 'Br',
                    'BZD' => '&#36;',
                    'CAD' => '&#36;',
                    'CDF' => 'Fr',
                    'CHF' => '&#67;&#72;&#70;',
                    'CLP' => '&#36;',
                    'CNY' => '&yen;',
                    'COP' => '&#36;',
                    'CRC' => '&#x20a1;',
                    'CVE' => '&#36;',
                    'CZK' => '&#75;&#269;',
                    'DJF' => 'Fr',
                    'DKK' => 'DKK',
                    'DOP' => 'RD&#36;',
                    'DZD' => '&#x62f;.&#x62c;',
                    'EGP' => 'EGP',
                    'ETB' => 'Br',
                    'EUR' => '&euro;',
                    'FJD' => '&#36;',
                    'FKP' => '&pound;',
                    'GBP' => '&pound;',
                    'GEL' => '&#x10da;',
                    'GIP' => '&pound;',
                    'GMD' => 'D',
                    'GNF' => 'Fr',
                    'GTQ' => 'Q',
                    'GYD' => '&#36;',
                    'HKD' => '&#36;',
                    'HNL' => 'L',
                    'HRK' => 'Kn',
                    'HTG' => 'G',
                    'HUF' => '&#70;&#116;',
                    'IDR' => 'Rp',
                    'ILS' => '&#8362;',
                    'INR' => '&#8377;',
                    'ISK' => 'Kr.',
                    'JMD' => '&#36;',
                    'JPY' => '&yen;',
                    'KES' => 'KSh',
                    'KGS' => '&#x43b;&#x432;',
                    'KHR' => '&#x17db;',
                    'KMF' => 'Fr',
                    'KRW' => '&#8361;',
                    'KYD' => '&#36;',
                    'KZT' => 'KZT',
                    'LAK' => '&#8365;',
                    'LBP' => '&#x644;.&#x644;',
                    'LKR' => '&#xdbb;&#xdd4;',
                    'LRD' => '&#36;',
                    'LSL' => 'L',
                    'MAD' => '&#x62f;. &#x645;.',
                    'MDL' => 'L',
                    'MGA' => 'Ar',
                    'MKD' => '&#x434;&#x435;&#x43d;',
                    'MMK' => 'Ks',
                    'MNT' => '&#x20ae;',
                    'MOP' => 'P',
                    'MRO' => 'UM',
                    'MUR' => '&#x20a8;',
                    'MVR' => '.&#x783;',
                    'MWK' => 'MK',
                    'MXN' => 'MXN',
                    'MYR' => '&#82;&#77;',
                    'MZN' => 'MT',
                    'NAD' => '&#36;',
                    'NGN' => '&#8358;',
                    'NIO' => 'C&#36;',
                    'NOK' => '&#107;&#114;',
                    'NPR' => '&#8360;',
                    'NZD' => '&#36;',
                    'PAB' => 'B/.',
                    'PEN' => 'S/.',
                    'PGK' => 'K',
                    'PHP' => '&#8369;',
                    'PKR' => '&#8360;',
                    'PLN' => '&#122;&#322;',
                    'PYG' => '&#8370;',
                    'QAR' => '&#x631;.&#x642;',
                    'RON' => 'lei',
                    'RSD' => '&#x434;&#x438;&#x43d;.',
                    'RUB' => '&#8381;',
                    'RWF' => 'Fr',
                    'SAR' => '&#x631;.&#x633;',
                    'SBD' => '&#36;',
                    'SCR' => '&#x20a8;',
                    'SEK' => '&#107;&#114;',
                    'SGD' => 'S&#36;',
                    'SHP' => '&pound;',
                    'SLL' => 'Le',
                    'SOS' => 'Sh',
                    'SRD' => '&#36;',
                    'STD' => 'Db',
                    'SZL' => 'L',
                    'THB' => '&#3647;',
                    'TJS' => '&#x405;&#x41c;',
                    'TOP' => 'T&#36;',
                    'TRY' => '&#8378;',
                    'TTD' => '&#36;',
                    'TWD' => '&#78;&#84;&#36;',
                    'TZS' => 'Sh',
                    'UAH' => '&#8372;',
                    'UGX' => 'UGX',
                    'USD' => '&#36;',
                    'UYU' => '&#36;',
                    'UZS' => 'UZS',
                    'VND' => '&#8363;',
                    'VUV' => 'Vt',
                    'WST' => 'T',
                    'XAF' => 'Fr',
                    'XCD' => '&#36;',
                    'XOF' => 'Fr',
                    'XPF' => 'Fr',
                    'YER' => '&#xfdfc;',
                    'ZAR' => '&#82;',
                    'ZMW' => 'ZK',
                ];

                $currencies = apply_filters('toocheke_get_currencies', $currencies);

                return $currencies;
            }
            public function toocheke_get_currency_symbol($currency = '')
            {

                if (! $currency) {

                    // If no currency is passed then default it to USD.
                    $currency = 'USD';
                }

                $currency   = strtoupper($currency);
                $currencies = $this->toocheke_get_currencies();

                $symbols = apply_filters('toocheke_currency_symbols', $currencies);

                $currency_symbol = isset($symbols[$currency]) ? $symbols[$currency] : '';

                return apply_filters('toocheke_currency_symbol', $currency_symbol, $currency);
            }
            /*
        * Alt hover for images
        */
            // Shared logic to insert hovertext into image tags
            public function toocheke_add_hovertext_to_images_in_html($html, $hovertext)
            {
                if (empty($html) || empty($hovertext)) {
                    return '';
                }

                libxml_use_internal_errors(true);
                $dom      = new DOMDocument();
                $encoding = '<?xml encoding="utf-8" ?>';
                $dom->loadHTML($encoding . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                $images = $dom->getElementsByTagName('img');
                foreach ($images as $img) {
                    if (! $img->hasAttribute('title')) {
                        $img->setAttribute('title', $hovertext);
                    }
                    // Always overwrite alt attribute
                    $img->setAttribute('alt', $hovertext);
                }

                $modified = $dom->saveHTML();
                $modified = preg_replace('/^<\?xml.*?\?>/', '', $modified);

                return $modified;
            }

            // Filter for the_content (only for 'comic' post type)
            public function toocheke_add_comic_hovertext_to_content($content)
            {
                // Get home layout setting (with 'default' as fallback)
                $home_layout = get_theme_mod('home_layout_setting', 'default');
                // Determine if comics should be displayed on the home page
                $display_comic_on_home_page = in_array($home_layout, ['alt-1', 'alt-2', 'alt-4']);

                // Check if we are not on a singular comic, in the main query, and on the homepage with the correct layout
                if (
                    ! is_singular('comic') &&                    // Not a singular 'comic'
                    ! in_the_loop() &&                           // Not in the loop
                    ! is_main_query() &&                         // Not the main query
                    ! (is_home() && $display_comic_on_home_page) // Not the homepage with comics enabled
                ) {
                    return $content;
                }

                $post_id = 0;

                if (is_singular('comic')) {
                    $post_id = get_the_ID();
                }
                if (is_home()) {
                    // Access the global $post object
                    global $post;

                    // Check if this is a 'comic' post type
                    if ('comic' === $post->post_type) {
                        // Get the post ID
                        $post_id = $post->ID;
                    }
                }

                $hovertext = get_post_meta($post_id, 'comic-hovertext', true);

                if (empty($hovertext)) {
                    return $content; // No hovertext, return nothing
                }

                return $this->toocheke_add_hovertext_to_images_in_html($content, $hovertext);
            }

            // Filter for get_post_metadata (only for 'comic' post type)
            public function toocheke_add_hovertext_to_desktop_comic_editor_meta($value, $object_id, $meta_key, $single)
            {
                if ($meta_key !== 'desktop_comic_editor' || ! $single) {
                    return $value;
                }

                $comic_post = get_post($object_id);
                if (! $comic_post || $comic_post->post_type !== 'comic') {
                    return $value;
                }

                $hovertext = get_post_meta($object_id, 'comic-hovertext', true);
                if (empty($hovertext)) {
                    return $value; // No hovertext, return nothing
                }

                // Prevent infinite recursion
                remove_filter('get_post_metadata', [$this, 'toocheke_add_hovertext_to_desktop_comic_editor_meta'], 10);

                $content = get_post_meta($object_id, 'desktop_comic_editor', true);

                // Re-add the filter
                add_filter('get_post_metadata', [$this, 'toocheke_add_hovertext_to_desktop_comic_editor_meta'], 10, 4);

                return $this->toocheke_add_hovertext_to_images_in_html($content, $hovertext);
            }
            public function toocheke_companion_disable_block_editor_for_post_types($use_block_editor, $post_type)
            {
                if ($post_type === 'series') {
                    return false; // Disable block editor for 'series'
                }
                return $use_block_editor;
            }
            private function toocheke_render_thumbnail($post_id)
            {
                $post_thumbnail_id = get_post_thumbnail_id($post_id);
                if ($post_thumbnail_id) {
                    $img = wp_get_attachment_image_src($post_thumbnail_id, 'featured_preview');
                    $src = $img ? $img[0] : plugins_url('toocheke-companion/img/no-image.png');
                } else {
                    $src = plugins_url('toocheke-companion/img/no-image.png');
                }
                echo '<img src="' . esc_url($src) . '" class="comic-thumbnail" />';
            }

            public function toocheke_add_manga_reader_body_class($classes)
            {
                if (is_singular('manga_chapter')) {
                    $classes[] = 'manga-reader';
                }

                if (is_singular('manga_volume') && isset($_GET['reader'])) {
                    $classes[] = 'manga-reader';
                }

                return $classes;
            }
            //Manga Sorting functionality

            // --- SERIES ---
            public function toocheke_manga_series_sortable_columns($columns)
            {
                $columns['manga_series_likes'] = 'manga_series_likes';
                return $columns;
            }

            public function toocheke_manga_series_sort($wp_query)
            {
                global $pagenow;
                if (! is_admin() || $pagenow !== 'edit.php') {
                    return;
                }

                if (($wp_query->get('orderby') === 'manga_series_likes') && $wp_query->get('post_type') === 'manga_series') {
                    $wp_query->set('meta_query', [
                        'relation' => 'OR',
                        ['key' => '_post_like_count', 'compare' => 'NOT EXISTS'],
                        ['key' => '_post_like_count', 'value' => 0, 'compare' => '>='],
                    ]);
                    $wp_query->set('orderby', 'meta_value_num');
                    $wp_query->set('meta_type', 'NUMERIC');
                }
            }

            // --- VOLUME ---
            public function toocheke_manga_volume_sortable_columns($columns)
            {
                $columns['manga_volume_likes'] = 'manga_volume_likes';
                return $columns;
            }

            public function toocheke_manga_volume_sort($wp_query)
            {
                global $pagenow;
                if (! is_admin() || $pagenow !== 'edit.php') {
                    return;
                }

                if (($wp_query->get('orderby') === 'manga_volume_likes') && $wp_query->get('post_type') === 'manga_volume') {
                    $wp_query->set('meta_query', [
                        'relation' => 'OR',
                        ['key' => '_post_like_count', 'compare' => 'NOT EXISTS'],
                        ['key' => '_post_like_count', 'value' => 0, 'compare' => '>='],
                    ]);
                    $wp_query->set('orderby', 'meta_value_num');
                    $wp_query->set('meta_type', 'NUMERIC');
                }
            }

            // --- CHAPTER ---
            public function toocheke_manga_chapter_sortable_columns($columns)
            {
                $columns['manga_chapter_views'] = 'manga_chapter_views';
                $columns['manga_chapter_likes'] = 'manga_chapter_likes';
                return $columns;
            }

            public function toocheke_manga_chapter_sort($wp_query)
            {
                global $pagenow;
                if (! is_admin() || $pagenow !== 'edit.php') {
                    return;
                }

                if ($wp_query->get('post_type') === 'manga_chapter') {
                    switch ($wp_query->get('orderby')) {
                        case 'manga_chapter_views':
                            $wp_query->set('meta_query', [
                                'relation' => 'OR',
                                ['key' => 'post_views_count', 'compare' => 'NOT EXISTS'],
                                ['key' => 'post_views_count', 'value' => 0, 'compare' => '>='],
                            ]);
                            $wp_query->set('orderby', 'meta_value_num');
                            $wp_query->set('meta_type', 'NUMERIC');
                            break;

                        case 'manga_chapter_likes':
                            $wp_query->set('meta_query', [
                                'relation' => 'OR',
                                ['key' => '_post_like_count', 'compare' => 'NOT EXISTS'],
                                ['key' => '_post_like_count', 'value' => 0, 'compare' => '>='],
                            ]);
                            $wp_query->set('orderby', 'meta_value_num');
                            $wp_query->set('meta_type', 'NUMERIC');
                            break;
                    }
                }
            }
        }

        /**
         * Template loader.
         *
         *
         */

        define('TOOCHEKE_COMPANION_PLUGIN_DIR', plugin_dir_path(__FILE__));
        if (! class_exists('Gamajo_Template_Loader')) {
            require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/class-gamajo-template-loader.php';
        }
        require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/class-toocheke-companion-template-loader.php';
        require TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/toocheke-companion-template-functions.php';

        $Toocheke_Companion_Comic_Features = new Toocheke_Companion_Comic_Features();
        $Toocheke_Companion_Comic_Features->init();

        // Now, include your block registration file
        // Ensure the path is correct, based on where you put 'toocheke-companion-blocks.php'
        require_once TOOCHEKE_COMPANION_PLUGIN_DIR . 'inc/toocheke-companion-blocks.php';

?>