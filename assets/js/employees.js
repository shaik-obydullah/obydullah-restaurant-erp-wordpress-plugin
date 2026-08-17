/**
 * Employees Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Employees = {
        currentPage: 1,
        perPage: 20,

        init: function () {
            this.bindEvents();
            this.loadEmployees();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('submit', '#employee-form', function (e) {
                e.preventDefault();
                self.saveEmployee();
            });

            $(document).on('click', '.edit-employee', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') +
                    'admin.php?page=orerp-employees&action=edit&id=' + id;
            });

            $(document).on('click', '.delete-employee', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                if (ObydullahERP.confirm('Are you sure you want to delete this employee?')) {
                    self.deleteEmployee(id);
                }
            });

            $(document).on('click', '#search-employees', function () {
                self.currentPage = 1;
                self.loadEmployees();
            });

            $(document).on('click', '#reset-employee-search', function () {
                $('#employee-search-input').val('');
                $('#employee-branch-filter').val('');
                self.currentPage = 1;
                self.loadEmployees();
            });
        },

        loadEmployees: function () {
            var self = this;
            var $container = $('#employees-list');
            var search = $('#employee-search-input').val() || '';
            var branchId = $('#employee-branch-filter').val() || 0;

            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_employees',
                nonce: orerpEmployees.nonce,
                page: self.currentPage,
                per_page: self.perPage,
                search: search,
                branch_id: branchId
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
            var employees = data.employees;

            if (employees.length === 0) {
                ObydullahERP.showEmpty($container, 'No employees found.');
                return;
            }

            var html = '<div style="margin-bottom: 15px;">';
            html += '<div class="orerp-filters">';
            html += '<div class="filter-group">';
            html += '<label>Search</label>';
            html += '<input type="text" id="employee-search-input" placeholder="Search by code or position..." class="regular-text">';
            html += '</div>';
            html += '<div class="filter-group">';
            html += '<label>Branch</label>';
            html += '<select id="employee-branch-filter" class="regular-text"><option value="">All Branches</option></select>';
            html += '</div>';
            html += '<div class="filter-actions">';
            html += '<button type="button" id="search-employees" class="button button-primary">Search</button>';
            html += '<button type="button" id="reset-employee-search" class="button">Reset</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            html += '<table class="orerp-table widefat">';
            html += '<thead><tr>';
            html += '<th>Code</th>';
            html += '<th>Name</th>';
            html += '<th>Position</th>';
            html += '<th>Branch</th>';
            html += '<th>Rate</th>';
            html += '<th>Hire Date</th>';
            html += '<th>Status</th>';
            html += '<th class="text-right">Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < employees.length; i++) {
                var emp = employees[i];
                html += '<tr>';
                html += '<td><strong>' + self.escapeHtml(emp.employee_code) + '</strong></td>';
                html += '<td>' + self.escapeHtml(emp.user_display_name || '-') + '</td>';
                html += '<td>' + self.escapeHtml(emp.position || '-') + '</td>';
                html += '<td>' + self.escapeHtml(emp.branch_name || '-') + '</td>';
                html += '<td>' + self.escapeHtml(emp.formatted_rate) + '</td>';
                html += '<td>' + self.escapeHtml(emp.formatted_hire_date || '-') + '</td>';
                html += '<td><span class="status-badge ' + (emp.is_active == 1 ? 'active' : 'inactive') + '">';
                html += emp.is_active == 1 ? 'Active' : 'Inactive';
                html += '</span></td>';
                html += '<td class="text-right">';
                html += '<a href="#" class="edit-employee orerp-btn orerp-btn-sm orerp-btn-outline" data-id="' + emp.id + '">Edit</a> ';
                html += '<a href="#" class="delete-employee orerp-btn orerp-btn-sm orerp-btn-danger" data-id="' + emp.id + '">Delete</a>';
                html += '</td>';
                html += '</tr>';
            }

            html += '</tbody></table>';

            $container.html(html);

            ObydullahERP.renderPagination($container, {
                total: data.total,
                total_pages: data.total_pages,
                current_page: data.current_page
            }, function (page) {
                Employees.currentPage = page;
                Employees.loadEmployees();
            });
        },

        saveEmployee: function () {
            var $btn = $('#submit-employee');
            var $spinner = $btn.find('.spinner');
            var $btnText = $btn.find('.btn-text');

            $btn.prop('disabled', true);
            $spinner.show();
            $btnText.text('Saving...');

            $.post(orerpAdmin.ajaxUrl, $('#employee-form').serialize(), function (response) {
                if (response.success) {
                    window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') +
                        'admin.php?page=orerp-employees';
                } else {
                    alert(response.data || orerpAdmin.strings.error);
                }
            }).fail(function () {
                alert(orerpAdmin.strings.error);
            }).always(function () {
                $btn.prop('disabled', false);
                $spinner.hide();
                $btnText.text('Save Employee');
            });
        },

        deleteEmployee: function (id) {
            $.post(orerpAdmin.ajaxUrl, {
                action: 'orerp_delete_employee',
                nonce: orerpEmployees.nonce,
                id: id
            }, function (response) {
                if (response.success) {
                    Employees.loadEmployees();
                } else {
                    alert(response.data || orerpAdmin.strings.error);
                }
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
        if ($('#employees-list').length || $('#employee-form').length) {
            Employees.init();
        }
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Employees = Employees;

})(jQuery);
