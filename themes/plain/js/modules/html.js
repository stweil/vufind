/**
 * Insertion of HTML fragments which VuFind has rendered (and escaped) with this
 * theme's templates.
 */

/**
 * Replace the content of an element with an HTML fragment from the server.
 * Scripts in the fragment are not run.
 * @param {Element} element Element to fill
 * @param {string} html HTML fragment
 */
export function setHtml(element, html) {
  const template = document.createElement('template');
  template.innerHTML = html;
  element.replaceChildren(template.content);
}
