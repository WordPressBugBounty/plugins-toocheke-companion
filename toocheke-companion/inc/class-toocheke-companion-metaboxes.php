<?php
/**
 * Registers and saves every metabox used on comic, series, and manga
 * series/volume/chapter edit screens: alt text, transcript, audio, bilingual
 * fields, age verification, hero/background images, pricing, and the
 * ComicScout/social-share image fields.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Metaboxes
{
            public function toocheke_age_verification_message()
            {
                echo 'This determines whether a browser cookie gets saved for 30 days when the user selects the "Yes" button. They will not be able to access the content on your website if they click "No".';
            }

            public function toocheke_age_verification_checkbox()
            {
                $this->toocheke_render_checkbox_field('toocheke-age-verification', false);
            }

            /**
             * Custom Title
             */
            public function toocheke_comic_title_2nd_language_meta_box()
            {
                if (! get_option('toocheke-bilingual-display')) {
                    return;
                }
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
                if (! get_option('toocheke-comic-layout-devices')) {
                    return;
                }
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
        $data = $this->toocheke_sanitize_rich_text_with_embeds($_POST['comic_blog_post_editor']);
        update_post_meta($post_id, 'comic_blog_post_editor', $data);
    }
}

            /**
             * Extra WYSIWYG meta boxy editor for comics
             */
            //This function initializes the meta box.
            public function toocheke_2nd_language_mobile_comic_editor_meta_box()
            {
                if (! get_option('toocheke-bilingual-display')) {
                    return;
                }
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
                if (! get_option('toocheke-bilingual-display')) {
                    return;
                }
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
        $data = $this->toocheke_sanitize_rich_text_with_embeds($_POST['comic_2nd_language_blog_post_editor']);
        update_post_meta($post_id, 'comic_2nd_language_blog_post_editor', $data);
    }
}

            /**
             * Extra WYSIWYG meta boxy editor for comics
             */
            //This function initializes the meta box.
            public function toocheke_2nd_language_desktop_comic_editor_meta_box()
            {
                if (! get_option('toocheke-bilingual-display')) {
                    return;
                }
                if (! get_option('toocheke-comic-layout-devices')) {
                    return;
                }
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
             * ComicScout Image Metabox
             */
            public function toocheke_comicscout_image_add_metabox()
            {
                 add_meta_box(
                'comicscout-image-metabox',
                __('ComicScout Thumbnail', 'toocheke-companion'),
                [$this, 'toocheke_comicscout_image_display_metabox'],
                'comic',
                'side',
                'high'
                );
                add_meta_box(
                'comicscout-image-metabox',
                __('ComicScout Thumbnail', 'toocheke-companion'),
                [$this, 'toocheke_comicscout_image_display_metabox'],
                'manga_chapter',
                'side',
                'core' // 👈 different priority for manga_chapter
                );
            }

            public function toocheke_comicscout_image_display_metabox($post)
            {
                $this->toocheke_render_image_metabox(
                    $post,
                    'comicscout_image_id',
                    'comicscout_image',
                    'upload_comicscout_image_button',
                    'remove_comicscout_image_button',
                    __('Upload thumbnail for ComicScout','toocheke-companion'),
                    __('Remove thumbnail for ComicScout','toocheke-companion'),
                    '⭐ <strong>Featured on <a href="https://www.thecomicscout.com/" target="_blank">ComicScout</a>!</strong><br>Used for ComicScout listings.<br><strong>Recommended size: 1000 × 1500px</strong>.',
                    '2/3'
                );
            }

            public function toocheke_comicscout_image_save($post_id)
            {
                $this->toocheke_save_image_metabox_field($post_id, 'comicscout_image', 'comicscout_image_id');
            }

            /**
             * ComicScout Social Share Image Metabox
             */
            public function toocheke_comicscout_social_share_image_add_metabox()
            {
                add_meta_box(
                    'comicscout-social-share-image-metabox',
                    __('ComicScout Social Share Image', 'toocheke-companion'),
                    [
                        $this,
                        'toocheke_comicscout_social_share_image_display_metabox'
                    ],
                    'comic',
                    'side',
                    'high'
                );
                add_meta_box(
                    'comicscout-social-share-image-metabox',
                    __('ComicScout Social Share Image', 'toocheke-companion'),
                    [
                        $this,
                        'toocheke_comicscout_social_share_image_display_metabox'
                    ],
                    'manga_chapter',
                    'side',
                    'core'
                );
            }

            public function toocheke_comicscout_social_share_image_display_metabox($post)
            {
                $this->toocheke_render_image_metabox(
                    $post,
                    'comicscout_social_share_image_id',
                    'comicscout_social_share_image',
                    'upload_comicscout_social_share_image_button',
                    'remove_comicscout_social_share_image_button',
                    __('Upload social share image for ComicScout', 'toocheke-companion'),
                    __('Remove social share image for ComicScout', 'toocheke-companion'),
                    '⭐ <strong>Promoted by <a href="https://www.thecomicscout.com/" target="_blank">ComicScout</a>!</strong><br>This image will be used by ComicScout when promoting your comic updates to social media platforms.<br><strong>Recommended size: 1200 × 630px</strong>.<br>If no image is uploaded, the <strong>Featured Image (Comic Thumbnail)</strong> will be used as a fallback.',
                    '1.91/1'
                );
            }

            public function toocheke_comicscout_social_share_image_save($post_id)
            {
                $this->toocheke_save_image_metabox_field($post_id, 'comicscout_social_share_image', 'comicscout_social_share_image_id');
            }

            /**
             * Series Hero Metabox
             */
            public function toocheke_series_hero_image_add_metabox()
            {
                add_meta_box('series-hero-metabox', __('Series Hero Image(Desktop)', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_hero_image_display_metabox'
                ], 'series', 'side', 'core');
            }

            public function toocheke_series_hero_image_display_metabox($post)
            {
                $this->toocheke_render_image_metabox(
                    $post,
                    'series_hero_image_id',
                    'series_hero_image',
                    'upload_series_hero_image_button',
                    'remove_series_hero_image_button',
                    __('Set series hero image','toocheke-companion'),
                    __('Remove series hero image','toocheke-companion'),
                    'Used for the series header on desktop.',
                    '16/9'
                );
            }

            public function toocheke_series_hero_image_save($post_id)
            {
                $this->toocheke_save_image_metabox_field($post_id, 'series_hero_image', 'series_hero_image_id');
            }

            /**
             * Series Mobile Hero Metabox
             */
            public function toocheke_series_mobile_hero_image_add_metabox()
            {
                add_meta_box('series-mobile-hero-metabox', __('Series Hero Image(Mobile)', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_mobile_hero_image_display_metabox'
                ], 'series', 'side', 'core', 90);
            }

            public function toocheke_series_mobile_hero_image_display_metabox($post)
            {
                $this->toocheke_render_image_metabox(
                    $post,
                    'series_mobile_hero_image_id',
                    'series_mobile_hero_image',
                    'upload_series_mobile_hero_image_button',
                    'remove_series_mobile_hero_image_button',
                    __('Set mobile hero image','toocheke-companion'),
                    __('Remove mobile hero image','toocheke-companion'),
                    'Used for the series header on mobile devices.',
                    '3/4'
                );
            }

           public function toocheke_series_mobile_hero_image_save($post_id)
            {
                $this->toocheke_save_image_metabox_field($post_id, 'series_mobile_hero_image', 'series_mobile_hero_image_id');
            }

            /**
             * Series Background Image Metabox
             */
            public function toocheke_series_bg_image_add_metabox()
            {
                add_meta_box('series-bg-image-metabox', __('Series Background Image', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_bg_image_display_metabox'
                ], 'series', 'side', 'core');
            }

            public function toocheke_series_bg_image_display_metabox($post)
            {
                $this->toocheke_render_image_metabox(
                    $post,
                    'series_bg_image_id',
                    'series_bg_image',
                    'upload_series_bg_image_button',
                    'remove_series_bg_image_button',
                    __('Set series background image','toocheke-companion'),
                    __('Remove series background image','toocheke-companion'),
                    'Optional background image for the series page.',
                    '16/9'
                );
            }

            public function toocheke_series_bg_image_save($post_id)
            {
                $this->toocheke_save_image_metabox_field($post_id, 'series_bg_image', 'series_bg_image_id');
            }

            /**
             * Series Background Color Metabox
             */

            public function toocheke_series_bg_color_add_metabox()
            {
                add_meta_box('series-bg-color-metabox', esc_html__('Series Background Color', 'toocheke-companion'), [
                    $this,
                    'toocheke_series_bg_color_display_metabox'
                ], 'series', 'side', 'core');
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
                    "core"
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

            /**
             * Series Comic Order Override Metabox
             */
            public function toocheke_series_comic_order_add_metabox()
            {
                add_meta_box(
                    'series-comic-order-metabox',
                    __('Comic Ordering', 'toocheke-companion'),
                    [$this, 'toocheke_series_comic_order_display_metabox'],
                    'series',
                    'side',
                    'core'
                );
            }

            public function toocheke_series_comic_order_display_metabox($post)
            {
                wp_nonce_field('toocheke_series_comic_order_meta_box', 'toocheke_series_comic_order_nonce');
                $current = get_post_meta($post->ID, 'series_comic_order_override', true);
                ?>
                <?php $this->toocheke_render_dismissible_info('series_comic_order', 'Override the global Comics Ordering setting for this series only.<br>Leave as <strong>Use Global Setting</strong> to inherit the default.'); ?>
                <p>
                    <label for="series_comic_order_override">
                        <strong><?php _e('Comic Order for this Series', 'toocheke-companion'); ?></strong>
                    </label>
                </p>
                <select name="series_comic_order_override" id="series_comic_order_override" style="width:100%">
                    <option value="" <?php selected($current, ''); ?>><?php _e('Use Global Setting', 'toocheke-companion'); ?></option>
                    <option value="ASC"  <?php selected($current, 'ASC');  ?>><?php _e('Ascending (oldest first)', 'toocheke-companion'); ?></option>
                    <option value="DESC" <?php selected($current, 'DESC'); ?>><?php _e('Descending (newest first)', 'toocheke-companion'); ?></option>
                </select>
                <?php
            }

            public function toocheke_series_comic_order_save($post_id)
            {
                if (!isset($_POST['toocheke_series_comic_order_nonce'])) {
                    return;
                }
                if (!wp_verify_nonce($_POST['toocheke_series_comic_order_nonce'], 'toocheke_series_comic_order_meta_box')) {
                    return;
                }
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }
                if (!current_user_can('edit_post', $post_id)) {
                    return;
                }

                $value = isset($_POST['series_comic_order_override'])
                    ? sanitize_text_field($_POST['series_comic_order_override'])
                    : '';

                // Only allow valid values
                if (!in_array($value, ['ASC', 'DESC', ''], true)) {
                    $value = '';
                }

                update_post_meta($post_id, 'series_comic_order_override', $value);
            }

            /**
             * Move Featured Image Metabox on 'comic' post type
             */
            public function toocheke_move_comic_featured_image_metabox()
            {
                remove_meta_box('postimagediv', 'comic', 'side');

                  add_meta_box(
        'postimagediv',
        __('Comic Thumbnail', 'toocheke-companion'),
        array($this, 'toocheke_comic_thumbnail_metabox'),
        'comic',
        'side',
        'high'
    );
            }

           public function toocheke_comic_thumbnail_metabox($post)
{
    $this->toocheke_render_dismissible_info('comic_thumbnail', 'Used for comic listings in Toocheke (homepage, archives, and comic carousel).<br>Displayed as a square thumbnail in listings.<br><strong>Recommended size: 300 × 300px or larger</strong>.');

    // Add an ID so JS can hide/show it
    echo '<div id="toocheke-featured-image-ratio-guide" style="
        width:100%;
        aspect-ratio:1/1;
        background:#f6f7f7;
        border:2px dashed #ccd0d4;
        display:flex;
         flex-direction:column;
        align-items:center;
        justify-content:center;
        text-align:center;
        font-size:12px;
        color:#646970;
        margin-bottom:10px;">
        <svg xmlns="http://www.w3.org/2000/svg"
     width="40"
     height="40"
     viewBox="0 0 24 24"
     fill="none"
     style="display:block">

    <rect x="3" y="5"
          width="18"
          height="14"
          rx="2"
          stroke="currentColor"
          stroke-width="1"/>

    <circle cx="9"
            cy="10"
            r="1.5"
            stroke="currentColor"
            stroke-width="1"/>

    <path d="M4.5 16l4.5-3.5a2 2 0 0 1 2.5 0l2.2 1.8"
          stroke="currentColor"
          stroke-width="1"
          stroke-linecap="round"
          stroke-linejoin="round"/>

    <path d="M10.8 16l4-3a2 2 0 0 1 2.5.1L19.5 15"
          stroke="currentColor"
          stroke-width="1"
          stroke-linecap="round"
          stroke-linejoin="round"/>

</svg>

<span>Image Preview</span>
    </div>';

    post_thumbnail_meta_box($post);
}

            /**
             * Move Featured Image Metabox on 'series' post type
             */
            public function toocheke_move_series_featured_image_metabox()
            {
                remove_meta_box('postimagediv', 'series', 'side');
                add_meta_box('postimagediv', __('Series Thumbnail', 'toocheke-companion'),  array($this, 'toocheke_series_thumbnail_metabox'), 'series', 'side', 'high');
            }

             public function toocheke_series_thumbnail_metabox($post)
            {
                $this->toocheke_render_dismissible_info('series_thumbnail', 'Used for series listings in Toocheke.<br><strong>Recommended size: at least 300px wide</strong>.');

                // Add an ID so JS can hide/show it
                echo '<div id="toocheke-featured-image-ratio-guide" style="
                    width:100%;
                    aspect-ratio:1.91/1;
                    background:#f6f7f7;
                    border:2px dashed #ccd0d4;
                    display:flex;
                    flex-direction:column;
                    align-items:center;
                    justify-content:center;
                    text-align:center;
                    font-size:12px;
                    color:#646970;
                    margin-bottom:10px;">
                    <svg xmlns="http://www.w3.org/2000/svg"
     width="40"
     height="40"
     viewBox="0 0 24 24"
     fill="none"
     style="display:block">

    <rect x="3" y="5"
          width="18"
          height="14"
          rx="2"
          stroke="currentColor"
          stroke-width="1"/>

    <circle cx="9"
            cy="10"
            r="1.5"
            stroke="currentColor"
            stroke-width="1"/>

    <path d="M4.5 16l4.5-3.5a2 2 0 0 1 2.5 0l2.2 1.8"
          stroke="currentColor"
          stroke-width="1"
          stroke-linecap="round"
          stroke-linejoin="round"/>

    <path d="M10.8 16l4-3a2 2 0 0 1 2.5.1L19.5 15"
          stroke="currentColor"
          stroke-width="1"
          stroke-linecap="round"
          stroke-linejoin="round"/>

</svg>

<span>Image Preview</span>
                </div>';

                post_thumbnail_meta_box($post);
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

            public function toocheke_set_age_verification_cookie()
            {
                check_ajax_referer('toocheke-verify-age');

                if (defined('DOING_AJAX') && DOING_AJAX) {
                    setcookie('toocheke_age_verification', true, time() + (60 * 60 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN); //expire in 30 days
                }
                die();
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

            public function toocheke_add_buy_comic_metaboxes(){
                $theme = wp_get_theme();

                 if ('Toocheke Premium' === $theme->name || 'Toocheke Premium' === $theme->parent_theme) {
                    add_meta_box(
                        'toocheke_comic_pricing',
                        __('Pricing for "Buy Comic"', 'toocheke'),
                        [$this, 'toocheke_comic_pricing_metabox_callback'], 
                        'comic', 
                        'normal',
                        'high'
                    );
                }
            }

            public function toocheke_comic_pricing_metabox_callback($post) {
                wp_nonce_field('toocheke_comic_pricing_nonce', 'toocheke_comic_pricing_nonce');

                $fields = [
                    'original_us_price' => 'Orignal Art - US Price',
                    'original_us_shipping' => 'Orignal Art - US Shipping',
                    'original_canada_price' => 'Orignal Art - Canada Price',
                    'original_canada_shipping' => 'Orignal Art - Canada Shipping',
                    'original_international_price' => 'Orignal Art - International Price',
                    'original_international_shipping' => 'Orignal Art - International Shipping',
                    'print_us_price' => 'Print - US Price',
                    'print_us_shipping' => 'Print - US Shipping',
                    'print_canada_price' => 'Print - Canada Price',
                    'print_canada_shipping' => 'Print - Canada Shipping',
                    'print_international_price' => 'Print - International Price',
                    'print_international_shipping' => 'Print - International Shipping',
                ];

                foreach ($fields as $key => $label) {
                    $value = get_post_meta($post->ID, $key, true); // empty if not set
                    echo '<p>';
                    echo '<label for="' . esc_attr($key) . '">' . esc_html($label) . ':</label> ';
                    echo '<input type="number" step="0.01" min="0" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                    echo '</p>';
                }
            }

public function toocheke_save_comic_pricing_metabox($post_id) {
    if (!isset($_POST['toocheke_comic_pricing_nonce'])) return;
    if (!wp_verify_nonce($_POST['toocheke_comic_pricing_nonce'], 'toocheke_comic_pricing_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $fields = [
        'original_us_price',
        'original_us_shipping',
        'original_canada_price',
        'original_canada_shipping',
        'original_international_price',
        'original_international_shipping',
        'print_us_price',
        'print_us_shipping',
        'print_canada_price',
        'print_canada_shipping',
        'print_international_price',
        'print_international_shipping',
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field]) && $_POST[$field] !== '') {
            update_post_meta($post_id, $field, floatval($_POST[$field]));
        } else {
            delete_post_meta($post_id, $field); // remove empty fields
        }
    }
}

    /**
     * Shared save handler for the simple image metaboxes above (ComicScout
     * thumbnail/social share image, series hero/mobile hero/background
     * image): each just stores an attachment ID from a hidden input into a
     * single post meta key, with the standard autosave/revision guards.
     *
     * @param int    $post_id    The post being saved.
     * @param string $input_name The $_POST key holding the attachment ID (set by toocheke_render_image_metabox()'s upload button JS).
     * @param string $meta_key   The post meta key to store the attachment ID under.
     */
    private function toocheke_save_image_metabox_field($post_id, $input_name, $meta_key)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if (isset($_POST[$input_name])) {
            update_post_meta($post_id, $meta_key, (int) $_POST[$input_name]);
        }
    }

    private function toocheke_render_image_metabox(
    $post,
    $meta_key,
    $input_name,
    $upload_button_id,
    $remove_button_id,
    $set_text,
    $remove_text,
    $instruction = '',
    $ratio = '1/1'
) {

    global $content_width, $_wp_additional_image_sizes;

    $image_id = get_post_meta($post->ID, $meta_key, true);

    $old_content_width = $content_width;
    $content_width = 254;

   if ($instruction) {
        $this->toocheke_render_dismissible_info($meta_key, $instruction);
    }

    if ($image_id && get_post($image_id)) {

        if (!isset($_wp_additional_image_sizes['post-thumbnail'])) {
            $thumbnail_html = wp_get_attachment_image($image_id, [$content_width, $content_width]);
        } else {
            $thumbnail_html = wp_get_attachment_image($image_id, 'post-thumbnail');
        }

        if (!empty($thumbnail_html)) {

            echo $thumbnail_html;

            echo '<p class="hide-if-no-js">
            <a href="javascript:;" id="'.$remove_button_id.'">'.$remove_text.'</a>
            </p>';

            echo '<input type="hidden" id="'.$input_name.'" name="'.$input_name.'" value="'.esc_attr($image_id).'" />';
        }

        $content_width = $old_content_width;

    } else {

        echo '<div style="
        width:100%;
        aspect-ratio:'.$ratio.';
        background:#f6f7f7;
        border:2px dashed #ccd0d4;
        display:flex;
         flex-direction:column;
        align-items:center;
        justify-content:center;
        text-align:center;
        font-size:12px;
        color:#646970;
        margin-bottom:10px;">
        <svg xmlns="http://www.w3.org/2000/svg"
     width="40"
     height="40"
     viewBox="0 0 24 24"
     fill="none"
     style="display:block">

    <rect x="3" y="5"
          width="18"
          height="14"
          rx="2"
          stroke="currentColor"
          stroke-width="1"/>

    <circle cx="9"
            cy="10"
            r="1.5"
            stroke="currentColor"
            stroke-width="1"/>

    <path d="M4.5 16l4.5-3.5a2 2 0 0 1 2.5 0l2.2 1.8"
          stroke="currentColor"
          stroke-width="1"
          stroke-linecap="round"
          stroke-linejoin="round"/>

    <path d="M10.8 16l4-3a2 2 0 0 1 2.5.1L19.5 15"
          stroke="currentColor"
          stroke-width="1"
          stroke-linecap="round"
          stroke-linejoin="round"/>

</svg>

<span>Image Preview</span>
        </div>';

        // IMPORTANT: Keep the IMG element for JS preview
        echo '<img src="" style="width:100%;height:auto;border:0;display:none;margin-bottom:10px;" />';

        echo '<p class="hide-if-no-js">
        <a title="'.$set_text.'"
        href="javascript:;" id="'.$upload_button_id.'"
        data-uploader_title="'.esc_attr__('Choose an image','toocheke-companion').'"
        data-uploader_button_text="'.$set_text.'">
        '.$set_text.'
        </a>
        </p>';

        echo '<input type="hidden" id="'.$input_name.'" name="'.$input_name.'" value="" />';
    }
}

}
