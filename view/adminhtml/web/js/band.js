/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([], function () {
    'use strict';

    /**
     * Band icons: one even-odd path each on a 16 viewBox. Every chip shows the icon and the label, so color
     * is never the only signal.
     */
    var icons = {
        complete: 'M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zM6.9 10.9L4 8l1.1-1.1 1.8 1.8 4-4L12 5.8z',
        good: 'M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 1.6a5.4 5.4 0 1 1 0 10.8A5.4 5.4 0 0 1 8 2.6zM7.1 10.5L4.6 8l1-1 1.5 1.5 3.3-3.3 1 1z',
        'needs_work': 'M8 1.2l7.2 13.1H.8zM7.2 5.6h1.6v4.6H7.2zm0 5.8h1.6V13H7.2z',
        poor: 'M3.5 2h9A1.5 1.5 0 0 1 14 3.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 12.5v-9A1.5 1.5 0 0 1 3.5 2zM5.2 4.1L8 6.9l2.8-2.8 1.1 1.1L9.1 8l2.8 2.8-1.1 1.1L8 9.1l-2.8 2.8-1.1-1.1L6.9 8 4.1 5.2z'
    };

    return {
        /**
         * @param {String} code
         * @returns {String}
         */
        iconPath: function (code) {
            return icons[code] || '';
        },

        /**
         * @param {String} code
         * @returns {String}
         */
        chipClass: function (code) {
            return code ? 'bce-completeness-chip bce-completeness-chip--' + code : 'bce-completeness-chip';
        },

        /**
         * Resolve a band code from a score and thresholds, mirroring the server-side resolver.
         *
         * @param {Number|null} score
         * @param {Object} thresholds {goodFrom: Number, needsWorkFrom: Number}
         * @returns {String|null}
         */
        resolve: function (score, thresholds) {
            if (score === null || score === undefined || score === '') {
                return null;
            }
            score = Number(score);

            if (score >= 100) {
                return 'complete';
            }

            if (score >= thresholds.goodFrom) {
                return 'good';
            }

            if (score >= thresholds.needsWorkFrom) {
                return 'needs_work';
            }

            return 'poor';
        }
    };
});
