/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'ko',
    'underscore',
    'uiComponent',
    'uiRegistry',
    'BigCartEnergy_CatalogCompleteness/js/band',
    'mage/translate'
], function ($, ko, _, Component, registry, band, $t) {
    'use strict';

    var PROVIDER = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form_data_source',
        STORAGE_KEY = 'bce_completeness.panel.collapsed';

    /**
     * The preview panel. In the editor it scores a searched product with the rules on the page, unsaved
     * included. On the product edit page it scores the product with a stored rule set, links each missing
     * rule to its field, and offers "Recalculate this product".
     *
     * The page passes its settings as "panelConfig" (a "config" key would be merged into the component by
     * the layout and vanish as a property).
     */
    return Component.extend({
        defaults: {
            context: 'editor',
            panelConfig: {},
            markSvg: '',
            state: 'empty',
            collapsed: false,
            query: '',
            results: [],
            product: null,
            rulesetId: null,
            result: null,
            error: '',
            unsaved: false,
            busy: false,
            searchTimer: null
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this,
                settings;

            this._super();
            settings = this.settings();

            if (this.isProduct()) {
                this.product({id: settings.productId});
                this.rulesetId(settings.ruleSets && settings.ruleSets.length ? String(settings.ruleSets[0].id) : null);
                this.collapsed(!!window.localStorage && window.localStorage.getItem(STORAGE_KEY) === '1');
                this.rulesetId.subscribe(function () {
                    self.load();
                });
                this.load();
            } else {
                this.state(this.hasRules() ? 'empty' : 'norules');
                // The form is a separate component tree: with cached scripts this panel can start before the form's
                // data source is registered, so read the saved rules again once it is.
                registry.get(PROVIDER, function () {
                    if (self.state() === 'norules' && self.hasRules()) {
                        self.state('empty');
                    }
                });
                $(document).on('bce-completeness:dirty', function (event, data) {
                    self.unsaved(!!data.dirty);
                });
                $(document).on('bce-completeness:rules-changed', function () {
                    if (!self.hasRules()) {
                        self.state('norules');
                        self.result(null);
                    } else if (self.product()) {
                        self.load();
                    } else if (self.state() === 'norules') {
                        self.state('empty');
                    }
                });
            }

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe(['state', 'collapsed', 'query', 'results', 'product', 'rulesetId', 'result', 'error', 'unsaved', 'busy']);

            this.query.subscribe(this.onQuery, this);

            return this;
        },

        /**
         * The page settings.
         *
         * @returns {Object}
         */
        settings: function () {
            return this.panelConfig || {};
        },

        /**
         * @returns {Array}
         */
        ruleSets: function () {
            return this.settings().ruleSets || [];
        },

        /**
         * @returns {Boolean}
         */
        canRecalculate: function () {
            return !!this.settings().canRecalculate;
        },

        /**
         * @returns {Boolean}
         */
        isProduct: function () {
            return this.context === 'product';
        },

        /**
         * @returns {Boolean}
         */
        isEditor: function () {
            return this.context === 'editor';
        },

        /**
         * The rows of the rules table as the server expects them: a flat list.
         *
         * @returns {Array}
         */
        currentRules: function () {
            var provider = registry.get(PROVIDER),
                rows = provider ? provider.get('data.rules') : [];

            rows = _.isArray(rows) ? rows : _.values(rows || {});

            return _.filter(_.map(rows, function (row) {
                return row ? _.pick(row, 'rule_id', 'role', 'attribute_code', 'rule_type', 'parameter', 'weight', 'applies_to') : null;
            }), function (row) {
                return row && (row.attribute_code || row.rule_type);
            });
        },

        /**
         * @returns {Boolean}
         */
        hasRules: function () {
            return this.currentRules().length > 0;
        },

        /**
         * @returns {String}
         */
        currentRuleSetName: function () {
            var id = this.rulesetId(),
                found = _.find(this.ruleSets(), function (set) {
                    return String(set.id) === String(id);
                });

            return found ? found.name : '';
        },

        toggle: function () {
            this.collapsed(!this.collapsed());

            if (window.localStorage) {
                window.localStorage.setItem(STORAGE_KEY, this.collapsed() ? '1' : '0');
            }
        },

        /**
         * Debounced product search by SKU or name.
         */
        onQuery: function () {
            var self = this,
                query = this.query();

            window.clearTimeout(this.searchTimer);

            if (!query || query.length < 2) {
                this.results([]);

                return;
            }

            this.searchTimer = window.setTimeout(function () {
                $.getJSON(self.settings().searchUrl, {q: query, isAjax: true}).done(function (response) {
                    self.results(response.items || []);
                });
            }, 300);
        },

        /**
         * @param {Object} item
         */
        pick: function (item) {
            this.product(item);
            this.results([]);
            this.query('');
            this.load();
        },

        clear: function () {
            this.product(null);
            this.result(null);
            this.state(this.hasRules() ? 'empty' : 'norules');
        },

        /**
         * Fetch the preview for the current product.
         */
        load: function () {
            var self = this,
                product = this.product(),
                payload;

            if (!product) {
                return;
            }

            payload = {
                'form_key': window.FORM_KEY,
                'product_id': product.id,
                isAjax: true
            };

            if (this.isProduct()) {
                payload['ruleset_id'] = this.rulesetId();
            } else {
                payload.rules = this.currentRules();
                payload.kind = this.formValue('kind') || 'attribute_set';
                payload['channel_code'] = this.formValue('channel_code') || '';
            }

            this.state('loading');
            this.error('');
            $.post(this.settings().previewUrl, payload)
                .done(function (data) {
                    self.result(data);
                    self.product(_.extend({}, self.product(), data.product || {}));
                    self.state('scored');
                })
                .fail(function (xhr) {
                    self.error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : $t('The preview could not be calculated.'));
                    self.state('error');
                });
        },

        /**
         * @param {String} key
         * @returns {*}
         */
        formValue: function (key) {
            var provider = registry.get(PROVIDER);

            return provider ? provider.get('data.' + key) : null;
        },

        /**
         * Rescore this product now (product page only), then reload the stored result.
         */
        recalculate: function () {
            var self = this;

            if (!this.canRecalculate() || this.busy()) {
                return;
            }
            this.busy(true);
            $.post(this.settings().recalculateUrl, {
                'form_key': window.FORM_KEY,
                'product_id': this.product().id,
                isAjax: true
            }).always(function () {
                self.busy(false);
                self.load();
            });
        },

        /**
         * Scroll to the field of a missing rule and open its fieldset.
         *
         * @param {Object} row
         */
        goToField: function (row) {
            this.revealField(row.attribute_code, 0);
        },

        /**
         * Find a product form field by attribute code. A collapsed section renders its fields only once it is
         * opened, so when the field is absent the next closed section is opened and the search retried.
         *
         * @param {String} code
         * @param {Number} attempt
         */
        revealField: function (code, attempt) {
            var self = this,
                $field = $('.product-form [data-index="' + code + '"], [data-index="' + code + '"]').first(),
                $closed;

            if ($field.length) {
                $closed = $field.closest('.fieldset-wrapper').find('> .fieldset-wrapper-title[data-state-collapsible="closed"]');

                if ($closed.length) {
                    $closed.first().trigger('click');
                }
                window.setTimeout(function () {
                    $field[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                    self.focusControl($field);
                }, 250);

                return;
            }

            if (attempt >= 12) {
                return;
            }
            $closed = $('.fieldset-wrapper-title[data-state-collapsible="closed"]').not('[data-bce-opened]').first();

            if (!$closed.length) {
                return;
            }
            $closed.attr('data-bce-opened', '1').trigger('click');
            window.setTimeout(function () {
                self.revealField(code, attempt + 1);
            }, 250);
        },

        /**
         * Focus a field's control: the WYSIWYG editor through its API when the textarea has one, else the
         * first input, textarea or select.
         *
         * @param {jQuery} $field
         */
        focusControl: function ($field) {
            var $textarea = $field.find('textarea').first(),
                id = $textarea.attr('id'),
                editor = id && window.tinymce && _.isFunction(window.tinymce.get) ? window.tinymce.get(id) : null;

            if (editor && _.isFunction(editor.focus)) {
                editor.focus();

                return;
            }
            $field.find('input, textarea, select').first().trigger('focus');
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        fieldLabel: function (row) {
            return $t('Edit %1').replace('%1', row.attribute_label);
        },

        /**
         * @returns {String}
         */
        bandClass: function () {
            var result = this.result();

            return result ? band.chipClass(result.band) : 'bce-completeness-chip';
        },

        /**
         * @returns {String}
         */
        bandIcon: function () {
            var result = this.result();

            return result ? band.iconPath(result.band) : '';
        },

        /**
         * @returns {String}
         */
        barClass: function () {
            var result = this.result();

            return result && result.band ? 'bce-completeness-dist__bar--' + result.band : '';
        },

        /**
         * @returns {String}
         */
        summaryLine: function () {
            var result = this.result();

            if (!result) {
                return '';
            }

            return $t('%1 missing · %2 passed')
                .replace('%1', result.missing_count)
                .replace('%2', result.passed_count);
        },

        /**
         * @param {Number} count
         * @returns {String}
         */
        skippedLabel: function (count) {
            return Number(count) === 1
                ? $t('1 incomplete rule was skipped.')
                : $t('%1 incomplete rules were skipped.').replace('%1', count);
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        shareLabel: function (row) {
            return row.weight_label + ' · ' + row.share + ' %';
        }
    });
});
