// // import Swiper JS
// import Swiper from 'swiper/bundle';
// import { Navigation, Pagination } from 'swiper/modules';
//
// export default function Slider($element) {
//   // Check for parameters
//   if ($element == null || $element.length === 0) throw new Error("There is no element for slider");
//
//   // Set parameters
//   const _$element = $element;
//   const _sliderType = _$element.dataset.sliderTrigger;
//
//   const _createSlider = () => {
//     const swiper = new Swiper(_$element, _getOptions());
//   }
//
//   const _getOptions = () => {
//     // Set default options.
//     let options = {};
//
//     // E.g. for landscape teasers homepage.
//     if (_sliderType === 'default') {
//       options = {
//         modules: [Navigation, Pagination],
//         slidesPerView: 1,
//         spaceBetween: 0,
//         speed: 600,
//         navigation: {
//           nextEl: ".swiper-button-next",
//           prevEl: ".swiper-button-prev",
//         },
//       }
//     }
//
//     return options;
//   }
//
//   const _init = () => {
//     _createSlider();
//   }
//
//   _init();
// }
