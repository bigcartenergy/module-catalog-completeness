/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'mageUtils',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, utils, confirm, $t) {
    'use strict';

    /**
     * Button handlers of the editor's actions bar. Every state change is a POST with the form key; Back asks
     * the page whether it may leave (the page shows the unsaved-changes modal when needed).
     *
     * @param {Object} config {handler: 'back'|'post'|'delete', url, title, message}
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var $button = $(element);

        /**
         * POST to a URL with the form key (utils.submit adds it).
         */
        function post() {
            utils.submit({url: config.url, data: {}});
        }

        $button.on('click', function (event) {
            event.preventDefault();

            switch (config.handler) {
                case 'back':
                    $(document).trigger('bce-completeness:leave', {url: config.url});
                    break;

                case 'delete':
                    confirm({
                        title: config.title || $t('Delete rule set'),
                        content: config.message || $t('Delete this rule set? This cannot be undone.'),
                        buttons: [{
                            text: $t('Cancel'),
                            class: 'action-secondary action-dismiss',
                            click: function () {
                                this.closeModal();
                            }
                        }, {
                            text: $t('Delete'),
                            class: 'action-primary action-accept',
                            click: function (clickEvent) {
                                this.closeModal(clickEvent, true);
                            }
                        }],
                        actions: {
                            confirm: post
                        }
                    });
                    break;

                default:
                    post();
            }
        });
    };
});
