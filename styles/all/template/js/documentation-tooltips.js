(function () {
	"use strict";

	// A malformed attribute must not stop the remaining tooltips working.
	function parseJson(value) {
		try {
			return JSON.parse(value || "{}") || {};
		} catch (error) {
			return {};
		}
	}

	function annotate(node, explanation) {
		var term = document.createElement("span");
		term.className = "documentation-tooltip-term";
		term.tabIndex = 0;
		term.textContent = node.textContent;
		term.dataset.docTooltip = explanation;
		node.replaceWith(term);
	}

	document.querySelectorAll("[data-doc-abbreviations]").forEach(function (article) {
		var definitions = parseJson(article.dataset.docAbbreviations);
		var headings = parseJson(article.dataset.docEventHeadings);
		var seen = Object.create(null);
		var walker = document.createTreeWalker(article, NodeFilter.SHOW_TEXT);
		var nodes = [];
		while (walker.nextNode()) {
			if (!walker.currentNode.parentElement.closest("a, code, pre, abbr, script, style, textarea, [data-doc-tooltip]")) {
				nodes.push(walker.currentNode);
			}
		}
		nodes.forEach(function (node) {
			var pattern = /\b(?:ACP|MCP|CLI|DBAL)\b/g;
			var fragment = document.createDocumentFragment();
			var offset = 0;
			var match;
			while ((match = pattern.exec(node.textContent))) {
				if (seen[match[0]] || !definitions[match[0]]) {
					continue;
				}
				seen[match[0]] = true;
				fragment.appendChild(document.createTextNode(node.textContent.slice(offset, match.index)));
				var text = document.createTextNode(match[0]);
				fragment.appendChild(text);
				annotate(text, definitions[match[0]]);
				offset = match.index + match[0].length;
			}
			if (offset) {
				fragment.appendChild(document.createTextNode(node.textContent.slice(offset)));
				node.replaceWith(fragment);
			}
		});
		article.querySelectorAll("thead th").forEach(function (heading) {
			var explanation = headings[heading.textContent.trim()];
			if (explanation) {
				var text = document.createTextNode(heading.textContent);
				heading.replaceChildren(text);
				annotate(text, explanation);
			}
		});
		article.querySelectorAll("img[alt]").forEach(function (image) {
			var description = image.getAttribute("alt").trim();
			if (!description) {
				return;
			}
			var target = image.closest("a") || image;
			if (!target.dataset.docTooltip) {
				target.dataset.docTooltip = description;
			}
			if (target === image) {
				target.tabIndex = 0;
			}
			target.classList.add("documentation-tooltip-image");
		});
	});

	var popup = document.createElement("div");
	popup.id = "documentation-tooltip-popup";
	popup.className = "documentation-tooltip-popup";
	popup.setAttribute("role", "tooltip");
	popup.hidden = true;
	document.body.appendChild(popup);
	var active = null;
	var timer;

	function hide() {
		clearTimeout(timer);
		if (active) {
			var ids = (active.getAttribute("aria-describedby") || "").split(/\s+/).filter(function (id) {
				return id && id !== popup.id;
			});
			if (ids.length) {
				active.setAttribute("aria-describedby", ids.join(" "));
			} else {
				active.removeAttribute("aria-describedby");
			}
		}
		active = null;
		popup.hidden = true;
	}

	function show(target) {
		hide();
		active = target;
		popup.textContent = target.dataset.docTooltip;
		popup.hidden = false;
		var ids = target.getAttribute("aria-describedby");
		target.setAttribute("aria-describedby", (ids ? ids + " " : "") + popup.id);
		var rect = target.getBoundingClientRect();
		var left = Math.max(12, Math.min(rect.left, window.innerWidth - popup.offsetWidth - 12));
		var top = rect.bottom + 6;
		if (top + popup.offsetHeight > window.innerHeight - 12) {
			top = Math.max(12, rect.top - popup.offsetHeight - 6);
		}
		popup.style.left = left + "px";
		popup.style.top = top + "px";
	}

	function scheduleHide() {
		timer = setTimeout(function () {
			if (active !== document.activeElement) {
				hide();
			}
		}, 150);
	}

	document.querySelectorAll("[data-doc-tooltip]").forEach(function (target) {
		target.addEventListener("mouseenter", function () { show(target); });
		target.addEventListener("mouseleave", scheduleHide);
		target.addEventListener("focus", function () { show(target); });
		target.addEventListener("blur", hide);
		target.addEventListener("click", function () { show(target); });
	});
	popup.addEventListener("mouseenter", function () { clearTimeout(timer); });
	popup.addEventListener("mouseleave", scheduleHide);
	document.addEventListener("keydown", function (event) {
		if (event.key === "Escape") {
			hide();
		}
	});
	document.addEventListener("click", function (event) {
		if (!event.target.closest("[data-doc-tooltip], .documentation-tooltip-popup")) {
			hide();
		}
	});
	window.addEventListener("resize", hide);
	document.addEventListener("scroll", hide, true);
}());
