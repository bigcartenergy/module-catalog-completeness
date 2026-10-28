/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'underscore',
    'Magento_Ui/js/lib/validation/validator',
    'mage/translate'
], function (_, validator, $t) {
    'use strict';

    /**
     * Validation rules of the rules table. Messages are passed per field so a row can say which rule it
     * conflicts with; the handlers mirror the server-side validator, which decides in the end.
     */
    validator.addRule(
        'bce-attribute-required',
        function (value) {
            return !_.isEmpty(String(value || '').trim());
        },
        $t('Choose the attribute this rule checks.')
    );

    validator.addRule(
        'bce-attribute-unique',
        function (value, params) {
            return !value || !params || !_.isFunction(params.isDuplicate) || !params.isDuplicate(value);
        },
        $t('This attribute already has a rule. Use one rule per attribute.')
    );

    validator.addRule(
        'bce-weight-required',
        function (value) {
            return ['1', '2', '3'].indexOf(String(value || '')) !== -1;
        },
        $t('Choose a weight. Without one, this rule adds 0 % to the score.')
    );

    validator.addRule(
        'bce-parameter',
        function (value, params) {
            var type = params && params.type ? params.type : '',
                text = String(value === null || value === undefined ? '' : value).trim(),
                entries;

            switch (type) {
                case 'min_length':
                case 'min_count':
                    return /^\d+$/.test(text) && Number(text) >= 1;

                case 'allowed_values':
                    entries = _.filter(text.split(','), function (entry) {
                        return entry.trim() !== '';
                    });

                    return entries.length > 0;

                case 'pattern':
                    return text !== '';

                default:
                    return true;
            }
        },
        $t('Enter a parameter.')
    );

    validator.addRule(
        'bce-type-compatible',
        function (value, params) {
            var input = params && params.input ? params.input : '';

            if (!value || !input) {
                return true;
            }

            if (input === 'gallery') {
                return value === 'present' || value === 'min_count';
            }

            if (value === 'min_count') {
                return input === 'multiselect';
            }

            return true;
        },
        $t('This rule type does not fit the attribute.')
    );

    return validator;
});
