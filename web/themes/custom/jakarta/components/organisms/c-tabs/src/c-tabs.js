((Drupal, once) => {
  /**
   * Toggle tab.
   *
   * @param _$trigger
   */
  function toggleTab(_$trigger) {
    const _$wrapper = _$trigger.closest('[data-tabs]');
    if (!_$wrapper) return;

    const _$tabs = _$wrapper.querySelectorAll('[data-tabs-toggle]');
    const _$panels = _$wrapper.querySelectorAll('[data-tabs-panel]');
    const _id = _$trigger.getAttribute('data-tabs-toggle');

    _$tabs.forEach(t => t.classList.remove('is-active'));
    _$panels.forEach(p => p.classList.remove('is-active'));

    _$trigger.classList.add('is-active');

    const _activePanel = _$wrapper.querySelector(`[data-tabs-panel="${_id}"]`);
    if (_activePanel) {
      _activePanel.classList.add('is-active');
    }
  }

  /**
   * Initialize tab.
   *
   * @param props
   */
  function init(props) {
    props._$triggers.forEach(_$trigger => {
      _$trigger.addEventListener('click', () => {
        toggleTab(_$trigger);
      });
    });
  }

  Drupal.behaviors.tabs = {
    attach(context) {
      const _$triggers = once('trigger', '[data-tabs-toggle]', context)

      // If there are triggers.
      if (_$triggers.length !== 0) {
        init({_$triggers});
      }
    }
  };
})(Drupal, once);