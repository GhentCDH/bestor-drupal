((Drupal, once) => {
  /**
   * Toggle dropdown.
   *
   * @param _$trigger
   */
  function toggleDropdown(_$trigger) {
    _$trigger.parentElement.classList.toggle('is-open');
  }

  /**
   * Initialize dropdown.
   *
   * @param props
   */
  function init(props) {
    props._$triggers.forEach(_$trigger => {
      _$trigger.addEventListener('click', () => {
        toggleDropdown(_$trigger);
      });
    });
  }

  Drupal.behaviors.dropdown = {
    attach(context) {
      const _$triggers = once('trigger', '[data-dropdown-toggle]', context)

      // If there are triggers.
      if (_$triggers) {
        init({_$triggers});
      }
    }
  };
})(Drupal, once);