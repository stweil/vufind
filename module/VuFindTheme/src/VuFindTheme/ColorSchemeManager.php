<?php

/**
 * Color Scheme Manager.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Theme
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use Laminas\Stdlib\RequestInterface as Request;
use VuFind\Config\Feature\ExplodeSettingTrait;
use VuFind\Cookie\CookieManager;
use VuFind\View\GlobalsContainer;

use function in_array;
use function is_string;
use function strlen;

/**
 * Color Scheme Manager.
 *
 * @category VuFind
 * @package  Theme
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ColorSchemeManager
{
    use ExplodeSettingTrait;

    /**
     * Supported color schemes for the current theme.
     *
     * @var array
     */
    protected $supportedColorSchemes;

    /**
     * Constructor.
     *
     * @param array            $config           Theme configuration
     * @param GlobalsContainer $globalsContainer Global data container
     * @param CookieManager    $cookieManager    Cookie manager
     * @param string           $currentTheme     Current theme
     */
    public function __construct(
        protected array $config,
        protected GlobalsContainer $globalsContainer,
        protected CookieManager $cookieManager,
        protected string $currentTheme,
    ) {
        $rawSupportedColorSchemes = $config['supported_color_schemes'][$currentTheme]
            ?? $config['supported_color_schemes']['*'] ?? '';
        // Ignore empty entries (e.g. caused by a trailing comma) and unusable values:
        $this->supportedColorSchemes = is_string($rawSupportedColorSchemes)
            ? array_values(array_filter($this->explodeListSetting($rawSupportedColorSchemes), strlen(...)))
            : [];
    }

    /**
     * Return the user-selected color scheme, and persist it as a cookie.
     *
     * @param Request $request Request object (for obtaining user parameters);
     * set to null if no request context is available.
     *
     * @return string 'dark' or 'light' if a scheme has been selected, or 'normal'
     * to use the system default
     */
    public function getSelectedColorScheme(?Request $request): string
    {
        // Find out if the user has a saved preference in the POST, URL or cookies:
        $selectedColorScheme = null;
        $saveColorScheme = false;
        if (isset($request)) {
            $selectedColorScheme = $request->getPost()->get('color_scheme')
                ?? $request->getQuery()->get('color_scheme')
                ?? $this->cookieManager->get('color_scheme')
                ?? 'normal';
            $saveColorScheme = true;
        }

        // Only use the selected color scheme (or system-suggested color scheme) if it's supported
        // by the current theme.
        if (!$this->isSupportedColorScheme($selectedColorScheme)) {
            $selectedColorScheme = $this->supportedColorSchemes[0] ?? 'light';
            $saveColorScheme = false;
        }

        // Save the current setting to a cookie so it persists.
        if ($saveColorScheme) {
            $this->cookieManager->set('color_scheme', $selectedColorScheme);
        }

        return $selectedColorScheme;
    }

    /**
     * Make the selected color scheme and the color scheme options available to the view.
     *
     * @param string $selectedColorScheme User-specified color scheme if applicable, or 'normal'
     *
     * @return void
     */
    public function sendColorSchemeInfoToView(string $selectedColorScheme): void
    {
        // Get access to the view model:
        if (PHP_SAPI !== 'cli') {
            // Store view options in globals:
            $this->globalsContainer['selectedColorScheme'] = $selectedColorScheme;
            $this->globalsContainer['colorSchemeOptions'] = $this->getColorSchemeOptions();
        }
    }

    /**
     * Return an array of information about user-selectable color schemes. Each
     * entry in the array is an associative array with 'name', 'label' and
     * 'icon' keys.
     *
     * @return array
     */
    protected function getColorSchemeOptions()
    {
        // Load all options from config
        $rawOptions = (array)($this->config['selectable_color_schemes'] ?? []);
        $options = [];
        foreach ($rawOptions as $rawOption) {
            $option = array_map(trim(...), explode(':', (string)$rawOption));
            $name = $option[0];
            // Only return valid options for the current theme
            if (!$this->isSupportedColorScheme($name)) {
                continue;
            }
            $options[] = [
                'name' => $name,
                // Fall back to the name if no label has been configured:
                'label' => ($option[1] ?? '') !== '' ? $option[1] : $name,
                'icon' => ($option[2] ?? '') !== '' ? $option[2] : false,
            ];
        }

        return $options;
    }

    /**
     * Determines if the specified color scheme is supported under the current theme.
     *
     * @param mixed $desiredColorScheme Color scheme name (anything but a string is
     * unsupported)
     *
     * @return bool
     */
    protected function isSupportedColorScheme(mixed $desiredColorScheme): bool
    {
        return is_string($desiredColorScheme)
            && in_array($desiredColorScheme, $this->supportedColorSchemes, true);
    }
}
