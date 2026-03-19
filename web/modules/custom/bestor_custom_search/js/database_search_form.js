(function ($, Drupal, once) {
  Drupal.behaviors.databaseSearchPrefill = {
    attach: function (context, settings) {
      const params = new URLSearchParams(window.location.search);
      const val = params.get('fullsearch') || '';

      once('search-prefill', '[name="fullsearch"]', context).forEach(function (el) {
        el.value = val;

        // Preserve all active facet/filter params on submit so they are not
        // wiped when the user submits the fullsearch form.
        const form = el.closest('form');
        if (!form) return;

        once('search-preserve-params', form).forEach(function (f) {
          f.addEventListener('submit', function () {
            const currentParams = new URLSearchParams(window.location.search);
            currentParams.forEach(function (value, key) {
              if (key === 'fullsearch') return;
              const hidden = document.createElement('input');
              hidden.type = 'hidden';
              hidden.name = key;
              hidden.value = value;
              f.appendChild(hidden);
            });
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);