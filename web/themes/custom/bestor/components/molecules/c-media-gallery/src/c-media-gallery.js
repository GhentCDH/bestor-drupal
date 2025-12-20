((Drupal) => {
  'use strict';

  Drupal.behaviors.mediaGallery = {
    attach(context) {
      context.querySelectorAll('.c-media-gallery').forEach(gallery => {
        if (gallery.dataset.init) return;
        gallery.dataset.init = 'true';

        const galleryId = gallery.dataset.galleryId;
        const mainImage = gallery.querySelector('.c-media-gallery__main-image');
        const mainButton = gallery.querySelector('.c-media-gallery__main-button');
        const thumbs = gallery.querySelectorAll('.c-media-gallery__thumb');

        thumbs.forEach(thumb => {
          thumb.addEventListener('click', () => {
            const index = Number(thumb.dataset.thumbIndex);

            thumbs.forEach(t => t.classList.remove('c-media-gallery__thumb--active'));
            thumb.classList.add('c-media-gallery__thumb--active');

            mainImage.src = thumb.dataset.displayUrl;
            mainImage.alt = thumb.dataset.alt;
            mainButton.dataset.openLightbox = index;
          });
        });

        mainButton.addEventListener('click', () => {
          const index = Number(mainButton.dataset.openLightbox) || 0;
          const instance = window.glightboxInstances?.[galleryId];
          if (instance) {
            instance.openAt(index);
          }
        });
      });
    }
  };
})(Drupal);