/**
 * Roles & Permissions Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Roles = {
        init: function () {
            if (window.location.search.indexOf('page=orerp-roles') === -1) return;

            Roles.loadRoles();

            $(document).on('change', '.role-assign', function () {
                var $select = $(this);
                var $row = $select.closest('tr');
                var employeeId = $select.data('employee');

                if (!ObydullahERP.confirm()) return;

                $.post(orerpAdmin.ajaxUrl, {
                    action: 'orerp_assign_employee_role',
                    nonce: orerpRoles.nonce,
                    user_id: employeeId,
                    role: $select.val()
                }, function (response) {
                    if (response.success) {
                        $row.find('.role-status').removeClass('hidden').text(response.data).fadeOut(2500);
                    } else {
                        alert(response.data || orerpAdmin.strings.error);
                    }
                });
            });
        },

        loadRoles: function () {
            var $container = $('#roles-list');
            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_employee_roles',
                nonce: orerpRoles.nonce
            }, function (response) {
                if (!response.success) {
                    ObydullahERP.showEmpty($container, response.data || orerpAdmin.strings.error);
                    return;
                }
                Roles.render($container, response.data);
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        render: function ($container, employees) {
            var roleLabels = {
                'restaurant_manager': 'Manager',
                'restaurant_kitchen_staff': 'Kitchen Staff',
                'restaurant_cashier': 'Cashier'
            };

            var html = '';

            if (!employees.length) {
                html = '<div class="orerp-empty"><p>No active employees found.</p></div>';
                $container.html(html);
                return;
            }

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Employee</th><th>Position</th><th>Branch</th><th>WP Role</th><th></th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < employees.length; i++) {
                var e = employees[i];
                var selected = e.wp_role ? (roleLabels[e.wp_role] || e.wp_role) : '';
                var selectedKey = e.wp_role || '';

                html += '<tr>';
                html += '<td><strong>' + Roles.esc(e.display_name || e.employee_code) + '</strong><br><small>' + Roles.esc(e.employee_code) + '</small></td>';
                html += '<td>' + Roles.esc(e.position || '-') + '</td>';
                html += '<td>' + Roles.esc(e.branch_name || '-') + '</td>';
                html += '<td>';
                if (!e.user_id) {
                    html += '<em>No linked user</em>';
                } else {
                    html += '<select class="role-assign" data-employee="' + e.user_id + '">';
                    html += '<option value="">None</option>';
                    html += '<option value="restaurant_manager"' + (selectedKey === 'restaurant_manager' ? ' selected' : '') + '>Manager</option>';
                    html += '<option value="restaurant_kitchen_staff"' + (selectedKey === 'restaurant_kitchen_staff' ? ' selected' : '') + '>Kitchen Staff</option>';
                    html += '<option value="restaurant_cashier"' + (selectedKey === 'restaurant_cashier' ? ' selected' : '') + '>Cashier</option>';
                    html += '</select>';
                }
                html += '</td>';
                html += '<td><span class="role-status hidden" style="color:#2271b1;"></span></td>';
                html += '</tr>';
            }

            html += '</tbody></table>';

            $container.html(html);
        },

        esc: function (t) {
            if (!t) return '';
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(t));
            return d.innerHTML;
        }
    };

    $(document).ready(function () {
        Roles.init();
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Roles = Roles;

})(jQuery);
