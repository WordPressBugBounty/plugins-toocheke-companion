<?php
/**
 * Manga Management Hub
 */
?>

<div class="wrap">
    <h1>Manga Management</h1>
    <div class="toocheke-hub-grid">

        <div class="toocheke-hub-item manga-series-all">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>All Manga Series</h3>
                    <p>View and manage all manga series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-list-view"></span></div>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=manga_series')); ?>" class="toocheke-hub-item-footer">Open <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item manga-series-add">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Add Manga Series</h3>
                    <p>Create a new manga series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=manga_series')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item manga-genre">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Manga Series Genres</h3>
                    <p>Manage genres for manga series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-tag"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=manga_genre&post_type=manga_series')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item manga-publisher">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Manga Series Publishers</h3>
                    <p>Manage publishers for manga series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-building"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=manga_publisher&post_type=manga_series')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
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

    </div>
</div>
