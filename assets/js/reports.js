/**
 * Reports Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Reports = {
        init: function () {
            if (window.location.search.indexOf('page=orerp-reports') === -1) return;

            $(document).on('click', '#run-report', function () {
                var reportType = $(this).data('report');
                Reports.loadReport(reportType);
            });

            $(document).on('click', '#orerp-print-report', function () {
                var $content = $('#report-content').clone();
                Reports.print($content);
            });
        },

        print: function ($content) {
            var w = window.open('', '_blank', 'width=900,height=650');
            if (!w) return;

            w.document.write('<html><head><title>Report</title><style>');
            w.document.write('body{font-family:Georgia,serif;color:#222;padding:30px;margin:0;}');
            w.document.write('h2,h3{color:#1d2327;} table{border-collapse:collapse;width:100%;margin:12px 0;}');
            w.document.write('th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;font-size:13px;}');
            w.document.write('th{background:#f0f0f1;} .text-right{text-align:right;}');
            w.document.write('.orerp-summary-grid{display:flex;gap:12px;margin:16px 0;flex-wrap:wrap;}');
            w.document.write('.orerp-summary-card{border:1px solid #ccc;padding:12px 16px;flex:1;min-width:160px;}');
            w.document.write('.orerp-summary-card .label{font-size:12px;color:#666;}');
            w.document.write('.orerp-summary-card .value{font-size:20px;font-weight:bold;}');
            w.document.write('</style></head><body>');
            w.document.write($content.html());
            w.document.write('<script>window.onload=function(){window.print();}<\/script>');
            w.document.write('</body></html>');
            w.document.close();
        },

        getParams: function () {
            return {
                date_from: $('#report-date-from').val() || '',
                date_to: $('#report-date-to').val() || '',
                branch_id: $('#report-branch-filter').val() || '',
                nonce: orerpReports.nonce
            };
        },

        loadReport: function (type) {
            var $container = $('#report-content');
            ObydullahERP.showLoading($container);

            var actionMap = {
                'sales':      'orerp_get_sales_report',
                'inventory':  'orerp_get_inventory_report',
                'financial':  'orerp_get_financial_report',
                'branches':   'orerp_get_branch_comparison',
                'employees':  'orerp_get_employee_performance'
            };

            var action = actionMap[type];
            if (!action) return;

            var params = $.extend(this.getParams(), { action: action });

            $.get(orerpAdmin.ajaxUrl, params, function (response) {
                if (response.success) {
                    Reports['render_' + type]($container, response.data);
                } else {
                    ObydullahERP.showEmpty($container, response.data || orerpAdmin.strings.error);
                }
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        /* ============================================
           SALES REPORT
           ============================================ */

        render_sales: function ($container, data) {
            var html = '';

            html += '<div class="orerp-summary-grid">';
            html += '<div class="orerp-summary-card green"><div class="label">Revenue</div><div class="value">' + Reports.currency(data.revenue) + '</div></div>';
            html += '<div class="orerp-summary-card red"><div class="label">COGS</div><div class="value">' + Reports.currency(data.cogs) + '</div></div>';
            html += '<div class="orerp-summary-card blue"><div class="label">Gross Profit</div><div class="value">' + Reports.currency(data.gross_profit) + '</div></div>';
            html += '<div class="orerp-summary-card purple"><div class="label">Margin</div><div class="value">' + data.margin + '%</div></div>';
            html += '</div>';

            // Monthly trend
            if (data.monthly && data.monthly.length) {
                html += '<h3>Monthly Trend</h3>';
                html += '<table class="orerp-table widefat"><thead><tr><th>Month</th><th class="text-right">Revenue</th><th class="text-right">COGS</th><th class="text-right">Gross Profit</th></tr></thead><tbody>';
                for (var i = 0; i < data.monthly.length; i++) {
                    var m = data.monthly[i];
                    var gp = parseFloat(m.revenue) - parseFloat(m.cogs);
                    html += '<tr>';
                    html += '<td>' + Reports.esc(m.month) + '</td>';
                    html += '<td class="text-right">' + Reports.currency(m.revenue) + '</td>';
                    html += '<td class="text-right">' + Reports.currency(m.cogs) + '</td>';
                    html += '<td class="text-right">' + Reports.currency(gp) + '</td>';
                    html += '</tr>';
                }
                html += '</tbody></table>';
            }

            // Purchase summary
            if (data.purchases && data.purchases.length) {
                html += '<h3>Purchase Summary</h3>';
                html += '<table class="orerp-table widefat"><thead><tr><th>Status</th><th class="text-right">Count</th><th class="text-right">Total</th></tr></thead><tbody>';
                for (var j = 0; j < data.purchases.length; j++) {
                    var p = data.purchases[j];
                    html += '<tr>';
                    html += '<td><span class="status-badge ' + p.status + '">' + Reports.capitalize(p.status) + '</span></td>';
                    html += '<td class="text-right">' + p.count + '</td>';
                    html += '<td class="text-right">' + Reports.currency(p.total) + '</td>';
                    html += '</tr>';
                }
                html += '</tbody></table>';
            }

            $container.html(html);
        },

        /* ============================================
           INVENTORY REPORT
           ============================================ */

        render_inventory: function ($container, data) {
            var html = '';

            html += '<div class="orerp-summary-grid">';
            html += '<div class="orerp-summary-card blue"><div class="label">Total Items</div><div class="value">' + data.total_items + '</div></div>';
            html += '<div class="orerp-summary-card green"><div class="label">Total Value</div><div class="value">' + Reports.currency(data.total_value) + '</div></div>';
            html += '<div class="orerp-summary-card orange"><div class="label">Low Stock</div><div class="value">' + data.low_stock.length + '</div></div>';
            html += '<div class="orerp-summary-card red"><div class="label">Out of Stock</div><div class="value">' + data.out_of_stock.length + '</div></div>';
            html += '</div>';

            if (data.items && data.items.length) {
                html += '<h3>Stock List</h3>';
                html += '<table class="orerp-table widefat"><thead><tr><th>Product</th><th>Branch</th><th class="text-right">Qty</th><th class="text-right">Min</th><th>Status</th></tr></thead><tbody>';
                for (var i = 0; i < data.items.length; i++) {
                    var s = data.items[i];
                    var qty = parseFloat(s.quantity);
                    var min = parseFloat(s.min_stock || 0);
                    var status = qty <= 0 ? 'Out of Stock' : (qty <= min ? 'Low Stock' : 'OK');
                    var badge = qty <= 0 ? 'inactive' : (qty <= min ? 'draft' : 'active');
                    html += '<tr>';
                    html += '<td>' + Reports.esc(s.product_name || 'Product #' + s.product_id) + '</td>';
                    html += '<td>' + Reports.esc(s.branch_name || '-') + '</td>';
                    html += '<td class="text-right">' + qty + '</td>';
                    html += '<td class="text-right">' + min + '</td>';
                    html += '<td><span class="status-badge ' + badge + '">' + status + '</span></td>';
                    html += '</tr>';
                }
                html += '</tbody></table>';
            } else {
                html += '<p>No stock data found.</p>';
            }

            $container.html(html);
        },

        /* ============================================
           FINANCIAL REPORT (P&L)
           ============================================ */

        render_financial: function ($container, data) {
            var html = '';

            html += '<div class="orerp-summary-grid">';
            html += '<div class="orerp-summary-card green"><div class="label">Total Revenue</div><div class="value">' + Reports.currency(data.total_revenue) + '</div></div>';
            html += '<div class="orerp-summary-card red"><div class="label">Total Expenses</div><div class="value">' + Reports.currency(data.total_expenses) + '</div></div>';
            html += '<div class="orerp-summary-card ' + (data.net_income >= 0 ? 'blue' : 'red') + '"><div class="label">Net Income</div><div class="value">' + Reports.currency(data.net_income) + '</div></div>';
            html += '</div>';

            // Revenue breakdown
            html += '<h3>Revenue</h3>';
            html += Reports.renderAccountTable(data.revenue, 'credit');

            // Expense breakdown
            html += '<h3>Expenses</h3>';
            html += Reports.renderAccountTable(data.expenses, 'debit');

            // Balance Sheet summary
            html += '<h3>Balance Sheet Summary</h3>';
            html += '<table class="orerp-table widefat"><thead><tr><th>Category</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead><tbody>';

            var sections = [
                { label: 'Assets', items: data.assets },
                { label: 'Liabilities', items: data.liabilities },
                { label: 'Equity', items: data.equity }
            ];

            for (var s = 0; s < sections.length; s++) {
                var section = sections[s];
                var totalDebit = 0, totalCredit = 0;
                for (var j = 0; j < section.items.length; j++) {
                    totalDebit += parseFloat(section.items[j].total_debit) || 0;
                    totalCredit += parseFloat(section.items[j].total_credit) || 0;
                }
                html += '<tr style="font-weight:bold;background:#f6f7f7;">';
                html += '<td>' + section.label + '</td>';
                html += '<td class="text-right">' + totalDebit.toFixed(2) + '</td>';
                html += '<td class="text-right">' + totalCredit.toFixed(2) + '</td>';
                html += '<td class="text-right">' + (totalDebit - totalCredit).toFixed(2) + '</td>';
                html += '</tr>';
            }

            html += '</tbody></table>';

            $container.html(html);
        },

        /* ============================================
           BRANCH COMPARISON
           ============================================ */

        render_branches: function ($container, data) {
            var html = '';

            if (!data.branches || !data.branches.length) {
                $container.html('<p>No branches found.</p>');
                return;
            }

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Branch</th>';
            html += '<th class="text-right">Employees</th>';
            html += '<th class="text-right">PO Count</th>';
            html += '<th class="text-right">PO Total</th>';
            html += '<th class="text-right">Kitchen Orders</th>';
            html += '<th class="text-right">Stock Items</th>';
            html += '</tr></thead><tbody>';

            var totals = { employees: 0, po_count: 0, po_total: 0, kitchen_orders: 0, stock_items: 0 };

            for (var i = 0; i < data.branches.length; i++) {
                var b = data.branches[i];
                totals.employees += b.employees;
                totals.po_count += b.po_count;
                totals.po_total += b.po_total;
                totals.kitchen_orders += b.kitchen_orders;
                totals.stock_items += b.stock_items;

                html += '<tr>';
                html += '<td><strong>' + Reports.esc(b.branch_name) + '</strong></td>';
                html += '<td class="text-right">' + b.employees + '</td>';
                html += '<td class="text-right">' + b.po_count + '</td>';
                html += '<td class="text-right">' + Reports.currency(b.po_total) + '</td>';
                html += '<td class="text-right">' + b.kitchen_orders + '</td>';
                html += '<td class="text-right">' + b.stock_items + '</td>';
                html += '</tr>';
            }

            html += '</tbody><tfoot><tr style="font-weight:bold;background:#f6f7f7;">';
            html += '<td>Total</td>';
            html += '<td class="text-right">' + totals.employees + '</td>';
            html += '<td class="text-right">' + totals.po_count + '</td>';
            html += '<td class="text-right">' + Reports.currency(totals.po_total) + '</td>';
            html += '<td class="text-right">' + totals.kitchen_orders + '</td>';
            html += '<td class="text-right">' + totals.stock_items + '</td>';
            html += '</tr></tfoot></table>';

            $container.html(html);
        },

        /* ============================================
           EMPLOYEE PERFORMANCE
           ============================================ */

        render_employees: function ($container, data) {
            var html = '';

            if (!data.employees || !data.employees.length) {
                $container.html('<p>No employees found.</p>');
                return;
            }

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Employee</th>';
            html += '<th>Branch</th>';
            html += '<th class="text-right">Days Worked</th>';
            html += '<th class="text-right">Total Hours</th>';
            html += '<th class="text-right">Tasks Completed</th>';
            html += '<th class="text-right">Avg Time (min)</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < data.employees.length; i++) {
                var e = data.employees[i];
                html += '<tr>';
                html += '<td><strong>' + Reports.esc(e.name) + '</strong></td>';
                html += '<td>' + Reports.esc(e.branch || '-') + '</td>';
                html += '<td class="text-right">' + e.days_worked + '</td>';
                html += '<td class="text-right">' + e.total_hours + '</td>';
                html += '<td class="text-right">' + e.tasks_completed + '</td>';
                html += '<td class="text-right">' + (e.avg_time_min > 0 ? e.avg_time_min : '-') + '</td>';
                html += '</tr>';
            }

            html += '</tbody></table>';

            $container.html(html);
        },

        /* ============================================
           HELPERS
           ============================================ */

        renderAccountTable: function (accounts, debitOrCredit) {
            if (!accounts || !accounts.length) return '<p>No data.</p>';

            var html = '<table class="orerp-table widefat"><thead><tr><th>Code</th><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Net</th></tr></thead><tbody>';
            var totalNet = 0;

            for (var i = 0; i < accounts.length; i++) {
                var a = accounts[i];
                var debit = parseFloat(a.total_debit) || 0;
                var credit = parseFloat(a.total_credit) || 0;
                var net = credit - debit;
                totalNet += net;

                html += '<tr>';
                html += '<td>' + Reports.esc(a.code) + '</td>';
                html += '<td>' + Reports.esc(a.name) + '</td>';
                html += '<td class="text-right">' + debit.toFixed(2) + '</td>';
                html += '<td class="text-right">' + credit.toFixed(2) + '</td>';
                html += '<td class="text-right">' + net.toFixed(2) + '</td>';
                html += '</tr>';
            }

            html += '</tbody><tfoot><tr style="font-weight:bold;background:#f6f7f7;">';
            html += '<td colspan="4">Total</td>';
            html += '<td class="text-right">' + totalNet.toFixed(2) + '</td>';
            html += '</tr></tfoot></table>';
            return html;
        },

        currency: function (amount) {
            return parseFloat(amount || 0).toFixed(2);
        },

        esc: function (t) {
            if (!t) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(t));
            return d.innerHTML;
        },

        capitalize: function (s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
        }
    };

    $(document).ready(function () {
        Reports.init();
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Reports = Reports;

})(jQuery);
