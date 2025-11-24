import { gsap } from "gsap";

export default function Animation($element, defaultOptions) {
  // Check for parameters
  if ($element == null) throw new Error("There is no element for animation");

  // Set parameters
  const tl = gsap.timeline({
    paused: true
  });

  let _options = {
    duration: 0.5,
    delay: 0,
    stagger: 0,
    distance: 0,
    direction: 'top',
    ...defaultOptions
  };

  // Resume the timline
  const play = () => tl.resume();

  // Replace options by data attributes parameters
  // Megre data attributes with default options
  const _setDefaultParamsFromAttributes = () => _options = { ..._options, ...$element.dataset };

  _setDefaultParamsFromAttributes();

  return {
    play,
    gsapTl: tl,
    get $element() { return $element },
    get options() { return _options }
  }
}
