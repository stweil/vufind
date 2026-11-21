/**
 * Menus of the site header (templates/partials/menu.phtml). They are <details>
 * elements and open and close without this module; it adds what users expect of
 * a menu: it closes with the Escape key, by a click elsewhere and when the
 * keyboard focus leaves it.
 */

/**
 * Close a menu.
 * @param {HTMLDetailsElement} menu Menu to close
 * @param {boolean} [restoreFocus] Move the keyboard focus back to the menu button
 */
function closeMenu(menu, restoreFocus = false) {
  menu.open = false;
  if (restoreFocus) {
    menu.querySelector('summary').focus();
  }
}

/**
 * Set up all menus below an element.
 * @param {Document|Element} root Element to search for menus
 */
export function initMenus(root) {
  const menus = [...root.querySelectorAll('details[data-menu]')];
  if (menus.length === 0) {
    return;
  }
  document.addEventListener('click', (event) => {
    menus
      .filter((menu) => menu.open && !menu.contains(event.target))
      .forEach((menu) => closeMenu(menu));
  });
  menus.forEach((menu) => {
    menu.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && menu.open) {
        closeMenu(menu, true);
      }
    });
    menu.addEventListener('focusout', (event) => {
      // relatedTarget is the element which gets the focus; it is null when the
      // focus leaves the page, and then the menu stays as it is.
      if (event.relatedTarget && !menu.contains(event.relatedTarget)) {
        closeMenu(menu);
      }
    });
  });
}
