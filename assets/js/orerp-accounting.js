/**
 * Accounting Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Accounting = {
        currentPage: 1,
        perPage: 20,

        init: function () {
            var page = window.location.search;
            if (page.indexOf('orerp-accounting') > -1) {
                this.initChartOfAccounts();
            } else if (page.indexOf('orerp-journal') > -1) {
                this.initJournalEntries();
            }
        },

        /* ============================================
           CHART OF ACCOUNTS
           ============================================ */

        initChartOfAccounts: function () {
            var self = this;
            self.loadAccounts();

            $(document).on('submit', '#account-form', function (e) {
                e.preventDefault();
                self.saveAccount();
            });

            $(document).on('click', '.edit-account', function (e) {
                e.preventDefault();
                window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') +
                    'admin.php?page=orerp-accounting&action=edit&id=' + $(this).data('id');
            });

            $(document).on('click', '.delete-account', function (e) {
                e.preventDefault();
                if (ObydullahERP.confirm('Delete this account?')) {
                    self.deleteAccount($(this).data('id'));
                }
            });

            $(document).on('click', '#refresh-trial-balance', function () {
                self.loadTrialBalance();
            });
        },

        loadAccounts: function () {
            var $container = $('#accounts-list');
            if (!$container.length) return;

            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_accounts',
                nonce: orerpAccounting.nonce
            }, function (response) {
                if (response.success) {
                    self.renderAccountsTable($container, response.data);
                } else {
                    ObydullahERP.showEmpty($container, response.data);
                }
            });
        },

        renderAccountsTable: function ($container, accounts) {
            if (!accounts.length) {
                ObydullahERP.showEmpty($container, 'No accounts found.');
                return;
            }

            var types = { asset: 'Asset', liability: 'Liability', equity: 'Equity', revenue: 'Revenue', expense: 'Expense' };
            var html = '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Code</th><th>Name</th><th>Type</th><th>Balance</th><th>Status</th><th class="text-right">Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < accounts.length; i++) {
                var a = accounts[i];
                html += '<tr>';
                html += '<td><strong>' + self.esc(a.code) + '</strong></td>';
                html += '<td>' + self.esc(a.name) + '</td>';
                html += '<td><span class="status-badge ' + a.type + '">' + (types[a.type] || a.type) + '</span></td>';
                html += '<td>' + a.formatted_balance + '</td>';
                html += '<td><span class="status-badge ' + (a.is_active == 1 ? 'active' : 'inactive') + '">';
                html += (a.is_active == 1 ? 'Active' : 'Inactive') + '</span></td>';
                html += '<td class="text-right">';
                html += '<a href="#" class="edit-account orerp-btn orerp-btn-sm orerp-btn-outline" data-id="' + a.id + '">Edit</a> ';
                html += '<a href="#" class="delete-account orerp-btn orerp-btn-sm orerp-btn-danger" data-id="' + a.id + '">Delete</a>';
                html += '</td></tr>';
            }

            html += '</tbody></table>';
            $container.html(html);
        },

        saveAccount: function () {
            var $btn = $('#submit-account');
            $btn.prop('disabled', true).find('.spinner').show();

            $.post(orerpAdmin.ajaxUrl, $('#account-form').serialize(), function (r) {
                if (r.success) {
                    window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-accounting';
                } else {
                    alert(r.data || orerpAdmin.strings.error);
                }
            }).always(function () { $btn.prop('disabled', false).find('.spinner').hide(); });
        },

        deleteAccount: function (id) {
            $.post(orerpAdmin.ajaxUrl, { action: 'orerp_delete_account', nonce: orerpAccounting.nonce, id: id }, function (r) {
                if (r.success) Accounting.loadAccounts();
                else alert(r.data || orerpAdmin.strings.error);
            });
        },

        loadTrialBalance: function () {
            var $container = $('#trial-balance-content');
            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_account_balances',
                nonce: orerpAccounting.nonce
            }, function (response) {
                if (response.success) {
                    self.renderTrialBalance($container, response.data);
                }
            });
        },

        renderTrialBalance: function ($container, data) {
            var accounts = data.accounts;
            if (!accounts.length) {
                $container.html('<p>No accounts with balances.</p>');
                return;
            }

            var html = '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Code</th><th>Account</th><th>Type</th><th class="text-right">Debit</th><th class="text-right">Credit</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < accounts.length; i++) {
                var a = accounts[i];
                html += '<tr>';
                html += '<td>' + self.esc(a.code) + '</td>';
                html += '<td>' + self.esc(a.name) + '</td>';
                html += '<td>' + self.capitalize(a.type) + '</td>';
                html += '<td class="text-right">' + (a.debit > 0 ? a.debit.toFixed(2) : '') + '</td>';
                html += '<td class="text-right">' + (a.credit > 0 ? a.credit.toFixed(2) : '') + '</td>';
                html += '</tr>';
            }

            html += '</tbody><tfoot><tr style="font-weight:bold;background:#f6f7f7;">';
            html += '<td colspan="3">Total</td>';
            html += '<td class="text-right">' + data.total_debit.toFixed(2) + '</td>';
            html += '<td class="text-right">' + data.total_credit.toFixed(2) + '</td>';
            html += '</tr></tfoot></table>';

            html += '<p style="margin-top:10px;">';
            html += '<span class="status-badge ' + (data.is_balanced ? 'active' : 'inactive') + '">';
            html += data.is_balanced ? 'Balanced' : 'Out of Balance';
            html += '</span></p>';

            $container.html(html);
        },

        /* ============================================
           JOURNAL ENTRIES
           ============================================ */

        initJournalEntries: function () {
            var self = this;
            self.loadJournalEntries();

            $(document).on('submit', '#journal-form', function (e) {
                e.preventDefault();
                self.saveEntry();
            });

            $(document).on('click', '.edit-entry', function (e) {
                e.preventDefault();
                window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') +
                    'admin.php?page=orerp-journal&action=edit&id=' + $(this).data('id');
            });

            $(document).on('click', '.delete-entry', function (e) {
                e.preventDefault();
                if (ObydullahERP.confirm('Delete this journal entry?')) {
                    self.deleteEntry($(this).data('id'));
                }
            });

            $(document).on('click', '#post-journal', function (e) {
                e.preventDefault();
                self.postEntry($(this).data('id'));
            });

            $(document).on('click', '#add-journal-line', function () {
                self.addJournalLine();
            });

            $(document).on('click', '.remove-line', function () {
                $(this).closest('tr').remove();
                self.calculateJournalTotals();
            });

            $(document).on('input', '.line-debit, .line-credit', function () {
                self.calculateJournalTotals();
            });

            $(document).on('click', '#search-journal', function () {
                self.currentPage = 1;
                self.loadJournalEntries();
            });

            $(document).on('click', '#reset-journal-search', function () {
                $('#journal-date-from').val('');
                $('#journal-date-to').val('');
                self.currentPage = 1;
                self.loadJournalEntries();
            });
        },

        loadJournalEntries: function () {
            var $container = $('#journal-list');
            if (!$container.length) return;

            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_journal_entries',
                nonce: orerpJournal.nonce,
                page: self.currentPage,
                per_page: self.perPage,
                date_from: $('#journal-date-from').val() || '',
                date_to: $('#journal-date-to').val() || ''
            }, function (response) {
                if (response.success) {
                    self.renderJournalTable($container, response.data);
                } else {
                    ObydullahERP.showEmpty($container, response.data);
                }
            });
        },

        renderJournalTable: function ($container, data) {
            var entries = data.entries;
            if (!entries.length) {
                ObydullahERP.showEmpty($container, 'No journal entries found.');
                return;
            }

            var html = '<div style="margin-bottom:15px;"><div class="orerp-filters">';
            html += '<div class="filter-group"><label>From</label><input type="date" id="journal-date-from" class="regular-text"></div>';
            html += '<div class="filter-group"><label>To</label><input type="date" id="journal-date-to" class="regular-text"></div>';
            html += '<div class="filter-actions">';
            html += '<button type="button" id="search-journal" class="button button-primary">Filter</button> ';
            html += '<button type="button" id="reset-journal-search" class="button">Reset</button>';
            html += '</div></div></div>';

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Entry #</th><th>Date</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Status</th><th class="text-right">Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < entries.length; i++) {
                var e = entries[i];
                var totals = e.totals || {};
                html += '<tr>';
                html += '<td><strong>' + self.esc(e.entry_number) + '</strong></td>';
                html += '<td>' + self.esc(e.formatted_date) + '</td>';
                html += '<td>' + self.esc(e.description) + '</td>';
                html += '<td class="text-right">' + parseFloat(totals.total_debit || 0).toFixed(2) + '</td>';
                html += '<td class="text-right">' + parseFloat(totals.total_credit || 0).toFixed(2) + '</td>';
                html += '<td><span class="status-badge ' + (e.is_posted ? 'completed' : 'draft') + '">';
                html += (e.is_posted ? 'Posted' : 'Draft') + '</span></td>';
                html += '<td class="text-right">';
                html += '<a href="#" class="edit-entry orerp-btn orerp-btn-sm orerp-btn-outline" data-id="' + e.id + '">Edit</a> ';
                html += '<a href="#" class="delete-entry orerp-btn orerp-btn-sm orerp-btn-danger" data-id="' + e.id + '">Delete</a>';
                html += '</td></tr>';
            }

            html += '</tbody></table>';
            $container.html(html);

            ObydullahERP.renderPagination($container, {
                total: data.total, total_pages: data.total_pages, current_page: data.current_page
            }, function (page) {
                Accounting.currentPage = page;
                Accounting.loadJournalEntries();
            });
        },

        addJournalLine: function () {
            var html = '<tr class="journal-line-row">';
            html += '<td><select name="new_lines[][account_id]" class="line-account" required><option value="">Select Account</option></select></td>';
            html += '<td><input type="text" name="new_lines[][description]" class="line-desc"></td>';
            html += '<td><input type="number" name="new_lines[][debit]" class="line-debit" step="0.01" min="0" value="0"></td>';
            html += '<td><input type="number" name="new_lines[][credit]" class="line-credit" step="0.01" min="0" value="0"></td>';
            html += '<td><button type="button" class="button remove-line">X</button></td>';
            html += '</tr>';
            $('#journal-lines-body').append(html);
        },

        calculateJournalTotals: function () {
            var totalDebit = 0;
            var totalCredit = 0;

            $('#journal-lines-body tr').each(function () {
                totalDebit += parseFloat($(this).find('.line-debit').val()) || 0;
                totalCredit += parseFloat($(this).find('.line-credit').val()) || 0;
            });

            $('#total-debit').text(totalDebit.toFixed(2));
            $('#total-credit').text(totalCredit.toFixed(2));

            var isBalanced = Math.abs(totalDebit - totalCredit) < 0.01;
            var $indicator = $('#balance-indicator');
            $indicator.removeClass('active inactive').addClass(isBalanced ? 'active' : 'inactive');
            $indicator.text(isBalanced ? 'Balanced' : 'Out of Balance');
        },

        saveEntry: function () {
            var $btn = $('#submit-journal');
            $btn.prop('disabled', true).find('.spinner').show();

            $.post(orerpAdmin.ajaxUrl, $('#journal-form').serialize(), function (r) {
                if (r.success) {
                    window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-journal';
                } else {
                    alert(r.data || orerpAdmin.strings.error);
                }
            }).always(function () { $btn.prop('disabled', false).find('.spinner').hide(); });
        },

        postEntry: function (id) {
            $.post(orerpAdmin.ajaxUrl, { action: 'orerp_post_journal_entry', nonce: orerpJournal.nonce, entry_id: id }, function (r) {
                if (r.success) {
                    Accounting.loadJournalEntries();
                } else {
                    alert(r.data || orerpAdmin.strings.error);
                }
            });
        },

        deleteEntry: function (id) {
            $.post(orerpAdmin.ajaxUrl, { action: 'orerp_delete_journal_entry', nonce: orerpJournal.nonce, id: id }, function (r) {
                if (r.success) Accounting.loadJournalEntries();
                else alert(r.data || orerpAdmin.strings.error);
            });
        },

        esc: function (t) {
            if (!t) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(t));
            return d.innerHTML;
        },

        capitalize: function (s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    };

    var self = Accounting;

    $(document).ready(function () {
        Accounting.init();
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Accounting = Accounting;
    window.ObydullahERP.Journal = Accounting;

})(jQuery);
