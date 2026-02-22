<?php
/**
 * Comic Management Hub
 */
?>

<div class="wrap">
    <h1>Comic Management</h1>
    <div class="toocheke-hub-grid">

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
                    <h3>Add New Comic</h3>
                    <p>Create a new comic</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-plus-alt"></span></div>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=comic')); ?>" class="toocheke-hub-item-footer">Add <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Comic Collections</h3>
                    <p>Manage comic collections</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-images-alt"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=collections&post_type=comic')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Comic Chapters</h3>
                    <p>Manage comic chapters</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-images-alt2"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=chapters&post_type=comic')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Comic Tags</h3>
                    <p>Manage comic tags</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-tag"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=comic_tags&post_type=comic')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Comic Locations</h3>
                    <p>Manage comic locations</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-location"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=comic_locations&post_type=comic')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Comic Characters</h3>
                    <p>Manage comic characters</p>
                </div>
                <div class="toocheke-hub-item-icon"><span class="dashicons dashicons-admin-users"></span></div>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=comic_characters&post_type=comic')); ?>" class="toocheke-hub-item-footer">Manage <span class="dashicons dashicons-arrow-right-alt2"></span></a>
            </div>
        </div>

    </div>
</div>
