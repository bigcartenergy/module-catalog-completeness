/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'underscore',
    'mageUtils',
    'Magento_Ui/js/form/provider'
], function (_, utils, Provider) {
    'use strict';

    var TOP_KEYS = ['ruleset_id', 'name', 'kind', 'channel_code', 'description', 'is_enabled', 'attribute_set_ids'],
        ROW_KEYS = ['rule_id', 'position', 'role', 'attribute_code', 'rule_type', 'parameter', 'weight', 'applies_to'];

    /**
     * One comparable text for a value: empty for nothing, '1'/'0' for booleans, sorted and joined for lists,
     * so '4' and 4, [] and undefined, or a reordered type list compare equal.
     *
     * @param {*} value
     * @returns {String}
     */
    function text(value) {
        if (value === null || value === undefined) {
            return '';
        }

        if (value === true) {
            return '1';
        }

        if (value === false) {
            return '0';
        }

        if (_.isArray(value)) {
            return _.map(value, text).sort().join(',');
        }

        return String(value);
    }

    /**
     * The form's data provider with a baseline of the loaded data, so the page knows when it is dirty.
     * The baseline is armed by the editor once the form has settled (see js/form/editor.js); until then the
     * form counts as clean, so the normalisation every field does while it initialises never flags a change.
     */
    return Provider.extend({
        defaults: {
            baseline: null,
            armed: false,
            submitted: false
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            this.baseline = this.snapshot();

            return this;
        },

        /**
         * A comparable copy of the data: the known fields only, every value as text.
         *
         * @returns {String}
         */
        snapshot: function () {
            var data = this.get('data') || {},
                rows = _.isArray(data.rules) ? data.rules : _.values(data.rules || {}),
                out = {};

            _.each(TOP_KEYS, function (key) {
                out[key] = text(data[key]);
            });
            out.rules = _.map(rows, function (row) {
                var copy = {};

                _.each(ROW_KEYS, function (key) {
                    copy[key] = text(row ? row[key] : '');
                });

                return copy;
            });

            return JSON.stringify(out);
        },

        /**
         * Take the current data as the clean state and start reporting changes.
         */
        arm: function () {
            this.baseline = this.snapshot();
            this.armed = true;
        },

        /**
         * @returns {Boolean}
         */
        isDirty: function () {
            return this.armed && this.snapshot() !== this.baseline;
        },

        /**
         * Remember the current state as clean.
         */
        markClean: function () {
            this.baseline = this.snapshot();
        },

        /**
         * @inheritdoc
         */
        save: function (options) {
            this.submitted = true;

            return this._super(options);
        }
    });
});
