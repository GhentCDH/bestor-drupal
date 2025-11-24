// components/molecules/c-dropdown/src/c-dropdown.js
((Drupal2, once2) => {
  function toggleDropdown(_$trigger) {
    _$trigger.parentElement.classList.toggle("is-open");
  }
  function init(props) {
    props._$triggers.forEach((_$trigger) => {
      _$trigger.addEventListener("click", () => {
        toggleDropdown(_$trigger);
      });
    });
  }
  Drupal2.behaviors.dropdown = {
    attach(context) {
      const _$triggers = once2("trigger", "[data-dropdown-toggle]", context);
      if (_$triggers) {
        init({ _$triggers });
      }
    }
  };
})(Drupal, once);
//# sourceMappingURL=c-dropdown.js.map
