/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'Magento_Ui/js/lib/view/utils/async',
    'ko',
    'underscore',
    'Magento_Ui/js/form/element/abstract',
    'BigCartEnergy_CatalogCompleteness/js/form/validation',
    'mage/translate'
], function ($, ko, _, Abstract, validator, $t) {
    'use strict';

    // Browsers with field-sizing grow the textarea in CSS; the JS sizing is only their fallback.
    var fieldSizing = !!(window.CSS && window.CSS.supports && window.CSS.supports('field-sizing', 'content'));

    /**
     * The Parameter input of a row: its unit, placeholder and validation follow the rule type;
     * Present and Greater than 0 need none and show "Not needed".
     */
    return Abstract.extend({
        defaults: {
            elementTmpl: 'BigCartEnergy_CatalogCompleteness/form/element/parameter',
            ruleType: '',
            attributeInput: '',
            imports: {
                ruleType: '${ $.parentName }.rule_type:value',
                attributeInput: '${ $.parentName }.attribute_code:attributeInput'
            },
            listens: {
                ruleType: 'onRuleType'
            }
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();
            this.validation = _.extend({}, this.validation, {
                'bce-parameter': {
                    get type() {
                        return self.ruleType;
                    },
                    message: function () {
                        return self.requiredMessage();
                    }
                }
            });
            // Size the textarea when it renders and whenever its value is set from outside it (a saved rule set,
            // a duplicate, a suggestion), not only while typing.
            $.async('#' + this.uid, document.body, this.fitToValue.bind(this));
            this.value.subscribe(function () {
                _.defer(this.fitToValue.bind(this));
            }, this);

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe(['ruleType', 'attributeInput']);

            this.needed = ko.computed(function () {
                return ['min_length', 'min_count', 'allowed_values', 'pattern'].indexOf(this.ruleType()) !== -1;
            }, this);

            this.unit = ko.computed(function () {
                switch (this.ruleType()) {
                    case 'min_length':
                        return $t('characters');

                    case 'min_count':
                        // Not "values": with an empty dictionary ([]) 2.4's translate returns Array.prototype.values.
                        return this.attributeInput() === 'gallery' ? $t('images') : $t('selected values');

                    default:
                        return '';
                }
            }, this);

            this.hint = ko.computed(function () {
                switch (this.ruleType()) {
                    case 'min_length':
                        return $t('Minimum number of characters');

                    case 'min_count':
                        return this.attributeInput() === 'gallery' ? $t('Minimum number of images') : $t('Minimum number of values');

                    case 'allowed_values':
                        return $t('Allowed values, separated by commas');

                    case 'pattern':
                        return $t('Pattern body, e.g. ^EN \\d{3}$');

                    default:
                        return '';
                }
            }, this);

            this.isPattern = ko.computed(function () {
                return this.ruleType() === 'pattern';
            }, this);

            return this;
        },

        /**
         * @returns {String}
         */
        requiredMessage: function () {
            switch (this.ruleType()) {
                case 'min_length':
                    return $t('Enter the minimum number of characters.');

                case 'min_count':
                    return this.attributeInput() === 'gallery' ? $t('Enter the minimum number of images.') : $t('Enter the minimum number of values.');

                case 'allowed_values':
                    return $t('Enter the allowed values, separated by commas.');

                case 'pattern':
                    return $t('Enter a pattern.');

                default:
                    return '';
            }
        },

        /**
         * The parameter is one line: Enter never inserts a break.
         *
         * @param {Object} data
         * @param {Event} event
         * @returns {Boolean}
         */
        onParameterKey: function (data, event) {
            return event.key !== 'Enter';
        },

        /**
         * Grow the textarea with its value (browsers without field-sizing). The CSS max-height caps it and the
         * textarea scrolls past the cap; the height is measured without a scrollbar and includes the border.
         *
         * @param {Object} data
         * @param {Event} event
         * @returns {Boolean}
         */
        autosize: function (data, event) {
            var element = event && event.target;

            if (element && element.tagName === 'TEXTAREA') {
                element.style.overflowY = 'hidden';
                element.style.height = 'auto';
                element.style.height = element.scrollHeight + element.offsetHeight - element.clientHeight + 'px';
                element.style.overflowY = '';
            }

            return true;
        },

        /**
         * Run the autosize on the rendered textarea where the browser has no field-sizing, so a saved long value
         * shows in full before anyone types. Skipped while the textarea is absent or not laid out.
         *
         * @param {HTMLElement} [node] - the textarea, when called on render
         */
        fitToValue: function (node) {
            var element = node && node.nodeType === 1 ? node : document.getElementById(this.uid);

            if (fieldSizing || !element || !element.offsetHeight) {
                return;
            }
            this.autosize(this, {target: element});
        },

        onRuleType: function () {
            if (!this.needed()) {
                this.error('');
            } else if (this.value()) {
                this.validate();
            }
        },

        /**
         * @inheritdoc
         */
        onUpdate: function () {
            var value = this.value();

            if (_.isString(value) && /[\r\n]/.test(value)) {
                this.value(value.replace(/\s*[\r\n]+\s*/g, ' '));

                return;
            }
            this._super();
            $(document).trigger('bce-completeness:rules-changed');
        }
    });
});
