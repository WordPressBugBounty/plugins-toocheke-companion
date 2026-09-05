<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Template part for displaying single comic
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */

$templates = new Toocheke_Companion_Template_Loader;
$display_comic_nav_above_comic = get_option('toocheke-comic-nav-above-comic') && 1 == get_option('toocheke-comic-nav-above-comic');
$display_minimal_comic_nav_above_comic = get_option('toocheke-comic-nav-above-comic-minimal') && 1 == get_option('toocheke-comic-nav-above-comic-minimal');
$comic_order = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
$series_id = get_query_var('series_id');
$click_to_next_comic = get_option('toocheke-click-comic-next') && 1 == get_option('toocheke-click-comic-next');
$next_link = toocheke_universal_get_next_comic_link($post->ID, 0, $series_id);
$companion = new Toocheke_Companion_Comic_Features();

// Whether/how to print the click-to-next-comic attributes on the
// ".click-to-next-wrapper" divs below. Printing this via a closure that
// calls esc_attr() at each of its two call sites (rather than building a
// pre-escaped '$next_attr' string once and echoing that string later)
// keeps the escaping call directly next to the actual output, which is
// both the safer pattern and what WordPress.org's Plugin Check scans for.
$has_next_link = (strlen($next_link[0]) > 0 && $click_to_next_comic);
$print_click_to_next_attrs = function () use ($next_link, $has_next_link) {
    if (! $has_next_link) {
        return;
    }
    printf(
        ' data-next-href="%1$s" data-next-title="%2$s" title="%2$s"',
        esc_attr($next_link[0]),
        esc_attr($next_link[1])
    );
};

?>

<div id="comic" class="single-comic-wrapper">

<?php
if ($display_comic_nav_above_comic) {

    if (!$series_id) {
        set_query_var('series_id', null);
    } else {
        set_query_var('series_id', $series_id);
    }
    set_query_var('below_comic', 0);

    set_query_var('display_minimal', $display_minimal_comic_nav_above_comic );
    
    if (is_singular('comic')) {
        $templates->get_template_part('content', 'comicnavigation');
    }

}
$comic_layout = get_option('toocheke-comic-layout-devices');
$wrapper_id = $comic_layout === '1' ? 'two-comic-options' : 'one-comic-option';
$allowed_tags = array(
    'img' => array(
        'src' => array(),
        'alt' => array(),
        'width' => array(),
        'height' => array(),
        'class' => array(),
    ),
);
echo '<div id="' . esc_attr($wrapper_id) . '">';
echo '<div id="spliced-comic">';
echo '<span class="default-lang">';
echo '<div class="click-to-next-wrapper"';
$print_click_to_next_attrs();
echo '>';
echo wp_kses(get_the_content(), $allowed_tags);
echo '</div>';
echo '</span>';
echo '</div>';
echo '<div id="unspliced-comic">';

echo '<span class="default-lang">';
echo '<div class="click-to-next-wrapper"';
$print_click_to_next_attrs();
echo '>';
echo wp_kses(get_post_meta($post->ID, 'desktop_comic_editor', true), $allowed_tags);
echo '</div>';
echo '</span>';

echo '</div>';
echo '</div>';
if ( is_singular('comic') ) {
     set_query_var('display_minimal', 0 );
$templates->get_template_part('content', 'comicnavigation');
}

?>
</div>
<?php
if ( is_singular('comic') ) {
$templates->get_template_part('content', 'comicblogpost');
}
?>
