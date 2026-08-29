(function () {
	"use strict";

	document.querySelectorAll(".docs-filter").forEach(function (input) {
		input.addEventListener("input", function () {
			var query = input.value.trim().toLowerCase();
			var panel = input.closest(".docs-nav-panel");

			panel.querySelectorAll(".docs-tree-section").forEach(function (section) {
				var links = section.querySelectorAll("a");
				var visible = false;

				links.forEach(function (link) {
					var matches = !query || link.textContent.toLowerCase().indexOf(query) !== -1;
					link.parentElement.hidden = !matches;
					visible = visible || matches;
				});

				section.hidden = !visible;
			});
		});
	});
}());
