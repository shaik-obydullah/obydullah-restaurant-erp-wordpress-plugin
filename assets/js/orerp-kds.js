/* Obydullah Restaurant ERP - Kitchen Display System (frontend board). */
(function () {
	'use strict';

	var cfg = window.orerpKds || {};
	var KDS = {
		branchId: parseInt(cfg.branchId, 10) || 0,
		state: {},
		timer: null,
		el: function (id) { return document.getElementById(id); },
		url: function (action, extra) {
			var params = extra || '';
			return cfg.ajaxUrl + '?action=' + encodeURIComponent(action) + params;
		},
		loadBranches: function () {
			var self = this;
			var req = new XMLHttpRequest();
			req.open('GET', this.url('orerp_get_branches', '&nonce=' + encodeURIComponent(cfg.branchNonce)));
			req.onload = function () {
				var res;
				try { res = JSON.parse(req.responseText); } catch (e) { return; }
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
			var url = this.url('orerp_get_kitchen_orders', '&nonce=' + encodeURIComponent(cfg.nonce)) +
				'&per_page=100&page=1&branch_id=' + encodeURIComponent(branch) + '&status=&date=' +
				encodeURIComponent(new Date().toISOString().slice(0, 10));
			var req = new XMLHttpRequest();
			req.open('GET', url);
			req.onload = function () {
				var res;
				try { res = JSON.parse(req.responseText); } catch (e) { return; }
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
			var html = '';

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
			var cards = document.querySelectorAll('.orerp-kds__btn');
			Array.prototype.forEach.call(cards, function (btn) {
				btn.onclick = function () {
					var card = this.closest('.orerp-kds__card');
					var body = 'action=orerp_update_order_status&nonce=' + encodeURIComponent(cfg.nonce) +
						'&order_id=' + card.getAttribute('data-id') + '&status=' + this.getAttribute('data-next');
					var req = new XMLHttpRequest();
					req.open('POST', cfg.ajaxUrl);
					req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					req.onload = function () {
						var res;
						try { res = JSON.parse(req.responseText); } catch (e) { return; }
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