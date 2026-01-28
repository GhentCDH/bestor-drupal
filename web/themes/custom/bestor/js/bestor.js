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

  // Popup for citing db lemmas
  Drupal.behaviors.citationPopup = {
    attach: function (context) {
      once('citation-popup', '#citation-popup', context).forEach(function(popup) {
        const toggleBtn = context.querySelector('.js-citation-toggle');
        const closeBtn = popup.querySelector('.js-citation-close');
        const copyBtn = popup.querySelector('.js-citation-copy');
        let resetTimeout = null;

        // SVG icons
        const copyIconSVG = '<svg class="citation-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
        const checkIconSVG = '<svg class="citation-icon" width="20" height="20" viewBox="2 12 28 16" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 17l5 5 12-12M16 20l2 2 12-12"></path></svg>';

        // reset
        function resetCopyIcon() {
          if (copyBtn) {
            copyBtn.innerHTML = copyIconSVG;
            copyBtn.classList.remove('is-copied');
          }
          // Clear timeout
          if (resetTimeout) {
            clearTimeout(resetTimeout);
            resetTimeout = null;
          }
        }

        // Toggle popup
        if (toggleBtn) {
          toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            popup.hidden = !popup.hidden;
            
            // Reset icon als popup gesloten wordt
            if (popup.hidden) {
              resetCopyIcon();
            }
          });
        }

        // Close popup
        if (closeBtn) {
          closeBtn.addEventListener('click', function() {
            popup.hidden = true;
            resetCopyIcon();
          });
        }

        // Copy to clipboard
        if (copyBtn) {
          copyBtn.addEventListener('click', function() {
            const text = this.getAttribute('data-citation');
            
            navigator.clipboard.writeText(text).then(() => {
              // Vervang icoon door dubbele vink
              this.innerHTML = checkIconSVG;
              this.classList.add('is-copied');
              
              // Reset na 1 minuut
              resetTimeout = setTimeout(() => {
                resetCopyIcon();
              }, 60000);
            });
          });
        }

        // Close when clicking outside
        document.addEventListener('click', function(e) {
          if (!popup.contains(e.target) && !e.target.closest('.js-citation-toggle')) {
            popup.hidden = true;
            resetCopyIcon();
          }
        });
      });
    }
  };

})(jQuery, Drupal, once);