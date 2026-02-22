<?php
/**
 * Premium Features Hub (inside your toocheke_admin_page())
 */
$theme = wp_get_theme();
if ('Toocheke Premium' != $theme->name && 'Toocheke Premium' != $theme->parent_theme) return;
?>

<div class="wrap">
    <h1>Premium Features</h1>
    <div class="toocheke-hub-grid">

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>All Slides</h3>
                    <p>View and manage all hero slides</p>
                </div>
                <div class="toocheke-hub-item-icon">
                    <span class="dashicons dashicons-images-alt"></span>
                </div>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=slide')); ?>" class="toocheke-hub-item-footer">
                    Open <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Add Slide</h3>
                    <p>Create a new hero slide</p>
                </div>
                <div class="toocheke-hub-item-icon">
                    <span class="dashicons dashicons-plus-alt"></span>
                </div>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=slide')); ?>" class="toocheke-hub-item-footer">
                    Add <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
            </div>
        </div>

        <div class="toocheke-hub-item">
            <div class="toocheke-hub-item-box">
                <div class="toocheke-hub-item-info">
                    <h3>Comic Sponsorships</h3>
                    <p>Manage sponsored comics</p>
                </div>
                <div class="toocheke-hub-item-icon">
                    <span class="dashicons dashicons-megaphone"></span>
                </div>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=comic_sponsorship')); ?>" class="toocheke-hub-item-footer">
                    Manage <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
            </div>
        </div>

    </div>
</div>
