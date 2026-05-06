/**
 * QuickPractice — Teacher Report Charts AMD module
 * Uses Chart.js (bundled with Moodle 4.x) to render:
 *   1. Score distribution bar chart
 *   2. Question correct-rate horizontal bar chart
 *
 * @module     mod_quickpractice/report_charts
 * @copyright  2024 QuickPractice Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/chartjs'], function($, Chart) {
    'use strict';

    return {
        init: function() {
            // ── 成绩分布图 ──────────────────────────────────
            var $distCanvas = document.getElementById('qp-dist-chart');
            if ($distCanvas) {
                var distData = JSON.parse($distCanvas.dataset.dist || '{}');
                new Chart($distCanvas, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(distData),
                        datasets: [{
                            label: '人数',
                            data: Object.values(distData),
                            backgroundColor: [
                                'rgba(239,68,68,0.7)',
                                'rgba(249,115,22,0.7)',
                                'rgba(234,179,8,0.7)',
                                'rgba(34,197,94,0.7)',
                                'rgba(59,130,246,0.7)'
                            ],
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: '成绩分布' },
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: '人数' }, ticks: { precision: 0 } },
                            x: { title: { display: true, text: '分数段' } }
                        }
                    }
                });
            }

            // ── 题目正确率图 ──────────────────────────────────
            var $qCanvas = document.getElementById('qp-qcorrect-chart');
            if ($qCanvas) {
                var qData = JSON.parse($qCanvas.dataset.qdata || '[]');
                var labels = qData.map(function(d) { return d.label; });
                var rates  = qData.map(function(d) { return d.correctrate; });

                var colors = rates.map(function(r) {
                    if (r >= 80) return 'rgba(34,197,94,0.7)';
                    if (r >= 50) return 'rgba(234,179,8,0.7)';
                    return 'rgba(239,68,68,0.7)';
                });

                new Chart($qCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '正确率 %',
                            data: rates,
                            backgroundColor: colors,
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: {
                            title: { display: true, text: '各题正确率' },
                            legend: { display: false }
                        },
                        scales: {
                            x: { min: 0, max: 100, title: { display: true, text: '正确率 (%)' } }
                        }
                    }
                });
            }
        }
    };
});
