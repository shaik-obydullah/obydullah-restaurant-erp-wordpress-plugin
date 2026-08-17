/**
 * Tax Reports Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var TaxReports = {
        init: function () {
            if (window.location.search.indexOf('page=orerp-tax-reports') === -1) return;

            $(document).on('click', '#run-tax-report', function () {
                TaxReports.load();
            });

            $(document).on('click', '#orerp-print-report', function () {
                var $content = $('#tax-report-content').clone();
                TaxReports.print($content);
            });
        },

        load: function () {
            var $container = $('#tax-report-content');
            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_tax_summary',
                nonce: orerpTaxReports.nonce,
                from: $('#tax-date-from').val() || '',
                to: $('#tax-date-to').val() || ''
            }, function (response) {
                if (!response.success) {
                    ObydullahERP.showEmpty($container, response.data || orerpAdmin.strings.error);
                    return;
                }
                TaxReports.render($container, response.data);
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        render: function ($container, data) {
            var html = '';

            html += '<h2>VAT / GST Summary</h2>';
            html += '<p>Period: <strong>' + TaxReports.esc(data.from) + '</strong> to <strong>' + TaxReports.esc(data.to) + '</strong></p>';

            html += '<div class="orerp-summary-grid">';
            html += '<div class="orerp-summary-card green"><div class="label">Output VAT (Sales)</div><div class="value">' + TaxReports.currency(data.output_vat) + '</div></div>';
            html += '<div class="orerp-summary-card red"><div class="label">Input VAT (Purchases)</div><div class="value">' + TaxReports.currency(data.input_vat) + '</div></div>';
            html += '<div class="orerp-summary-card ' + (data.net_payable >= 0 ? 'blue' : 'orange') + '"><div class="label">Net VAT Payable</div><div class="value">' + TaxReports.currency(data.net_payable) + '</div></div>';
            html += '</div>';

            if (data.monthly && data.monthly.length) {
                html += '<h3>Monthly Breakdown</h3>';
                html += '<table class="orerp-table widefat"><thead><tr>';
                html += '<th>Month</th><th class="text-right">Output VAT</th><th class="text-right">Input VAT</th><th class="text-right">Net Payable</th>';
                html += '</tr></thead><tbody>';

                var totalOutput = 0, totalInput = 0;
                for (var i = 0; i < data.monthly.length; i++) {
                    var m = data.monthly[i];
                    totalOutput += parseFloat(m.output_vat);
                    totalInput += parseFloat(m.input_vat);

                    html += '<tr>';
                    html += '<td>' + TaxReports.esc(m.month) + '</td>';
                    html += '<td class="text-right">' + TaxReports.currency(m.output_vat) + '</td>';
                    html += '<td class="text-right">' + TaxReports.currency(m.input_vat) + '</td>';
                    html += '<td class="text-right">' + TaxReports.currency(m.net_payable) + '</td>';
                    html += '</tr>';
                }

                html += '<tr style="font-weight:bold;background:#f6f7f7;">';
                html += '<td>Total</td>';
                html += '<td class="text-right">' + TaxReports.currency(totalOutput) + '</td>';
                html += '<td class="text-right">' + TaxReports.currency(totalInput) + '</td>';
                html += '<td class="text-right">' + TaxReports.currency(totalOutput - totalInput) + '</td>';
                html += '</tr>';
                html += '</tbody></table>';
            }

            $container.html(html);
        },

        print: function ($content) {
            var w = window.open('', '_blank', 'width=900,height=650');
            if (!w) return;

            w.document.write('<html><head><title>Tax Report</title><style>');
            w.document.write('body{font-family:Georgia,serif;color:#222;padding:30px;margin:0;}');
            w.document.write('h2,h3{color:#1d2327;} table{border-collapse:collapse;width:100%;margin:12px 0;}');
            w.document.write('th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;font-size:13px;}');
            w.document.write('th{background:#f0f0f1;} .text-right{text-align:right;}');
            w.document.write('.orerp-summary-grid{display:flex;gap:12px;margin:16px 0;}');
            w.document.write('.orerp-summary-card{border:1px solid #ccc;padding:12px 16px;flex:1;}');
            w.document.write('.orerp-summary-card .label{font-size:12px;color:#666;}');
            w.document.write('.orerp-summary-card .value{font-size:20px;font-weight:bold;}');
            w.document.write('</style></head><body>');
            w.document.write($content.html());
            w.document.write('<script>window.onload=function(){window.print();}<\/script>');
            w.document.write('</body></html>');
            w.document.close();
        },

        currency: function (amount) {
            return parseFloat(amount || 0).toFixed(2);
        },

        esc: function (t) {
            if (!t) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(t));
            return d.innerHTML;
        }
    };

    $(document).ready(function () {
        TaxReports.init();
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.TaxReports = TaxReports;

})(jQuery);
