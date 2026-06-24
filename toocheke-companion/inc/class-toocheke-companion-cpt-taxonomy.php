<?php
/**
 * Registers the comic, series, and manga (series/volume/chapter) custom post
 * types and the 'chapters', 'collections', 'genres', and other custom
 * taxonomies. Also handles rewrite rules and the one-time creation of
 * landing pages (collection/chapter/character/genre archive pages) on theme
 * activation.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_CPT_Taxonomy
{
    /**
     * Creates a landing page (used by the genres, chapters, collections,
     * characters, original-art, and print archive pages) on theme
     * activation, if a page with the given title doesn't already exist.
     *
     * @param string $title    The page title (already translated).
     * @param string $template The page template file to assign, e.g. 'page-templates/series-genres.php'.
     * @param string $slug     The page slug.
     */
    private function toocheke_create_landing_page_on_theme_activation($title, $template, $slug)
    {
        $new_page_title    = $title;
        $new_page_content  = '';
        $new_page_template = $template;
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

        $new_page = [
            'post_type'    => 'page',
            'post_title'   => $new_page_title,
            'post_content' => $new_page_content,
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_slug'    => $slug,
        ];
        // If the page doesn't already exist, create it
        if (! isset($page_check->ID)) {
            $new_page_id = wp_insert_post($new_page);
            if (! empty($new_page_template)) {
                update_post_meta($new_page_id, '_wp_page_template', $new_page_template);
            }
        }
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
            'has_archive'         => 'manga',
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-toocheke-companion',
            'rewrite'             => [
                'slug'  => 'manga',
                'with_front' => false,
                'feeds' => true,
            ],
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

    //Genres page
    public function toocheke_companion_create_genre_page_on_theme_activation()
    {
        $this->toocheke_create_landing_page_on_theme_activation(__('Genres', 'toocheke-companion'), 'page-templates/series-genres.php', 'genres');
    }


            //Chapters page
            public function toocheke_companion_create_chapter_page_on_theme_activation()
            {
                $this->toocheke_create_landing_page_on_theme_activation(__('Chapters', 'toocheke-companion'), 'page-templates/comic-chapters.php', 'chapters');
            }


            //Collections page
            public function toocheke_companion_create_collection_page_on_theme_activation()
            {
                $this->toocheke_create_landing_page_on_theme_activation(__('Collections', 'toocheke-companion'), 'page-templates/comic-collections.php', 'collections');
            }


            //Characters page
            public function toocheke_companion_create_character_page_on_theme_activation()
            {
                $this->toocheke_create_landing_page_on_theme_activation(__('Characters', 'toocheke-companion'), 'page-templates/comic-characters.php', 'characters');
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

            //Create original art page
            public function toocheke_companion_create_original_art_page_on_theme_activation()
            {
                $this->toocheke_create_landing_page_on_theme_activation(__('Original Art', 'toocheke-companion'), 'page-templates/comic-buy-original.php', 'original-art');
            }


            //Create print page
            public function toocheke_companion_create_print_page_on_theme_activation()
            {
                $this->toocheke_create_landing_page_on_theme_activation(__('Print', 'toocheke-companion'), 'page-templates/comic-buy-print.php', 'print');
            }


            public function toocheke_random_add_rewrite()
            {
                global $wp;
                $wp->add_query_var('random');
                $wp->add_query_var('sid');
                add_rewrite_rule('random/?$', 'index.php?random=1', 'top');
            }

            /**
             * Register rewrite rule for series feed
             */
            public function toocheke_series_feed_rewrite_rule() {
                add_rewrite_rule(
                    '^series/([^/]+)/feed/?$',
                    'index.php?post_type=series&name=$matches[1]&series_feed=1',
                    'top'
                );
            }

            /**
             * Register rewrite rule for manga series feed
             */
            public function toocheke_manga_series_feed_rewrite_rule() {
                add_rewrite_rule(
                    '^manga_series/([^/]+)/feed/?$',
                    'index.php?post_type=manga_series&name=$matches[1]&manga_series_feed=1',
                    'top'
                );
            }

            public function toocheke_companion_disable_block_editor_for_post_types($use_block_editor, $post_type)
            {
                if ($post_type === 'series') {
                    return false; // Disable block editor for 'series'
                }
                return $use_block_editor;
            }

}
