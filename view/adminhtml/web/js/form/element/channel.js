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
        KIND = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.kind';

    /**
     * The Channel select of a profile. The role options of the rules table and the hints follow it, and
     * a profile covers every product in every attribute set, so the affected count it announces is the
     * whole catalog: on load, when the kind becomes "channel", and whenever the channel changes.
     */
    return Select.extend({
        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();
            this.value.subscribe(this.onChannelChanged, this);
            this.onChannelChanged(this.value());
            registry.get(KIND, function (kind) {
                self.announceAffected(kind.value());
                kind.value.subscribe(function (value) {
                    self.announceAffected(value);
                });
            });

            return this;
        },

        /**
         * @param {String} value
         */
        onChannelChanged: function (value) {
            $(document).trigger('bce-completeness:channel', {channel: value || null});
        },

        /**
         * Every product in every attribute set, from the same per-set counts the sets picker uses.
         *
         * @param {String} kind
         */
        announceAffected: function (kind) {
            if (kind !== 'channel') {
                return;
            }
            registry.get(CONFIG, function (config) {
                var counts = config.bceAttributeSetCounts || {},
                    total = 0,
                    sets = 0;

                _.each(counts, function (count) {
                    total += Number(count || 0);
                    sets++;
                });
                $(document).trigger('bce-completeness:affected', {products: total, sets: sets});
            });
        },

        /**
         * @inheritdoc
         */
        onUpdate: function () {
            this._super();
            this.announceAffected('channel');
        }
    });
});
