/**
 * Suppliers Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Suppliers = {
        currentPage: 1,
        perPage: 20,

        init: function () {
            this.bindEvents();
            this.loadSuppliers();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('submit', '#supplier-form', function (e) {
                e.preventDefault();
                self.saveSupplier();
            });

            $(document).on('click', '.edit-supplier', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') +
                    'admin.php?page=orerp-suppliers&action=edit&id=' + id;
            });

            $(document).on('click', '.delete-supplier', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                if (ObydullahERP.confirm('Are you sure you want to delete this supplier?')) {
                    self.deleteSupplier(id);
                }
            });

            $(document).on('click', '#search-suppliers', function () {
                self.currentPage = 1;
                self.loadSuppliers();
            });

            $(document).on('click', '#reset-supplier-search', function () {
                $('#supplier-search-input').val('');
                self.currentPage = 1;
                self.loadSuppliers();
            });
        },

        loadSuppliers: function () {
            var self = this;
            var $container = $('#suppliers-list');
            var search = $('#supplier-search-input').val() || '';

            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_suppliers',
                nonce: orerpSuppliers.nonce,
                page: self.currentPage,
                per_page: self.perPage,
                search: search
            }, function (response) {
                if (response.success) {
                    self.renderTable($container, response.data);
                } else {
                    ObydullahERP.showEmpty($container, response.data);
                }
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        renderTable: function ($container, data) {
            var self = this;
            var suppliers = data.suppliers;

            if (suppliers.length === 0) {
                ObydullahERP.showEmpty($container, 'No suppliers found.');
                return;
            }

            var html = '<div style="margin-bottom: 15px;"><div class="orerp-filters">';
            html += '<div class="filter-group"><label>Search</label>';
            html += '<input type="text" id="supplier-search-input" placeholder="Search suppliers..." class="regular-text"></div>';
            html += '<div class="filter-actions">';
            html += '<button type="button" id="search-suppliers" class="button button-primary">Search</button> ';
            html += '<button type="button" id="reset-supplier-search" class="button">Reset</button>';
            html += '</div></div></div>';

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Code</th><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Terms</th><th>Balance</th><th>Status</th><th class="text-right">Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < suppliers.length; i++) {
                var s = suppliers[i];
                html += '<tr>';
                html += '<td><strong>' + self.escapeHtml(s.code) + '</strong></td>';
                html += '<td>' + self.escapeHtml(s.name) + '</td>';
                html += '<td>' + self.escapeHtml(s.contact_person || '-') + '</td>';
                html += '<td>' + self.escapeHtml(s.phone || '-') + '</td>';
                html += '<td>' + self.escapeHtml(s.email || '-') + '</td>';
                html += '<td>' + self.escapeHtml(s.payment_terms || '-') + '</td>';
                html += '<td>' + ObydullahERP.formatCurrency(s.balance) + '</td>';
                html += '<td><span class="status-badge ' + (s.is_active == 1 ? 'active' : 'inactive') + '">';
                html += (s.is_active == 1 ? 'Active' : 'Inactive') + '</span></td>';
                html += '<td class="text-right">';
                html += '<a href="#" class="edit-supplier orerp-btn orerp-btn-sm orerp-btn-outline" data-id="' + s.id + '">Edit</a> ';
                html += '<a href="#" class="delete-supplier orerp-btn orerp-btn-sm orerp-btn-danger" data-id="' + s.id + '">Delete</a>';
                html += '</td></tr>';
            }

            html += '</tbody></table>';
            $container.html(html);

            ObydullahERP.renderPagination($container, {
                total: data.total, total_pages: data.total_pages, current_page: data.current_page
            }, function (page) {
                Suppliers.currentPage = page;
                Suppliers.loadSuppliers();
            });
        },

        saveSupplier: function () {
            var $btn = $('#submit-supplier');
            $btn.prop('disabled', true).find('.spinner').show();
            $btn.find('.btn-text').text('Saving...');

            $.post(orerpAdmin.ajaxUrl, $('#supplier-form').serialize(), function (response) {
                if (response.success) {
                    window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-suppliers';
                } else {
                    alert(response.data || orerpAdmin.strings.error);
                }
            }).fail(function () { alert(orerpAdmin.strings.error); })
            .always(function () { $btn.prop('disabled', false).find('.spinner').hide().siblings('.btn-text').text('Save Supplier'); });
        },

        deleteSupplier: function (id) {
            $.post(orerpAdmin.ajaxUrl, { action: 'orerp_delete_supplier', nonce: orerpSuppliers.nonce, id: id }, function (response) {
                if (response.success) Suppliers.loadSuppliers();
                else alert(response.data || orerpAdmin.strings.error);
            });
        },

        escapeHtml: function (text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    $(document).ready(function () {
        if ($('#suppliers-list').length || $('#supplier-form').length) {
            Suppliers.init();
        }
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Suppliers = Suppliers;

})(jQuery);
