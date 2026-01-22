
(function (Drupal, drupalSettings, once) {

  /**
   * Control the carousel logic.
   */
  class BrandsCarousel {

    constructor(element) {
      this.hover = false;
      this.carousel = element;
      this.cid = element.getAttribute("data-id");
      this.lastUpdated = element.getAttribute("data-last_updated");
      this.configKey = this.cid + '_' + this.lastUpdated;
      this.cfg = drupalSettings.easy_carousel[this.configKey].config;
      this.slides = this.carousel.querySelectorAll('.slide');
      this.list = this.carousel.querySelector('.list');
      this.newsItems = Array.from(this.list.children);
      this.offset = 0.0;
      this.marginBetweenSlides = Number.parseFloat(this.cfg.margin_between_slides);
      this.newsWidth = Number.parseFloat(this.cfg.slide_width) + this.marginBetweenSlides;
      // TODO: Implement vertical movement, this.cfg.axis = "vertical";
      this.prepareCarouselSlides();
    }

    prepareCarouselSlides() {

      // Prepare events if proceed
      if (this.cfg.stop_on_hover) {
        this.carousel.addEventListener('mouseover', () => { this.hover = true; });
        this.carousel.addEventListener('mouseout', () => { this.hover = false; this.animate(); });
      }

      // Get current screen width
      let viewportWidth = window.innerWidth;

      // Get the number of required slide clones to fill twice screen width
      let numClones = Math.ceil((viewportWidth * 2) / this.newsWidth);

      if (this.slides.length > 0) {
        this.slides.forEach((slide) => {
          // If the width of the image is greater than slide's width,
          // adjust the image's width to the slide's width.
          let img = slide.querySelector('.slide-image img');
          if (img) {
            if (img.width > this.cfg.slide_width) {
              img.width = this.cfg.slide_width;
            }
          }
        });

        // Clonamos elementos hasta llenar el doble del ancho de la ventana
        for (let i = 0; i < numClones; i++) {
          this.newsItems.forEach((item) => {
            let clone = item.cloneNode(true);
            this.list.appendChild(clone);
          });
        }
      }
    }

    animate() {
      if (this.hover)
        return;

      if (this.slides.length > 0) {
        // Check direction (right to left or left to right)
        if (this.cfg.direction === 'rtl') {
          // Get position to the left
          this.offset -= Number.parseFloat(this.cfg.speed);
          // If first element disapear from the left, move it to the right
          if (Math.abs(this.offset) >= this.newsWidth) {
            this.offset += this.newsWidth;
            let firstItem = this.list.firstElementChild;
            this.list.appendChild(firstItem);
          }
        }
        else if (this.cfg.direction === 'ltr') {
          // Get position to the right
          this.offset += Number.parseFloat(this.cfg.speed);
          // If last element disapear from the right, move it to the left
          if (this.offset >= 0) {
            let lastItem = this.list.lastElementChild;
            this.list.prepend(lastItem);
            this.offset -= this.newsWidth; // Ajustamos el offset para evitar el espacio en blanco
          }
        }
        // Animate to the offset position
        this.list.style.transform = `translateX(${this.offset}px)`;
        requestAnimationFrame(() => { this.animate(); });
      }
    }

  }

  Drupal.behaviors.easyCarouselBrands = {
    attach: function (context, settings) {
      once('easyCarouselBrandsInit', '.easy-carousel--brands', context).forEach(function (element) {
        const app = new BrandsCarousel(element);
        app.animate();
      });
    }
  };

  // Attach behaviors to the document.
  // This is necessary to ensure that the behaviors are applied to the
  // elements that are added dynamically to the DOM.
  // This is a workaround for the fact that Drupal.behaviors are not
  // automatically applied to dynamically added elements.
  // See https://www.drupal.org/node/2641234
  // This line is necesary for example when you are in an Incognito tab.
  // In incognito mode, some resources (like cookies, caches and other files)
  // are loaded in a diferent way that affect to the javascript behavior.
  Drupal.attachBehaviors(document);

})(Drupal, drupalSettings, once);

