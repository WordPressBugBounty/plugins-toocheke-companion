<?php
/**
 * Series Management Hub
 */
?>

<div class="wrap">
    <h1>Series Management</h1>
    <div class="toocheke-hub-grid">

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>All Series</h3>
                    <p>View and manage all series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-list-view"></span></div>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=series')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Add New Series</h3>
                    <p>Create a new series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=series')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Series Genres</h3>
                    <p>Manage genres for series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-tag"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=genres&post_type=series')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Series Tags</h3>
                    <p>Manage tags for series</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-tag"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=series_tags&post_type=series')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

    </div>
</div>
