# Provenance of the templates and scripts

Every template of this theme was written for it, with the template of the standard
theme (`bootstrap5`) at hand for the view helpers, variables and translation keys it
uses. This table records which file that was and the last commit which had changed
it at the time, so that later changes can be found.

The sources were read on 2026-11-21 in the branch `vufind-1590-dark-mode-review-fixes`
at commit `112b8d3ec7`. That branch is based on commit `c90007c9b6` of the upstream branch
`dev`; a commit listed below which is not part of upstream is one of the dark mode
branch.

To see what has changed in a source since then (here for the result page, with
`vufind-org` as the name of the upstream remote):

```bash
git log --oneline 281ccea49d..vufind-org/dev -- themes/bootstrap5/templates/search/results.phtml
```

To list every source which has changed, run this in the repository root:

```bash
grep -o '`themes/[^`]*` at `[0-9a-f]*`' themes/plain/PROVENANCE.md | tr -d '`' | sort -u | while read -r file at commit; do git diff --quiet "$commit" vufind-org/dev -- "$file" || echo "changed: $file"; done
```

## Templates

Paths in the first column are relative to `themes/plain/templates/`.

| Template | Source and commit | Remarks |
| --- | --- | --- |
| `ajax/itemCallnumber.phtml` | `themes/bootstrap5/templates/ajax/itemCallnumber.phtml` at `6eee64c8e7` |  |
| `ajax/itemLocationList.phtml` | `themes/bootstrap5/templates/ajax/itemLocationList.phtml` at `ca17d6dfc7` |  |
| `ajax/status-available-services.phtml` | `themes/bootstrap5/templates/ajax/status-available-services.phtml` at `12efd6a78a` |  |
| `ajax/status-available.phtml` | `themes/bootstrap5/templates/ajax/status-available.phtml` at `12efd6a78a` |  |
| `ajax/status-unavailable.phtml` | `themes/bootstrap5/templates/ajax/status-unavailable.phtml` at `12efd6a78a` |  |
| `ajax/status-uncertain.phtml` | `themes/bootstrap5/templates/ajax/status-uncertain.phtml` at `12efd6a78a` |  |
| `ajax/status-unknown.phtml` | `themes/bootstrap5/templates/ajax/status-unknown.phtml` at `12efd6a78a` |  |
| `ajax/status.phtml` | `themes/bootstrap5/templates/ajax/status.phtml` at `12efd6a78a` |  |
| `error/404.phtml` | `themes/root/templates/error/404.phtml` at `2d41baaa46` |  |
| `error/exception.phtml` | `themes/bootstrap5/templates/error/index.phtml` at `7a87c10d34` | the exception details |
| `error/index.phtml` | `themes/bootstrap5/templates/error/index.phtml` at `7a87c10d34` | plus the message for a missing template |
| `footer.phtml` | `themes/bootstrap5/templates/footer.phtml` at `f206358752` | no footer menu |
| `header.phtml` | `themes/bootstrap5/templates/header.phtml` at `f206358752`<br>`themes/bootstrap5/templates/Section/HeaderBar.phtml` at `f206358752` | fixed set of three menus instead of the configurable header bar |
| `header/color-scheme-menu.phtml` | `themes/bootstrap5/templates/Section/HeaderBar/HeaderBar-colorSchemeOptions.phtml` at `3f84bf00dc` |  |
| `header/language-menu.phtml` | `themes/bootstrap5/templates/Section/HeaderBar/HeaderBar-allLangs.phtml` at `331eefa05b` |  |
| `header/theme-menu.phtml` | `themes/bootstrap5/templates/Section/HeaderBar/HeaderBar-themeOptions.phtml` at `f206358752` |  |
| `Helpers/icons/sprite.phtml` | `themes/root/templates/Helpers/icons/svg-sprite.phtml` at `7d8f053b10` |  |
| `Helpers/pagination-item.phtml` | `themes/bootstrap5/templates/Helpers/pagination-item.phtml` at `5a99f23d0d` |  |
| `Helpers/pagination.phtml` | `themes/bootstrap5/templates/Helpers/pagination.phtml` at `5a99f23d0d` | no first and last page links |
| `layout/head.phtml` | `themes/bootstrap5/templates/layout/layout.phtml` at `3f84bf00dc` | the head part |
| `layout/layout.phtml` | `themes/bootstrap5/templates/layout/layout.phtml` at `3f84bf00dc` | page frame only; no lightbox, cookie consent, analytics, keep-alive, inline scripts |
| `layout/redirect.phtml` | `themes/bootstrap5/templates/layout/redirect.phtml` at `fbe428dd80` |  |
| `partials/availability.phtml` | `themes/bootstrap5/templates/RecordTab/holdingsils/standard.phtml` at `ca17d6dfc7`<br>`themes/bootstrap5/templates/ajax/itemLocationList.phtml` at `ca17d6dfc7` | the status part |
| `partials/field-list.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/core-fields.phtml` at `199f4f456b`<br>`themes/bootstrap5/templates/RecordTab/description.phtml` at `7a52ab1557` | a description list instead of a table |
| `partials/item-status.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/result-list.phtml` at `6cfb9022a8` | the place for the AJAX status |
| `partials/menu.phtml` | `themes/bootstrap5/templates/_ui/components/menu-button.phtml` at `3f84bf00dc` | a details element instead of a Bootstrap dropdown |
| `partials/missing-in-theme.phtml` | none | new |
| `partials/notice.phtml` | none | new |
| `partials/select-form.phtml` | `themes/bootstrap5/templates/search/controls/sort.phtml` at `eff092375f`<br>`themes/bootstrap5/templates/search/controls/limit.phtml` at `199f4f456b` | the form both share |
| `partials/status.phtml` | `themes/bootstrap5/templates/ajax/status-available.phtml` at `12efd6a78a` | the label all status templates share |
| `Recommend/RemoveFilters.phtml` | `themes/bootstrap5/templates/Recommend/RemoveFilters.phtml` at `12efd6a78a` |  |
| `Recommend/SideFacets.phtml` | `themes/bootstrap5/templates/Recommend/SideFacets.phtml` at `f206358752` |  |
| `Recommend/SideFacets/checkbox-filters.phtml` | `themes/bootstrap5/templates/Recommend/SideFacets/checkbox-filters.phtml` at `afa448ad96` |  |
| `Recommend/SideFacets/facet-list.phtml` | `themes/bootstrap5/templates/Recommend/SideFacets/cluster-list.phtml` at `a73b68b2c2`<br>`themes/bootstrap5/templates/Recommend/SideFacets/facet.phtml` at `12efd6a78a` |  |
| `Recommend/SideFacets/facet-value.phtml` | `themes/bootstrap5/templates/Recommend/SideFacets/single-facet.phtml` at `a73b68b2c2` |  |
| `Recommend/SideFacets/range.phtml` | `themes/bootstrap5/templates/Recommend/SideFacets/range-slider.phtml` at `d95165a818` | no slider |
| `Recommend/SwitchQuery.phtml` | `themes/bootstrap5/templates/Recommend/SwitchQuery.phtml` at `12efd6a78a` |  |
| `Recommend/SwitchType.phtml` | `themes/bootstrap5/templates/Recommend/SwitchType.phtml` at `12efd6a78a` |  |
| `record/tabs.phtml` | `themes/bootstrap5/templates/_ui/components/tabs.phtml` at `6eb4fa039c`<br>`themes/bootstrap5/templates/record/view.phtml` at `f206358752` | links and server-side content instead of AJAX tabs |
| `record/view.phtml` | `themes/bootstrap5/templates/record/view.phtml` at `f206358752` |  |
| `RecordDriver/DefaultRecord/core-fields.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/core-fields.phtml` at `199f4f456b` |  |
| `RecordDriver/DefaultRecord/core.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/core.phtml` at `b714fa24de` |  |
| `RecordDriver/DefaultRecord/cover.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/cover.phtml` at `6b47b749fe` |  |
| `RecordDriver/DefaultRecord/data-allRecordLinks.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-allRecordLinks.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/data-allSubjectHeadings.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-allSubjectHeadings.phtml` at `36bccc7241` |  |
| `RecordDriver/DefaultRecord/data-authorNotes.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-authorNotes.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/data-authors.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-authors.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/data-childRecords.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-childRecords.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/data-containerTitle.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-containerTitle.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/data-onlineAccess.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-onlineAccess.phtml` at `46a14f30fa` |  |
| `RecordDriver/DefaultRecord/data-publicationDetails.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-publicationDetails.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/data-series.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-series.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/data-summary.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-summary.phtml` at `1e9e6236ce` |  |
| `RecordDriver/DefaultRecord/data-tags.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/data-tags.phtml` at `00ad5394f8` | intentionally empty |
| `RecordDriver/DefaultRecord/format-list.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/format-list.phtml` at `12efd6a78a` |  |
| `RecordDriver/DefaultRecord/link-author.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/link-author.phtml` at `8815a933f6` | always a search; no author page |
| `RecordDriver/DefaultRecord/link-generic.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/link-generic.phtml` at `8815a933f6` |  |
| `RecordDriver/DefaultRecord/link-journaltitle.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/link-journaltitle.phtml` at `8815a933f6` |  |
| `RecordDriver/DefaultRecord/link-publisher.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/link-publisher.phtml` at `8815a933f6` |  |
| `RecordDriver/DefaultRecord/link-series.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/link-series.phtml` at `8815a933f6` |  |
| `RecordDriver/DefaultRecord/link-subject.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/link-subject.phtml` at `8815a933f6` |  |
| `RecordDriver/DefaultRecord/link-title.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/link-title.phtml` at `8815a933f6` |  |
| `RecordDriver/DefaultRecord/result-list.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/result-list.phtml` at `6cfb9022a8` |  |
| `RecordDriver/DefaultRecord/result-published.phtml` | `themes/bootstrap5/templates/RecordDriver/DefaultRecord/result-list.phtml` at `6cfb9022a8` | the "Published" line |
| `RecordTab/description.phtml` | `themes/bootstrap5/templates/RecordTab/description.phtml` at `7a52ab1557` |  |
| `RecordTab/holdingsils.phtml` | `themes/bootstrap5/templates/RecordTab/holdingsils.phtml` at `ad68cabc7a` |  |
| `RecordTab/holdingsils/items.phtml` | `themes/bootstrap5/templates/RecordTab/holdingsils/standard.phtml` at `ca17d6dfc7`<br>`themes/bootstrap5/templates/RecordTab/holdingsils/electronic.phtml` at `9b80fec60e` | read-only |
| `RecordTab/holdingsils/location.phtml` | `themes/bootstrap5/templates/RecordTab/holdingsils.phtml` at `ad68cabc7a` | one location |
| `RecordTab/staffviewarray.phtml` | `themes/bootstrap5/templates/RecordTab/staffviewarray.phtml` at `12efd6a78a` |  |
| `RecordTab/staffviewmarc.phtml` | `themes/bootstrap5/templates/RecordTab/staffviewmarc.phtml` at `12efd6a78a` |  |
| `search/controls/limit.phtml` | `themes/bootstrap5/templates/search/controls/limit.phtml` at `199f4f456b` |  |
| `search/controls/showing.phtml` | `themes/bootstrap5/templates/search/controls/showing.phtml` at `f206358752` |  |
| `search/controls/sort.phtml` | `themes/bootstrap5/templates/search/controls/sort.phtml` at `eff092375f` |  |
| `search/filters.phtml` | `themes/bootstrap5/templates/search/filters.phtml` at `f49839d997` | one list for all widths; no drop-down for long lists |
| `search/home.phtml` | `themes/bootstrap5/templates/search/home.phtml` at `0e5953b7d7` | no content blocks |
| `search/list-list.phtml` | `themes/bootstrap5/templates/search/list-list.phtml` at `a4f4af5073`<br>`themes/bootstrap5/templates/search/results-list.phtml` at `12efd6a78a` |  |
| `search/no-results.phtml` | `themes/bootstrap5/templates/search/results.phtml` at `281ccea49d` | the branch without results |
| `search/recommendations.phtml` | `themes/bootstrap5/templates/search/results.phtml` at `281ccea49d` | new: skips modules without a template in this theme |
| `search/results.phtml` | `themes/bootstrap5/templates/search/results.phtml` at `281ccea49d` |  |
| `search/searchbox.phtml` | `themes/bootstrap5/templates/search/searchbox.phtml` at `0e5953b7d7` | the setup logic; basic search only |
| `search/searchbox/hidden-fields.phtml` | `themes/bootstrap5/templates/search/searchbox.phtml` at `0e5953b7d7` | the hidden fields |
| `search/searchbox/type-select.phtml` | `themes/bootstrap5/templates/search/searchbox.phtml` at `0e5953b7d7` | the select element |

## Scripts

Paths in the first column are relative to `themes/plain/`.

| Script | Source and commit | Remarks |
| --- | --- | --- |
| `js/modules/item-status.js` | `themes/bootstrap5/js/check_item_statuses.js` at `ca17d6dfc7` | one request for all records of the page; no queue, no observer |
| `js/modules/covers.js` | `themes/bootstrap5/js/covers.js` at `6b47b749fe` | no backlinks |
| `js/modules/html.js` | `themes/bootstrap5/js/common.js` at `de07066bf5` | replaces VuFind.setInnerHtml |
| `js/modules/site.js` | `themes/bootstrap5/js/common.js` at `de07066bf5`<br>`themes/bootstrap5/templates/layout/layout.phtml` at `3f84bf00dc` | replaces VuFind.path and the inline configuration script |
| `js/modules/menu.js` | none | new; Bootstrap provides this for the standard theme |
| `js/main.js` | none | new |
