((Drupal) => {
  'use strict';

  Drupal.behaviors.bestorLightbox = {
    attach(context) {
      if (typeof GLightbox === 'undefined') return;

      window.glightboxInstances = window.glightboxInstances || {};

      context.querySelectorAll('[data-gallery]').forEach(link => {
        const galleryId = link.dataset.gallery;
        if (!galleryId || window.glightboxInstances[galleryId]) return;

        window.glightboxInstances[galleryId] = GLightbox({
          selector: `[data-lightbox-custom][data-gallery="${galleryId}"]`,
          loop: true,
          touchNavigation: true
        });
      });
    }
  };
})(Drupal);