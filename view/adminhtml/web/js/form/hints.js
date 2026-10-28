/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'ko',
    'underscore',
    'uiComponent',
    'uiRegistry',
    'mage/translate'
], function ($, ko, _, Component, registry, $t) {
    'use strict';

    var RULES = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.rules_fieldset.rules',
        KIND = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.kind',
        CHANNEL = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.channel_code';

    /**
     * The "Suggested" list beside the rules table. Each Add appends one row with the role, rule type,
     * parameter and weight prefilled and the attribute left empty; the hint is then marked as added.
     * Nothing is added without a click.
     */
    return Component.extend({
        defaults: {
            lists: {},
            kind: 'attribute_set',
            channel: null,
            added: {}
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

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe({
                kind: this.kind,
                channel: this.channel,
                added: {}
            });

            this.current = ko.computed(function () {
                var code = this.kind() === 'channel' ? this.channel() : 'core';

                if (!code || code === 'custom') {
                    return null;
                }

                return this.lists[code] || null;
            }, this);

            return this;
        },

        /**
         * @returns {String}
         */
        listKey: function () {
            var list = this.current();

            return list ? list.code : '';
        },

        /**
         * @param {Object} hint
         * @returns {Boolean}
         */
        isAdded: function (hint) {
            var added = this.added();

            return !!added[this.listKey() + ':' + hint.role + ':' + hint.label];
        },

        /**
         * The accessible name of a hint's Add button.
         *
         * @param {Object} hint
         * @returns {String}
         */
        addLabel: function (hint) {
            return $t('Add a rule for %1').replace('%1', hint.label);
        },

        /**
         * @param {Object} hint
         */
        add: function (hint) {
            var self = this,
                key = this.listKey() + ':' + hint.role + ':' + hint.label,
                added;

            registry.get(RULES, function (rules) {
                rules.addFromHint({
                    role: self.kind() === 'channel' ? hint.role : '',
                    'rule_type': hint.rule_type,
                    parameter: hint.parameter || '',
                    weight: hint.weight ? String(hint.weight) : ''
                });
                added = _.clone(self.added());
                added[key] = true;
                self.added(added);
            });
        },

        /**
         * The rule in words for a hint, before an attribute is chosen.
         *
         * @param {Object} hint
         * @returns {String}
         */
        describe: function (hint) {
            var p = hint.parameter,
                m;

            switch (hint.rule_type) {
                case 'min_length':
                    return $t('At least %1 characters').replace('%1', p);

                case 'min_count':
                    return $t('At least %1').replace('%1', p);

                case 'gt_zero':
                    return $t('Greater than 0');

                case 'allowed_values':
                    return $t('One of: %1').replace('%1', p);

                case 'pattern':
                    m = /^\^\[\\s\\S\]\{1,(\d+)\}\$$/.exec(p || '');

                    return m ? $t('At most %1 characters').replace('%1', m[1]) : $t('Matches %1').replace('%1', p);

                default:
                    return $t('Filled in');
            }
        },

        /**
         * @param {Object} hint
         * @returns {String}
         */
        weightLabel: function (hint) {
            switch (Number(hint.weight)) {
                case 3:
                    return $t('Required');

                case 2:
                    return $t('Important');

                default:
                    return $t('Nice to have');
            }
        }
    });
});
