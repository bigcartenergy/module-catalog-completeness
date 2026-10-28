/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'underscore',
    'uiRegistry',
    'Magento_Ui/js/form/element/select'
], function ($, _, registry, Select) {
    'use strict';

    var CONFIG = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.bce_config',
        CHANNEL = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.channel_code',
        KIND = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.kind';

    /**
     * The Role select of a rule row. The form gives it every role of every channel at creation, so a stored
     * or prefilled role survives the select's own value check; once the profile's channel is known the
     * options narrow to that channel's roles. The empty value is "Custom" (the caption), which the server
     * stores as the custom role. Hidden for attribute set rules, whose role stays empty.
     */
    return Select.extend({
        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();

            registry.get(CONFIG, function (config) {
                self.roleMap = config.bceRoles || {};
                registry.get(KIND, function (kind) {
                    self.applyKind(kind.value());
                    kind.value.subscribe(self.applyKind, self);
                });
                registry.get(CHANNEL, function (channel) {
                    self.applyChannel(channel.value());
                    channel.value.subscribe(self.applyChannel, self);
                });
            });

            return this;
        },

        /**
         * @param {String} kind
         */
        applyKind: function (kind) {
            this.visible(kind === 'channel');
        },

        /**
         * @param {String} channel
         */
        applyChannel: function (channel) {
            var options = this.roleMap && this.roleMap[channel] ? this.roleMap[channel] : [],
                current = this.value();

            if (!options.length && this.roleMap && this.roleMap.custom) {
                options = this.roleMap.custom;
            }
            this.setOptions(options);

            if (current && !_.find(options, function (option) {
                return option.value === current;
            })) {
                this.value('');
            }
        }
    });
});
