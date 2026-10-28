/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'ko',
    'Magento_Ui/js/form/element/checkbox-set'
], function ($, ko, CheckboxSet) {
    'use strict';

    /**
     * The Kind radios: attribute set rules or channel profile. Other fields import the two flags.
     */
    return CheckboxSet.extend({
        defaults: {
            multiple: false
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super();

            this.isAttributeSetKind = ko.computed(function () {
                return this.value() !== 'channel';
            }, this);
            this.isChannelKind = ko.computed(function () {
                return this.value() === 'channel';
            }, this);

            this.value.subscribe(function (value) {
                $(document).trigger('bce-completeness:kind', {kind: value});
            });

            return this;
        }
    });
});
