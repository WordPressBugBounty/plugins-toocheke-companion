<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Template part for displaying list of comics
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package snbtoocheke
 */
$series_id = get_query_var('series_id');
$col_id = get_query_var('col_id');
$comic_url = get_permalink();
if ($series_id) {
    $comic_url = add_query_arg('sid', $series_id, $comic_url);
}
if ($col_id) {
    $comic_url = add_query_arg('col', $col_id, $comic_url);
}
?>

<?php
echo '<span class="comic-thumbnail-wrapper">';
if ( get_the_post_thumbnail(get_the_ID()) != '' ) {

    toocheke_companion_comic_link_open($comic_url);
    the_post_thumbnail('full');
    toocheke_companion_comic_link_close();
  
  } else {
    
   toocheke_companion_comic_link_open($comic_url);
   echo '<img src="';
   echo esc_attr(toocheke_universal_get_first_image());
   echo '" alt="" />';
   toocheke_companion_comic_link_close();
 
  
  }
  echo '<br/>';
  echo get_the_date();
  echo '</span>';

