# Theme "plain" (prototype)

A standalone VuFind theme: it extends only `root` and loads nothing from
`bootstrap5` or `sandal5`. It has no Bootstrap, no jQuery, no web fonts and no
build step; what is in this directory is what the browser gets.

It is a prototype of one journey, searching and reading records: search home,
result list with facets, record page with the tabs Holdings, Description and Staff
View, and the error page. Everything else is listed under
[Not covered](#not-covered).

## Enabling it

In `config.ini`, section `[Site]`:

```ini
alternate_themes = plain:plain
selectable_themes = "standard:Standard,plain:Plain (prototype)"
supported_color_schemes[plain] = "normal,dark,light,contrast-light,contrast-dark"
selectable_color_schemes[] = "normal:color_scheme_normal:circle-half-stroke"
selectable_color_schemes[] = "dark:color_scheme_dark:moon"
selectable_color_schemes[] = "light:color_scheme_light:sun"
selectable_color_schemes[] = "contrast-light:color_scheme_contrast_light:contrast"
selectable_color_schemes[] = "contrast-dark:color_scheme_contrast_dark:contrast"
```

`supported_color_schemes` is an array: a local `config.ini` which sets one entry
replaces the inherited array, so repeat the entries of the other themes there.
The theme is then available as `?ui=plain`.

## Technology

| Choice | Reason |
| --- | --- |
| Semantic HTML with native elements for behaviour: `<details>` for menus, folded facets and "more…", links for tabs, forms with a button for sort order and page size | Works without JavaScript and comes with keyboard and screen reader support. |
| Plain CSS: custom properties, cascade layers, logical properties, grid, container queries | No build step, so no Node version to keep up with. Layers replace `!important` and specificity tricks; logical properties make right-to-left layouts possible without a second stylesheet. |
| No Bootstrap | Its color modes are built for light and dark, its components need its JavaScript, and its class names would tie the templates to it. The theme needs about 1,500 lines of CSS of its own. |
| ES modules, no library | The three behaviours (menus, item status, covers) are 235 lines. |
| One SVG sprite with the theme's own icons | No icon font; icons take the text color, so they follow every color scheme. |
| System fonts | Nothing to download; the user's own font settings apply. |

VuFind's view helpers do the work wherever they exist (search box data, record
links, record data formatter, icons, translation, pagination, recommendations).
Where a helper renders a template, the template is in this theme.

## Structure

```
theme.config.php      stylesheets, the script entry point, icon names
PROVENANCE.md         which bootstrap5 template each template was written from
css/
  layers.css          order of the cascade layers (must be loaded first)
  tokens.css          spacing, type, radii, sizes: everything but colors
  schemes/            one file per color scheme; auto.css picks one by media query
  base.css            element defaults, focus ring, reduced motion
  layout.css          layout objects (l-)
  components/         one file per component (c-)
  utilities.css       utilities (u-)
images/icons.svg      icon sprite
js/main.js            entry point; imports js/modules/, one module per behaviour
languages/            texts VuFind does not have (en, de)
templates/
  layout/             page frame and <head>
  header/, partials/  parts used by several templates
  search/, record/, RecordTab/, RecordDriver/, Recommend/, ajax/, error/, Helpers/
                      the names VuFind's controllers and view helpers ask for
```

## Conventions

### Templates

- One job per template. Markup used twice is a partial in `templates/partials/`
  (or next to its only user, as in `search/searchbox/`).
- Every template starts with a comment which says what it shows, lists its
  variables as `@property` and names what it leaves out.
- `$this->render('x', [...])` replaces all variables of the called template, so
  pass everything it needs; `$this->render('x')` shares the caller's variables.
- All visible text goes through `transEsc()`, `transEscAttr()` or, for messages
  whose key ends in `_html`, `translate()`. All other output is escaped with
  `escapeHtml()` or `escapeHtmlAttr()`, or built with `htmlAttributes()`.
  A variable which already holds escaped HTML has a name ending in `Html`.
- No `style` attributes, no inline scripts, no event handler attributes. Data for
  scripts is in `data-` attributes.

### CSS

- Class names are `prefix-block__element--modifier`. The prefix says which layer a
  class belongs to: `l-` layout, `c-` component, `u-` utility. One component, one
  file in `css/components/`, named after the block.
- A state is expressed by the attribute which also tells assistive technology
  about it (`aria-current`, `open`, `hidden`), not by a class.
- Components use tokens only: `var(--color-…)`, `var(--space-…)` and so on. No
  literal colors, and no literal lengths for spacing, font size or radius.
- No `!important`, no ID selectors. A selector stays inside its component; the
  one exception is `css/components/table.css`, which styles table cells by
  element because VuFind produces some tables itself.
