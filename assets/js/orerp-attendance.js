/**
 * Attendance & Shifts Module JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var Attendance = {
        init: function () {
            if (window.location.search.indexOf('page=orerp-attendance') === -1) return;

            $(document).on('click', '#run-attendance', function () {
                Attendance.loadAttendance(1);
            });

            $(document).on('click', '#run-shifts', function () {
                Attendance.loadShifts();
            });

            $(document).on('click', '.attendance-pagination a', function (e) {
                e.preventDefault();
                Attendance.loadAttendance($(this).data('page'));
            });
        },

        attendanceParams: function (page) {
            return {
                per_page: 20,
                page: page || 1,
                employee_id: $('#attendance-employee-filter').val() || 0,
                branch_id: $('#attendance-branch-filter').val() || 0,
                date_from: $('#attendance-date-from').val() || '',
                date_to: $('#attendance-date-to').val() || '',
                nonce: orerpEmployees.nonce
            };
        },

        loadAttendance: function (page) {
            var $container = $('#attendance-list');
            ObydullahERP.showLoading($container);

            var params = $.extend(this.attendanceParams(page), { action: 'orerp_get_attendance' });

            $.get(orerpAdmin.ajaxUrl, params, function (response) {
                if (!response.success) {
                    ObydullahERP.showEmpty($container, response.data || orerpAdmin.strings.error);
                    return;
                }
                Attendance.renderAttendance($container, response.data);
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        renderAttendance: function ($container, data) {
            var html = '';

            if (!data.attendance.length) {
                $container.html('<div class="orerp-empty"><p>No attendance records found.</p></div>');
                return;
            }

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Employee</th><th>Branch</th><th>Position</th><th>Clock In</th><th>Clock Out</th><th>Hours</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < data.attendance.length; i++) {
                var a = data.attendance[i];
                html += '<tr>';
                html += '<td><strong>' + Attendance.esc(a.employee_code || '#' + a.employee_id) + '</strong></td>';
                html += '<td>' + Attendance.esc(a.branch_name || '-') + '</td>';
                html += '<td>' + Attendance.esc(a.position || '-') + '</td>';
                html += '<td>' + Attendance.esc(a.formatted_clock_in) + '</td>';
                html += '<td>' + Attendance.esc(a.formatted_clock_out) + '</td>';
                html += '<td>' + Attendance.esc(a.hours_worked) + '</td>';
                html += '</tr>';
            }

            html += '</tbody></table>';

            html += '<div class="orerp-pagination">';
            html += '<span class="displaying-num">' + data.total + ' records</span>';
            html += '<span class="pagination-links">';
            if (data.current_page > 1) {
                html += '<a class="attendance-pagination prev-page" href="#" data-page="' + (data.current_page - 1) + '">&lsaquo;</a>';
            }
            html += '<span class="current-page">' + data.current_page + ' of ' + data.total_pages + '</span>';
            if (data.current_page < data.total_pages) {
                html += '<a class="attendance-pagination next-page" href="#" data-page="' + (data.current_page + 1) + '">&rsaquo;</a>';
            }
            html += '</span></div>';

            $container.html(html);
        },

        loadShifts: function () {
            var $container = $('#shifts-list');
            ObydullahERP.showLoading($container);

            $.get(orerpAdmin.ajaxUrl, {
                action: 'orerp_get_shifts',
                nonce: orerpEmployees.nonce,
                branch_id: $('#shift-branch-filter').val() || 0
            }, function (response) {
                if (!response.success) {
                    ObydullahERP.showEmpty($container, response.data || orerpAdmin.strings.error);
                    return;
                }
                Attendance.renderShifts($container, response.data);
            }).fail(function () {
                ObydullahERP.showEmpty($container, orerpAdmin.strings.error);
            });
        },

        renderShifts: function ($container, shifts) {
            var html = '';

            if (!shifts.length) {
                $container.html('<div class="orerp-empty"><p>No shifts defined for the selected branch.</p></div>');
                return;
            }

            html += '<table class="orerp-table widefat"><thead><tr>';
            html += '<th>Name</th><th>Branch</th><th>Start</th><th>End</th><th>Status</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < shifts.length; i++) {
                var s = shifts[i];
                html += '<tr>';
                html += '<td><strong>' + Attendance.esc(s.name || 'Shift') + '</strong></td>';
                html += '<td>' + Attendance.esc(s.branch_name || s.branch_id) + '</td>';
                html += '<td>' + Attendance.esc(s.start_time) + '</td>';
                html += '<td>' + Attendance.esc(s.end_time) + '</td>';
                html += '<td><span class="status-badge ' + (s.is_active ? 'active' : 'inactive') + '">' + (s.is_active ? 'Active' : 'Inactive') + '</span></td>';
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
        Attendance.init();
    });

    window.ObydullahERP = window.ObydullahERP || {};
    window.ObydullahERP.Attendance = Attendance;

})(jQuery);
