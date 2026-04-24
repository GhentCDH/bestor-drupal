(function (Drupal, once) {
  'use strict';

  const TAB_KEY      = 'database_search_active_tab';
  const SCROLL_KEY   = 'database_search_scroll';
  const SECTIONS_KEY = 'bestor.filters.sections';

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  function storageGet(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); }
    catch { return null; }
  }

  function storageSet(key, value) {
    try { sessionStorage.setItem(key, JSON.stringify(value)); }
    catch { /* quota exceeded, ignore */ }
  }

  // ---------------------------------------------------------------------------
  // 1. Accordion: persist open/closed state of filter sections across submits
  // ---------------------------------------------------------------------------

  Drupal.behaviors.databaseFiltersAccordion = {
    attach(context) {
      once('filters-accordion', '.database-filters', context).forEach(wrapper => {
        const sections = wrapper.querySelectorAll('details.database-filters-section[id]');

        // Restore saved state only if it was saved on the same page path.
        const saved = storageGet(SECTIONS_KEY) || {};
        if (saved._path === window.location.pathname) {
          sections.forEach(el => {
            if (el.id in saved) el.open = saved[el.id];
          });
        }

        // Update badges after state is restored.
        updateAllBadges(wrapper);

        // Persist state on every toggle, including the current page path.
        sections.forEach(el => {
          el.addEventListener('toggle', () => {
            const state = storageGet(SECTIONS_KEY) || {};
            state._path = window.location.pathname;
            state[el.id] = el.open;
            storageSet(SECTIONS_KEY, state);
          });
        });

        // Save state just before submit so it survives a full page reload.
        const form = wrapper.closest('form');
        if (form) {
          form.addEventListener('submit', () => {
            const state = { _path: window.location.pathname };
            sections.forEach(el => { state[el.id] = el.open; });
            storageSet(SECTIONS_KEY, state);
          });
        }
      });
    }
  };

  // ---------------------------------------------------------------------------
  // 2. Relation tabs: switch active tab and persist selection
  // ---------------------------------------------------------------------------

  Drupal.behaviors.databaseFiltersRelTabs = {
    attach(context) {
      const tabs = once('filters-rel-tabs', '.database-filters-rel-tab', context);
      if (!tabs.length) return;

      // Restore saved tab on page load.
      const saved = storageGet(TAB_KEY);
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
          const panel = layout.querySelector(`#${target}`);
          if (panel) panel.classList.add('is-active');
          storageSet(TAB_KEY, target);
        });
      });
    }
  };

  // ---------------------------------------------------------------------------
  // 3. Scroll preservation on non-AJAX form submit
  //    (AJAX scroll is already disabled via disableViewsScrollTop in database.js)
  // ---------------------------------------------------------------------------

  Drupal.behaviors.databaseFiltersScrollPreservation = {
    attach(context) {
      once('filters-scroll', 'form[id^="views-exposed-form"]', context).forEach(form => {
        // Restore scroll position after a full page reload triggered by submit.
        const saved = storageGet(SCROLL_KEY);
        if (saved !== null) {
          window.scrollTo(0, saved);
          sessionStorage.removeItem(SCROLL_KEY);
        }

        form.addEventListener('submit', () => {
          storageSet(SCROLL_KEY, window.scrollY);
        });
      });
    }
  };

  // ---------------------------------------------------------------------------
  // 4. Active filter badges — grey by default, primary colour when active
  // ---------------------------------------------------------------------------

  Drupal.behaviors.databaseFiltersBadges = {
    attach(context) {
      once('filters-badges', '.database-filters', context).forEach(wrapper => {
        updateAllBadges(wrapper);

        // Re-evaluate on any input change within the filter form.
        wrapper.addEventListener('change', () => updateAllBadges(wrapper));
        wrapper.addEventListener('input',  () => updateAllBadges(wrapper));
      });
    }
  };

  function countActiveInputs(section) {
    let count = 0;
    section.querySelectorAll('input, select, textarea').forEach(el => {
      if (el.type === 'hidden') return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (el.checked) count++;
      } else if (el.tagName === 'SELECT') {
        // Ignore "- Any -" style default options (value '' or 'All').
        const val = el.value;
        if (val !== '' && val !== 'All' && val !== 'no_fallback') count++;
      } else {
        if (el.value.trim() !== '') count++;
      }
    });
    return count;
  }

  function updateAllBadges(wrapper) {
    // Top-level accordion secties.
    wrapper.querySelectorAll('details.database-filters-section[id]').forEach(section => {
      const badge = section.querySelector(':scope > summary .filter-badge');
      if (!badge) return;
      badge.classList.toggle('is-active', countActiveInputs(section) > 0);
    });

    // Relatie sub-tabs.
    wrapper.querySelectorAll('.database-filters-rel-tab').forEach(tab => {
      const badge = tab.querySelector('.filter-badge');
      if (!badge) return;
      const panel = document.getElementById(tab.dataset.target);
      if (!panel) return;
      badge.classList.toggle('is-active', countActiveInputs(panel) > 0);
    });
  }


  // ---------------------------------------------------------------------------
  // 5. Active class in advanced search menu
  // ---------------------------------------------------------------------------

  Drupal.behaviors.advancedSearchMenuActive = {
  attach(context) {
    once('menu-active', '.menu--advanced-search', context).forEach(menu => {
      menu.querySelectorAll('a').forEach(link => {
        if (link.pathname === window.location.pathname) {
          link.classList.add('is-active');
        }
      });
    });
  }
};

}(Drupal, once));