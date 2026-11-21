<?php

// Configuration of the "plain" theme: a standalone theme without Bootstrap,
// jQuery or a build step. See README.md in this directory.

return [
    // The root theme has no layout or page templates; this theme brings its own
    // and must not extend bootstrap5 or sandal5.
    'extends' => 'root',
    'css' => [
        // The order only matters for layers.css, which declares the order of the
        // cascade layers; every other file puts its rules into one of them.
        ['file' => 'layers.css'],
        ['file' => 'tokens.css'],
        ['file' => 'base.css'],
        ['file' => 'layout.css'],
        ['file' => 'components/button.css'],
        ['file' => 'components/cover.css'],
        ['file' => 'components/facet.css'],
        ['file' => 'components/field.css'],
        ['file' => 'components/field-list.css'],
        ['file' => 'components/filter-list.css'],
        ['file' => 'components/highlight.css'],
        ['file' => 'components/icon.css'],
        ['file' => 'components/item-status.css'],
        ['file' => 'components/menu.css'],
        ['file' => 'components/menu-bar.css'],
        ['file' => 'components/notice.css'],
        ['file' => 'components/page-title.css'],
        ['file' => 'components/pagination.css'],
        ['file' => 'components/record.css'],
        ['file' => 'components/result.css'],
        ['file' => 'components/result-list.css'],
        ['file' => 'components/search-form.css'],
        ['file' => 'components/site-footer.css'],
        ['file' => 'components/site-header.css'],
        ['file' => 'components/skip-link.css'],
        ['file' => 'components/status.css'],
        ['file' => 'components/table.css'],
        ['file' => 'components/tabs.css'],
        ['file' => 'components/tag.css'],
        ['file' => 'components/toolbar.css'],
        ['file' => 'utilities.css'],
        // The color scheme file (css/schemes/<name>.css) is added by
        // templates/layout/layout.phtml, because it depends on the request.
    ],
    'js' => [
        // One entry point; it imports the modules in js/modules/.
        ['file' => 'main.js', 'attributes' => ['type' => 'module']],
    ],
    'favicon' => 'vufind-favicon.ico',
    'icons' => [
        'defaultSet' => 'Sprite',
        'sets' => [
            // images/icons.svg holds one <symbol> per icon; the id is the icon name.
            'Sprite' => [
                'template' => 'sprite',
                'src' => 'icons.svg',
            ],
        ],
        'aliases' => [
            // Names used by this theme's templates.
            'cover-missing' => 'Sprite:image',
            'external-link' => 'Sprite:external-link',
            'facet-checked' => 'Sprite:checkbox-checked',
            'facet-exclude' => 'Sprite:minus-circle',
            'facet-unchecked' => 'Sprite:checkbox-unchecked',
            'menu-color-scheme' => 'Sprite:circle-half',
            'menu-language' => 'Sprite:globe',
            'menu-open' => 'Sprite:chevron-down',
            'menu-selected' => 'Sprite:check',
            'menu-theme' => 'Sprite:layout',
            'notice-danger' => 'Sprite:alert-octagon',
            'notice-info' => 'Sprite:info',
            'notice-warning' => 'Sprite:alert-triangle',
            'page-next' => 'Sprite:chevron-right',
            'page-next-rtl' => 'Sprite:chevron-left',
            'page-prev' => 'Sprite:chevron-left',
            'page-prev-rtl' => 'Sprite:chevron-right',
            'search' => 'Sprite:search',
            'search-filter-remove' => 'Sprite:close',
            'status-available' => 'Sprite:check-circle',
            'status-unavailable' => 'Sprite:x-circle',
            'status-uncertain' => 'Sprite:alert-triangle',
            'status-unknown' => 'Sprite:help-circle',
            // Names used in config.ini ([Site] selectable_color_schemes).
            'circle-half-stroke' => 'Sprite:circle-half',
            'contrast' => 'Sprite:contrast',
            'moon' => 'Sprite:moon',
            'sun' => 'Sprite:sun',
        ],
    ],
];
