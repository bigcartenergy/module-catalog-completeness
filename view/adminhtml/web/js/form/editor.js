/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'underscore',
    'uiRegistry',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, _, registry, confirm, $t) {
    'use strict';

    var PROVIDER = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form_data_source',
        RULES = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.rules_fieldset.rules';

    /**
     * Page-level behaviour of the rule set editor: the unsaved-changes flag and leave modal, the
     * "rescores N products" hint, the saved-and-rescoring progress, and the Recalculate now button.
     *
     * @param {Object} config
     */
    return function (config) {
        var $hint = $('[data-role=bce-affected-hint]'),
            $flag = $('[data-role=bce-unsaved-flag]'),
            $affectedLink = $('[data-role=bce-affected-link]'),
            $affectedKnown = $('[data-role=bce-affected-known]'),
            $affectedUnknown = $('[data-role=bce-affected-unknown]'),
            $rescoring = $('[data-role=bce-saved-recalculating]'),
            $done = $('[data-role=bce-saved-done]'),
            affected = config.affected || 0,
            affectedSets = config.affectedSets || 0,
            lastSnapshot = null,
            stableTicks = 0,
            dirtyTimer = null,
            pollTimer = null;

        /**
         * @param {Number} n
         * @returns {String}
         */
        function formatNumber(n) {
            return Number(n || 0).toLocaleString();
        }

        /**
         * "1 product" or "N products".
         *
         * @param {Number} n
         * @returns {String}
         */
        function products(n) {
            return Number(n) === 1 ? $t('1 product') : $t('%1 products').replace('%1', formatNumber(n));
        }

        /**
         * "1 attribute set" or "N attribute sets".
         *
         * @param {Number} n
         * @returns {String}
         */
        function sets(n) {
            return Number(n) === 1 ? $t('1 attribute set') : $t('%1 attribute sets').replace('%1', formatNumber(n));
        }

        function renderAffected() {
            if (!config.canEdit) {
                return;
            }

            if (affected > 0 || config.kind === 'channel') {
                $hint.text($t('Save and recalculate rescores %1.').replace('%1', products(affected)));
                $affectedLink.text($t('%1 in %2').replace('%1', products(affected)).replace('%2', sets(affectedSets)));
                $affectedKnown.prop('hidden', false);
                $affectedUnknown.prop('hidden', true);
            } else {
                $hint.text($t('Choose attribute sets to see how many products will be rescored.'));
                $affectedKnown.prop('hidden', true);
                $affectedUnknown.prop('hidden', false);
            }
        }

        /**
         * Whether the form holds unsaved changes.
         *
         * @returns {Boolean}
         */
        function isDirty() {
            var provider = registry.get(PROVIDER);

            return !!(provider && provider.isDirty && provider.isDirty());
        }

        /**
         * Arm the provider's baseline once the form has settled: every data row rendered and the data
         * unchanged for a full tick. Before that, field initialisation is still writing normalised values.
         *
         * @returns {Boolean} whether the baseline is armed
         */
        function arm() {
            var provider = registry.get(PROVIDER),
                rules = registry.get(RULES),
                rows,
                snapshot;

            if (!provider || !rules || !_.isFunction(provider.arm)) {
                return false;
            }
            rows = provider.get('data.rules') || [];
            rows = _.isArray(rows) ? rows.length : _.size(rows);

            if (rules.elems().length < rows) {
                return false;
            }
            snapshot = provider.snapshot();

            if (snapshot !== lastSnapshot) {
                lastSnapshot = snapshot;
                stableTicks = 0;

                return false;
            }
            stableTicks++;

            if (stableTicks < 1) {
                return false;
            }
            provider.arm();

            return true;
        }

        function checkDirty() {
            var provider = registry.get(PROVIDER),
                dirty;

            if (provider && !provider.armed && !arm()) {
                return;
            }
            dirty = isDirty();
            $flag.prop('hidden', !dirty);
            $(document).trigger('bce-completeness:dirty', {dirty: dirty});
        }

        /**
         * @param {Object} status
         */
        function renderRescoring(status) {
            var run = status.run;

            if (run && String(run.ruleset_id) === String(config.rulesetId)) {
                $rescoring.find('[data-role=bce-rescore-text]').text(
                    $t('Rescoring %1: %2 done (%3 %). The dashboard updates when this finishes; you can keep editing or leave this page.')
                        .replace('%1', products(run.total))
                        .replace('%2', formatNumber(run.done))
                        .replace('%3', run.percent)
                );
                $rescoring.find('[data-role=bce-rescore-bar]').attr('aria-valuemax', run.total).attr('aria-valuenow', run.done);
                $rescoring.find('[data-role=bce-rescore-fill]').css('width', run.percent + '%');
                $rescoring.prop('hidden', false);
                $done.prop('hidden', true);
                pollTimer = window.setTimeout(pollStatus, (config.pollSeconds || 4) * 1000);
            } else if (status.state !== 'recalculating') {
                $rescoring.prop('hidden', true);
                $done.prop('hidden', false);
            } else {
                pollTimer = window.setTimeout(pollStatus, (config.pollSeconds || 4) * 1000);
            }
        }

        function pollStatus() {
            $.getJSON(config.statusUrl, {'ruleset_id': config.rulesetId || '', 'isAjax': true})
                .done(renderRescoring)
                .fail(function () {
                    pollTimer = window.setTimeout(pollStatus, (config.pollSeconds || 4) * 1000);
                });
        }

        // Unsaved changes: poll the provider snapshot cheaply; the form's own observables are many.
        dirtyTimer = window.setInterval(checkDirty, 1000);

        $(document).on('bce-completeness:affected', function (event, data) {
            affected = data.products || 0;
            affectedSets = data.sets || 0;
            renderAffected();
        });

        $(document).on('bce-completeness:leave', function (event, data) {
            var leave = function () {
                var provider = registry.get(PROVIDER);

                window.clearInterval(dirtyTimer);

                // Leaving was chosen: the form counts as clean, so the browser does not ask a second time.
                if (provider && _.isFunction(provider.markClean)) {
                    provider.markClean();
                }
                window.location.href = data.url;
            };

            if (!isDirty()) {
                leave();

                return;
            }

            confirm({
                title: $t('Leave without saving?'),
                content: $t('You changed this rule set. If you leave this page now, those changes are lost.'),
                focus: '.action-accept',
                buttons: [{
                    text: $t('Leave without saving'),
                    class: 'action-secondary action-dismiss',
                    click: function () {
                        this.closeModal();
                        leave();
                    }
                }, {
                    text: $t('Stay on page'),
                    class: 'action-primary action-accept',
                    click: function () {
                        this.closeModal(true);
                    }
                }]
            });
        });

        $(window).on('beforeunload', function () {
            var provider = registry.get(PROVIDER);

            if (provider && !provider.submitted && isDirty()) {
                return $t('You have unsaved changes.');
            }
        });

        $('[data-role=bce-recalculate-now]').on('click', function () {
            var $button = $(this);

            $button.prop('disabled', true);
            $.post(config.recalculateUrl, {
                'form_key': window.FORM_KEY,
                'ruleset_id': $button.data('ruleset-id'),
                'isAjax': true
            }).done(function () {
                window.location.href = window.location.href.replace(/[?&]saved=[^&]*/, '') +
                    (window.location.href.indexOf('?') === -1 ? '?' : '&') + 'saved=recalculating';
            }).fail(function () {
                $button.prop('disabled', false);
                window.alert($t('The recalculation could not be started.'));
            });
        });

        if (config.savedState === 'recalculating') {
            pollStatus();
        }

        renderAffected();
    };
});
