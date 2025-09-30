<?php
    /**
     * Template part for displaying the content for the a single manga series post(single-manga_series.php)
     *
     * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
     *
     * @package Toocheke
     */
    $templates = new Toocheke_Companion_Template_Loader;
    $display_likes                = get_option('toocheke-comic-likes') && 1 == get_option('toocheke-comic-likes');
    $manga_series_id     = get_the_ID();
    $latest_chapter_link = $latest_chapter_title = $first_chapter_link = $first_chapter_title = false;

    // Get the latest volume
    $latest_volume = get_posts([
        'post_type'      => 'manga_volume',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'     => 'series_id',
                'value'   => $manga_series_id,
                'compare' => '=',
            ],
        ],
        'meta_key'       => 'volume_number', // make sure this meta field exists
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ]);

    if ($latest_volume) {
        $volume_id = $latest_volume[0]->ID;

        // Step 2: Get the latest chapter from that volume (by chapter_number)
        $latest_chapter = get_posts([
            'post_type'      => 'manga_chapter',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'volume_id',
                    'value'   => $volume_id,
                    'compare' => '=',
                ],
            ],
            'meta_key'       => 'chapter_number',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);

        if ($latest_chapter) {
            $latest_chapter_link  = get_permalink($latest_chapter[0]->ID);
            $latest_chapter_title = get_the_title($latest_chapter[0]->ID);
        }
    }

    // Get the first volume
    $first_volume = get_posts([
        'post_type'      => 'manga_volume',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'     => 'series_id',
                'value'   => $manga_series_id,
                'compare' => '=',
            ],
        ],
        'meta_key'       => 'volume_number',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC', // earliest volume
    ]);

    if ($first_volume) {
        $first_volume_id = $first_volume[0]->ID;

        // Get the first chapter in that volume
        $first_chapter = get_posts([
            'post_type'      => 'manga_chapter',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'volume_id',
                    'value'   => $first_volume_id,
                    'compare' => '=',
                ],
            ],
            'meta_key'       => 'chapter_number',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC', // earliest chapter
        ]);

        if ($first_chapter) {
            $first_chapter_link  = get_permalink($first_chapter[0]->ID);
            $first_chapter_title = get_the_title($first_chapter[0]->ID);
        }
    }

    $manga_creator = get_post_meta($manga_series_id, 'manga_creator', true);
    $manga_status  = get_post_meta($manga_series_id, 'manga_status', true);
    $manga_rating  = get_post_meta($manga_series_id, 'manga_rating', true);
    // Get genres
    $genres = get_the_terms($manga_series_id, 'manga_genre');

    // Get publishers
    $publishers = get_the_terms($manga_series_id, 'manga_publisher');
?>


                     <!--start content-->
                                          <nav aria-label="breadcrumb">
  <ol class="manga-breadcrumb breadcrumb">
    <li class="breadcrumb-item active" aria-current="page"><?php echo esc_html(get_the_title()); ?></li>
  </ol>
</nav>
                     <div class="manga-row">
                        <div class="col-md-5">


<?php if (has_post_thumbnail()): ?>
        <?php the_post_thumbnail('full'); ?>

<?php endif; ?>
                        </div>
                         <div class="col-md-7">
<h1><?php echo esc_html(get_the_title()); ?></h1>
<?php
  

    if (! empty($manga_creator)) {
        printf(
            '<p class="manga-creator">By: %s</p>',
            esc_html($manga_creator)
        );
    }
    the_content();
?>

     <?php
    if ($display_likes):
?>
<p>
		            <b>Like this:</b> <span class="single-comic-total-likes">
		               <?php echo do_shortcode("[toocheke-like-button]"); ?>
		</span>
        </p>
		<?php
            endif;
        ?>

<?php if ($latest_chapter_link): ?>
    <div class="manga-series-nav">
    <a class="btn btn-outline-black btn-sm btn-manga-chapter-link btn-manga-first-chapter" title="<?php echo esc_attr($first_chapter_title) ?>" href="<?php echo esc_url($first_chapter_link); ?>"><i class="fas fa-lg fa-step-backward fa-fw"></i> Read First Chapter</a>
