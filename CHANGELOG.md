# Changelog

All notable changes to `bigcartenergy/module-catalog-completeness` by Big Cart Energy.

## 1.0.2 - 2026-11-05

### Fixed

- The rule set editor's Suggested core content Add fills in the attribute as well as the rule type, parameter and weight.
- Removing a rule no longer moves the first rule down the table, and no longer drops a second, unrelated rule from the form, which Save then deleted.
- The attribute picker's list is no longer cut off at the bottom of the rules table.
- Move up/down and drag in the rules table now set the saved order of the rules; before, Save kept the order the rules were loaded in.

### Changed

- The code meets Adobe's Commerce Marketplace coding standard. The module's classes are no longer declared `final`, a rule pattern that does not compile or fails while it runs is handled without PHP's `@` operator, and the Marketplace package is built without the maintainer tool in `dev/`. No behaviour change: such a pattern still matches nothing and logs nothing.
- Run with the module's own `phpunit.xml.dist`, the unit suite skips the one test that needs Magento's database layer; Magento's `dev/tests/unit` run still includes it.

### Tested

- Magento Open Source 2.4.9, 2.4.8, 2.4.7, 2.4.6 and 2.3.7-p4, and the Mage-OS Distribution 4.0.0 (PHP 8.4 and 8.5), 3.6.0, 3.5.0, 3.4.0, 3.3.0, 3.2.0, 3.1.0, 3.0.0, 2.3.0, 2.2.2, 2.1.0, 2.0.0, 1.3.1, 1.2.0, 1.1.1 and 1.0.6, with the same checks as 1.0.1 at this release's code. The README's tables give the PHP and database of each.
- Adobe's Marketplace installation and Varnish test, reproduced on Magento Open Source 2.4.9 (PHP 8.5, Varnish 8) from the Marketplace package: Composer install, Magento install, compile, static content deploy, production mode, full reindex, and the full-page cache checks before and after price updates over REST.

## 1.0.1 - 2026-11-03

### Fixed

- On Mage-OS 1.1.0 to 2.2.2, `module:uninstall BigCartEnergy_CatalogCompleteness --remove-data` left the store unable to generate the interceptors of the product repository and the product action, at the next request in developer mode and in `setup:di:compile`: the compiled-interceptor package those releases bundle keeps plugin lists in `generated/staticcache`, which Magento's cleanup never empties. The uninstall now deletes the cached lists that name this module's plugins. Nothing changes on Magento Open Source or the other Mage-OS releases.

### Documentation

- Tested on the Mage-OS Distribution: 4.0.0 (PHP 8.4 and 8.5), 3.6.0, 3.5.0, 3.4.0, 3.3.0, 3.2.0, 3.1.0, 3.0.0, 2.3.0, 2.2.2, 2.1.0, 2.0.0, 1.3.1, 1.2.0, 1.1.1 and 1.0.6.

## 1.0.0 - 2026-10-28

### Added

- Rule sets: attribute set rules and channel profiles, with six rule types, three weights and product type scoping.
- Scores from 0 to 100 with four bands; the thresholds are settings.
- The `bce_completeness_score` indexer, working in Update on Save and Update by Schedule; deleted products never stop it.
- The dashboard: stat tiles, distribution, breakdown by attribute set, category, brand and product type, most-missing rules, the work list with search, filters, paging, the missing-rules popover and the mass actions Recalculate and Export selected.
- CSV export with formula-injection guarding and a row cap.
- The rule set editor with the rules table, live shares, inline validation, dirty tracking, the leave-page modal, read-only mode, a preview panel, and per-channel hints for Google Merchant Center, Microsoft Merchant Center, Meta, Pinterest, TikTok Shop, Amazon, eBay and Walmart Marketplace, plus a core content list.
- The preview panel on the product edit page with "Recalculate this product".
- The Completeness column on the product grid, sortable and range-filterable.
- Rule coverage on the dashboard: a Rule coverage select (Products with rules, or No rules assigned with the count), a notice with the count and a link, and a work list of the products no rule covers, with "No rules assigned" in the Score cell and the CSV; Recalculate leaves them alone and says why.
- In the No rules assigned view the work list's Band, Score and Last scored filters step aside, also when the grid keeps them from the scored view, so the list, Export selected and Recalculate work under any saved filter; when an export or a recalculation fails unexpectedly, the admin sees a fixed message and the error goes to the Magento exception log.
- Three REST routes and three read-only product extension attributes; every score has a state (`scored`, `no_rules_assigned`, `not_calculated`).
- The `bce:completeness:recalculate` command with a progress bar.
- The recalculation queue (topic `bce_completeness.recalculate`, consumer `bceCompletenessRecalculate`) and the dashboard's progress, stale and error states.
- The periodic rescore: the cron job `bce_completeness_rescore` in its own cron group `bce_completeness`, with settings for the switch, the schedule (every six hours by default), every product or only the products changed since the last run by `updated_at`, and the batch size, walking the catalog by product ID range and logging each run to `var/log/bce_completeness.log`.
- Settings under Stores > Configuration > Big Cart Energy > PIM: Catalog Completeness.
- The admin menu: Big Cart Energy > PIM: Catalog Completeness, with Dashboard, Rule sets and Settings; the same title on the module's group in User Roles and on its settings section.
