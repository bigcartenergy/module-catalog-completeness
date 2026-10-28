/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'BigCartEnergy_CatalogCompleteness/js/band'
], function (Column, band) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'BigCartEnergy_CatalogCompleteness/grid/cells/score',
            noScoreLabel: 'No rule set'
        },

        /**
         * Whether the row has a score at all.
         *
         * @param {Object} row
         * @returns {Boolean}
         */
        hasScore: function (row) {
            var value = row[this.index];

            return value !== null && value !== undefined && value !== '';
        },

        /**
         * The number as text.
         *
         * @param {Object} row
         * @returns {String}
         */
        getLabel: function (row) {
            return this.hasScore(row) ? String(row[this.index]) : '';
        },

        /**
         * Band code (complete, good, needs_work, poor) or an empty string.
         *
         * @param {Object} row
         * @returns {String}
         */
        getBand: function (row) {
            return row[this.index + '_band'] || '';
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        getBandLabel: function (row) {
            return row[this.index + '_band_label'] || '';
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        getIconPath: function (row) {
            return band.iconPath(this.getBand(row));
        },

        /**
         * @param {Object} row
         * @returns {String}
         */
        getChipClass: function (row) {
            return band.chipClass(this.getBand(row));
        }
    });
});