<a class="btn btn-outline-black btn-sm btn-manga-chapter-link btn-manga-latest-chapter" title="<?php echo esc_attr($latest_chapter_title) ?>" href="<?php echo esc_url($latest_chapter_link); ?>">Read Latest Chapter <i class="fas fa-lg fa-step-forward fa-fw"></i></a>
    </div>

<?php endif; ?>
                        </div>
                     </div>
                     <hr class="toocheke-hr manga-hr" />
                     <div class="manga-row">
                        <div class="col-lg-12">
                            <!--Series Information-->
<h2><?php _e('Series Information', 'toocheke-companion'); ?></h2>
<div class="manga-info-table">

  <div class="manga-info-row">
    <div class="manga-info-col">
      <span class="manga-info-key"><?php _e('Status', 'toocheke-companion'); ?></span>
      <span class="manga-info-value">
        <?php echo esc_html($manga_status ?? ''); ?>
      </span>
    </div>
    <div class="manga-info-col">
      <span class="manga-info-key"><?php _e('Rating', 'toocheke-companion'); ?></span>
      <span class="manga-info-value">
        <?php echo esc_html($manga_rating ?? ''); ?>
      </span>
    </div>
  </div>

  <?php if (! empty($genres) && ! is_wp_error($genres)): ?>
    <div class="manga-info-row">
        <div class="manga-info-col">
            <span class="manga-info-key"><?php _e('Genres', 'toocheke-companion'); ?></span>
            <div class="manga-info-tags">
                <?php foreach ($genres as $genre):
                        $genre_slug = sanitize_title($genre->name);
                        $genre_url  = esc_url(add_query_arg('genre', $genre_slug, home_url()));
                    ?>
	    <span class="manga-info-tag">
	        <?php echo esc_html($genre->name); ?>
                </span>
	<?php endforeach; ?>

            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (! empty($publishers) && ! is_wp_error($publishers)): ?>
    <div class="manga-info-row">
        <div class="manga-info-col">
            <span class="manga-info-key"><?php _e('Publishers', 'toocheke-companion'); ?></span>
            <div class="manga-info-tags">

                            <?php foreach ($publishers as $publisher):
                                    $publisher_slug = sanitize_title($publisher->name);
                                    $publisher_url  = esc_url(add_query_arg('publisher', $publisher_slug, home_url()));
                                ?>
	    <span class="manga-info-tag">
	        <?php echo esc_html($publisher->name); ?>
                            </span>
	<?php endforeach; ?>

            </div>
        </div>
    </div>
<?php endif; ?>

</div>
        <!--./Series Information-->
 <hr class="toocheke-hr manga-hr" />

         <!--Volumes-->
         <h2><?php _e('Volumes', 'toocheke-companion'); ?></h2>
         <?php

             $args = [
                 'post_type'      => 'manga_volume',
                 'posts_per_page' => -1,
                 'orderby'        => 'volume_number', // assuming you store a numeric meta 'volume_number'
                 'order'          => 'ASC',
                 'meta_query'     => [
                     [
                         'key'     => 'series_id',
                         'value'   => $manga_series_id,
                         'compare' => '=',
                         'type'    => 'NUMERIC',
                     ],
                 ],
             ];

             $query        = new WP_Query($args);
             $volume_count = $query->found_posts; // number of volumes

         if ($volume_count > 0): ?>
<h4 class="mb-4 font-weight-normal">
    <em>
        <?php
            /* translators: %s: number of volumes */
            echo sprintf(
                _n('%s Volume', '%s Volumes', $volume_count, 'toocheke-companion'),
                esc_html($volume_count)
            );
        ?>
        </em>
    </h4>

    <div class="manga-related-list-container">
        <?php while ($query->have_posts()): $query->the_post();
    $templates->get_template_part('content', 'relatedmangavolume');
endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
<?php endif; ?>

                 <!--./Volumes-->

                        </div>
                     </div>
              <!--end content-->
