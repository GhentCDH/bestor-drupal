(Drupal => {
  // Set variables
  let once = false;

  // Initiate drupal behaviour
  Drupal.behaviors.overlay = {
    attach(context) {
      // Check for once, stop if this isn't the first time
      if (once) return;

      // Check for the overlay triggers
      const _$triggers = context.querySelectorAll("[data-overlay-trigger]");
      // If there are triggers
      if (_$triggers.length > 0) {
        // Set once to true
        once = true;

        // Add a click handler to each trigger, add overlay open class to the body
        _$triggers.forEach(_$trigger => {
          _$trigger.addEventListener("click", (e) => {
            e.preventDefault();
            document.documentElement.classList.toggle('overlay-open');
          });
        });
      }
    }
  };
})(Drupal);
