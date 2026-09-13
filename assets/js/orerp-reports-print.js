/* Obydullah Restaurant ERP - Report print handler. */
(function () {
	'use strict';

	function printReport() {
		window.print();
	}

	window.addEventListener('load', printReport);

	var button = document.querySelector('.orerp-print-button');
	if (button) {
		button.addEventListener('click', function (event) {
			event.preventDefault();
			printReport();
		});
	}
})();