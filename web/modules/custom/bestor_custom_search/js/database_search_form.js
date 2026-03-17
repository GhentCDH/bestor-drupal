(function ($, Drupal, once) {
  Drupal.behaviors.databaseSearchPrefill = {
    attach: function (context, settings) {
      const params = new URLSearchParams(window.location.search);
      const val = params.get('fullsearch') || '';

      once('search-prefill', '[name="fullsearch"]', context).forEach(function (el) {
        el.value = val;
      });
    }
  };
})(jQuery, Drupal, once);