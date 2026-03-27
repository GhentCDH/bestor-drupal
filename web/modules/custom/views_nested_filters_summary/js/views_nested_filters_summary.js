(function ($, Drupal, once) {
  'use strict';

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Parse a data-remove-selector value into { selector, value }.
   * Convention: "fieldname:value" where only the FIRST colon is the delimiter.
   * Values may themselves contain colons (e.g. URIs, date ranges).
   */
  function parseRemoveSelector(raw) {
    const colonIndex = raw.indexOf(':');
    if (colonIndex === -1) return null;
    return {
      selector: raw.substring(0, colonIndex),
      value:    raw.substring(colonIndex + 1),
    };
  }

  /**
   * Remove a facet parameter from a URL object.
   * Handles both Drupal Facets styles:
   *   - f[0]=fieldname:value  (standard Facets URL processor)
   *   - fieldname[value]=1    (Query string URL processor / startchar widget)
   *
   * @param {URL}    url
   * @param {string} selector  Field/facet name
   * @param {string} value     Facet value
   * @return {boolean} Whether anything was removed
   */
  function removeFacetFromUrl(url, selector, value) {
    const toDelete = [];

    url.searchParams.forEach((paramValue, key) => {
      // Style 1: f[N]=selector:value
      if (/^f\[\d+\]$/.test(key) && paramValue === `${selector}:${value}`) {
        toDelete.push({ key, value: null });
        return;
      }
      // Style 2: selector[value]=* (e.g. startchar[A]=1)
      const match = key.match(/^(.+)\[(.+)\]$/);
      if (match && match[1] === selector && match[2] === value) {
        toDelete.push({ key, value: null });
      }
    });

    toDelete.forEach(({ key }) => url.searchParams.delete(key));

    // Style 1 leaves gaps in f[N] numbering — re-index to avoid Drupal ignoring them
    if (toDelete.some(({ key }) => /^f\[\d+\]$/.test(key))) {
      const remaining = [];
      url.searchParams.forEach((v, k) => {
        if (/^f\[\d+\]$/.test(k)) remaining.push(v);
      });
      // Remove all f[N] first, then re-add in order
      [...url.searchParams.keys()]
        .filter(k => /^f\[\d+\]$/.test(k))
        .forEach(k => url.searchParams.delete(k));
      remaining.forEach((v, i) => url.searchParams.append(`f[${i}]`, v));
    }

    return toDelete.length > 0;
  }

  /**
   * Reset a single form input to its empty/unchecked state.
   * Returns true if the input was modified.
   *
   * @param {HTMLElement} input
   * @param {string}      value  Expected value (for checkbox/radio/hidden matching)
   */
  function resetInput(input, value) {
    if (input.tagName === 'SELECT') {
      if (input.multiple) {
        // Deselect only the matching option
        Array.from(input.options).forEach(opt => {
          if (opt.value === value) opt.selected = false;
        });
      } else {
        input.selectedIndex = -1;
      }
      return true;
    }
    if (input.type === 'checkbox' || input.type === 'radio') {
      if (input.value === value) { input.checked = false; return true; }
      return false;
    }
    if (input.type === 'hidden') {
      if (input.value === value) { input.remove(); return true; }
      return false;
    }
    // text, search, number, date, …
    input.value = '';
    return true;
  }

  // ---------------------------------------------------------------------------
  // Behavior: isolate BEF auto_submit crashes after AJAX reattach
  // ---------------------------------------------------------------------------
  Drupal.behaviors.nestedFiltersBefSafetyNet = {
    attach() {
      if (!Drupal.behaviors.autoSubmit?.attach) return;
      if (Drupal.behaviors.autoSubmit._safetyPatched) return;
      const orig = Drupal.behaviors.autoSubmit.attach.bind(Drupal.behaviors.autoSubmit);
      Drupal.behaviors.autoSubmit.attach = function (ctx, settings) {
        try { orig(ctx, settings); } catch (e) { /* swallow BEF crash on AJAX reattach */ }
      };
      Drupal.behaviors.autoSubmit._safetyPatched = true;
    }
  };

  // ---------------------------------------------------------------------------
  // Behavior: reattach contrib behavior after every AJAX response
  // ---------------------------------------------------------------------------
  Drupal.behaviors.nestedFiltersSummaryReattach = {
    attach(context) {
      once('nested-summary-reattach', 'body', context).forEach(() => {
        $(document).on('ajaxComplete', () => {
          document.querySelectorAll('.views-filters-summary').forEach(el => {
            el.removeAttribute('data-once');
          });
          Drupal.behaviors.viewsFiltersSummary?.attach(document, drupalSettings);
        });
      });
    }
  };

  // ---------------------------------------------------------------------------
  // Behavior: patch ViewsFiltersSummaryHandler prototype
  // ---------------------------------------------------------------------------
  Drupal.behaviors.nestedFiltersSummaryPatches = {
    attach(context) {
      once('nested-summary-patches', 'body', context).forEach(() => {
        if (!Drupal.ViewsFiltersSummaryHandler) return;

        const proto = Drupal.ViewsFiltersSummaryHandler.prototype;

        // --- addEventListeners --------------------------------------------------
        // Full override needed: contrib marks links disabled and wires broken
        // handlers for facet-URL and nested-bracket fields.
        proto.addEventListeners = function (element) {
          const self = this;

          self.onRemoveClick = function (event) {
            event.preventDefault();
            const raw = event.currentTarget.getAttribute('data-remove-selector');
            if (!raw) return;

            const parsed = parseRemoveSelector(raw);
            if (!parsed) return;
            const { selector, value } = parsed;

            const exposedForm = self.getExposedForm();
            if (!exposedForm) return;

            const inputs = self.getFormElementsByName(exposedForm, selector);

            // No form input → facet lives only in the URL
            if (!inputs.length) {
              const url = new URL(window.location.href);
              removeFacetFromUrl(url, selector, value);
              window.location.href = url.toString();
              return;
            }

            // Form-based filter: reset matching inputs, then submit
            let changed = false;
            inputs.forEach(input => {
              if (resetInput(input, value)) changed = true;
            });
            if (changed) self.submit(exposedForm);
          };

          self.onResetClick = function (event) {
            event.preventDefault();
            window.location.href = window.location.pathname;
          };

          self.onRemoveClick = self.onRemoveClick.bind(self);
          self.onResetClick  = self.onResetClick.bind(self);

          element.querySelectorAll('a.remove-filter').forEach(el => {
            el.addEventListener('click', self.onRemoveClick);
            el.classList.remove('disabled');
          });
          element.querySelectorAll('a.reset').forEach(el => {
            el.addEventListener('click', self.onResetClick);
            el.classList.remove('disabled');
          });
        };

        // --- reset --------------------------------------------------------------
        // Extend (not replace) to catch nested bracket-style field names that
        // the contrib reset misses (e.g. field_date[min], field_date[max]).
        const origReset = proto.reset;
        proto.reset = function (exposedForm, filterIds) {
          origReset.call(this, exposedForm, filterIds);
          if (!filterIds?.length) return;
          filterIds.forEach(id => {
            exposedForm
              .querySelectorAll(
                `select[name^="${id}["], input[name^="${id}["]`
              )
              .forEach(el => {
                if (el.tagName === 'SELECT') el.selectedIndex = -1;
                else el.value = '';
              });
          });
        };

        // --- getFilterSubmit ----------------------------------------------------
        // Prefer the BEF auto-submit button so AJAX is triggered correctly.
        const origGetSubmit = proto.getFilterSubmit;
        proto.getFilterSubmit = function (exposedForm) {
          return exposedForm.querySelector('[data-bef-auto-submit-click]')
            || origGetSubmit.call(this, exposedForm);
        };

        // Force reattach so the patched prototype is used immediately
        document.querySelectorAll('.views-filters-summary').forEach(el => {
          el.removeAttribute('data-once');
        });
        Drupal.behaviors.viewsFiltersSummary.attach(document, drupalSettings);
      });
    }
  };

}(jQuery, Drupal, once));