// components/molecules/c-language-picker/src/c-language-picker.js
((Drupal2, once2) => {
  function toggleLanguagePicker(_$trigger) {
    _$trigger.parentElement.classList.toggle("is-open");
  }
  function init(props) {
    props._$triggers.forEach((_$trigger) => {
      _$trigger.addEventListener("click", () => {
        toggleLanguagePicker(_$trigger);
      });
    });
  }
  Drupal2.behaviors.languagePicker = {
    attach(context) {
      const _$triggers = once2("trigger", "[data-language-picker-toggle]", context);
      if (_$triggers) {
        init({ _$triggers });
      }
    }
  };
})(Drupal, once);
//# sourceMappingURL=c-language-picker.js.map
