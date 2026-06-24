<?php
/**
 * Adds image and manual-ordering fields to the add/edit term screens for
 * the collections, chapters, characters, and genres taxonomies, and handles
 * saving those fields and sorting terms by their configured order.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Taxonomy_Terms
{
    /**
     * Shared helpers for the genres/chapters/collections/comic_characters
     * taxonomies below. Each taxonomy's image field, manual ordering field,
     * and admin-list sorting follow the same pattern; these helpers take the
     * taxonomy's word root (e.g. 'genre', 'chapter') and derive the term
     * meta keys ('{word}-image-id', '{word}-order') and admin column names
     * ('{word}_image', '{word}_order') from it. Note: the actual registered
     * taxonomy slug isn't always "{word}s" (the characters taxonomy is
     * registered as 'comic_characters'), so methods that need the real
     * taxonomy slug take it as a separate parameter.
     */

    /**
     * Enqueues the WP media uploader on a taxonomy's add/edit term screen.
     *
     * @param string $taxonomy_slug The registered taxonomy slug, e.g. 'genres', 'comic_characters'.
     */
    private function toocheke_load_media_for_taxonomy($taxonomy_slug)
    {
        if (! isset($_GET['taxonomy']) || $_GET['taxonomy'] != $taxonomy_slug) {
            return;
        }
        wp_enqueue_media();
    }

    /**
     * Saves a term's image id on term creation, if one was submitted.
     *
     * @param int    $term_id The term being created.
     * @param string $word    The taxonomy word root, e.g. 'genre'. Reads $_POST['{word}-image-id']
     *                        and stores it in term meta '{word}-image-id'.
     */
    private function toocheke_save_term_image_meta($term_id, $word)
    {
        $field = $word . '-image-id';
        if (isset($_POST[$field]) && '' !== $_POST[$field]) {
            add_term_meta($term_id, $field, absint(sanitize_title($_POST[$field])), true);
        }
    }

    /**
     * Updates a term's image id on term edit, clearing it if removed.
     *
     * @param int    $term_id The term being updated.
     * @param string $word    The taxonomy word root, e.g. 'chapter'.
     */
    private function toocheke_update_term_image_meta($term_id, $word)
    {
        $field = $word . '-image-id';
        // If this field isn't part of the current request at all (e.g. a Quick
        // Edit save, which only submits name/slug/parent and never renders our
        // custom fields), there's nothing to update -- leave the existing image
        // alone. Only an explicitly empty value (submitted by the full Edit Term
        // form, which always renders this hidden field) means the user cleared it.
        if (! isset($_POST[$field])) {
            return;
        }
        if ('' !== $_POST[$field]) {
            update_term_meta($term_id, $field, absint(sanitize_title($_POST[$field])));
        } else {
            update_term_meta($term_id, $field, '');
        }
    }

    /**
     * Renders a term's image thumbnail for its admin list-table column.
     *
     * @param string $content     The column's existing content (passed through if this isn't our column).
     * @param string $column_name The column being rendered, checked against '{word}_image'.
     * @param int    $term_id     The term whose image to show.
     * @param string $word        The taxonomy word root, e.g. 'collection'.
     * @return string
     */
    private function toocheke_render_term_image_column($content, $column_name, $term_id, $word)
    {
        if ($column_name !== $word . '_image') {
            return $content;
        }

        $term_id  = absint($term_id);
        $thumb_id = get_term_meta($term_id, $word . '-image-id', true);

        if (! empty($thumb_id)) {
            $term_img = wp_get_attachment_url($thumb_id);
            $content .= "<img src=\"$term_img\" width=\"100%\" height=\"auto\"/>";
        }

        return $content;
    }

    /**
     * Saves a term's manual sort order on term creation, defaulting to 1.
     *
     * @param int    $term_id The term being created.
     * @param string $word    The taxonomy word root, e.g. 'chapter'.
     */
    private function toocheke_save_term_order_meta($term_id, $word)
    {
        $field = $word . '-order';
        if (isset($_POST[$field]) && ! empty($_POST[$field])) {
            $order = absint(sanitize_title($_POST[$field]));
            add_term_meta($term_id, $field, $order, true);
        } else {
            add_term_meta($term_id, $field, 1, true);
        }
    }

    /**
     * Updates a term's manual sort order on term edit, if submitted.
     *
     * @param int    $term_id The term being updated.
     * @param string $word    The taxonomy word root, e.g. 'collection'.
     */
    private function toocheke_update_term_order_meta($term_id, $word)
    {
        $field = $word . '-order';
        if (isset($_POST[$field]) && ! empty($_POST[$field])) {
            $order = absint(sanitize_title($_POST[$field]));
            update_term_meta($term_id, $field, $order);
        }
    }

    /**
     * Adds the "Order" admin list-table column header.
     *
     * @param array  $columns Existing column headers.
     * @param string $word    The taxonomy word root, e.g. 'character'.
     * @return array
     */
    private function toocheke_add_term_order_column($columns, $word)
    {
        $columns[$word . '_order'] = __('Order', 'toocheke-companion');
        return $columns;
    }

    /**
     * Marks the "Order" column as sortable.
     *
     * @param array  $sortable Existing sortable columns.
     * @param string $word     The taxonomy word root, e.g. 'character'.
     * @return array
     */
    private function toocheke_add_term_order_column_sortable($sortable, $word)
    {
        $sortable[$word . '_order'] = $word . '_order';
        return $sortable;
    }

    /**
     * Sorts a taxonomy's admin term list by its manual order meta when
     * viewing edit-tags.php for that taxonomy (or leaves the query alone
     * otherwise). Terms without the order meta set are still included
     * (via the NOT EXISTS clause) rather than being hidden.
     *
     * @param WP_Term_Query $term_query
     * @param string        $taxonomy_slug The registered taxonomy slug, e.g. 'comic_characters'.
     * @param string        $word          The taxonomy word root used in the meta key, e.g. 'character'.
     * @return WP_Term_Query
     */
    private function toocheke_sort_terms_by_order_meta($term_query, $taxonomy_slug, $word)
    {
        global $pagenow;
        if (! is_admin()) {
            return $term_query;
        }
        // WP_Term_Query does not define a get() or a set() method so the query_vars member must
        // be manipulated directly
        if (is_admin() && $pagenow == 'edit-tags.php' && $term_query->query_vars['taxonomy'][0] == $taxonomy_slug && (! isset($_GET['orderby']) || $_GET['orderby'] == $word . '_order')) {
            // set orderby to the named clause in the meta_query
            $term_query->query_vars['orderby'] = 'order_clause';
            $term_query->query_vars['order']   = isset($_GET['order']) ? sanitize_title($_GET['order']) : "DESC";
            // the OR relation and the NOT EXISTS clause allow for terms without a meta_value at all
            $args = [
                'relation' => 'OR',
                'order_clause'      => [
                    'key'  => $word . '-order',
                    'type' => 'NUMERIC',
                ],
                [
                    'key'     => $word . '-order',
                    'compare' => 'NOT EXISTS',
                ],
            ];
            $term_query->meta_query = new WP_Meta_Query($args);
        }
        return $term_query;
    }

    /* Genre specific functions and terms */
    public function toocheke_companion_genre_load_media()
    {
        $this->toocheke_load_media_for_taxonomy('genres');
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
        $this->toocheke_save_term_image_meta($term_id, 'genre');
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
        $this->toocheke_update_term_image_meta($term_id, 'genre');
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

    /* Add content into column */
    public function toocheke_companion_add_genre_image_column_content($content, $column_name, $term_id)
    {
        return $this->toocheke_render_term_image_column($content, $column_name, $term_id, 'genre');
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
                $this->toocheke_save_term_order_meta($term_id, 'chapter');
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
                $this->toocheke_update_term_order_meta($term_id, 'chapter');
            }

            /*
     * Displaying the order column
     */
            public function toocheke_companion_chapter_add_order_column($columns)
            {
                return $this->toocheke_add_term_order_column($columns, 'chapter');
            }

            /* Make column sortable */
            public function toocheke_companion_chapter_add_order_column_sortable($sortable)
            {
                return $this->toocheke_add_term_order_column_sortable($sortable, 'chapter');
            }

            /* Sort columns */
            public function toocheke_companion_chapter_sort_by_chapter_order($term_query)
            {
                return $this->toocheke_sort_terms_by_order_meta($term_query, 'chapters', 'chapter');
            }

            /* Chapter Image Functions */
            public function toocheke_companion_chapter_load_media()
            {
                $this->toocheke_load_media_for_taxonomy('chapters');
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
                $this->toocheke_save_term_image_meta($term_id, 'chapter');
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
                $this->toocheke_update_term_image_meta($term_id, 'chapter');
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

            /* Add content into column */
            public function toocheke_companion_add_chapter_image_column_content($content, $column_name, $term_id)
            {
                return $this->toocheke_render_term_image_column($content, $column_name, $term_id, 'chapter');
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
                $this->toocheke_save_term_order_meta($term_id, 'collection');
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
                $this->toocheke_update_term_order_meta($term_id, 'collection');
            }

            /*
 * Displaying the order column
 */
            public function toocheke_companion_collection_add_order_column($columns)
            {
                return $this->toocheke_add_term_order_column($columns, 'collection');
            }

            /* Make column sortable */
            public function toocheke_companion_collection_add_order_column_sortable($sortable)
            {
                return $this->toocheke_add_term_order_column_sortable($sortable, 'collection');
            }

            /* Sort columns */
            public function toocheke_companion_collection_sort_by_collection_order($term_query)
            {
                return $this->toocheke_sort_terms_by_order_meta($term_query, 'collections', 'collection');
            }

            /* Image Functions */
            public function toocheke_companion_collection_load_media()
            {
                $this->toocheke_load_media_for_taxonomy('collections');
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
                $this->toocheke_save_term_image_meta($term_id, 'collection');
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
                $this->toocheke_update_term_image_meta($term_id, 'collection');
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

            /* Add content into column */
            public function toocheke_companion_add_collection_image_column_content($content, $column_name, $term_id)
            {
                return $this->toocheke_render_term_image_column($content, $column_name, $term_id, 'collection');
            }

            /* Character specific functions and terms */

            /* Image Functions */
            public function toocheke_companion_character_load_media()
            {
                $this->toocheke_load_media_for_taxonomy('comic_characters');
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
                $this->toocheke_save_term_image_meta($term_id, 'character');
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
                $this->toocheke_update_term_image_meta($term_id, 'character');
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
                $this->toocheke_save_term_order_meta($term_id, 'character');
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
                $this->toocheke_update_term_order_meta($term_id, 'character');
            }

            /*
     * Displaying the order column
     */
            public function toocheke_companion_character_add_order_column($columns)
            {
                return $this->toocheke_add_term_order_column($columns, 'character');
            }

            /* Make column sortable */
            public function toocheke_companion_character_add_order_column_sortable($sortable)
            {
                return $this->toocheke_add_term_order_column_sortable($sortable, 'character');
            }

            /* Sort columns */
            public function toocheke_companion_character_sort_by_character_order($term_query)
            {
                return $this->toocheke_sort_terms_by_order_meta($term_query, 'comic_characters', 'character');
            }

            /* Add content into column */
            public function toocheke_companion_add_character_image_column_content($content, $column_name, $term_id)
            {
                return $this->toocheke_render_term_image_column($content, $column_name, $term_id, 'character');
            }

}
