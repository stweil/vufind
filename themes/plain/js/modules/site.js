/**
 * Facts about the page which the server provides as data attributes of the
 * <body> element (templates/layout/layout.phtml).
 */

/**
 * Build the URL of one of VuFind's JSON AJAX handlers.
 * @param {string} method Name of the handler, for example "getItemStatuses"
 * @param {object} [parameters] Further query parameters
 * @returns {string} URL of the handler
 */
export function ajaxUrl(method, parameters = {}) {
  const query = new URLSearchParams({ ...parameters, method });
  return `${document.body.dataset.basePath}/AJAX/JSON?${query}`;
}

/**
 * Get the ID of the search whose results the page shows.
 * @returns {string} Search ID, or an empty string on other pages
 */
export function getSearchId() {
  return document.body.dataset.searchId || '';
}
