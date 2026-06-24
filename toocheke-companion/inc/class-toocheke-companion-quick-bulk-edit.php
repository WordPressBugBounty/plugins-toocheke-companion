<?php
/**
 * Adds the custom fields shown in the Quick Edit and Bulk Edit panels on
 * post list screens, and saves the values submitted from them.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Quick_Bulk_Edit
{
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

                 $post_id = absint($post_id); 

                // check nonce
                if (isset($_POST['toocheke_companion_nonce']) && ! wp_verify_nonce($_POST['toocheke_companion_nonce'], 'toocheke_companion_quick_edit_nonce')) {
                    return;
                }

                // update the series for the comic
                if (isset($_POST['parent_id'])) {
                    update_post_meta($post_id, 'post_parent', sanitize_text_field($_POST['parent_id']));
                }
                // update patreon level - SANITIZE INPUT
                if (isset($_REQUEST['patreon_level'])) {
                    update_post_meta($post_id, 'patreon-level', sanitize_text_field($_REQUEST['patreon_level']));
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
                    // Sanitize post ID
                    $id = absint($id);

                    if (! current_user_can('edit_post', $id)) {
                        continue;
                    }

                    // if series is empty, we shouldn't change it
                    if (! empty($_POST['series'])) {
                        update_post_meta($id, 'post_parent', sanitize_text_field($_POST['series']));
                    }

                    // if patreon level empty, do nothing - SANITIZE INPUT
                    if (! empty($_POST['patreon_level'])) {
                        update_post_meta($id, 'patreon-level', sanitize_text_field($_POST['patreon_level']));
                    }
                }

                die();
            }

}
