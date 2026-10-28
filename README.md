<p><img src="docs/wheelie.svg" alt="" width="64" height="64"></p>

# Catalog Completeness: a PIM module for Magento Open Source

`bigcartenergy/module-catalog-completeness` is a free PIM module for Magento Open Source by Big Cart Energy: completeness scoring against weighted rules per attribute set, channel-readiness profiles, a work list of the products to fix first, CSV export and a REST API, all in the Magento admin. It does not syndicate feeds to any channel and holds no product data outside Magento: the catalog stays in Magento, and the module measures it.

In the admin it sits under Big Cart Energy > PIM: Catalog Completeness, with three screens: Dashboard, Rule sets and Settings.

## The problem

A catalog with thousands of SKUs is never finished. Descriptions stop at one line, a third of the products have one image, the GTIN column is half empty, and nobody can say which products to fix first. Shoppers bounce, feeds get disapproved, and the data entry list is a spreadsheet somebody made last year.

Catalog Completeness turns "our product data is patchy" into a number per product and a work list. You say what a complete product needs, in rules you weigh. Every product gets a score from 0 to 100, a band (Complete, Good, Needs work, Poor) and the list of rules it fails. The dashboard shows where the gaps are by attribute set, category, brand and product type, which rules fail most, and the products to fix first. Export the list as CSV and hand it to whoever does the data entry.

Channel profiles do the same for a sales channel: a Google Merchant Center profile scores every product against what that feed needs (brand, GTIN, condition, images, and so on), so "ready for Google" is a number you can read without opening a feed report. The module never connects to a channel or sends a feed; it only measures.

Nothing comes pre-set. You create the rule sets and choose which of your attributes belong to them, one rule at a time. The editor suggests the fields each channel commonly requires, and nothing is added until you click Add.

## What it does

