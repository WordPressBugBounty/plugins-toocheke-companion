<?php
    /**
     * Template part for displaying the content for the a single manga volume post(single-manga_volume.php)
     *
     * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
     *
     * @package Toocheke
     */
    //intiailize volume variables
    $templates = new Toocheke_Companion_Template_Loader;
    $display_likes                = get_option('toocheke-comic-likes') && 1 == get_option('toocheke-comic-likes');
    $latest_chapter_link = $latest_chapter_title = $first_chapter_link = $first_chapter_title =  $manga_series_title = $manga_series_link  = $volume_formatted_release_date = false;
    $manga_volume_id = get_the_ID();
    $manga_series_id = get_post_meta(get_the_ID(), 'series_id', true);

    if ($manga_series_id) {
        $manga_series_title = get_the_title($manga_series_id);
        $manga_series_link  = get_permalink($manga_series_id);

    }
   

    // Step 2: Get the latest chapter from that volume (by chapter_number)
    $latest_chapter = get_posts([
        'post_type'      => 'manga_chapter',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'     => 'volume_id',
                'value'   => $manga_volume_id,
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

    // Get the first chapter in that volume
    $first_chapter = get_posts([
        'post_type'      => 'manga_chapter',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'     => 'volume_id',
                'value'   => $manga_volume_id,
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

    $volume_release_date = get_post_meta($manga_volume_id, 'release_date', true);
    if ( $volume_release_date ) {
    // Convert to timestamp
    $timestamp = strtotime( $volume_release_date );

    // Output formatted date as "Mar 5, 2019"
    $volume_formatted_release_date =  date_i18n( 'M j, Y', $timestamp );
}
    $volume_isbn  = get_post_meta($manga_volume_id, 'isbn', true);
    $volume_pages  = get_post_meta($manga_volume_id, 'pages', true);
    $volume_rating  = get_post_meta($manga_volume_id, 'rating', true);
    $volume_buy_digital_url  = get_post_meta($manga_volume_id, 'buy_digital_url', true);
    $volume_buy_print_url  = get_post_meta($manga_volume_id, 'buy_print_url', true);

     //intiailize previous and next volume variables

$current_number   = intval(get_post_meta($manga_volume_id, 'volume_number', true));

$previous_volume = null;
$next_volume     = null;

// Get previous volume
$prev_args = [
    'post_type'      => 'manga_volume',
    'posts_per_page' => 1,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'   => 'series_id',
            'value' => $manga_series_id,
        ],
        [
            'key'     => 'volume_number',
            'value'   => $current_number,
            'compare' => '<',
            'type'    => 'NUMERIC',
        ],
    ],
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'volume_number',
    'order'          => 'DESC', // closest smaller number
];
$prev_query = new WP_Query($prev_args);
if ($prev_query->have_posts()) {
    $previous_volume = $prev_query->posts[0];
}
wp_reset_postdata();

// Get next volume
$next_args = [
    'post_type'      => 'manga_volume',
    'posts_per_page' => 1,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'   => 'series_id',
            'value' => $manga_series_id,
        ],
        [
            'key'     => 'volume_number',
            'value'   => $current_number,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ],
    ],
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'volume_number',
    'order'          => 'ASC', // closest bigger number
];
$next_query = new WP_Query($next_args);
if ($next_query->have_posts()) {
    $next_volume = $next_query->posts[0];
}
wp_reset_postdata();

//Volumes carousel
if ($manga_series_id) {
    $volumes_query = new WP_Query([
        'post_type'      => 'manga_volume',
        'posts_per_page' => -1,
        'meta_key'       => 'volume_number',  // order by volume_number
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'series_id',
                'value'   => $manga_series_id,
                'compare' => '='
            ]
        ]
    ]);
}

?>

                     <!--start content-->
                           <nav aria-label="breadcrumb">
  <ol class="manga-breadcrumb breadcrumb">
    <?php if($manga_series_title && $manga_series_link ):?>
      <li class="breadcrumb-item"><a href="<?php echo esc_url($manga_series_link); ?>" title="<?php echo esc_attr($manga_series_title); ?>"><?php echo esc_html($manga_series_title); ?></a></li>
      <?php endif;?>
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
    the_content();
?>

     <?php
    if ($display_likes):
?>
<p>
		            <b><?php esc_html_e('Like this:', 'toocheke-companion'); ?></b> <span class="single-comic-total-likes">
		               <?php echo do_shortcode("[toocheke-like-button]"); ?>
		</span>
        </p>
		<?php
            endif;
        ?>
