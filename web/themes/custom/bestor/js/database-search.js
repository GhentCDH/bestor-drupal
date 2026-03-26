(function ($, Drupal, once) {
  'use strict';

  // Reset button: navigate to clean base URL (clears all query params).
  Drupal.behaviors.cleanResetAction = {
    attach: function (context) {
      once('clean-reset', 'input[type="reset"], button[value="Reset"]', context).forEach(function(resetBtn) {
        resetBtn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          window.location.href = window.location.pathname;
        });
      });
    }
  };

  // Highlight active BEF checkbox facets immediately (no transition delay).
  Drupal.behaviors.instantFacetHighlight = {
    attach: function (context) {
      once('instant-facet', '.bef-checkboxes input[type="checkbox"]', context).forEach(function(checkbox) {
        const formItem = checkbox.closest('.js-form-type-checkbox');
        if (!formItem) return;
        formItem.classList.toggle('highlight', checkbox.checked);
        checkbox.addEventListener('change', function() {
          formItem.classList.toggle('highlight', this.checked);
        }, true);
      });
    }
  };

  // Trigger auto-submit after Selectize widget interactions.
  Drupal.behaviors.selectifyAutoSubmit = {
    attach: function (context) {
      once('selectify-submit', '.selectify', context).forEach(function(widget) {
        const dropdown = widget.querySelector('.selectify-available-display');
        if (dropdown) {
          dropdown.addEventListener('click', function(e) {
            const option = e.target.closest('.selectify-available-one-option');
            if (option && !option.classList.contains('s-selected')) {
              setTimeout(() => $(widget.closest('form')).find('.js-form-submit').trigger('click'), 300);
            }
          });
        }
        widget.querySelectorAll('.remove-tag').forEach(function(btn) {
          btn.addEventListener('click', function() {
            setTimeout(() => $(widget.closest('form')).find('.js-form-submit').trigger('click'), 300);
          });
        });
      });
    }
  };

  // Author facet: sync hidden multiselect to/from visible dummy select.
  Drupal.behaviors.facetDummySelect = {
    attach(context) {
      once('facet-dummy', '.author-facet-dropdown', context).forEach(wrapper => {
        const dummySelect = wrapper.querySelector('.js-author-dummy-select');
        const realSelect  = wrapper.querySelector('.author-facet-hidden select');
        if (!dummySelect || !realSelect) return;

        function syncFromReal() {
          dummySelect.innerHTML = '';
          const empty = document.createElement('option');
          empty.value = '';
          dummySelect.appendChild(empty);
          Array.from(realSelect.options).forEach(opt => {
            if (!opt.value) return;
            const o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.textContent;
            o.selected = opt.selected;
            dummySelect.appendChild(o);
          });
        }

        function syncToReal() {
          const val = dummySelect.value;
          Array.from(realSelect.options).forEach(o => { o.selected = (o.value === val); });
          realSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        syncFromReal();
        dummySelect.addEventListener('change', syncToReal);
        document.addEventListener('facets_ajax_update', syncFromReal);
      });
    }
  };

  // Disable Views' built-in scroll-to-top on AJAX filter updates.
  Drupal.behaviors.disableViewsScrollTop = {
    attach: function (context) {
      once('disable-scroll-top', 'body', context).forEach(function () {
        Drupal.AjaxCommands.prototype.viewsScrollTop = function () {};
        Drupal.AjaxCommands.prototype.scrollTop    = function () {};
      });
    }
  };

  // After every AJAX rebuild, remove 'disabled' from summary links and
  // ensure the contrib behavior is re-attached on the full document.
  // Without this, the once() block prevents re-attachment after AJAX,
  // leaving links with no click handler — causing navigation to href="/".
  Drupal.behaviors.summaryLinksEnable = {
    attach() {
      document.querySelectorAll(
        '.views-filters-summary a.remove-filter.disabled, .views-filters-summary a.reset.disabled'
      ).forEach(link => link.classList.remove('disabled'));
    }
  };

  // Safety net: prevent navigation to href="/" if the contrib handler
  // failed to attach. The contrib handler calls preventDefault itself,
  // but if it's missing (e.g. after AJAX rebuild), we catch it here.
  Drupal.behaviors.summaryLinksSafetyNet = {
    attach(context) {
      once('summary-safety', 'body', context).forEach(() => {
        document.addEventListener('click', e => {
          const link = e.target.closest('.views-filters-summary a.remove-filter, .views-filters-summary a.reset');
          if (!link) return;
          // Always prevent default — the contrib handler or our own logic
          // will handle the actual filter removal via AJAX/form submit.
          // This stops the browser from following href="/".
          e.preventDefault();
        }, true); // capture phase — runs before contrib handler
      });
    }
  };

  // Patch views_filters_summary to fix two contrib bugs:
  //
  // 1. reset() regex only strips one bracket level, so nested input names
  //    like rel_institution[institution][value] are never matched.
  //
  // 2. getFilterSubmit() fails with BEF because the apply button has class
  //    js-hide (display:none), causing the visibility check to skip it and
  //    fall back to the reset button — sending reset=Reset in the request.
  Drupal.behaviors.viewsFiltersSummaryFixes = {
    attach(context) {
      once('summary-fixes', 'body', context).forEach(() => {
        if (!Drupal.ViewsFiltersSummaryHandler) return;

        // Fix 1: nested input names.
        const origReset = Drupal.ViewsFiltersSummaryHandler.prototype.reset;
        Drupal.ViewsFiltersSummaryHandler.prototype.reset = function (exposedForm, filterIds) {
          origReset.call(this, exposedForm, filterIds);
          if (!filterIds || !filterIds.length) return;
          filterIds.forEach(id => {
            exposedForm.querySelectorAll(`select[name^="${id}["], input[name^="${id}["]`).forEach(el => {
              if (el.tagName === 'SELECT') el.selectedIndex = -1;
              else el.value = '';
            });
          });
        };

        // Fix 2: prefer BEF's marked submit button.
        const origGetSubmit = Drupal.ViewsFiltersSummaryHandler.prototype.getFilterSubmit;
        Drupal.ViewsFiltersSummaryHandler.prototype.getFilterSubmit = function (exposedForm) {
          return exposedForm.querySelector('[data-bef-auto-submit-click]')
            || origGetSubmit.call(this, exposedForm);
        };
      });
    }
  };

})(jQuery, Drupal, once);