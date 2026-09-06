/**
 * SERP Intelligence admin UI (Phase 3.6).
 */
(function ($) {
	"use strict";

	if (typeof RSAIP_SERP_INTELLIGENCE === "undefined") {
		return;
	}

	var cfg = RSAIP_SERP_INTELLIGENCE;
	var actions = cfg.actions || {};
	var labels = cfg.labels || {};
	var current = null;
	var savedId = 0;

	function setStatus(msg, isErr) {
		var $el = $("#rsaip-serp-status");
		$el.text(msg || "");
		$el.toggleClass("rsaip-error", !!isErr);
	}

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function esc(s) {
		return $("<div>")
			.text(s == null ? "" : String(s))
			.html();
	}

	function renderListItem(key, value) {
		var label = labels[key] || key;
		var html = "<div class='rsaip-brief-section'><h3>" + esc(label) + "</h3>";
		if (Array.isArray(value)) {
			html += "<ul>";
			value.forEach(function (item) {
				html += "<li>" + esc(item) + "</li>";
			});
			html += "</ul>";
		} else {
			html += "<p>" + esc(value) + "</p>";
		}
		html += "</div>";
		return html;
	}

	function renderAnalysis(analysis) {
		current = analysis || null;
		savedId = analysis && analysis.id ? parseInt(analysis.id, 10) || 0 : 0;
		$("#rsaip-serp-result-panel").prop("hidden", false);

		var order = [
			"search_intent",
			"expected_serp_features",
			"recommended_article_type",
			"recommended_heading_structure",
			"missing_topics",
			"related_entities",
			"recommended_word_count",
			"recommended_media",
			"suggested_faq",
			"eeat_recommendations",
			"common_mistakes",
			"opportunities"
		];

		var html = "";
		if (analysis.query) {
			html += "<p><strong>" + esc(analysis.query) + "</strong></p>";
		}
		order.forEach(function (key) {
			if (analysis[key] === undefined || analysis[key] === null || analysis[key] === "") {
				return;
			}
			if (Array.isArray(analysis[key]) && !analysis[key].length) {
				return;
			}
			html += renderListItem(key, analysis[key]);
		});
		$("#rsaip-serp-result").html(html || "<p class='rsaip-note'>Empty analysis.</p>");

		if (analysis.project_id) {
			$("#rsaip-serp-project").val(String(analysis.project_id));
		}
		$("#rsaip-serp-keyword-id").val(analysis.keyword_id || 0);
		$("#rsaip-serp-brief-id").val(analysis.brief_id || 0);
	}

	function loadList() {
		return post(actions.list || "rsaip_serp_list", {
			q: $("#rsaip-serp-q").val() || "",
			project_id: parseInt($("#rsaip-serp-project").val(), 10) || 0
		})
			.done(function (res) {
				var $body = $("#rsaip-serp-list-body").empty();
				if (!res || !res.success) {
					$body.append("<tr><td colspan='7'>" + esc(cfg.i18n.error) + "</td></tr>");
					return;
				}
				var items = (res.data && res.data.items) || [];
				if (!items.length) {
					$body.append("<tr><td colspan='7'>No saved analyses yet.</td></tr>");
					return;
				}
				items.forEach(function (row) {
					var tr =
						"<tr data-id='" +
						esc(row.id) +
						"'>" +
						"<td>" +
						esc(row.query_text) +
						"</td>" +
						"<td>" +
						esc(row.recommended_article_type) +
						"</td>" +
						"<td>" +
						esc(row.project_id || "—") +
						"</td>" +
						"<td>" +
						esc(row.keyword_id || "—") +
						"</td>" +
						"<td>" +
						esc(row.brief_id || "—") +
						"</td>" +
						"<td>" +
						esc(row.updated_at) +
						"</td>" +
						"<td><button type='button' class='button button-small rsaip-serp-open'>Open</button></td>" +
						"</tr>";
					$body.append(tr);
				});
			})
			.fail(function () {
				$("#rsaip-serp-list-body").html(
					"<tr><td colspan='7'>" + esc(cfg.i18n.error) + "</td></tr>"
				);
			});
	}

	function attach(action, extra) {
		if (!savedId) {
			setStatus("Save the analysis first", true);
			return;
		}
		var data = $.extend({ id: savedId }, extra || {});
		setStatus(cfg.i18n.saving || "Saving…");
		post(action, data)
			.done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				renderAnalysis((res.data && res.data.analysis) || {});
				setStatus(cfg.i18n.saved || "Saved.");
				loadList();
			})
			.fail(function () {
				setStatus(cfg.i18n.error, true);
			});
	}

	$(function () {
		if (cfg.projectId) {
			$("#rsaip-serp-project").val(String(cfg.projectId));
		}
		loadList();

		$("#rsaip-serp-analyze").on("click", function () {
			var query = ($("#rsaip-serp-query").val() || "").trim();
			if (!query) {
				setStatus(cfg.i18n.needQuery || "Enter a query first.", true);
				return;
			}
			setStatus(cfg.i18n.analyzing || "Analyzing…");
			$("#rsaip-serp-analyze").prop("disabled", true);
			post(actions.analyze || "rsaip_serp_analyze", {
				query: query,
				language: $("#rsaip-serp-language").val() || "",
				country: $("#rsaip-serp-country").val() || "",
				audience: $("#rsaip-serp-audience").val() || "",
				notes: $("#rsaip-serp-notes").val() || ""
			})
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					renderAnalysis((res.data && res.data.analysis) || {});
					setStatus("Analysis ready — save to keep it.");
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				})
				.always(function () {
					$("#rsaip-serp-analyze").prop("disabled", false);
				});
		});

		$("#rsaip-serp-save").on("click", function () {
			if (!current) {
				setStatus("Analyze a query first", true);
				return;
			}
			setStatus(cfg.i18n.saving || "Saving…");
			post(actions.save || "rsaip_serp_save", {
				id: savedId || 0,
				analysis: JSON.stringify(current),
				project_id: parseInt($("#rsaip-serp-project").val(), 10) || 0,
				keyword_id: parseInt($("#rsaip-serp-keyword-id").val(), 10) || 0,
				brief_id: parseInt($("#rsaip-serp-brief-id").val(), 10) || 0,
				query: current.query || "",
				language: $("#rsaip-serp-language").val() || "",
				country: $("#rsaip-serp-country").val() || ""
			})
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					renderAnalysis((res.data && res.data.analysis) || {});
					setStatus(cfg.i18n.saved || "Saved.");
					loadList();
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				});
		});

		$("#rsaip-serp-attach-project").on("click", function () {
			attach(actions.attachProject || "rsaip_serp_attach_project", {
				project_id: parseInt($("#rsaip-serp-project").val(), 10) || 0
			});
		});
		$("#rsaip-serp-attach-keyword").on("click", function () {
			attach(actions.attachKeyword || "rsaip_serp_attach_keyword", {
				keyword_id: parseInt($("#rsaip-serp-keyword-id").val(), 10) || 0
			});
		});
		$("#rsaip-serp-attach-brief").on("click", function () {
			attach(actions.attachBrief || "rsaip_serp_attach_brief", {
				brief_id: parseInt($("#rsaip-serp-brief-id").val(), 10) || 0
			});
		});

		$("#rsaip-serp-refresh").on("click", loadList);
		$("#rsaip-serp-q").on("keydown", function (e) {
			if (e.key === "Enter") {
				e.preventDefault();
				loadList();
			}
		});

		$("#rsaip-serp-list-body").on("click", ".rsaip-serp-open", function () {
			var id = parseInt($(this).closest("tr").data("id"), 10);
			if (!id) {
				return;
			}
			setStatus("Loading…");
			post(actions.get || "rsaip_serp_get", { id: id })
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					var analysis = (res.data && res.data.analysis) || {};
					if (analysis.query) {
						$("#rsaip-serp-query").val(analysis.query);
					}
					renderAnalysis(analysis);
					setStatus("");
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				});
		});
	});
})(jQuery);
