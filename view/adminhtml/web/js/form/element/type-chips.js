/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'underscore',
    'Magento_Ui/js/form/element/checkbox-set'
], function ($, _, CheckboxSet) {
    'use strict';

    /**
     * The product type chips of a row: a multi-checkbox set drawn as toggle buttons, all on by default.
     * On checkbox-set the options are a plain array, not an observable.
     */
    return CheckboxSet.extend({
        defaults: {
            multiple: true,
            elementTmpl: 'BigCartEnergy_CatalogCompleteness/form/element/type-chips'
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();

            if (_.isEmpty(this.value())) {
                this.value(_.pluck(this.options || [], 'value'));
            }

            return this;
        },

        /**
         * @param {String} typeId
         * @returns {Boolean}
         */
        isOn: function (typeId) {
            return _.indexOf(_.map(this.value() || [], String), String(typeId)) !== -1;
        },

        /**
         * @param {String} typeId
         */
        toggleType: function (typeId) {
            var current = _.map(this.value() || [], String);

            if (this.disabled()) {
                return;
            }

            if (_.indexOf(current, String(typeId)) !== -1) {
                current = _.without(current, String(typeId));
            } else {
                current.push(String(typeId));
            }
            this.value(current);
            $(document).trigger('bce-completeness:rules-changed');
        }
    });
});
