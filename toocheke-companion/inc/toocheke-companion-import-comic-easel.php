<?php
    $imported     = 0;
    $imported_tax = 0;

    // Append images to post from post's featured image
    function toocheke_companion_import_comic_easel_featured_images()
    {
        global $imported;
        global $wpdb;
        $comic_posts = get_posts(['post_type' => 'comic', 'numberposts' => -1]);
        try {

            //import comics
            foreach ($comic_posts as $comic_post):
                $post_content = $comic_post->post_content;

                $post_id = $comic_post->ID;
                if (empty(get_post_meta($post_id, 'comic_blog_post_editor', true))) {
                    if (! empty($post_content)) {
                        update_post_meta($post_id, 'comic_blog_post_editor', $post_content);

                    }

                    if (has_post_thumbnail($comic_post->ID)) {
                        $comic_image = get_the_post_thumbnail($post_id, 'full');
                        if (! empty($comic_image)) {
                            $table            = $wpdb->posts;
                            $post_type        = 'comic';
                            $new_post_content = $comic_image;
                            $affected         = $wpdb->query(
                                $wpdb->prepare("UPDATE {$table} SET post_content = %s WHERE post_type = %s AND ID = %s"
                                    , $new_post_content
                                    , $post_type
                                    , $post_id
                                )
                            );

                        }
                    }
                }

            endforeach;

            //update locations/tags/characters
            toocheke_convert_taxonomy('post_tag', 'comic_tags');
            toocheke_convert_taxonomy('locations', 'comic_locations');
            toocheke_convert_taxonomy('characters', 'comic_characters');

            //Success!
            echo '<div class="notice notice-success is-dismissible"><p><b>Success!</b>  Comics have been successfully imported from <b>Comic Easel</b>!</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
            $imported = 1;
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible"><p>Error encountered while importing your comics. Try again. If issue persists, contact us.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
            $imported = 0;
        }

    }
    function toocheke_convert_taxonomy($old_tax, $new_tax)
    {

        global $wpdb;
        $table_name  = $wpdb->prefix . 'term_taxonomy';
        $data_update = ['taxonomy' => $new_tax];
        $data_where  = ['taxonomy' => $old_tax];
        $wpdb->update($table_name, $data_update, $data_where);
    }

    // Catch the return $_POST and do something with them.
    if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'toocheke-import')) {
        toocheke_companion_import_comic_easel_featured_images();
    }
    if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'toocheke-import-tax')) {
        global $imported_tax;
        try {
            toocheke_convert_taxonomy('post_tag', 'comic_tags');
            toocheke_convert_taxonomy('locations', 'comic_locations');
            toocheke_convert_taxonomy('characters', 'comic_characters');
            echo '<div class="notice notice-success is-dismissible"><p><b>Success!</b>  Characters/Locations/Tags have been successfully imported from Comic Easel!</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
            $imported_tax = 1;
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible"><p>Error encountered while importing your characters/locations/tags. Try again. If issue persists, contact us.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
            $imported_tax = 0;
        }
    }
?>
<div class="wrap">
<h2><?php _e('Import From Comic Easel', 'toocheke-companion'); ?></h2>
<?php
    global $imported;
    if ($imported !== 1):
?>
  <div class="notice notice-warning is-dismissible">
	<p><b>Please note!</b>  If you are only using ComicPress(without the Comic Easel plugin) you will first need to migrate from <b>ComicPress</b> to <b>Comic Easel</b>. Once that is done you can import the comics into <b>Toocheke</b>. These tutorials will walk you through the process of migrating from ComicPress to Comic Easel: <a href="https://www.youtube.com/watch?v=30hf7kz2XOs" target="_blank">Part 1</a>, <a href="https://www.youtube.com/watch?v=UpOYUJFaTmU" target="_blank">Part 2</a>, <a href="https://www.youtube.com/watch?v=5su82XPgQ50" target="_blank">Part 3</a>, <a href="https://www.youtube.com/watch?v=9wW6u08dZZs" target="_blank">Part 4</a> and <a href="https://www.youtube.com/watch?v=7co2FcbhLGo" target="_blank">Part 5</a> </p>
<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
<?php
    endif;
?>

	<h3><?php esc_html_e('Need to import your comics from Comic Easel?', 'toocheke-companion'); ?></h3>
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
<h3><?php esc_html_e('Need to import Characters, Locations and Tags from Comic Easel? ', 'toocheke-companion'); ?></h3>
<p>
<?php esc_html_e('Simply click the import button below.', 'toocheke-companion'); ?>
</p>
<form method="post" id="frmToochekeImportTax" name="template">
<?php wp_nonce_field('toocheke-import-tax')?>

<p class="submit" style="margin-left: 10px;">
	<input type="submit" class="button-primary" value="<?php _e('Import', 'toocheke-companion')?>" />
	<input type="hidden" name="action" value="tc-import-tax" />
</p>
</form>