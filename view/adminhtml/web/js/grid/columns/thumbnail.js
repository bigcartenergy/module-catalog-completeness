/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'Magento_Ui/js/grid/columns/thumbnail'
], function (Thumbnail) {
    'use strict';

    /**
     * The work list thumbnail: the product image, or a plain dashed box when the product has none
     * (instead of the catalog's placeholder picture).
     */
    return Thumbnail.extend({
        defaults: {
            bodyTmpl: 'BigCartEnergy_CatalogCompleteness/grid/cells/thumbnail'
        },

        /**
         * @param {Object} row
         * @returns {Boolean}
         */
        hasImage: function (row) {
            var value = row[this.index];

            return !!value && value !== 'no_selection';
        }
    });
});
