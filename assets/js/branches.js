/**
 * Branches Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var ObydullahERP = window.ObydullahERP || {};

    var Branches = {
        currentPage: 1,
        perPage: 20,

        init: function () {
            this.bindEvents();
            this.loadBranches();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('submit', '#branch-form', function (e) {
                e.preventDefault();
                self.saveBranch();
            });

            $(document).on('click', '.edit-branch', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-branches&action=edit&id=' + id;
            });

            $(document).on('click', '.delete-branch', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                if (ObydullahERP.confirm('Are you sure you want to delete this branch?')) {
                    self.deleteBranch(id);
                }
            });

            $(document).on('click', '#search-branches', function () {
                self.currentPage = 1;
                self.loadBranches();
            });

            $(document).on('click', '#reset-branch-search', function () {
                $('#branch-search-input').val('');
                self.currentPage = 1;
                self.loadBranches();
            });

            $(document).on('keyup', '#branch-search-input', function (e) {
                if (e.keyCode === 13) {
                    self.currentPage = 1;
                    self.loadBranches();
                }
            });
        },

        loadBranches: function () {
            var self = this;
            var $container = $('#branches-list');
            var search = $('#branch-search-input').val() || '';

            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_branches',
                nonce: orerpBranches.nonce,
                page: self.currentPage,
                per_page: self.perPage,
                search: search
            }, function (response) {
                if (response.success) {
                    self.renderBranchesTable($container, response.data);
                } else {
                    ObydullahERP.showEmpty($container, response.data);
                }
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        renderBranchesTable: function ($container, data) {
            var branches = data.branches;

            if (branches.length === 0) {
                ObydullahERP.showEmpty($container, 'No branches found.');
                return;
            }

            var html = '<div style="margin-bottom: 15px;">';
            html += '<div class="orerp-filters">';
            html += '<div class="filter-group">';
            html += '<label>Search</label>';
            html += '<input type="text" id="branch-search-input" placeholder="Search branches..." class="regular-text">';
            html += '</div>';
            html += '<div class="filter-actions">';
            html += '<button type="button" id="search-branches" class="button button-primary">Search</button>';
            html += '<button type="button" id="reset-branch-search" class="button">Reset</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            html += '<table class="orerp-table widefat">';
            html += '<thead><tr>';
            html += '<th>Name</th>';
            html += '<th>Code</th>';
            html += '<th>Phone</th>';
            html += '<th>Email</th>';
            html += '<th>Manager</th>';
            html += '<th>Status</th>';
            html += '<th class="text-right">Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < branches.length; i++) {
                var branch = branches[i];
                html += '<tr>';
                html += '<td><strong>' + self.escapeHtml(branch.name) + '</strong></td>';
                html += '<td>' + self.escapeHtml(branch.code) + '</td>';
                html += '<td>' + self.escapeHtml(branch.phone || '-') + '</td>';
                html += '<td>' + self.escapeHtml(branch.email || '-') + '</td>';
                html += '<td>' + self.escapeHtml(branch.manager_name) + '</td>';
                html += '<td><span class="status-badge ' + (branch.is_active == 1 ? 'active' : 'inactive') + '">';
                html += branch.is_active == 1 ? 'Active' : 'Inactive';
                html += '</span></td>';
                html += '<td class="text-right">';
                html += '<a href="#" class="edit-branch orerp-btn orerp-btn-sm orerp-btn-outline" data-id="' + branch.id + '">Edit</a> ';
                html += '<a href="#" class="delete-branch orerp-btn orerp-btn-sm orerp-btn-danger" data-id="' + branch.id + '">Delete</a>';
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
                Branches.currentPage = page;
                Branches.loadBranches();
            });
        },

        saveBranch: function () {
            var self = this;
            var $btn = $('#submit-branch');
            var $spinner = $btn.find('.spinner');
            var $btnText = $btn.find('.btn-text');

            $btn.prop('disabled', true);
            $spinner.show();
            $btnText.text('Saving...');

            var formData = $('#branch-form').serialize();

            $.post(orerpAdmin.ajaxUrl, formData, function (response) {
                if (response.success) {
                    window.location.href = orerpAdmin.ajaxUrl.replace('admin-ajax.php', '') + 'admin.php?page=orerp-branches';
                } else {
                    alert(response.data || orerpAdmin.strings.error);
                }
            }).fail(function () {
                alert(orerpAdmin.strings.error);
            }).always(function () {
                $btn.prop('disabled', false);
                $spinner.hide();
                $btnText.text('Save Branch');
            });
        },

        deleteBranch: function (id) {
            $.post(orerpAdmin.ajaxUrl, {
                action: 'orerp_delete_branch',
                nonce: orerpBranches.nonce,
                id: id
            }, function (response) {
                if (response.success) {
                    Branches.loadBranches();
                } else {
                    alert(response.data || orerpAdmin.strings.error);
                }
            }).fail(function () {
                alert(orerpAdmin.strings.error);
            });
        },

        escapeHtml: function (text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    var self = Branches;

    $(document).ready(function () {
        if ($('#branches-list').length || $('#branch-form').length) {
            Branches.init();
        }
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Branches = Branches;

})(jQuery);
