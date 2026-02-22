<?php
    /**
     * Admin dashboard page (inside your toocheke_admin_page())
     */
    $theme = wp_get_theme(); // existing in your function
?>

<div class="wrap">
  <!-- HERO / intro kept as you already have -->
 <h1>Welcome to Toocheke!</h1>
<img src="<?php echo esc_url(plugins_url('toocheke-companion' . '/img/ToochekeWPAdminDashboardHero.png')); ?>" alt="Toocheke Dashboard Hero">

<p><strong>Toocheke is a sleek, mobile-friendly WordPress theme designed specifically for webcomics. Whether you’re publishing a single series or multiple comic series, Toocheke makes it easy and enjoyable!</strong></p>

<h3>Why you’ll love Toocheke:</h3>
<ul>
    <li><strong>Fully responsive:</strong> Your comics look great on desktops, laptops, tablets, and mobile phones.</li>
    <li><strong>Flexible customization:</strong> Choose from multiple color schemes and style options to match your brand.</li>
    <li><strong>Variety of layouts:</strong> Different page layouts let you display comics exactly how you want.</li>
    <li><strong>Webtoon-ready:</strong> Optimized for vertical scrolling comics for a smooth reading experience.</li>
    <li><strong>Multiple series support:</strong> Easily manage several comic series from one website.</li>
    <li><strong>Manga layout:</strong> Dedicated support for Manga-style series, volumes, and chapters.</li>
</ul>

<p>And that’s just the beginning! Explore the dashboard to discover all the tools and features that make publishing your webcomic a breeze.</p>

  <!-- features list kept... -->

  <h2 style="margin-top:36px;">Quick Links</h2>
<div class="toocheke-hub-grid">
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>All Series</h3>
          <p>View and manage all series</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-list-view"></span></div>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=series')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>Add Series</h3>
           <p>Create a new series</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=series')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>

<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>All Comics</h3>
          <p>View and manage all comics</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-list-view"></span></div>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=comic')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>Add Comic</h3>
          <p>Create a new comic</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=comic')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>All Manga Series</h3>
          <p>View and manage all manga series</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-list-view"></span></div>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_series')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>Add Manga Series</h3>
          <p>Create a new manga series</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></div>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_series')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item manga-volumes-all">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>All Manga Volumes</h3>
                    <p>View and manage all manga volumes</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-list-view"></span></div>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_volume')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item manga-volume-add">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Add Manga Volume</h3>
                    <p>Create a new manga volume</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_volume')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item manga-chapters-all">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>All Manga Chapters</h3>
                    <p>View and manage all manga chapters</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-list-view"></span></div>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_chapter')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item manga-chapter-add">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Add Manga Chapter</h3>
                    <p>Create a new manga chapter</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_chapter')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>
 <!-- Premium (conditional) -->
    <?php if ('Toocheke Premium' == $theme->name || 'Toocheke Premium' == $theme->parent_theme): ?>
        <div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>All Slides</h3>
          <p>View and manage all hero slides</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-images-alt"></span></div>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=slide')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>Add Slide</h3>
          <p>Create a new hero slide</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=slide')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
            <?php endif; ?>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>Options</h3>
          <p>Configure theme & plugin settings</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-admin-generic"></span></div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=toocheke-options-page')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>Comic Easel Import</h3>
          <p>Import comics from the Comic Easel plugin</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-download"></span></div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=toocheke-import-comic-easel')); ?>" class="toocheke-hub-item-footer">Import <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>
<div class="toocheke-hub-item">
    <div class="toocheke-hub-item-box">
        <div class="toocheke-hub-item-info">
          <h3>Webcomic Import</h3>
          <p>Import comics from the Webcomic plugin</p>
        </div>
        <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-download"></span></div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=toocheke-import-webcomic')); ?>" class="toocheke-hub-item-footer">Import <span class="dashicons dashicons-arrow-right-alt2"></span></a>
</div>
</div>

</div>
  
<?php
