<?php
/**
 * Kitchen Display System (KDS) - Standalone Board
 *
 * Rendered via the [orerp_kds] shortcode. Polls the kitchen order endpoints
 * and displays a color-coded board for kitchen staff.
 *
 * @package Obydullah_ERP
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$orerp_kds_branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$orerp_kds_nonce = wp_create_nonce('orerp_kitchen');
$orerp_kds_branch_nonce = wp_create_nonce('orerp_branches');
$orerp_kds_ajax  = admin_url('admin-ajax.php');
?>
<div id="orerp-kds" class="orerp-kds">
    <style>
        .orerp-kds { --kds-bg: #1d2327; --kds-text: #fff; --kds-pending: #f0ad4e; --kds-preparing: #5bc0de; --kds-ready: #5cb85c; background: var(--kds-bg); color: var(--kds-text); min-height: 100vh; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .orerp-kds * { box-sizing: border-box; }
        .orerp-kds__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        .orerp-kds__clock { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
        .orerp-kds__filter { padding: 6px 10px; border-radius: 4px; border: 1px solid #444; background: #2c3338; color: var(--kds-text); }
        .orerp-kds__stats { display: flex; gap: 12px; margin-bottom: 18px; }
        .orerp-kds__stat { background: #2c3338; padding: 10px 18px; border-radius: 6px; border-left: 4px solid var(--kds-pending); }
        .orerp-kds__stat .n { font-size: 26px; font-weight: 800; }
        .orerp-kds__stat .l { font-size: 12px; opacity: .8; }
        .orerp-kds__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
        .orerp-kds__card { background: #2c3338; border-radius: 8px; padding: 14px; border-top: 5px solid var(--kds-pending); }
        .orerp-kds__card[data-status="preparing"] { border-top-color: var(--kds-preparing); }
        .orerp-kds__card[data-status="ready"] { border-top-color: var(--kds-ready); }
        .orerp-kds__card-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
        .orerp-kds__id { font-size: 18px; font-weight: 800; }
        .orerp-kds__time { font-size: 12px; opacity: .7; }
        .orerp-kds__meta { font-size: 13px; opacity: .85; margin-bottom: 10px; }
        .orerp-kds__actions { display: flex; gap: 8px; margin-top: 10px; }
        .orerp-kds__btn { flex: 1; border: none; border-radius: 5px; padding: 10px 8px; font-weight: 700; cursor: pointer; color: #1d2327; font-size: 13px; }
        .orerp-kds__btn[data-next="preparing"] { background: var(--kds-preparing); }
        .orerp-kds__btn[data-next="ready"] { background: var(--kds-ready); }
        .orerp-kds__btn[data-next="completed"] { background: #6c757d; color: #fff; }
        .orerp-kds__empty { text-align: center; padding: 60px 20px; opacity: .6; grid-column: 1 / -1; }
        .orerp-kds__err { background: #c0392b; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; display: none; }
        .orerp-kds__notes { font-size: 13px; font-style: italic; opacity: .8; margin-top: 8px; }
    </style>

    <div class="orerp-kds__top">
        <div class="orerp-kds__clock" id="orerp-kds-clock"></div>
        <div>
            <label for="orerp-kds-branch"><?php esc_html_e('Branch', 'obydullah-restaurant-erp'); ?></label>
            <select id="orerp-kds-branch" class="orerp-kds__filter">
                <option value=""><?php esc_html_e('All', 'obydullah-restaurant-erp'); ?></option>
            </select>
        </div>
    </div>

    <div class="orerp-kds__err" id="orerp-kds-err"></div>

    <div class="orerp-kds__stats">
        <div class="orerp-kds__stat"><div class="n" id="orerp-kds-n-pending">0</div><div class="l"><?php esc_html_e('Pending', 'obydullah-restaurant-erp'); ?></div></div>
        <div class="orerp-kds__stat" style="border-left-color:var(--kds-preparing);"><div class="n" id="orerp-kds-n-preparing">0</div><div class="l"><?php esc_html_e('Preparing', 'obydullah-restaurant-erp'); ?></div></div>
        <div class="orerp-kds__stat" style="border-left-color:var(--kds-ready);"><div class="n" id="orerp-kds-n-ready">0</div><div class="l"><?php esc_html_e('Ready', 'obydullah-restaurant-erp'); ?></div></div>
    </div>

    <div class="orerp-kds__grid" id="orerp-kds-grid">
        <div class="orerp-kds__empty"><?php esc_html_e('Loading orders...', 'obydullah-restaurant-erp'); ?></div>
    </div>
</div>

<script>
(function () {
    var KDS = {
        branchId: <?php echo intval($orerp_kds_branch_id); ?>,
        state: {},
        timer: null,
        el: function (id) { return document.getElementById(id); },
        loadBranches: function () {
            var self = this;
            var req = new XMLHttpRequest();
            req.open('GET', '<?php echo esc_url($orerp_kds_ajax); ?>?action=orerp_get_branches&nonce=' + encodeURIComponent('<?php echo esc_js($orerp_kds_branch_nonce); ?>'));
            req.onload = function () {
                var res = JSON.parse(req.responseText);
                if (!res.success) return;
                var sel = self.el('orerp-kds-branch');
                res.data.forEach(function (b) {
                    var o = document.createElement('option');
                    o.value = b.id;
                    o.text = b.name;
                    if (b.id === self.branchId) o.selected = true;
                    sel.appendChild(o);
                });
                self.start();
            };
            req.send();
        },
        start: function () {
            this.load();
            this.timer = setInterval(this.load.bind(this), 10000);
            var t = this.el('orerp-kds-clock');
            var tick = function () { t.textContent = new Date().toLocaleString(); };
            tick();
            setInterval(tick, 1000);
        },
        load: function () {
            var self = this;
            var branch = this.el('orerp-kds-branch').value || this.branchId;
            var url = '<?php echo esc_url($orerp_kds_ajax); ?>?action=orerp_get_kitchen_orders&nonce=' +
                encodeURIComponent('<?php echo esc_js($orerp_kds_nonce); ?>') +
                '&per_page=100&page=1&branch_id=' + branch + '&status=&date=' +
                encodeURIComponent(new Date().toISOString().slice(0, 10));
            var req = new XMLHttpRequest();
            req.open('GET', url);
            req.onload = function () {
                var res = JSON.parse(req.responseText);
                if (!res.success) { self.showError(res.data || 'Error'); return; }
                self.render(res.data.orders);
            };
            req.send();
        },
        showError: function (msg) {
            var e = this.el('orerp-kds-err');
            e.textContent = msg;
            e.style.display = 'block';
        },
        render: function (orders) {
            var grid = this.el('orerp-kds-grid');
            var counts = { pending: 0, preparing: 0, ready: 0 };
            var html = 'orerp_';

            (orders || []).forEach(function (o) {
                if (counts[o.status] !== undefined) counts[o.status]++;
                html += '<div class="orerp-kds__card" data-status="' + o.status + '" data-id="' + o.id + '">';
                html += '<div class="orerp-kds__card-head"><span class="orerp-kds__id">#' + o.order_id + '</span><span class="orerp-kds__time">' + o.elapsed + '</span></div>';
                html += '<div class="orerp-kds__meta">Station: ' + (o.station || '-') + ' &middot; Priority: ' + o.priority + '</div>';
                if (o.notes) html += '<div class="orerp-kds__notes">' + o.notes + '</div>';
                html += '<div class="orerp-kds__actions">';
                if (o.status === 'pending') html += '<button class="orerp-kds__btn" data-next="preparing">Start</button>';
                if (o.status === 'preparing') html += '<button class="orerp-kds__btn" data-next="ready">Ready</button>';
                if (o.status === 'ready') html += '<button class="orerp-kds__btn" data-next="completed">Done</button>';
                html += '</div></div>';
            });

            if (!orders || !orders.length) {
                html = '<div class="orerp-kds__empty">No orders in the kitchen.</div>';
            }

            grid.innerHTML = html;

            this.el('orerp-kds-n-pending').textContent = counts.pending;
            this.el('orerp-kds-n-preparing').textContent = counts.preparing;
            this.el('orerp-kds-n-ready').textContent = counts.ready;

            this.bindButtons();
        },
        bindButtons: function () {
            var self = this;
            Array.prototype.forEach.call(document.querySelectorAll('.orerp-kds__btn'), function (btn) {
                btn.onclick = function () {
                    var card = this.closest('.orerp-kds__card');
                    var body = 'action=orerp_update_order_status&nonce=' + encodeURIComponent('<?php echo esc_js($orerp_kds_nonce); ?>') +
                        '&order_id=' + card.getAttribute('data-id') + '&status=' + this.getAttribute('data-next');
                    var req = new XMLHttpRequest();
                    req.open('POST', '<?php echo esc_url($orerp_kds_ajax); ?>');
                    req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    req.onload = function () {
                        var res = JSON.parse(req.responseText);
                        if (res.success) self.load();
                    };
                    req.send(body);
                };
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        KDS.loadBranches();
    });
})();
</script>
