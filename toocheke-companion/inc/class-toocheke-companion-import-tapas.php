<?php
/**
 * Toocheke Companion — Import from Tapas.io.
 *
 * Lets a comic creator migrate one or more series off Tapas.io into this site's own 'series'/'comic' post types.
 *
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (! defined('TOOCHEKE_TAPAS_IMPORT_OPTION')) {
    define('TOOCHEKE_TAPAS_IMPORT_OPTION', 'toocheke_tapas_import_job');
}

// Hosts this importer is ever allowed to request. Tapas serves its pages
// from tapas.io and its comic-panel/thumbnail images from a per-region
// CDN subdomain (seen in the wild as us-a.tapas.io); allow the whole
// *.tapas.io family for images while keeping page fetches on the bare
// apex domain.
if (! defined('TOOCHEKE_TAPAS_ALLOWED_HOST_SUFFIX')) {
    define('TOOCHEKE_TAPAS_ALLOWED_HOST_SUFFIX', 'tapas.io');
}

trait Toocheke_Companion_Import_Tapas
{
    /* =========================================================================
       HOOK REGISTRATION
       Called once from init() in toocheke-companion.php, matching the
       pattern used by the Bluesky/Notifications features — see those
       files' toocheke_*_register_hooks() for precedent.
    ========================================================================= */

    public function toocheke_tapas_register_hooks()
    {
        if (! is_admin()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'toocheke_tapas_enqueue_admin_assets']);
        add_action('admin_notices',         [$this, 'toocheke_tapas_admin_error_notice']);

        add_action('wp_ajax_toocheke_tapas_import_start',   [$this, 'toocheke_tapas_ajax_start']);
        add_action('wp_ajax_toocheke_tapas_import_step',    [$this, 'toocheke_tapas_ajax_step']);
        add_action('wp_ajax_toocheke_tapas_import_status',  [$this, 'toocheke_tapas_ajax_status']);
        add_action('wp_ajax_toocheke_tapas_import_discard', [$this, 'toocheke_tapas_ajax_discard']);
        add_action('wp_ajax_toocheke_tapas_import_retry',   [$this, 'toocheke_tapas_ajax_retry_series']);
        add_action('wp_ajax_toocheke_tapas_import_resume_from', [$this, 'toocheke_tapas_ajax_resume_from']);
        add_action('wp_ajax_toocheke_tapas_dismiss_error',  [$this, 'toocheke_tapas_ajax_dismiss_error']);
    }

    /* =========================================================================
       ADMIN ASSETS
    ========================================================================= */

    public function toocheke_tapas_enqueue_admin_assets($hook)
    {
        // Note: this page is a submenu of our own custom top-level menu
        // ('toocheke-menu'), so its $hook suffix is
        // 'toocheke-menu_page_toocheke-import-tapas', NOT 'admin.php' —
        // checking $_GET['page'] alone (matching how every other
        // conditionally-enqueued asset in this plugin does it, e.g.
        // toocheke_bluesky_enqueue_admin_assets()) is what actually works.
        if (empty($_GET['page']) || 'toocheke-import-tapas' !== $_GET['page']) {
            return;
        }

        $css_path = TOOCHEKE_COMPANION_PLUGIN_DIR . 'css/toocheke-tapas-import.css';
        $js_path  = TOOCHEKE_COMPANION_PLUGIN_DIR . 'js/toocheke-tapas-import.js';

        wp_enqueue_style(
            'toocheke-tapas-import',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'css/toocheke-tapas-import.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : TOOCHEKE_COMPANION_VERSION
        );

        wp_enqueue_script(
            'toocheke-tapas-import',
            TOOCHEKE_COMPANION_PLUGIN_URL . 'js/toocheke-tapas-import.js',
            ['jquery'],
            file_exists($js_path) ? filemtime($js_path) : TOOCHEKE_COMPANION_VERSION,
            true
        );

        wp_localize_script('toocheke-tapas-import', 'toochekeTapasImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('toocheke_tapas_import'),
            'diag'    => [
                'pluginVersion' => TOOCHEKE_COMPANION_VERSION,
                'wpVersion'     => get_bloginfo('version'),
                'phpVersion'    => PHP_VERSION,
            ],
            'i18n'    => [
                'resolving'      => __('Reading series page…', 'toocheke-companion'),
                /* translators: %s: series title or slug */
                'importing'      => __('Importing “%s”…', 'toocheke-companion'),
                /* translators: 1: series title or slug, 2: number of episodes imported */
                'seriesDone'     => __('Finished “%1$s” — %2$d episode(s) imported.', 'toocheke-companion'),
                'allDone'        => __('All done! Your Tapas series have been imported.', 'toocheke-companion'),
                /* translators: %d: seconds until the next automatic retry */
                'throttled'      => __('Tapas is rate-limiting requests. Retrying automatically in %d seconds…', 'toocheke-companion'),
                'throttledManual'=> __('Tapas is still rate-limiting requests. Click “Resume Import” to keep going whenever you\'re ready.', 'toocheke-companion'),
                /* translators: %s: the underlying error message */
                'genericError'   => __('Something went wrong: %s', 'toocheke-companion'),
                'confirmDiscard' => __('Discard the current import and start over? Anything already imported will stay on your site.', 'toocheke-companion'),
                'confirmStart'   => __('This will start importing the series listed above. Continue?', 'toocheke-companion'),
            ],
        ]);
    }

    /* =========================================================================
       ADMIN PAGE
    ========================================================================= */

    public function toocheke_tapas_render_import_page()
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'toocheke-companion'));
        }

        $job = $this->toocheke_tapas_get_job();
        $has_existing_job = ! empty($job['series']) && 'completed' !== $job['status'];
        ?>
        <div class="wrap toocheke-tapas-import-wrap">
            <h2><?php esc_html_e('Import From Tapas.io', 'toocheke-companion'); ?></h2>

            <div class="notice notice-warning">
                <p>
                    <b><?php esc_html_e('Please note!', 'toocheke-companion'); ?></b>
                    <?php esc_html_e('To avoid any rate limiting from Tapas, the import process is intentionally paced, so a large series (hundreds or thousands of episodes) can take a while — you can safely close this page and come back later to resume exactly where it left off.', 'toocheke-companion'); ?>
                    <?php esc_html_e('Only episodes Tapas serves for free to anonymous visitors can be imported — locked/paid and mature-gated episodes will be skipped.', 'toocheke-companion'); ?>
                </p>
            </div>

            <h3><?php esc_html_e('Series to import', 'toocheke-companion'); ?></h3>
            <p>
                <?php esc_html_e('Enter one or more Tapas series URLs, e.g. https://tapas.io/series/your-series-name — each will become its own Series post here, with every episode imported underneath it as a Comic post, in order.', 'toocheke-companion'); ?>
            </p>

            <div id="toocheke-tapas-url-rows" class="toocheke-tapas-url-rows">
                <div class="toocheke-tapas-url-row">
                    <input type="url" class="regular-text toocheke-tapas-url-input" placeholder="https://tapas.io/series/your-series-name" />
                    <button type="button" class="button toocheke-tapas-remove-row" aria-label="<?php esc_attr_e('Remove', 'toocheke-companion'); ?>">&times;</button>
                </div>
            </div>
            <p>
                <button type="button" id="toocheke-tapas-add-row" class="button"><?php esc_html_e('+ Add another series', 'toocheke-companion'); ?></button>
            </p>

            <p class="submit">
                <button type="button" id="toocheke-tapas-start" class="button button-primary"><?php esc_html_e('Start Import', 'toocheke-companion'); ?></button>
                <button type="button" id="toocheke-tapas-resume" class="button button-primary" style="<?php echo $has_existing_job ? '' : 'display:none;'; ?>"><?php esc_html_e('Resume Import', 'toocheke-companion'); ?></button>
                <button type="button" id="toocheke-tapas-discard" class="button" style="<?php echo $has_existing_job ? '' : 'display:none;'; ?>"><?php esc_html_e('Discard & Start Over', 'toocheke-companion'); ?></button>
            </p>

            <div id="toocheke-tapas-progress" class="toocheke-tapas-progress" style="display:none;">
                <div class="toocheke-tapas-overall">
                    <div class="toocheke-tapas-overall-label"></div>
                    <div class="toocheke-tapas-progressbar toocheke-tapas-progressbar--overall">
                        <div class="toocheke-tapas-progressbar-fill"></div>
                    </div>
                </div>
                <div class="toocheke-tapas-series-card">
                    <h4 class="toocheke-tapas-series-title"></h4>
                    <div class="toocheke-tapas-progressbar toocheke-tapas-progressbar--series">
                        <div class="toocheke-tapas-progressbar-fill"></div>
                    </div>
                    <p class="toocheke-tapas-series-status"></p>
                    <p class="toocheke-tapas-copy-row" style="display:none;">
                        <button type="button" class="button button-primary toocheke-tapas-retry-series"><?php esc_html_e('Retry This Series', 'toocheke-companion'); ?></button>
                        <button type="button" class="button toocheke-tapas-copy-report"><?php esc_html_e('Copy Diagnostic Report', 'toocheke-companion'); ?></button>
                        <span class="toocheke-tapas-copy-confirm" style="display:none;color:#00a32a;"><?php esc_html_e('Copied!', 'toocheke-companion'); ?></span>
                    </p>
                    <p class="toocheke-tapas-resume-row" style="display:none;">
                        <label for="toocheke-tapas-resume-episode"><?php esc_html_e('Or, if that episode won\'t load (e.g. it\'s mature-gated), find the next one that does load on Tapas and resume from there:', 'toocheke-companion'); ?></label><br />
                        <input type="text" id="toocheke-tapas-resume-episode" class="regular-text" placeholder="https://tapas.io/episode/12345 or just 12345" />
                        <button type="button" class="button toocheke-tapas-resume-from"><?php esc_html_e('Resume From This Episode', 'toocheke-companion'); ?></button>
                    </p>
                </div>
                <div class="toocheke-tapas-log"></div>
            </div>

            <div id="toocheke-tapas-existing-job" style="<?php echo $has_existing_job ? '' : 'display:none;'; ?>">
                <p><em><?php esc_html_e('You have an import in progress. Click “Resume Import” above to continue it, or “Discard & Start Over” to abandon it (anything already imported stays on your site either way).', 'toocheke-companion'); ?></em></p>
            </div>
        </div>
        <?php
    }

    /* =========================================================================
       JOB STATE (single, not-autoloaded option — see class docblock)
    ========================================================================= */

    protected function toocheke_tapas_default_job()
    {
        return [
            'status'        => 'idle', // idle | running | throttled | completed | error
            'current_index' => 0,
            'series'        => [],
            'updated'       => time(),
        ];
    }

    protected function toocheke_tapas_get_job()
    {
        $job = get_option(TOOCHEKE_TAPAS_IMPORT_OPTION, null);
        if (! is_array($job) || empty($job['series']) || ! is_array($job['series'])) {
            return $this->toocheke_tapas_default_job();
        }
        return wp_parse_args($job, $this->toocheke_tapas_default_job());
    }

    protected function toocheke_tapas_save_job($job)
    {
        $job['updated'] = time();
        update_option(TOOCHEKE_TAPAS_IMPORT_OPTION, $job, false);
    }

    protected function toocheke_tapas_delete_job()
    {
        delete_option(TOOCHEKE_TAPAS_IMPORT_OPTION);
    }

    /**
     * Escalating backoff for a URL that keeps coming back throttled
     * (genuine 429/503 rate-limiting, or a 403 that looks like a
     * transient anti-bot block rather than the content itself being
     * gated — see toocheke_tapas_http_get()). Each consecutive strike on
     * the SAME series entry doubles the wait, capped at 10 minutes, so a
     * stubborn block gets progressively more patience rather than either
     * giving up too fast or hammering Tapas at a fixed interval forever.
     * Resets to zero the moment a fetch succeeds — see
     * toocheke_tapas_reset_backoff_strikes().
     */
    protected function toocheke_tapas_apply_backoff_strike(array &$entry, array $fetch)
    {
        $entry['throttle_strikes'] = isset($entry['throttle_strikes']) ? $entry['throttle_strikes'] + 1 : 1;

        if ($entry['throttle_strikes'] > 8) {
            // Persistent enough, across enough automatic and manual
            // retries, that it's very unlikely to be a passing block —
            // stop asking Tapas and hand control back to the creator
            // (Retry This Series, or Resume From This Episode) instead
            // of retrying forever.
            return ['throttled' => false, 'gave_up' => true];
        }

        $base   = ! empty($fetch['retry_after']) ? (int) $fetch['retry_after'] : $this->toocheke_tapas_backoff_seconds();
        $waited = min(600, $base * (2 ** min(5, $entry['throttle_strikes'] - 1)));

        // Only log every so often, not on every single strike, so a long
        // stretch of backoff-and-retry doesn't flood the rolling log.
        if (1 === $entry['throttle_strikes'] || 0 === $entry['throttle_strikes'] % 3) {
            $this->toocheke_tapas_log($entry, sprintf(
                /* translators: 1: attempt count, 2: seconds until the next retry */
                __('Tapas is temporarily blocking requests (attempt %1$d) — waiting %2$ds before trying this page again.', 'toocheke-companion'),
                $entry['throttle_strikes'],
                $waited
            ));
        }

        return ['throttled' => true, 'retry_after' => $waited];
    }

    protected function toocheke_tapas_reset_backoff_strikes(array &$entry)
    {
        $entry['throttle_strikes'] = 0;
    }

    /**
     * Appends a short status line to a series' rolling log, capped so the
     * option never grows unbounded across a multi-thousand-episode run.
     */
    protected function toocheke_tapas_log(array &$series_entry, $message)
    {
        if (empty($series_entry['log']) || ! is_array($series_entry['log'])) {
            $series_entry['log'] = [];
        }
        $series_entry['log'][] = $message;
        if (count($series_entry['log']) > 12) {
            $series_entry['log'] = array_slice($series_entry['log'], -12);
        }
    }

    /**
     * Every episode/series-page problem that stops a series (a hard fetch
     * failure, or a suspiciously short finish) gets appended here, so a
     * run with more than one distinct problem shows all of them together
     * rather than only the most recent one silently replacing the last.
     */
    protected function toocheke_tapas_flag_episode(array &$entry, $tapas_episode_id, $message)
    {
        if (empty($entry['flagged_episodes']) || ! is_array($entry['flagged_episodes'])) {
            $entry['flagged_episodes'] = [];
        }
        $entry['flagged_episodes'][] = [
            'episode_id' => $tapas_episode_id,
            'message'    => $message,
            'time'       => current_time('mysql'),
        ];
        // Bounded for the same reason as the rolling log — a pathological
        // run shouldn't grow the option without limit.
        if (count($entry['flagged_episodes']) > 25) {
            $entry['flagged_episodes'] = array_slice($entry['flagged_episodes'], -25);
        }
    }

    /**
     * Captures everything needed for a webcomic creator to hand this off
     * to a developer for troubleshooting: what failed, on which series/
     * episode, and what environment it happened in. Stored as its own
     * option (separate from the job state) so it survives a Discard, and
     * surfaced two ways — an admin notice on every wp-admin page (so it
     * can't be missed even if the creator has navigated away from the
     * import page) and a "Copy Diagnostic Report" button on the import
     * page itself while it's still open.
     */
    protected function toocheke_tapas_record_failure(array &$entry, $tapas_episode_id = null)
    {
        $this->toocheke_tapas_flag_episode($entry, $tapas_episode_id, $entry['error']);

        update_option('toocheke_tapas_import_last_error', [
            'type'             => 'error',
            'message'          => $entry['error'],
            'series_slug'      => $entry['slug'],
            'series_url'       => $entry['url'],
            'tapas_episode_id' => $tapas_episode_id,
            'flagged_episodes' => $entry['flagged_episodes'],
            'time'             => current_time('mysql'),
            'plugin_version'   => TOOCHEKE_COMPANION_VERSION,
            'wp_version'       => get_bloginfo('version'),
            'php_version'      => PHP_VERSION,
        ], false);
    }

    /**
     * Same mechanism as toocheke_tapas_record_failure(), for the "finished,
     * but suspiciously short of Tapas' own episode count" case — worth the
     * creator's attention, but not phrased as a hard failure since the
     * import itself didn't error out.
     */
    protected function toocheke_tapas_record_incomplete_notice(array &$entry, $actual_count)
    {
        $this->toocheke_tapas_flag_episode($entry, $entry['last_episode_id'], $entry['warning']);
        $this->toocheke_tapas_write_error_option($entry, 'incomplete');
    }

    /**
     * Used when one or more episodes were already individually flagged
     * (see toocheke_tapas_flag_episode() calls in
     * toocheke_tapas_import_episode_data()) and all that's needed is to
     * surface the existing list — avoids tacking on a redundant summary
     * entry the way calling toocheke_tapas_record_incomplete_notice()
     * here would (it always adds one more flag of its own).
     */
    protected function toocheke_tapas_record_existing_flags(array &$entry)
    {
        $this->toocheke_tapas_write_error_option($entry, 'incomplete');
    }

    protected function toocheke_tapas_write_error_option(array $entry, $type)
    {
        update_option('toocheke_tapas_import_last_error', [
            'type'             => $type,
            'message'          => $entry['warning'],
            'series_slug'      => $entry['slug'],
            'series_url'       => $entry['url'],
            'tapas_episode_id' => $entry['last_episode_id'],
            'flagged_episodes' => $entry['flagged_episodes'],
            'time'             => current_time('mysql'),
            'plugin_version'   => TOOCHEKE_COMPANION_VERSION,
            'wp_version'       => get_bloginfo('version'),
            'php_version'      => PHP_VERSION,
        ], false);
    }

    /**
     * Site-wide admin notice for the last recorded import failure (see
     * toocheke_tapas_record_failure()). Deliberately not scoped to the
     * Tapas import page — the whole point is that a creator running a
     * multi-hour import in a background tab still sees it show up
     * wherever they're working in wp-admin.
     */
    public function toocheke_tapas_admin_error_notice()
    {
        if (! current_user_can('edit_posts')) {
            return;
        }

        $err = get_option('toocheke_tapas_import_last_error');
        if (empty($err) || empty($err['message'])) {
            return;
        }

        $is_incomplete = isset($err['type']) && 'incomplete' === $err['type'];

        $report  = 'Toocheke Companion — Tapas Import ' . ($is_incomplete ? 'Shortfall' : 'Error') . " Report\n";
        $report .= 'Plugin version: ' . $err['plugin_version'] . "\n";
        $report .= 'WordPress version: ' . $err['wp_version'] . "\n";
        $report .= 'PHP version: ' . $err['php_version'] . "\n";
        $report .= 'Series: ' . $err['series_slug'] . ' (' . $err['series_url'] . ")\n";
        if (! empty($err['tapas_episode_id'])) {
            $report .= ($is_incomplete ? 'Stopped after Tapas episode #' : 'Failed on Tapas episode #') . $err['tapas_episode_id'] . "\n";
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

        $notice_id = 'toocheke-tapas-error-notice';
        ?>
        <div id="<?php echo esc_attr($notice_id); ?>" class="notice <?php echo $is_incomplete ? 'notice-warning' : 'notice-error'; ?>">
            <p>
                <b>
                <?php
                echo $is_incomplete
                    ? esc_html__('Tapas Import may be missing some episodes', 'toocheke-companion')
                    : esc_html__('Tapas Import ran into a problem', 'toocheke-companion');
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
                data.append('action', 'toocheke_tapas_dismiss_error');
                data.append('nonce', '<?php echo esc_js(wp_create_nonce('toocheke_tapas_import')); ?>');
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data });
                wrap.remove();
            });
        })();
        </script>
        <?php
    }

    public function toocheke_tapas_ajax_dismiss_error()
    {
        $this->toocheke_tapas_ajax_guard();
        delete_option('toocheke_tapas_import_last_error');
        wp_send_json_success();
    }

    /* =========================================================================
       AJAX: start / step / status / discard
    ========================================================================= */

    public function toocheke_tapas_ajax_start()
    {
        $this->toocheke_tapas_ajax_guard();

        $raw_urls = isset($_POST['urls']) ? (array) wp_unslash($_POST['urls']) : [];
        $entries  = [];
        $errors   = [];

        foreach ($raw_urls as $raw_url) {
            $raw_url = trim(sanitize_text_field($raw_url));
            if ('' === $raw_url) {
                continue;
            }

            $slug = $this->toocheke_tapas_validate_series_url($raw_url);
            if (! $slug) {
                /* translators: %s: the URL the user entered */
                $errors[] = sprintf(__('“%s” doesn\'t look like a Tapas series URL (expected https://tapas.io/series/NAME).', 'toocheke-companion'), $raw_url);
                continue;
            }

            $entries[] = [
                'url'               => 'https://tapas.io/series/' . $slug,
                'slug'              => $slug,
                'status'            => 'pending', // pending | resolving | importing | done | failed
                'tapas_series_id'   => null,
                'series_post_id'    => null,
                'next_id'           => null, // next episode id to fetch, or -1 when there is none
                'episodes_done'     => 0,
                'episodes_skipped'  => 0,
                'skip_reasons'      => [], // 'locked' | 'mature' | 'unknown' => count
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
            wp_send_json_error(['message' => empty($errors) ? __('Please enter at least one Tapas series URL.', 'toocheke-companion') : implode(' ', $errors)]);
        }

        $job                   = $this->toocheke_tapas_default_job();
        $job['status']         = 'running';
        $job['series']         = $entries;
        $job['current_index']  = 0;
        $this->toocheke_tapas_save_job($job);
        delete_option('toocheke_tapas_import_last_error');

        wp_send_json_success([
            'job'      => $this->toocheke_tapas_job_for_js($job),
            'warnings' => $errors,
        ]);
    }

    public function toocheke_tapas_ajax_status()
    {
        $this->toocheke_tapas_ajax_guard();
        $job = $this->toocheke_tapas_get_job();
        wp_send_json_success(['job' => $this->toocheke_tapas_job_for_js($job)]);
    }

    public function toocheke_tapas_ajax_discard()
    {
        $this->toocheke_tapas_ajax_guard();
        $this->toocheke_tapas_delete_job();
        delete_option('toocheke_tapas_import_last_error');
        wp_send_json_success(['job' => $this->toocheke_tapas_job_for_js($this->toocheke_tapas_default_job())]);
    }

    /**
     * Retries a single failed series in place, picking up from exactly
     * where it stopped rather than re-fetching everything from episode
     * #1 (that would also work, thanks to the idempotent dedup checks —
     * but this is faster and doesn't re-request pages that already
     * succeeded). If the series never got far enough to create a Series
     * post, this puts it back to 'pending' so it resolves from scratch;
     * otherwise it goes back to 'importing' with its existing cursor
     * (next_id) intact, so the very next step retries the episode that
     * failed.
     */
    public function toocheke_tapas_ajax_retry_series()
    {
        $this->toocheke_tapas_ajax_guard();

        $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
        $job  = $this->toocheke_tapas_get_job();

        $found_index = null;
        foreach ($job['series'] as $index => $entry) {
            $is_failed         = 'failed' === $entry['status'];
            // Retrying only makes sense when it could plausibly change
            // the outcome — a plain skip-summary (locked/mature content
            // that will still be locked/mature next time) doesn't
            // qualify, only an unexplained shortfall does.
            $is_short_finish   = 'done' === $entry['status'] && ! empty($entry['warning']) && empty($entry['episodes_skipped']);
            if ($entry['slug'] === $slug && ($is_failed || $is_short_finish)) {
                $found_index = $index;
                break;
            }
        }

        if (null === $found_index) {
            wp_send_json_error(['message' => __('That series isn\'t in a retryable state (it may have already been retried).', 'toocheke-companion')]);
        }

        $entry = &$job['series'][$found_index];

        if ('failed' === $entry['status']) {
            // A genuine fetch/insert failure: resume from exactly the
            // cursor it stopped on.
            $entry['status'] = $entry['series_post_id'] ? 'importing' : 'pending';
        } else {
            // "Done, but short of Tapas' own count": the cursor is -1
            // (Tapas said "no next"), so there's nothing to resume from —
            // the only way to find out if more episodes actually exist is
            // to walk the chain again from episode #1. Every episode
            // already on the site is recognized and skipped (see
            // toocheke_tapas_find_existing_comic_post()), so this is slower
            // than a true resume but never re-imports anything.
            $entry['status']         = 'pending';
            $entry['next_id']        = null;
            $entry['episodes_done']  = 0;
        }

        $entry['error']   = '';
        $entry['warning'] = '';
        $this->toocheke_tapas_log($entry, __('Retrying…', 'toocheke-companion'));

        $job['current_index'] = $found_index;
        $job['status']        = 'running';
        $this->toocheke_tapas_save_job($job);
        delete_option('toocheke_tapas_import_last_error');

        wp_send_json_success(['job' => $this->toocheke_tapas_job_for_js($job)]);
    }

    /**
     * Manual escape hatch for when an episode simply can't be fetched by
     * the importer no matter how many times it retries (most commonly:
     * mature/NSFW-gated content that requires being signed in — see the
     * 403 handling in toocheke_tapas_http_get()). There's no way to
     * automatically discover what comes after an unreadable page, so
     * this lets the creator check Tapas themselves, find the next
     * episode that IS reachable, and point the importer at it directly —
     * skipping the gated one rather than losing the rest of the series.
     */
    public function toocheke_tapas_ajax_resume_from()
    {
        $this->toocheke_tapas_ajax_guard();

        $slug       = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
        $episode_id = isset($_POST['episode_id']) ? $this->toocheke_tapas_extract_episode_id(wp_unslash($_POST['episode_id'])) : 0;
        $job        = $this->toocheke_tapas_get_job();

        if ($episode_id <= 0) {
            wp_send_json_error(['message' => __('That doesn\'t look like a Tapas episode URL or ID. Paste something like https://tapas.io/episode/1761200, or just the number.', 'toocheke-companion')]);
        }

        $found_index = null;
        foreach ($job['series'] as $index => $entry) {
            if ($entry['slug'] === $slug && ('failed' === $entry['status'] || 'done' === $entry['status'])) {
                $found_index = $index;
                break;
            }
        }

        if (null === $found_index) {
            wp_send_json_error(['message' => __('Couldn\'t find that series in the current import.', 'toocheke-companion')]);
        }

        $entry = &$job['series'][$found_index];

        if (empty($entry['series_post_id'])) {
            wp_send_json_error(['message' => __('This series hasn\'t created its Series post yet, so there\'s nothing to resume into — use Retry instead.', 'toocheke-companion')]);
        }

        $entry['next_id']  = $episode_id;
        $entry['status']   = 'importing';
        $entry['error']    = '';
        $entry['warning']  = '';
        $this->toocheke_tapas_log($entry, sprintf(
            /* translators: %d: Tapas episode id */
            __('Manually resumed from episode #%d.', 'toocheke-companion'),
            $episode_id
        ));

        $job['current_index'] = $found_index;
        $job['status']        = 'running';
        $this->toocheke_tapas_save_job($job);
        delete_option('toocheke_tapas_import_last_error');

        wp_send_json_success(['job' => $this->toocheke_tapas_job_for_js($job)]);
    }

    /**
     * Accepts either a bare number or a Tapas episode URL
     * (https://tapas.io/episode/12345[?...]) and returns the numeric
     * episode id, or 0 if neither pattern matches.
     */
    protected function toocheke_tapas_extract_episode_id($raw)
    {
        $raw = trim((string) $raw);
        if (ctype_digit($raw)) {
            return (int) $raw;
        }
        if (preg_match('~^https://tapas\.io/episode/(\d+)~', $raw, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    /**
     * Processes exactly ONE unit of work (resolving a series, or importing
     * one episode of the currently-active series) and returns. The JS side
     * is what loops — see js/toocheke-tapas-import.js — so this never has
     * to worry about PHP execution time limits piling up across a large
     * series. It CAN still take a while within a single step, though, for
     * an episode with many panel images (each is its own download +
     * Media Library attach) — hence raising the time limit below rather
     * than relying on whatever a given host's default happens to be.
     */
    public function toocheke_tapas_ajax_step()
    {
        $this->toocheke_tapas_ajax_guard();

        // Suppressed: some hosts disable this function entirely (it's a
        // no-op there, not an error), and this is a "best effort, not
        // load-bearing" raise — see the docblock above.
        @set_time_limit(120); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_time_limit

        $job = $this->toocheke_tapas_get_job();

        if (empty($job['series'])) {
            wp_send_json_success(['job' => $this->toocheke_tapas_job_for_js($job), 'finished' => true]);
        }

        // Advance past any already-finished series at the front of the queue.
        while ($job['current_index'] < count($job['series'])
            && in_array($job['series'][$job['current_index']]['status'], ['done', 'failed'], true)) {
            $job['current_index']++;
        }

        if ($job['current_index'] >= count($job['series'])) {
            $job['status'] = 'completed';
            $this->toocheke_tapas_save_job($job);
            wp_send_json_success(['job' => $this->toocheke_tapas_job_for_js($job), 'finished' => true]);
        }

        $index = $job['current_index'];
        $entry = &$job['series'][$index];

        // Every unit of work funnels through here so that any Tapas
        // request failure is handled in exactly one place.
        $result = $this->toocheke_tapas_process_one_unit($entry);

        if (! empty($result['throttled'])) {
            $job['status'] = 'throttled';
            $this->toocheke_tapas_save_job($job);
            wp_send_json_success([
                'job'            => $this->toocheke_tapas_job_for_js($job),
                'finished'       => false,
                'throttled'      => true,
                'retry_after'    => $result['retry_after'],
            ]);
        }

        $job['status'] = 'running';
        $this->toocheke_tapas_save_job($job);

        wp_send_json_success([
            'job'      => $this->toocheke_tapas_job_for_js($job),
            'finished' => false,
        ]);
    }

    protected function toocheke_tapas_ajax_guard()
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'toocheke-companion')], 403);
        }
        check_ajax_referer('toocheke_tapas_import', 'nonce');
    }

    /**
     * Strips the job down to what the browser actually needs (no internal
     * cursor bookkeeping) and computes the small derived fields the
     * progress UI displays.
     */
    protected function toocheke_tapas_job_for_js(array $job)
    {
        $series_out = [];
        foreach ($job['series'] as $entry) {
            $series_out[] = [
                'slug'               => $entry['slug'],
                'status'             => $entry['status'],
                'episodes_done'      => (int) $entry['episodes_done'],
                'episodes_skipped'   => isset($entry['episodes_skipped']) ? (int) $entry['episodes_skipped'] : 0,
                'skip_reasons'       => isset($entry['skip_reasons']) ? $entry['skip_reasons'] : [],
                'episodes_total'     => $entry['episodes_total'],
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

    /* =========================================================================
       CORE STATE MACHINE — one series entry, one call = one unit of work
    ========================================================================= */

    protected function toocheke_tapas_process_one_unit(array &$entry)
    {
        if ('pending' === $entry['status']) {
            return $this->toocheke_tapas_resolve_series($entry);
        }

        return $this->toocheke_tapas_import_next_episode($entry);
    }

    /**
     * First unit of work for a series: fetch the series URL (which is
     * simultaneously that series' first episode page), create the Series
     * post, and import episode #1 from the same page fetch — no extra
     * request needed for it.
     */
    protected function toocheke_tapas_resolve_series(array &$entry)
    {
        $entry['status'] = 'resolving';

        $fetch = $this->toocheke_tapas_http_get($entry['url']);
        if (! empty($fetch['throttled'])) {
            $backoff = $this->toocheke_tapas_apply_backoff_strike($entry, $fetch);
            if (! empty($backoff['throttled'])) {
                return $backoff;
            }
            $entry['status'] = 'failed';
            $entry['error']  = __('Tapas kept blocking this series\' page even after several waits — stopping here. You can try "Retry This Series" again later.', 'toocheke-companion');
            $this->toocheke_tapas_log($entry, $entry['error']);
            $this->toocheke_tapas_record_failure($entry);
            return ['throttled' => false];
        }
        $this->toocheke_tapas_reset_backoff_strikes($entry);

        if (empty($fetch['body'])) {
            $entry['status'] = 'failed';
            $entry['error']  = $fetch['error'] ?: __('Could not load the series page.', 'toocheke-companion');
            $this->toocheke_tapas_log($entry, $entry['error']);
            $this->toocheke_tapas_record_failure($entry);
            return ['throttled' => false];
        }

        $data = $this->toocheke_tapas_parse_episode_html($fetch['body']);

        if (empty($data['series_id']) || empty($data['episode_id'])) {
            $entry['status'] = 'failed';
            $entry['error']  = __('That page didn\'t look like a Tapas series (it may be mature-content gated behind a login, or the URL is wrong).', 'toocheke-companion');
            $this->toocheke_tapas_log($entry, $entry['error']);
            $this->toocheke_tapas_record_failure($entry);
            return ['throttled' => false];
        }

        $entry['tapas_series_id'] = $data['series_id'];
        $entry['episodes_total']  = $data['episodes_total'];

        // Idempotent: reuse an existing Series post from a previous run
        // rather than creating a duplicate.
        $series_post_id = $this->toocheke_tapas_find_existing_series_post($data['series_id']);

        if (! $series_post_id) {
            $series_post_id = wp_insert_post([
                'post_title'   => wp_strip_all_tags($data['series_title']),
                'post_content' => wp_kses_post($data['series_description']),
                'post_status'  => 'publish',
                'post_type'    => 'series',
            ], true);

            if (is_wp_error($series_post_id)) {
                $entry['status'] = 'failed';
                $entry['error']  = $series_post_id->get_error_message();
                $this->toocheke_tapas_log($entry, $entry['error']);
                $this->toocheke_tapas_record_failure($entry);
                return ['throttled' => false];
            }

            update_post_meta($series_post_id, '_toocheke_tapas_series_id', $data['series_id']);
            update_post_meta($series_post_id, '_toocheke_tapas_series_url', $entry['url']);

            if (! empty($data['series_thumb_url'])) {
                $thumb_id = $this->toocheke_tapas_sideload_largest($data['series_thumb_url'], $series_post_id, $data['series_title']);
                if ($thumb_id) {
                    set_post_thumbnail($series_post_id, $thumb_id);
                }
            }

            $this->toocheke_tapas_log($entry, sprintf(
                /* translators: %s: series title */
                __('Created series “%s”.', 'toocheke-companion'),
                $data['series_title']
            ));
        } else {
            $this->toocheke_tapas_log($entry, __('Found an existing Series post from a previous run — continuing into it.', 'toocheke-companion'));
        }

        $entry['series_post_id'] = $series_post_id;
        $entry['status']         = 'importing';

        // Import episode #1 right now — it's already sitting in $data from
        // this same fetch, so this doesn't cost an extra HTTP request.
        return $this->toocheke_tapas_import_episode_data($entry, $data);
    }

    /**
     * Every subsequent unit of work for an 'importing' series: fetch the
     * next episode (by the cursor left behind by the previous step) and
     * import it.
     */
    protected function toocheke_tapas_import_next_episode(array &$entry)
    {
        if (null === $entry['next_id'] || -1 === (int) $entry['next_id']) {
            return $this->toocheke_tapas_finish_series($entry);
        }

        $episode_url = 'https://tapas.io/episode/' . (int) $entry['next_id'];
        $fetch       = $this->toocheke_tapas_http_get($episode_url);
        if (! empty($fetch['throttled'])) {
            $backoff = $this->toocheke_tapas_apply_backoff_strike($entry, $fetch);
            if (! empty($backoff['throttled'])) {
                return $backoff;
            }
            $entry['error'] = sprintf(
                /* translators: %d: Tapas episode id */
                __('Tapas kept blocking episode #%d even after several waits — stopping here so later episodes aren\'t skipped. Once you\'ve confirmed on Tapas whether that episode is reachable, use "Retry This Series" or "Resume From This Episode" below.', 'toocheke-companion'),
                (int) $entry['next_id']
            );
            $this->toocheke_tapas_log($entry, $entry['error']);
            $entry['status'] = 'failed';
            $this->toocheke_tapas_record_failure($entry, (int) $entry['next_id']);
            return ['throttled' => false];
        }
        $this->toocheke_tapas_reset_backoff_strikes($entry);

        if (empty($fetch['body'])) {
            // A single unreachable episode shouldn't sink the whole
            // series — but toocheke_tapas_http_get() already retried a
            // couple of times internally, so if we're here it genuinely
            // didn't recover; log the real technical reason (not just a
            // generic message) so it can actually be diagnosed. Tapas'
            // own "-1 = no next" convention means we can't discover
            // further episodes past a broken link this way, though, so
            // this stops the series here rather than silently skipping
            // ahead and losing the rest of the chain.
            $entry['error'] = sprintf(
                /* translators: 1: Tapas episode id, 2: underlying technical error */
                __('Could not load episode #%1$d after retrying — stopping here so later episodes aren\'t skipped. Technical detail: %2$s', 'toocheke-companion'),
                (int) $entry['next_id'],
                $fetch['error'] ?: __('unknown error', 'toocheke-companion')
            );
            $this->toocheke_tapas_log($entry, $entry['error']);
            $entry['status'] = 'failed';
            $this->toocheke_tapas_record_failure($entry, (int) $entry['next_id']);
            return ['throttled' => false];
        }

        $data = $this->toocheke_tapas_parse_episode_html($fetch['body']);

        if (empty($data['episode_id'])) {
            $entry['error']  = __('An episode page came back in an unexpected format — stopping this series here.', 'toocheke-companion');
            $this->toocheke_tapas_log($entry, $entry['error']);
            $entry['status'] = 'failed';
            $this->toocheke_tapas_record_failure($entry, (int) $entry['next_id']);
            return ['throttled' => false];
        }

        return $this->toocheke_tapas_import_episode_data($entry, $data);
    }

    /**
     * Turns one parsed episode payload into a Comic post — or, if it was
     * already imported in a prior run, just advances the cursor past it —
     * or, if the page genuinely has nothing to import (most commonly a
     * paid/rental "Wait Until Free" episode, or mature-gated content),
     * skips it without creating a placeholder post at all.
     */
    protected function toocheke_tapas_import_episode_data(array &$entry, array $data)
    {
        $entry['next_id']         = $data['next_id'];
        $entry['last_episode_id'] = $data['episode_id'];

        $comic_post_id = $this->toocheke_tapas_find_existing_comic_post($data['episode_id']);

        if ($comic_post_id && get_post_meta($comic_post_id, '_toocheke_tapas_import_complete', true)) {
            $this->toocheke_tapas_log($entry, sprintf(
                /* translators: %s: episode title */
                __('“%s” was already imported — skipping.', 'toocheke-companion'),
                $data['episode_title']
            ));
        } elseif (! $comic_post_id && empty($data['content_image_urls'])) {
            // Nothing to import and no post started for it yet — don't
            // create an empty placeholder a reader could stumble onto.
            // The reason is worth being specific about where possible,
            // since "mature" and "paid/rental" call for different
            // action from the creator (one truly can't be imported
            // anonymously; the other might just need re-checking).
            if (! empty($data['is_mature'])) {
                $reason_key   = 'mature';
                $reason_label = __('mature/NSFW-gated content — not viewable without being signed in.', 'toocheke-companion');
            } elseif (! empty($data['is_locked'])) {
                $reason_key   = 'locked';
                $reason_label = __('a paid/rental (“Wait Until Free”) episode — Tapas doesn\'t serve images for these to anonymous visitors.', 'toocheke-companion');
            } else {
                $reason_key   = 'unknown';
                $reason_label = __('no panel images were found on its page for an unclear reason — worth checking it directly on Tapas.', 'toocheke-companion');
            }

            $entry['episodes_skipped']++;
            $entry['skip_reasons'][$reason_key] = ! empty($entry['skip_reasons'][$reason_key]) ? $entry['skip_reasons'][$reason_key] + 1 : 1;

            $warning = sprintf(
                /* translators: 1: episode title, 2: reason it was skipped */
                __('Skipped “%1$s” — %2$s', 'toocheke-companion'),
                $data['episode_title'],
                $reason_label
            );
            $this->toocheke_tapas_log($entry, $warning);
            $this->toocheke_tapas_flag_episode($entry, $data['episode_id'], $warning);
        } else {
            if ($comic_post_id) {
                // A post exists for this episode but never got marked
                // complete — most likely a prior step that timed out
                // partway through downloading a many-panel episode's
                // images (see toocheke_tapas_ajax_step()'s time-limit
                // raise), from before the skip-before-create check
                // above existed. Finish it in place rather than
                // creating a second post for the same episode.
                $this->toocheke_tapas_log($entry, sprintf(
                    /* translators: %s: episode title */
                    __('Finishing an incomplete import of “%s” from an earlier interrupted run…', 'toocheke-companion'),
                    $data['episode_title']
                ));
            } else {
                $post_date = $this->toocheke_tapas_parse_episode_date($data['episode_date_str']);

                $postarr = [
                    'post_title'   => wp_strip_all_tags($data['episode_title']),
                    'post_status'  => 'publish',
                    'post_type'    => 'comic',
                    'post_parent'  => (int) $entry['series_post_id'],
                    'post_content' => '',
                ];

                if (! empty($data['story_excerpt'])) {
                    $postarr['post_excerpt'] = wp_strip_all_tags($data['story_excerpt']);
                }

                if ($post_date) {
                    $postarr['post_date']     = $post_date;
                    $postarr['post_date_gmt'] = get_gmt_from_date($post_date);
                }

                $comic_post_id = wp_insert_post($postarr, true);

                if (is_wp_error($comic_post_id)) {
                    $entry['error'] = $comic_post_id->get_error_message();
                    $this->toocheke_tapas_log($entry, $entry['error']);
                    $comic_post_id = 0;
                    // Cursor has already moved on above, so a single bad
                    // insert doesn't wedge the whole series — it just skips
                    // that one episode and keeps going.
                } else {
                    // Tagged with the episode id immediately (so a
                    // timeout doesn't cause a *duplicate* post next
                    // time — see the lookup above), but NOT marked
                    // complete until every image has actually landed.
                    update_post_meta($comic_post_id, '_toocheke_tapas_episode_id', $data['episode_id']);
                    update_post_meta($comic_post_id, '_toocheke_tapas_series_id', $entry['tapas_series_id']);
                }
            }

            if ($comic_post_id) {
                if (! empty($data['episode_thumb_url']) && ! has_post_thumbnail($comic_post_id)) {
                    $thumb_id = $this->toocheke_tapas_sideload_largest($data['episode_thumb_url'], $comic_post_id, $data['episode_title']);
                    if ($thumb_id) {
                        set_post_thumbnail($comic_post_id, $thumb_id);
                    }
                }

                $content = $this->toocheke_tapas_build_comic_content($data['content_image_urls'], $comic_post_id, $data['episode_title']);
                if ('' !== $content) {
                    wp_update_post(['ID' => $comic_post_id, 'post_content' => $content]);
                }

                // Everything for this episode landed — now, and only
                // now, is it safe to treat as done on any future run.
                update_post_meta($comic_post_id, '_toocheke_tapas_import_complete', 1);

                $entry['episodes_done']++;
                $entry['last_episode_title'] = $data['episode_title'];

                if ('' === $content) {
                    // Only reachable now via the legacy-partial-post
                    // path above (a post from before this skip logic
                    // existed, whose images turn out to be
                    // unavailable after all) — same safety-net
                    // reasoning as the skip branch, just for a post
                    // that already exists rather than one about to be
                    // created.
                    $reason = ! empty($data['is_mature'])
                        ? __('this looks like mature/NSFW-gated content.', 'toocheke-companion')
                        : (! empty($data['is_locked'])
                            ? __('this looks like a paid/rental Tapas episode.', 'toocheke-companion')
                            : __('no panel images were found on this episode\'s page.', 'toocheke-companion'));
                    $warning = sprintf(
                        /* translators: 1: episode title, 2: likely reason */
                        __('“%1$s” was created but has NO images — %2$s', 'toocheke-companion'),
                        $data['episode_title'],
                        $reason
                    );
                    $this->toocheke_tapas_log($entry, $warning);
                    $this->toocheke_tapas_flag_episode($entry, $data['episode_id'], $warning);
                } else {
                    $this->toocheke_tapas_log($entry, sprintf(
                        /* translators: %s: episode title */
                        __('Imported “%s”.', 'toocheke-companion'),
                        $data['episode_title']
                    ));
                }
            }
        }

        if (-1 === (int) $data['next_id']) {
            return $this->toocheke_tapas_finish_series($entry);
        }

        return ['throttled' => false];
    }

    /**
     * Reached the end of the "next episode" chain (Tapas' own signal that
     * there is no more content). Whether that's actually true is worth
     * double-checking: it can also happen if some episode along the way
     * had a page structure the parser didn't recognize (mature-gated,
     * unlisted, a non-standard post type) and silently read as "no next"
     * when Tapas' own site would have kept going. Comparing what actually
     * landed on the site against the episode count Tapas itself reported
     * on the series page is a cheap, reliable way to catch that — a
     * clean finish and a broken one both look identical otherwise.
     */
    protected function toocheke_tapas_finish_series(array &$entry)
    {
        $entry['status'] = 'done';

        $actual_count = $entry['series_post_id']
            ? $this->toocheke_tapas_count_comics_for_series((int) $entry['series_post_id'])
            : $entry['episodes_done'];

        $this->toocheke_tapas_log($entry, sprintf(
            /* translators: 1: episodes imported this run, 2: total now on the site */
            __('Finished — %1$d episode(s) imported this run (%2$d total now on the site).', 'toocheke-companion'),
            $entry['episodes_done'],
            $actual_count
        ));

        $expected_count  = $actual_count + $entry['episodes_skipped'];
        $unexplained_gap = ! empty($entry['episodes_total']) ? (int) $entry['episodes_total'] - $expected_count : 0;

        if ($entry['episodes_skipped'] > 0) {
            // The clear, common case: we know exactly why some episodes
            // didn't become posts (locked/rental, mature-gated, or an
            // unrecognized page) — see toocheke_tapas_import_episode_data().
            // A matching post count no longer means a clean 1:1 import
            // once skipping-on-purpose is in the picture, so this always
            // gets a summary rather than reading as a plain success.
            $reason_parts = [];
            $reason_labels = [
                'locked'  => __('locked/paid (“Wait Until Free”)', 'toocheke-companion'),
                'mature'  => __('mature/NSFW-gated', 'toocheke-companion'),
                'unknown' => __('unclear reason — worth checking manually', 'toocheke-companion'),
            ];
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
                    __('Additionally, %d further episode(s) are unaccounted for beyond what\'s explained above — Tapas\' own episode count is higher than imported + skipped combined.', 'toocheke-companion'),
                    $unexplained_gap
                );
            }

            $entry['warning'] = $warning;
            $this->toocheke_tapas_log($entry, $warning);
            $this->toocheke_tapas_record_existing_flags($entry);
        } elseif (! empty($entry['episodes_total']) && $actual_count < (int) $entry['episodes_total']) {
            $warning = sprintf(
                /* translators: 1: episodes Tapas reported, 2: episodes actually on the site, 3: how many short, 4: last episode title, 5: last episode's Tapas id */
                __('Heads up: Tapas listed %1$d episodes for this series, but only %2$d ended up on your site (%3$d short). The importer stopped after “%4$s” (Tapas episode #%5$d) because that page\'s “next episode” link said there wasn\'t one. This can happen when some episodes are locked/mature-gated, unlisted, or removed — or it can mean that one page had a layout the importer didn\'t recognize. Worth checking episode #%5$d on Tapas yourself and clicking “Next” to see what\'s really there.', 'toocheke-companion'),
                (int) $entry['episodes_total'],
                $actual_count,
                (int) $entry['episodes_total'] - $actual_count,
                $entry['last_episode_title'],
                (int) $entry['last_episode_id']
            );
            $entry['warning'] = $warning;
            $this->toocheke_tapas_log($entry, $warning);
            $this->toocheke_tapas_record_incomplete_notice($entry, $actual_count);
        } elseif (! empty($entry['flagged_episodes'])) {
            // Every episode is accounted for (the count matches), but
            // one or more still came back with no images — see where
            // 'flagged_episodes' is populated in
            // toocheke_tapas_import_episode_data(). A matching post
            // count alone doesn't mean a clean import, so this still
            // needs to be surfaced rather than reading as a plain
            // success.
            $count = count($entry['flagged_episodes']);
            $warning = sprintf(
                /* translators: %d: number of episodes with no images */
                _n(
                    'Heads up: %d episode imported with no images — see the log below for which one and why.',
                    'Heads up: %d episodes imported with no images — see the log below for which ones and why.',
                    $count,
                    'toocheke-companion'
                ),
                $count
            );
            $entry['warning'] = $warning;
            $this->toocheke_tapas_log($entry, $warning);
            $this->toocheke_tapas_record_existing_flags($entry);
        }

        return ['throttled' => false];
    }

    protected function toocheke_tapas_count_comics_for_series($series_post_id)
    {
        $found = get_posts([
            'post_type'      => 'comic',
            'post_parent'    => $series_post_id,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        return count($found);
    }

    /**
     * Downloads every comic-panel image for one episode (in reading
     * order) into the Media Library and returns the <img> markup to use
     * as the Comic post's content — this is deliberately plain, unwrapped
     * <img> tags (matching how Tapas itself stacks panels) rather than a
     * gallery shortcode, so it renders correctly regardless of what other
     * block/shortcode support a given theme build has.
     */
    protected function toocheke_tapas_build_comic_content(array $image_urls, $post_id, $desc)
    {
        $html = '';
        foreach ($image_urls as $image) {
            $attachment_id = $this->toocheke_tapas_sideload_image($image['url'], $post_id, $desc);
            if (! $attachment_id) {
                continue;
            }
            // Every <img> here is meant to butt directly against the
            // next one, like Tapas' own stacked-panel layout — but
            // <img> is an inline element, so joining them with even a
            // newline in the HTML source (as this used to do) renders
            // as a visible gap between panels in most themes. Forcing
            // block display + zero margin here makes that immune to
            // both this file's own formatting AND whatever a theme's
            // default image CSS happens to do — not something to rely
            // on "no whitespace in the source" alone to prevent, since
            // a future edit in the block/classic editor could easily
            // reintroduce it.
            $html .= wp_get_attachment_image($attachment_id, 'full', false, [
                'class'   => 'toocheke-tapas-panel',
                'loading' => 'lazy',
                'style'   => 'display:block;margin:0;padding:0;border:0;',
            ]);
        }
        return $html;
    }

    /* =========================================================================
       LOOKUPS (idempotency — see class docblock)
    ========================================================================= */

    protected function toocheke_tapas_find_existing_series_post($tapas_series_id)
    {
        $found = get_posts([
            'post_type'      => 'series',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_toocheke_tapas_series_id',
            'meta_value'     => $tapas_series_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        return ! empty($found) ? (int) $found[0] : 0;
    }

    protected function toocheke_tapas_find_existing_comic_post($tapas_episode_id)
    {
        $found = get_posts([
            'post_type'      => 'comic',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_toocheke_tapas_episode_id',
            'meta_value'     => $tapas_episode_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        return ! empty($found) ? (int) $found[0] : 0;
    }

    /* =========================================================================
       HTML PARSING
       DOMDocument/XPath for anything structural (attributes, stable class
       hooks); one small, tightly-scoped regex for the inline JS state
       object Tapas embeds on every page (there is no attribute hook for
       "episode count" or "series id" otherwise).
    ========================================================================= */

    protected function toocheke_tapas_parse_episode_html($html)
    {
        $out = [
            'series_id'          => null,
            'series_title'       => '',
            'series_description' => '',
            'series_thumb_url'   => '',
            'episodes_total'     => null,
            'episode_id'         => null,
            'episode_title'      => '',
            'episode_date_str'   => '',
            'episode_thumb_url'  => '',
            'story_excerpt'      => '',
            'content_image_urls' => [],
            'next_id'            => -1,
            'is_locked'          => false,
            'is_mature'          => false,
        ];

        if ('' === trim((string) $html)) {
            return $out;
        }

        // seriesId: 163237, ... episode: { id: 1637963, ... }
        if (preg_match('/seriesId\s*:\s*(\d+)/', $html, $m)) {
            $out['series_id'] = (int) $m[1];
        }
        if (preg_match('/episode\s*:\s*\{[^}]*?\bid\s*:\s*(\d+)/s', $html, $m)) {
            $out['episode_id'] = (int) $m[1];
        }
        if (preg_match('/seriesTitle\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $html, $m)) {
            $out['series_title'] = $this->toocheke_tapas_unescape_js_string($m[1]);
        }
        // Two independent signals Tapas uses for paid/rental episodes —
        // either is enough to flag it; see the note on 'is_locked' where
        // it's used, for why this is a hint rather than the sole trigger.
        if (preg_match('/data-is-rental="true"/i', $html)
            || preg_match('/episode\s*:\s*\{[^}]*?\bfree\s*:\s*false/s', $html)) {
            $out['is_locked'] = true;
        }
        if (preg_match('/data-ep-is-mature="true"/i', $html)
            || preg_match('/episode\s*:\s*\{[^}]*?\bnsfw\s*:\s*true/s', $html)) {
            $out['is_mature'] = true;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // --- Episode wrapper: carries the "next episode" chain cursor. ---
        $episode_wrap = $xpath->query('//*[starts-with(@id,"episode-")][@data-next-id]');
        if ($episode_wrap->length) {
            $node = $episode_wrap->item(0);
            $next = $node->getAttribute('data-next-id');
            $out['next_id'] = ('' !== $next) ? (int) $next : -1;
            if (! $out['episode_id']) {
                $id_attr = $node->getAttribute('data-ep-id');
                if ($id_attr !== '') {
                    $out['episode_id'] = (int) $id_attr;
                }
            }
        }

        // --- Episode title & date. ---
        $title_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " viewer__header ")]//*[contains(concat(" ", normalize-space(@class), " "), " title ")]');
        if ($title_node->length) {
            $out['episode_title'] = trim($title_node->item(0)->textContent);
        }
        $date_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " viewer__header ")]//*[contains(concat(" ", normalize-space(@class), " "), " date ")]');
        if ($date_node->length) {
            $out['episode_date_str'] = trim($date_node->item(0)->textContent);
        }

        // --- Episode thumbnail (toolbar), distinct from the comic panels. ---
        $thumb_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " row-item--info ")]//img');
        if ($thumb_node->length) {
            $out['episode_thumb_url'] = $thumb_node->item(0)->getAttribute('src');
        }

        // --- Comic panel images, in reading order. ---
        $panel_nodes = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " viewer__body ")]//img[contains(concat(" ", normalize-space(@class), " "), " content__img ")]');
        foreach ($panel_nodes as $panel) {
            $src = $panel->getAttribute('data-src');
            if ('' === $src) {
                $src = $panel->getAttribute('src');
            }
            if ('' === $src || 0 === stripos($src, 'data:')) {
                continue;
            }
            $out['content_image_urls'][] = [
                'url'    => $src,
                'width'  => $panel->getAttribute('data-width'),
                'height' => $panel->getAttribute('data-height'),
            ];
        }

        // --- Episode "story" teaser (short creator note, when present). ---
        $story_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " js-episode-story ")]');
        if ($story_node->length) {
            $out['story_excerpt'] = trim($story_node->item(0)->textContent);
        }

        // --- Series-level info (only present when this fetch is the
        //     series/first-episode page; harmless no-op otherwise). ---
        $series_title_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " title-wrapper ")]//*[contains(concat(" ", normalize-space(@class), " "), " title ")]');
        if ($series_title_node->length) {
            $text = trim($series_title_node->item(0)->textContent);
            if ('' !== $text) {
                $out['series_title'] = $text;
            }
        }
        $series_desc_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " js-series-description ")]');
        if ($series_desc_node->length) {
            $out['series_description'] = trim($series_desc_node->item(0)->textContent);
        }
        $series_thumb_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " thumb-wrapper ")]//img');
        if ($series_thumb_node->length) {
            $out['series_thumb_url'] = $series_thumb_node->item(0)->getAttribute('src');
        }
        $ep_count_node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " js-ep-cnt ")]');
        if ($ep_count_node->length && preg_match('/(\d+)/', $ep_count_node->item(0)->textContent, $m)) {
            $out['episodes_total'] = (int) $m[1];
        }

        if (empty($out['series_title'])) {
            $out['series_title'] = __('Untitled Series', 'toocheke-companion');
        }
        if (empty($out['episode_title'])) {
            $out['episode_title'] = __('Untitled Episode', 'toocheke-companion');
        }

        return $out;
    }

    /**
     * Very small helper for the one inline-JS string value we read
     * (seriesTitle) — undoes basic JS string escaping without pulling in
     * a JSON parser for a single field.
     */
    protected function toocheke_tapas_unescape_js_string($raw)
    {
        return html_entity_decode(stripslashes($raw), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Tapas shows dates like "Dec 02, 2020" with no time-of-day. Anchoring
     * to noon (rather than midnight) avoids a GMT conversion nudging the
     * stored date to the previous calendar day for sites west of UTC.
     */
    protected function toocheke_tapas_parse_episode_date($date_str)
    {
        $date_str = trim((string) $date_str);
        if ('' === $date_str) {
            return false;
        }
        $timestamp = strtotime($date_str . ' 12:00:00');
        if (false === $timestamp) {
            return false;
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /* =========================================================================
       HTTP
    ========================================================================= */

    protected function toocheke_tapas_is_allowed_host($url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }
        $host = strtolower($host);
        return $host === TOOCHEKE_TAPAS_ALLOWED_HOST_SUFFIX
            || '.' . TOOCHEKE_TAPAS_ALLOWED_HOST_SUFFIX === substr($host, -1 - strlen(TOOCHEKE_TAPAS_ALLOWED_HOST_SUFFIX));
    }

    /**
     * Fetches a page URL. Never throws/dies — always returns an array
     * with either 'body' set, or 'throttled' => true with a suggested
     * 'retry_after' (seconds), or 'error' with a human message.
     */
    /**
     * Fetches a page URL. Never throws/dies — always returns an array
     * with either 'body' set, or 'throttled' => true with a suggested
     * 'retry_after' (seconds), or 'error' with a human-readable message
     * that still includes the underlying technical reason (HTTP status /
     * connection error) so a failure can actually be diagnosed rather
     * than just reported as "didn't work".
     *
     * A single hard failure (a dropped connection, a one-off 403/500) is
     * common enough across a run of hundreds/thousands of requests that
     * treating it as instantly fatal to the whole series would be overly
     * brittle, so this retries a couple of times with a short pause
     * first — separately from, and in addition to, the JS-side
     * throttle backoff for genuine 429/503 rate-limiting.
     */
    protected function toocheke_tapas_http_get($url, $attempt = 1)
    {
        if (! $this->toocheke_tapas_is_allowed_host($url)) {
            return ['body' => '', 'throttled' => false, 'error' => __('Refused to fetch a non-Tapas URL.', 'toocheke-companion')];
        }

        $response = wp_remote_get($url, [
            'timeout'    => 20,
            'redirection'=> 3,
            'user-agent' => 'ToochekeCompanion-TapasImporter/' . TOOCHEKE_COMPANION_VERSION . ' (+https://leetoo.net)',
            'headers'    => ['Accept' => 'text/html'],
        ]);

        if (is_wp_error($response)) {
            if ($attempt < 3) {
                sleep(2 * $attempt);
                return $this->toocheke_tapas_http_get($url, $attempt + 1);
            }
            /* translators: %s: underlying connection error */
            return ['body' => '', 'throttled' => false, 'error' => sprintf(__('Connection error: %s', 'toocheke-companion'), $response->get_error_message())];
        }

        $code = wp_remote_retrieve_response_code($response);

        if (429 === $code || 503 === $code || 403 === $code) {
            // 429/503 are Tapas explicitly saying "slow down". A 403 is
            // less clear-cut — it can mean genuinely-forbidden content,
            // but in practice a 403 that hits one specific page while its
            // neighbours fetch fine (rather than every request failing)
            // has consistently turned out to be a transient anti-bot
            // block rather than the content itself being restricted.
            // Treating it the same as a rate limit — back off and retry
            // the SAME page later rather than giving up after a few quick
            // attempts — is what actually clears it; see
            // toocheke_tapas_apply_backoff_strike(), which is what
            // escalates the wait on repeated strikes and is where this
            // eventually surfaces to the creator if it truly never clears.
            $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
            if ($retry_after <= 0) {
                $retry_after = $this->toocheke_tapas_backoff_seconds();
            }
            return ['body' => '', 'throttled' => true, 'retry_after' => min(900, max(15, $retry_after))];
        }

        if ($code < 200 || $code >= 300) {
            if ($attempt < 3) {
                sleep(2 * $attempt);
                return $this->toocheke_tapas_http_get($url, $attempt + 1);
            }
            /* translators: 1: HTTP status code, 2: number of attempts made */
            return ['body' => '', 'throttled' => false, 'error' => sprintf(__('Tapas returned HTTP %1$d for this page (after %2$d attempts).', 'toocheke-companion'), $code, $attempt)];
        }

        $body = wp_remote_retrieve_body($response);

        if ('' === trim($body) && $attempt < 3) {
            // An empty 200 response is itself unusual enough to be worth
            // one retry rather than treating it as "successfully nothing".
            sleep(2 * $attempt);
            return $this->toocheke_tapas_http_get($url, $attempt + 1);
        }

        return ['body' => $body, 'throttled' => false, 'error' => '' === trim($body) ? __('Tapas returned an empty page.', 'toocheke-companion') : ''];
    }

    /**
     * Exponential-ish backoff used only when Tapas doesn't tell us how
     * long to wait via a Retry-After header. Kept intentionally simple —
     * a fixed, generous pause — since the JS side already caps automatic
     * retries and hands control back to the creator rather than hammering
     * a rate limit indefinitely.
     */
    protected function toocheke_tapas_backoff_seconds()
    {
        return 45;
    }

    /* =========================================================================
       IMAGES
    ========================================================================= */

    /**
     * Tapas' CDN serves resized variants as `{hash}_{sizecode}.{ext}`
     * alongside the original at `{hash}.{ext}`. When the requested URL
     * matches that pattern, tries the (larger) unsuffixed original first
     * and falls back to the given URL if that 404s — see class docblock
     * for why this is only done for thumbnails, not the (often numerous)
     * comic-panel images.
     */
    protected function toocheke_tapas_sideload_largest($url, $post_id, $desc)
    {
        $stripped = $this->toocheke_tapas_strip_size_suffix($url);

        if ($stripped && $stripped !== $url) {
            $attachment_id = $this->toocheke_tapas_sideload_image($stripped, $post_id, $desc);
            if ($attachment_id) {
                return $attachment_id;
            }
        }

        return $this->toocheke_tapas_sideload_image($url, $post_id, $desc);
    }

    protected function toocheke_tapas_strip_size_suffix($url)
    {
        if (preg_match('~^(https://[^\s]+/[a-z0-9]{2}/[a-z0-9-]{20,40})_[a-z]{1,3}(\.[a-z]{3,4})(?:\?.*)?$~i', $url, $m)) {
            return $m[1] . $m[2];
        }
        return $url;
    }

    /**
     * Thin, defensive wrapper around media_sideload_image(): confines
     * requests to the allowed host, loads the admin includes it needs
     * (not guaranteed to already be loaded in an admin-ajax context),
     * and always returns an attachment ID or 0 rather than a WP_Error/
     * HTML string (media_sideload_image()'s return type varies by WP
     * version depending on the 4th argument, so this pins it down once).
     */
    protected function toocheke_tapas_sideload_image($url, $post_id, $desc)
    {
        if (empty($url) || ! $this->toocheke_tapas_is_allowed_host($url)) {
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

        $result = media_sideload_image($url, $post_id, $desc, 'id');

        if (is_wp_error($result)) {
            error_log(sprintf('[Toocheke Tapas Import] Failed to sideload image %s: %s', $url, $result->get_error_message()));
            return 0;
        }

        return (int) $result;
    }

    /* =========================================================================
       INPUT VALIDATION
    ========================================================================= */

    /**
     * Accepts only https://tapas.io/series/{name}[/...] and returns the
     * sanitized series slug, or false. Deliberately strict — this feeds
     * directly into an outbound HTTP request.
     */
    protected function toocheke_tapas_validate_series_url($url)
    {
        $url = esc_url_raw($url);
        if ('' === $url) {
            return false;
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        $path = wp_parse_url($url, PHP_URL_PATH);

        if (! $host || strtolower($host) !== TOOCHEKE_TAPAS_ALLOWED_HOST_SUFFIX) {
            return false;
        }

        if (! $path || ! preg_match('~^/series/([A-Za-z0-9_-]+)/?$~', $path, $m)) {
            return false;
        }

        return $m[1];
    }
}