<?php if ($latest_chapter_link): ?>
    <div class="manga-series-nav">
    <a class="btn btn-outline-black btn-sm btn-manga-chapter-link btn-manga-first-chapter" title="<?php echo esc_attr($first_chapter_title) ?>" href="<?php echo esc_url($first_chapter_link); ?>"><i class="fas fa-lg fa-step-backward fa-fw"></i> <?php esc_html_e('Read First Chapter', 'toocheke-companion'); ?></a>
<a class="btn btn-outline-black btn-sm btn-manga-chapter-link btn-manga-latest-chapter" title="<?php echo esc_attr($latest_chapter_title) ?>" href="<?php echo esc_url($latest_chapter_link); ?>"><?php esc_html_e('Read Latest Chapter', 'toocheke-companion'); ?> <i class="fas fa-lg fa-step-forward fa-fw"></i></a>
    </div>

<?php endif; ?>
                        </div>
                     </div>
                     <hr class="toocheke-hr manga-hr" />
                     <div class="manga-row">
                        <div class="col-lg-12">
                            <!--Volume Information-->
<h2><?php _e('Volume Information', 'toocheke-companion'); ?></h2>
<div class="manga-info-table">

  <div class="manga-info-row">
    <div class="manga-info-col">
      <span class="manga-info-key"><?php _e('Release Date', 'toocheke-companion'); ?></span>
      <span class="manga-info-value">
        <?php echo esc_html($volume_formatted_release_date ?? ''); ?>
      </span>
    </div>
    <div class="manga-info-col">
      <span class="manga-info-key"><?php _e('ISBN', 'toocheke-companion'); ?></span>
      <span class="manga-info-value">
        <?php echo esc_html($volume_isbn ?? ''); ?>
      </span>
    </div>
  </div>

    <div class="manga-info-row">
    <div class="manga-info-col">
      <span class="manga-info-key"><?php _e('Rating', 'toocheke-companion'); ?></span>
      <span class="manga-info-value">
        <?php echo esc_html($volume_rating ?? ''); ?>
      </span>
    </div>
    <div class="manga-info-col">
      <span class="manga-info-key"><?php _e('Pages', 'toocheke-companion'); ?></span>
      <span class="manga-info-value">
        <?php echo esc_html($volume_pages ?? ''); ?>
      </span>
    </div>
  </div>

</div>
 <hr class="toocheke-hr manga-hr" />
        <!--./Volume Information-->

 <?php if($volume_buy_digital_url || $volume_buy_print_url):?>
       <!--Volume Purchasing-->
       <h2><?php _e('Buy', 'toocheke-companion'); ?></h2>
      
        <div class="manga-volume-purchase-options">
<?php
    $buy_links = [
        'digital' => [
            'url'   => $volume_buy_digital_url ?? '',
            'label' => __('Buy Digital', 'toocheke-companion'),
            'title' => __('Buy Digital Copy', 'toocheke-companion'),
        ],
        'print'   => [
            'url'   => $volume_buy_print_url ?? '',
            'label' => __('Buy Print', 'toocheke-companion'),
            'title' => __('Buy Print Copy', 'toocheke-companion'),
        ],
    ];

    // Get the site's host for comparison
    $site_host = parse_url(home_url(), PHP_URL_HOST);

    foreach ($buy_links as $link):
        if (! empty($link['url'])):
            $link_host   = parse_url($link['url'], PHP_URL_HOST);
            $is_external = $link_host && $link_host !== $site_host;
        ?>
		        <a class="btn btn-outline-black btn-lg btn-manga-volume-buy-link"
		           title="<?php echo esc_attr($link['title']); ?>"
		           href="<?php echo esc_url($link['url']); ?>"
		           <?php if ($is_external): ?>target="_blank" rel="noopener"<?php endif; ?>>
	            <?php echo esc_html($link['label']); ?>
	        </a>
	    <?php endif;
            endforeach;
        ?>

</div>
  <hr class="toocheke-hr manga-hr" />
        <!--./Volume Purchasing-->

    <?php endif;?>
         <!--Previous and Next Volumes-->
         <?php if($previous_volume || $next_volume):?>
             <div class="manga-related-list-container">
       
<?php if ( $previous_volume ) : ?>
             <div class="manga-prev-volume-col">
