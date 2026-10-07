/*
 * Documentation full-text search results, powered by Pagefind.
 *
 * Renders results into the element carrying data-doc-search. The
 * standalone site in phpbbdocs-hugo ships its own copy of this file; keep
 * the two in sync.
 *
 * Attributes on that element:
 *   data-doc-search-bundles   JSON array of Pagefind bundle URLs, each
 *                             ending in "/". The first is loaded and the
 *                             rest are merged into it.
 *   data-doc-search-messages  JSON object of display strings: length,
 *                             noResults, unavailable, loading.
 *   data-doc-search-query     Optional query; defaults to the page's ?q=.
 *   data-doc-search-base-url  Optional site root the indexed paths are
 *                             relative to; defaults to "/".
 *   data-doc-search-links     Optional JSON {"prefix", "template",
 *                             "placeholder"}: a result URL starting with
 *                             prefix becomes template with placeholder
 *                             replaced by the rest of the URL (used to map
 *                             Hugo paths to phpBB routes).
 */
(function () {
	"use strict";

	var MAX_RESULTS = 50;
	var panel = document.querySelector("[data-doc-search]");

	if (!panel) {
		return;
	}

	function readJson(name, fallback) {
		try {
			return JSON.parse(panel.getAttribute(name)) || fallback;
		} catch (error) {
			return fallback;
		}
	}

	var bundles = readJson("data-doc-search-bundles", []);
	var messages = readJson("data-doc-search-messages", {});
	var links = readJson("data-doc-search-links", null);
	var query = panel.getAttribute("data-doc-search-query");
	var baseUrl = panel.getAttribute("data-doc-search-base-url") || "/";

	if (query === null) {
		query = new URLSearchParams(window.location.search).get("q") || "";
	}
	query = query.trim();

	// Show the query in any empty search box on the page.
	document.querySelectorAll("input[type=search][name=q]").forEach(function (input) {
		if (!input.value) {
			input.value = query;
		}
	});

	var status = document.createElement("p");
	status.className = "doc-search-status";
	status.setAttribute("role", "status");
	panel.appendChild(status);

	function showStatus(text) {
		status.textContent = text || "";
		status.hidden = !text;
	}

	// Pagefind excerpts are HTML with <mark> around matches. Rebuild them
	// from an inert template keeping only text and <mark>, so nothing else
	// from the index reaches the page as markup.
	function excerptNode(html) {
		var template = document.createElement("template");
		var fragment = document.createDocumentFragment();

		template.innerHTML = html || "";
		template.content.childNodes.forEach(function (node) {
			if (node.nodeType === Node.ELEMENT_NODE && node.nodeName === "MARK") {
				var mark = document.createElement("mark");
				mark.textContent = node.textContent;
				fragment.appendChild(mark);
			} else {
				fragment.appendChild(document.createTextNode(node.textContent));
			}
		});

		return fragment;
	}

	function resultUrl(url) {
		if (links && links.prefix && url.indexOf(links.prefix) === 0) {
			return links.template.replace(links.placeholder, url.slice(links.prefix.length).replace(/\/$/, ""));
		}
		return url;
	}

	function render(results) {
		if (!results.length) {
			showStatus(messages.noResults);
			return;
		}

		var list = document.createElement("ol");
		list.className = "doc-search-results";

		results.forEach(function (result) {
			var item = document.createElement("li");
			var heading = document.createElement("h3");
			var link = document.createElement("a");
			var excerpt = document.createElement("p");

			link.href = resultUrl(result.url);
			link.textContent = (result.meta && result.meta.title) || result.url;
			heading.appendChild(link);
			excerpt.appendChild(excerptNode(result.excerpt));
			item.appendChild(heading);
			item.appendChild(excerpt);
			list.appendChild(item);
		});

		showStatus("");
		panel.appendChild(list);
	}

	if (query.length < 2 || query.length > 100) {
		showStatus(messages.length);
		return;
	}

	if (!bundles.length) {
		showStatus(messages.unavailable);
		return;
	}

	showStatus(messages.loading);

	import(bundles[0] + "pagefind.js")
		.then(function (pagefind) {
			// Indexed paths are relative to the site root (/<lang>/...), not to
			// each bundle's own directory, which Pagefind would otherwise assume.
			return pagefind.options({ basePath: bundles[0], baseUrl: baseUrl })
				.then(function () {
					return bundles.slice(1).reduce(function (merged, bundle) {
						return merged.then(function () {
							return pagefind.mergeIndex(bundle, { baseUrl: baseUrl });
						});
					}, Promise.resolve());
				})
				.then(function () {
					return pagefind.search(query);
				});
		})
		.then(function (search) {
			return Promise.all(search.results.slice(0, MAX_RESULTS).map(function (result) {
				return result.data();
			}));
		})
		.then(render)
		.catch(function () {
			showStatus(messages.unavailable);
		});
}());
