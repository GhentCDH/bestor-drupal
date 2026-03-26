(function (Drupal, once) {
  'use strict';

  const TAB_KEY    = 'database_search_active_tab';
  const SCROLL_KEY = 'database_search_scroll';

  // ---------------------------------------------------------------------------
  // 1. Tab switching
  //    (already handles is-active toggling; this adds sessionStorage persistence)
  // ---------------------------------------------------------------------------

  Drupal.behaviors.advancedSearchFilters = {
    attach(context) {
      const tabs = once('advanced-search-tabs', '.database-filters-rel-tab', context);
      if (!tabs.length) return;

      // Restore saved tab on page load.
      const saved = sessionStorage.getItem(TAB_KEY);
      if (saved) {
        const layout = document.querySelector('.database-filters-rel-layout');
        if (layout) {
          layout.querySelectorAll('.database-filters-rel-tab').forEach(t =>
            t.classList.toggle('is-active', t.dataset.target === saved)
          );
          layout.querySelectorAll('.database-filters-rel-form').forEach(f =>
            f.classList.toggle('is-active', f.id === saved)
          );
        }
      }

      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          const target = tab.dataset.target;
          const layout = tab.closest('.database-filters-rel-layout');
          layout.querySelectorAll('.database-filters-rel-tab').forEach(t => t.classList.remove('is-active'));
          layout.querySelectorAll('.database-filters-rel-form').forEach(f => f.classList.remove('is-active'));
          tab.classList.add('is-active');
          const form = layout.querySelector(`#${target}`);
          if (form) form.classList.add('is-active');
          sessionStorage.setItem(TAB_KEY, target);
        });
      });
    }
  };

  // ---------------------------------------------------------------------------
  // 2. Scroll preservation on non-AJAX form submit
  //    (AJAX scroll is already disabled via disableViewsScrollTop in database.js)
  // ---------------------------------------------------------------------------

  Drupal.behaviors.advancedSearchScrollPreservation = {
    attach(context) {
      once('scroll-preservation', 'form[id^="views-exposed-form"]', context).forEach(form => {
        // Restore scroll after a non-AJAX submit reloaded the page.
        const saved = sessionStorage.getItem(SCROLL_KEY);
        if (saved !== null) {
          window.scrollTo(0, parseInt(saved, 10));
          sessionStorage.removeItem(SCROLL_KEY);
        }

        form.addEventListener('submit', () => {
          sessionStorage.setItem(SCROLL_KEY, window.scrollY);
        });
      });
    }
  };


}(Drupal, once));