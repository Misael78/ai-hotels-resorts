
(function (Drupal, drupalSettings, once) {

  /**
   * Control the carousel logic.
   */
  class SimpleCarousel {

    constructor(element) {
      this.hover = false;
      this.currentSlide = 0;
      this.carousel = element;
      this.cid = element.getAttribute("data-id");
      this.lastUpdated = element.getAttribute("data-last_updated");
      this.configKey = this.cid + '_' + this.lastUpdated;
      this.cfg = drupalSettings.easy_carousel[this.configKey].config;
      this.indicators = this.carousel.querySelectorAll('.indicator');
      this.slides = this.carousel.querySelectorAll('.slide');
      this.list = this.carousel.querySelector('.list');
      this.itemWidth = this.list.querySelector('.item').offsetWidth;
      this.scrollBehavior = 'smooth';

      // Prepare controls if proceed
      if (this.cfg.show_controls) {
        this.carousel.querySelector('.carousel-control.prev').addEventListener('click', () => { this.movePreviousSlide(); });
        this.carousel.querySelector('.carousel-control.next').addEventListener('click', () => { this.moveNextSlide(); });
        this.enableDisableControls();
      }

      // Prepare indicators if proceed
      if (this.cfg.show_indicators) {
        this.indicators.forEach((indicator, index) => {
          indicator.addEventListener('click', () => { this.moveToIndex(index); });
        });
      }

      // Prepare auto start sliding if proceed
      if (this.cfg.auto_start) {
        this.carousel.addEventListener('mouseover', () => { this.hover = true; });
        this.carousel.addEventListener('mouseout', () => { this.hover = false; });
        this.autoStartIntervalId = setInterval(() => {
          if (!this.hover) {
            this.moveNextSlide();
          }
        }, this.cfg.speed);
      }

      document.addEventListener('resize', () => { this.updateHeight(); });

      // Adjust the iframe (videos) width/height with a good screen ratio.
      this.adjustVideoIframesHeight();

      // Wait for image load to calculate correct carousel's height
      this.waitForImagesToLoad().then(() => {
        // Image container has a image placeholder with. When imagen is
        // loaded, remove preconfigured height to adjust to image's height.
        let slideImages = this.list.querySelectorAll('.slide-image');
        for (let slideImage of slideImages)
          slideImage.style.height = "auto";

        // Add extra space in the 'slide-content' to display the indicators
        if (this.cfg.show_indicators) {
          let slideContentItems = this.list.querySelectorAll('.slide-content');
          for (let slideContentItem of slideContentItems)
            slideContentItem.style.height = `${slideContentItem.offsetHeight + 25}px`;
        }

        // Update carousel's height
        this.updateHeight();
      });
    }

    /**
     * Control the image load proccess. Until all image are loaded, promise
     * won't be resolved.
     *
     * @returns
     *   Promise.
     */
    waitForImagesToLoad() {
      const images = this.list.querySelectorAll('.slide-image img');
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

    /**
     * Move to the next slide.
     */
    moveNextSlide() {
      this.currentSlide = (this.currentSlide + 1) % this.slides.length;
      this.moveTo('next');
      // Disable the next button if we are in the last slide
      this.enableDisableControls();
    }

    /**
     * Move to the previous slide.
     */
    movePreviousSlide() {
      this.currentSlide = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
      this.moveTo('previous');
      // Disable the previous button if we are in the first slide
      this.enableDisableControls();
    }

    /**
     * Enable/disable the carousel controls based on the current slide.
     */
    enableDisableControls() {
      // Disable the next button if we are in the last slide
      this.carousel.querySelector('.carousel-control.next').disabled = (this.currentSlide === this.slides.length - 1);
      this.carousel.querySelector('.carousel-control.next').style.opacity = (this.currentSlide === this.slides.length - 1) ? 0.2 : 1;
      // Disable the previous button if we are in the first slide
      this.carousel.querySelector('.carousel-control.prev').disabled = (this.currentSlide === 0);
      this.carousel.querySelector('.carousel-control.prev').style.opacity = (this.currentSlide === 0) ? 0.2 : 1;
    }

    /**
     * Move to the specified slide index (zero based).
     * The smooth scroll is disabled in this operation.
     *
     * @param {*} index
     *   Destination slide index.
     */
    moveToIndex(index) {
      if (index >= 0 || index < this.slides.length) {
        if (index != this.currentSlide) {
          let direction = (index < this.currentSlide) ? 'previous' : 'next';
          // Disable temporary smooth scroll (with smooth behavior doesn't work
          // as expected)
          this.scrollBehavior = 'auto';
          // Use movePreviousSlide or moveNextSlide to go to specified slide index
          while (this.currentSlide != index) {
            if (direction === 'previous') {
              this.movePreviousSlide();
            }
            else {
              this.moveNextSlide();
            }
          }
          this.scrollBehavior = 'smooth';
        }
      }
    }

    /**
     * Move to next/previous slide.
     *
     * @param {*} direction
     *   String with "next" or "previous" for the movement direction.
     */
    moveTo(direction) {
      const scrollPosition = this.list.scrollLeft; // Current scroll position
      const maxScrollPosition = this.list.scrollWidth - this.list.clientWidth; // Max. scroll position

      if (direction === "previous") {
        if (scrollPosition <= 0) {
          // If we are in the first item, go to the lastSi estamos en el primer elemento, mover al último.
          this.list.scrollTo({
            left: maxScrollPosition,
            behavior: this.scrollBehavior
          });
        }
        else {
          // Move to previous
          this.list.scrollBy({
            left: -this.itemWidth,
            behavior: this.scrollBehavior
          });
        }
      }
      else {
        if (scrollPosition >= maxScrollPosition) {
          // If we are in the last item, go to first
          this.list.scrollTo({
            left: 0,
            behavior: this.scrollBehavior
          });
        }
        else {
          // Go to next
          this.list.scrollBy({
            left: this.itemWidth,
            behavior: this.scrollBehavior
          });
        }
      }

      // Update indicators
      this.updateIndicators();

      // Adjust iframe ratio and carousel height based on current slide
      this.updateHeight();
    }

    /**
     * Mark as "active" the current slide indicator.
     */
    updateIndicators() {
      if (this.cfg.show_indicators) {
        this.indicators.forEach((indicator, index) => {
          indicator.classList.toggle('active', index === this.currentSlide);
        });
      }
    }

    /**
     * This function calculate all the carousel youtube/vimeo iframe height based on the
     * 16:9 ratio.
     */
    adjustVideoIframesHeight() {
      for (let activeSlide of this.slides) {
        let remoteVideoIframe = activeSlide.querySelector('.easy-carousel-remote-video');
        if (remoteVideoIframe) {
          const windowWidth = window.innerWidth;
          // Calculate height based on 16:9 ratio
          const height = windowWidth * 9 / 16;
          // Adjust iframe width/height
          remoteVideoIframe.style.height = `${height}px`;
        }
      }
    }

    /**
     * Adjust the carousel's height with the current slide's height.
     */
    updateHeight() {
      let slideHeight = this.slides[this.currentSlide].offsetHeight;
      this.carousel.style.height = `${slideHeight}px`;
    }
  }

  Drupal.behaviors.easyCarouselSimple = {
    attach: function (context, settings) {
      once('easyCarouselSimpleInit', '.easy-carousel--simple', context).forEach(function (element) {
        const app = new SimpleCarousel(element);
      });
    }
  };

})(Drupal, drupalSettings, once);
