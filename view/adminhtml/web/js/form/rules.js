/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'ko',
    'underscore',
    'uiRegistry',
    'Magento_Ui/js/dynamic-rows/dynamic-rows',
    'BigCartEnergy_CatalogCompleteness/js/form/validation',
    'mage/translate'
], function ($, ko, _, registry, DynamicRows, validator, $t) {
    'use strict';

    var KIND = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.kind',
        CHANNEL = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.channel_code';

    /**
     * The rules table: a dynamic rows component with live shares, the footer sentence, duplicate checks,
     * keyboard reordering and the validation summary. Rows are removed outright (no soft delete flag).
     * Its data lives at data.rules as a flat list, the shape the save controller reads.
     */
    return DynamicRows.extend({
        defaults: {
            template: 'BigCartEnergy_CatalogCompleteness/form/rules',
            addButton: false,
            columnsHeader: false,
            deleteProperty: false,
            pageSize: 200,
            kind: 'attribute_set',
            channel: null,
            changeTick: 0
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();

            registry.get(KIND, function (kind) {
                self.kind(kind.value());
                kind.value.subscribe(function (value) {
                    self.kind(value);
                });
            });
            registry.get(CHANNEL, function (channel) {
                self.channel(channel.value());
                channel.value.subscribe(function (value) {
                    self.channel(value);
                });
            });
            $(document).on('bce-completeness:rules-changed', function () {
                self.changeTick(self.changeTick() + 1);
            });

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe(['kind', 'channel', 'changeTick']);

            this.hasRole = ko.computed(function () {
                return this.kind() === 'channel';
            }, this);

            this.totalWeight = ko.computed(function () {
                this.changeTick();

                return _.reduce(this.rows(), function (sum, row) {
                    return sum + this.weightOf(row);
                }, 0, this);
            }, this);

            this.requiredCount = ko.computed(function () {
                this.changeTick();

                return _.filter(this.rows(), function (row) {
                    return this.weightOf(row) === 3;
                }, this).length;
            }, this);

            this.requiredShare = ko.computed(function () {
                var total = this.totalWeight();

                return total > 0 ? Math.round(100 * this.requiredCount() * 3 / total) : 0;
            }, this);

            this.footerSentence = ko.computed(function () {
                var count = this.requiredCount(),
                    across = count === 1 ? $t('across 1 rule') : $t('across %1 rules').replace('%1', count);

                return $t('A product scores 100 when every rule passes. Required rules carry %1 % of the score %2.')
                    .replace('%1', this.requiredShare())
                    .replace('%2', across);
            }, this);

            this.countLabel = ko.computed(function () {
                var count = this.elems().length;

                if (!count) {
                    return $t('None yet');
                }

                return count === 1 ? $t('1 rule') : $t('%1 rules').replace('%1', count);
            }, this);

            this.summaryErrors = ko.computed(function () {
                var errors = [];

                this.changeTick();
                _.each(this.sortedRecords(), function (record, position) {
                    _.each(record.errors ? record.errors() : [], function (error) {
                        errors.push({
                            record: record,
                            field: error.field,
                            label: $t('Rule %1: %2').replace('%1', position + 1).replace('%2', error.message)
                        });
                    });
                });

                return errors;
            }, this);

            this.summaryHeadline = ko.computed(function () {
                var rows = _.uniq(_.pluck(this.summaryErrors(), 'record')).length;

                return rows === 1
                    ? $t('1 rule needs fixing before you can save')
                    : $t('%1 rules need fixing before you can save').replace('%1', rows);
            }, this);

            return this;
        },

        /**
         * The rows as plain data, whatever shape the provider holds them in.
         *
         * @returns {Array}
         */
        rows: function () {
            var data = this.recordData();

            return _.isArray(data) ? data : _.values(data || {});
        },

        /**
         * @param {Object} row
         * @returns {Number}
         */
        weightOf: function (row) {
            var weight = row && row.weight !== undefined && row.weight !== null ? parseInt(row.weight, 10) : 0;

            return [1, 2, 3].indexOf(weight) !== -1 ? weight : 0;
        },

        /**
         * Records in display order.
         *
         * @returns {Array}
         */
        sortedRecords: function () {
            return this.elems().slice().sort(function (a, b) {
                return ~~a.position - ~~b.position;
            });
        },

        /**
         * Add an empty row, or a row prefilled from a hint (attribute always left empty). The data is written
         * first; the record and its fields pick it up when they are created.
         *
         * @param {Object} data
         */
        addRow: function (data) {
            var self = this,
                index = this.rows().length,
                path = this.dataScope + '.' + this.index;

            _.each(data || {}, function (value, key) {
                self.source.set(path + '.' + index + '.' + key, value);
            });
            this.processingAddChild(false, index, index);
            $(document).trigger('bce-completeness:rules-changed');
        },

        /**
         * @param {Object} data
         */
        addFromHint: function (data) {
            this.addRow(data);
        },

        /**
         * Move a row up or down with the keyboard.
         *
         * @param {Object} record
         * @param {Number} delta
         */
        moveRecord: function (record, delta) {
            var sorted = this.sortedRecords(),
                index = sorted.indexOf(record),
                target = index + delta,
                other,
                position;

            if (index === -1 || target < 0 || target >= sorted.length) {
                return;
            }
            other = sorted[target];
            position = record.position;
            record.position = other.position;
            other.position = position;
            this.elems(this.sortedRecords());
            $(document).trigger('bce-completeness:rules-changed');
        },

        /**
         * @param {String} code
         * @param {Object} record
         * @returns {Boolean}
         */
        isDuplicateAttribute: function (code, record) {
            return this.firstRowWithAttribute(code, record) !== null;
        },

        /**
         * Position of an earlier row with the same attribute, or null. Only the later row of a pair is the
         * duplicate, so the first row keeps its attribute and the message names it.
         *
         * @param {String} code
         * @param {Object} record
         * @returns {Number|null}
         */
        firstRowWithAttribute: function (code, record) {
            var found = null;

            if (!code) {
                return null;
            }
            _.some(this.sortedRecords(), function (other, position) {
                var picker;

                if (other === record) {
                    return true;
                }
                picker = other.getChild ? other.getChild('attribute_code') : null;

                if (picker && picker.value() === code) {
                    found = position;

                    return true;
                }

                return false;
            });

            return found;
        },

        /**
         * Scroll to a row and focus the control of one of its fields (or its first control).
         *
         * @param {Object} record
         * @param {String} field
         */
        focusRow: function (record, field) {
            var $row = $('[data-bce-record="' + record.name + '"]'),
                $target;

            if (!$row.length) {
                return;
            }
            $target = field ? $row.find('[data-index="' + field + '"]').find('button, input, select').filter(':visible').first() : $();

            if (!$target.length) {
                $target = $row.find('button, input, select').filter(':visible').first();
            }
            $row[0].scrollIntoView({behavior: 'smooth', block: 'center'});
            $target.trigger('focus');
        },

        /**
         * Re-run the validation of every row that shows an error, so messages that no longer apply go away.
         */
        revalidateRows: function () {
            _.each(this.elems(), function (record) {
                if (_.isFunction(record.revalidate)) {
                    record.revalidate();
                }
            });
        },

        /**
         * @inheritdoc
         */
        deleteRecord: function (index, recordId) {
            var self = this;

            this._super(index, recordId);
            $(document).trigger('bce-completeness:rules-changed');
            _.defer(function () {
                self.revalidateRows();
                $(document).trigger('bce-completeness:rules-changed');
            });
        }
    });
});
