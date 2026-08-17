/**
 * Obydullah Restaurant ERP - Admin JS
 * @package Obydullah_ERP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    window.ObydullahERP = window.ObydullahERP || {};

    ObydullahERP.ajax = function (action, data, callback) {
        var postData = $.extend({}, data || {}, {
            action: action,
            nonce: orerpAdmin.nonce
        });

        $.post(orerpAdmin.ajaxUrl, postData, function (response) {
            if (typeof callback === 'function') {
                callback(response);
            }
        }).fail(function () {
            if (typeof callback === 'function') {
                callback({ success: false, data: orerpAdmin.strings.error });
            }
        });
    };

    ObydullahERP.showLoading = function ($container) {
        $container.html(
            '<div class="orerp-loading">' +
            '<span class="spinner is-active"></span>' +
            '<p>' + orerpAdmin.strings.loading + '</p>' +
            '</div>'
        );
    };

    ObydullahERP.showEmpty = function ($container, message) {
        $container.html(
            '<div class="orerp-empty">' +
            '<span class="dashicons dashicons-clipboard"></span>' +
            '<p>' + (message || orerpAdmin.strings.noData) + '</p>' +
            '</div>'
        );
    };

    ObydullahERP.confirm = function (message) {
        return window.confirm(message || orerpAdmin.strings.confirm);
    };

    ObydullahERP.formatCurrency = function (amount) {
        return parseFloat(amount || 0).toFixed(2);
    };

    ObydullahERP.initModal = function () {
        $(document).on('click', '.orerp-modal-close, .orerp-modal-cancel', function () {
            $(this).closest('.orerp-modal-overlay').removeClass('active');
        });

        $(document).on('click', '.orerp-modal-overlay', function (e) {
            if ($(e.target).hasClass('orerp-modal-overlay')) {
                $(this).removeClass('active');
            }
        });
    };

    ObydullahERP.openModal = function (selector) {
        $(selector).addClass('active');
    };

    ObydullahERP.closeModal = function (selector) {
        $(selector).removeClass('active');
    };

    ObydullahERP.initTabs = function () {
        $(document).on('click', '.orerp-tabs .tab', function () {
            var tabId = $(this).data('tab');

            $(this).closest('.orerp-tabs').find('.tab').removeClass('active');
            $(this).addClass('active');

            var $container = $(this).closest('.orerp-card, .wrap');
            $container.find('.orerp-tab-content').removeClass('active');
            $container.find('#' + tabId).addClass('active');
        });
    };

    ObydullahERP.initBranchSelector = function () {
        $(document).on('change', '#orerp-branch-select', function () {
            var branchId = $(this).val();
            ObydullahERP.ajax('orerp_set_current_branch', { branch_id: branchId }, function (response) {
                if (response.success) {
                    location.reload();
                }
            });
        });
    };

    ObydullahERP.renderPagination = function ($container, data, loadCallback) {
        var html = '<div class="orerp-pagination">';
        html += '<span class="displaying-num">' + data.total + ' items</span>';
        html += '<span class="pagination-links">';

        if (data.current_page > 1) {
            html += '<a class="prev-page" href="#" data-page="' + (data.current_page - 1) + '">&lsaquo;</a>';
        }

        html += '<span class="current-page">' + data.current_page + ' of ' + data.total_pages + '</span>';

        if (data.current_page < data.total_pages) {
            html += '<a class="next-page" href="#" data-page="' + (data.current_page + 1) + '">&rsaquo;</a>';
        }

        html += '</span></div>';

        $container.find('.orerp-pagination').remove();
        $container.append(html);

        $container.find('.orerp-pagination a').on('click', function (e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (typeof loadCallback === 'function') {
                loadCallback(page);
            }
        });
    };

    $(document).ready(function () {
        ObydullahERP.initModal();
        ObydullahERP.initTabs();
        ObydullahERP.initBranchSelector();
    });

})(jQuery);
