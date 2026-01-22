
(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.easyCarouselBootstrap = {
    attach: function (context, settings) {

      once('easyCarouselBootstrapInit', '.easy-carousel--bootstrap', context).forEach(function (element) {

        const cid = element.getAttribute("data-id");
        const lastUpdated = element.getAttribute("data-last_updated");
        const configKey = cid + '_' + lastUpdated;
        const cfg = drupalSettings.easy_carousel[configKey].config;
        const carousel = element;

        function syncCarouselHeights() {
          const items = document.querySelectorAll('.carousel-item');
          let maxHeight = 0;

          // Find the maximun height
          items.forEach(item => {
            const height = item.offsetHeight;
            if (height > maxHeight) maxHeight = height;
          });

          // Apply the maximun height to all slides
          items.forEach(item => {
            item.style.minHeight = `${maxHeight}px`;
          });
        }

        /**
         * This function calculate the youtube/vimeo iframe height based on the
         * 16:9 ratio.
         */
        function adjustVideoIframeHeight(videoElement) {
          let windowWidth = window.innerWidth;
          // Calculate height based on 16:9 ratio
          let height = windowWidth * 9 / 16;
          // Adjust iframe width/height
          videoElement.style.width = `${windowWidth}px`;
          videoElement.style.height = `${height}px`;
        }

        function stopCarousel() {
          carouselInstance._config.ride=false;
          carousel.setAttribute('data-bs-ride', false);
          carouselInstance.pause();
        }

        const videos = element.querySelectorAll('.easy-carousel-video');
        for(let v of videos) {
          adjustVideoIframeHeight(v);
        }


        /**
         * Control the image load proccess. Until all image are loaded, promise
         * won't be resolved.
         *
         * @returns
         *   Promise.
         */
        function waitForImagesToLoad() {
          const images = carousel.querySelectorAll('.carousel-item img');
          const imagePromises = Array.from(images).map((img) => {
            if (img.complete) {
              return Promise.resolve();
            } else {
              // Wait for "load" event
              return new Promise((resolve) => {
                img.addEventListener('load', resolve);
                // On error, resolve also.
                img.addEventListener('error', resolve);
              });
            }
          });
          return Promise.all(imagePromises);
        }

        // Wait for image load to calculate correct carousel's height
        waitForImagesToLoad().then(() => {
          syncCarouselHeights();
        });

        // .carousel-control-prev, .carousel-control-next
        // Check if is required auto start slide movement
        // if (cfg.auto_start && cfg.stop_on_click) {
        //   const prevButton = document.querySelector('[data-bs-slide="prev"]');
        //   const nextButton = document.querySelector('[data-bs-slide="next"]');
        //   if (prevButton && nextButton && carouselInstance) {
        //     prevButton.addEventListener('click', () => carouselInstance.pause(false));
        //     nextButton.addEventListener('click', stopCarousel);
        //   }
        // }

        //carouselInstance.cycle();'
        carousel.addEventListener('resize', syncCarouselHeights);

      });

    }

  };
})(Drupal, drupalSettings, once);
