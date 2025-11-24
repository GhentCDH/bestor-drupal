import { gsap } from "gsap";
import { SplitText } from "gsap/SplitText";
import Animation from "./animation";

export default function AnimationClipText($element) {
  // Check for parameters
  if ($element == null) throw new Error("There is no element for the clip text");

  // Create a new animation instance, we extend from this instance
  // Pass default options if there are no data attributes for them
  let animation = new Animation($element, {
    fromDirection: "bottom",
    animateType: "lines",
    duration: 0.6,
  });

  // Set parameters
  // Search for actual parent div of the text elements
  const _$textElement = $element.querySelector("h1,h2,h3,h4,h5,.h1,.h2,.h3,.h4,.h5,p,div");
  let _splittedData = null;

  const _splitText = async () => {

    // Wait for fonts to load
    await document.fonts.ready;

    // Add class to the text parent
    _$textElement.classList.add("clip-text-parent");

    // Return the splitted data
    _splittedData = SplitText.create(_$textElement, {
      type: "lines, words, chars",
      mask: "lines",
      linesClass: "line",
      wordsClass: "word"
    });
  }

  const _setStartPositions = () => {
    // Set options
    let _options = {
      opacity: 1,
    };

    // If the direction is from the bottom
    if (animation.options.fromDirection == "bottom") {
      // Set options
      _options = {
        y: "101%"
      }
    }

    // If the direction is from the top
    if (animation.options.fromDirection == "top") {
      // Set options
      _options = {
        y: "-101%"
      }
    }

    // If the direction is from the left
    if (animation.options.fromDirection == "left") {
      // Set options
      _options = {
        x: "-101%"
      }
    }

    // If the direction is from the right
    if (animation.options.fromDirection == "right") {
      // Set options
      _options = {
        x: "101%"
      }
    }

    // Set position for what we what we want to animate
    gsap.set(_splittedData[animation.options.animateType], _options);
  }

  const _setupAnimation = () => {
    // GSAP animation
    animation.gsapTl.to(_splittedData[animation.options.animateType], {
      x: 0,
      y: 0,
      stagger: animation.options.stagger,
      duration: animation.options.duration,
      delay: animation.options.delay
    });

    // Set opacity back (avoid glitch on initial load)
    gsap.set($element, {
      opacity: 1
    });
  }

  const _init = async () => {
    // If there is an element to split
    if (_$textElement !== null) {
      // Split text
      await _splitText();
      // Set start positions
      _setStartPositions();
      // Setup animation
      _setupAnimation();
    }
  };

  (async () => {
    try {
      await _init(); // Wait for _init to complete
    } catch (error) {
      console.error("Error during initialization:", error);
    }
  })();

  return animation;
}
