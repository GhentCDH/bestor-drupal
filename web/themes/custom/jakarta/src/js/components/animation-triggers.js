import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import AnimationRetriever from "./animation-retriever";
import imagesLoaded from "imagesloaded";
import { helperDispatchDomRefresh } from "./helpers";

export default function AnimationTriggers($triggers, minBreakpoint) {
  // Check for parameters
  if ($triggers == null || $triggers.length == 0) throw new Error("There are no animation triggers");
  if (minBreakpoint == null || minBreakpoint == 0) throw new Error("The provided breakpoint is missing or invalid");

  // Register the scrolltrigger plugin for GSAP
  gsap.registerPlugin(ScrollTrigger);

  // Set parameters
  const _$triggers = $triggers;
  const _minBreakpoint = minBreakpoint;
  const _mm = gsap.matchMedia();
  let _collections = [];

  const _createCollections = () => {
    // Set new collection
    _collections = [];

    // Loop
    _$triggers.forEach($trigger => {
      // Add init flag
      $trigger.setAttribute("data-ready", "yes");

      // Search for animations
      const _$animations = $trigger.querySelectorAll("[data-animation]");

      // If there are animations
      if (_$animations.length > 0) {
        // Loop and create animations
        const _animations = [..._$animations].map($animation => new AnimationRetriever($animation));
        // Add to collection
        _collections.push({
          $trigger,
          animations: _animations
        });
      }
    });
  }

  const _createScrollTriggersFromCollection = () => {
    // Create a scroll trigger for each collection
    _collections.forEach(collection => _createScrollTrigger(collection));
  }

  const _createScrollTrigger = (collection) => {
    ScrollTrigger.create({
      trigger: collection.$trigger,
      once: true,
      start: "top 80%",
      end: "bottom top",
      //markers: true,
      invalidateOnRefresh: true,
      onEnter: () => {
        // Play all the animations of this collection
        collection.animations.forEach(animation => {
          if (animation.play !== undefined) {
            animation.play();
          }
        });
      }
    });
  }

  const _init = () => {
    // Set match media logic
    _mm.add(`(min-width: ${_minBreakpoint}px)`, (context) => {
      // Create collections of triggers
      _createCollections();
      // Create scroll trigger
      _createScrollTriggersFromCollection();

      // Wait for all images to be loaded on the body
      imagesLoaded(document.body, (instance) => {
        // Refresh scrolltrigger
        helperDispatchDomRefresh();
      });
    });
  }

  _init();
}
