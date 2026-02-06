=== Toocheke Companion ===
Author: LeeToo
Contributors: toocheke
Tags: webcomic, comic, webtoon, manga
Requires at least: 5.3
Tested up to: 6.9
Stable tag: 1.194
Text Domain: toocheke-companion
Donate link: https://www.patreon.com/toocheke
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Transform your WordPress theme into a platform for publishing your webcomics.


== Description ==

**Toocheke Companion Website:** [Toocheke Companion](https://leetoo.net/toocheke-companion/ "Toocheke Companion - Plugin for publishing your webcomic")

Looking to publish your comics or manga on your WordPress website? **Toocheke Companion** extends your WordPress theme with the ability to create, manage, and display comic and manga posts. While it works best with the **Toocheke WordPress theme**, it is compatible with most modern WordPress themes.

### Key Features

- Display comics and manga in nearly any WordPress theme
- Full support for the WordPress Block Editor (Gutenberg)
- Includes 15 blocks and matching shortcodes for flexible comic layouts
- Easy customization with color schemes and layout options
- **New: Dedicated Manga layout support** — organize and display manga with series, volumes, and chapters
- Responsive layout optimized for mobile and desktop
- Dual-version responsive comic support (desktop/mobile)
- Webtoon-style vertical scrolling support
- Thumbnail-based archive navigation
- Organize comics into series, chapters, collections and character/location tags
- Built-in social sharing, likes, bookmarks, and age verification
- Top ten comics based on post views
- Infinite scroll for archive display
- Bilingual/multilingual comic and manga support

== Installation == 

1. Go to **Plugins > Add New** in your WordPress admin dashboard.
2. Search for **Toocheke Companion**.
3. Click **Install Now** and then **Activate**.

== Usage ==

Toocheke Companion enables you to display comics using either shortcodes or the WordPress Block Editor. Create a page or post and insert the desired block or shortcode.

### Available Blocks and Shortcodes

1. **Toocheke All Series**  
   `[toocheke-all-series comics_order="ASC/DESC" link_to="series/comic"]`

2. **Toocheke All Chapters**  
   `[toocheke-all-chapters sid="####"]`

3. **Toocheke Latest Chapters**  
   `[toocheke-latest-chapters sid="####"]`

4. **Toocheke First Comic**  
   `[toocheke-first-comic]` or `[toocheke-first-comic sid="####"]`

5. **Toocheke Latest Comic**  
   `[toocheke-latest-comic]` or `[toocheke-latest-comic sid="####"]`

6. **Toocheke Latest Comics**  
   `[toocheke-latest-comics]`, `[toocheke-latest-comics sid="####"]`, `[toocheke-latest-comics limit="####"]`

7. **Toocheke Top Ten Comics**  
   `[toocheke-top-ten-comics]`

8. **Toocheke Scheduled Comics**  
   `[toocheke-scheduled-comics]`

9. **Toocheke Comic Archive**  
   `[toocheke-comic-archive]` or `[toocheke-comic-archive sid="####"]`

10. **Toocheke Collection Archive**  
    `[toocheke-collection-archive term="collection-slug"]`

11. **Toocheke Chapter Archive**  
    `[toocheke-chapter-archive term="chapter-slug"]`

12. **Toocheke Tag Archive**  
    `[toocheke-tag-archive term="comic-tag-slug"]`

13. **Toocheke Location Archive**  
    `[toocheke-location-archive term="location-slug"]`

14. **Toocheke Character Archive**  
    `[toocheke-character-archive term="character-slug"]`

15. **Toocheke Characters**  
    `[toocheke-characters]`

All of the above are also available as Gutenberg blocks under the widgets category in the WordPress Block Editor. 

You can also display Manga features with the following shortcodes

1. **All Manga Series**  
    `[toocheke-all-manga-series]`

2. **Popular Manga Series**  
    `[toocheke-popular-manga-series]`

3. **Popular Manga Volumes**  
    `[toocheke-popular-manga-volumes]`

4. **Popular Manga Chapters**  
    `[toocheke-popular-manga-chapters]`


== Frequently Asked Questions ==

= The comic permalinks are not working. How do I fix them? =

Go to **Settings > Permalinks** and click **Save**. This will refresh your permalink settings.

= Where can I get support? =

Visit [https://leetoo.net/contact/](https://leetoo.net/contact/) for assistance.

== Changelog ==

= 1.194 =
* FIX: Resolved comic navigation for collections when using non-Toocheke themes.

= 1.193 =
* UPDATE: Added support for social sharing buttons when using non-Toocheke themes.

= 1.192 =
* FIX: Improved handling of Series IDs in chapter navigation buttons.
* FIX: Added additional bot-detection safeguards to prevent automated crawling from inflating like counts.

= 1.191 =
* UPDATE: Comic blog post fields now support embedded YouTube videos with secure iframe handling

= 1.190 =
* UPDATE: Added the ability to set "Buy Comic" pricing directly on individual comic posts, in addition to the existing global pricing options. This allows per-comic pricing. **Premium only**.

= 1.189 =
* UPDATE: Confirmed compatibility with WordPress 6.9.

= 1.188 =
* UPDATE: Added support for right-to-left (RTL) reading, enabling proper layout for languages such as Japanese.

= 1.187 =
* FIX: Resolved an issue where the comic list was not displaying correctly.

= 1.186 =
* UPDATE: Added option to set the default manga page view (single page or two-page spread).

= 1.185 =
* NEW. Added zoom functionality for a better manga reading experience on mobile devices. Now you can easily zoom in and out for improved readability and navigation.
* UPDATE. Made strings on manga-related pages translatable, enabling better localization support for different languages.

= 1.184 =
* FIX. Minor styling and performance improvements.

= 1.183 =
* NEW. Added full Manga layout support, including dedicated series, volume, and chapter pages and support for Patreon and ChamaWP.
* FIX. Minor styling and performance improvements.

= 1.181 =
* UPDATE. Added option to decide whether to always show comic navigation buttons, even if there isn’t a first, previous, next, or last comic available.

= 1.180 =
* UPDATE. Added shortcode support for displaying the current year.

= 1.179 =
* UPDATE: Enhanced the Webcomic plugin import process — comics are now only assigned to a series when multiple series/collections exist, preventing unnecessary series assignment for comics that may disrupt comic navigation.

= 1.178 =
* NEW: Added ability to import comics from the Webcomic plugin

= 1.177 =
* FIX: Alt hover effect for desktop version of comic

= 1.176 =
* FIX: Fixed an issue where the alt hover effect was not being applied correctly.

= 1.175 =
* FIX: Resolved alt hover issue not working on 3rd party themes.

= 1.174 =
* ENHANCEMENT: Introduced full Gutenberg block editor support.
* Added 15 custom blocks for displaying series, chapters, comic posts, characters, archives, and more.

= 1.173 =
* UPDATE. Added support for blocks

= 1.172 =
* UPDATE. Enhanced like feature with bot check

= 1.171 =
* FIX. Authors could not add comic posts. Added capability support for authors.

= 1.170 =
* UPDATE. Added support for hover text.

= 1.169 =
* UPDATE. Added support for sponsoring a comic.

= 1.168 =
* UPDATE. Added shortcodes for displaying comic taxonomy archives.

= 1.167 =
* FIX. Security fix for saving editor meta fields for comics

= 1.166 =
* UPDATE. Added option to scroll past header when navigating through comic posts
* UPDATE. Added option to consolidate comic posts and regular blog posts into main feed.

= 1.165 =
* UPDATE. Replaced Twitter icon with X icon for the social sharing button.

= 1.164 =
* FIX. Removed content display for age verification popup

= 1.163 =
* FIX. Reset of comic post variable from traditional navigation buttons to prevent chapters navigation conflict

= 1.162 =
* FIX. Layout issues for comic archive shortcode

= 1.161 =
* FIX. Display of comic archive

= 1.160 =
* FIX. Formatting of paragraphs in comic blog post.

= 1.159 =
* UPDATE. Added age verification support for individual comics and series.

= 1.158 =
* FIX. Paragraph spacing for Rich text editors.

= 1.157 =
* UPDATE. Expanded the default search results to include comic posts.

= 1.156 =
* UPDATE. Added "limit" option for the latest comics shortcode.

= 1.155 =
* UPDATE. Replaced sharing button images with fontawesome icons.

= 1.154 =
* FIX. toocheke-latest-comic shortcode - comments and blog posts were not being displayed. Post conflict with chapter dropdown.

= 1.153 =
* UPDATE. WhatsApp and LinkedIn social media share buttons

= 1.152 =
* UPDATE. Bluesky and threads social media share buttons

= 1.151 =
* UPDATE. Add short code link parameter for "toocheke-all-series"

= 1.150 =
* FIX. Warning messages for sorting comics

= 1.149 =
* FIX. Kofi link for support links.

= 1.148 =
* UPDATE. Added sidebar override for series.

= 1.147 =
* FIX. Paging for all chapters shortcode.

= 1.146 =
* FIX. Bug in sort function for comics.

= 1.145 =
* FIX. Ordering of likes and views in comics admin.

= 1.144 =
* UPDATE. Added series filtering to chapters shortcode

= 1.143 =
* FIX. Chapters navigation

= 1.142 =
* UPDATE. Added comic archive navigation button

= 1.141 =
* FIX. Getting the correct URL for the comic page for social medial sharing

= 1.140 =
* UPDATE. Added support buttons

= 1.139 =
* UPDATE. Added yearly archive options

= 1.138 =
* FIX. Import error for comics

= 1.137 =
* FIX. Import error for comics

= 1.136 =
* UPDATE. Optimized import process for Comic Easel

= 1.135 =
* UPDATE. Transcript meta

= 1.134 =
* UPDATE. Added transcript field

= 1.133 =
* UPDATE. Add parameter for ordering comics for the "toocheke-all-series" shortcode

= 1.132 =
* UPDATE. Added locations/tags/characters to import process from Comic Easel. Optimized process for importing from Comic Easel

= 1.131 =
* FIX. Displaying comments on the latest comic shortcode.

= 1.130 =
* FIX. Adding option to delete audio file(for Premium only).

= 1.129 =
* UPDATE. Changed function for handling random comic navigation.

= 1.128 =
* FIX. Handling cacheing for random comic navigation.

= 1.127 =
* UPDATE. Added meta data for comic's blog post.

= 1.126 =
* UPDATE. Fixed layout for comic's blog post.

= 1.125 =
* UPDATE. Displaying comic's blog post irrespective of theme.

= 1.124 =
* UPDATE. Added display of analytics (views,comments,likes) irrespective of theme.

= 1.123 =
* FIX. Counting comic post views irrespective of theme.

= 1.122 =
* FIX. Added a workaround to handle age verification with caching enabled.

= 1.121 =
* UPDATE. Added option to setup commenting behind a paywall.

= 1.120 =
* FIX. Handling of age verification with cache plugin installed.

= 1.119 =
* ADDITION. Archive options for displaying archives segmented by series

= 1.118 =
* ADDITION. Archive options for displaying archives segmented by chapters

= 1.117 =
* UPDATE. Option to support navigation to future comic posts(Premium only).

= 1.116 =
* UPDATE. Option to choose which series is displayed on the home page of a traditional layout.

= 1.115 =
* UPDATE. Added option for buying original art and print copies of a comic(Premium only).

= 1.114 =
* FIX. Add check for posted custom fields.

= 1.113 =
* Fixed saving of empty comic custom fields

= 1.112 =
* Fixed ordering of comic characters

= 1.111 =
* Updated copy link url

= 1.110 =
* Updated code for copy link

= 1.109 =
* Added shortcode support for ordering characters

= 1.108 =
* Added ordering for characters.

= 1.107 =
* Added scheduled comics shortcode.

= 1.106 =
* Updated name of enqueued font awesome font to prevent conflict

= 1.105 =
* Removed deprecated get_page_by_title().

= 1.104 =
* Fixed chapter navigation

= 1.103 =
* Added missing files.

= 1.102 =
* Fixed chapter navigation error.

= 1.101 =
* Added option to disable keyboard navigation.

= 1.100 =
* Changed description on ordering option.

= 1.99 =
* Added support for chapter navigation

= 1.98 =
* Added option for displaying comic's blog post on webtoon layouts.

= 1.97 =
* Fixed bulk edit of Patreon level.

= 1.96 =
* Fixed shortcodes parameters.

= 1.95 =
* Thumbnail archive fix.

= 1.94 =
* Fix for comic navigation for latest comic shortcode.

= 1.93 =
* Added shortcode support for series navigation.

= 1.92 =
* Added shortcode for displaying first comic.

= 1.91 =
* WordPress update compatibility.

= 1.90 =
* Fixed call to function error.

= 1.89 =
* Added  mobile device image option for series posts

= 1.88 =
* Renaming of functions

= 1.87 =
* Added support for displaying comic posts in any theme.

= 1.86 =
* Added taxonomy cloud support for Comic tags, locations & characters.

= 1.85 =
* Added ordering option for Collections

= 1.84 =
* Handling empty string for audio metabox

= 1.83 =
* Added ordering option for the comic slider navigation.

= 1.82 =
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
* jQuery Fullscreen 0.6.0 Copyright (c) 2016 Vladimir Zhuravlev are licensed under MIT (https://github.com/private-face/jquery.fullscreen/blob/master/LICENSE)
* Swiper 11.2.10 Copyright 2014-2025 Vladimir Kharlampidi are licensed under MIT (https://github.com/nolimits4web/swiper/blob/master/LICENSE)



