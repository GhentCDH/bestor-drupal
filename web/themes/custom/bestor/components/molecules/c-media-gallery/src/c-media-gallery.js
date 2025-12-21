((Drupal) => {
  'use strict';

  Drupal.behaviors.mediaGallery = {
    attach(context) {
      // Only run on actual frontend pages, not in admin/modals
      if (context !== document || document.body.classList.contains('path-admin')) {
        return;
      }

      context.querySelectorAll('.c-media-gallery').forEach(gallery => {
        if (gallery.dataset.init) return;
        gallery.dataset.init = 'true';

        const galleryId = gallery.dataset.galleryId;
        const mainImage = gallery.querySelector('.c-media-gallery__main-image');
        const mainButton = gallery.querySelector('.c-media-gallery__main-button');
        const thumbs = gallery.querySelectorAll('.c-media-gallery__thumb');
        const thumbsContainer = gallery.querySelector('.c-media-gallery__thumbs');
        const prevBtn = gallery.querySelector('.c-media-gallery__scroll-btn--prev');
        const nextBtn = gallery.querySelector('.c-media-gallery__scroll-btn--next');

        if (!mainImage) return;

        // Thumbnail click events
        thumbs.forEach(thumb => {
          thumb.addEventListener('click', () => {
            const index = Number(thumb.dataset.thumbIndex);

            thumbs.forEach(t => t.classList.remove('c-media-gallery__thumb--active'));
            thumb.classList.add('c-media-gallery__thumb--active');

            mainImage.src = thumb.dataset.displayUrl;
            mainImage.alt = thumb.dataset.alt;
            
            if (mainButton) {
              mainButton.dataset.openLightbox = index;
            }
          });
        });
        
        // Lightbox click event
        if (mainButton) {
          mainButton.addEventListener('click', () => {
            const index = Number(mainButton.dataset.openLightbox) || 0;
            const instance = window.glightboxInstances?.[galleryId];
            if (instance) {
              instance.openAt(index);
            }
          });
        }

        // Scroll button functionality
        if (thumbsContainer && prevBtn && nextBtn) {
          const scrollAmount = 200;

          const updateButtonVisibility = () => {
            const isAtStart = thumbsContainer.scrollLeft <= 0;
            const isAtEnd = thumbsContainer.scrollLeft + thumbsContainer.clientWidth >= thumbsContainer.scrollWidth - 1;
            
            prevBtn.classList.toggle('hidden', isAtStart);
            nextBtn.classList.toggle('hidden', isAtEnd);
          };

          prevBtn.addEventListener('click', () => {
            thumbsContainer.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
          });

          nextBtn.addEventListener('click', () => {
            thumbsContainer.scrollBy({ left: scrollAmount, behavior: 'smooth' });
          });

          thumbsContainer.addEventListener('scroll', updateButtonVisibility);
          
          // Initial check
          updateButtonVisibility();
          
          // Check after images load
          setTimeout(updateButtonVisibility, 100);
        }
      });
    }
  };
})(Drupal);