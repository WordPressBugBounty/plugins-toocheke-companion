/**
 * Toocheke Companion — Tapas.io import admin UI.
 *
 * The server side (class-toocheke-companion-import-tapas.php) does exactly
 * ONE unit of work per AJAX call and returns. This script is the loop: it
 * keeps calling "step" until the job reports finished/throttled, updating
 * the progress UI from each response. That's what makes bulk imports of
 * arbitrarily large series possible without ever hitting a PHP execution
 * time limit, and what makes "close the tab, come back later, resume"
 * work — the job state lives entirely server-side in one option; this
 * script is just a dumb, restartable loop over it.
 */
jQuery(document).ready(function ($) {
    var cfg = window.toochekeTapasImport || {};
    var $rows      = $('#toocheke-tapas-url-rows');
    var $addRow    = $('#toocheke-tapas-add-row');
    var $start     = $('#toocheke-tapas-start');
    var $agree     = $('#toocheke-tapas-agree');
    var $resume    = $('#toocheke-tapas-resume');
    var $discard   = $('#toocheke-tapas-discard');
    var $progress  = $('#toocheke-tapas-progress');
    var $overallLabel = $('.toocheke-tapas-overall-label');
    var $overallFill  = $('.toocheke-tapas-progressbar--overall .toocheke-tapas-progressbar-fill');
    var $seriesTitle  = $('.toocheke-tapas-series-title');
    var $seriesFill   = $('.toocheke-tapas-progressbar--series .toocheke-tapas-progressbar-fill');
    var $seriesStatus = $('.toocheke-tapas-series-status');
    var $log          = $('.toocheke-tapas-log');
    var $copyRow       = $('.toocheke-tapas-copy-row');
    var $copyBtn       = $('.toocheke-tapas-copy-report');
    var $copyConfirm   = $('.toocheke-tapas-copy-confirm');
    var $retryBtn      = $('.toocheke-tapas-retry-series');
    var $resumeRow     = $('.toocheke-tapas-resume-row');
    var $resumeInput   = $('#toocheke-tapas-resume-episode');
    var $resumeBtn     = $('.toocheke-tapas-resume-from');

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
        // Handles both WP-style numbered placeholders (%1$s, %2$d — used
        // whenever a string has more than one placeholder, per WP i18n
        // conventions) and plain %s/%d for single-placeholder strings.
        return template.replace(/%(\d+)\$[sd]|%[sd]/g, function (match, num) {
            return num ? args[parseInt(num, 10) - 1] : args[i++];
        });
    }

    function appendLog(line) {
        var $line = $('<div class="toocheke-tapas-log-line"></div>').text(line);
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

        $seriesTitle.text(active.slug);

        // "Processed" (imported + deliberately skipped) is what actually
        // reflects progress through the episode list — using
        // episodes_done alone would make the bar look stuck partway
        // through any series with locked/paid content, even once every
        // episode has genuinely been walked.
        var processed = active.episodes_done + (active.episodes_skipped || 0);

        var pct = 0;
        if (active.episodes_total) {
            pct = Math.min(100, Math.round((processed / active.episodes_total) * 100));
        } else if (processed > 0) {
            pct = 100; // unknown total but we've made progress — avoid a stuck empty bar
        }
        $seriesFill.css('width', pct + '%');

        var statusText = '';
        if (active.status === 'pending' || active.status === 'resolving') {
            statusText = cfg.i18n.resolving;
        } else if (active.status === 'importing') {
            statusText = fmt(cfg.i18n.importing, active.last_episode_title || active.slug) +
                ' (' + processed + (active.episodes_total ? ' / ' + active.episodes_total : '') + ')';
        } else if (active.status === 'done') {
            statusText = fmt(cfg.i18n.seriesDone, active.slug, active.episodes_done);
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
        // Retry only makes sense if it could plausibly change the
        // outcome — see the matching comment server-side in
        // toocheke_tapas_ajax_retry_series().
        var canRetry = active.status === 'failed' || (!!active.warning && !active.episodes_skipped);
        if (needsAttention) {
            $copyRow.show();
            $copyBtn.data('report', buildDiagnosticReport(active));
            $retryBtn.toggle(canRetry).data('slug', active.slug);
            if (canRetry && active.series_post_id) {
                $resumeRow.show();
                $resumeBtn.data('slug', active.slug);
            } else {
                $resumeRow.hide();
            }
        } else {
            $copyRow.hide();
            $resumeRow.hide();
        }

        $log.empty();
        $.each(active.log || [], function (i, line) {
            appendLog(line);
        });
    }

    function buildDiagnosticReport(seriesEntry) {
        var lines = [
            'Toocheke Companion — Tapas Import Report',
            'Plugin version: ' + (cfg.diag ? cfg.diag.pluginVersion : 'unknown'),
            'WordPress version: ' + (cfg.diag ? cfg.diag.wpVersion : 'unknown'),
            'PHP version: ' + (cfg.diag ? cfg.diag.phpVersion : 'unknown'),
            'Series: ' + seriesEntry.slug,
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
        $rows.find('.toocheke-tapas-url-input').each(function () {
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

        ajax('toocheke_tapas_import_step', {}).done(function (res) {
            if (!res || !res.success) {
                appendLog(fmt(cfg.i18n.genericError, (res && res.data && res.data.message) || 'unknown error'));
                running = false;
                setButtonsRunning(false);
                return;
            }

            var data = res.data;
            renderJob(data.job);

            if (data.throttled) {
                consecutiveThrottles++;
                var wait = data.retry_after || 45;

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

    $resumeBtn.on('click', function () {
        var slug = $(this).data('slug');
        var raw  = $.trim($resumeInput.val());
        if (!slug || !raw) {
            appendLog('Enter an episode URL or ID first.');
            return;
        }
        ajax('toocheke_tapas_import_resume_from', { slug: slug, episode_id: raw }).done(function (res) {
            if (!res || !res.success) {
                appendLog(fmt(cfg.i18n.genericError, (res && res.data && res.data.message) || 'could not resume'));
                return;
            }
            consecutiveThrottles = 0;
            renderJob(res.data.job);
            $copyRow.hide();
            $resumeRow.hide();
            $resumeInput.val('');
            startLoop();
        });
    });

    $retryBtn.on('click', function () {
        var slug = $(this).data('slug');
        if (!slug) {
            return;
        }
        ajax('toocheke_tapas_import_retry', { slug: slug }).done(function (res) {
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
            '<div class="toocheke-tapas-url-row">' +
                '<input type="url" class="regular-text toocheke-tapas-url-input" placeholder="https://tapas.io/series/your-series-name" />' +
                '<button type="button" class="button toocheke-tapas-remove-row" aria-label="Remove">&times;</button>' +
            '</div>'
        );
        $rows.append($row);
    });

    $rows.on('click', '.toocheke-tapas-remove-row', function () {
        if ($rows.find('.toocheke-tapas-url-row').length > 1) {
            $(this).closest('.toocheke-tapas-url-row').remove();
        } else {
            $(this).closest('.toocheke-tapas-url-row').find('.toocheke-tapas-url-input').val('');
        }
    });

    $start.on('click', function () {
        var urls = collectUrls();
        if (!urls.length) {
            appendLog('Please enter at least one Tapas series URL.');
            return;
        }
        if (!window.confirm(cfg.i18n.confirmStart)) {
            return;
        }

        ajax('toocheke_tapas_import_start', { urls: urls }).done(function (res) {
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
        ajax('toocheke_tapas_import_discard', {}).done(function () {
            $progress.hide();
            $log.empty();
            $resume.hide();
            $discard.hide();
            $('#toocheke-tapas-existing-job').hide();
        });
    });

    // On load: if a job is already in progress (survived a page reload /
    // reopened tab), show its current state immediately without starting
    // the loop — the creator explicitly clicks Resume to continue.
    ajax('toocheke_tapas_import_status', {}).done(function (res) {
        if (res && res.success && res.data.job && res.data.job.series && res.data.job.series.length) {
            renderJob(res.data.job);
            if ('completed' !== res.data.job.status) {
                $resume.show();
                $discard.show();
            }
        }
    });
});
