/**
 * Toocheke Companion — Webtoons import admin UI.
 *
 * The PHP side does one unit of work per AJAX call; this script is the
 * loop, polling "step" until the job finishes or throttles.
 */
jQuery(document).ready(function ($) {
    var cfg = window.toochekeWebtoonsImport || {};
    var $rows      = $('#toocheke-webtoons-url-rows');
    var $addRow    = $('#toocheke-webtoons-add-row');
    var $start     = $('#toocheke-webtoons-start');
    var $agree     = $('#toocheke-webtoons-agree');
    var $resume    = $('#toocheke-webtoons-resume');
    var $discard   = $('#toocheke-webtoons-discard');
    var $progress  = $('#toocheke-webtoons-progress');
    var $overallLabel = $('.toocheke-webtoons-overall-label');
    var $overallFill  = $('.toocheke-webtoons-progressbar--overall .toocheke-webtoons-progressbar-fill');
    var $seriesTitle  = $('.toocheke-webtoons-series-title');
    var $seriesFill   = $('.toocheke-webtoons-progressbar--series .toocheke-webtoons-progressbar-fill');
    var $seriesStatus = $('.toocheke-webtoons-series-status');
    var $log          = $('.toocheke-webtoons-log');
    var $copyRow       = $('.toocheke-webtoons-copy-row');
    var $copyBtn       = $('.toocheke-webtoons-copy-report');
    var $copyConfirm   = $('.toocheke-webtoons-copy-confirm');
    var $retryBtn      = $('.toocheke-webtoons-retry-series');

    var running        = false;
    var consecutiveThrottles = 0;
    var MAX_AUTO_RETRIES = 5;
    var STEP_DELAY_MS   = 400; // gentle pacing between requests, even on success

    function ajax(action, data) {
        return $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            data: $.extend({ action: action, nonce: cfg.nonce }, data || {}),
        });
    }

    function fmt(template) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        // Handles both numbered (%1$s) and plain (%s) placeholders.
        return template.replace(/%(\d+)\$[sd]|%[sd]/g, function (match, num) {
            return num ? args[parseInt(num, 10) - 1] : args[i++];
        });
    }

    function appendLog(line) {
        var $line = $('<div class="toocheke-webtoons-log-line"></div>').text(line);
        $log.append($line);
        $log.scrollTop($log[0].scrollHeight);
    }

    function renderJob(job) {
        if (!job || !job.series || !job.series.length) {
            return;
        }

        $progress.show();

        var totalSeries = job.series.length;
        var doneSeries  = 0;
        $.each(job.series, function (i, s) {
            if (s.status === 'done' || s.status === 'failed') {
                doneSeries++;
            }
        });

        $overallLabel.text('Series ' + Math.min(doneSeries + 1, totalSeries) + ' of ' + totalSeries);
        $overallFill.css('width', Math.round((doneSeries / totalSeries) * 100) + '%');

        var active = job.series[Math.min(job.current_index, job.series.length - 1)];
        if (!active) {
            return;
        }

        $seriesTitle.text(active.title || active.slug);

        // Position in the episode list, so skipped and already-imported
        // episodes still move the bar.
        var processed = active.cursor || 0;

        var pct = 0;
        if (active.status === 'done') {
            pct = 100;
        } else if (active.episodes_total) {
            pct = Math.min(100, Math.round((processed / active.episodes_total) * 100));
        }
        $seriesFill.css('width', pct + '%');

        var statusText = '';
        if (active.status === 'pending' || active.status === 'resolving') {
            statusText = cfg.i18n.resolving;
        } else if (active.status === 'indexing') {
            statusText = fmt(cfg.i18n.indexing, active.index_found || 0);
        } else if (active.status === 'importing') {
            statusText = fmt(cfg.i18n.importing, active.last_episode_title || active.title || active.slug) +
                ' (' + processed + (active.episodes_total ? ' / ' + active.episodes_total : '') + ')';
        } else if (active.status === 'done') {
            statusText = fmt(cfg.i18n.seriesDone, active.title || active.slug, active.episodes_done);
            if (active.episodes_skipped) {
                statusText += ' (' + active.episodes_skipped + ' skipped)';
            }
            if (active.warning) {
                statusText += ' ' + active.warning;
            }
        } else if (active.status === 'failed') {
            statusText = active.error || 'Failed.';
        }
        if (active.flagged_episodes && active.flagged_episodes.length > 1) {
            statusText += ' (' + active.flagged_episodes.length + ' episodes flagged this run — see Copy Diagnostic Report for the full list.)';
        }
        $seriesStatus.text(statusText);

        var needsAttention = active.status === 'failed' || !!active.warning;
        // Retry only if it could actually change the outcome — same
        // check server-side in toocheke_webtoons_ajax_retry_series().
        var canRetry = active.status === 'failed' || (!!active.warning && !active.episodes_skipped);
        if (needsAttention) {
            $copyRow.show();
            $copyBtn.data('report', buildDiagnosticReport(active));
            $retryBtn.toggle(canRetry).data('titleNo', active.title_no);
        } else {
            $copyRow.hide();
        }

        $log.empty();
        $.each(active.log || [], function (i, line) {
            appendLog(line);
        });
    }

    function buildDiagnosticReport(seriesEntry) {
        var lines = [
            'Toocheke Companion — Webtoons Import Report',
            'Plugin version: ' + (cfg.diag ? cfg.diag.pluginVersion : 'unknown'),
            'WordPress version: ' + (cfg.diag ? cfg.diag.wpVersion : 'unknown'),
            'PHP version: ' + (cfg.diag ? cfg.diag.phpVersion : 'unknown'),
            'Series: ' + seriesEntry.slug + ' (title_no ' + seriesEntry.title_no + ')',
            'Episodes imported this run: ' + seriesEntry.episodes_done,
            'Episodes skipped this run: ' + (seriesEntry.episodes_skipped || 0)
        ];
        if (seriesEntry.skip_reasons) {
            $.each(seriesEntry.skip_reasons, function (reason, count) {
                lines.push('  - ' + reason + ': ' + count);
            });
        }
        lines.push('Error: ' + (seriesEntry.error || '(none recorded)'));
        lines.push('Warning: ' + (seriesEntry.warning || '(none recorded)'));
        lines.push('Recent log:');
        $.each(seriesEntry.log || [], function (i, line) {
            lines.push('  - ' + line);
        });
        if (seriesEntry.flagged_episodes && seriesEntry.flagged_episodes.length > 1) {
            lines.push('All episodes flagged this run:');
            $.each(seriesEntry.flagged_episodes, function (i, f) {
                lines.push('  - #' + f.episode_id + ': ' + f.message);
            });
        }
        return lines.join('\n');
    }

    function collectUrls() {
        var urls = [];
        $rows.find('.toocheke-webtoons-url-input').each(function () {
            var v = $.trim($(this).val());
            if (v) {
                urls.push(v);
            }
        });
        return urls;
    }

    function setButtonsRunning(isRunning) {
        $start.prop('disabled', isRunning || !$agree.is(':checked'));
        $resume.prop('disabled', isRunning);
        $discard.prop('disabled', isRunning);
    }

    $agree.on('change', function () {
        $start.prop('disabled', !$agree.is(':checked'));
    });

    function loop() {
        if (!running) {
            return;
        }

        ajax('toocheke_webtoons_import_step', {}).done(function (res) {
            if (!res || !res.success) {
                appendLog(fmt(cfg.i18n.genericError, (res && res.data && res.data.message) || 'unknown error'));
                running = false;
                setButtonsRunning(false);
                return;
            }

            var data = res.data;
            renderJob(data.job);

            if (data.throttled) {
                var wait = data.retry_after || 45;

                // A cleanup-in-progress pause is never a reason to give
                // up automatically — it always finishes on its own, so
                // this doesn't count toward the Webtoons-throttle retry cap
                // and shows the real reason instead of the generic
                // rate-limit message.
                if (data.pause_reason) {
                    appendLog(data.pause_reason);
                    setTimeout(loop, wait * 1000);
                    return;
                }

                consecutiveThrottles++;

                if (consecutiveThrottles > MAX_AUTO_RETRIES) {
                    appendLog(cfg.i18n.throttledManual);
                    running = false;
                    setButtonsRunning(false);
                    $resume.show();
                    return;
                }

                appendLog(fmt(cfg.i18n.throttled, wait));
                setTimeout(loop, wait * 1000);
                return;
            }

            consecutiveThrottles = 0;

            if (data.finished) {
                appendLog(cfg.i18n.allDone);
                running = false;
                setButtonsRunning(false);
                $resume.hide();
                $discard.show();
                return;
            }

            setTimeout(loop, STEP_DELAY_MS);
        }).fail(function (xhr) {
            appendLog(fmt(cfg.i18n.genericError, xhr.statusText || 'request failed'));
            // Network hiccups shouldn't kill a multi-hour import — back off
            // and keep trying rather than stopping outright.
            setTimeout(loop, 15000);
        });
    }

    function startLoop() {
        if (running) {
            return;
        }
        running = true;
        setButtonsRunning(true);
        $progress.show();
        loop();
    }

    $retryBtn.on('click', function () {
        var titleNo = $(this).data('titleNo');
        if (!titleNo) {
            return;
        }
        ajax('toocheke_webtoons_import_retry', { title_no: titleNo }).done(function (res) {
            if (!res || !res.success) {
                appendLog(fmt(cfg.i18n.genericError, (res && res.data && res.data.message) || 'could not retry'));
                return;
            }
            consecutiveThrottles = 0;
            renderJob(res.data.job);
            $copyRow.hide();
            startLoop();
        });
    });

    $copyBtn.on('click', function () {
        var text = $(this).data('report') || '';
        var $temp = $('<textarea readonly></textarea>').val(text).css({ position: 'fixed', top: '-9999px' }).appendTo('body');
        $temp[0].select();
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            } else {
                document.execCommand('copy');
            }
        } catch (e) { /* clipboard unavailable — textarea remains selected as a fallback */ }
        $temp.remove();
        $copyConfirm.show();
        setTimeout(function () { $copyConfirm.hide(); }, 2000);
    });

    $addRow.on('click', function () {
        var $row = $(
            '<div class="toocheke-webtoons-url-row">' +
                '<input type="url" class="regular-text toocheke-webtoons-url-input" placeholder="https://www.webtoons.com/en/canvas/your-series/list?title_no=12345" />' +
                '<button type="button" class="button toocheke-webtoons-remove-row" aria-label="Remove">&times;</button>' +
            '</div>'
        );
        $rows.append($row);
    });

    $rows.on('click', '.toocheke-webtoons-remove-row', function () {
        if ($rows.find('.toocheke-webtoons-url-row').length > 1) {
            $(this).closest('.toocheke-webtoons-url-row').remove();
        } else {
            $(this).closest('.toocheke-webtoons-url-row').find('.toocheke-webtoons-url-input').val('');
        }
    });

    $start.on('click', function () {
        var urls = collectUrls();
        if (!urls.length) {
            appendLog('Please enter at least one Webtoons series URL.');
            return;
        }
        if (!window.confirm(cfg.i18n.confirmStart)) {
            return;
        }

        ajax('toocheke_webtoons_import_start', { urls: urls }).done(function (res) {
            if (!res || !res.success) {
                appendLog(fmt(cfg.i18n.genericError, (res && res.data && res.data.message) || 'could not start'));
                return;
            }
            if (res.data.warnings && res.data.warnings.length) {
                $.each(res.data.warnings, function (i, w) { appendLog(w); });
            }
            renderJob(res.data.job);
            $resume.hide();
            $discard.show();
            startLoop();
        });
    });

    $resume.on('click', function () {
        consecutiveThrottles = 0;
        startLoop();
    });

    $discard.on('click', function () {
        if (!window.confirm(cfg.i18n.confirmDiscard)) {
            return;
        }
        ajax('toocheke_webtoons_import_discard', {}).done(function () {
            $progress.hide();
            $log.empty();
            $resume.hide();
            $discard.hide();
            $('#toocheke-webtoons-existing-job').hide();
        });
    });

    // "Start a series over" — permanent delete + re-import.
    var $cleanupSelect  = $('#toocheke-webtoons-cleanup-select');
    var $cleanupConfirm = $('#toocheke-webtoons-cleanup-confirm-text');
    var $cleanupStart   = $('#toocheke-webtoons-cleanup-start');
    var $cleanupProgress = $('#toocheke-webtoons-cleanup-progress');
    var $cleanupFill    = $('.toocheke-webtoons-progressbar--cleanup .toocheke-webtoons-progressbar-fill');
    var $cleanupStatus  = $('.toocheke-webtoons-cleanup-status');
    var cleanupRunning  = false;

    function updateCleanupButtonState() {
        var selectedTitle = $cleanupSelect.find(':selected').data('title') || '';
        var matches = selectedTitle !== '' && $cleanupConfirm.val() === selectedTitle;
        $cleanupStart.prop('disabled', !matches || cleanupRunning);
    }
    $cleanupSelect.on('change', updateCleanupButtonState);
    $cleanupConfirm.on('input', updateCleanupButtonState);

    function cleanupStep() {
        ajax('toocheke_webtoons_cleanup_step', {}).done(function (res) {
            if (!res || !res.success) {
                $cleanupStatus.text((res && res.data && res.data.message) || 'Something went wrong.');
                cleanupRunning = false;
                return;
            }
            var job = res.data.job;
            var pct = job.comics_total ? Math.min(100, Math.round((job.comics_done / job.comics_total) * 100)) : (res.data.done ? 100 : 0);
            $cleanupFill.css('width', pct + '%');
            $cleanupStatus.text('Deleted ' + job.comics_done + ' / ' + job.comics_total + ' comics (' + job.attachments_deleted + ' files removed so far)…');

            if (res.data.done) {
                $cleanupStatus.text('Done — “' + job.series_title + '” and everything under it has been permanently removed. You can now re-run the import for it from scratch.');
                cleanupRunning = false;
                $cleanupSelect.html('<option value="">' + $cleanupSelect.find('option:first').text() + '</option>');
                $cleanupConfirm.val('');
                updateCleanupButtonState();
                return;
            }
            setTimeout(cleanupStep, STEP_DELAY_MS);
        }).fail(function () {
            $cleanupStatus.text('Lost connection — will keep retrying…');
            setTimeout(cleanupStep, 15000);
        });
    }

    $cleanupStart.on('click', function () {
        var seriesPostId = $cleanupSelect.val();
        var selectedTitle = $cleanupSelect.find(':selected').data('title') || '';
        if (!seriesPostId || $cleanupConfirm.val() !== selectedTitle) {
            return;
        }
        if (!window.confirm('This permanently deletes "' + selectedTitle + '", every comic under it, and every attached image file. This cannot be undone. Continue?')) {
            return;
        }

        cleanupRunning = true;
        $cleanupStart.prop('disabled', true);
        $cleanupSelect.prop('disabled', true);
        $cleanupConfirm.prop('disabled', true);
        $cleanupProgress.show();
        $cleanupStatus.text('Starting…');

        ajax('toocheke_webtoons_cleanup_start', { series_post_id: seriesPostId }).done(function (res) {
            if (!res || !res.success) {
                $cleanupStatus.text((res && res.data && res.data.message) || 'Could not start.');
                cleanupRunning = false;
                $cleanupSelect.prop('disabled', false);
                $cleanupConfirm.prop('disabled', false);
                updateCleanupButtonState();
                return;
            }
            cleanupStep();
        });
    });

    // "Episodes needing attention" — re-download missing panels.
    var $repairStart    = $('#toocheke-webtoons-repair-start');
    var $repairProgress = $('#toocheke-webtoons-repair-progress');
    var $repairFill     = $('.toocheke-webtoons-progressbar--repair .toocheke-webtoons-progressbar-fill');
    var $repairStatus   = $('.toocheke-webtoons-repair-status');

    function repairStep() {
        ajax('toocheke_webtoons_repair_step', {}).done(function (res) {
            if (!res || !res.success) {
                $repairStatus.text((res && res.data && res.data.message) || 'Something went wrong.');
                $repairStart.prop('disabled', false);
                return;
            }
            var job = res.data.job;
            var pct = job.total ? Math.min(100, Math.round(((job.total - job.queue.length) / job.total) * 100)) : 100;
            $repairFill.css('width', (res.data.done ? 100 : pct) + '%');

            if (res.data.done) {
                var msg = fmt(cfg.i18n.repairDone, job.fixed);
                if (job.still_missing && job.still_missing.length) {
                    msg += ' ' + fmt(cfg.i18n.repairStill, $.map(job.still_missing, function (m) { return m.title; }).join(', '));
                }
                $repairStatus.text(msg);
                setTimeout(function () { window.location.reload(); }, 4000);
                return;
            }

            $repairStatus.text(fmt(cfg.i18n.repairing, job.total - job.queue.length, job.total));
            setTimeout(repairStep, res.data.throttled ? (res.data.retry_after || 10) * 1000 : STEP_DELAY_MS);
        }).fail(function () {
            $repairStatus.text('Lost connection — will keep retrying…');
            setTimeout(repairStep, 15000);
        });
    }

    $repairStart.on('click', function () {
        $repairStart.prop('disabled', true);
        $repairProgress.show();
        $repairStatus.text('Starting…');
        ajax('toocheke_webtoons_repair_start', {}).done(function (res) {
            if (!res || !res.success) {
                $repairStatus.text((res && res.data && res.data.message) || 'Could not start.');
                $repairStart.prop('disabled', false);
                return;
            }
            repairStep();
        });
    });

    if ($repairProgress.length) {
        ajax('toocheke_webtoons_repair_status', {}).done(function (res) {
            if (res && res.success && res.data.job) {
                $repairStart.prop('disabled', true);
                $repairProgress.show();
                $repairStatus.text('Resuming an interrupted repair…');
                repairStep();
            }
        });
    }

    // Show any in-progress job on load, but don't auto-resume it.
    ajax('toocheke_webtoons_import_status', {}).done(function (res) {
        if (res && res.success && res.data.job && res.data.job.series && res.data.job.series.length) {
            renderJob(res.data.job);
            if ('completed' !== res.data.job.status) {
                $resume.show();
                $discard.show();
            }
        }
    });

    // Unlike the import itself, a cleanup that was interrupted (tab
    // closed, page reloaded) is always safe to just continue — deleting
    // is idempotent, so this resumes it automatically rather than
    // making the person notice and click something again.
    if ($cleanupProgress.length) {
        ajax('toocheke_webtoons_cleanup_status', {}).done(function (res) {
            if (res && res.success && res.data.job) {
                cleanupRunning = true;
                $cleanupSelect.prop('disabled', true);
                $cleanupConfirm.prop('disabled', true);
                $cleanupProgress.show();
                $cleanupStatus.text('Resuming an interrupted cleanup…');
                cleanupStep();
            }
        });
    }
});
