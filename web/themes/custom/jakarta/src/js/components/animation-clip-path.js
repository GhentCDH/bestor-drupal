import { gsap } from "gsap";
import Animation from "./animation";

export default function AnimationClipPath($element) {
  // Check for parameters
  if ($element == null) throw new Error("There is no element for the clip path");

  // Create a new animation instance, we extend from this instance
  // Pass default options if there are no data attributes for them
  let animation = new Animation($element, {
    //fromDirection: "top",
    duration: 1
  });

  const _setStartPositions = () => {
    // Set default start positions
    let _startOptions = {
      scale: 1.05
    };

    // If the direction is from the bottom
    if (animation.options.direction === "left") {
      // Set options
      _startOptions.clipPath = "inset(0% 100% 0% 0%)"
    }
    if (animation.options.direction === "right") {
      // Set options
      _startOptions.clipPath = "inset(0% 0% 0% 100%)"
    }
    if (animation.options.direction === "top") {
      // Set options
      _startOptions.clipPath = "inset(0% 0% 100% 0%)"
    }
    if (animation.options.direction === "bottom") {
      // Set options
      _startOptions.clipPath = "inset(100% 0% 0% 0%)"
    }

    // Set start positions
    gsap.set(animation.$element, _startOptions);
  }

  const _setupAnimation = () => {
    // GSAP animation
    animation.gsapTl.to($element, {
      ease: "Sine.easeInOut",
      clipPath: "inset(0% 0% 0% 0%)",
      scale: 1,
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
