=== Toocheke Companion ===
Author: LeeToo
Contributors: toocheke
Tags: webcomic, comic, webtoon, cartoon
Requires at least: 5.3
Tested up to: 6.8
Stable tag: 1.172
Text Domain: toocheke-companion
Donate link: https://www.patreon.com/toocheke
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Transform your WordPress theme into a platform for publishing your webcomics.


== Description ==

Toocheke Companion Website: [Toocheke Companion](https://leetoo.net/toocheke-companion/ "Toocheke Companion - Plugin for publishing your webcomic")

Looking to publish your comics on your WordPress website? Toocheke Companion adds to your WordPress theme, the ability to create and manage your comic posts. You can now add the ability to diplay comics to almost any WordPress theme. Works best with the Toocheke WordPress theme.

= Features =
- Ability to display comics posts in any theme.
- Easy customization with a variety of color schemes.
- Different layout options for the comic page on desktops and other larger devices.
- Mobile device-friendly(responsive) page layout
- Responsive comic(display one version of the comic for desktops and another version for mobile devices)
- Optimized for the webtoon/vertical scroll format comics
- Thumbnail navigation of the comic archive
- Segment your comics into chapters
- Add character and location tags for each of your comics
- Social share buttons
- Publish multiple comic series
- Age verification for NSFW comics.
- Four comic archive layout options(Thumnail list, Text list, Calendar & Gallery)
- Customizable comic navigation buttons.
- Chapter navigation.
- Supports like functionality for comics.
- Supports bookmarking of comics.
- Supports tracking of post views with a top ten feature.
- Supports bilingual comics.
- Can show comic archive as an infinite scroll(lazy load).

== Installation == 

- In your admin panel, go to the Plugins > Add New page.
- Just do a search for Toocheke Companion and the first result should be the one you're looking for.
- Click Install Now.
- Click Activate to use your new plugin right away.

= Displaying Comics Posts on Any Theme =
Toocheke Companion comes with built in features that can transform just about any WordPress theme into a webcomic publishing platform. All that is required is creating a page and adding your choice of comic related shortcodes. The following is a list features available with their corresponding shortcodes.

- Display a list of the comic "Series" in a grid layout with thumbnail images.
- Display a list of the comic "Chapters" in a grid layout with thumbnail images.
- Display a list of the latest six "Chapters" in a grid layout with thumbnail images.
- Display the first comic post with comic navigation buttons.
- Display the latest comic post with comic navigation buttons.
- Display a list of the latest comics with pagination.
- Display a list of top ten comics.
- Display a list of upcoming scheduled comics.
- Display a comic archive in a variety of formats(text list, thumbnail list, thumbnail gallery and calendar).
- Display the comic's characters.


`[toocheke-all-series comics_order="ASC/DESC" link_to="series/comic"]` 

`[toocheke-all-chapters sid="####"]`

`[toocheke-latest-chapters sid="####"]`

`[toocheke-first-comic]`

`[toocheke-first-comic sid="####"]`

`[toocheke-latest-comic]`

`[toocheke-latest-comic sid="####"]`

`[toocheke-latest-comics]`

`[toocheke-latest-comics sid="####"]`

`[toocheke-latest-comics limit="####"]`

`[toocheke-top-ten-comics]`

`[toocheke-scheduled-comics]`

`[toocheke-comic-archive]`

`[toocheke-comic-archive sid="####"]`

`[toocheke-characters]`

`[toocheke-collection-archive term="collection-slug"]`

`[toocheke-chapter-archive term="chapter-slug"]`

`[toocheke-tag-archive term="comic-tag-slug"]`

`[toocheke-location-archive term="location-slug"]`

`[toocheke-character-archive term="character-slug"]`


== Frequently Asked Questions ==

= The comic permalinks are not working. how do I get them to work? =

Go to your settings -> permalinks and just click save, this will flush out your old permalinks settings.

= Where can I get help with Toocheke Companion? =

https://leetoo.net/contact/

== Changelog ==
= 1.172=
* UPDATE. Enhanced like feature with bot check

= 1.171=
* FIX. Authors could not add comic posts. Added capability support for authors.

= 1.170=
* UPDATE. Added support for hover text.

= 1.169=
* UPDATE. Added support for sponsoring a comic.

= 1.168=
* UPDATE. Added shortcodes for displaying comic taxonomy archives.

= 1.167=
* FIX. Security fix for saving editor meta fields for comics

= 1.166=
* UPDATE. Added option to scroll past header when navigating through comic posts
* UPDATE. Added option to consolidate comic posts and regular blog posts into main feed.

= 1.165=
* UPDATE. Replaced Twitter icon with X icon for the social sharing button.

= 1.164=
* FIX. Removed content display for age verification popup

= 1.163=
* FIX. Reset of comic post variable from traditional navigation buttons to prevent chapters navigation conflict

= 1.162=
* FIX. Layout issues for comic archive shortcode

= 1.161=
* FIX. Display of comic archive

= 1.160=
* FIX. Formatting of paragraphs in comic blog post.

= 1.159=
* UPDATE. Added age verification support for individual comics and series.

= 1.158=
* FIX. Paragraph spacing for Rich text editors.

= 1.157=
* UPDATE. Expanded the default search results to include comic posts.

= 1.156=
* UPDATE. Added "limit" option for the latest comics shortcode.

= 1.155=
* UPDATE. Replaced sharing button images with fontawesome icons.

= 1.154=
* FIX. toocheke-latest-comic shortcode - comments and blog posts were not being displayed. Post conflict with chapter dropdown.

= 1.153=
* UPDATE. WhatsApp and LinkedIn social media share buttons

= 1.152=
* UPDATE. Bluesky and threads social media share buttons

= 1.151=
* UPDATE. Add short code link parameter for "toocheke-all-series"

= 1.150=
* FIX. Warning messages for sorting comics

= 1.149=
* FIX. Kofi link for support links.

= 1.148=
* UPDATE. Added sidebar override for series.

= 1.147=
* FIX. Paging for all chapters shortcode.

= 1.146=
* FIX. Bug in sort function for comics.

= 1.145=
* FIX. Ordering of likes and views in comics admin.

= 1.144=
* UPDATE. Added series filtering to chapters shortcode

= 1.143=
* FIX. Chapters navigation

= 1.142=
* UPDATE. Added comic archive navigation button

= 1.141=
* FIX. Getting the correct URL for the comic page for social medial sharing

= 1.140=
* UPDATE. Added support buttons

= 1.139=
* UPDATE. Added yearly archive options

= 1.138=
* FIX. Import error for comics

= 1.137=
* FIX. Import error for comics

= 1.136=
* UPDATE. Optimized import process for Comic Easel

= 1.135=
* UPDATE. Transcript meta

= 1.134=
* UPDATE. Added transcript field

= 1.133=
* UPDATE. Add parameter for ordering comics for the "toocheke-all-series" shortcode

= 1.132=
* UPDATE. Added locations/tags/characters to import process from Comic Easel. Optimized process for importing from Comic Easel

= 1.131=
* FIX. Displaying comments on the latest comic shortcode.

= 1.130=
* FIX. Adding option to delete audio file(for Premium only).

= 1.129=
* UPDATE. Changed function for handling random comic navigation.

= 1.128=
* FIX. Handling cacheing for random comic navigation.

= 1.127=
* UPDATE. Added meta data for comic's blog post.

= 1.126=
* UPDATE. Fixed layout for comic's blog post.

= 1.125=
* UPDATE. Displaying comic's blog post irrespective of theme.

= 1.124=
* UPDATE. Added display of analytics (views,comments,likes) irrespective of theme.

= 1.123=
* FIX. Counting comic post views irrespective of theme.

= 1.122=
* FIX. Added a workaround to handle age verification with caching enabled.

= 1.121=
* UPDATE. Added option to setup commenting behind a paywall.

= 1.120=
* FIX. Handling of age verification with cache plugin installed.

= 1.119=
* ADDITION. Archive options for displaying archives segmented by series

= 1.118=
* ADDITION. Archive options for displaying archives segmented by chapters

= 1.117=
* UPDATE. Option to support navigation to future comic posts(Premium only).

= 1.116=
* UPDATE. Option to choose which series is displayed on the home page of a traditional layout.

= 1.115=
* UPDATE. Added option for buying original art and print copies of a comic(Premium only).

= 1.114=
* FIX. Add check for posted custom fields.

= 1.113=
* Fixed saving of empty comic custom fields

= 1.112=
* Fixed ordering of comic characters

= 1.111=
* Updated copy link url

= 1.110=
* Updated code for copy link

= 1.109=
* Added shortcode support for ordering characters

= 1.108=
* Added ordering for characters.

= 1.107=
* Added scheduled comics shortcode.

= 1.106=
* Updated name of enqueued font awesome font to prevent conflict

= 1.105=
* Removed deprecated get_page_by_title().

= 1.104=
* Fixed chapter navigation

= 1.103=
* Added missing files.

= 1.102=
* Fixed chapter navigation error.

= 1.101=
* Added option to disable keyboard navigation.

= 1.100=
* Changed description on ordering option.

= 1.99=
* Added support for chapter navigation

= 1.98=
* Added option for displaying comic's blog post on webtoon layouts.

= 1.97=
* Fixed bulk edit of Patreon level.

= 1.96=
* Fixed shortcodes parameters.

= 1.95=
* Thumbnail archive fix.

= 1.94=
* Fix for comic navigation for latest comic shortcode.

= 1.93=
* Added shortcode support for series navigation.

= 1.92=
* Added shortcode for displaying first comic.

= 1.91=
* WordPress update compatibility.

= 1.90=
* Fixed call to function error.

= 1.89=
* Added  mobile device image option for series posts

= 1.88=
* Renaming of functions

= 1.87=
* Added support for displaying comic posts in any theme.

= 1.86=
* Added taxonomy cloud support for Comic tags, locations & characters.

= 1.85=
* Added ordering option for Collections

= 1.84=
* Handling empty string for audio metabox

= 1.83=
* Added ordering option for the comic slider navigation.

= 1.82=
* Infinite scroll of comic archive on home page.

= 1.81 =
* Added notice for ComicPress to Comic Easel migration.

= 1.80 =
* Added support for post views.

= 1.79 =
* Added option to enable blog post listing on series landing page.

= 1.78 =
* Added option to allow discussion on comic posts on the home page.
* Streamlined option menu by adding tabs.

= 1.77 =
* Update to functionality for clicking to the next comic.

= 1.76 =
* Added support for navigating to the next comic on clicking a comic's image

= 1.75 =
* Support for background image on series page template
* Support for background color on series page template

= 1.74 =
* Add publicize support on comic posts for JetPack

= 1.73 =
* Setting the first comic order for chapter links

= 1.72 =
* Ordering of comic series

= 1.71 =
* Fixed bookmark nav function

= 1.70 =
* Copy update on options page

= 1.69 =
* Added support for comic navigation buttons above the comic(traditional layouts)
* Added bookmark menu item for navigation bar

= 1.68 =
* Fixed missing toocheke_image_click_message function

= 1.67 =
* Added support for bilingual comic

= 1.66 =
* Updated hide/display blog feature

= 1.65 =
* Added option to hide or display blog posts

= 1.64 =
* Added support for keyboard navigation of comics

= 1.63 =
* Fixed thumbnail image column in admin

= 1.62 =
* Added option to display/hide comic analytics
* Added featured image column
* Added likes feature

= 1.61 =
* Fixed Comic Archive Layout option dropdown

= 1.60 =
* Add feature to allow for disabling of the click to enlarge image on comic images.

= 1.59 =
* Fixed age verification bug.

= 1.58 =
* Changed image link filtering to comic pages only.

= 1.57 =
* Support for editing patreon in bulk.
* Removing image link from comic images.

= 1.56 =
* Age verification for NSFW comics.

= 1.55 =
* Added option to display list of all comics on multiple series home page.

= 1.54 =
* Support for custom social buttons
* Ability to hide/show random button

= 1.53 =
* Fixed comic's blog post editor to allow for paragraphs.

= 1.52 =
* Changed positioning of desktop comic and blog editor fields

= 1.51 =
* Adding series to quick and bulk edit

= 1.50 =
* Removed comic paragraph

= 1.49 =
* Added support for swipe navigation like Instagram

= 1.48 =
* Updated comments form

= 1.47 =
* Updated icons

= 1.46 =
* Fixed comments URL

= 1.45 =
* Making menu visible to editor roles

= 1.44 =
* Added new files

= 1.43 =
* Added series feature
* Centralized all Toocheke menus under one main menu

= 1.42 =
* Changed input type for chapter order

= 1.41 =
* Validation of collections and chapters

= 1.40 =
* Removed old navigation icons

= 1.39 =
* Changed default image for comic navigation

= 1.38 =
* Setting default comic navigation option

= 1.37 =
* Added media js

= 1.36 =
* Added functionality for changing comic navigation

= 1.35 =
* Updated icon

= 1.34 =
* Updated icon

= 1.33 =
* Updated icon

= 1.32 =
* Fixed resources path

= 1.31 =
* Added some plugin options
* Consolidated option pages into one
* Added plugin icon

= 1.30 =
* Fixed enqueue character image script bug

= 1.29 =
* Updated enqueue character image script

= 1.28 =
* Fixed character image bug

= 1.27 =
* Added character image

= 1.26 =
* Added character page

= 1.25 =
* Changed description field to WYSIWYG taxonomy
* Added comic sorting option

= 1.24 =
* Fixed audio save bug

= 1.23 =
* Added audio to comic post type.

= 1.22 =
* Comic Blog Editor

= 1.21 =
* Comic Easel bulk import - fixed variable declaration.

= 1.20 =
* Comic Easel bulk import - fixed SQL query.

= 1.19 =
* Comic Easel bulk import - switched from WP function to SQL query.

= 1.18 =
* Fixed Comic Easel bulk import.

= 1.17 =
* Updated Comic Easel fixed bulk import.

= 1.16 =
* Updated Comic Easel import functionality incase it fails during bulk import.

= 1.15 =
* Updated Comic Easel import functionality

= 1.14 =
* Added option for adding a blog post within the comic

= 1.13 =
* Added option for two versions of the comic on each post

= 1.12 =
* Fixed saving bug for collections and chapters

= 1.11 =
* Added comic collections taxonomy

= 1.10 =
* Added social share sub-menu to comics main menu

= 1.9 =
* Added instructions for comic easel import

= 1.8 =
* Fix imported comic layout

= 1.7 =
* Added import for comic easel

= 1.6 =
* Updated post type for comic

= 1.5 =
* Changed post type for comic

= 1.4 =
* Added default image settings
* Updating comic number

= 1.3 =
* Added social sharing for comic pages

= 1.2 =
* Bug fix for toocheke_rewrite_flush

== Licenses & Credits ==

* Font Awesome Free 5.8.1 by @fontawesome is licensed under (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) https://fontawesome.com/license/free
* Gamajo_Template_Loader by Gary Jones licensed to use under the GPL-2.0 and later license (https://github.com/GaryJones/Gamajo-Template-Loader)



