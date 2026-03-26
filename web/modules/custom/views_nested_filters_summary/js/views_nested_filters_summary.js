(function ($, Drupal, once) {
  'use strict';

  console.log('=== nestedFiltersSummary DEBUG LOADED ===');

  // GLOBAL AJAX DEBUG
  $(document).ajaxSend((e, xhr, settings) => {
    console.log('>>> AJAX SEND:', settings.url);
  });

  $(document).ajaxComplete((e, xhr, settings) => {
    console.log('<<< AJAX COMPLETE:', settings.url);
  });

  // Isolate BEF auto_submit crashes
  Drupal.behaviors.nestedFiltersBefSafetyNet = {
    attach() {
      if (!Drupal.behaviors.autoSubmit?.attach) return;
      if (Drupal.behaviors.autoSubmit._safetyPatched) return;
      const orig = Drupal.behaviors.autoSubmit.attach.bind(Drupal.behaviors.autoSubmit);
      Drupal.behaviors.autoSubmit.attach = function(ctx, settings) {
        try { orig(ctx, settings); } catch(e) { console.warn('[BEF crash isolated]', e); }
      };
      Drupal.behaviors.autoSubmit._safetyPatched = true;
    }
  };

  // Remove 'disabled' class
  Drupal.behaviors.nestedFiltersSummaryEnable = {
    attach() {
      console.log('[Enable] attach');
      document.querySelectorAll(
        '.views-filters-summary a.remove-filter.disabled, .views-filters-summary a.reset.disabled'
      ).forEach(link => {
        console.log('[Enable] removing disabled from', link);
        link.classList.remove('disabled');
      });
    }
  };

  // Safety net click
  Drupal.behaviors.nestedFiltersSummarySafetyNet = {
    attach(context) {
      once('nested-summary-safety', 'body', context).forEach(() => {
        console.log('[SafetyNet] attach');
        document.addEventListener('click', e => {
          const link = e.target.closest(
            '.views-filters-summary a.remove-filter, .views-filters-summary a.reset'
          );
          if (!link) return;
          console.log('--- CLICK DETECTED ---');
          console.log('link:', link);
          console.log('href:', link.getAttribute('href'));
          console.log('data-remove-selector:', link.getAttribute('data-remove-selector'));
          e.preventDefault();
        }, true);
      });
    }
  };

  // Reattach behavior after AJAX
  Drupal.behaviors.nestedFiltersSummaryReattach = {
    attach(context) {
      once('nested-summary-reattach', 'body', context).forEach(() => {
        console.log('[Reattach] attach');
        $(document).on('ajaxComplete', () => {
          console.log('[Reattach] ajaxComplete → reattaching');
          document.querySelectorAll('.views-filters-summary').forEach(el => {
            el.removeAttribute('data-once');
          });
          if (Drupal.behaviors.viewsFiltersSummary?.attach) {
            console.log('[Reattach] calling contrib attach');
            Drupal.behaviors.viewsFiltersSummary.attach(document, drupalSettings);
          } else {
            console.warn('[Reattach] contrib behavior missing!');
          }
        });
      });
    }
  };

  // Patches
  Drupal.behaviors.nestedFiltersSummaryPatches = {
    attach(context) {
      once('nested-summary-patches', 'body', context).forEach(() => {
        console.log('[Patches] attach');

        if (!Drupal.ViewsFiltersSummaryHandler) {
          console.warn('[Patches] Handler not found!');
          return;
        }

        // Override addEventListeners to apply our fixes before binding
        Drupal.ViewsFiltersSummaryHandler.prototype.addEventListeners = function (element) {
          const self = this;

          self.onRemoveClick = function (event) {
            event.preventDefault();
            const removeSelector = event.currentTarget.getAttribute('data-remove-selector');
            if (!removeSelector) return;

            const colonIndex = removeSelector.indexOf(':');
            const selector = removeSelector.substring(0, colonIndex);
            const value = removeSelector.substring(colonIndex + 1);

            console.log('=== onRemoveClick START ===');
            console.log('removeSelector:', removeSelector);
            console.log('selector:', selector, 'value:', value);

            const exposedForm = self.getExposedForm();
            if (!exposedForm) {
              console.warn('No exposed form!');
              return;
            }

            const inputs = self.getFormElementsByName(exposedForm, selector);
            console.log('inputs found in form:', inputs.length, inputs);

            // Facet is URL-only (no form input)
            if (!inputs.length) {
              const url = new URL(window.location.href);
              const toDelete = [];

              url.searchParams.forEach((paramValue, key) => {
                // Only remove exact nested key/value matches: e.g., startchar[A]
                const match = key.match(/^(.+)\[(.+)\]$/);
                if (match) {
                  const paramName = match[1];
                  const paramKey = match[2];
                  if (paramName === selector && paramKey === value) {
                    console.log('→ MATCH (nested) for removal:', key);
                    toDelete.push(key);
                  }
                }
              });

              console.log('[Facet removal] deleting:', toDelete);
              toDelete.forEach(key => url.searchParams.delete(key));
              console.log('[Facet removal] NEW URL:', url.toString());

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

            console.log('[onRemoveClick] reset inputs count:', resetInputs.size);

            if (resetInputs.size > 0) self.submit(exposedForm);
          };

          self.onResetClick = function (event) {
            event.preventDefault();
            const exposedForm = self.getExposedForm();
            const filterIds = (event.currentTarget.getAttribute('data-filter-ids') || '')
              .split(',').map(s => s.trim()).filter(Boolean);
            self.reset(exposedForm, filterIds);
            self.submit(exposedForm);
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

        // Force reattach
        document.querySelectorAll('.views-filters-summary').forEach(el => {
          el.removeAttribute('data-once');
        });
        Drupal.behaviors.viewsFiltersSummary.attach(document, drupalSettings);

        // DEBUG getExposedForm
        const origGetForm = Drupal.ViewsFiltersSummaryHandler.prototype.getExposedForm;
        Drupal.ViewsFiltersSummaryHandler.prototype.getExposedForm = function () {
          const form = origGetForm.call(this);
          console.log('[getExposedForm]', form);
          return form;
        };

        // DEBUG getFormElementsByName
        const origGetInputs = Drupal.ViewsFiltersSummaryHandler.prototype.getFormElementsByName;
        Drupal.ViewsFiltersSummaryHandler.prototype.getFormElementsByName = function (form, name) {
          const result = origGetInputs.call(this, form, name);
          console.log('[getFormElementsByName]', name, '→', result.length, result);
          return result;
        };

        // DEBUG submit
        const origSubmit = Drupal.ViewsFiltersSummaryHandler.prototype.submit;
        Drupal.ViewsFiltersSummaryHandler.prototype.submit = function (form) {
          console.log('[SUBMIT] form:', form);
          return origSubmit.call(this, form);
        };

        // reset
        const origReset = Drupal.ViewsFiltersSummaryHandler.prototype.reset;
        Drupal.ViewsFiltersSummaryHandler.prototype.reset = function (exposedForm, filterIds) {
          console.log('[RESET]', filterIds);
          origReset.call(this, exposedForm, filterIds);
          if (!filterIds || !filterIds.length) return;
          filterIds.forEach(id => {
            exposedForm
              .querySelectorAll(`select[name^="${id}["], input[name^="${id}["]`)
              .forEach(el => {
                if (el.tagName === 'SELECT') el.selectedIndex = -1;
                else el.value = '';
              });
          });
        };

        // getFilterSubmit
        const origGetSubmit = Drupal.ViewsFiltersSummaryHandler.prototype.getFilterSubmit;
        Drupal.ViewsFiltersSummaryHandler.prototype.getFilterSubmit = function (exposedForm) {
          const btn = exposedForm.querySelector('[data-bef-auto-submit-click]')
            || origGetSubmit.call(this, exposedForm);
          console.log('[getFilterSubmit]', btn);
          return btn;
        };
      });
    }
  };

}(jQuery, Drupal, once));