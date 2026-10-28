# Changelog

All notable changes to `bigcartenergy/module-catalog-completeness` by Big Cart Energy.

## Unreleased

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
