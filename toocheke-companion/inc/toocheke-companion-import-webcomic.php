<?php
    $imported = 0;
    function toocheke_import_from_webcomic()
    {
        global $imported;
        global $wpdb;
        try {
            $webcomic_data = get_option('webcomic');

            if (empty($webcomic_data) || empty($webcomic_data['collections'])) {
                echo '<div class="notice notice-error is-dismissible"><p>No Webcomic collections found in your database.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
                return;
            }

            $collections_count = count($webcomic_data['collections']); // Count the collections

            //import collections

            foreach ($webcomic_data['collections'] as $collection_key) {
                $collection = get_option($collection_key); // e.g., get_option('webcomic1')

                if (empty($collection)) {
                    continue;
                }

                // Check if a series post already exists with this slug
                $existing_series = get_page_by_path($collection['slug'], OBJECT, 'series');
                if ($existing_series) {
                    continue; // Skip existing
                }

                $postarr = [
                    'post_title'   => $collection['name'],
                    'post_name'    => $collection['slug'],
                    'post_content' => $collection['description'],
                    'post_status'  => 'publish',
                    'post_type'    => 'series',
                ];

                $post_id = wp_insert_post($postarr);

                if (is_wp_error($post_id)) {
                    error_log('Failed to insert series: ' . $collection['name']);
                    continue;
                }

                // Assign featured image if 'media' exists and is a valid attachment ID
                if (! empty($collection['media']) && get_post($collection['media'])) {
                    set_post_thumbnail($post_id, $collection['media']);
                }
            }
            //import characters and storylines
            toocheke_import_webcomic_taxonomies($webcomic_data);
            //import comic posts
            toocheke_import_comic_posts($webcomic_data, $collections_count);

            echo '<div class="notice notice-success is-dismissible"><p><b>Success!</b>  Comics have been successfully imported from <b>Webcomic</b>!</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
            $imported = 1;
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible"><p>Error encountered while importing from <b>Webcomic</b>. Try again. If issue persists, contact us.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
            $imported = 0;
        }

    }
    function toocheke_import_webcomic_taxonomies($webcomic_data)
    {
        $webcomic_data = get_option('webcomic');

        if (empty($webcomic_data) || empty($webcomic_data['collections'])) {
            echo '<div class="notice notice-error"><p>No Webcomic collections found for taxonomy import.</p></div>';
            return;
        }

        foreach ($webcomic_data['collections'] as $collection_key) {
            $collection = get_option($collection_key);

            if (empty($collection) || empty($collection['taxonomies'])) {
                continue;
            }

            foreach ($collection['taxonomies'] as $taxonomy_name) {
                if (! taxonomy_exists($taxonomy_name)) {
                    continue;
                }

                $terms = get_terms([
                    'taxonomy'   => $taxonomy_name,
                    'hide_empty' => false,
                ]);

                if (is_wp_error($terms) || empty($terms)) {
                    continue;
                }

                // Determine Toocheke target taxonomy and corresponding image meta key
                if (strpos($taxonomy_name, '_storyline') !== false) {
                    $target_taxonomy = 'chapters';
                    $target_meta_key = 'chapter-image-id';
                } elseif (strpos($taxonomy_name, '_character') !== false) {
                    $target_taxonomy = 'comic_characters';
                    $target_meta_key = 'character-image-id';
                } else {
                    continue;
                }

                foreach ($terms as $term) {
                    // Skip if term already exists in target taxonomy
                    if (term_exists($term->slug, $target_taxonomy)) {
                        continue;
                    }

                    // Insert term into target taxonomy
                    $new_term = wp_insert_term($term->name, $target_taxonomy, [
                        'slug'        => $term->slug,
                        'description' => $term->description,
                    ]);

                    if (is_wp_error($new_term)) {
                        continue;
                    }

                    // Retrieve Webcomic term image (attachment ID)
                    $image_id = get_term_meta($term->term_id, 'webcomic_media', true);
                    if (! empty($image_id) && is_numeric($image_id)) {
                        add_term_meta($new_term['term_id'], $target_meta_key, $image_id, true);
                    }
                }
            }
        }

    }

    function toocheke_import_comic_posts($webcomic_data, $no_of_series = 1)
    {
        global $wpdb;
        //set to true for now. May get the value from user input later
        $update_content_and_blog = true;
        $series_id               = null;
        foreach ($webcomic_data['collections'] as $collection_key) {

            $collection = get_option($collection_key);
            if (empty($collection)) {
                continue;
            }

            $collection_post_type = $collection['id'];   // e.g., 'webcomic1'
            $series_slug          = $collection['slug']; // e.g., 'comic-series-1'

            // Get the series post only if we have more than one series
            if ($no_of_series > 1) {
                $series_post = get_page_by_path($series_slug, OBJECT, 'series');
                if (! $series_post) {
                    error_log("Series not found for slug: $series_slug");
                    continue;
                }

                $series_id = $series_post->ID;
            }

            // Define original taxonomies
            $storyline_tax = $collection_post_type . '_storyline';
            $character_tax = $collection_post_type . '_character';

            // Get all comic posts from this collection
            $comics = get_posts([
                'post_type'   => $collection_post_type,
                'numberposts' => -1,
                'post_status' => 'publish',
            ]);

            foreach ($comics as $comic_post) {
                // Avoid duplicates
                $existing = get_posts([
                    'post_type'   => 'comic',
                    'post_parent' => $series_id,
                    'title'       => $comic_post->post_title,
                    'numberposts' => 1,
                ]);
                if (! empty($existing)) {
                    continue;
                }

                $new_post = [
                    'post_title'   => $comic_post->post_title,
                    'post_content' => $comic_post->post_content,
                    'post_status'  => 'publish',
                    'post_type'    => 'comic',
                    'post_date'    => $comic_post->post_date,
                    'post_parent'  => $series_id,
                ];

                $new_post_id = wp_insert_post($new_post);
                if (is_wp_error($new_post_id)) {
                    error_log("Failed to insert comic: " . $comic_post->post_title);
                    continue;
                }

                // Copy featured image
                // Try to find an image with meta_key = 'webcomic_post' and meta_value = $comic_post->ID
                $thumbnail_query = new WP_Query([
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'posts_per_page' => 1,
                    'meta_query'     => [
                        [
                            'key'   => 'webcomic_post',
                            'value' => $comic_post->ID,
                        ],
                    ],
                ]);

                if ($thumbnail_query->have_posts()) {
                    $thumbnail_id = $thumbnail_query->posts[0]->ID;
                    set_post_thumbnail($new_post_id, $thumbnail_id);
                }

                //copy content to blog post editor and then copy the featured image to content area
                if ($update_content_and_blog) {
                    // Store original content in custom field
                    $formatted_content = wpautop($comic_post->post_content);
                    update_post_meta($new_post_id, 'comic_blog_post_editor', $formatted_content);

                    // Replace post content with full-size featured image HTML
                    if (isset($thumbnail_id)) {
                        $image_html = wp_get_attachment_image($thumbnail_id, 'full');
                        wp_update_post([
                            'ID'           => $new_post_id,
                            'post_content' => $image_html,
                        ]);
                    }

                }

                // Copy meta
                $meta = get_post_meta($comic_post->ID);
                foreach ($meta as $key => $values) {
                    foreach ($values as $value) {
                        add_post_meta($new_post_id, $key, maybe_unserialize($value));
                    }
                }

                // Assign storyline terms to 'chapters'
                $storyline_terms = wp_get_object_terms($comic_post->ID, $storyline_tax, ['fields' => 'slugs']);
                if (! empty($storyline_terms) && ! is_wp_error($storyline_terms)) {
                    wp_set_object_terms($new_post_id, $storyline_terms, 'chapters');
                }

                // Assign character terms to 'comic_characters'
                $character_terms = wp_get_object_terms($comic_post->ID, $character_tax, ['fields' => 'slugs']);
                if (! empty($character_terms) && ! is_wp_error($character_terms)) {
                    wp_set_object_terms($new_post_id, $character_terms, 'comic_characters');
                }
            }
        }

    }

    // Catch the return $_POST and do something with them.
    if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'toocheke-import')) {
        toocheke_import_from_webcomic();
    }

?>
<div class="wrap">
<h2><?php _e('Import From Webcomic', 'toocheke-companion'); ?></h2>
<?php
    global $imported;
    if ($imported !== 1):
?>
  <div class="notice notice-warning is-dismissible">
	<p><b>Please note!</b>  Only collections, comics, characters, and storylines will be imported. Transcription posts will not be included. </p>
<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
<?php
    endif;
?>

	<h3><?php esc_html_e('Need to import your comics from Webcomic?', 'toocheke-companion'); ?></h3>
<p>
<?php esc_html_e('Simply click the import button below. Make sure not to click the button more than once otherwise the comics will be imported multiple times!', 'toocheke-companion'); ?>
</p>
<form method="post" id="frmToochekeImport" name="template">
<?php wp_nonce_field('toocheke-import')?>

<p class="submit" style="margin-left: 10px;">
	<input type="submit" class="button-primary" value="<?php _e('Import', 'toocheke-companion')?>" />
	<input type="hidden" name="action" value="tc-import" />
</p>
</form>

</form>