- `.highlight` has no prefix: the markup comes from a VuFind helper.
- Scripts find their elements by `data-` attributes, never by class.

### JavaScript

- ES modules, no globals. `js/main.js` imports one module per behaviour and
  calls its `init…` function.
- Progressive enhancement: the markup works without the script. Item status and
  AJAX covers are hidden in the HTML and shown by their module.
- JSDoc on every function.

## Color schemes

A scheme is one file in `css/schemes/` with one block: the values of the color
tokens and of three sizes (border width of controls, focus ring width, shadow).
All scheme files set the same properties in the same order, so two of them can be
compared line by line. Components never mention a scheme.

`templates/layout/layout.phtml` loads the file of the selected scheme and prints
its name as `data-bs-theme` on `<html>`, as the standard theme does (the name is
VuFind's; the stylesheets of this theme do not use the attribute). VuFind's scheme
`normal` is `auto.css`, which has no values of its own and imports one of the
others:

| System setting | File |
| --- | --- |
| none | `light.css` |
| dark | `dark.css` |
| more contrast | `contrast-light.css` |
| more contrast and dark | `contrast-dark.css` |

| Scheme | Target |
| --- | --- |
| `light`, `dark` | WCAG 2.2 AA: text 4.5:1, controls and focus ring 3:1 |
| `contrast-light`, `contrast-dark` | WCAG 2.2 AAA: text 7:1; controls and focus ring 4.5:1; all backgrounds the same; thicker lines |

High contrast comes in a light and a dark variant because the people who need it
do not agree on the polarity: glare-sensitive readers need light text on black,
others read dark text on white better. Windows offers both for the same reason.

### Adding a scheme

1. Copy `css/schemes/light.css` to `css/schemes/<name>.css` and change the values.
   Keep every property; set `color-scheme` to `light` or `dark`.
2. Add `<name>` to `supported_color_schemes[plain]` and a line
   `selectable_color_schemes[] = "<name>:<label key>:<icon>"` to `config.ini`.
3. Add the label to `languages/*.ini` of this theme, and the icon to the aliases
   in `theme.config.php` if it is a new one.

No template, component or script changes. If the system should be able to choose
the scheme, add an `@import` line with a media query to `auto.css`.

Under Windows contrast themes (`forced-colors: active`) the browser replaces the
colors of every scheme. The theme relies on borders and outlines there, never on
shadows or backgrounds; the four components with a selected state use the
system's selection colors (see the end of their files).

## Adding a page

1. Find the template VuFind asks for: open the page with `VUFIND_ENV=development`.
   The message for a missing page has the template name under "Exception".
2. Read the `bootstrap5` template of that name for the variables and helpers, and
   write the template here. Add the pair to `PROVENANCE.md`.
3. Use existing components; a new component is a new file in `css/components/`
   which has to be listed in `theme.config.php`.
4. Run the checks below.

## Pages and parts the theme does not have

- A page without a template here ends in VuFind's error page, and
  `templates/error/index.phtml` recognizes the missing template: it shows "This
  page is not part of the prototype view" with a link which opens the same address
  in the standard theme (`?ui=standard`). The HTTP status is 500, as for any
  rendering error.
- A record tab without a template is left out of the tab row
  (`templates/record/tabs.phtml`); requested directly, it shows the same message.
- A recommendation module without a template is left out
  (`templates/search/recommendations.phtml`).

## Not covered

Login and account pages, favorites and lists, cart, lightbox dialogs, advanced
search, search tabs, autocomplete, virtual keyboard, browse, alphabetic browse,
author pages, collections, hierarchies, comments, tags, ratings, record toolbar
(cite, e-mail, export, print), previous and next record, similar items, grid view,
bulk actions, search history, feedback, help pages, content pages, cookie consent,
analytics, CAPTCHA, session keep-alive, admin and upgrade pages, and all search
backends other than Solr which need templates of their own (EDS, Primo, Summon and
others).

Within the covered pages: hierarchical facets, selecting several facet values at
once, the full facet list, the date slider, spelling suggestions, top facets,
flash messages, breadcrumbs, OpenURL and identifier links, hold and request
links, the full status table in result lists, cover backlinks, a print
stylesheet. Right-to-left languages should work (logical properties throughout)
but have not been tested.

## Checks

```bash
node_modules/.bin/eslint themes/plain/js
```

```bash
vendor/bin/php-cs-fixer fix --config=tests/vufind_templates.php-cs-fixer.php --dry-run --diff --path-mode=intersection themes/plain
```

`eslint.config.mjs` has an entry which makes ESLint read this theme's scripts as
ES modules. The second command checks the templates and `theme.config.php`.
`phpcs` (`tests/phpcs.xml`) only checks `module/` and does not look at themes.
There is no CSS linter in the repository.
