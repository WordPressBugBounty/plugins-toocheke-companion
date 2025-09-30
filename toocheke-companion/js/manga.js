jQuery(document).ready(function ($) {




    // check native support
    jQuery('#support').text(jQuery.fullscreen.isNativelySupported() ? 'supports' : 'doesn\'t support');
    // open in fullscreen
    jQuery("#btn-fullscreen").click(function () {
        jQuery('main').fullscreen();
        return false;
    });

    // document's event
    jQuery(document).bind('fscreenchange', function (e, state, elem) {
        // if we currently in fullscreen mode
        if (jQuery.fullscreen.isFullScreen()) {
            jQuery('main .requestfullscreen').hide();
            jQuery('main .exitfullscreen').show();
        } else {
            jQuery('main .requestfullscreen').show();
            jQuery('main .exitfullscreen').hide();
        }

    });
    jQuery("#btn-one-page").click(function () {
        jQuery("swiper-container").attr("slides-per-view", "1");
        jQuery("swiper-container").attr("slides-per-group", "1");
        jQuery('.manga-reader-container').removeClass('two-pages');
    });
    jQuery("#btn-two-pages").click(function () {
        jQuery("swiper-container").attr("slides-per-view", "2");
        jQuery("swiper-container").attr("slides-per-group", "2");
        jQuery('.manga-reader-container').addClass('two-pages');
    });
    jQuery("swiper-slide img").click(function () {
        jQuery('.manga-page-nav').toggleClass('d-block');

    });

    const mainSwiper = document.querySelector('swiper-container');


    var totalSlides = jQuery('swiper-container swiper-slide').length;
    var current = 1;
    //console.log(totalSlides);
    jQuery('.swiper-pagination').text(current + '/' + totalSlides);
    mainSwiper.swiper.on('realIndexChange', function () {
        // console.log('changed');

        current = mainSwiper.swiper.realIndex + 1;
        //console.log(current );
        if (current > totalSlides)
            current = 1;
        jQuery('.swiper-pagination').text(current + '/' + totalSlides);

    });

   const swiperEl = document.getElementById('manga-swiper');
const loader = document.getElementById('swiper-loader-container');

if (swiperEl) {
  // If already initialized
  if (swiperEl.swiper) {
    setup(swiperEl.swiper);
  } else {
    swiperEl.addEventListener('swiperinit', () => {
      setup(swiperEl.swiper);
    });
  }
}

function setup(swiper) {
  console.log('Swiper ready:', swiper);

  const images = swiperEl.querySelectorAll('swiper-slide img');
  let loadedCount = 0;

  if (images.length === 0) {
    hideLoader();
    return;
  }

  images.forEach(img => {
    if (img.complete) {
      // Already loaded (from cache)
      increment();
    } else {
      img.addEventListener('load', increment);
      img.addEventListener('error', increment); // still count if it errors
    }
  });

  function increment() {
    loadedCount++;
    if (loadedCount === images.length) {
     // console.log('All images loaded');
      hideLoader();
    }
  }
}

function hideLoader() {
  if (loader) {
    loader.classList.add('loaded');
    //console.log('Loader hidden!');
  }
}




});

