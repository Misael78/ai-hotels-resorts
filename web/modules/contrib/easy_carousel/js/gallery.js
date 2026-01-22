(function (Drupal, drupalSettings, once) {

  /**
   * Control the carousel logic.
   */
  class GalleryCarousel {

    constructor(element) {
      this.hover = false;
      this.carousel = element;
      this.cid = element.getAttribute("data-id");
      this.lastUpdated = element.getAttribute("data-last_updated");
      this.configKey = this.cid + '_' + this.lastUpdated;
      this.cfg = drupalSettings.easy_carousel[this.configKey].config;
      this.slides = this.carousel.querySelectorAll('.slide');
      this.content = element.querySelector('.content');
      this.currentIndex = 0;
      this.autostart = this.cfg.auto_start || false;
      this.interval = null;

      this.prepareThumbnails();
      this.load(this.slides[this.currentIndex]);

      if (this.autostart) {
        this.startAutoplay();
      }
    }

    prepareThumbnails() {
      this.slides.forEach((slide, index) => {
        slide.addEventListener('click', (evt) => {
          this.stopAutoplay(); // Stop autoplay on click
          this.load(evt.target.closest('.slide'));
          this.currentIndex = index;
        });
      });
    }

    load(slideToLoad) {
      let activeSlide = this.content;
      let newSlide = slideToLoad.cloneNode(true);

      this.slides.forEach((slide) => {
        let slideImage = slide.querySelector('.slide-image');
        if (slideImage) {
          slideImage.style.height = `${this.cfg.thumbnail_width}px`;
        }
        else {
          slide.style.height = `${this.cfg.thumbnail_width}px`;
          slide.classList.add('no-image');
        }
        slide.classList.remove('active');
      });

      slideToLoad.classList.add('active');
      let newSlideImage = newSlide.querySelector('.slide-image');
      if (newSlideImage) {
        newSlideImage.style.height = '100%';
      }
      else {
        newSlide.classList.add('no-image');
      }

      activeSlide.classList.add('fade-out');

      setTimeout(() => {
        this.content.innerHTML = newSlide.outerHTML;
        this.content.classList.remove('fade-out');
        this.content.classList.add('fade-in');

        setTimeout(() => {
          this.content.classList.remove('fade-in');
        }, 500);
      }, 300);
    }

    startAutoplay() {
      this.interval = setInterval(() => {
        this.currentIndex = (this.currentIndex + 1) % this.slides.length;
        this.load(this.slides[this.currentIndex]);
      }, this.cfg.speed);
    }

    stopAutoplay() {
      if (this.interval) {
        clearInterval(this.interval);
        this.interval = null;
      }
    }
  }

  Drupal.behaviors.easyCarouselBrands = {
    attach: function (context, settings) {
      once('easyCarouselGalleryInit', '.easy-carousel--gallery', context).forEach(function (element) {
        new GalleryCarousel(element);
      });
    }
  };

})(Drupal, drupalSettings, once);
