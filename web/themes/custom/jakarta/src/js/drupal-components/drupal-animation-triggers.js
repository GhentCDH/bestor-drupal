import AnimationTriggers from "../components/animation-triggers.js";

(Drupal => {
  // Initiate drupal behaviour
  Drupal.behaviors.animationTriggers = {
    attach(context) {
      // Get all the lottie triggers
      const _$triggers = context.querySelectorAll("[data-animation-trigger]:not([data-ready])");

      // If there are triggers
      if (_$triggers.length > 0) {
        new AnimationTriggers(_$triggers, 5);
      }
    }
  }
})(Drupal);
