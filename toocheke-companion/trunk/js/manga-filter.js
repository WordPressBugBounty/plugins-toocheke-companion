(function () {
    'use strict';

    const grid     = document.getElementById('manga-series-grid');
    const sentinel = document.getElementById('manga-series-sentinel');
    const loading  = document.getElementById('manga-series-loading');
    const form     = document.getElementById('manga-series-filter-form');

    // Bail if elements are not present on this page
    if (!grid || !sentinel || !form) return;

    const API_URL    = toochekeMangaFilter.restUrl;
    const PER_PAGE   = 12;

    let currentPage   = 1;
    let totalPages    = 1;
    let isFetching    = false;
    let activeFilters = { publisher: '', genre: '' };

    // -------------------------------------------------------------------------
    // Render a single series card matching the theme's existing markup
    // -------------------------------------------------------------------------
    function renderCard(series) {
        const a     = document.createElement('a');
        a.href      = series.permalink;
        a.className = 'manga-grid-item-container fade-in';
        a.title     = series.title;

        const thumbnailDiv     = document.createElement('div');
        thumbnailDiv.className = 'manga-grid-item-thumbnail manga-thumbnail';

        if (series.thumbnail) {
            const img    = document.createElement('img');
            img.src      = series.thumbnail;
            img.alt      = series.title;
            img.loading  = 'lazy';
            img.decoding = 'async';

            if (series.thumbnail_srcset) {
                img.srcset = series.thumbnail_srcset;
            }

            if (series.thumbnail_sizes) {
                img.sizes = series.thumbnail_sizes;
            }

            thumbnailDiv.appendChild(img);
        }

        const span       = document.createElement('span');
        span.className   = 'manga-grid-item-title';
        span.textContent = series.title;

        a.appendChild(thumbnailDiv);
        a.appendChild(span);

        return a;
    }

    // -------------------------------------------------------------------------
    // Fetch a page of results from the REST API
    // replace = true clears the grid first (used when filters change)
    // -------------------------------------------------------------------------
    async function fetchSeries(page, filters, replace = false) {
        if (isFetching) return;

        isFetching            = true;
        loading.style.display = 'block';

        const params = new URLSearchParams({
            page,
            per_page:  PER_PAGE,
            publisher: filters.publisher,
            genre:     filters.genre,
        });

        try {
            const response = await fetch(`${API_URL}?${params.toString()}`);

            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }

            const data = await response.json();

            if (replace) {
                grid.innerHTML = '';
            }

            if (data.series.length === 0 && replace) {
                const msg       = document.createElement('p');
                msg.className   = 'manga-no-results';
                msg.textContent = toochekeMangaFilter.noResults;
                grid.appendChild(msg);
            } else {
                data.series.forEach(function (series) {
                    grid.appendChild(renderCard(series));
                });
            }

            totalPages  = data.totalPages;
            currentPage = data.page;

            // Stop observing once all pages are loaded
            if (currentPage >= totalPages) {
                observer.disconnect();
                sentinel.style.display = 'none';
            } else {
                observer.observe(sentinel);
                sentinel.style.display = 'block';
            }

        } catch (error) {
            console.error('Manga series fetch error:', error);
        } finally {
            isFetching            = false;
            loading.style.display = 'none';
        }
    }

    // -------------------------------------------------------------------------
    // IntersectionObserver — triggers next page when sentinel scrolls into view
    // -------------------------------------------------------------------------
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting && currentPage < totalPages && !isFetching) {
                fetchSeries(currentPage + 1, activeFilters);
            }
        });
    }, {
        rootMargin: '200px', // begin loading 200px before the sentinel is visible
    });

    observer.observe(sentinel);

    // -------------------------------------------------------------------------
    // Filter form submit — resets page state and reloads with new filters
    // -------------------------------------------------------------------------
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn     = form.querySelector('button[type="submit"]');
        submitBtn.classList.add('disabled');
        submitBtn.disabled  = true;
        submitBtn.innerHTML = '<span class="manga-button-spinner"></span> ' + toochekeMangaFilter.loadingText;

        activeFilters = {
            publisher: form.querySelector('#publisher').value,
            genre:     form.querySelector('#genre').value,
        };

        // Reset pagination state for the new filter
        currentPage = 1;
        totalPages  = 1;

        // Re-observe sentinel in case it was disconnected after a full load
        observer.observe(sentinel);
        sentinel.style.display = 'block';

        fetchSeries(1, activeFilters, true).then(function () {
            submitBtn.classList.remove('disabled');
            submitBtn.disabled  = false;
            submitBtn.innerHTML = toochekeMangaFilter.filterLabel;
        });
    });

    // -------------------------------------------------------------------------
    // Initial load on page ready
    // -------------------------------------------------------------------------
    fetchSeries(1, activeFilters, true);

})();