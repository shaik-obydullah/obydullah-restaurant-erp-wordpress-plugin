/**
 * General Ledger Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Ledger = {
        init: function () {
            if (window.location.search.indexOf('page=orerp-ledger') === -1) return;

            $(document).on('click', '#run-ledger', function () {
                Ledger.load();
            });
        },

        load: function () {
            var $container = $('#ledger-content');
            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_ledger',
                nonce: orerpLedger.nonce,
                from: $('#ledger-date-from').val() || '',
                to: $('#ledger-date-to').val() || '',
                account_id: $('#ledger-account-filter').val() || 0
            }, function (response) {
                if (!response.success) {
                    ObydullahERP.showEmpty($container, response.data || orerpAdmin.strings.error);
                    return;
                }
                Ledger.render($container, response.data);
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        render: function ($container, data) {
            var html = '';

            html += '<h3>Period: ' + Ledger.esc(data.from) + ' to ' + Ledger.esc(data.to) + '</h3>';

            if (!data.accounts.length) {
                $container.html('<div class="orerp-empty"><p>No ledger entries found for this period.</p></div>');
                return;
            }

            for (var i = 0; i < data.accounts.length; i++) {
                var acc = data.accounts[i];

                html += '<div class="orerp-ledger-account" style="margin-bottom:20px;">';
                html += '<h4>' + Ledger.esc(acc.account_code + ' - ' + acc.account_name) + '</h4>';
                html += '<table class="orerp-table widefat"><thead><tr>';
                html += '<th>Date</th><th>Description</th><th>Ref</th><th class="text-right">Debit</th><th class="text-right">Credit</th>';
                html += '</tr></thead><tbody>';

                html += '<tr style="background:#f6f7f7;">';
                html += '<td colspan="3"><em>Opening balance</em></td>';
                html += '<td class="text-right">' + Ledger.currency(acc.opening) + '</td><td></td>';
                html += '</tr>';

                for (var j = 0; j < acc.entries.length; j++) {
                    var en = acc.entries[j];
                    html += '<tr>';
                    html += '<td>' + Ledger.esc(en.entry_date) + '</td>';
                    html += '<td>' + Ledger.esc(en.entry_description || en.description) + '</td>';
                    html += '<td>' + Ledger.esc(en.reference_type || '-') + ' ' + (en.reference_id ? '#' + en.reference_id : '') + '</td>';
                    html += '<td class="text-right">' + (en.debit > 0 ? Ledger.currency(en.debit) : '') + '</td>';
                    html += '<td class="text-right">' + (en.credit > 0 ? Ledger.currency(en.credit) : '') + '</td>';
                    html += '</tr>';
                }

                html += '<tr style="font-weight:bold;background:#f6f7f7;">';
                html += '<td colspan="3">Totals</td>';
                html += '<td class="text-right">' + Ledger.currency(acc.debit) + '</td>';
                html += '<td class="text-right">' + Ledger.currency(acc.credit) + '</td>';
                html += '</tr>';
                html += '</tbody></table>';
                html += '</div>';
            }

            html += '<h3>Grand Totals</h3>';
            html += '<p>Debit: <strong>' + Ledger.currency(data.totals.debit) + '</strong> | Credit: <strong>' + Ledger.currency(data.totals.credit) + '</strong></p>';

            $container.html(html);
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
        Ledger.init();
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Ledger = Ledger;

})(jQuery);
