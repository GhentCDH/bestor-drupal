(function (Drupal) {
  'use strict';

  /**
   * Handles tab switching in the relations accordion.
   *
   * Clicking a tab hides all relation forms and shows the one
   * matching the tab's data-target attribute.
   */
  Drupal.behaviors.advancedSearchFilters = {
    attach(context) {
      const tabs = context.querySelectorAll('.database-filters-rel-tab');

      if (!tabs.length) {
        return;
      }

      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          const target = tab.dataset.target;

          // Deactivate all tabs and forms within the same layout.
          const layout = tab.closest('.database-filters-rel-layout');
          layout.querySelectorAll('.database-filters-rel-tab').forEach(t => t.classList.remove('is-active'));
          layout.querySelectorAll('.database-filters-rel-form').forEach(f => f.classList.remove('is-active'));

          // Activate the clicked tab and its corresponding form.
          tab.classList.add('is-active');
          const form = layout.querySelector(`#${target}`);
          if (form) {
            form.classList.add('is-active');
          }
        });
      });
    }
  };

}(Drupal));