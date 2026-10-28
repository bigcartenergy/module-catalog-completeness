/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'mage/translate',
    'mage/template'
], function ($, $t) {
    'use strict';

    /**
     * The dashboard's live behaviour: the Recalculate buttons post with the form key, and while a run is
     * active the status endpoint is polled every few seconds to move the progress bar and swap the message.
     *
     * @param {Object} config
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var $root = $(element),
            $status = $root.find('[data-role=bce-status]'),
            $recalcMessage = $status.find('[data-role=bce-status-recalc]'),
            $staleMessage = $status.find('[data-role=bce-status-stale]'),
            $errorMessage = $status.find('[data-role=bce-status-error]'),
            $text = $status.find('[data-role=bce-status-text]'),
            $actions = $('[data-role=bce-completeness-actions]'),
            $button = $actions.find('[data-role=bce-recalculate]'),
            $buttonLabel = $button.find('[data-role=bce-recalculate-label]'),
            timer = null,
            wasRunning = config.status && config.status.state === 'recalculating';

        /**
         * @param {Number} n
         * @returns {String}
         */
        function formatNumber(n) {
            return Number(n || 0).toLocaleString();
        }

        /**
         * Render the status payload into the message blocks.
         *
         * @param {Object} status
         */
        function render(status) {
            var run = status.run,
                failed = status.failed,
                running = status.state === 'recalculating';

            $recalcMessage.prop('hidden', !running);
            $staleMessage.prop('hidden', status.state !== 'stale');
            $errorMessage.prop('hidden', status.state !== 'error');
            $text.attr('data-state', status.state);

            if (running && run) {
                $recalcMessage.find('[data-role=bce-recalc-headline]').text(
                    $t('Recalculating scores: %1 of %2 products done (%3 %).')
                        .replace('%1', formatNumber(run.done))
                        .replace('%2', formatNumber(run.total))
                        .replace('%3', run.percent)
                );
                $recalcMessage.find('[data-role=bce-recalc-waiting]').prop('hidden', !run.waiting);
                $recalcMessage.find('[data-role=bce-recalc-bar]')
                    .attr('aria-valuemax', run.total)
                    .attr('aria-valuenow', run.done);
                $recalcMessage.find('[data-role=bce-recalc-fill]').css('width', run.percent + '%');
            }

            if (status.state === 'error' && failed) {
                $errorMessage.find('[data-role=bce-error-message]').text(failed.message || '');
            }

            if (status.state === 'ok') {
                $text.text(
                    $t('Scores as of %1 · %2')
                        .replace('%1', status.scored_at_formatted)
                        .replace('%2', status.mode_label)
                );
            }

            $button.prop('disabled', running);
            $buttonLabel.text(running ? $t('Recalculating…') : $t('Recalculate'));
        }

        /**
         * Poll the status endpoint while a run is active; reload the page once it finishes so the numbers refresh.
         */
        function poll() {
            $.getJSON(config.statusUrl, {'ruleset_id': config.rulesetId || '', 'isAjax': true})
                .done(function (status) {
                    var running = status.state === 'recalculating';

                    render(status);

                    if (running) {
                        wasRunning = true;
                        schedule();
                    } else if (wasRunning) {
                        window.location.reload();
                    }
                })
                .fail(function () {
                    schedule();
                });
        }

        function schedule() {
            window.clearTimeout(timer);
            timer = window.setTimeout(poll, (config.pollSeconds || 4) * 1000);
        }

        /**
         * Queue a recalculation of the current scope.
         */
        function recalculate() {
            $button.prop('disabled', true);
            $buttonLabel.text($t('Recalculating…'));
            $.post(config.recalculateUrl, {
                'form_key': window.FORM_KEY,
                'ruleset_id': config.rulesetId || '',
                'isAjax': true
            }).done(function () {
                wasRunning = true;
                poll();
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : $t('The recalculation could not be started.');

                $button.prop('disabled', false);
                $buttonLabel.text($t('Recalculate'));
                window.alert(message);
            });
        }

        $button.on('click', recalculate);
        $root.on('click', '[data-role=bce-recalculate-primary], [data-role=bce-recalculate-now], [data-role=bce-recalculate-retry]', recalculate);

        if (wasRunning) {
            schedule();
        }
    };
});
