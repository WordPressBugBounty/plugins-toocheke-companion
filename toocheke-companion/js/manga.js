jQuery(document).ready(function ($) {

    // check native support
    jQuery('#support').text(jQuery.fullscreen.isNativelySupported() ? 'supports' : 'doesn\'t support');

    // open in fullscreen
    jQuery("#btn-fullscreen").click(function () {
        jQuery('main').fullscreen();
        return false;
    });

    // fullscreen event
    jQuery(document).bind('fscreenchange', function () {
        if (jQuery.fullscreen.isFullScreen()) {
            jQuery('main .requestfullscreen').hide();
            jQuery('main .exitfullscreen').show();
        } else {
            jQuery('main .requestfullscreen').show();
            jQuery('main .exitfullscreen').hide();
        }
    });

    const swiperEl = document.getElementById('manga-swiper');
    const loader = document.getElementById('swiper-loader-container');
    const wrapperEl = document.getElementById('manga-swiper-wrapper');

    if (!swiperEl) {
        return;
    }

    const isRTL = swiperEl.dataset.rtl === '1';
    const desktopSlides = parseInt(swiperEl.dataset.desktopSlides || '2', 10);

    let currentMode = null;

    function isMobile() {
        return window.innerWidth < 768;
    }

    function getCurrentSlidesPerView() {
        if (isMobile()) {
            return 1;
        }
        return jQuery('.manga-reader-container').hasClass('two-pages') ? desktopSlides : 1;
    }

    function applyDirectionAttributes() {
        // On mobile we always use ltr to prevent Swiper's vertical mode
        // from inverting touch coordinates in RTL, which causes swipe to break.
        // On desktop we restore rtl if the setting is enabled.
        const dir = isMobile() ? 'ltr' : (isRTL ? 'rtl' : 'ltr');

        if (wrapperEl) {
            wrapperEl.setAttribute('dir', dir);
        }

        if (!isMobile() && isRTL) {
            swiperEl.setAttribute('rtl', 'true');
        } else {
            swiperEl.removeAttribute('rtl');
        }
    }

    function initSwiper() {
        applyDirectionAttributes();

        const slidesPerView = getCurrentSlidesPerView();

        Object.assign(swiperEl, {
            direction: isMobile() ? 'vertical' : 'horizontal',
            slidesPerView: slidesPerView,
            slidesPerGroup: slidesPerView,
            spaceBetween: 0,
            zoom: true,
            navigation: true,
            keyboard: { enabled: true },
            pagination: { type: 'progressbar' },
            simulateTouch: true,
            allowTouchMove: true,
        });

        swiperEl.initialize();

        const swiper = swiperEl.swiper;
        if (!swiper) {
            return;
        }

        currentMode = isMobile() ? 'mobile' : 'desktop';
        setupSwiper(swiper);
    }

    function destroyAndReinit() {
        if (swiperEl.swiper && !swiperEl.swiper.destroyed) {
            swiperEl.swiper.destroy(true, true);
        }
        initSwiper();
    }

    function reInitIfModeChanged() {
        const newMode = isMobile() ? 'mobile' : 'desktop';
        if (newMode !== currentMode) {
            destroyAndReinit();
        }
    }

    function setupSwiper(swiper) {
        const totalSlides = jQuery('swiper-container swiper-slide').length;
        let current = swiper.realIndex + 1;

        jQuery('.swiper-pagination').text(current + '/' + totalSlides);

        swiper.on('realIndexChange', function () {
            current = swiper.realIndex + 1;
            if (current > totalSlides) {
                current = 1;
            }
            jQuery('.swiper-pagination').text(current + '/' + totalSlides);
        });

        swiper.on('tap', function (swiperInstance, e) {
            if (e.target && e.target.tagName.toLowerCase() === 'img') {
                jQuery('.manga-page-nav').toggleClass('d-block');
            }
        });

        bindLoader();
    }

    function bindLoader() {
        const images = swiperEl.querySelectorAll('swiper-slide img');
        let loadedCount = 0;

        if (images.length === 0) {
            hideLoader();
            return;
        }

        images.forEach(img => {
            if (img.complete) {
                increment();
            } else {
                img.addEventListener('load', increment, { once: true });
                img.addEventListener('error', increment, { once: true });
            }
        });

        function increment() {
            loadedCount++;
            if (loadedCount === images.length) {
                hideLoader();
            }
        }
    }

    function hideLoader() {
        if (loader) {
            loader.classList.add('loaded');
        }
    }

    // One page / two page toggle buttons
    jQuery("#btn-one-page").click(function () {
        jQuery('.manga-reader-container').removeClass('two-pages');
        if (swiperEl.swiper) {
            swiperEl.swiper.params.slidesPerView = 1;
            swiperEl.swiper.params.slidesPerGroup = 1;
            swiperEl.swiper.update();
        }
    });

    jQuery("#btn-two-pages").click(function () {
        if (isMobile()) {
            return; // two-page mode not applicable on mobile
        }
        jQuery('.manga-reader-container').addClass('two-pages');
        if (swiperEl.swiper) {
            swiperEl.swiper.params.slidesPerView = desktopSlides;
            swiperEl.swiper.params.slidesPerGroup = desktopSlides;
            swiperEl.swiper.update();
        }
    });

    // Initialize on page load
    initSwiper();

    // Reinitialize if viewport crosses the mobile/desktop boundary
    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(reInitIfModeChanged, 150);
    });

});