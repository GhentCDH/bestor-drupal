(function ($, Drupal, once) {
  Drupal.behaviors.mirrorChangeConfirm = {
    attach: function (context, settings) {
      once('relationship-nodes-modal-save', '#relationship-nodes-modal-save', context)
        .forEach(function (el) {
          $(el).on('click', function (e) {
            e.preventDefault();
            $('#relationship-nodes-confirm-mirror-change').val(1);
            $('#relationship-nodes-hidden-submit').trigger('click');
            $(el).closest('.ui-dialog-content').dialog('close');
          });
        });

      once('relationship-nodes-modal-cancel', '#relationship-nodes-modal-cancel', context)
        .forEach(function (el) {
          $(el).on('click', function (e) {
            e.preventDefault();
            var dialog = $(el).closest('.ui-dialog-content');
            var originalValue = $('#relationship-nodes-confirm-mirror-change').attr('data-original');
            $('#relationship-nodes-referencing-type input[type="radio"]').each(function () {
              $(this).prop('checked', $(this).val() === originalValue);
            });
            dialog.dialog('close');
          });
        });
    }
  };
})(jQuery, Drupal, once);
