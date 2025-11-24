import AnimationFadeIn from "./animation-fade-in";
import AnimationClipPath from "./animation-clip-path";
import AnimationClipText from "./animation-clip-text";

export default function AnimationRetriever($element) {
  // Check for parameters
  if ($element == null) throw new Error("There is no element for the animation");

  // Set parameters
  const _$element = $element;
  const _type = _$element.getAttribute("data-animation");
  const _animationTypes = [
    {
      id: "fade-in",
      create: () => new AnimationFadeIn(_$element)
    },
    {
      id: "clip-path",
      create: () => new AnimationClipPath(_$element)
    },
    {
      id: "clip-text",
      create: () => new AnimationClipText(_$element)
    }
  ];

  const _getAnimationInstance = () => {
    // Set animation instance
    const _animationType = _animationTypes.find(type => _type == type.id);
    // If there is a type
    if (_animationType !== undefined) {
      return _animationType.create();
    }
  }

  return _getAnimationInstance();
}
