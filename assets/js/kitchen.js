/**
 * Kitchen Module JS - KDS + Recipes
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Kitchen = {
        currentPage: 1,
        perPage: 50,
        refreshTimer: null,

        init: function () {
            var page = window.location.search;
            if (page.indexOf('page=orerp-kitchen') > -1) {
                this.initKDS();
            } else if (page.indexOf('page=orerp-recipes') > -1) {
                this.initRecipes();
            }
        },

        /* ============================================
           KITCHEN DISPLAY SYSTEM (KDS)
           ============================================ */

        initKDS: function () {
            var self = this;
            self.loadOrders();
            self.loadStats();

            $(document).on('change', '#kitchen-branch-filter, #kitchen-station-filter, #kitchen-status-filter', function () {
                self.currentPage = 1;
                self.loadOrders();
            });

            $(document).on('click', '.kds-action-btn', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var orderId = $btn.data('id');
                var action = $btn.data('action');
                self.updateOrderStatus(orderId, action, $btn);
            });

            $(document).on('submit', '#kitchen-order-form', function (e) {
                e.preventDefault();
                self.createOrder();
            });

            // Auto-refresh every 15 seconds
            self.refreshTimer = setInterval(function () {
                if (document.hidden) return;
                self.loadOrders();
                self.loadStats();
            }, 15000);

            $(window).on('beforeunload', function () {
                if (self.refreshTimer) clearInterval(self.refreshTimer);
            });
        },

        loadOrders: function () {
            var $container = $('#kitchen-orders-grid');
            if (!$container.length) return;

            var params = {
                action: 'orerp_get_kitchen_orders',
                nonce: orerpKitchen.nonce,
                page: this.currentPage,
                branch_id: $('#kitchen-branch-filter').val() || '',
                station: $('#kitchen-station-filter').val() || '',
                status: $('#kitchen-status-filter').val() || ''
            };

            $.get(orerpAdmin.ajaxUrl, params, function (response) {
                if (response.success) {
                    Kitchen.renderOrdersGrid($container, response.data);
                } else {
                    ObydullahERP.showEmpty($container, response.data);
                }
            });
        },

        renderOrdersGrid: function ($container, data) {
            var orders = data.orders;
            if (!orders.length) {
                $container.html('<div class="kds-empty"><p>No kitchen orders found for this filter.</p></div>');
                return;
            }

            var html = '';
            for (var i = 0; i < orders.length; i++) {
                var o = orders[i];
                var priorityClass = o.priority == 2 ? 'urgent' : (o.priority == 1 ? 'high' : 'normal');
                var statusClass = 'kds-card-' + o.status;

                html += '<div class="kds-card ' + statusClass + ' ' + priorityClass + '" data-id="' + o.id + '">';

                html += '<div class="kds-card-header">';
                html += '<span class="kds-order-num">#' + o.id + '</span>';
                html += '<span class="kds-priority ' + priorityClass + '">' + Kitchen.priorityLabel(o.priority) + '</span>';
                html += '</div>';

                html += '<div class="kds-card-body">';
                html += '<div class="kds-order-meta">';
                html += '<span class="kds-station">' + Kitchen.esc(o.station || 'General') + '</span>';
                html += '<span class="kds-time">' + Kitchen.esc(o.elapsed) + '</span>';
                html += '</div>';
                html += '<div class="kds-order-id">WC Order: #' + o.order_id + '</div>';
                if (o.notes) {
                    html += '<div class="kds-notes">' + Kitchen.esc(o.notes) + '</div>';
                }
                if (o.estimated_time) {
                    html += '<div class="kds-est">Est: ' + o.estimated_time + 'min</div>';
                }
                html += '</div>';

                html += '<div class="kds-card-footer">';
                html += '<span class="kds-status-badge ' + o.status + '">' + Kitchen.esc(o.status) + '</span>';

                if (o.status !== 'completed' && o.status !== 'cancelled') {
                    html += '<div class="kds-actions">';
                    if (o.status === 'pending') {
                        html += '<button class="button kds-action-btn orerp-btn-sm orerp-btn-primary" data-id="' + o.id + '" data-action="preparing">Start</button> ';
                    }
                    if (o.status === 'preparing') {
                        html += '<button class="button kds-action-btn orerp-btn-sm orerp-btn-success" data-id="' + o.id + '" data-action="ready">Ready</button> ';
                    }
                    if (o.status === 'ready') {
                        html += '<button class="button kds-action-btn orerp-btn-sm orerp-btn-primary" data-id="' + o.id + '" data-action="completed">Complete</button> ';
                    }
                    html += '<button class="button kds-action-btn orerp-btn-sm orerp-btn-danger" data-id="' + o.id + '" data-action="cancelled">Cancel</button>';
                    html += '</div>';
                }

                html += '</div>';
                html += '</div>';
            }

            $container.html(html);
        },

        updateOrderStatus: function (orderId, status, $btn) {
            $btn.prop('disabled', true);

            $.post(orerpAdmin.ajaxUrl, {
                action: 'orerp_update_order_status',
                nonce: orerpKitchen.nonce,
                order_id: orderId,
                status: status
            }, function (response) {
                if (response.success) {
                    Kitchen.loadOrders();
                    Kitchen.loadStats();
                } else {
                    alert(response.data || orerpAdmin.strings.error);
                    $btn.prop('disabled', false);
                }
            });
        },

        loadStats: function () {
            var branchId = $('#kitchen-branch-filter').val() || '';

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_kitchen_stats',
                nonce: orerpKitchen.nonce,
                branch_id: branchId
            }, function (response) {
                if (response.success) {
                    var s = response.data;
                    $('#stat-pending .stat-number').text(s.pending);
                    $('#stat-preparing .stat-number').text(s.preparing);
                    $('#stat-ready .stat-number').text(s.ready);
                    $('#stat-completed .stat-number').text(s.completed_today);
                }
            });
        },

        createOrder: function () {
            var $btn = $('#submit-kitchen-order');
            $btn.prop('disabled', true).find('.spinner').show();

            $.post(orerpAdmin.ajaxUrl, $('#kitchen-order-form').serialize(), function (r) {
                if (r.success) {
                    window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-kitchen';
                } else {
                    alert(r.data || orerpAdmin.strings.error);
                }
            }).always(function () {
                $btn.prop('disabled', false).find('.spinner').hide();
            });
        },

        priorityLabel: function (p) {
            if (p == 2) return 'URGENT';
            if (p == 1) return 'HIGH';
            return 'Normal';
        },

        /* ============================================
           RECIPES
           ============================================ */

        initRecipes: function () {
            var self = this;
            self.loadRecipes();

            $(document).on('submit', '#recipe-form', function (e) {
                e.preventDefault();
                self.saveRecipe();
            });

            $(document).on('click', '.edit-recipe', function (e) {
                e.preventDefault();
                window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') +
                    'admin.php?page=orerp-recipes&action=edit&id=' + $(this).data('id');
            });

            $(document).on('click', '.delete-recipe', function (e) {
                e.preventDefault();
                if (ObydullahERP.confirm('Delete this recipe and all its ingredients?')) {
                    self.deleteRecipe($(this).data('id'));
                }
            });

            $(document).on('click', '#add-ingredient', function () {
                self.addIngredientRow();
            });

            $(document).on('click', '.remove-ingredient', function () {
                $(this).closest('.ingredient-row').remove();
            });
        },

        loadRecipes: function () {
            var $container = $('#recipes-list');
            if (!$container.length) return;

            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_recipes',
                nonce: orerpRecipes.nonce,
                page: this.currentPage,
                per_page: 20
            }, function (response) {
                if (response.success) {
                    Kitchen.renderRecipesTable($container, response.data);
                } else {
                    ObydullahERP.showEmpty($container, response.data);
                }
            });
        },

        renderRecipesTable: function ($container, data) {
            var recipes = data.recipes;
            if (!recipes.length) {
                ObydullahERP.showEmpty($container, 'No recipes found.');
                return;
            }

            var html = '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Name</th><th>Product</th><th>Ingredients</th><th>Prep</th><th>Cook</th><th>Total</th><th>Status</th><th class="text-right">Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < recipes.length; i++) {
                var r = recipes[i];
                html += '<tr>';
                html += '<td><strong>' + Kitchen.esc(r.name) + '</strong></td>';
                html += '<td>' + Kitchen.esc(r.product_name || 'N/A') + '</td>';
                html += '<td>' + (r.ingredient_count || 0) + '</td>';
                html += '<td>' + (r.prep_time_minutes ? r.prep_time_minutes + 'm' : '-') + '</td>';
                html += '<td>' + (r.cook_time_minutes ? r.cook_time_minutes + 'm' : '-') + '</td>';
                html += '<td>' + (r.total_time ? r.total_time + 'm' : '-') + '</td>';
                html += '<td><span class="status-badge ' + (r.is_active == 1 ? 'active' : 'inactive') + '">';
                html += (r.is_active == 1 ? 'Active' : 'Inactive') + '</span></td>';
                html += '<td class="text-right">';
                html += '<a href="#" class="edit-recipe orerp-btn orerp-btn-sm orerp-btn-outline" data-id="' + r.id + '">Edit</a> ';
                html += '<a href="#" class="delete-recipe orerp-btn orerp-btn-sm orerp-btn-danger" data-id="' + r.id + '">Delete</a>';
                html += '</td></tr>';
            }

            html += '</tbody></table>';

            ObydullahERP.renderPagination($container, {
                total: data.total, total_pages: data.total_pages, current_page: data.current_page
            }, function (page) {
                Kitchen.currentPage = page;
                Kitchen.loadRecipes();
            });

            $container.html(html);
        },

        addIngredientRow: function () {
            var options = '<option value="">Select</option>';
            var selectHtml = '<select name="ingredients[][product_id]" class="regular-text" required>' + options + '</select>';

            var html = '<tr class="ingredient-row">';
            html += '<td>' + selectHtml + '</td>';
            html += '<td><input type="number" name="ingredients[][quantity]" class="small-text" step="0.001" min="0" required></td>';
            html += '<td><input type="text" name="ingredients[][unit]" class="regular-text" placeholder="g, kg, ml, L..."></td>';
            html += '<td><input type="text" name="ingredients[][notes]" class="regular-text"></td>';
            html += '<td class="text-right"><button type="button" class="button remove-ingredient">Remove</button></td>';
            html += '</tr>';
            $('#ingredients-body').append(html);
        },

        saveRecipe: function () {
            var $btn = $('#submit-recipe');
            $btn.prop('disabled', true).find('.spinner').show();

            $.post(orerpAdmin.ajaxUrl, $('#recipe-form').serialize(), function (r) {
                if (r.success) {
                    window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-recipes';
                } else {
                    alert(r.data || orerpAdmin.strings.error);
                }
            }).always(function () {
                $btn.prop('disabled', false).find('.spinner').hide();
            });
        },

        deleteRecipe: function (id) {
            $.post(orerpAdmin.ajaxUrl, {
                action: 'orerp_delete_recipe',
                nonce: orerpRecipes.nonce,
                id: id
            }, function (r) {
                if (r.success) Kitchen.loadRecipes();
                else alert(r.data || orerpAdmin.strings.error);
            });
        },

        esc: function (t) {
            if (!t) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(t));
            return d.innerHTML;
        }
    };

    $(document).ready(function () {
        Kitchen.init();
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Kitchen = Kitchen;
    window.ObydullahERP.Recipes = Kitchen;

})(jQuery);
