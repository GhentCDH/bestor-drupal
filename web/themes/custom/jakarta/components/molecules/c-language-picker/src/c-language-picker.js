((Drupal, once) => {
  /**
   * Toggle language picker.
   *
   * @param _$trigger
   */
  function toggleLanguagePicker(_$trigger) {
    _$trigger.parentElement.classList.toggle('is-open');
  }

  /**
   * Initialize language picker.
   *
   * @param props
   */
  function init(props) {
    props._$triggers.forEach(_$trigger => {
      _$trigger.addEventListener('click', () => {
        toggleLanguagePicker(_$trigger);
      });
    });
  }

  Drupal.behaviors.languagePicker = {
    attach(context) {
      const _$triggers = once('trigger', '[data-language-picker-toggle]', context)

      // If there are triggers.
      if (_$triggers) {
        init({_$triggers});
      }
    }
  };
})(Drupal, once);