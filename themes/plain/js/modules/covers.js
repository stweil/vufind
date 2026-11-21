/**
 * Cover images which VuFind looks up on request ([Content] ajaxcovers in
 * config.ini). The image elements (templates/RecordDriver/DefaultRecord/
 * cover.phtml) are hidden and have no address; this module asks the AJAX handler
 * "getRecordCover" for it. Without an address the placeholder icon stays.
 *
 * Not covered: the note on the source of a cover (backlink) and the HTML
 * replacement for a missing cover ([Content] ajaxcoversFallbacks).
 */
import { ajaxUrl } from './site.js';

/**
 * Look up and show one cover.
 * @param {HTMLImageElement} image Image element with the record data attributes
 * @returns {Promise<void>} Settled when the address has been set or the lookup has failed
 */
async function loadCover(image) {
  const { recordId, recordSource: source, size, context } = image.dataset;
  try {
    const response = await fetch(ajaxUrl('getRecordCover', { recordId, source, size, context }));
    if (!response.ok) {
      return;
    }
    const { url } = (await response.json()).data;
    if (url) {
      image.src = url;
      image.hidden = false;
    }
  } catch (error) {
    console.error(error);
  }
}

/**
 * Load all covers below an element.
 * @param {Document|Element} root Element to search for covers
 */
export function initCovers(root) {
  root.querySelectorAll('img[data-cover]').forEach(loadCover);
}