<?php
           printf(
    '<a class="manga-prev-volume manga-volume-nav" href="%s" title="%s"><i class="fas fa-lg fa-chevron-left"></i> %s</a>',
    esc_url( get_permalink( $previous_volume ) ),                  
    esc_attr( get_the_title( $previous_volume ) ),        
    esc_html__( 'Previous Volume', 'toocheke-companion' )                
);
    $post = $previous_volume;
    setup_postdata($post);
    $templates->get_template_part('content', 'relatedmangavolume');
    wp_reset_postdata();
?>
 </div>
<?php endif; ?>
               
                 

<?php if ( $next_volume ) : ?>
     <div class="manga-next-volume-col">
<?php
    printf(
    '<a class="manga-next-volume manga-volume-nav" href="%s" title="%s">%s <i class="fas fa-lg fa-chevron-right"></i></a>',
    esc_url( get_permalink( $next_volume ) ),                  
    esc_attr( get_the_title( $next_volume ) ),        
    esc_html__( 'Next Volume', 'toocheke-companion' )                
);

    $post = $next_volume;
    setup_postdata($post);
    $templates->get_template_part('content', 'relatedmangavolume');
    wp_reset_postdata();
    ?>
     </div>
<?php endif; ?>

       
                 
         
            </div>
              <hr class="toocheke-hr manga-hr" />
            <?php endif;?>
          <!--./Previous and Next Volumes-->
          
         <!--Chapters-->
         <h2><?php _e('Chapters', 'toocheke-companion'); ?></h2>
         <?php

             $args = [
                 'post_type'      => 'manga_chapter',
                 'posts_per_page' => -1,
                 'orderby'        => 'chapter_number', // assuming you store a numeric meta 'chapter_number'
                 'order'          => 'ASC',
                 'meta_query'     => [
                     [
                         'key'     => 'volume_id',
                         'value'   => $manga_volume_id,
                         'compare' => '=',
                         'type'    => 'NUMERIC',
                     ],
                 ],
             ];

             $query        = new WP_Query($args);
             $chapter_count = $query->found_posts; // number of chapters

         if ($chapter_count > 0): ?>
<h4 class="mb-4 font-weight-normal">
    <em>
        <?php
            /* translators: %s: number of chapters */
            echo sprintf(
                _n('%s Chapter', '%s Chapters', $chapter_count, 'toocheke-companion'),
                esc_html($chapter_count)
            );
        ?>
        </em>
    </h4>

    <div class="manga-related-list-container">
        <?php while ($query->have_posts()): $query->the_post(); ?>
	        <?php
                    $manga_chapter_id        = get_the_ID();
                    $release_date           = get_post_meta($manga_chapter_id, 'release_date', true);
                    $pages                  = get_post_meta($manga_chapter_id, 'pages', true);
                    //$notes                  = get_post_meta($manga_chapter_id, 'notes', true);
               




                    $formatted_release_date = false;
                    if ($release_date) {
                        $date_obj               = new DateTime($release_date);
                        $formatted_release_date = $date_obj->format('M. d, Y'); // Mar. 05, 2019
                    }
               

                ?>
	            <div class="manga-related-item-container fade-in">
	                 <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
	                <div class="manga-related-item-thumbnail manga-thumbnail">

	                    <?php if (has_post_thumbnail()): ?>
	                        <?php the_post_thumbnail('full'); ?>
	                    <?php else: ?>
                        <img src="<?php echo esc_url(plugins_url('toocheke-companion/img/no-image.png')); ?>" alt="<?php the_title_attribute(); ?>" />
                    <?php endif; ?>

                </div>
                </a>
                <div class="manga-related-info-container">
                     <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                        <h3 class="manga-related-info-title"><?php the_title(); ?></h3>
                    </a>
                    <div class="manga-data-pages"><span><?php echo esc_html($formatted_release_date ?? ''); ?></span> | <span><?php
    /* translators: %s: number of pages */
    echo sprintf(
        _n('%s page', '%s pages', $pages, 'toocheke-companion'),
        esc_html($pages)
);
?></span></div>
        <a href="<?php echo esc_url(get_permalink()); ?>"
   title="<?php printf( esc_attr__( 'Read %s', 'toocheke-companion' ), get_the_title() ); ?>">

   <?php _e('READ', 'toocheke-companion'); ?>
</a>


                </div>

                    </div>
        <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
<?php endif; ?>

                 <!--./Chapters-->

                        </div>
                     </div>
              <!--end content-->
