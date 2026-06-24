<?php
/**
 * Registers and renders every front-end shortcode the plugin provides
 * (latest/all/top-ten comics and chapters, series and character listings,
 * manga series/volume listings, archive shortcodes, etc.), most of which
 * delegate to a template part via Toocheke_Companion_Template_Loader.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Shortcodes
{
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
                add_shortcode('toocheke-first-manga-series', [$this, 'toocheke_first_manga_series_shortcode']);
                add_shortcode('toocheke-latest-manga-series', [$this, 'toocheke_latest_manga_series_shortcode']);
                add_shortcode('toocheke-first-manga-volume', [$this, 'toocheke_first_manga_volume_shortcode']);
                add_shortcode('toocheke-latest-manga-volume', [$this, 'toocheke_latest_manga_volume_shortcode']);
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
            }

            //Display Top Ten Comics
            public function toocheke_top_ten_comics_shortcode()
            {
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $output = $output . $templates->get_template_part('content', 'toptencomics');

             return ob_get_clean();
            }

            //Display Scheduled Comics
            public function toocheke_scheduled_comics_shortcode()
            {
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $output = $output . $templates->get_template_part('content', 'scheduledcomics');

             return ob_get_clean();
            }

            //Display Characters
            public function toocheke_characters_shortcode()
            {
                $output    = '';
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $output = $output . $templates->get_template_part('content', 'characters');

             return ob_get_clean();
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
            }

            //Display First Comic
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
            }

            //Display current year
            public function toocheke_current_year_shortcode()
            {
                return date('Y');
            }

           /**
 * All Manga Series shortcode (with filter form and infinite scroll)
 */
