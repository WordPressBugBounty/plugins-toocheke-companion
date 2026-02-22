<?php
    /**
     * Template part for displaying comic archive
     * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
     *
     * @package Toocheke
     */
    $templates    = new Toocheke_Companion_Template_Loader;
    $taxonomy     = get_query_var('taxonomy');
    $term         = get_query_var('term');
    $comics_paged = isset($_GET['comics_paged']) ? (int) $_GET['comics_paged'] : 1;
    $comic_order  = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
    if ($taxonomy) {
        set_query_var('taxonomy', $taxonomy);
    }
    if ($term) {
        set_query_var('term', $term);
    }

    if (post_type_exists('comic') && $taxonomy && $term):
        $comics_args = [
            'post_type'      => 'comic',
            'post_status'    => 'publish',
            'posts_per_page' => get_option('posts_per_page'),
            'paged'          => $comics_paged,
            'orderby'        => 'post_date',
            'order'          => $comic_order,
            "tax_query"      => [
                [
                    'taxonomy' => $taxonomy, // use the $tax you define at the top of your script
                    'field'    => 'slug',
                    'terms'    => $term,
                ],
            ],
        ];

        $comics_query = new WP_Query($comics_args);
        if ($comics_query->have_posts()):
    ?>



		<ul id="comic-list">
		      <?php

                      /* Start the Loop */
                      while ($comics_query->have_posts()): $comics_query->the_post();

                          /*
     * Include the Post-Type-specific template for the content.
     * If you want to override this in a child theme, then include a file
     * called content-___.php (where ___ is the Post Type name) and that will be used instead.
     */
                          $templates->get_template_part('content', 'comiclistitem');

                      endwhile;
                  ?>
		</ul>


		     <!-- Start Pagination -->
		<?php
                // Set up paginated links.
                $comic_links = paginate_links([
                    'format'    => '?comics_paged=%#%#comics-section',
                    'current'   => $comics_paged,
                    'total'     => $comics_query->max_num_pages,
                    'prev_text' => wp_kses(__('<i class=\'fas fa-chevron-left\'></i>', 'toocheke-companion'), ['i' => ['class' => []]]),
                    'next_text' => wp_kses(__('<i class=\'fas fa-chevron-right\'></i>', 'toocheke-companion'), ['i' => ['class' => []]]),

                ]);

                if ($comic_links):

            ?>

		<nav class="pagination">

		    <?php echo wp_kses($comic_links, [
                        'a'    => [
                            'href'  => [],
                            'class' => [],
                        ],
                        'i'    => [
                            'class' => [],
                        ],
                        'span' => [
                            'class' => [],
                        ],
                ]); ?>

		</nav>
		<!--/ .navigation -->
		<?php
            endif;
        ?>
<!-- End Pagination -->



<?php
    $comics_query = null;
    wp_reset_postdata();
    endif;

endif;
?>