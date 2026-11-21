/**
 * Availability, location and call number of the records in a result list.
 *
 * The placeholders (templates/partials/item-status.phtml) are hidden in the
 * HTML. This module asks VuFind's AJAX handler "getItemStatuses" for all records
 * of the page in one request and fills them; the labels in the answer are
 * rendered by the templates in templates/ajax/.
 *
 * Not covered: the full status table ([Item_Status] show_full_status) and
 * status sources other than the library system (for example OverDrive).
 */
import { setHtml } from './html.js';
import { ajaxUrl, getSearchId } from './site.js';

/**
 * Get one part of a placeholder.
 * @param {Element} placeholder Placeholder of a record
 * @param {string} name Name of the part: availability, location or callnumber
 * @returns {Element} The part
 */
function getPart(placeholder, name) {
  return placeholder.querySelector(`[data-item-status-part="${name}"]`);
}

/**
 * Show the status of a record in its placeholder.
 * @param {HTMLElement} placeholder Placeholder of the record
 * @param {object} status Status of the record from the AJAX handler
 */
function showStatus(placeholder, status) {
  if (status.missing_data) {
    placeholder.hidden = true;
    return;
  }
  if (status.error) {
    getPart(placeholder, 'availability').textContent = status.error;
    return;
  }
  setHtml(getPart(placeholder, 'availability'), status.availability_message || '');
  if (status.locationList) {
    // Several locations: the server has rendered them with their call numbers.
    setHtml(getPart(placeholder, 'location'), status.locationList);
    return;
  }
  getPart(placeholder, 'location').textContent = status.reserve === 'true'
    ? status.reserve_message
    : status.location || '';
  setHtml(getPart(placeholder, 'callnumber'), status.callnumberHtml || '');
}

/**
 * Request the statuses of records.
 * @param {string[]} recordIds IDs of the records
 * @returns {Promise<object[]>} Statuses, each with the ID of its record
 */
async function fetchStatuses(recordIds) {
  const body = new URLSearchParams({ sid: getSearchId() });
  recordIds.forEach((id) => body.append('id[]', id));
  const response = await fetch(ajaxUrl('getItemStatuses'), {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body,
  });
  if (!response.ok) {
    throw new Error(`Item status request failed with status ${response.status}`);
  }
  return (await response.json()).data.statuses;
}

/**
 * Load and show the statuses of all records below an element.
 * @param {Document|Element} root Element to search for placeholders
 * @returns {Promise<void>} Settled when all placeholders are filled
 */
export async function initItemStatuses(root) {
  const placeholders = [...root.querySelectorAll('[data-item-status]')];
  if (placeholders.length === 0) {
    return;
  }
  // The placeholders contain the text "Loading…".
  placeholders.forEach((placeholder) => {
    placeholder.hidden = false;
  });
  try {
    const statuses = await fetchStatuses(placeholders.map((placeholder) => placeholder.dataset.recordId));
    const statusById = new Map(statuses.map((status) => [status.id, status]));
    placeholders.forEach((placeholder) => {
      showStatus(placeholder, statusById.get(placeholder.dataset.recordId) || { missing_data: true });
    });
  } catch (error) {
    console.error(error);
    placeholders.forEach((placeholder) => {
      getPart(placeholder, 'availability').textContent = placeholder.dataset.errorText;
    });
  }
}
