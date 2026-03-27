(function ($, Drupal, once) {
  'use strict';

  // Isolate BEF auto_submit crashes after AJAX reattach
  Drupal.behaviors.nestedFiltersBefSafetyNet = {
    attach() {
      if (!Drupal.behaviors.autoSubmit?.attach) return;
      if (Drupal.behaviors.autoSubmit._safetyPatched) return;
      const orig = Drupal.behaviors.autoSubmit.attach.bind(Drupal.behaviors.autoSubmit);
      Drupal.behaviors.autoSubmit.attach = function (ctx, settings) {
        try { orig(ctx, settings); } catch (e) { /* swallow BEF crash */ }
      };
      Drupal.behaviors.autoSubmit._safetyPatched = true;
    }
  };

  // Reattach contrib behavior after every AJAX response
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

  // Patch ViewsFiltersSummaryHandler
  Drupal.behaviors.nestedFiltersSummaryPatches = {
    attach(context) {
      once('nested-summary-patches', 'body', context).forEach(() => {
        if (!Drupal.ViewsFiltersSummaryHandler) return;

        Drupal.ViewsFiltersSummaryHandler.prototype.addEventListeners = function (element) {
          const self = this;

          self.onRemoveClick = function (event) {
            event.preventDefault();
            const removeSelector = event.currentTarget.getAttribute('data-remove-selector');
            if (!removeSelector) return;

            const colonIndex = removeSelector.indexOf(':');
            const selector = removeSelector.substring(0, colonIndex);
            const value    = removeSelector.substring(colonIndex + 1);

            const exposedForm = self.getExposedForm();
            if (!exposedForm) return;

            const inputs = self.getFormElementsByName(exposedForm, selector);

            // Facet filter: URL-only, no form input present
            if (!inputs.length) {
              const url = new URL(window.location.href);
              const toDelete = [];

              url.searchParams.forEach((paramValue, key) => {
                const match = key.match(/^(.+)\[(.+)\]$/);
                if (match && match[1] === selector && match[2] === value) {
                  toDelete.push(key);
                }
              });

              toDelete.forEach(key => url.searchParams.delete(key));
              window.location.href = url.toString();
              return;
            }

            // Standard form-based filter: reset inputs and submit
            const resetInputs = new Set();
            inputs.forEach(input => {
              if (input.tagName === 'SELECT' && !input.multiple) {
                input.selectedIndex = -1;
                resetInputs.add(input);
              } else if (input.type === 'checkbox' || input.type === 'radio') {
                if (input.value === value) { input.checked = false; resetInputs.add(input); }
              } else if (input.type === 'hidden' && input.value === value) {
                input.remove(); resetInputs.add(input);
              } else {
                input.value = ''; resetInputs.add(input);
              }
            });

            if (resetInputs.size > 0) self.submit(exposedForm);
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

        // Ensure nested selects/inputs are fully cleared on reset
        const origReset = Drupal.ViewsFiltersSummaryHandler.prototype.reset;
        Drupal.ViewsFiltersSummaryHandler.prototype.reset = function (exposedForm, filterIds) {
          origReset.call(this, exposedForm, filterIds);
          if (!filterIds?.length) return;
          filterIds.forEach(id => {
            exposedForm
              .querySelectorAll(`select[name^="${id}["], input[name^="${id}["]`)
              .forEach(el => {
                if (el.tagName === 'SELECT') el.selectedIndex = -1;
                else el.value = '';
              });
          });
        };

        // Prefer BEF auto-submit button over default submit detection
        const origGetSubmit = Drupal.ViewsFiltersSummaryHandler.prototype.getFilterSubmit;
        Drupal.ViewsFiltersSummaryHandler.prototype.getFilterSubmit = function (exposedForm) {
          return exposedForm.querySelector('[data-bef-auto-submit-click]')
            || origGetSubmit.call(this, exposedForm);
        };

        // Force initial attach with patched prototype
        document.querySelectorAll('.views-filters-summary').forEach(el => {
          el.removeAttribute('data-once');
        });
        Drupal.behaviors.viewsFiltersSummary.attach(document, drupalSettings);
      });
    }
  };

}(jQuery, Drupal, once));