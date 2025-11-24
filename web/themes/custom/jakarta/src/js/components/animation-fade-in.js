import { gsap } from "gsap";
import Animation from "./animation";

export default function AnimationFadeIn($element) {
  // Check for parameters
  if ($element == null) throw new Error("There is no element for the fade in animation");

  // Create a new animation instance, we extend from this instance
  // Pass default options if there are no data attributes for them
  let animation = new Animation($element, {
    fromDirection: "bottom",
    duration: 1,
  });

  const _setStartPositions = () => {
    // Set default start positions
    let _startOptions = {
      x: 0,
      y: 0,
      opacity: 0
    };

    // If direction is bottom
    if (animation.options.fromDirection == "bottom") {
      _startOptions.y = animation.options.distance;
    }

    // If direction is top
    if (animation.options.fromDirection == "top") {
      _startOptions.y = animation.options.distance * -1;
    }

    // If direction is left
    if (animation.options.fromDirection == "left") {
      _startOptions.x = animation.options.distance * -1;
    }

    // If direction is right
    if (animation.options.fromDirection == "right") {
      _startOptions.x = animation.options.distance;
    }

    // Set start positions
    gsap.set(animation.$element, _startOptions);
  }

  const _setupAnimation = () => {
    // GSAP animation
    animation.gsapTl.to($element, {
      x: 0,
      y: 0,
      opacity: 1,
      duration: animation.options.duration,
      delay: animation.options.delay
    });
  }

  const _init = () => {
    // Set start positions
    _setStartPositions();
    // Setup animation
    _setupAnimation();
  }

  _init();

  return animation;
}
