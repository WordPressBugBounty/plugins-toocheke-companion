<?php
/**
 * Toocheke Companion — Import from Webtoons.
 *
 * Lets a comic creator migrate one or more series (Originals or
 * Canvas) off Webtoons.
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (! defined('TOOCHEKE_WEBTOONS_IMPORT_OPTION')) {
    define('TOOCHEKE_WEBTOONS_IMPORT_OPTION', 'toocheke_webtoons_import_job');
}

// Hosts this importer is allowed to request — pages from webtoons.com,
// images from the pstatic.net CDN.
if (! defined('TOOCHEKE_WEBTOONS_ALLOWED_HOST_SUFFIX')) {
    define('TOOCHEKE_WEBTOONS_ALLOWED_HOST_SUFFIX', 'webtoons.com');
}
if (! defined('TOOCHEKE_WEBTOONS_IMAGE_HOST_SUFFIX')) {
    define('TOOCHEKE_WEBTOONS_IMAGE_HOST_SUFFIX', 'pstatic.net');
}

trait Toocheke_Companion_Import_Webtoons
{
    protected function toocheke_webtoons_max_image_attempts()
    {
        return 3;
    }

    // Called once from init() in toocheke-companion.php.
    public function toocheke_webtoons_register_hooks()
    {
        if (! is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'toocheke_webtoons_enqueue_admin_assets']);
        add_action('admin_notices',         [$this, 'toocheke_webtoons_admin_error_notice']);

        add_action('wp_ajax_toocheke_webtoons_import_start',   [$this, 'toocheke_webtoons_ajax_start']);
        add_action('wp_ajax_toocheke_webtoons_import_step',    [$this, 'toocheke_webtoons_ajax_step']);
        add_action('wp_ajax_toocheke_webtoons_import_status',  [$this, 'toocheke_webtoons_ajax_status']);
        add_action('wp_ajax_toocheke_webtoons_import_discard', [$this, 'toocheke_webtoons_ajax_discard']);
        add_action('wp_ajax_toocheke_webtoons_import_retry',   [$this, 'toocheke_webtoons_ajax_retry_series']);
        add_action('wp_ajax_toocheke_webtoons_dismiss_error',  [$this, 'toocheke_webtoons_ajax_dismiss_error']);

        add_action('wp_ajax_toocheke_webtoons_cleanup_start', [$this, 'toocheke_webtoons_ajax_cleanup_start']);
        add_action('wp_ajax_toocheke_webtoons_cleanup_step',  [$this, 'toocheke_webtoons_ajax_cleanup_step']);
        add_action('wp_ajax_toocheke_webtoons_cleanup_status', [$this, 'toocheke_webtoons_ajax_cleanup_status']);

        add_action('wp_ajax_toocheke_webtoons_repair_start',  [$this, 'toocheke_webtoons_ajax_repair_start']);
        add_action('wp_ajax_toocheke_webtoons_repair_step',   [$this, 'toocheke_webtoons_ajax_repair_step']);
        add_action('wp_ajax_toocheke_webtoons_repair_status', [$this, 'toocheke_webtoons_ajax_repair_status']);
    }

    public function toocheke_webtoons_enqueue_admin_assets($hook)
    {
        // This page hangs off our own top-level menu, so $hook won't be
        // 'admin.php' — just check the page slug instead.
        if (empty($_GET['page']) || 'toocheke-import-webtoons' !== $_GET['page']) {
            return;
        }

        $css_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'css/toocheke-webtoons-import.css';
        $js_path  = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/toocheke-webtoons-import.js';

        wp_enqueue_style(
            'toocheke-webtoons-import',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'css/toocheke-webtoons-import.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : TOOCHEKE_COMPANION_VERSION
        );

        wp_enqueue_script(
            'toocheke-webtoons-import',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'js/toocheke-webtoons-import.js',
            ['jquery'],
            file_exists($js_path) ? filemtime($js_path) : TOOCHEKE_COMPANION_VERSION,
            true
        );

        wp_localize_script('toocheke-webtoons-import', 'toochekeWebtoonsImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('toocheke_webtoons_import'),
            'diag'    => [
                'pluginVersion' => TOOCHEKE_COMPANION_VERSION,
                'wpVersion'     => get_bloginfo('version'),
                'phpVersion'    => PHP_VERSION,
            ],
            'i18n'    => [
                'resolving'      => __('Reading series page…', 'toocheke-companion'),
                /* translators: %d: number of episodes found so far */
                'indexing'       => __('Reading the episode list… (%d found so far)', 'toocheke-companion'),
                /* translators: %s: series title or slug */
                'importing'      => __('Importing “%s”…', 'toocheke-companion'),
                /* translators: 1: series title or slug, 2: number of episodes imported */
                'seriesDone'     => __('Finished “%1$s” — %2$d episode(s) imported.', 'toocheke-companion'),
                'allDone'        => __('All done! Your Webtoons series have been imported.', 'toocheke-companion'),
                /* translators: %d: seconds until the next automatic retry */
                'throttled'      => __('Webtoons is rate-limiting requests. Retrying automatically in %d seconds…', 'toocheke-companion'),
                'throttledManual'=> __('Webtoons is still rate-limiting requests. Click “Resume Import” to keep going whenever you\'re ready.', 'toocheke-companion'),
                /* translators: %s: the underlying error message */
                'genericError'   => __('Something went wrong: %s', 'toocheke-companion'),
                'confirmDiscard' => __('Discard the current import and start over? Anything already imported will stay on your site.', 'toocheke-companion'),
                'confirmStart'   => __('This will start importing the series listed above. Continue?', 'toocheke-companion'),
                /* translators: 1: episodes repaired so far, 2: total episodes being repaired */
                'repairing'      => __('Repaired %1$d of %2$d episodes…', 'toocheke-companion'),
                /* translators: %d: number of episodes repaired */
                'repairDone'     => __('Repair finished — %d episode(s) fixed.', 'toocheke-companion'),
                /* translators: %s: comma-separated episode titles */
                'repairStill'    => __('Still missing panels after 3 tries: %s. Webtoons may be refusing those images — try again later.', 'toocheke-companion'),
            ],
        ]);
    }

    public function toocheke_webtoons_render_import_page()
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'toocheke-companion'));
        }

        $job = $this->toocheke_webtoons_get_job();
        $has_existing_job = ! empty($job['series']) && 'completed' !== $job['status'];
        ?>
        <div class="wrap toocheke-webtoons-import-wrap">
            <h2><?php esc_html_e('Import From Webtoons', 'toocheke-companion'); ?></h2>

            <div class="notice notice-warning">
                <p>
                    <b><?php esc_html_e('Please note!', 'toocheke-companion'); ?></b>
                    <?php esc_html_e('To avoid any rate limiting from Webtoons, the import process is intentionally paced, so a large series (hundreds or thousands of episodes) can take a while — you can safely close this page and come back later to resume exactly where it left off.', 'toocheke-companion'); ?>
                    <?php esc_html_e('Only episodes Webtoons serves for free to anonymous visitors can be imported — locked/paid (Daily Pass or Fast Pass) and mature-gated episodes will be skipped.', 'toocheke-companion'); ?>
                </p>
            </div>

            <h3><?php esc_html_e('Series to import', 'toocheke-companion'); ?></h3>
            <p>
                <?php esc_html_e('Enter one or more Webtoons series URLs — a Canvas series (e.g. https://www.webtoons.com/en/canvas/your-series/list?title_no=12345) or an Original (e.g. https://www.webtoons.com/en/comedy/your-series/list?title_no=123). Each will become its own Series post here, with every episode imported as a Comic post in the series.', 'toocheke-companion'); ?>
            </p>

            <p class="toocheke-webtoons-agreement">
                <label>
                    <input type="checkbox" id="toocheke-webtoons-agree" />
                    <b style="color:#c00;"><?php esc_html_e('Yes, I confirm I have the legal right to import these comics — I own the copyright, or I have the copyright holder\'s permission.', 'toocheke-companion'); ?></b>
                </label><br />
                <span class="description"><?php esc_html_e('This import feature is offered in good faith. Please respect the copyright of the comics you import.', 'toocheke-companion'); ?></span>
            </p>

            <div id="toocheke-webtoons-url-rows" class="toocheke-webtoons-url-rows">
                <div class="toocheke-webtoons-url-row">
                    <input type="url" class="regular-text toocheke-webtoons-url-input" placeholder="https://www.webtoons.com/en/canvas/your-series/list?title_no=12345" />
                    <button type="button" class="button toocheke-webtoons-remove-row" aria-label="<?php esc_attr_e('Remove', 'toocheke-companion'); ?>">&times;</button>
                </div>
            </div>
            <p>
                <button type="button" id="toocheke-webtoons-add-row" class="button"><?php esc_html_e('+ Add another series', 'toocheke-companion'); ?></button>
            </p>

            <p class="submit">
                <button type="button" id="toocheke-webtoons-start" class="button button-primary" disabled><?php esc_html_e('Start Import', 'toocheke-companion'); ?></button>
                <button type="button" id="toocheke-webtoons-resume" class="button button-primary" style="<?php echo $has_existing_job ? '' : 'display:none;'; ?>"><?php esc_html_e('Resume Import', 'toocheke-companion'); ?></button>
                <button type="button" id="toocheke-webtoons-discard" class="button" style="<?php echo $has_existing_job ? '' : 'display:none;'; ?>"><?php esc_html_e('Discard & Start Over', 'toocheke-companion'); ?></button>
            </p>

            <div id="toocheke-webtoons-progress" class="toocheke-webtoons-progress" style="display:none;">
                <div class="toocheke-webtoons-overall">
                    <div class="toocheke-webtoons-overall-label"></div>
                    <div class="toocheke-webtoons-progressbar toocheke-webtoons-progressbar--overall">
                        <div class="toocheke-webtoons-progressbar-fill"></div>
                    </div>
                </div>
                <div class="toocheke-webtoons-series-card">
                    <h4 class="toocheke-webtoons-series-title"></h4>
                    <div class="toocheke-webtoons-progressbar toocheke-webtoons-progressbar--series">
                        <div class="toocheke-webtoons-progressbar-fill"></div>
                    </div>
                    <p class="toocheke-webtoons-series-status"></p>
                    <p class="toocheke-webtoons-copy-row" style="display:none;">
                        <button type="button" class="button button-primary toocheke-webtoons-retry-series"><?php esc_html_e('Retry This Series', 'toocheke-companion'); ?></button>
                        <button type="button" class="button toocheke-webtoons-copy-report"><?php esc_html_e('Copy Diagnostic Report', 'toocheke-companion'); ?></button>
                        <span class="toocheke-webtoons-copy-confirm" style="display:none;color:#00a32a;"><?php esc_html_e('Copied!', 'toocheke-companion'); ?></span>
                    </p>
                </div>
                <div class="toocheke-webtoons-log"></div>
            </div>

            <div id="toocheke-webtoons-existing-job" style="<?php echo $has_existing_job ? '' : 'display:none;'; ?>">
                <p><em><?php esc_html_e('You have an import in progress. Click “Resume Import” above to continue it, or “Discard & Start Over” to abandon it (anything already imported stays on your site either way).', 'toocheke-companion'); ?></em></p>
            </div>

            <?php $flagged_episodes = $this->toocheke_webtoons_get_flagged_episodes(); ?>
            <?php if ($flagged_episodes || is_array(get_option('toocheke_webtoons_repair_job'))) : ?>
            <div class="toocheke-webtoons-attention" id="toocheke-webtoons-attention">
                <h3><?php esc_html_e('Episodes needing attention', 'toocheke-companion'); ?></h3>
                <p>
                    <?php esc_html_e('These episodes imported, but some panels wouldn\'t download. Repair checks each one on Webtoons again and downloads only the panels that are missing.', 'toocheke-companion'); ?>
                </p>
                <?php if ($flagged_episodes) : ?>
                <table class="widefat striped toocheke-webtoons-attention-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Episode', 'toocheke-companion'); ?></th>
                            <th><?php esc_html_e('Series', 'toocheke-companion'); ?></th>
                            <th><?php esc_html_e('Missing', 'toocheke-companion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flagged_episodes as $episode) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url(get_edit_post_link($episode['post_id'])); ?>"><?php echo esc_html($episode['title']); ?></a></td>
                            <td><?php echo esc_html($episode['series']); ?></td>
                            <td><?php echo esc_html(sprintf(
                                /* translators: %d: number of missing panels */
                                _n('%d panel', '%d panels', $episode['missing'], 'toocheke-companion'),
                                $episode['missing']
                            )); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <p>
                    <button type="button" id="toocheke-webtoons-repair-start" class="button button-primary">
                        <?php echo esc_html(sprintf(
                            /* translators: %d: number of flagged episodes */
                            _n('Repair %d episode', 'Repair %d episodes', count($flagged_episodes), 'toocheke-companion'),
                            count($flagged_episodes)
                        )); ?>
                    </button>
                </p>
                <div id="toocheke-webtoons-repair-progress" style="display:none;">
                    <div class="toocheke-webtoons-progressbar toocheke-webtoons-progressbar--repair">
                        <div class="toocheke-webtoons-progressbar-fill"></div>
                    </div>
                    <p class="toocheke-webtoons-repair-status"></p>
                </div>
            </div>
            <?php endif; ?>

            <?php $cleanup_candidates = $this->toocheke_webtoons_get_cleanup_candidates(); ?>
            <?php if ($cleanup_candidates) : ?>
            <div class="toocheke-webtoons-danger-zone">
                <h3><?php esc_html_e('Start a series over', 'toocheke-companion'); ?></h3>
                <p>
                    <?php esc_html_e('If an import for one of these series went wrong — duplicate images, a scene stuck in a loop, anything that looks broken — this permanently deletes that series\' Series post, every Comic post under it, and every Media Library file attached to those posts, so you can re-run the import on a clean slate. This cannot be undone.', 'toocheke-companion'); ?>
                </p>
                <p>
                    <select id="toocheke-webtoons-cleanup-select">
                        <option value=""><?php esc_html_e('Choose a series…', 'toocheke-companion'); ?></option>
                        <?php foreach ($cleanup_candidates as $c) : ?>
                            <option value="<?php echo esc_attr($c['post_id']); ?>" data-title="<?php echo esc_attr($c['title']); ?>">
                                <?php echo esc_html($c['title']); ?> (<?php echo esc_html(sprintf(
                                    /* translators: %d: number of Comic posts under this series */
                                    _n('%d comic', '%d comics', $c['count'], 'toocheke-companion'),
                                    $c['count']
                                )); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="toocheke-webtoons-cleanup-confirm-text">
                        <?php esc_html_e('Type the series title above exactly to confirm:', 'toocheke-companion'); ?>
                    </label><br />
                    <input type="text" id="toocheke-webtoons-cleanup-confirm-text" class="regular-text" autocomplete="off" />
                </p>
                <p>
                    <button type="button" id="toocheke-webtoons-cleanup-start" class="button" style="color:#c00;border-color:#c00;" disabled>
                        <?php esc_html_e('Permanently Delete & Start Over', 'toocheke-companion'); ?>
                    </button>
                </p>
                <div id="toocheke-webtoons-cleanup-progress" style="display:none;">
                    <div class="toocheke-webtoons-progressbar toocheke-webtoons-progressbar--cleanup">
                        <div class="toocheke-webtoons-progressbar-fill"></div>
                    </div>
                    <p class="toocheke-webtoons-cleanup-status"></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // Job state is stored in a single, not-autoloaded option so a large
    // import survives a closed tab, a PHP timeout, or a page reload.
    // Job state is stored in a single, not-autoloaded option so a large
    // import survives a closed tab, a PHP timeout, or a page reload.
    protected function toocheke_webtoons_default_job()
    {
        return [
            'status'        => 'idle', // idle | running | throttled | completed | error
            'current_index' => 0,
            'series'        => [],
            'updated'       => time(),
        ];
    }

    protected function toocheke_webtoons_get_job()
    {
        $job = get_option(TOOCHEKE_WEBTOONS_IMPORT_OPTION, null);
        if (! is_array($job) || empty($job['series']) || ! is_array($job['series'])) {
            return $this->toocheke_webtoons_default_job();
        }
        return wp_parse_args($job, $this->toocheke_webtoons_default_job());
    }

    protected function toocheke_webtoons_save_job($job)
    {
        $job['updated'] = time();
        update_option(TOOCHEKE_WEBTOONS_IMPORT_OPTION, $job, false);
    }

    protected function toocheke_webtoons_delete_job()
    {
        delete_option(TOOCHEKE_WEBTOONS_IMPORT_OPTION);
    }

    // Doubles the wait on each consecutive strike (capped at 10 minutes)
    // so a stubborn 429/503 or anti-bot block gets more patience over
    // time instead of hammering Webtoons on a fixed interval.
    protected function toocheke_webtoons_apply_backoff_strike(array &$entry, array $fetch)
    {
        $entry['throttle_strikes'] = isset($entry['throttle_strikes']) ? $entry['throttle_strikes'] + 1 : 1;

        if ($entry['throttle_strikes'] > 8) {
            // Give up and let the creator retry manually.
            return ['throttled' => false, 'gave_up' => true];
        }

        $base   = ! empty($fetch['retry_after']) ? (int) $fetch['retry_after'] : $this->toocheke_webtoons_backoff_seconds();
        $waited = min(600, $base * (2 ** min(5, $entry['throttle_strikes'] - 1)));

        // Only log every so often, not on every single strike, so a long
        // stretch of backoff-and-retry doesn't flood the rolling log.
        if (1 === $entry['throttle_strikes'] || 0 === $entry['throttle_strikes'] % 3) {
            $this->toocheke_webtoons_log($entry, sprintf(
                /* translators: 1: attempt count, 2: seconds until the next retry */
                __('Webtoons is temporarily blocking requests (attempt %1$d) — waiting %2$ds before trying this page again.', 'toocheke-companion'),
                $entry['throttle_strikes'],
                $waited
            ));
        }

        return ['throttled' => true, 'retry_after' => $waited];
    }

    protected function toocheke_webtoons_reset_backoff_strikes(array &$entry)
    {
        $entry['throttle_strikes'] = 0;
    }

    // Rolling log for one series, capped so it doesn't grow forever.
    protected function toocheke_webtoons_log(array &$series_entry, $message)
    {
        if (empty($series_entry['log']) || ! is_array($series_entry['log'])) {
            $series_entry['log'] = [];
        }
        $series_entry['log'][] = $message;
        if (count($series_entry['log']) > 12) {
            $series_entry['log'] = array_slice($series_entry['log'], -12);
        }
    }

    // Tracks every episode with a problem this run, so the summary can
    // list all of them instead of just the most recent one.
    protected function toocheke_webtoons_flag_episode(array &$entry, $webtoons_episode_id, $message)
    {
        if (empty($entry['flagged_episodes']) || ! is_array($entry['flagged_episodes'])) {
            $entry['flagged_episodes'] = [];
        }
        $entry['flagged_episodes'][] = [
            'episode_id' => $webtoons_episode_id,
            'message'    => $message,
            'time'       => current_time('mysql'),
        ];
        if (count($entry['flagged_episodes']) > 25) {
            $entry['flagged_episodes'] = array_slice($entry['flagged_episodes'], -25);
        }
    }

    // Stored separately from the job state so it survives a Discard.
    // Shows up as an admin notice plus a "Copy Diagnostic Report" button.
    protected function toocheke_webtoons_record_failure(array &$entry, $webtoons_episode_id = null)
    {
        $this->toocheke_webtoons_flag_episode($entry, $webtoons_episode_id, $entry['error']);

        update_option('toocheke_webtoons_import_last_error', [
            'type'             => 'error',
            'message'          => $entry['error'],
            'series_slug'      => $entry['slug'],
            'series_url'       => $entry['url'],
            'webtoons_episode_id' => $webtoons_episode_id,
            'flagged_episodes' => $entry['flagged_episodes'],
            'time'             => current_time('mysql'),
            'plugin_version'   => TOOCHEKE_COMPANION_VERSION,
            'wp_version'       => get_bloginfo('version'),
            'php_version'      => PHP_VERSION,
        ], false);
    }

    // Same as above, but for a "finished, just short of Webtoons' own
    // episode count" case rather than a hard failure.
    protected function toocheke_webtoons_record_incomplete_notice(array &$entry, $actual_count)
    {
        $this->toocheke_webtoons_flag_episode($entry, $entry['last_episode_id'], $entry['warning']);
        $this->toocheke_webtoons_write_error_option($entry, 'incomplete');
    }

    // Same option write, but skips adding another flag entry when the
    // episodes were already flagged individually.
    protected function toocheke_webtoons_record_existing_flags(array &$entry)
    {
        $this->toocheke_webtoons_write_error_option($entry, 'incomplete');
    }

    protected function toocheke_webtoons_write_error_option(array $entry, $type)
    {
        update_option('toocheke_webtoons_import_last_error', [
            'type'             => $type,
            'message'          => $entry['warning'],
            'series_slug'      => $entry['slug'],
            'series_url'       => $entry['url'],
            'webtoons_episode_id' => $entry['last_episode_id'],
            'flagged_episodes' => $entry['flagged_episodes'],
            'time'             => current_time('mysql'),
            'plugin_version'   => TOOCHEKE_COMPANION_VERSION,
            'wp_version'       => get_bloginfo('version'),
            'php_version'      => PHP_VERSION,
        ], false);
    }

    // Shows on every wp-admin page, not just the import page, so it's
    // seen even if the creator has navigated away while it's running.
    public function toocheke_webtoons_admin_error_notice()
    {
        if (! current_user_can('edit_posts')) {
            return;
        }

        $err = get_option('toocheke_webtoons_import_last_error');
        if (empty($err) || empty($err['message'])) {
            return;
        }

        $is_incomplete = isset($err['type']) && 'incomplete' === $err['type'];

        $report  = 'Toocheke Companion — Webtoons Import ' . ($is_incomplete ? 'Shortfall' : 'Error') . " Report\n";
        $report .= 'Plugin version: ' . $err['plugin_version'] . "\n";
        $report .= 'WordPress version: ' . $err['wp_version'] . "\n";
        $report .= 'PHP version: ' . $err['php_version'] . "\n";
        $report .= 'Series: ' . $err['series_slug'] . ' (' . $err['series_url'] . ")\n";
        if (! empty($err['webtoons_episode_id'])) {
            $report .= ($is_incomplete ? 'Stopped after Webtoons episode #' : 'Failed on Webtoons episode #') . $err['webtoons_episode_id'] . "\n";
        }
        $report .= 'When: ' . $err['time'] . " (site time)\n";
        $report .= ($is_incomplete ? 'Detail: ' : 'Error: ') . $err['message'] . "\n";

        $flagged = ! empty($err['flagged_episodes']) && is_array($err['flagged_episodes']) ? $err['flagged_episodes'] : [];
        if (count($flagged) > 1) {
            $report .= "\nAll episodes flagged this run for this series:\n";
            foreach ($flagged as $f) {
                $report .= '- #' . $f['episode_id'] . ': ' . $f['message'] . "\n";
            }
        }

        $notice_id = 'toocheke-webtoons-error-notice';
        ?>
        <div id="<?php echo esc_attr($notice_id); ?>" class="notice <?php echo $is_incomplete ? 'notice-warning' : 'notice-error'; ?>">
            <p>
                <b>
                <?php
                echo $is_incomplete
                    ? esc_html__('Webtoons Import may be missing some episodes', 'toocheke-companion')
                    : esc_html__('Webtoons Import ran into a problem', 'toocheke-companion');
                ?>
                </b><br />
                <?php
                if ($is_incomplete) {
                    printf(
                        /* translators: 1: series slug, 2: shortfall detail */
                        esc_html__('Series “%1$s” finished, but %2$s', 'toocheke-companion'),
                        esc_html($err['series_slug']),
                        esc_html($err['message'])
                    );
                } else {
                    printf(
                        /* translators: 1: series slug, 2: error message */
                        esc_html__('Series “%1$s” stopped importing: %2$s', 'toocheke-companion'),
                        esc_html($err['series_slug']),
                        esc_html($err['message'])
                    );
                }
                ?>
            </p>
            <?php if (count($flagged) > 1) : ?>
            <p>
                <?php
                printf(
                    /* translators: %d: number of episodes flagged */
                    esc_html__('%d episodes were flagged in this series during this run — see the full list in the report below.', 'toocheke-companion'),
                    count($flagged)
                );
                ?>
            </p>
            <?php endif; ?>
            <p>
                <?php esc_html_e('If you need help, copy the report below and send it to your developer.', 'toocheke-companion'); ?>
            </p>
            <p>
                <textarea id="<?php echo esc_attr($notice_id); ?>-report" readonly rows="7" style="width:100%;max-width:640px;font-family:Consolas,Monaco,monospace;font-size:12px;"><?php echo esc_textarea($report); ?></textarea>
            </p>
            <p>
                <button type="button" class="button" id="<?php echo esc_attr($notice_id); ?>-copy"><?php esc_html_e('Copy Diagnostic Report', 'toocheke-companion'); ?></button>
                <button type="button" class="button" id="<?php echo esc_attr($notice_id); ?>-dismiss"><?php esc_html_e('Dismiss', 'toocheke-companion'); ?></button>
                <span id="<?php echo esc_attr($notice_id); ?>-copied" style="display:none;color:#00a32a;margin-left:8px;"><?php esc_html_e('Copied!', 'toocheke-companion'); ?></span>
            </p>
        </div>
        <script>
        (function () {
            var wrap   = document.getElementById('<?php echo esc_js($notice_id); ?>');
            var report = document.getElementById('<?php echo esc_js($notice_id); ?>-report');
            var copied = document.getElementById('<?php echo esc_js($notice_id); ?>-copied');

            document.getElementById('<?php echo esc_js($notice_id); ?>-copy').addEventListener('click', function () {
                report.select();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(report.value);
                } else {
                    document.execCommand('copy');
                }
                copied.style.display = 'inline';
                setTimeout(function () { copied.style.display = 'none'; }, 2000);
            });

            document.getElementById('<?php echo esc_js($notice_id); ?>-dismiss').addEventListener('click', function () {
                var data = new URLSearchParams();
                data.append('action', 'toocheke_webtoons_dismiss_error');
                data.append('nonce', '<?php echo esc_js(wp_create_nonce('toocheke_webtoons_import')); ?>');
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data });
                wrap.remove();
            });
        })();
        </script>
        <?php
    }

    public function toocheke_webtoons_ajax_dismiss_error()
    {
        $this->toocheke_webtoons_ajax_guard();
        delete_option('toocheke_webtoons_import_last_error');
        wp_send_json_success();
    }

    // Stricter than the regular import guard — this deletes real posts
    // and Media Library files, so it requires delete_posts on top of
    // the usual nonce/capability check.
    protected function toocheke_webtoons_cleanup_ajax_guard()
    {
        $this->toocheke_webtoons_ajax_guard();
        if (! current_user_can('delete_posts')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'toocheke-companion')], 403);
        }
    }

    // Series eligible for the "start this series over" tool — any
    // local Series post this importer created, regardless of whether
    // its import ever finished or is currently tracked in the job
    // option (a creator may have already discarded that, or the page
    // may have been reloaded since).
    protected function toocheke_webtoons_get_cleanup_candidates()
    {
        $posts = get_posts([
            'post_type'      => 'series',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_key'       => '_toocheke_webtoons_series_id',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'post_id' => $p->ID,
                'title'   => get_the_title($p),
                'count'   => $this->toocheke_webtoons_count_comics_for_series($p->ID),
            ];
        }
        return $out;
    }

    // Starts a batched delete of one series' local copy: every Comic
    // post created for it, all Media Library files attached to each of
    // those, and finally the Series post itself. Scoped by
    // '_toocheke_webtoons_episode_id'/'_toocheke_webtoons_series_id' meta
    // rather than post_parent alone, since that's the same authoritative
    // link the importer itself uses to recognize "this post belongs to
    // this series" — matches even if post_parent ever drifted.
    public function toocheke_webtoons_ajax_cleanup_start()
    {
        $this->toocheke_webtoons_cleanup_ajax_guard();

        $series_post_id = isset($_POST['series_post_id']) ? absint($_POST['series_post_id']) : 0;
        $series_post     = $series_post_id ? get_post($series_post_id) : null;

        if (! $series_post || 'series' !== $series_post->post_type || ! get_post_meta($series_post_id, '_toocheke_webtoons_series_id', true)) {
            wp_send_json_error(['message' => __('That doesn\'t look like a series this importer created.', 'toocheke-companion')]);
        }

        // Refuse to start if an import for this exact series is still
        // actively progressable — the per-step pause elsewhere catches
        // most of this race, but a request already in flight at the
        // instant this snapshot is taken could still slip through, so
        // this gives the person a clear, upfront reason instead.
        $series_url = get_post_meta($series_post_id, '_toocheke_webtoons_series_url', true);
        $import_job = $this->toocheke_webtoons_get_job();
        foreach ((array) $import_job['series'] as $import_entry) {
            if ($series_url && isset($import_entry['url']) && $import_entry['url'] === $series_url
                && in_array($import_entry['status'], ['pending', 'resolving', 'indexing', 'importing', 'throttled'], true)) {
                wp_send_json_error(['message' => __('An import for this series is still in progress. Stop it first (leave the import page, or click Discard & Start Over) before deleting it.', 'toocheke-companion')]);
            }
        }

        $comic_ids = get_posts([
            'post_type'      => 'comic',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post_parent'    => $series_post_id,
            'fields'         => 'ids',
        ]);

        $job = [
            'series_post_id'  => $series_post_id,
            'series_title'    => get_the_title($series_post),
            'series_url'      => get_post_meta($series_post_id, '_toocheke_webtoons_series_url', true),
            'remaining_comics' => array_map('intval', $comic_ids),
            'comics_total'    => count($comic_ids),
            'comics_done'     => 0,
            'attachments_deleted' => 0,
            'status'          => count($comic_ids) ? 'running' : 'deleting_series',
        ];

        update_option('toocheke_webtoons_cleanup_job', $job, false);

        wp_send_json_success(['job' => $job]);
    }

    // One unit of work: delete one Comic post's attachments and the
    // post itself, or — once every comic is gone — the Series post and
    // any import-job/error state that referenced it. Batched the same
    // way the import itself is, so deleting a series with thousands of
    // attached files can never hit a single request's time limit.
    public function toocheke_webtoons_ajax_cleanup_step()
    {
        $this->toocheke_webtoons_cleanup_ajax_guard();

        $job = get_option('toocheke_webtoons_cleanup_job');
        if (! is_array($job)) {
            wp_send_json_error(['message' => __('No cleanup in progress.', 'toocheke-companion')]);
        }

        if (! empty($job['remaining_comics'])) {
            $comic_id = array_shift($job['remaining_comics']);

            $attachments = get_posts([
                'post_type'      => 'attachment',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'post_parent'    => $comic_id,
                'fields'         => 'ids',
            ]);
            foreach ($attachments as $attachment_id) {
                if (wp_delete_attachment($attachment_id, true)) {
                    $job['attachments_deleted']++;
                }
            }

            wp_delete_post($comic_id, true);
            $job['comics_done']++;

            if (empty($job['remaining_comics'])) {
                $job['status'] = 'deleting_series';
            }

            update_option('toocheke_webtoons_cleanup_job', $job, false);
            wp_send_json_success(['job' => $job, 'done' => false]);
        }

        // Every comic is gone — remove the series post itself, and any
        // import-job/error state that still points at it, so a fresh
        // "Start Import" for this same URL begins completely clean.
        $title_no = get_post_meta($job['series_post_id'], '_toocheke_webtoons_series_id', true);
        if ($title_no) {
            delete_option($this->toocheke_webtoons_index_option($title_no));
        }

        // The series thumbnail is attached to the series post too.
        $series_attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post_parent'    => $job['series_post_id'],
            'fields'         => 'ids',
        ]);
        foreach ($series_attachments as $attachment_id) {
            if (wp_delete_attachment($attachment_id, true)) {
                $job['attachments_deleted']++;
            }
        }
        wp_delete_post($job['series_post_id'], true);

        $import_job = $this->toocheke_webtoons_get_job();
        if (! empty($import_job['series']) && is_array($import_job['series'])) {
            $import_job['series'] = array_values(array_filter($import_job['series'], function ($entry) use ($job) {
                return (int) $entry['series_post_id'] !== (int) $job['series_post_id'];
            }));
            if (empty($import_job['series'])) {
                delete_option(TOOCHEKE_WEBTOONS_IMPORT_OPTION);
            } else {
                update_option(TOOCHEKE_WEBTOONS_IMPORT_OPTION, $import_job, false);
            }
        }

        $last_error = get_option('toocheke_webtoons_import_last_error');
        if (is_array($last_error) && ! empty($job['series_url']) && isset($last_error['series_url']) && $last_error['series_url'] === $job['series_url']) {
            delete_option('toocheke_webtoons_import_last_error');
        }

        $job['status'] = 'done';
        delete_option('toocheke_webtoons_cleanup_job');

        wp_send_json_success(['job' => $job, 'done' => true]);
    }

    public function toocheke_webtoons_ajax_cleanup_status()
    {
        $this->toocheke_webtoons_cleanup_ajax_guard();
        $job = get_option('toocheke_webtoons_cleanup_job');
        wp_send_json_success(['job' => is_array($job) ? $job : null]);
    }

    // Comic posts that finished importing with panels that wouldn't download.
    protected function toocheke_webtoons_get_flagged_episodes()
    {
        $posts = get_posts([
            'post_type'      => 'comic',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_key'       => '_toocheke_webtoons_missing_panels',
            'meta_value'     => 0,
            'meta_compare'   => '>',
            'meta_type'      => 'NUMERIC',
            'orderby'        => 'date',
            'order'          => 'ASC',
        ]);

        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'post_id' => $p->ID,
                'title'   => get_the_title($p),
                'series'  => $p->post_parent ? get_the_title($p->post_parent) : '',
                'missing' => (int) get_post_meta($p->ID, '_toocheke_webtoons_missing_panels', true),
            ];
        }
        return $out;
    }

    public function toocheke_webtoons_ajax_repair_start()
    {
        $this->toocheke_webtoons_ajax_guard();

        if (is_array(get_option('toocheke_webtoons_cleanup_job'))) {
            wp_send_json_error(['message' => __('A “Start a series over” delete is still running — wait for it to finish first.', 'toocheke-companion')]);
        }

        $queue = [];
        foreach ($this->toocheke_webtoons_get_flagged_episodes() as $episode) {
            $queue[] = ['id' => (int) $episode['post_id'], 'tries' => 0];
        }
        if (! $queue) {
            wp_send_json_error(['message' => __('No episodes need repair.', 'toocheke-companion')]);
        }

        $job = [
            'queue'         => $queue,
            'total'         => count($queue),
            'fixed'         => 0,
            'still_missing' => [],
            'status'        => 'running',
        ];
        update_option('toocheke_webtoons_repair_job', $job, false);

        wp_send_json_success(['job' => $job]);
    }

    // One episode per call, sharing the import's step lock so the two
    // can never run a step at the same moment.
    public function toocheke_webtoons_ajax_repair_step()
    {
        $this->toocheke_webtoons_ajax_guard();

        $job = get_option('toocheke_webtoons_repair_job');
        if (! is_array($job)) {
            wp_send_json_error(['message' => __('No repair in progress.', 'toocheke-companion')]);
        }

        if (get_transient('toocheke_webtoons_step_lock')) {
            wp_send_json_success(['job' => $job, 'done' => false, 'throttled' => true, 'retry_after' => 10]);
        }
        set_transient('toocheke_webtoons_step_lock', 1, 150);
        @set_time_limit(120); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_time_limit

        $result = $this->toocheke_webtoons_repair_one($job);

        if (! empty($result['done'])) {
            delete_option('toocheke_webtoons_repair_job');
        } else {
            update_option('toocheke_webtoons_repair_job', $job, false);
        }
        delete_transient('toocheke_webtoons_step_lock');

        wp_send_json_success(['job' => $job, 'done' => ! empty($result['done']), 'throttled' => ! empty($result['throttled']), 'retry_after' => isset($result['retry_after']) ? $result['retry_after'] : 0]);
    }

    public function toocheke_webtoons_ajax_repair_status()
    {
        $this->toocheke_webtoons_ajax_guard();
        $job = get_option('toocheke_webtoons_repair_job');
        wp_send_json_success(['job' => is_array($job) ? $job : null]);
    }

    // Episode numbers restart at 1 in every series, so lookups are
    // always scoped to the Series post.
    protected function toocheke_webtoons_find_existing_comic_post($episode_id, $series_post_id, $episode_title = '')
    {
        $found = get_posts([
            'post_type'      => 'comic',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_parent'    => $series_post_id,
            'meta_key'       => '_toocheke_webtoons_episode_id',
            'meta_value'     => $episode_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        if (! empty($found)) {
            return (int) $found[0];
        }

        // Orphan from an interrupted step that never got tagged: same
        // series, same title, no episode tag yet. Tagged on the spot so
        // it's found normally from here on.
        if ($series_post_id && '' !== $episode_title) {
            $orphans = get_posts([
                'post_type'      => 'comic',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post_parent'    => $series_post_id,
                'title'          => wp_strip_all_tags($episode_title),
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    ['key' => '_toocheke_webtoons_episode_id', 'compare' => 'NOT EXISTS'],
                ],
            ]);
            if (! empty($orphans)) {
                $orphan_id = (int) $orphans[0];
                update_post_meta($orphan_id, '_toocheke_webtoons_episode_id', $episode_id);
                return $orphan_id;
            }
        }

        return 0;
    }

    // The viewer URL saved on the post, or one rebuilt from its series.
    protected function toocheke_webtoons_episode_url_for_post($post_id)
    {
        $url = get_post_meta($post_id, '_toocheke_webtoons_episode_url', true);
        if ($url) {
            return $url;
        }

        $episode_id = (int) get_post_meta($post_id, '_toocheke_webtoons_episode_id', true);
        $parent_id  = (int) wp_get_post_parent_id($post_id);
        $series_url = $parent_id ? get_post_meta($parent_id, '_toocheke_webtoons_series_url', true) : '';
        if (! $episode_id || ! $series_url) {
            return '';
        }

        return preg_replace('~/list\?title_no=(\d+)$~', '/e/viewer?title_no=$1&episode_no=' . $episode_id, $series_url);
    }

    // Re-fetches one flagged episode and downloads only the panels the
    // saved progress map doesn't already have.
    protected function toocheke_webtoons_repair_one(array &$job)
    {
        if (empty($job['queue'])) {
            $job['status'] = 'done';
            return ['done' => true];
        }

        $item    = array_shift($job['queue']);
        $post_id = (int) $item['id'];

        if ($post_id && get_post($post_id)) {
            $title   = get_the_title($post_id);
            $missing = (int) get_post_meta($post_id, '_toocheke_webtoons_missing_panels', true);
            $url     = $this->toocheke_webtoons_episode_url_for_post($post_id);
            $fetch   = $url ? $this->toocheke_webtoons_http_get($url) : ['body' => ''];

            if (! empty($fetch['throttled'])) {
                array_unshift($job['queue'], $item);
                return [
                    'throttled'    => true,
                    'retry_after'  => $fetch['retry_after'],
                    'pause_reason' => __('Webtoons is rate-limiting requests — waiting before repairing the next episode.', 'toocheke-companion'),
                ];
            }

            $data = ! empty($fetch['body']) ? $this->toocheke_webtoons_parse_viewer_html($fetch['body']) : [];

            if (! empty($data['images'])) {
                $missing  = 0;
                $pending  = 0;
                $deadline = microtime(true) + $this->toocheke_webtoons_step_budget();
                $content  = $this->toocheke_webtoons_build_comic_content($data['images'], $post_id, $title, $missing, $deadline, $pending);

                if ($pending > 0) {
                    // Out of time — carry on with this episode next call.
                    array_unshift($job['queue'], $item);
                    return ['throttled' => true, 'retry_after' => 1];
                }

                if ('' !== $content) {
                    wp_update_post(['ID' => $post_id, 'post_content' => $content]);
                }
                if (0 === $missing) {
                    delete_post_meta($post_id, '_toocheke_webtoons_missing_panels');
                    delete_post_meta($post_id, '_toocheke_webtoons_import_attempts');
                    $this->toocheke_webtoons_prune_unused_attachments($post_id);
                    $this->toocheke_webtoons_clear_image_progress($post_id);
                    $job['fixed']++;
                } else {
                    update_post_meta($post_id, '_toocheke_webtoons_missing_panels', $missing);
                }
            }

            if ($missing > 0) {
                $item['tries'] = (int) $item['tries'] + 1;
                if ($item['tries'] < $this->toocheke_webtoons_max_image_attempts()) {
                    $job['queue'][] = $item;
                } else {
                    $job['still_missing'][] = ['id' => $post_id, 'title' => $title, 'missing' => $missing];
                }
            }
        }

        if (empty($job['queue'])) {
            $job['status'] = 'done';
            return ['done' => true];
        }
        return ['done' => false];
    }

    public function toocheke_webtoons_ajax_start()
    {
        $this->toocheke_webtoons_ajax_guard();

        $raw_urls = isset($_POST['urls']) ? (array) wp_unslash($_POST['urls']) : [];
        $entries  = [];
        $errors   = [];
        $seen     = [];

        foreach ($raw_urls as $raw_url) {
            if (! is_string($raw_url)) {
                continue;
            }
            $raw_url = trim(sanitize_text_field($raw_url));
            if ('' === $raw_url) {
                continue;
            }

            $parsed = $this->toocheke_webtoons_validate_series_url($raw_url);
            if (! $parsed) {
                /* translators: %s: the URL the user entered */
                $errors[] = sprintf(__('“%s” doesn\'t look like an English Webtoons series URL (expected https://www.webtoons.com/en/canvas/NAME/list?title_no=ID or https://www.webtoons.com/en/GENRE/NAME/list?title_no=ID).', 'toocheke-companion'), $raw_url);
                continue;
            }
            if (isset($seen[$parsed['title_no']])) {
                continue;
            }
            $seen[$parsed['title_no']] = true;

            $entries[] = [
                'url'               => $parsed['url'],
                'slug'              => $parsed['slug'],
                'title_no'          => $parsed['title_no'],
                'kind'              => $parsed['kind'], // canvas | original
                'title'             => '',
                'status'            => 'pending', // pending | resolving | indexing | importing | done | failed
                'series_post_id'    => null,
                'index_next_page'   => 0,
                'index_found'       => 0,
                'index_done'        => false,
                'cursor'            => 0, // position of the next episode in the index
                'episodes_done'     => 0,
                'episodes_skipped'  => 0,
                'skip_reasons'      => [], // 'locked' | 'mature' | 'missing' | 'unknown' => count
                'episodes_total'    => null,
                'last_episode_title'=> '',
                'last_episode_id'   => null,
                'throttle_strikes'  => 0,
                'flagged_episodes'  => [],
                'error'             => '',
                'log'               => [],
            ];
        }

        if (empty($entries)) {
            wp_send_json_error(['message' => empty($errors) ? __('Please enter at least one Webtoons series URL.', 'toocheke-companion') : implode(' ', $errors)]);
        }

        $job                   = $this->toocheke_webtoons_default_job();
        $job['status']         = 'running';
        $job['series']         = $entries;
        $job['current_index']  = 0;
        $this->toocheke_webtoons_save_job($job);
        delete_option('toocheke_webtoons_import_last_error');

        wp_send_json_success([
            'job'      => $this->toocheke_webtoons_job_for_js($job),
            'warnings' => $errors,
        ]);
    }

    public function toocheke_webtoons_ajax_status()
    {
        $this->toocheke_webtoons_ajax_guard();
        $job = $this->toocheke_webtoons_get_job();
        wp_send_json_success(['job' => $this->toocheke_webtoons_job_for_js($job)]);
    }

    public function toocheke_webtoons_ajax_discard()
    {
        $this->toocheke_webtoons_ajax_guard();
        $job = $this->toocheke_webtoons_get_job();
        foreach ((array) $job['series'] as $entry) {
            if (! empty($entry['title_no'])) {
                delete_option($this->toocheke_webtoons_index_option($entry['title_no']));
            }
        }
        $this->toocheke_webtoons_delete_job();
        delete_option('toocheke_webtoons_import_last_error');
        wp_send_json_success(['job' => $this->toocheke_webtoons_job_for_js($this->toocheke_webtoons_default_job())]);
    }

    // Resumes a failed series from where it left off instead of
    // starting over.
    public function toocheke_webtoons_ajax_retry_series()
    {
        $this->toocheke_webtoons_ajax_guard();

        $title_no = isset($_POST['title_no']) ? absint($_POST['title_no']) : 0;
        $job      = $this->toocheke_webtoons_get_job();

        $found_index = null;
        foreach ($job['series'] as $index => $entry) {
            $is_failed       = 'failed' === $entry['status'];
            // Only offer a retry when it could actually change something —
            // a plain locked/mature skip summary won't be any different
            // next time, so exclude that.
            $is_short_finish = 'done' === $entry['status'] && ! empty($entry['warning']) && empty($entry['episodes_skipped']);
            if ((int) $entry['title_no'] === $title_no && ($is_failed || $is_short_finish)) {
                $found_index = $index;
                break;
            }
        }

        if (null === $found_index) {
            wp_send_json_error(['message' => __('That series isn\'t in a retryable state (it may have already been retried).', 'toocheke-companion')]);
        }

        $entry = &$job['series'][$found_index];

        if ('failed' === $entry['status']) {
            // Pick up at the page or episode it stopped on.
            if (empty($entry['series_post_id'])) {
                $entry['status'] = 'pending';
            } else {
                $entry['status'] = ! empty($entry['index_done']) ? 'importing' : 'indexing';
            }
        } else {
            // Walk the whole list again. Episodes that are already
            // imported are skipped without a request, so nothing is
            // downloaded twice.
            $entry['status']           = 'pending';
            $entry['cursor']           = 0;
            $entry['index_done']       = false;
            $entry['index_next_page']  = 0;
            $entry['episodes_done']    = 0;
            $entry['episodes_skipped'] = 0;
            $entry['skip_reasons']     = [];
            $entry['flagged_episodes'] = [];
        }

        $entry['error']   = '';
        $entry['warning'] = '';
        $this->toocheke_webtoons_log($entry, __('Retrying…', 'toocheke-companion'));

        $job['current_index'] = $found_index;
        $job['status']        = 'running';
        $this->toocheke_webtoons_save_job($job);
        delete_option('toocheke_webtoons_import_last_error');

        wp_send_json_success(['job' => $this->toocheke_webtoons_job_for_js($job)]);
    }

    // Processes one unit of work (resolve a series, or import one
    // episode) and returns — the JS side loops this, so a large series
    // never hits a PHP time limit. A single step can still take a while
    // for a many-panel episode, hence raising it below.
    public function toocheke_webtoons_ajax_step()
    {
        $this->toocheke_webtoons_ajax_guard();

        // Some hosts disable this function entirely — harmless no-op.
        @set_time_limit(120); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_time_limit

        // A slow step can still be running server-side when the client
        // gives up and retries, starting a second process on the same
        // episode — this makes the second request wait instead.
        if (get_transient('toocheke_webtoons_step_lock')) {
            wp_send_json_success([
                'job'          => $this->toocheke_webtoons_job_for_js($this->toocheke_webtoons_get_job()),
                'finished'     => false,
                'throttled'    => true,
                'retry_after'  => 10,
                'pause_reason' => __('Still finishing the previous step — waiting for it before continuing.', 'toocheke-companion'),
            ]);
        }
        // Auto-releases on its own well before this — just a backstop in
        // case a crash skips the delete_transient() calls below.
        set_transient('toocheke_webtoons_step_lock', 1, 150);

        $job = $this->toocheke_webtoons_get_job();

        if (empty($job['series'])) {
            delete_transient('toocheke_webtoons_step_lock');
            wp_send_json_success(['job' => $this->toocheke_webtoons_job_for_js($job), 'finished' => true]);
        }

        // Advance past any already-finished series at the front of the queue.
        while ($job['current_index'] < count($job['series'])
            && in_array($job['series'][$job['current_index']]['status'], ['done', 'failed'], true)) {
            $job['current_index']++;
        }

        if ($job['current_index'] >= count($job['series'])) {
            $job['status'] = 'completed';
            $this->toocheke_webtoons_save_job($job);
            delete_transient('toocheke_webtoons_step_lock');
            wp_send_json_success(['job' => $this->toocheke_webtoons_job_for_js($job), 'finished' => true]);
        }

        $index = $job['current_index'];
        $entry = &$job['series'][$index];

        // Every unit of work funnels through here so that any Webtoons
        // request failure is handled in exactly one place.
        $result = $this->toocheke_webtoons_process_one_unit($entry);

        if (! empty($result['throttled'])) {
            $job['status'] = 'throttled';
            $this->toocheke_webtoons_save_job($job);
            delete_transient('toocheke_webtoons_step_lock');
            wp_send_json_success([
                'job'            => $this->toocheke_webtoons_job_for_js($job),
                'finished'       => false,
                'throttled'      => true,
                'retry_after'    => $result['retry_after'],
                'pause_reason'   => isset($result['pause_reason']) ? $result['pause_reason'] : '',
            ]);
        }

        $job['status'] = 'running';
        $this->toocheke_webtoons_save_job($job);

        delete_transient('toocheke_webtoons_step_lock');
        wp_send_json_success([
            'job'      => $this->toocheke_webtoons_job_for_js($job),
            'finished' => false,
        ]);
    }

    protected function toocheke_webtoons_ajax_guard()
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'toocheke-companion')], 403);
        }
        check_ajax_referer('toocheke_webtoons_import', 'nonce');
    }

    // Strips the job down to what the browser needs for the progress UI.
    protected function toocheke_webtoons_job_for_js(array $job)
    {
        $series_out = [];
        foreach ($job['series'] as $entry) {
            $series_out[] = [
                'title_no'           => (int) $entry['title_no'],
                'slug'               => $entry['slug'],
                'title'              => isset($entry['title']) ? $entry['title'] : '',
                'status'             => $entry['status'],
                'cursor'             => isset($entry['cursor']) ? (int) $entry['cursor'] : 0,
                'episodes_done'      => (int) $entry['episodes_done'],
                'episodes_skipped'   => isset($entry['episodes_skipped']) ? (int) $entry['episodes_skipped'] : 0,
                'skip_reasons'       => isset($entry['skip_reasons']) ? $entry['skip_reasons'] : [],
                'episodes_total'     => $entry['episodes_total'],
                'index_found'        => isset($entry['index_found']) ? (int) $entry['index_found'] : 0,
                'last_episode_title' => $entry['last_episode_title'],
                'error'              => $entry['error'],
                'warning'            => isset($entry['warning']) ? $entry['warning'] : '',
                'flagged_episodes'   => isset($entry['flagged_episodes']) ? $entry['flagged_episodes'] : [],
                'log'                => array_slice((array) $entry['log'], -6),
                'series_post_id'     => $entry['series_post_id'],
            ];
        }

        return [
            'status'        => $job['status'],
            'current_index' => $job['current_index'],
            'series'        => $series_out,
        ];
    }

    protected function toocheke_webtoons_process_one_unit(array &$entry)
    {
        // A "Start a series over" delete can be running for this exact
        // series — creating new posts here while that's deleting them
        // would race it, so pause until the cleanup finishes.
        $cleanup_job = get_option('toocheke_webtoons_cleanup_job');
        if (is_array($cleanup_job) && ! empty($entry['series_post_id']) && (int) $cleanup_job['series_post_id'] === (int) $entry['series_post_id']) {
            $message = __('This series is currently being deleted by "Start a series over" — waiting for that to finish before continuing.', 'toocheke-companion');
            $this->toocheke_webtoons_log($entry, $message);
            return [
                'throttled'    => true,
                'retry_after'  => 10,
                'pause_reason' => $message,
            ];
        }

        if (in_array($entry['status'], ['pending', 'resolving'], true)) {
            return $this->toocheke_webtoons_resolve_series($entry);
        }

        if ('indexing' === $entry['status']) {
            return $this->toocheke_webtoons_index_next_page($entry);
        }

        return $this->toocheke_webtoons_import_next_episode($entry);
    }

    // Marks a series failed and records why.
    protected function toocheke_webtoons_fail(array &$entry, $message, $episode_id = null)
    {
        $entry['status'] = 'failed';
        $entry['error']  = $message;
        $this->toocheke_webtoons_log($entry, $message);
        $this->toocheke_webtoons_record_failure($entry, $episode_id);
        return ['throttled' => false];
    }

    // Handles a throttled fetch for a series: returns the pause to
    // apply, or null once the strikes run out (caller should fail).
    protected function toocheke_webtoons_throttle_pause(array &$entry, array $fetch)
    {
        $backoff = $this->toocheke_webtoons_apply_backoff_strike($entry, $fetch);
        return ! empty($backoff['throttled']) ? $backoff : null;
    }

    // First unit of work: reads page 1 of the list, creates the Series
    // post, and starts the episode index.
    protected function toocheke_webtoons_resolve_series(array &$entry)
    {
        $entry['status'] = 'resolving';

        $fetch = $this->toocheke_webtoons_http_get($entry['url']);
        if (! empty($fetch['throttled'])) {
            $pause = $this->toocheke_webtoons_throttle_pause($entry, $fetch);
            if ($pause) {
                return $pause;
            }
            return $this->toocheke_webtoons_fail($entry, __('Webtoons kept blocking this series\' page even after several waits — stopping here. You can try "Retry This Series" again later.', 'toocheke-companion'));
        }
        $this->toocheke_webtoons_reset_backoff_strikes($entry);

        if (! empty($fetch['gated'])) {
            return $this->toocheke_webtoons_fail($entry, __('Webtoons wants a sign-in to view this series (it\'s probably mature-rated), so it can\'t be imported.', 'toocheke-companion'));
        }

        if (empty($fetch['body'])) {
            return $this->toocheke_webtoons_fail($entry, $fetch['error'] ? $fetch['error'] : __('Could not load the series page.', 'toocheke-companion'));
        }

        $data = $this->toocheke_webtoons_parse_list_html($fetch['body'], $entry['kind']);

        if ((int) $data['title_no'] !== (int) $entry['title_no'] || empty($data['rows'])) {
            return $this->toocheke_webtoons_fail($entry, __('That page didn\'t look like a Webtoons series (it may be mature-content gated behind a login, have no free episodes, or the URL is wrong).', 'toocheke-companion'));
        }

        $entry['title'] = $data['title'];

        // Idempotent: reuse an existing Series post from a previous run
        // rather than creating a duplicate.
        $series_post_id = $this->toocheke_webtoons_find_existing_series_post($entry['title_no'], $data['title']);

        if (! $series_post_id) {
            $series_post_id = wp_insert_post([
                'post_title'   => wp_strip_all_tags($data['title']),
                'post_content' => wp_kses_post(wpautop(esc_html($data['description']))),
                'post_status'  => 'publish',
                'post_type'    => 'series',
                'meta_input'   => [
                    '_toocheke_webtoons_series_id'  => $entry['title_no'],
                    '_toocheke_webtoons_series_url' => $entry['url'],
                ],
            ], true);

            if (is_wp_error($series_post_id)) {
                return $this->toocheke_webtoons_fail($entry, $series_post_id->get_error_message());
            }

            $this->toocheke_webtoons_log($entry, sprintf(
                /* translators: %s: series title */
                __('Created series “%s”.', 'toocheke-companion'),
                $data['title']
            ));
        } else {
            $this->toocheke_webtoons_log($entry, __('Found an existing Series post from a previous run — continuing into it.', 'toocheke-companion'));
        }

        if (! empty($data['thumb_url']) && ! has_post_thumbnail($series_post_id)) {
            $thumb_id = $this->toocheke_webtoons_sideload_largest($data['thumb_url'], $series_post_id, $data['title']);
            if ($thumb_id) {
                set_post_thumbnail($series_post_id, $thumb_id);
            } else {
                $this->toocheke_webtoons_log($entry, __('The series thumbnail couldn\'t be downloaded, so the Series post has no featured image yet.', 'toocheke-companion'));
            }
        }

        $entry['series_post_id']  = $series_post_id;
        $entry['status']          = 'indexing';
        $entry['index_done']      = false;
        $entry['index_next_page'] = $data['next_page'];
        $entry['index_found']     = count($data['rows']);

        $rows = [];
        foreach ($data['rows'] as $row) {
            $rows[$row['id']] = $row;
        }
        $this->toocheke_webtoons_save_index($entry['title_no'], $rows);

        if (! $data['next_page']) {
            $this->toocheke_webtoons_finalize_index($entry);
        }

        return ['throttled' => false];
    }

    // The list is paged newest-first, so building the full episode
    // index takes one request per page.
    protected function toocheke_webtoons_index_next_page(array &$entry)
    {
        $page = (int) $entry['index_next_page'];

        if ($page < 2 || $page > 5000) {
            $this->toocheke_webtoons_finalize_index($entry);
            return ['throttled' => false];
        }

        $fetch = $this->toocheke_webtoons_http_get($entry['url'] . '&page=' . $page);
        if (! empty($fetch['throttled'])) {
            $pause = $this->toocheke_webtoons_throttle_pause($entry, $fetch);
            if ($pause) {
                return $pause;
            }
            return $this->toocheke_webtoons_fail($entry, sprintf(
                /* translators: %d: page number of the episode list */
                __('Webtoons kept blocking page %d of the episode list even after several waits — stopping here. Use "Retry This Series" to pick up from this page.', 'toocheke-companion'),
                $page
            ));
        }
        $this->toocheke_webtoons_reset_backoff_strikes($entry);

        if (empty($fetch['body'])) {
            return $this->toocheke_webtoons_fail($entry, sprintf(
                /* translators: 1: page number of the episode list, 2: underlying technical error */
                __('Could not load page %1$d of the episode list after retrying. Technical detail: %2$s', 'toocheke-companion'),
                $page,
                $fetch['error'] ? $fetch['error'] : __('unknown error', 'toocheke-companion')
            ));
        }

        $data = $this->toocheke_webtoons_parse_list_html($fetch['body'], $entry['kind'], $page);

        if ((int) $data['title_no'] !== (int) $entry['title_no']) {
            return $this->toocheke_webtoons_fail($entry, sprintf(
                /* translators: %d: page number of the episode list */
                __('Page %d of the episode list came back in an unexpected format — stopping this series here.', 'toocheke-companion'),
                $page
            ));
        }

        $rows   = $this->toocheke_webtoons_get_index($entry['title_no']);
        $before = count($rows);
        foreach ($data['rows'] as $row) {
            $rows[$row['id']] = $row;
        }
        $this->toocheke_webtoons_save_index($entry['title_no'], $rows);

        $entry['index_found'] = count($rows);
        // A page with nothing new means we've hit the end.
        $entry['index_next_page'] = count($rows) > $before ? $data['next_page'] : 0;

        if (0 === $page % 10) {
            $this->toocheke_webtoons_log($entry, sprintf(
                /* translators: %d: number of episodes found so far */
                __('Reading the episode list… %d episodes found so far.', 'toocheke-companion'),
                $entry['index_found']
            ));
        }

        if (! $entry['index_next_page']) {
            $this->toocheke_webtoons_finalize_index($entry);
        }

        return ['throttled' => false];
    }

    // Orders the index oldest-first and fixes each episode's publish
    // date. Same-day episodes are spaced a second apart so they keep
    // their order.
    protected function toocheke_webtoons_finalize_index(array &$entry)
    {
        $rows = array_values($this->toocheke_webtoons_get_index($entry['title_no']));
        usort($rows, function ($a, $b) {
            return (int) $a['id'] - (int) $b['id'];
        });

        $prev = '';
        $n    = 0;
        foreach ($rows as &$row) {
            $day = substr((string) $row['date'], 0, 10);
            $n   = ('' !== $day && $day === $prev) ? $n + 1 : 0;
            $prev = $day;
            $row['date'] = '' !== $day ? gmdate('Y-m-d H:i:s', strtotime($day . ' 12:00:00 UTC') + $n) : '';
        }
        unset($row);

        $this->toocheke_webtoons_save_index($entry['title_no'], $rows);

        $entry['index_done']      = true;
        $entry['index_next_page'] = 0;
        $entry['episodes_total']  = count($rows);
        $entry['cursor']          = 0;
        $entry['status']          = 'importing';

        $this->toocheke_webtoons_log($entry, sprintf(
            /* translators: %d: number of episodes */
            _n('Found %d episode on the series page.', 'Found %d episodes on the series page.', count($rows), 'toocheke-companion'),
            count($rows)
        ));
    }

    protected function toocheke_webtoons_index_option($title_no)
    {
        return 'toocheke_webtoons_index_' . (int) $title_no;
    }

    // The episode index lives in its own option so the job record
    // stays small — it's read on every step.
    protected function toocheke_webtoons_get_index($title_no)
    {
        $rows = get_option($this->toocheke_webtoons_index_option($title_no), []);
        return is_array($rows) ? $rows : [];
    }

    protected function toocheke_webtoons_save_index($title_no, array $rows)
    {
        update_option($this->toocheke_webtoons_index_option($title_no), $rows, false);
    }

    // Skips past episodes already imported, then imports the one at
    // the cursor.
    protected function toocheke_webtoons_import_next_episode(array &$entry)
    {
        $rows  = array_values($this->toocheke_webtoons_get_index($entry['title_no']));
        $total = count($rows);

        if (! $total) {
            // The index went missing — rebuild it from the series page.
            $entry['status'] = 'pending';
            return ['throttled' => false];
        }

        $series_post_id   = (int) $entry['series_post_id'];
        $comic_post_id    = 0;
        $skipped_existing = 0;
        $capped           = false;
        $started          = microtime(true);

        while ($entry['cursor'] < $total) {
            $row           = $rows[$entry['cursor']];
            $comic_post_id = $this->toocheke_webtoons_find_existing_comic_post($row['id'], $series_post_id, $row['title']);

            if ($comic_post_id && get_post_meta($comic_post_id, '_toocheke_webtoons_import_complete', true)) {
                $entry['cursor']++;
                $skipped_existing++;
                $comic_post_id = 0;
                if ($skipped_existing >= 100 || (microtime(true) - $started) > 10) {
                    $capped = true;
                    break;
                }
                continue;
            }
            break;
        }

        if ($skipped_existing) {
            $this->toocheke_webtoons_log($entry, sprintf(
                /* translators: %d: number of episodes */
                _n('Skipped %d episode that was already imported.', 'Skipped %d episodes that were already imported.', $skipped_existing, 'toocheke-companion'),
                $skipped_existing
            ));
        }

        if ($capped) {
            return ['throttled' => false];
        }

        if ($entry['cursor'] >= $total) {
            return $this->toocheke_webtoons_finish_series($entry);
        }

        return $this->toocheke_webtoons_import_episode($entry, $rows[$entry['cursor']], $comic_post_id, $total);
    }

    protected function toocheke_webtoons_skip_labels()
    {
        return [
            'mature'  => __('mature-gated (needs a sign-in)', 'toocheke-companion'),
            'locked'  => __('locked/paid (Daily Pass or Fast Pass) or otherwise restricted', 'toocheke-companion'),
            'missing' => __('no longer available (404)', 'toocheke-companion'),
            'unknown' => __('unclear reason — worth checking manually', 'toocheke-companion'),
        ];
    }

    protected function toocheke_webtoons_skip_details()
    {
        return [
            'mature'  => __('it\'s mature-rated and Webtoons only shows it to signed-in readers.', 'toocheke-companion'),
            'locked'  => __('it\'s a paid (Daily Pass or Fast Pass) or otherwise restricted episode that Webtoons doesn\'t show to anonymous visitors.', 'toocheke-companion'),
            'missing' => __('its page no longer exists on Webtoons (HTTP 404).', 'toocheke-companion'),
            'unknown' => __('no panel images were found on its page for an unclear reason — worth checking it directly on Webtoons.', 'toocheke-companion'),
        ];
    }

    // No placeholder post for an episode there's nothing to import
    // for — it's counted, logged and flagged instead.
    protected function toocheke_webtoons_skip_episode(array &$entry, array $row, $reason_key, $total)
    {
        $details = $this->toocheke_webtoons_skip_details();

        $entry['episodes_skipped']++;
        $entry['skip_reasons'][$reason_key] = ! empty($entry['skip_reasons'][$reason_key]) ? $entry['skip_reasons'][$reason_key] + 1 : 1;

        $warning = sprintf(
            /* translators: 1: episode title, 2: reason it was skipped */
            __('Skipped “%1$s” — %2$s', 'toocheke-companion'),
            $row['title'],
            $details[$reason_key]
        );
        $this->toocheke_webtoons_log($entry, $warning);
        $this->toocheke_webtoons_flag_episode($entry, $row['id'], $warning);

        $entry['cursor']++;
        if ($entry['cursor'] >= $total) {
            return $this->toocheke_webtoons_finish_series($entry);
        }
        return ['throttled' => false];
    }

    // How long one step may spend downloading panels before it hands
    // back to the browser and picks up on the next call.
    protected function toocheke_webtoons_step_budget()
    {
        $max = (int) ini_get('max_execution_time');
        return $max > 0 ? max(10, min(45, (int) ($max * 0.6))) : 45;
    }

    // Fetches the episode at the cursor and turns it into a Comic post.
    protected function toocheke_webtoons_import_episode(array &$entry, array $row, $comic_post_id, $total)
    {
        $entry['last_episode_id']    = $row['id'];
        $entry['last_episode_title'] = $row['title'];

        $fetch = $this->toocheke_webtoons_http_get($row['url']);
        if (! empty($fetch['throttled'])) {
            $pause = $this->toocheke_webtoons_throttle_pause($entry, $fetch);
            if ($pause) {
                return $pause;
            }
            return $this->toocheke_webtoons_fail($entry, sprintf(
                /* translators: %d: Webtoons episode number */
                __('Webtoons kept blocking episode #%d even after several waits — stopping here. Use "Retry This Series" to pick up from this episode.', 'toocheke-companion'),
                (int) $row['id']
            ), $row['id']);
        }
        $this->toocheke_webtoons_reset_backoff_strikes($entry);

        if (! empty($fetch['gated'])) {
            return $this->toocheke_webtoons_skip_episode($entry, $row, 'mature', $total);
        }
        if (! empty($fetch['missing'])) {
            return $this->toocheke_webtoons_skip_episode($entry, $row, 'missing', $total);
        }

        if (empty($fetch['body'])) {
            // Already retried inside http_get(), so this didn't recover.
            return $this->toocheke_webtoons_fail($entry, sprintf(
                /* translators: 1: Webtoons episode number, 2: underlying technical error */
                __('Could not load episode #%1$d after retrying — stopping here. Technical detail: %2$s', 'toocheke-companion'),
                (int) $row['id'],
                $fetch['error'] ? $fetch['error'] : __('unknown error', 'toocheke-companion')
            ), $row['id']);
        }

        $data = $this->toocheke_webtoons_parse_viewer_html($fetch['body']);

        if (! $data['viewer_found']) {
            // Sent back to the list page instead of the viewer: not
            // viewable without signing in.
            if (false === strpos((string) $fetch['final_url'], '/viewer')) {
                return $this->toocheke_webtoons_skip_episode($entry, $row, 'locked', $total);
            }
            return $this->toocheke_webtoons_fail($entry, __('An episode page came back in an unexpected format — stopping this series here.', 'toocheke-companion'), $row['id']);
        }

        if ((int) $data['episode_id'] !== (int) $row['id']) {
            // Redirected to a different episode.
            return $this->toocheke_webtoons_skip_episode($entry, $row, 'locked', $total);
        }

        if (! $comic_post_id && empty($data['images'])) {
            $reason = $data['is_mature'] ? 'mature' : ($data['is_locked'] ? 'locked' : 'unknown');
            return $this->toocheke_webtoons_skip_episode($entry, $row, $reason, $total);
        }

        if ($comic_post_id) {
            // Exists but never got marked complete — likely a step that
            // timed out on a many-panel episode. Finish it in place.
            $this->toocheke_webtoons_log($entry, sprintf(
                /* translators: %s: episode title */
                __('Finishing an incomplete import of “%s” from an earlier interrupted run…', 'toocheke-companion'),
                $row['title']
            ));
        } else {
            $postarr = [
                'post_title'   => wp_strip_all_tags($row['title']),
                'post_status'  => 'publish',
                'post_type'    => 'comic',
                'post_parent'  => (int) $entry['series_post_id'],
                'post_content' => '',
                // Tagged in the same insert, so an interruption can't
                // leave an untagged orphan behind.
                'meta_input'   => [
                    '_toocheke_webtoons_episode_id'  => $row['id'],
                    '_toocheke_webtoons_series_id'   => $entry['title_no'],
                    '_toocheke_webtoons_episode_url' => $row['url'],
                ],
            ];

            if (! empty($data['note'])) {
                $postarr['post_excerpt'] = wp_strip_all_tags($data['note']);
            }

            if (! empty($row['date'])) {
                $postarr['post_date']     = $row['date'];
                $postarr['post_date_gmt'] = get_gmt_from_date($row['date']);
            }

            $comic_post_id = wp_insert_post($postarr, true);

            if (is_wp_error($comic_post_id)) {
                $warning = $comic_post_id->get_error_message();
                $this->toocheke_webtoons_log($entry, $warning);
                $this->toocheke_webtoons_flag_episode($entry, $row['id'], $warning);
                // A bad insert only loses this one episode.
                $entry['cursor']++;
                return $entry['cursor'] >= $total ? $this->toocheke_webtoons_finish_series($entry) : ['throttled' => false];
            }
        }

        if (! empty($row['thumb']) && ! has_post_thumbnail($comic_post_id)) {
            $thumb_id = $this->toocheke_webtoons_sideload_largest($row['thumb'], $comic_post_id, $row['title']);
            if ($thumb_id) {
                set_post_thumbnail($comic_post_id, $thumb_id);
            } else {
                $this->toocheke_webtoons_log($entry, sprintf(
                    /* translators: %s: episode title */
                    __('The thumbnail for “%s” couldn\'t be downloaded.', 'toocheke-companion'),
                    $row['title']
                ));
            }
        }

        $missing  = 0;
        $pending  = 0;
        $deadline = microtime(true) + $this->toocheke_webtoons_step_budget();
        $content  = $this->toocheke_webtoons_build_comic_content($data['images'], $comic_post_id, $row['title'], $missing, $deadline, $pending);

        if ($pending > 0) {
            // Out of time for this step — progress is saved, so the
            // next call carries on with the rest of the panels.
            $message = sprintf(
                /* translators: 1: episode title, 2: panels downloaded so far, 3: total panels */
                __('Downloading panels for “%1$s” — %2$d of %3$d done…', 'toocheke-companion'),
                $row['title'],
                count($data['images']) - $pending - $missing,
                count($data['images'])
            );
            return ['throttled' => true, 'retry_after' => 1, 'pause_reason' => $message];
        }

        if ('' !== $content) {
            wp_update_post(['ID' => $comic_post_id, 'post_content' => $content]);
        }

        if ($missing > 0) {
            $attempts = (int) get_post_meta($comic_post_id, '_toocheke_webtoons_import_attempts', true) + 1;
            update_post_meta($comic_post_id, '_toocheke_webtoons_import_attempts', $attempts);

            if ($attempts < $this->toocheke_webtoons_max_image_attempts()) {
                // Cursor stays on this episode so the next step tries it again.
                $message = sprintf(
                    /* translators: 1: number of images, 2: episode title */
                    _n('%1$d image in “%2$s” didn\'t download — retrying it…', '%1$d images in “%2$s” didn\'t download — retrying them…', $missing, 'toocheke-companion'),
                    $missing,
                    $row['title']
                );
                return ['throttled' => true, 'retry_after' => 5, 'pause_reason' => $message];
            }
            update_post_meta($comic_post_id, '_toocheke_webtoons_missing_panels', $missing);
        } else {
            delete_post_meta($comic_post_id, '_toocheke_webtoons_missing_panels');
            delete_post_meta($comic_post_id, '_toocheke_webtoons_import_attempts');
        }

        update_post_meta($comic_post_id, '_toocheke_webtoons_import_complete', 1);
        $this->toocheke_webtoons_prune_unused_attachments($comic_post_id);
        // Kept for flagged episodes so Repair knows which panels already exist.
        if (0 === $missing) {
            $this->toocheke_webtoons_clear_image_progress($comic_post_id);
        }

        $entry['episodes_done']++;

        if ('' === $content) {
            $warning = sprintf(
                /* translators: %s: episode title */
                __('“%s” was created but has NO images — no panels were found on its page.', 'toocheke-companion'),
                $row['title']
            );
            $this->toocheke_webtoons_log($entry, $warning);
            $this->toocheke_webtoons_flag_episode($entry, $row['id'], $warning);
        } elseif ($missing > 0) {
            $warning = sprintf(
                /* translators: 1: episode title, 2: number of images */
                _n('“%1$s” is missing %2$d image that wouldn\'t download. Use “Repair” below to try again.', '“%1$s” is missing %2$d images that wouldn\'t download. Use “Repair” below to try again.', $missing, 'toocheke-companion'),
                $row['title'],
                $missing
            );
            $this->toocheke_webtoons_log($entry, $warning);
            $this->toocheke_webtoons_flag_episode($entry, $row['id'], $warning);
        } else {
            $this->toocheke_webtoons_log($entry, sprintf(
                /* translators: %s: episode title */
                __('Imported “%s”.', 'toocheke-companion'),
                $row['title']
            ));
        }

        $entry['cursor']++;
        if ($entry['cursor'] >= $total) {
            return $this->toocheke_webtoons_finish_series($entry);
        }

        return ['throttled' => false];
    }

    // Every episode in the index has been visited. Compare what's on
    // the site against the list so a shortfall doesn't pass quietly.
    protected function toocheke_webtoons_finish_series(array &$entry)
    {
        $entry['status'] = 'done';

        $actual_count = $entry['series_post_id']
            ? $this->toocheke_webtoons_count_comics_for_series((int) $entry['series_post_id'])
            : $entry['episodes_done'];

        $this->toocheke_webtoons_log($entry, sprintf(
            /* translators: 1: episodes imported this run, 2: total now on the site */
            __('Finished — %1$d episode(s) imported this run (%2$d total now on the site).', 'toocheke-companion'),
            $entry['episodes_done'],
            $actual_count
        ));

        $expected_count  = $actual_count + $entry['episodes_skipped'];
        $unexplained_gap = ! empty($entry['episodes_total']) ? (int) $entry['episodes_total'] - $expected_count : 0;

        if ($entry['episodes_skipped'] > 0) {
            $reason_parts  = [];
            $reason_labels = $this->toocheke_webtoons_skip_labels();
            foreach ($entry['skip_reasons'] as $reason_key => $reason_count) {
                $label = isset($reason_labels[$reason_key]) ? $reason_labels[$reason_key] : $reason_key;
                $reason_parts[] = sprintf(
                    /* translators: 1: number of episodes, 2: reason label */
                    __('%1$d %2$s', 'toocheke-companion'),
                    $reason_count,
                    $label
                );
            }

            $warning = sprintf(
                /* translators: 1: number imported, 2: number skipped, 3: comma-separated reason breakdown */
                __('%1$d episode(s) imported, %2$d skipped (%3$s).', 'toocheke-companion'),
                $entry['episodes_done'],
                $entry['episodes_skipped'],
                implode(', ', $reason_parts)
            );

            if ($unexplained_gap > 0) {
                $warning .= ' ' . sprintf(
                    /* translators: %d: number of further episodes unaccounted for */
                    __('Additionally, %d further episode(s) are unaccounted for — Webtoons\' list is longer than imported + skipped combined.', 'toocheke-companion'),
                    $unexplained_gap
                );
            }

            $entry['warning'] = $warning;
            $this->toocheke_webtoons_log($entry, $warning);
            $this->toocheke_webtoons_record_existing_flags($entry);
        } elseif (! empty($entry['episodes_total']) && $actual_count < (int) $entry['episodes_total']) {
            $warning = sprintf(
                /* translators: 1: episodes Webtoons listed, 2: episodes actually on the site, 3: how many short, 4: last episode title, 5: last episode's Webtoons number */
                __('Heads up: Webtoons listed %1$d episodes for this series, but only %2$d ended up on your site (%3$d short). The last episode the importer handled was “%4$s” (Webtoons episode #%5$d). Some episodes may have been deleted from your site since an earlier run, or failed to save — "Retry This Series" will fill in any gaps.', 'toocheke-companion'),
                (int) $entry['episodes_total'],
                $actual_count,
                (int) $entry['episodes_total'] - $actual_count,
                $entry['last_episode_title'],
                (int) $entry['last_episode_id']
            );
            $entry['warning'] = $warning;
            $this->toocheke_webtoons_log($entry, $warning);
            $this->toocheke_webtoons_record_incomplete_notice($entry, $actual_count);
        } elseif (! empty($entry['flagged_episodes'])) {
            // Post count matches, but one or more still came back with
            // no images — still worth surfacing.
            $count = count($entry['flagged_episodes']);
            $warning = sprintf(
                /* translators: %d: number of flagged episodes */
                _n(
                    'Heads up: %d episode needs attention — see the log below for which one and why.',
                    'Heads up: %d episodes need attention — see the log below for which ones and why.',
                    $count,
                    'toocheke-companion'
                ),
                $count
            );
            $entry['warning'] = $warning;
            $this->toocheke_webtoons_log($entry, $warning);
            $this->toocheke_webtoons_record_existing_flags($entry);
        }

        return ['throttled' => false];
    }

    // COUNT(*) rather than fetching every post ID — this runs once per
    // series on every load of the import page, and some series here
    // run into the thousands of episodes.
    protected function toocheke_webtoons_count_comics_for_series($series_post_id)
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'comic' AND post_status != 'trash'",
            $series_post_id
        ));
    }

    // Downloads every panel image for one episode and returns plain
    // <img> markup for the post content, in reading order.
    // Resumable per-image: tracks which source URLs already succeeded
    // (as post meta, updated after each image lands) so an interruption
    // partway through a many-panel episode only re-does the images that
    // didn't finish. Keyed by the URL's path only — see
    // toocheke_webtoons_stable_url_key().
    // $missing counts images that failed to download; $pending counts
    // ones not tried yet because the step ran out of time.
    protected function toocheke_webtoons_build_comic_content(array $images, $post_id, $desc, &$missing = null, $deadline = 0, &$pending = null)
    {
        $progress = $this->toocheke_webtoons_get_image_progress($post_id);
        $missing  = 0;
        $pending  = 0;
        $failed   = [];

        foreach ($images as $image) {
            $key = $this->toocheke_webtoons_stable_url_key($image['url']);

            if (isset($progress[$key]) && wp_attachment_is_image($progress[$key])) {
                continue;
            }
            if (isset($failed[$key])) {
                continue;
            }
            if ($deadline && microtime(true) >= $deadline) {
                $pending++;
                continue;
            }

            $attachment_id = $this->toocheke_webtoons_sideload_image($image['url'], $post_id, $desc, true);
            if (! $attachment_id) {
                $failed[$key] = true;
                continue;
            }

            // Recorded straight away, so a timeout on the next image
            // can't lose this one.
            $progress[$key] = $attachment_id;
            $this->toocheke_webtoons_save_image_progress($post_id, $progress);
        }

        $html = '';
        foreach ($images as $image) {
            $key = $this->toocheke_webtoons_stable_url_key($image['url']);
            if (empty($progress[$key]) || ! wp_attachment_is_image($progress[$key])) {
                if (isset($failed[$key])) {
                    $missing++;
                }
                continue;
            }
            // Block display so the strips stack with no gap between them.
            $html .= wp_get_attachment_image($progress[$key], 'full', false, [
                'class'   => 'toocheke-webtoons-panel',
                'loading' => 'lazy',
                'style'   => 'display:block;margin:0;padding:0;border:0;',
            ]);
        }
        return $html;
    }

    // Just the scheme/host/path, no query string — see the docblock on
    // toocheke_webtoons_build_comic_content() for why the query string
    // can't be part of the key.
    protected function toocheke_webtoons_stable_url_key($url)
    {
        return explode('?', $url, 2)[0];
    }

    // Reads the url => attachment_id map recorded so far for this post.
    protected function toocheke_webtoons_get_image_progress($post_id)
    {
        $raw = get_post_meta($post_id, '_toocheke_webtoons_image_progress', true);
        $map = $raw ? json_decode($raw, true) : null;
        return is_array($map) ? $map : [];
    }

    protected function toocheke_webtoons_save_image_progress($post_id, array $progress)
    {
        update_post_meta($post_id, '_toocheke_webtoons_image_progress', wp_json_encode($progress));
    }

    // Only meaningful mid-import — once a post is marked complete this
    // is never read again, so clearing it is just housekeeping.
    protected function toocheke_webtoons_clear_image_progress($post_id)
    {
        delete_post_meta($post_id, '_toocheke_webtoons_image_progress');
    }

    // Deletes any attachment still parented to this comic post that
    // isn't one of the ones actually used in its finished content —
    // safe because it's scoped to this one post and checked against
    // its own just-built, authoritative list, not a guess by title or
    // filename. Catches the extras a concurrent-request race can leave
    // behind. The featured thumbnail is tracked separately from the
    // panel list, so it's explicitly protected here.
    protected function toocheke_webtoons_prune_unused_attachments($post_id)
    {
        $progress = $this->toocheke_webtoons_get_image_progress($post_id);
        $used     = array_map('intval', array_values($progress));
        $keep     = (int) get_post_thumbnail_id($post_id);

        $attached = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post_parent'    => $post_id,
            'fields'         => 'ids',
        ]);

        foreach ($attached as $attachment_id) {
            $attachment_id = (int) $attachment_id;
            if ($attachment_id === $keep || in_array($attachment_id, $used, true)) {
                continue;
            }
            wp_delete_attachment($attachment_id, true);
        }
    }

    protected function toocheke_webtoons_find_existing_series_post($webtoons_series_id, $series_title = '')
    {
        $found = get_posts([
            'post_type'      => 'series',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_toocheke_webtoons_series_id',
            'meta_value'     => $webtoons_series_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        if (! empty($found)) {
            return (int) $found[0];
        }

        // Same orphan-recovery fallback as toocheke_webtoons_find_existing_comic_post()
        // — matches an untagged post by title, then tags it retroactively.
        if ('' !== $series_title) {
            $orphans = get_posts([
                'post_type'      => 'series',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'title'          => wp_strip_all_tags($series_title),
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    ['key' => '_toocheke_webtoons_series_id', 'compare' => 'NOT EXISTS'],
                ],
            ]);
            if (! empty($orphans)) {
                $orphan_id = (int) $orphans[0];
                update_post_meta($orphan_id, '_toocheke_webtoons_series_id', $webtoons_series_id);
                return $orphan_id;
            }
        }

        return 0;
    }

    // Fallback wait when Webtoons doesn't send a Retry-After header.
    protected function toocheke_webtoons_backoff_seconds()
    {
        return 45;
    }

    // Dates on the list pages look like "Sep 25, 2026" — no time, so
    // anchoring to noon keeps a timezone shift from moving it a day.
    protected function toocheke_webtoons_parse_list_date($raw)
    {
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return '';
        }
        $timestamp = strtotime($raw . ' 12:00:00 UTC');
        return false === $timestamp ? '' : gmdate('Y-m-d', $timestamp);
    }

    protected function toocheke_webtoons_strip_query($url)
    {
        return explode('?', (string) $url, 2)[0];
    }

    protected function toocheke_webtoons_cls($class)
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
    }

    protected function toocheke_webtoons_load_xpath($html)
    {
        $previous = libxml_use_internal_errors(true);
        $dom      = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($dom);
    }

    // The pages double-encode some titles (e.g. "&amp;#39;"), so decode
    // once more and tidy the whitespace.
    protected function toocheke_webtoons_clean_text($text)
    {
        $text = preg_replace_callback('/&(?:#\d+|#x[0-9a-f]+|amp|quot|lt|gt|apos);/i', function ($m) {
            return html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, (string) $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    // Same, but keeps line breaks (descriptions, creator notes).
    protected function toocheke_webtoons_clean_block($text)
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $text = preg_replace_callback('/&(?:#\d+|#x[0-9a-f]+|amp|quot|lt|gt|apos);/i', function ($m) {
            return html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    protected function toocheke_webtoons_absolute_url($href)
    {
        $href = trim((string) $href);
        if ('' === $href) {
            return '';
        }
        if (0 === strpos($href, '/') && 0 !== strpos($href, '//')) {
            $href = 'https://www.webtoons.com' . $href;
        }
        return $this->toocheke_webtoons_host_matches($href, TOOCHEKE_WEBTOONS_ALLOWED_HOST_SUFFIX) ? $href : '';
    }

    // Reads one page of a series' episode list: the series info (only
    // meaningful on page 1), this page's episode rows, and the next
    // page number if there is one.
    protected function toocheke_webtoons_parse_list_html($html, $kind, $current_page = 1)
    {
        $out = [
            'title_no'    => null,
            'title'       => '',
            'description' => '',
            'thumb_url'   => '',
            'rows'        => [],
            'next_page'   => 0,
        ];

        if ('' === trim((string) $html)) {
            return $out;
        }

        if (preg_match('/\btitleNo\s*:\s*"?(\d+)"?/', $html, $m)) {
            $out['title_no'] = (int) $m[1];
        }

        $xpath = $this->toocheke_webtoons_load_xpath($html);

        $node = $xpath->query('//meta[@property="og:title"]/@content');
        if ($node->length) {
            $out['title'] = $this->toocheke_webtoons_clean_text($node->item(0)->nodeValue);
        }
        if ('' === $out['title']) {
            $node = $xpath->query('//title');
            if ($node->length) {
                $out['title'] = $this->toocheke_webtoons_clean_text(preg_replace('/\s*\|\s*WEBTOON\s*$/i', '', $node->item(0)->textContent));
            }
        }
        if ('' === $out['title']) {
            $out['title'] = __('Untitled Series', 'toocheke-companion');
        }

        $node = $xpath->query('//p[' . $this->toocheke_webtoons_cls('summary') . ']');
        if ($node->length) {
            $out['description'] = $this->toocheke_webtoons_clean_block($node->item(0)->textContent);
        }
        if ('' === $out['description']) {
            $node = $xpath->query('//meta[@property="og:description"]/@content');
            if ($node->length) {
                $out['description'] = $this->toocheke_webtoons_clean_block($node->item(0)->nodeValue);
            }
        }

        // Canvas pages show the real cover in the header. On Originals
        // the header is character art, so the cover is the og:image —
        // a cropped rendition, hence the query string gets stripped.
        $thumb = '';
        if ('canvas' === $kind) {
            $node = $xpath->query('//*[' . $this->toocheke_webtoons_cls('detail_header') . ']//*[' . $this->toocheke_webtoons_cls('thmb') . ']//img/@src');
            if ($node->length) {
                $thumb = $node->item(0)->nodeValue;
            }
        }
        if ('' === $thumb) {
            $node = $xpath->query('//meta[@property="og:image"]/@content');
            if (! $node->length) {
                $node = $xpath->query('//meta[@name="twitter:image"]/@content');
            }
            if ($node->length) {
                $thumb = $node->item(0)->nodeValue;
            }
        }
        $out['thumb_url'] = $this->toocheke_webtoons_strip_query(trim($thumb));

        $items = $xpath->query('//ul[@id="_listUl"]/li[' . $this->toocheke_webtoons_cls('_episodeItem') . ']');
        foreach ($items as $li) {
            $id = (int) $li->getAttribute('data-episode-no');
            if ($id <= 0) {
                continue;
            }

            $link = $xpath->query('.//a[' . $this->toocheke_webtoons_cls('detail_list_link') . ']', $li);
            $url  = $link->length ? $this->toocheke_webtoons_absolute_url($link->item(0)->getAttribute('href')) : '';

            $img   = $xpath->query('.//img', $li);
            $title = '';
            $subj  = $xpath->query('.//*[' . $this->toocheke_webtoons_cls('subj') . ']', $li);
            if ($subj->length) {
                $title = $this->toocheke_webtoons_clean_text($subj->item(0)->textContent);
            }
            if ('' === $title && $img->length) {
                $title = $this->toocheke_webtoons_clean_text($img->item(0)->getAttribute('alt'));
            }
            if ('' === $title) {
                /* translators: %d: episode number */
                $title = sprintf(__('Episode %d', 'toocheke-companion'), $id);
            }

            $date = $xpath->query('.//*[' . $this->toocheke_webtoons_cls('date') . ']', $li);

            $out['rows'][] = [
                'id'    => $id,
                'url'   => $url,
                'title' => $title,
                'date'  => $date->length ? $this->toocheke_webtoons_parse_list_date($date->item(0)->textContent) : '',
                'thumb' => $img->length ? $this->toocheke_webtoons_strip_query(trim($img->item(0)->getAttribute('src'))) : '',
            ];
        }

        // The "next" link jumps a whole group of ten pages, so look at
        // every page link: anything above this page means the next one
        // exists.
        $highest = 0;
        $links   = $xpath->query('//*[' . $this->toocheke_webtoons_cls('paginate') . ']//a/@href');
        foreach ($links as $href) {
            if (preg_match('/[?&]page=(\d+)/', $href->nodeValue, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }
        if ($highest > (int) $current_page) {
            $out['next_page'] = (int) $current_page + 1;
        }

        return $out;
    }

    // Reads one episode's viewer page: the panel images in order (the
    // real URL is in data-url, src is a placeholder), the creator's
    // note, and whatever shows why a page has no images.
    protected function toocheke_webtoons_parse_viewer_html($html)
    {
        $out = [
            'viewer_found' => false,
            'series_id'    => null,
            'episode_id'   => null,
            'note'         => '',
            'images'       => [],
            'is_locked'    => false,
            'is_mature'    => false,
        ];

        if ('' === trim((string) $html)) {
            return $out;
        }

        if (preg_match('/viewerParam\s*:\s*\{[^}]*?\btitleNo\s*:\s*(\d+)/s', $html, $m)) {
            $out['series_id'] = (int) $m[1];
        }
        if (preg_match('/viewerParam\s*:\s*\{[^}]*?\bepisodeNo\s*:\s*(\d+)/s', $html, $m)) {
            $out['episode_id']   = (int) $m[1];
            $out['viewer_found'] = true;
        }

        if (preg_match('/\bisMatureTitle\s*:\s*true/i', $html)
            || preg_match('/regionalContentRatingMap\s*:\s*\{[^}]*"MATURE"/', $html)) {
            $out['is_mature'] = true;
        }
        if (preg_match('/class="[^"]*\b(?:ico_lock|ico_fast|ico_daily|paywall|_unlock)\b[^"]*"/i', $html)) {
            $out['is_locked'] = true;
        }

        $xpath = $this->toocheke_webtoons_load_xpath($html);

        $panels = $xpath->query('//*[@id="_imageList"]//img[' . $this->toocheke_webtoons_cls('_images') . ']');
        foreach ($panels as $panel) {
            $src = $panel->getAttribute('data-url');
            if ('' === $src) {
                $src = $panel->getAttribute('src');
            }
            $src = trim($src);
            if ('' === $src || 0 === stripos($src, 'data:') || false !== strpos($src, 'bg_transparency')) {
                continue;
            }
            $out['images'][] = ['url' => $src];
        }

        $note = $xpath->query('//*[' . $this->toocheke_webtoons_cls('_creatorNoteText') . ']');
        if ($note->length) {
            $out['note'] = $this->toocheke_webtoons_clean_block($note->item(0)->textContent);
        }

        return $out;
    }

    protected function toocheke_webtoons_host_matches($url, $suffix)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }
        $host = strtolower($host);
        return $host === $suffix || '.' . $suffix === substr($host, -1 - strlen($suffix));
    }

    // Webtoons turns away plain bot user agents, so requests go out
    // looking like a normal desktop browser.
    protected function toocheke_webtoons_user_agent()
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';
    }

    // Works out where a redirect points, or '' if it can't be read.
    protected function toocheke_webtoons_redirect_target($from, $location)
    {
        if (is_array($location)) {
            $location = reset($location);
        }
        $location = trim((string) $location);
        if ('' === $location) {
            return '';
        }
        if (0 === strpos($location, '//')) {
            return 'https:' . $location;
        }
        if (0 === strpos($location, '/')) {
            return 'https://' . wp_parse_url($from, PHP_URL_HOST) . $location;
        }
        return 0 === stripos($location, 'http') ? $location : '';
    }

    // Fetches a page. Never throws — returns 'body', or 'throttled' with
    // a suggested wait, or 'gated' (sent to a sign-in / age gate), or
    // 'missing' (404), or 'error' with the underlying reason. Redirects
    // are followed by hand so a gate can be told apart from a page, and
    // so they can't leave webtoons.com.
    protected function toocheke_webtoons_http_get($url, $attempt = 1, $hops = 0)
    {
        $out = ['body' => '', 'throttled' => false, 'gated' => false, 'missing' => false, 'error' => '', 'final_url' => $url];

        if (! $this->toocheke_webtoons_host_matches($url, TOOCHEKE_WEBTOONS_ALLOWED_HOST_SUFFIX)) {
            $out['error'] = __('Refused to fetch a non-Webtoons URL.', 'toocheke-companion');
            return $out;
        }

        $response = wp_remote_get($url, [
            'timeout'     => 25,
            'redirection' => 0,
            'user-agent'  => $this->toocheke_webtoons_user_agent(),
            'headers'     => [
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer'         => 'https://www.webtoons.com/en/',
            ],
        ]);

        if (is_wp_error($response)) {
            if ($attempt < 3) {
                sleep(2 * $attempt);
                return $this->toocheke_webtoons_http_get($url, $attempt + 1, $hops);
            }
            /* translators: %s: underlying connection error */
            $out['error'] = sprintf(__('Connection error: %s', 'toocheke-companion'), $response->get_error_message());
            return $out;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code >= 300 && $code < 400) {
            $target = $this->toocheke_webtoons_redirect_target($url, wp_remote_retrieve_header($response, 'location'));
            $path   = strtolower((string) wp_parse_url($target, PHP_URL_PATH));
            if (preg_match('~age-?gate|login|signin|/member~', $path)) {
                $out['gated']     = true;
                $out['final_url'] = $target;
                return $out;
            }
            if ('' === $target || $hops >= 3) {
                $out['error'] = __('Webtoons sent this page to an unexpected place.', 'toocheke-companion');
                return $out;
            }
            return $this->toocheke_webtoons_http_get($target, 1, $hops + 1);
        }

        if (429 === $code || 503 === $code || 403 === $code) {
            // A 403 on one page while its neighbours load fine is
            // usually a temporary block, and backing off clears it.
            $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
            if ($retry_after <= 0) {
                $retry_after = $this->toocheke_webtoons_backoff_seconds();
            }
            $out['throttled']   = true;
            $out['retry_after'] = min(900, max(15, $retry_after));
            return $out;
        }

        if (404 === $code || 410 === $code) {
            $out['missing'] = true;
            return $out;
        }

        if ($code < 200 || $code >= 300) {
            if ($attempt < 3) {
                sleep(2 * $attempt);
                return $this->toocheke_webtoons_http_get($url, $attempt + 1, $hops);
            }
            /* translators: 1: HTTP status code, 2: number of attempts made */
            $out['error'] = sprintf(__('Webtoons returned HTTP %1$d for this page (after %2$d attempts).', 'toocheke-companion'), $code, $attempt);
            return $out;
        }

        $body = wp_remote_retrieve_body($response);

        if ('' === trim($body) && $attempt < 3) {
            // An empty 200 is unusual enough to be worth a retry.
            sleep(2 * $attempt);
            return $this->toocheke_webtoons_http_get($url, $attempt + 1, $hops);
        }

        $out['body']  = $body;
        $out['error'] = '' === trim($body) ? __('Webtoons returned an empty page.', 'toocheke-companion') : '';
        return $out;
    }

    // Image downloads need a Referer from webtoons.com or the CDN
    // answers 403. Added only while one of our own sideloads runs.
    public function toocheke_webtoons_image_request_args($args, $url)
    {
        if ($this->toocheke_webtoons_host_matches($url, TOOCHEKE_WEBTOONS_IMAGE_HOST_SUFFIX)) {
            if (! isset($args['headers']) || ! is_array($args['headers'])) {
                $args['headers'] = [];
            }
            $args['headers']['Referer'] = 'https://www.webtoons.com/';
            $args['user-agent']         = $this->toocheke_webtoons_user_agent();
        }
        return $args;
    }

    // Panels are only ever shown at full size, so skip the resized
    // copies — an episode can be over a hundred strips.
    public function toocheke_webtoons_no_image_sizes($sizes)
    {
        return [];
    }

    // Thumbnails come as a cropped rendition (?type=...) of the real
    // image — try the bare URL for the full-size one first, and fall
    // back to the URL as given if that fails.
    protected function toocheke_webtoons_sideload_largest($url, $post_id, $desc)
    {
        $stripped = $this->toocheke_webtoons_strip_query($url);

        if ($stripped && $stripped !== $url) {
            $attachment_id = $this->toocheke_webtoons_sideload_image($stripped, $post_id, $desc);
            if ($attachment_id) {
                return $attachment_id;
            }
        }

        return $this->toocheke_webtoons_sideload_image($url, $post_id, $desc);
    }

    // Wraps media_sideload_image(): confines requests to the image
    // CDN, adds the Referer, loads the admin includes it needs, and
    // always returns an attachment ID or 0 (its return type otherwise
    // varies by WP version).
    protected function toocheke_webtoons_sideload_image($url, $post_id, $desc, $panel = false)
    {
        if (empty($url) || ! $this->toocheke_webtoons_host_matches($url, TOOCHEKE_WEBTOONS_IMAGE_HOST_SUFFIX)) {
            return 0;
        }

        if (! function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (! function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (! function_exists('wp_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        add_filter('http_request_args', [$this, 'toocheke_webtoons_image_request_args'], 10, 2);
        if ($panel) {
            add_filter('intermediate_image_sizes_advanced', [$this, 'toocheke_webtoons_no_image_sizes']);
        }

        $result = media_sideload_image($url, $post_id, $desc, 'id');

        remove_filter('http_request_args', [$this, 'toocheke_webtoons_image_request_args'], 10);
        if ($panel) {
            remove_filter('intermediate_image_sizes_advanced', [$this, 'toocheke_webtoons_no_image_sizes']);
        }

        if (is_wp_error($result)) {
            error_log(sprintf('[Toocheke Webtoons Import] Failed to sideload image %s: %s', $url, $result->get_error_message()));
            return 0;
        }

        return (int) $result;
    }

    protected function toocheke_webtoons_genre_slugs()
    {
        return ['drama', 'fantasy', 'comedy', 'action', 'slice-of-life', 'romance', 'super-hero', 'sf', 'thriller', 'supernatural', 'mystery', 'sports', 'historical', 'heartwarming', 'horror', 'graphic-novel', 'tiptoon'];
    }

    // Accepts an English series list URL (Canvas or an Original's
    // genre path), or any episode URL of the series, and returns the
    // pieces of its list URL. Strict since this feeds directly into an
    // outbound request.
    protected function toocheke_webtoons_validate_series_url($url)
    {
        $url = esc_url_raw($url);
        if ('' === $url) {
            return false;
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if (! in_array($host, ['www.webtoons.com', 'webtoons.com', 'm.webtoons.com'], true)) {
            return false;
        }

        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $args);
        $title_no = isset($args['title_no']) ? absint($args['title_no']) : 0;

        if (! $title_no || ! preg_match('~^/en/([A-Za-z_-]+)/([A-Za-z0-9_-]+)/(?:list|[A-Za-z0-9_%-]+/viewer)/?$~', $path, $m)) {
            return false;
        }

        // "challenge" is the older name for Canvas.
        $group = strtolower(str_replace('_', '-', $m[1]));
        if ('challenge' === $group) {
            $group = 'canvas';
        }
        if ('canvas' !== $group && ! in_array($group, $this->toocheke_webtoons_genre_slugs(), true)) {
            return false;
        }

        return [
            'url'      => 'https://www.webtoons.com/en/' . $group . '/' . $m[2] . '/list?title_no=' . $title_no,
            'slug'     => $m[2],
            'title_no' => $title_no,
            'kind'     => 'canvas' === $group ? 'canvas' : 'original',
        ];
    }
}