public function toocheke_all_manga_series_shortcode($atts)
{
    $default_atts = [
        'title' => '',
    ];
    $params = shortcode_atts($default_atts, $atts);

    // Handle filters from URL
    $selected_publisher = isset($_GET['publisher']) ? sanitize_text_field($_GET['publisher']) : '';
    $selected_genre     = isset($_GET['genre']) ? sanitize_text_field($_GET['genre']) : '';

    ob_start();
    $customizer_title = get_theme_mod('manga_series_setting');

    $section_title = !empty($params['title'])
        ? $params['title']
        : (!empty($customizer_title)
            ? $customizer_title
            : __('Manga Series', 'toocheke-companion')
        );

    ?>

    <hr class="toocheke-hr manga-hr" />
    <h2 id="manga-series-header">
        <?php echo esc_html($section_title); ?>
    </h2>

    <form id="manga-series-filter-form" class="manga-series-filter-form">
        <div class="filter-field">
            <label for="publisher"><?php esc_html_e('Publisher:', 'toocheke-companion'); ?></label>
            <select name="publisher" id="publisher">
                <option value=""><?php esc_html_e('All Publishers', 'toocheke-companion'); ?></option>
                <?php
                $publishers = get_terms(['taxonomy' => 'manga_publisher', 'hide_empty' => true]);
                if (!is_wp_error($publishers)) {
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
                if (!is_wp_error($genres)) {
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
            <button type="submit" class="btn-sm btn-danger btn">
                <?php esc_html_e('Filter', 'toocheke-companion'); ?>
            </button>
        </div>
    </form>

    <hr class="toocheke-hr manga-hr" />

    <!-- Populated by manga-filter.js -->
    <div id="manga-series-grid" class="manga-grid-list-container"></div>

    <!-- IntersectionObserver watches this to trigger next page load -->
    <div id="manga-series-sentinel"></div>

    <!-- Loading indicator -->
    <div id="manga-series-loading" style="display:none; text-align:center; padding: 1rem;">
        <span class="manga-button-spinner"></span>
        <?php esc_html_e('Loading...', 'toocheke-companion'); ?>
    </div>

    <?php

    // Flag that this shortcode was used on this page
    add_action('wp_footer', [$this, 'toocheke_enqueue_manga_filter_script']);

    return ob_get_clean();
}

public function toocheke_enqueue_manga_filter_script()
{
    wp_enqueue_script(
        'toocheke-manga-filter',
        TOOCHEKE_COMPANION_PLUGIN_URL . 'js/manga-filter.js',
        [],
        TOOCHEKE_COMPANION_VERSION,
        true
    );

    wp_localize_script('toocheke-manga-filter', 'toochekeMangaFilter', [
        'restUrl'     => esc_url_raw(rest_url('toocheke/v1/manga-series')),
        'filterLabel' => __('Filter', 'toocheke-companion'),
        'noResults'   => __('No series found.', 'toocheke-companion'),
        'loadingText' => __('Loading...', 'toocheke-companion'),
    ]);
}

            /**
             * Popular Manga Series shortcode
             */
            public function toocheke_popular_manga_series_shortcode($atts)
            {
                $default_atts = [
                    'title' => '',
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $templates = new Toocheke_Companion_Template_Loader;

                $query_pop_series = new WP_Query([
                    'post_type'      => 'manga_series',
                    'posts_per_page' => 4,
                    'orderby'        => 'meta_value_num',
                    'meta_key'       => '_post_like_count',
                    'order'          => 'DESC',
                ]);

                ob_start();

                $customizer_title = get_theme_mod('manga_popular_series_setting');

                $section_title = ! empty( $params['title'] )
                    ? $params['title']
                    : ( ! empty( $customizer_title )
                        ? $customizer_title
                        : __( 'Popular Series', 'toocheke-companion' )
                    );


                $template_args = [
                    'query'         => $query_pop_series,
                    'section_id'    => 'manga-popular-series-header',
                    'section_title' => esc_html( $section_title ),
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
                $default_atts = [
                    'title' => '',
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $templates = new Toocheke_Companion_Template_Loader;

                $query_pop_volumes = new WP_Query([
                    'post_type'      => 'manga_volume',
                    'posts_per_page' => 4,
                    'orderby'        => 'meta_value_num',
                    'meta_key'       => '_post_like_count',
                    'order'          => 'DESC',
                ]);

                ob_start();

                $customizer_title = get_theme_mod('manga_popular_volumes_setting');

                $section_title = ! empty( $params['title'] )
                    ? $params['title']
                    : ( ! empty( $customizer_title )
                        ? $customizer_title
                        : __( 'Popular Volumes', 'toocheke-companion' )
                    );

                $template_args = [
                    'query'         => $query_pop_volumes,
                    'section_id'    => 'manga-popular-volumes-header',
                    'section_title' => esc_html( $section_title ),
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
                $default_atts = [
                    'title' => '',
                ];
                $params    = shortcode_atts($default_atts, $atts);
                $templates = new Toocheke_Companion_Template_Loader;

                $query_pop_chapters = new WP_Query([
                    'post_type'      => 'manga_chapter',
                    'posts_per_page' => 4,
                    'orderby'        => 'meta_value_num',
                    'meta_key'       => '_post_like_count',
                    'order'          => 'DESC',
                ]);

                ob_start();

                $customizer_title = get_theme_mod('manga_popular_chapters_setting');

                $section_title = ! empty( $params['title'] )
                    ? $params['title']
                    : ( ! empty( $customizer_title )
                        ? $customizer_title
                        : __( 'Popular Chapters', 'toocheke-companion' )
                    );

                $template_args = [
                    'query'         => $query_pop_chapters,
                    'section_id'    => 'manga-popular-chapters-header',
                    'section_title' => esc_html( $section_title ),
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
             * First Manga Series shortcode
             */
            public function toocheke_first_manga_series_shortcode($atts) {
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $templates->get_template_part('content', 'firstmangaseries', ['series_order' => 'ASC']);
                return ob_get_clean();
            }

            /**
             * Latest Manga Series shortcode
             */
            public function toocheke_latest_manga_series_shortcode($atts) {
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $templates->get_template_part('content', 'latestmangaseries', ['series_order' => 'DESC']);
                return ob_get_clean();
            }

            /**
             * First Manga Volume shortcode
             */
            public function toocheke_first_manga_volume_shortcode($atts) {
				$default_atts = [
                    "sid" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);

                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }
				
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $templates->get_template_part('content', 'firstmangavolume', ['volume_order' => 'ASC']);
                return ob_get_clean();
            }

            /**
             * Latest Manga Volume shortcode
             */
            public function toocheke_latest_manga_volume_shortcode($atts) {
				$default_atts = [
                    "sid" => null,
                ];
                $params    = shortcode_atts($default_atts, $atts);

                if (! empty($params['sid'])) {
                    set_query_var('series_id', (int) $params['sid']);
                }
                $templates = new Toocheke_Companion_Template_Loader;
                ob_start();
                $templates->get_template_part('content', 'latestmangavolume', ['volume_order' => 'DESC']);
                return ob_get_clean();
            }

}
