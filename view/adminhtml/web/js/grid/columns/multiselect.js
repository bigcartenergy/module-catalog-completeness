/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'underscore',
    'Magento_Ui/js/grid/columns/multiselect'
], function (_, Multiselect) {
    'use strict';

    // The dashboard parameters (Model/Dashboard/Filter::PARAMS); nothing else is passed on, so a URL key never is.
    var PARAMS = ['scope', 'attribute_set', 'category', 'brand', 'type', 'band', 'enabled', 'tile', 'rule', 'coverage'],
        RENDER = '/mui/index/render/';

    /**
     * The dashboard parameters on the grid's update URL, where the work list's data provider writes them as
     * name/value path segments after mui/index/render.
     *
     * @param {String} url
     * @returns {Object}
     */
    function paramsFromUrl(url) {
        var path = String(url || '').split(/[?#]/)[0],
            at = path.indexOf(RENDER),
            params = {},
            parts,
            i;

        if (at === -1) {
            return params;
        }
        parts = path.slice(at + RENDER.length).split('/');

        for (i = 0; i + 1 < parts.length; i += 2) {
            if (_.indexOf(PARAMS, parts[i]) !== -1 && parts[i + 1] !== '') {
                params[parts[i]] = decodeURIComponent(parts[i + 1]);
            }
        }

        return params;
    }

    /**
     * Selections column that carries the dashboard filter parameters along with a mass action, so
     * "Export selected" in select-all mode exports what the merchant sees. The parameters come from the grid's
     * update URL, which every grid request carries; the grid's data storage can answer a request from its cache
     * without the response's own copy of them.
     */
    return Multiselect.extend({
        /**
         * @inheritdoc
         */
        getFiltering: function () {
            var params = this._super(),
                source = this.source(),
                dashboardParams = source ? paramsFromUrl(source.update_url) : {};

            if (_.isEmpty(dashboardParams) && source) {
                dashboardParams = source.get('data.bce_params') || {};
            }

            if (_.isObject(dashboardParams)) {
                params = _.extend({}, params, dashboardParams);
            }

            return params;
        }
    });
});
