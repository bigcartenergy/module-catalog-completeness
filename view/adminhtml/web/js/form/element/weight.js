/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'underscore',
    'Magento_Ui/js/form/element/select',
    'BigCartEnergy_CatalogCompleteness/js/form/validation'
], function ($, _, Select, validator) {
    'use strict';

    /**
     * The Weight select of a row: Required, Important or Nice to have. A row without a weight is an error.
     */
    return Select.extend({
        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            this.validation = _.extend({}, this.validation, {'bce-weight-required': true});

            return this;
        },

        /**
         * @inheritdoc
         */
        onUpdate: function () {
            this._super();
            $(document).trigger('bce-completeness:rules-changed');
        }
    });
});
