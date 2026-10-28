/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery'
], function ($) {
    'use strict';

    /**
     * Filters and the scope select apply as soon as they change; without JavaScript the forms still submit.
     * The summary tiles are buttons (so Space and Enter both work) that open the filtered dashboard.
     */
    return function () {
        $(document).on('change', '[data-role=bce-autosubmit]', function () {
            var $form = $(this).closest('form');

            if ($form.length) {
                $form.trigger('submit');
            }
        });

        $(document).on('click', '[data-bce-href]', function () {
            var url = $(this).attr('data-bce-href');

            if (url) {
                window.location.href = url;
            }
        });
    };
});
