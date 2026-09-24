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

})(jQuery, Drupal, once);