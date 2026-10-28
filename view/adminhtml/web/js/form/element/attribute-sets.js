/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'underscore',
    'uiRegistry',
    'Magento_Ui/js/form/element/ui-select',
    'mage/translate'
], function ($, _, registry, UiSelect, $t) {
    'use strict';

    var CONFIG = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.bce_config',
        KIND = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.kind';

    /**
     * The attribute sets multiselect. Every change recounts the products covered and tells the page,
     * and warns the rules table so attribute rows can show "Not in N sets". The count is announced for
     * attribute set rules only: a channel profile covers every set, and its Channel select reports that.
     */
    return UiSelect.extend({
        defaults: {
            affectedTimer: null
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();
            this.value.subscribe(this.onSetsChanged, this);
            registry.get(KIND, function (kind) {
                kind.value.subscribe(function () {
                    self.onSetsChanged(self.value());
                });
            });
            this.onSetsChanged(this.value());

            return this;
        },

        /**
         * @param {Array} sets
         */
        onSetsChanged: function (sets) {
            var self = this,
                ids = _.map(sets || [], String);

            $(document).trigger('bce-completeness:attribute-sets', {sets: ids});
            window.clearTimeout(this.affectedTimer);
            this.affectedTimer = window.setTimeout(function () {
                self.recount(ids);
            }, 300);
        },

        /**
         * @param {Array} ids
         */
        recount: function (ids) {
            var self = this;

            registry.get(CONFIG, function (config) {
                registry.get(KIND, function (kind) {
                    var counts = config.bceAttributeSetCounts || {},
                        total = 0;

                    if (kind.value() === 'channel') {
                        return;
                    }
                    _.each(ids, function (id) {
                        total += Number(counts[id] || 0);
                    });
                    self.notice($t('%1 products in these attribute sets.').replace('%1', total.toLocaleString()));
                    $(document).trigger('bce-completeness:affected', {products: total, sets: ids.length});
                });
            });
        }
    });
});
