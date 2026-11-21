/**
 * Entry point of the theme's scripts, loaded as an ES module (see
 * theme.config.php). Every behaviour is a module of its own in modules/ and
 * improves markup which already works without it.
 */
import { initCovers } from './modules/covers.js';
import { initItemStatuses } from './modules/item-status.js';
import { initMenus } from './modules/menu.js';

initMenus(document);
initItemStatuses(document);
initCovers(document);
