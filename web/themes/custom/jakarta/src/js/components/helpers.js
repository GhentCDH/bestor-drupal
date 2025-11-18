// Check if we can hover
export const helperCanHover = () => window.matchMedia("(any-hover: hover)").matches;

// Retrieve a value from a data attribute
export const helperGetDataParam = ($element, param) => {
  // Check if param exists and is not empty
  if ($element.hasAttribute(`data-${param}`) && $element.getAttribute(`data-${param}`) !== false) {
    return $element.getAttribute(`data-${param}`);
  }

  return null;
}

export const helperDispatchDomRefreshName = "DOMrefresh";
export const helperDispatchDomRefresh = () => {
  document.body.dispatchEvent(new Event(helperDispatchDomRefreshName));
}
