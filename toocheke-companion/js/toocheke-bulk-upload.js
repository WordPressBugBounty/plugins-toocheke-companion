(function ($) {
    'use strict';

    var cfg = window.toochekeBulkUpload;
    if (!cfg) {
        return;
    }

    var i18n = cfg.i18n;
    var collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    var items = [];
    var queue = [];
    var active = 0;
    var keySeq = 0;
    var previewTimer = null;
    var previewSeq = 0;
    var previewOk = false;
    var busy = false;
    var dateTouched = false;
    var daysTouched = false;

    var $drop = $('#toocheke-bulk-drop');
    var $file = $('#toocheke-bulk-file');
    var $grid = $('#toocheke-bulk-grid');
    var $toolbar = $('#toocheke-bulk-toolbar');
    var $errors = $('#toocheke-bulk-upload-errors');
    var $preview = $('#toocheke-bulk-preview');
    var $create = $('#toocheke-bulk-create');
    var $progress = $('#toocheke-bulk-progress');
    var $fill = $('#toocheke-bulk-fill');
    var $status = $('#toocheke-bulk-status');
    var $log = $('#toocheke-bulk-log');
    var $batches = $('#toocheke-bulk-batches');

    function sprintf(str) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        return str.replace(/%(?:(\d+)\$)?[sd]/g, function (m, pos) {
            return args[pos ? parseInt(pos, 10) - 1 : i++];
        });
    }

    function request(action, data, extra) {
        return $.ajax($.extend({
            url: cfg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: $.extend({ action: 'toocheke_bulk_' + action, nonce: cfg.nonce }, data)
        }, extra));
    }

    function errorText(xhr) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }
        return xhr && xhr.status ? 'HTTP ' + xhr.status : 'Network error';
    }

    function showUploadError(message) {
        $('<p>').text(message).appendTo($errors);
    }

    function readyItems() {
        return items.filter(function (it) { return it.state === 'ready'; });
    }

    function mode() {
        return $('input[name="tbu_mode"]:checked').val();
    }

    function params() {
        var ready = readyItems();
        var checked = function (sel) {
            return $(sel + ' input:checked').map(function () { return this.value; }).get().join(',');
        };
        return {
            tbu_ids: ready.map(function (it) { return it.id; }).join(','),
            tbu_uploaded: ready.filter(function (it) { return it.uploaded; }).map(function (it) { return it.id; }).join(','),
            tbu_mode: mode(),
            tbu_direction: mode() === 'backdate' ? $('#tbu_direction').val() : 'forward',
            tbu_date: $('#tbu_date').val(),
            tbu_time: $('#tbu_time').val(),
            tbu_weekdays: $('.tbu-weekday:checked').map(function () { return this.value; }).get().join(','),
            tbu_per_day: $('#tbu_per_day').val(),
            tbu_continue: $('#tbu_continue').is(':checked') ? '1' : '0',
            tbu_pattern: $('#tbu_pattern').val(),
            tbu_first: $('#tbu_first').val(),
            tbu_series: $('#tbu_series').val(),
            tbu_chapters: checked('#tbu_chapters'),
            tbu_collections: checked('#tbu_collections'),
            tbu_new_chapter: $('#tbu_new_chapter').val(),
            tbu_new_collection: $('#tbu_new_collection').val()
        };
    }

    function itemEl(it) {
        var $li = $('<li class="toocheke-bulk-item">').attr('data-key', it.key).addClass('is-' + it.state);
        var $thumb = $('<div class="toocheke-bulk-thumb">');
        if (it.thumb) {
            $('<img>').attr({ src: it.thumb, alt: '' }).appendTo($thumb);
        }
        if (it.state === 'uploading') {
            $('<span class="toocheke-bulk-spinner spinner is-active">').appendTo($thumb);
        }
        $('<span class="toocheke-bulk-index">').appendTo($thumb);
        $('<button type="button" class="toocheke-bulk-remove" aria-label="' + i18n.remove + '">&times;</button>').appendTo($thumb);
        $li.append($thumb);
        $('<span class="toocheke-bulk-name">').text(it.name).appendTo($li);
        if (it.state === 'error') {
            var $err = $('<span class="toocheke-bulk-item-error">').text(it.error + ' ');
            $('<button type="button" class="button-link toocheke-bulk-retry">').text(i18n.retry).appendTo($err);
            $li.append($err);
        }
        return $li;
    }

    function renumber() {
        $grid.children().each(function (i) {
            $(this).find('.toocheke-bulk-index').text(i + 1);
        });
        var ready = readyItems().length;
        $('#toocheke-bulk-count').text(ready + ' / ' + items.length);
        $toolbar.prop('hidden', !items.length);
    }

    function renderGrid() {
        $grid.empty();
        items.forEach(function (it) { $grid.append(itemEl(it)); });
        renumber();
    }

    function updateItem(it) {
        var $old = $grid.children('[data-key="' + it.key + '"]');
        if ($old.length) {
            $old.replaceWith(itemEl(it));
            renumber();
        }
    }

    function findItem(key) {
        for (var i = 0; i < items.length; i++) {
            if (items[i].key === key) {
                return items[i];
            }
        }
        return null;
    }

    function pump() {
        while (active < cfg.concurrent && queue.length) {
            upload(queue.shift());
        }
    }

    function upload(it) {
        active++;
        it.state = 'uploading';
        var fd = new FormData();
        fd.append('action', 'toocheke_bulk_upload');
        fd.append('nonce', cfg.nonce);
        fd.append('tbu_file', it.file, it.file.name);

        $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (res && res.success) {
                it.id = res.data.id;
                it.thumb = res.data.thumb;
                it.state = 'ready';
                it.file = null;
            } else {
                it.state = 'error';
                it.error = sprintf(i18n.uploadFailed, res && res.data ? res.data.message : '');
            }
        }).fail(function (xhr) {
            it.state = 'error';
            it.error = sprintf(i18n.uploadFailed, errorText(xhr));
        }).always(function () {
            active--;
            updateItem(it);
            pump();
            if (!active && !queue.length) {
                schedulePreview();
            }
        });
    }

    function addFiles(fileList) {
        var files = Array.prototype.slice.call(fileList);
        $errors.empty();

        files = files.filter(function (f) {
            if (cfg.allowed.indexOf(f.type) === -1) {
                showUploadError(sprintf(i18n.badType, f.name));
                return false;
            }
            if (cfg.maxBytes && f.size > cfg.maxBytes) {
                showUploadError(sprintf(i18n.tooLarge, f.name));
                return false;
            }
            return true;
        });

        files.sort(function (a, b) { return collator.compare(a.name, b.name); });

        var room = cfg.maxItems - items.length;
        if (files.length > room) {
            showUploadError(sprintf(i18n.tooMany, cfg.maxItems));
            files = files.slice(0, Math.max(room, 0));
        }

        files.forEach(function (f) {
            var it = { key: ++keySeq, id: 0, name: f.name, thumb: '', uploaded: true, state: 'uploading', file: f, error: '' };
            items.push(it);
            queue.push(it);
        });

        renderGrid();
        pump();
        schedulePreview();
    }

    function addLibrary(models) {
        var known = {};
        items.forEach(function (it) { if (it.id) { known[it.id] = true; } });

        var picked = models.filter(function (m) {
            return m.type === 'image' && !known[m.id];
        }).sort(function (a, b) {
            return collator.compare(a.filename || a.title || '', b.filename || b.title || '');
        });

        var room = cfg.maxItems - items.length;
        if (picked.length > room) {
            showUploadError(sprintf(i18n.tooMany, cfg.maxItems));
            picked = picked.slice(0, Math.max(room, 0));
        }

        picked.forEach(function (m) {
            items.push({
                key: ++keySeq,
                id: m.id,
                name: m.filename || m.title,
                thumb: m.sizes && m.sizes.thumbnail ? m.sizes.thumbnail.url : m.url,
                uploaded: false,
                state: 'ready',
                file: null,
                error: ''
            });
        });

        renderGrid();
        schedulePreview();
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(doPreview, 350);
    }

    function setPreviewMessage(text, isError) {
        previewOk = false;
        $create.prop('disabled', true);
        $preview.empty().append($('<em>').toggleClass('toocheke-bulk-error-text', !!isError).text(text));
    }

    function doPreview() {
        if (busy) {
            return;
        }

        $('#toocheke-bulk-anchor').text('');

        if (!readyItems().length) {
            setPreviewMessage(i18n.previewEmpty, false);
            return;
        }

        var seq = ++previewSeq;
        request('preview', params()).done(function (res) {
            if (seq !== previewSeq) {
                return;
            }
            if (!res.success) {
                setPreviewMessage(res.data.message, true);
                return;
            }
            renderPreview(res.data);
        }).fail(function (xhr) {
            if (seq === previewSeq) {
                setPreviewMessage(sprintf(i18n.error, errorText(xhr)), true);
            }
        });
    }

    function renderPreview(data) {
        var thumbs = {};
        items.forEach(function (it) { thumbs[it.id] = it.thumb; });

        var showDate = mode() !== 'draft';
        var $table = $('<table class="widefat striped"><thead><tr></tr></thead><tbody></tbody></table>');
        var heads = ['#', '', i18n.colTitle];
        if (showDate) {
            heads.push(i18n.colDate);
        }
        heads.forEach(function (h) { $table.find('thead tr').append($('<th>').text(h)); });

        data.rows.forEach(function (row, i) {
            var $tr = $('<tr>').toggleClass('is-duplicate', row.dup);
            $tr.append($('<td>').text(i + 1));
            $tr.append($('<td class="toocheke-bulk-preview-thumb">').append($('<img>').attr({ src: thumbs[row.id] || '', alt: '' })));
            var $title = $('<td>').text(row.title);
            if (row.dup) {
                $title.append($('<br>')).append($('<small class="toocheke-bulk-error-text">').text(i18n.duplicate));
            }
            $tr.append($title);
            if (showDate) {
                $tr.append($('<td>').text(row.dup ? '—' : row.date));
            }
            $table.find('tbody').append($tr);
        });

        $preview.empty().append($('<div class="toocheke-bulk-preview-scroll">').append($table));

        if ($('#tbu_continue').is(':checked') && mode() !== 'draft') {
            syncDateLock(!!data.anchor);
            var note = i18n.noAnchor;
            if (data.anchor) {
                note = sprintf(mode() === 'backdate' && $('#tbu_direction').val() === 'backward' ? i18n.anchorBefore : i18n.anchorAfter, data.anchor.title, data.anchor.date);
            }
            $('#toocheke-bulk-anchor').text(note);
        }

        previewOk = data.active > 0 && !active && !queue.length;
        $create.prop('disabled', !previewOk).data('count', data.active);
    }

    function syncMode() {
        var m = mode();
        $('#toocheke-bulk-dates').prop('hidden', m === 'draft');
        $('#toocheke-bulk-direction-wrap').prop('hidden', m !== 'backdate');
        $('#toocheke-bulk-direction-help').prop('hidden', m !== 'backdate');
        syncDateLabel();
        if (!dateTouched) {
            var d = new Date();
            if (m === 'backdate') {
                d.setDate(d.getDate() - 1);
                $('#tbu_direction').val('backward');
            } else {
                d.setDate(d.getDate() + 1);
            }
            $('#tbu_date').val(isoDate(d));
            syncWeekday();
        }
        var label = { schedule: i18n.createSchedule, backdate: i18n.createBackdate, draft: i18n.createDraft }[m];
        $create.text(label);
        $('#toocheke-bulk-create-label').text(label);
    }

    function syncDateLock(locked) {
        $('#tbu_date').prop('disabled', locked).closest('.toocheke-bulk-field').toggleClass('is-disabled', locked);
    }

    function syncDateLabel() {
        var text = i18n.firstDate;
        if (mode() === 'backdate' && $('#tbu_direction').val() === 'backward') {
            text = i18n.lastDate;
        }
        $('#toocheke-bulk-date-label').text(text);
    }

    function isoDate(d) {
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    function syncWeekday() {
        if (daysTouched) {
            return;
        }
        var parts = ($('#tbu_date').val() || '').split('-');
        if (parts.length !== 3) {
            return;
        }
        var day = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)).getDay();
        $('.tbu-weekday').prop('checked', false).filter('[value="' + day + '"]').prop('checked', true);
    }

    function log(text, cls) {
        $('<div class="toocheke-bulk-log-line">').addClass(cls || '').text(text).appendTo($log);
        $log.scrollTop($log[0].scrollHeight);
    }

    function setBusy(state) {
        busy = state;
        $('.toocheke-bulk-wrap').toggleClass('is-busy', state);
        $create.prop('disabled', state || !previewOk);
    }

    function runBatch(batchId, total, cursor, retries) {
        retries = retries || 0;
        $progress.prop('hidden', false);
        setBusy(true);

        request('step', { tbu_batch: batchId }).done(function (res) {
            if (!res.success) {
                finishBusy();
                log(sprintf(i18n.error, res.data.message), 'is-error');
                return;
            }
            var d = res.data;
            d.created.forEach(function (c) {
                log('✓ ' + c.title + (c.date ? ' — ' + c.date : '') + ' [' + c.status + ']');
            });
            d.errors.forEach(function (e) { log('✗ ' + e, 'is-error'); });
            $fill.css('width', Math.round((d.cursor / Math.max(d.total, 1)) * 100) + '%');
            $status.text(sprintf(i18n.creating, d.cursor, d.total));

            if (!d.done) {
                runBatch(batchId, d.total, d.cursor, 0);
                return;
            }

            finishBusy();
            $status.text(sprintf(i18n.finished, d.total - d.failed, d.skipped + d.failed));
            addBatchRow(d.row, batchId);
            items = [];
            queue = [];
            renderGrid();
            setPreviewMessage(i18n.previewEmpty, false);
            $('<a class="button">').attr('href', 'edit.php?post_type=comic').text(i18n.viewComics).appendTo($status);
        }).fail(function (xhr) {
            if (retries < 3) {
                setTimeout(function () { runBatch(batchId, total, cursor, retries + 1); }, 1500);
                return;
            }
            finishBusy();
            $status.text(sprintf(i18n.error, errorText(xhr)));
            $('<button type="button" class="button">').text(i18n.resume).on('click', function () {
                runBatch(batchId, total, cursor, 0);
            }).appendTo($status);
        });
    }

    function finishBusy() {
        setBusy(false);
    }

    function addBatchRow(html, batchId) {
        if (!html) {
            return;
        }
        $batches.children('[data-batch="' + batchId + '"]').remove();
        $batches.prepend(html);
        $('#toocheke-bulk-no-batches').prop('hidden', true);
    }

    function startCreate() {
        var count = $create.data('count') || readyItems().length;
        if (!window.confirm(sprintf(i18n.confirmCreate, count))) {
            return;
        }

        $log.empty();
        $fill.css('width', '0%');
        $status.text(sprintf(i18n.creating, 0, count));
        $progress.prop('hidden', false);
        setBusy(true);

        request('start', params()).done(function (res) {
            if (!res.success) {
                finishBusy();
                $status.text(sprintf(i18n.error, res.data.message));
                return;
            }
            runBatch(res.data.batch, res.data.total, 0, 0);
        }).fail(function (xhr) {
            finishBusy();
            $status.text(sprintf(i18n.error, errorText(xhr)));
        });
    }

    function runUndo($li, deleteImages) {
        var id = $li.attr('data-batch');
        $li.find('button').prop('disabled', true);
        $li.find('.toocheke-bulk-batch-actions').append($('<span class="spinner is-active">'));

        request('undo', { tbu_batch: id, tbu_delete_images: deleteImages ? '1' : '0' }).done(function (res) {
            if (!res.success) {
                $li.find('.spinner').remove();
                $li.find('button').prop('disabled', false);
                window.alert(res.data.message);
                return;
            }
            if (res.data.done) {
                $li.replaceWith(res.data.row);
                return;
            }
            runUndo($li, deleteImages);
        }).fail(function (xhr) {
            $li.find('.spinner').remove();
            $li.find('button').prop('disabled', false);
            window.alert(sprintf(i18n.error, errorText(xhr)));
        });
    }

    $('#toocheke-bulk-browse').on('click', function () { $file.trigger('click'); });
    $file.on('change', function () {
        addFiles(this.files);
        this.value = '';
    });

    $drop.on('dragenter dragover', function (e) {
        e.preventDefault();
        $drop.addClass('is-over');
    }).on('dragleave drop', function (e) {
        e.preventDefault();
        $drop.removeClass('is-over');
    }).on('drop', function (e) {
        var dt = e.originalEvent.dataTransfer;
        if (dt && dt.files && dt.files.length) {
            addFiles(dt.files);
        }
    });

    $(document).on('dragover drop', function (e) {
        if (!$(e.target).closest($drop).length) {
            e.preventDefault();
        }
    });

    var frame = null;
    $('#toocheke-bulk-library').on('click', function () {
        if (!frame) {
            frame = wp.media({
                title: $(this).text(),
                multiple: true,
                library: { type: 'image' },
                button: { text: $(this).text() }
            });
            frame.on('select', function () {
                addLibrary(frame.state().get('selection').toJSON());
            });
        }
        frame.open();
    });

    $grid.sortable({
        items: '> li',
        tolerance: 'pointer',
        update: function () {
            var order = $grid.children().map(function () { return parseInt($(this).attr('data-key'), 10); }).get();
            items = order.map(findItem).filter(Boolean);
            renumber();
            schedulePreview();
        }
    });

    $grid.on('click', '.toocheke-bulk-remove', function () {
        var key = parseInt($(this).closest('li').attr('data-key'), 10);
        items = items.filter(function (it) { return it.key !== key; });
        queue = queue.filter(function (it) { return it.key !== key; });
        $(this).closest('li').remove();
        renumber();
        schedulePreview();
    });

    $grid.on('click', '.toocheke-bulk-retry', function () {
        var it = findItem(parseInt($(this).closest('li').attr('data-key'), 10));
        if (it && it.file) {
            it.state = 'uploading';
            queue.push(it);
            updateItem(it);
            pump();
        }
    });

    $('#toocheke-bulk-sort').on('click', function () {
        items.sort(function (a, b) { return collator.compare(a.name, b.name); });
        renderGrid();
        schedulePreview();
    });

    $('#toocheke-bulk-reverse').on('click', function () {
        items.reverse();
        renderGrid();
        schedulePreview();
    });

    $('#toocheke-bulk-clear').on('click', function () {
        items = items.filter(function (it) { return it.state === 'uploading'; });
        renderGrid();
        schedulePreview();
    });

    $('input[name="tbu_mode"]').on('change', function () {
        syncMode();
        schedulePreview();
    });

    $('#tbu_direction').on('change', function () {
        syncDateLabel();
        schedulePreview();
    });

    $('#tbu_date').on('input change', function () {
        dateTouched = true;
        syncWeekday();
        schedulePreview();
    });

    $('.tbu-weekday').on('change', function () {
        daysTouched = true;
        schedulePreview();
    });

    $('#tbu_time, #tbu_per_day, #tbu_continue, #tbu_pattern, #tbu_first, #tbu_series, #tbu_new_chapter, #tbu_new_collection').on('input change', schedulePreview);

    $create.on('click', startCreate);

    $batches.on('click', '.toocheke-bulk-undo', function () {
        var $li = $(this).closest('li');
        if (window.confirm(i18n.confirmUndo)) {
            runUndo($li, $li.find('.toocheke-bulk-delete-images').is(':checked'));
        }
    });

    $batches.on('click', '.toocheke-bulk-resume', function () {
        var $li = $(this).closest('li');
        $log.empty();
        runBatch($li.attr('data-batch'), parseInt($li.attr('data-total'), 10), parseInt($li.attr('data-cursor'), 10), 0);
    });

    $(window).on('beforeunload', function () {
        if (busy || active || queue.length) {
            return true;
        }
        return undefined;
    });

    syncMode();
    syncWeekday();
    $('#tbu_continue').on('change', function () {
        $('#toocheke-bulk-anchor').text('');
        syncDateLock(this.checked);
    });
}(jQuery));
