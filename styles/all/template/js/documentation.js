(function () {
	"use strict";

	document.querySelectorAll(".docs-filter").forEach(function (input) {
		input.addEventListener("input", function () {
			var query = input.value.trim().toLowerCase();
			var panel = input.closest(".docs-nav-panel");

			panel.querySelectorAll(".docs-tree-section").forEach(function (section) {
				var heading = section.querySelector(":scope > a");
				var sectionMatches = !query || (heading && heading.textContent.toLowerCase().indexOf(query) !== -1);
				var visible = sectionMatches;

				section.querySelectorAll("li > a").forEach(function (link) {
					var subsection = link.closest(".docs-tree-subsection");
					var title = subsection && subsection.querySelector(".docs-tree-subsection-title");
					var matches = sectionMatches || (title && title.textContent.toLowerCase().indexOf(query) !== -1)
						|| link.textContent.toLowerCase().indexOf(query) !== -1;
					link.parentElement.hidden = !matches;
					visible = visible || matches;
				});

				section.querySelectorAll(".docs-tree-subsection").forEach(function (subsection) {
					var hasVisiblePage = Array.prototype.some.call(subsection.querySelectorAll("li > a"), function (link) {
						return !link.parentElement.hidden;
					});
					subsection.hidden = !hasVisiblePage;
				});

				section.hidden = !visible;
			});
		});
	});
}());
