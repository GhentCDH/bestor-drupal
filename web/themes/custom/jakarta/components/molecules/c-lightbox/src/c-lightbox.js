import GLightbox from "glightbox";

(Drupal => {
  // Set variables
  let once = false;

  // Initiate drupal behaviour
  Drupal.behaviors.lightbox = {
    attach(context) {
      // Set selector and find items
      const _selector = '[data-lightbox-custom]';
      const _$items = context.querySelectorAll(_selector);

      // If there are items
      if (_$items.length > 0) {
        once = true;
        GLightbox({
          selector: _selector
        });
      }
    }
  };
})(Drupal);
