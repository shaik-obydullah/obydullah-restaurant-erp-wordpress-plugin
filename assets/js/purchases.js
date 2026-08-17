/**
 * Purchases Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Purchases = {
        currentPage: 1,
        perPage: 20,

        init: function () {
            this.bindEvents();
            this.loadPurchases();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('submit', '#purchase-form', function (e) {
                e.preventDefault();
                self.savePurchase();
            });

            $(document).on('click', '.edit-purchase', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') +
                    'admin.php?page=orerp-purchases&action=edit&id=' + id;
            });

            $(document).on('click', '.delete-purchase', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                if (ObydullahERP.confirm('Delete this purchase order?')) {
                    self.deletePurchase(id);
                }
            });

            $(document).on('click', '.receive-purchase', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                if (ObydullahERP.confirm('Receive this purchase order? Stock will be updated.')) {
                    self.receivePurchase(id);
                }
            });

            $(document).on('click', '#search-purchases', function () {
                self.currentPage = 1;
                self.loadPurchases();
            });

            $(document).on('click', '#reset-purchase-search', function () {
                $('#purchase-search-input').val('');
                $('#purchase-status-filter').val('');
                self.currentPage = 1;
                self.loadPurchases();
            });

            $(document).on('click', '#add-po-item', function () {
                self.addPoItem();
            });

            $(document).on('click', '.remove-item', function () {
                $(this).closest('tr').remove();
                self.calculateTotals();
            });

            $(document).on('input', '.po-qty, .po-cost', function () {
                self.calculateItemTotal($(this).closest('tr'));
                self.calculateTotals();
            });

            $(document).on('input', '#po-tax', function () {
                self.calculateTotals();
            });
        },

        loadPurchases: function () {
            var self = this;
            var $container = $('#purchases-list');
            var search = $('#purchase-search-input').val() || '';
            var status = $('#purchase-status-filter').val() || '';

            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_purchases',
                nonce: orerpPurchases.nonce,
                page: self.currentPage,
                per_page: self.perPage,
                search: search,
                status: status
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
            var purchases = data.purchases;

            if (purchases.length === 0) {
                ObydullahERP.showEmpty($container, 'No purchase orders found.');
                return;
            }

            var html = '<div style="margin-bottom:15px;"><div class="orerp-filters">';
            html += '<div class="filter-group"><label>Search</label>';
            html += '<input type="text" id="purchase-search-input" placeholder="PO Number..." class="regular-text"></div>';
            html += '<div class="filter-group"><label>Status</label>';
            html += '<select id="purchase-status-filter" class="regular-text">';
            html += '<option value="">All</option><option value="draft">Draft</option><option value="pending">Pending</option>';
            html += '<option value="partial">Partial</option><option value="received">Received</option><option value="cancelled">Cancelled</option>';
            html += '</select></div>';
            html += '<div class="filter-actions">';
            html += '<button type="button" id="search-purchases" class="button button-primary">Search</button> ';
            html += '<button type="button" id="reset-purchase-search" class="button">Reset</button>';
            html += '</div></div></div>';

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>PO Number</th><th>Supplier</th><th>Branch</th><th>Total</th><th>Status</th><th>Date</th><th class="text-right">Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < purchases.length; i++) {
                var p = purchases[i];
                html += '<tr>';
                html += '<td><strong>' + self.escapeHtml(p.po_number) + '</strong></td>';
                html += '<td>' + self.escapeHtml(p.supplier_name || '-') + '</td>';
                html += '<td>' + self.escapeHtml(p.branch_name || '-') + '</td>';
                html += '<td>' + self.escapeHtml(p.formatted_total) + '</td>';
                html += '<td><span class="status-badge ' + p.status + '">' + self.capitalize(p.status) + '</span></td>';
                html += '<td>' + self.escapeHtml(p.formatted_date) + '</td>';
                html += '<td class="text-right">';

                if (p.status !== 'received' && p.status !== 'cancelled') {
                    html += '<button class="receive-purchase orerp-btn orerp-btn-sm orerp-btn-success" data-id="' + p.id + '">Receive</button> ';
                }
                html += '<a href="#" class="edit-purchase orerp-btn orerp-btn-sm orerp-btn-outline" data-id="' + p.id + '">Edit</a> ';
                html += '<a href="#" class="delete-purchase orerp-btn orerp-btn-sm orerp-btn-danger" data-id="' + p.id + '">Delete</a>';
                html += '</td></tr>';
            }

            html += '</tbody></table>';
            $container.html(html);

            ObydullahERP.renderPagination($container, {
                total: data.total, total_pages: data.total_pages, current_page: data.current_page
            }, function (page) {
                Purchases.currentPage = page;
                Purchases.loadPurchases();
            });
        },

        addPoItem: function () {
            var row = '<tr class="po-item-row">';
            row += '<td><select name="new_items[][product_id]" class="po-product-select regular-text" required>';
            row += '<option value="">Select Product</option></select></td>';
            row += '<td><input type="number" name="new_items[][quantity]" class="po-qty" min="1" value="1" required></td>';
            row += '<td><input type="number" name="new_items[][unit_cost]" class="po-cost" step="0.01" min="0" value="0.00" required></td>';
            row += '<td class="po-item-total">0.00</td>';
            row += '<td><button type="button" class="button remove-item">X</button></td>';
            row += '</tr>';

            $('#po-items-body').append(row);
        },

        calculateItemTotal: function ($row) {
            var qty = parseFloat($row.find('.po-qty').val()) || 0;
            var cost = parseFloat($row.find('.po-cost').val()) || 0;
            $row.find('.po-item-total').text((qty * cost).toFixed(2));
        },

        calculateTotals: function () {
            var subtotal = 0;
            $('#po-items-body tr').each(function () {
                var qty = parseFloat($(this).find('.po-qty').val()) || 0;
                var cost = parseFloat($(this).find('.po-cost').val()) || 0;
                subtotal += qty * cost;
            });

            var tax = parseFloat($('#po-tax').val()) || 0;
            var total = subtotal + tax;

            $('#po-subtotal').text(subtotal.toFixed(2));
            $('#po-total').text(total.toFixed(2));
        },

        savePurchase: function () {
            var $btn = $('#submit-purchase');
            $btn.prop('disabled', true).find('.spinner').show();
            $btn.find('.btn-text').text('Saving...');

            var formData = new FormData($('#purchase-form')[0]);

            $.ajax({
                url: orerpAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-purchases';
                    } else {
                        alert(response.data || orerpAdmin.strings.error);
                    }
                },
                error: function () { alert(orerpAdmin.strings.error); },
                complete: function () { $btn.prop('disabled', false).find('.spinner').hide().siblings('.btn-text').text('Save Purchase Order'); }
            });
        },

        deletePurchase: function (id) {
            $.post(orerpAdmin.ajaxUrl, { action: 'orerp_delete_purchase', nonce: orerpPurchases.nonce, id: id }, function (r) {
                if (r.success) Purchases.loadPurchases();
                else alert(r.data || orerpAdmin.strings.error);
            });
        },

        receivePurchase: function (id) {
            $.post(orerpAdmin.ajaxUrl, { action: 'orerp_receive_purchase', nonce: orerpPurchases.nonce, purchase_id: id }, function (r) {
                if (r.success) Purchases.loadPurchases();
                else alert(r.data || orerpAdmin.strings.error);
            });
        },

        escapeHtml: function (t) {
            if (!t) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(t));
            return d.innerHTML;
        },

        capitalize: function (s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    };

    $(document).ready(function () {
        if ($('#purchases-list').length || $('#purchase-form').length) {
            Purchases.init();
        }
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Purchases = Purchases;

})(jQuery);
