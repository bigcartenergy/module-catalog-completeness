/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'ko',
    'Magento_Ui/js/grid/columns/column',
    'mage/translate'
], function ($, ko, Column, $t) {
    'use strict';

    /**
     * The Missing column: three tags, "+N", and a popover with every missing rule. The popover opens when the
     * row is hovered or focused (the cell itself included) and closes on leave, blur or Escape.
     */
    return Column.extend({
        defaults: {
            bodyTmpl: 'BigCartEnergy_CatalogCompleteness/grid/cells/missing',
            rowSelector: '#bce-completeness-worklist tr.data-row',
            openRow: null
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            this.initRowEvents();

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe(['openRow']);

            return this;
        },

        /**
         * Open and close from the whole row, not only the Missing cell.
         */
        initRowEvents: function () {
            var self = this,
                selector = this.rowSelector;

            $(document)
                .on('mouseenter focusin', selector, function () {
                    var row = self.rowOf(this);

                    if (row) {
                        self.open(row);
                    }
                })
                .on('mouseleave', selector, function () {
                    self.close();
                })
                .on('focusout', selector, function (event) {
                    if (!event.relatedTarget || !$.contains(this, event.relatedTarget)) {
                        self.close();
                    }
                })
                .on('keydown', selector, function (event) {
                    if (event.key === 'Escape') {
                        self.close();
                    }
                });
        },

        /**
         * The row data bound to a table row element.
         *
         * @param {HTMLElement} element
         * @returns {Object|null}
         */
        rowOf: function (element) {
            var context = ko.contextFor(element),
                row = context ? context.$row : null;

            if (ko.isObservable(row)) {
                row = row();
            }

            return row || null;
        },

        /**
         * @param {Object} row
         * @returns {Array}
         */
        getTags: function (row) {
            return row[this.index + '_tags'] || [];
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        getMore: function (row) {
            return row[this.index + '_more'] || '';
        },

        /**
         * @param {Object} row
         * @returns {Number}
         */
        getCount: function (row) {
            return row[this.index + '_count'] || 0;
        },

        /**
         * @param {Object} row
         * @returns {Array}
         */
        getAll: function (row) {
            return row[this.index + '_all'] || [];
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        getPopoverTitle: function (row) {
            var count = this.getCount(row);

            return count === 1 ? $t('1 missing rule') : $t('%1 missing rules').replace('%1', count);
        },

        /**
         * @param {Object} row
         */
        open: function (row) {
            if (this.getCount(row) > 0) {
                this.openRow(row._rowIndex);
            }
        },

        close: function () {
            this.openRow(null);
        },

        /**
         * @param {Object} row
         * @returns {Boolean}
         */
        isOpen: function (row) {
            return this.openRow() === row._rowIndex;
        }
    });
});