- **Rule sets with weighted rules.** Attribute set rules cover the attribute sets you pick; a channel profile covers every product. A rule checks one attribute with one of six rule types: Present, Minimum length, Minimum count (images), Greater than 0, One of the allowed values, Matches a pattern. Each rule is Required (counts 3), Important (2) or Nice to have (1), and names the product types it applies to (all six by default).
- **The score.** `round(100 × passed weight / total weight)`, over the rules that apply to the product's type. It reads 100 only when every applicable rule passes; a product one rule short never rounds up to 100. A product that no rule applies to has no score, shows "No rules assigned" (see Rule coverage below), and stays out of the averages. Bands: Complete at 100, Good from 80, Needs work from 50, Poor below; the two thresholds are settings.
- **Rule semantics.** Present means non-empty after trimming spaces: `0` is a value on a number or Yes/No field, a dropdown needs an option, a multiselect needs at least one value. Minimum length counts the trimmed text, with HTML tags stripped on description, short description and other text editor attributes. Minimum count counts enabled gallery images (hidden images do not count). Greater than 0 reads the decimal. Allowed values matches the option label or the stored value, case-insensitively, against your comma list. Pattern applies a PCRE body (no delimiters, no modifiers), checked when you save; a pattern that errors at run time fails its rule and nothing else. An attribute missing from a product's attribute set fails its rule for that product.
- **The dashboard.** Five tiles (average, products scored, Complete, Needs work or Poor, the rule failing most), each a filter; the score distribution; a breakdown by attribute set, category, brand or product type sorted lowest average first; the ten most-missing rules; the work list with keyword search, filters, 20 to 200 rows a page, a hover popover with the full missing list, and the mass actions Recalculate and Export selected. The filter bar (attribute set, category, brand, product type, band, enabled products only) applies to everything on the page.
- **Rule coverage.** Products that no rule covers carry no score, so they would otherwise not show at all. The Rule coverage select beside the Rule set select switches the page between the scored products and the products with no rules assigned: in the attribute set rules, those whose attribute set no enabled attribute set rule set covers, or whose product type none of its rules applies to; in a channel profile, those whose product type none of its rules applies to. Its option shows the count, and a notice above the tiles gives the count with a link whenever there are any. In that view the work list lists them with the Score cell reading "No rules assigned", the filters on the products themselves still apply (band, tiles and the work list's Score and Last scored filters need a score and step aside, also when the grid keeps them from the scored view), Export CSV and Export selected list them with "No rules assigned" in the Band column, and Recalculate rescores nothing and says why. Coverage is read from the rule sets as they are, so it holds before any recalculation.
- **CSV export.** The filtered work list, or the rows you tick, with every missing rule per product. Cells that start with `=`, `+`, `-`, `@`, a tab or a carriage return get a leading quote, so a spreadsheet opens them as text. A row cap in the settings bounds the file.
- **The product page panel.** On every product edit page: the score and band, each missing rule with a link to the field that fixes it, the rules it passes, a select for the channel profiles, and "Recalculate this product".
- **The product grid.** A Completeness column with the band beside the score. It sorts and filters by range.
- **Kept current by an indexer.** `bce_completeness_score` ("Catalog completeness score" in Index Management) works in both modes. Update on Save rescores a product when it is saved, mass-updated or imported. Update by Schedule follows the product and attribute value tables through the standard changelog; a deleted product is handled by deleting its rows, never by stopping the indexer.
- **REST and extension attributes.** Three routes (below) and three read-only extension attributes on products, `bce_completeness_score`, `bce_completeness_missing` and `bce_completeness_state`. Every score carries a state, so a missing score always says why. Nothing accepts a score from the caller.

Out of scope at launch: per-store-view scoring, GraphQL, storefront output of any kind, Adobe Commerce (see the FAQ).

## Requirements

- Composer accepts Magento Open Source 2.3.0 to 2.4.9 on PHP 7.1 to 8.5 (the code uses PHP 7.1 syntax throughout). The lines tested so far are listed below; any other line is untested, so test on staging first. On 2.3.0 to 2.3.4, Magento itself does not honour the template guard this module relies on (see Compatibility). Magento 2.0 to 2.2 are not supported. Adobe Commerce is not supported at launch (see the FAQ).
- `bigcartenergy/module-core`, installed by composer with this package. It owns the Big Cart Energy menu item and configuration tab and does nothing on its own.

### Compatibility

Every framework API the module uses (declarative schema, the materialized view indexer, the database message queue, UI component forms, listings and dynamic rows, the REST routes, extension attributes) exists from 2.3.0 on, and the code was checked class by class against the 2.3.0, 2.3.5-p2, 2.3.7-p4, 2.4.8 and 2.4.9 sources. Two things differ by line and are handled in the module: the templates escape through the block (the `$escaper` variable only exists from 2.4.0), and the `__disableTmpl` guard on admin-controlled labels is honoured by Magento from 2.3.5 (earlier lines ignore it, as they do for core's own guards). Nothing branches on a version string.

### Tested versions

A version that is not in this table has not been tested. Use at your own risk and test on staging first.

| Magento Open Source | PHP | Database | Result |
| --- | --- | --- | --- |
| 2.4.9 | 8.5 | MySQL 8.4 | Pass: install, admin pages, REST, indexer in both modes, queue, CSV, mass actions, the periodic rescore from Magento cron, uninstall with a product save afterwards, unit tests in place |
| 2.4.8 | 8.3 | MySQL 8.4 | Pass: the same checks |
| 2.4.7 | 8.2 | MySQL 8.0 | Pass: the same checks |
| 2.4.6 | 8.1 | MySQL 8.0 | Pass: the same checks |
| 2.3.7-p4 | 7.4 | MySQL 5.7 | Pass: the same checks |

## Install

```
composer require bigcartenergy/module-catalog-completeness
bin/magento module:enable BigCartEnergy_Core BigCartEnergy_CatalogCompleteness --clear-static-content
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

`setup:upgrade` creates six tables, all prefixed `bce_completeness_` (rule sets, their attribute sets, rules, scores, failures, runs). It creates no rule set, no product attribute, no configuration row and no data; the settings' defaults live in the module. The indexer's changelog table `bce_completeness_score_cl` exists only while the indexer is in Update by Schedule (see below).

In production mode also run `bin/magento setup:static-content:deploy` after the compile.

## Set up

1. **Rule sets.** Big Cart Energy > PIM: Catalog Completeness > Rule sets > Create a rule set. Pick the kind: attribute set rules (choose the attribute sets; each attribute set can sit in only one rule set of this kind) or channel profile (choose the channel). Add rules one at a time, or click Add on a suggestion beside the table; each added row has its role, rule type and parameter filled and its attribute left for you to choose. The footer shows each rule's share of the score as you weigh them. The preview panel scores any product you search for with the rules on the page, unsaved edits included.
2. **Save** stores the rules and marks the scores out of date. **Save and recalculate** also queues a rescore of every product the rule set covers.
3. **Settings.** Big Cart Energy > PIM: Catalog Completeness > Settings, which opens Stores > Configuration > Big Cart Energy > PIM: Catalog Completeness: the brand attribute for the dashboard's brand grouping (default `manufacturer`), whether the dashboard shows enabled products only by default, the work list page size, the CSV row cap, the two band thresholds, and the periodic rescore (below).

## Keeping scores current

The indexer `bce_completeness_score` works in either mode. Which mode it starts in depends on the Magento version: 2.4.8 and 2.4.9 put a new indexer in Update by Schedule, 2.4.7 and earlier leave it in Update on Save whatever the other indexers use. Check it after the install and set the mode you want:

```
bin/magento indexer:show-mode bce_completeness_score
bin/magento indexer:set-mode schedule bce_completeness_score   # or: realtime
```

- **Update on Save** (`realtime`): a product save, a mass attribute update or an import rescores the products touched, in the same request. No changelog table, no triggers.
- **Update by Schedule** (`schedule`): switching to this mode creates the changelog table `bce_completeness_score_cl` and adds the module's statements to the catalog tables' triggers; changes are then collected there and applied by Magento's indexer cron. Make sure cron runs.

**Recalculate** on the dashboard queues a run on the `bce_completeness.recalculate` topic (database queue); the REST recalculate route rescores its one product at once instead (see REST). Magento's `consumers_runner` cron starts the consumer `bceCompletenessRecalculate`; to run it yourself:

```
bin/magento queue:consumers:start bceCompletenessRecalculate --single-thread --max-messages=10
```

The dashboard's status line counts the products done while a run is active. When cron is not available, recalculate from the command line; it runs in the foreground with a progress bar:

```
bin/magento bce:completeness:recalculate                 # every product
bin/magento bce:completeness:recalculate --ruleset=3     # one rule set
bin/magento bce:completeness:recalculate --product=1042  # one product
```

### Periodic rescore

Product saves, mass attribute updates and imports rescore the products they touch. A change that bypasses all three, such as an ERP sync, a script or a migration writing straight to the database, reaches the scores only when something rescores them. In Update by Schedule the indexer's triggers already follow direct writes to the product and attribute value tables; the periodic rescore also covers Update on Save, the stock status and every table the triggers do not watch. It runs from Magento cron as the job `bce_completeness_rescore`, in its own cron group `bce_completeness`, which runs in a separate process like Magento's indexer group.

The settings are under Stores > Configuration > Big Cart Energy > PIM: Catalog Completeness, in the Periodic rescore group:

| Setting | Default | What it does |
| --- | --- | --- |
| Rescore on a schedule | Yes | Turns the rescore on or off. When it is off, the job stays in Magento's cron schedule and ends at once. |
| Schedule | `0 */6 * * *`, every six hours | A cron expression in five fields. Each field takes `*`, a number, a range such as `1-5`, a list such as `0,30`, or a step on `*` such as `*/15`; month and day names (`jan`, `mon`) count as numbers. The save refuses anything else, so a typo cannot stop Magento's cron from planning the group. |
| Products to rescore | Every product | Every product, or only the products whose `updated_at` moved since 15 minutes before the last run started. Magento's own saves and imports set `updated_at`; many direct database writes do not, so choose the changed-since mode only when your sync sets it too. A run with no earlier run on record rescores every product. |
| Products per batch | 500 | How many products a run loads and scores at a time, one product ID range after another, from 50 to 5000. Lower it if cron runs out of memory. |

Each run writes one line to `var/log/bce_completeness.log`: what it covered, the products scored, the batches and the duration, for example `Periodic rescore (every product): 18420 products in 37 batches of up to 500, 41.2 s.` A run that fails logs how many products it scored before the error, and Magento marks the cron job as failed. The start of the last completed run is kept in Magento's `flag` table under `bce_completeness_rescore`. The dashboard's Recalculate, the command and the indexer work as before.

## Channel profiles and hints

A channel profile scores every product against roles (Brand, GTIN, Condition, ...) mapped to your attributes. Hints exist for Google Merchant Center, Microsoft Merchant Center, Meta (Facebook and Instagram Shops), Pinterest, TikTok Shop, Amazon, eBay and Walmart Marketplace. Each list was written from the channel's own documentation; `docs/channels.md` names the page and the date it was read. Channels change their rules, so treat a hint as a starting point and the channel's current specification as the authority.

Category-specific requirements (Amazon product type attributes, eBay item specifics, and the like) are not hinted: add them as rules once you know which categories you list in. For a distributor feed or any other channel, create a Custom profile.

## REST

Admin token with the module's ACL resources.

| Route | Does |
| --- | --- |
| `GET /V1/bce-completeness/products/:productId/scores` | The stored scores of one product, one entry per rule set that applies to it, with band and missing rule labels |
| `GET /V1/bce-completeness/rulesets` | Every rule set with its rules |
| `POST /V1/bce-completeness/products/:productId/recalculate` | Rescores one product now and returns its scores |

Every score entry has a `state`: `scored`; `no_rules_assigned` when no rule applies to the product; or `not_calculated` when rules apply and no score has been calculated yet. An entry without a score has no `score`, `band`, `band_label` or `scored_at` key at all, because Magento's REST output leaves out null values, so read `state` rather than testing `score` for null. When no score comes from the attribute set rules, the scores and recalculate routes start with one entry for them with `is_primary` true, `ruleset_id` 0 and the state that says why, followed by the channel profiles' entries.

Products loaded through the product repository in the admin and over REST carry the read-only extension attributes `bce_completeness_score` (the attribute set rule set's score, left out when there is none), `bce_completeness_missing` (labels of the rules it fails) and `bce_completeness_state` (the same three states for that score). Sending them on a product save does nothing.

## Permissions

Under Big Cart Energy > PIM: Catalog Completeness in User Roles: Dashboard, Rule sets (view), Edit rule sets, Recalculate scores, Export CSV; the settings section, PIM: Catalog Completeness Section, under Stores > Settings > Configuration. A user with Rule sets but not Edit rule sets opens the editor read-only.

## What it touches in a store

- Reads product attribute values (admin scope), gallery images and the stock status. Writes scores to its own tables only; product data is never changed.
- No storefront output: no layout, script or storefront query. Shoppers never see a score.
- No outbound connection. Nothing leaves the store.
- Database tables: `bce_completeness_ruleset`, `bce_completeness_ruleset_attribute_set`, `bce_completeness_rule`, `bce_completeness_score`, `bce_completeness_failure`, `bce_completeness_run`.
- Cron: the job `bce_completeness_rescore` in the cron group `bce_completeness`, one row in the `flag` table, and its own log file, `var/log/bce_completeness.log`.

## Remove

```
bin/magento module:disable BigCartEnergy_CatalogCompleteness
composer remove bigcartenergy/module-catalog-completeness
bin/magento setup:upgrade
bin/magento setup:di:compile   # production mode
bin/magento cache:clean
```

**The Core module.** `bigcartenergy/module-core` is removable on its own once no other Big Cart Energy module requires it. When Catalog Completeness was the only one, composer removes `module-core` with it as an orphaned dependency, and `setup:upgrade` drops the `BigCartEnergy_Core` entry from `app/etc/config.php` because the module's code is gone. To be explicit, name both: `bin/magento module:disable BigCartEnergy_CatalogCompleteness BigCartEnergy_Core` and `composer remove bigcartenergy/module-catalog-completeness bigcartenergy/module-core`.

**The data.** The removal above keeps the six `bce_completeness_*` tables and the module's configuration values: declarative schema drops a table only when an installed module's `db_schema_whitelist.json` lists it and no enabled module's `db_schema.xml` declares it, and `composer remove` takes the whitelist away with the code before `setup:upgrade` runs. Do not run `setup:upgrade` while the module is disabled but still installed: Magento reads table declarations only from enabled modules but whitelists from every installed one, so that `setup:upgrade` drops the six tables, with the rule sets and scores in them. To drop them on purpose, run the uninstall command before removing the package; it runs the module's `Uninstall` class, which first takes the indexer out of Update by Schedule (so the catalog tables' triggers no longer mention the module and the changelog table goes with them), then drops the six tables and removes the indexer, queue, configuration, cron schedule and flag rows, and finally removes the package with composer:

```
bin/magento module:uninstall BigCartEnergy_CatalogCompleteness --remove-data
```

`module:uninstall` works for packages installed by composer, which is the case here, and it uninstalls this module only. The Core module stays installed and enabled while something else requires `bigcartenergy/module-core`: another Big Cart Energy module, or the store's own `composer.json`. When Catalog Completeness was installed alone, as under Install, composer removes `module-core` with it as an orphaned dependency, as in the removal above; the `--no-update-with-dependencies` that Magento passes to composer does not keep it. To keep the Core module, run `composer require bigcartenergy/module-core` before the uninstall. Product saves keep working during and after the uninstall in either indexer mode. The log file `var/log/bce_completeness.log` stays; delete it by hand if you like.

## Installation and configuration service

Big Cart Energy installs the module, writes the rule sets and channel profiles with you, and tunes the thresholds for your catalog. The documentation and the installation service are at https://bigcartenergy.com/tools/catalog-completeness.

## FAQ

**Adobe Commerce?** Not at launch. The module is built for Magento Open Source, which keys product data by `entity_id`. Adobe Commerce keys its versioned product data by `row_id`, and its product tables are structured so that the module's foreign keys and indexer subscriptions would not work unchanged. The module's own tables are designed so that support can be added later.

**Does it change product data?** No. It reads product attributes and gallery images and writes scores to its own tables.

**Does it slow the admin?** A product save in Update on Save mode scores that product (a handful of queries). A full recalculation walks the catalog in chunks of 500 in the background. The periodic rescore runs from cron in its own process, every six hours by default, one batch of 500 products at a time. Nothing runs on the storefront.

## Licence

Open Software License 3.0 (OSL-3.0), the same licence as Magento Open Source. See [LICENSE.txt](https://github.com/bigcartenergy/module-catalog-completeness/blob/main/LICENSE.txt).

Repository: https://github.com/bigcartenergy/module-catalog-completeness · Issues: https://github.com/bigcartenergy/module-catalog-completeness/issues · Documentation: https://bigcartenergy.com/tools/catalog-completeness

Use at your own risk. Test on staging before production.

Copyright (c) Big Cart Energy. https://bigcartenergy.com
