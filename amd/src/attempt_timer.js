/**
 * QuickPractice — Attempt Timer AMD module
 * Handles countdown display and auto-submit on time expiry.
 *
 * @module     mod_quickpractice/attempt_timer
 * @copyright  2024 QuickPractice Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    'use strict';

    return {
        init: function(config) {
            var timelimit = config.timelimit;   // seconds, 0 = none
            var startedAt = config.startedAt;  // unix timestamp
            var submitUrl = config.submitUrl;

            if (!timelimit) return;

            var $display = $('#qp-timer-display');
            var $fill    = $('#qp-timer-fill');
            if (!$display.length) return;

            function tick() {
                var elapsed   = Math.floor(Date.now() / 1000) - startedAt;
                var remaining = timelimit - elapsed;

                if (remaining <= 0) {
                    $display.text('00:00').closest('.qp-timer-bar').addClass('qp-timer-danger');
                    // Auto-submit
                    document.getElementById('qp-attempt-form').submit();
                    return;
                }

                var mins = Math.floor(remaining / 60);
                var secs = remaining % 60;
                $display.text(
                    (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs
                );

                // Progress bar
                var pct = (remaining / timelimit) * 100;
                $fill.css('width', pct + '%');

                if (remaining <= 60) {
                    $display.closest('.qp-timer-bar').addClass('qp-timer-danger');
                } else if (remaining <= 300) {
                    $display.closest('.qp-timer-bar').addClass('qp-timer-warning');
                }

                setTimeout(tick, 1000);
            }

            tick();
        }
    };
});
