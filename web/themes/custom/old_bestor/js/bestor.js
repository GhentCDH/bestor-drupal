/**
 * @file
 * bestor behaviors.
 */
(function (Drupal) {

  function updateIntroVisibility() {
    document.querySelectorAll(".tile-text").forEach(tile => {
      const intro = tile.querySelector(".intro-text");
      if (!intro) return; 
      intro.classList.remove("hidden");

      if (tile.scrollHeight > 450) {
        intro.classList.add("hidden");
      }
    });
  }

  

  document.addEventListener("DOMContentLoaded", updateIntroVisibility);
  window.addEventListener("resize", updateIntroVisibility);

} (Drupal));
