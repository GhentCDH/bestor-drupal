(function ($, Drupal, once) {
  'use strict';

  // FIX: Reset form action to clean URL before submit
  Drupal.behaviors.cleanResetAction = {
    attach: function (context) {
      once('clean-reset', 'input[type="reset"], button[value="Reset"]', context).forEach(function(resetBtn) {
        resetBtn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const form = this.closest('form');
          const basePath = window.location.pathname;
          
          // Clear all form elements
          form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
            const formItem = cb.closest('.js-form-type-checkbox');
            if (formItem) formItem.classList.remove('highlight');
          });
          
          form.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
          form.querySelectorAll('.bef-link--selected').forEach(link => link.classList.remove('bef-link--selected'));
          form.querySelectorAll('.bef-links input[type="hidden"]').forEach(hidden => hidden.remove());
          form.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
          
          window.location.href = basePath;
        });
      });
    }
  };


  //FIX: set and remove highlight class on facets (without transition)
  Drupal.behaviors.instantFacetHighlight = {
    attach: function (context) {
      const checkboxes = once('instant-facet', '.bef-checkboxes input[type="checkbox"]', context);
      
      checkboxes.forEach(function(checkbox) {
        const formItem = checkbox.closest('.js-form-type-checkbox');
        
        if (checkbox.checked) {
          formItem.classList.add('highlight');
        } else {
          formItem.classList.remove('highlight');
        }
        
        checkbox.addEventListener('change', function() {
          if (this.checked) {
            formItem.classList.add('highlight');
          } else {
            formItem.classList.remove('highlight');
          }
        }, true);
      });
    }
  };


  // FIX: Selectify auto-submit
  Drupal.behaviors.selectifyAutoSubmit = {
    attach: function (context) {
      once('selectify-submit', '.selectify', context).forEach(function(widget) {
        
        const dropdown = widget.querySelector('.selectify-available-display');
        if (dropdown) {
          dropdown.addEventListener('click', function(e) {
            const option = e.target.closest('.selectify-available-one-option');
            if (option && !option.classList.contains('s-selected')) {
              setTimeout(() => {
                const form = widget.closest('form');
                if (form) {
                  $(form).find('.js-form-submit').trigger('click');
                }
              }, 300);
            }
          });
        }
        
        widget.querySelectorAll('.remove-tag').forEach(function(btn) {
          btn.addEventListener('click', function() {
            setTimeout(() => {
              const form = widget.closest('form');
              if (form) {
                $(form).find('.js-form-submit').trigger('click');
              }
            }, 300);
          });
        });
      });
    }
  };


  // FEAT: Visually replace multiselect by styled dropdown.
  Drupal.behaviors.facetDummySelect = {
    attach(context) {
      once('facet-dummy', '.author-facet-dropdown', context).forEach(wrapper => {

        const dummySelect = wrapper.querySelector('.js-author-dummy-select');
        const realSelect = wrapper.querySelector('.author-facet-hidden select');

        if (!dummySelect || !realSelect) {
          console.warn('Facet dummy select: missing elements', {
            dummySelect,
            realSelect
          });
          return;
        }

        function syncFromReal() {
          dummySelect.innerHTML = '';

          // Empty option (optioneel)
          const empty = document.createElement('option');
          empty.value = '';
          empty.textContent = '';
          dummySelect.appendChild(empty);

          Array.from(realSelect.options).forEach(option => {
            if (!option.value) return;

            const o = document.createElement('option');
            o.value = option.value;
            o.textContent = option.textContent;
            o.selected = option.selected;

            dummySelect.appendChild(o);
          });
        }

        function syncToReal() {
          const value = dummySelect.value;

          Array.from(realSelect.options).forEach(o => {
            o.selected = (o.value === value);
          });

          // Facets AJAX trigger
          realSelect.dispatchEvent(
            new Event('change', { bubbles: true })
          );
        }

        // Init
        syncFromReal();

        // User interaction
        dummySelect.addEventListener('change', syncToReal);

        // Facets AJAX refresh
        document.addEventListener('facets_ajax_update', syncFromReal);
      });
    }
  };

})(jQuery, Drupal, once);