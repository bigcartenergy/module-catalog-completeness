/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'ko',
    'underscore',
    'Magento_Ui/js/dynamic-rows/record'
], function ($, ko, _, Record) {
    'use strict';

    /**
     * One rule row: its share of the score, the "Edited" tag, its errors and the missing-attribute warning.
     * The computed values are created after the parent initialisation, once parentComponent and the data
     * source exist.
     */
    return Record.extend({
        defaults: {
            template: 'BigCartEnergy_CatalogCompleteness/form/rule-row',
            initialSnapshot: null,
            tick: 0
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();
            this.initialSnapshot = this.snapshot();
            this.initComputed();
            $(document).on('bce-completeness:rules-changed', function () {
                self.tick(self.tick() + 1);
            });

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe(['tick']);

            return this;
        },

        /**
         * The row's derived values. Each one re-reads on every rules-changed tick.
         */
        initComputed: function () {
            this.hasWeight = ko.computed(function () {
                this.tick();

                return [1, 2, 3].indexOf(parseInt(this.rowValue('weight'), 10)) !== -1;
            }, this);

            this.share = ko.computed(function () {
                var parent = this.rulesTable(),
                    weightField = this.getChild('weight'),
                    weight,
                    total;

                this.tick();

                if (weightField && weightField.value) {
                    weightField.value();
                }
                weight = parseInt(this.rowValue('weight'), 10);

                if (!parent || !this.hasWeight()) {
                    return '0 %';
                }
                total = parent.totalWeight();

                return (total > 0 ? Math.round(100 * weight / total) : 0) + ' %';
            }, this);

            this.isEdited = ko.computed(function () {
                this.tick();

                return this.initialSnapshot === null || this.snapshot() !== this.initialSnapshot;
            }, this);

            this.errors = ko.computed(function () {
                var errors = [];

                this.tick();
                _.each(this.elems(), function (elem) {
                    var message = elem.error ? elem.error() : '';

                    if (message) {
                        errors.push({field: elem.index, message: message});
                    }
                });

                return errors;
            }, this);

            this.warning = ko.computed(function () {
                var picker = this.getChild('attribute_code');

                this.tick();

                return picker && picker.warningLine ? picker.warningLine() : '';
            }, this);
        },

        /**
         * The rules table (the dynamic rows collection), once it is registered. Not named "parent": the
         * dynamic rows set a "parent" string on every record.
         *
         * @returns {Object|null}
         */
        rulesTable: function () {
            return _.isFunction(this.parentComponent) ? this.parentComponent() || null : null;
        },

        /**
         * Re-run the validation of every field that currently shows an error, so a message that no longer
         * applies (a duplicate that was removed, for example) goes away.
         */
        revalidate: function () {
            _.each(this.elems(), function (elem) {
                if (elem.error && elem.error() && _.isFunction(elem.validate)) {
                    elem.validate();
                }
            });
        },

        /**
         * @param {String} key
         * @returns {*}
         */
        rowValue: function (key) {
            var row = this.source ? this.source.get(this.dataScope) : null;

            return row && row[key] !== undefined ? row[key] : null;
        },

        /**
         * @returns {String}
         */
        snapshot: function () {
            var row = _.omit(this.source ? this.source.get(this.dataScope) || {} : {}, 'record_id', 'position', 'initialize'),
                keys = ['rule_id', 'role', 'attribute_code', 'rule_type', 'parameter', 'weight', 'applies_to'],
                out = {};

            _.each(keys, function (key) {
                var value = row[key];

                if (value === null || value === undefined) {
                    value = '';
                }
                out[key] = _.isArray(value) ? _.map(value, String).sort().join(',') : String(value);
            });

            return JSON.stringify(out);
        }
    });
});
