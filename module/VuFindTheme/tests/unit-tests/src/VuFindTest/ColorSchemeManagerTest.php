<?php

/**
 * Color Scheme Manager Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Stefan Weil 2026.
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
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Stdlib\Parameters;
use VuFind\Cookie\CookieManager;
use VuFind\View\GlobalsContainer;
use VuFindTheme\ColorSchemeManager;

/**
 * Color Scheme Manager Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ColorSchemeManagerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get a color scheme manager for the theme "foo".
     *
     * @param array          $config        Theme configuration
     * @param ?CookieManager $cookieManager Cookie manager (null for a default stub)
     *
     * @return ColorSchemeManager
     */
    protected function getManager(array $config, ?CookieManager $cookieManager = null): ColorSchemeManager
    {
        return new ColorSchemeManager(
            $config,
            new GlobalsContainer(),
            $cookieManager ?? $this->createStub(CookieManager::class),
            'foo'
        );
    }

    /**
     * Data provider for testGetSelectedColorScheme.
     *
     * @return \Iterator
     */
    public static function selectedColorSchemeProvider(): \Iterator
    {
        $all = ['supported_color_schemes' => ['foo' => 'normal,dark,light']];
        yield 'no user input defaults to normal' => [$all, [], [], null, 'normal', true];
        yield 'query parameter' => [$all, [], ['color_scheme' => 'dark'], null, 'dark', true];
        yield 'cookie' => [$all, [], [], 'light', 'light', true];
        yield 'query parameter overrides cookie' => [$all, [], ['color_scheme' => 'dark'], 'light', 'dark', true];
        yield 'post parameter overrides query parameter' => [
            $all, ['color_scheme' => 'light'], ['color_scheme' => 'dark'], null, 'light', true,
        ];
        yield 'unknown value falls back to first supported scheme' => [
            $all, [], ['color_scheme' => 'sepia'], null, 'normal', false,
        ];
        yield 'array value falls back to first supported scheme' => [
            $all, [], ['color_scheme' => ['dark']], null, 'normal', false,
        ];
        yield 'unsupported value falls back to first supported scheme' => [
            ['supported_color_schemes' => ['foo' => 'light,dark']], [], [], null, 'light', false,
        ];
        yield 'no supported schemes fall back to light' => [[], [], ['color_scheme' => 'dark'], null, 'light', false];
        yield 'other theme only falls back to light' => [
            ['supported_color_schemes' => ['bar' => 'normal,dark,light']], [], [], 'dark', 'light', false,
        ];
        yield 'wildcard applies to unlisted theme' => [
            ['supported_color_schemes' => ['*' => 'normal,dark,light']], [], [], 'dark', 'dark', true,
        ];
        yield 'theme setting overrides wildcard' => [
            ['supported_color_schemes' => ['*' => 'normal,dark,light', 'foo' => 'light']],
            [], [], 'dark', 'light', false,
        ];
        yield 'whitespace and empty entries are ignored' => [
            ['supported_color_schemes' => ['foo' => ' dark , light ,']], [], [], null, 'dark', false,
        ];
        yield 'non-string setting is ignored' => [
            ['supported_color_schemes' => ['foo' => ['dark']]], [], [], 'dark', 'light', false,
        ];
    }

    /**
     * Test getSelectedColorScheme with a request.
     *
     * @param array   $config         Theme configuration
     * @param array   $post           POST parameters
     * @param array   $query          Query parameters
     * @param ?string $cookie         Cookie value
     * @param string  $expected       Expected color scheme
     * @param bool    $expectedToSave Should the color scheme be saved to the cookie?
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('selectedColorSchemeProvider')]
    public function testGetSelectedColorScheme(
        array $config,
        array $post,
        array $query,
        ?string $cookie,
        string $expected,
        bool $expectedToSave
    ): void {
        $request = new Request();
        $request->setPost(new Parameters($post));
        $request->setQuery(new Parameters($query));
        $cookieManager = $this->createMock(CookieManager::class);
        $cookieManager->method('get')->with('color_scheme')->willReturn($cookie);
        if ($expectedToSave) {
            $cookieManager->expects($this->once())->method('set')->with('color_scheme', $expected);
        } else {
            $cookieManager->expects($this->never())->method('set');
        }
        $this->assertSame($expected, $this->getManager($config, $cookieManager)->getSelectedColorScheme($request));
    }

    /**
     * Data provider for testGetSelectedColorSchemeWithoutRequest.
     *
     * @return \Iterator
     */
    public static function selectedColorSchemeWithoutRequestProvider(): \Iterator
    {
        yield 'first supported scheme' => [['supported_color_schemes' => ['foo' => 'dark,light']], 'dark'];
        yield 'trailing comma' => [['supported_color_schemes' => ['foo' => 'normal,dark,']], 'normal'];
        yield 'empty setting' => [['supported_color_schemes' => ['foo' => '']], 'light'];
        yield 'no setting' => [[], 'light'];
    }

    /**
     * Test getSelectedColorScheme without a request (e.g. on the command line).
     *
     * @param array  $config   Theme configuration
     * @param string $expected Expected color scheme
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('selectedColorSchemeWithoutRequestProvider')]
    public function testGetSelectedColorSchemeWithoutRequest(array $config, string $expected): void
    {
        $cookieManager = $this->createMock(CookieManager::class);
        $cookieManager->expects($this->never())->method('get');
        $cookieManager->expects($this->never())->method('set');
        $this->assertSame($expected, $this->getManager($config, $cookieManager)->getSelectedColorScheme(null));
    }

    /**
     * Data provider for testGetColorSchemeOptions.
     *
     * @return \Iterator
     */
    public static function colorSchemeOptionsProvider(): \Iterator
    {
        $supported = ['foo' => 'normal,dark,light'];
        yield 'no selectable schemes' => [['supported_color_schemes' => $supported], []];
        yield 'all parts' => [
            [
                'supported_color_schemes' => $supported,
                'selectable_color_schemes' => [
                    'normal:color_scheme_normal:circle-half-stroke',
                    'dark:color_scheme_dark:moon',
                ],
            ],
            [
                ['name' => 'normal', 'label' => 'color_scheme_normal', 'icon' => 'circle-half-stroke'],
                ['name' => 'dark', 'label' => 'color_scheme_dark', 'icon' => 'moon'],
            ],
        ];
        yield 'missing parts and whitespace' => [
            [
                'supported_color_schemes' => $supported,
                'selectable_color_schemes' => ['dark', ' light : color_scheme_light '],
            ],
            [
                ['name' => 'dark', 'label' => 'dark', 'icon' => false],
                ['name' => 'light', 'label' => 'color_scheme_light', 'icon' => false],
            ],
        ];
        yield 'unsupported schemes are skipped without leaving gaps' => [
            [
                'supported_color_schemes' => ['foo' => 'normal,light'],
                'selectable_color_schemes' => ['normal:a', 'dark:b', 'light:c', ''],
            ],
            [
                ['name' => 'normal', 'label' => 'a', 'icon' => false],
                ['name' => 'light', 'label' => 'c', 'icon' => false],
            ],
        ];
        yield 'string instead of array' => [
            ['supported_color_schemes' => $supported, 'selectable_color_schemes' => 'dark:color_scheme_dark'],
            [['name' => 'dark', 'label' => 'color_scheme_dark', 'icon' => false]],
        ];
        yield 'nothing supported' => [['selectable_color_schemes' => ['dark:color_scheme_dark']], []];
    }

    /**
     * Test getColorSchemeOptions.
     *
     * @param array $config   Theme configuration
     * @param array $expected Expected options
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('colorSchemeOptionsProvider')]
    public function testGetColorSchemeOptions(array $config, array $expected): void
    {
        $this->assertSame($expected, $this->callMethod($this->getManager($config), 'getColorSchemeOptions'));
    }
}
