/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'underscore',
    'Magento_Ui/js/form/element/select',
    'BigCartEnergy_CatalogCompleteness/js/form/validation',
    'mage/translate'
], function ($, _, Select, validator, $t) {
    'use strict';

    /**
     * The Rule select of a row. Checks that the type fits the attribute (images take Present or Minimum
     * count; Minimum count otherwise needs a multiselect).
     */
    return Select.extend({
        defaults: {
            attributeInput: '',
            imports: {
                attributeInput: '${ $.parentName }.attribute_code:attributeInput'
            },
            listens: {
                attributeInput: 'onAttributeInput'
            }
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();
            this.validation = _.extend({}, this.validation, {
                'bce-type-compatible': {
                    get input() {
                        return self.attributeInput;
                    },
                    message: function () {
                        return self.attributeInput === 'gallery'
                            ? $t('Images take Present or Minimum count.')
                            : $t('Minimum count works for images and multiselect attributes only.');
                    }
                }
            });

            return this;
        },

        onAttributeInput: function () {
            if (this.value()) {
                this.validate();
            }
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
