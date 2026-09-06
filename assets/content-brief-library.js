/**
 * AI Content Brief Library admin UI (Phase 3.2).
 */
(function ($) {
	"use strict";

	if (typeof RSAIP_BRIEF_LIBRARY === "undefined") {
		return;
	}

	var cfg = RSAIP_BRIEF_LIBRARY;
	var labels = cfg.labels || {};
	var page = 1;
	var perPage = 20;
	var total = 0;
	var current = null;

	function setStatus(text) {
		$("#rsaip-lib-status-text").text(text || "");
	}

	function ajax(action, data) {
		return $.ajax({
			url: cfg.ajaxUrl,
			method: "POST",
			dataType: "json",
			data: $.extend({ action: action, nonce: cfg.nonce }, data || {})
		});
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function renderList(items) {
		var $body = $("#rsaip-lib-body").empty();
		if (!items || !items.length) {
			$body.append(
				"<tr><td colspan='6'>" + escapeHtml("No briefs found.") + "</td></tr>"
			);
			return;
		}
		items.forEach(function (row) {
			var tr = $("<tr/>");
			tr.append("<td>" + escapeHtml(row.title || "—") + "</td>");
			tr.append("<td>" + escapeHtml(row.primary_keyword || "—") + "</td>");
			tr.append(
				"<td><span class='rsaip-badge rsaip-badge-" +
					escapeHtml(row.status || "draft") +
					"'>" +
					escapeHtml(row.status || "") +
					"</span></td>"
			);
			tr.append("<td>" + escapeHtml(String(row.word_count || 0)) + "</td>");
			tr.append("<td>" + escapeHtml(row.updated_at || "") + "</td>");

			var actions = $("<td class='rsaip-lib-actions'/>");
			actions.append(
				$("<button type='button' class='button button-small'/>")
					.text("Open")
					.on("click", function () {
						openBrief(row.id);
					})
			);
			actions.append(" ");
			actions.append(
				$("<button type='button' class='button button-small'/>")
					.text("Duplicate")
					.on("click", function () {
						ajax(cfg.actions.duplicate, { id: row.id }).done(function (resp) {
							if (resp && resp.success) {
								setStatus("Duplicated.");
								loadList();
							}
						});
					})
			);
			actions.append(" ");
			if (row.status === "archived") {
				actions.append(
					$("<button type='button' class='button button-small'/>")
						.text("Restore")
						.on("click", function () {
							ajax(cfg.actions.restore, { id: row.id }).done(function () {
								setStatus(cfg.i18n.restored);
								loadList();
							});
						})
				);
			} else {
				actions.append(
					$("<button type='button' class='button button-small'/>")
						.text("Archive")
						.on("click", function () {
							ajax(cfg.actions.archive, { id: row.id }).done(function () {
								setStatus(cfg.i18n.archived);
								loadList();
							});
						})
				);
			}
			actions.append(" ");
			actions.append(
				$("<button type='button' class='button button-small'/>")
					.text("Delete")
					.on("click", function () {
						if (!window.confirm(cfg.i18n.confirmDel)) {
							return;
						}
						ajax(cfg.actions.delete, { id: row.id }).done(function () {
							setStatus(cfg.i18n.deleted);
							$("#rsaip-lib-detail-panel").prop("hidden", true);
							loadList();
						});
					})
			);
			tr.append(actions);
			$body.append(tr);
		});
	}

	function renderDetail(brief) {
		current = brief;
		$("#rsaip-lib-edit-title").val(brief.title || "");
		$("#rsaip-lib-edit-status").val(brief.status || "draft");
		$("#rsaip-lib-detail-title").text(brief.title || "Brief detail");

		var order = [
			"search_intent",
			"primary_keyword",
			"secondary_keywords",
			"long_tail_keywords",
			"semantic_keywords",
			"entities",
			"faq_ideas",
			"h1",
			"h2_structure",
			"h3_suggestions",
			"meta_description",
			"suggested_slug",
			"internal_linking_opportunities",
			"external_authority_suggestions",
			"schema_recommendation",
			"eeat_recommendations",
			"recommended_word_count"
		];
		var html = "";
		if (brief.topic) {
			html +=
				'<p class="rsaip-note"><strong>Topic:</strong> ' +
				escapeHtml(brief.topic) +
				"</p>";
		}
		order.forEach(function (key) {
			if (typeof brief[key] === "undefined") {
				return;
			}
			var label = labels[key] || key;
			var val = brief[key];
			html += "<div class='rsaip-brief-section'><h3>" + escapeHtml(label) + "</h3>";
			if (Array.isArray(val)) {
				html += "<ul>";
				val.forEach(function (item) {
					html += "<li>" + escapeHtml(item) + "</li>";
				});
				html += "</ul>";
			} else {
				html += "<p>" + escapeHtml(String(val)) + "</p>";
			}
			html += "</div>";
		});
		$("#rsaip-lib-detail").html(html);
		$("#rsaip-lib-detail-panel").prop("hidden", false);
	}

	function openBrief(id) {
		setStatus("Loading brief…");
		ajax(cfg.actions.get, { id: id })
			.done(function (resp) {
				if (resp && resp.success && resp.data) {
					renderDetail(resp.data);
					setStatus("");
				} else {
					setStatus(cfg.i18n.error);
				}
			})
			.fail(function () {
				setStatus(cfg.i18n.error);
			});
	}

	function loadList() {
		setStatus("Loading…");
		ajax(cfg.actions.list, {
			q: $("#rsaip-lib-q").val() || "",
			status: $("#rsaip-lib-status").val() || "all",
			sort: $("#rsaip-lib-sort").val() || "updated_at",
			order: $("#rsaip-lib-order").val() || "DESC",
			page: page,
			per_page: perPage
		})
			.done(function (resp) {
				if (!(resp && resp.success && resp.data)) {
					setStatus(cfg.i18n.error);
					return;
				}
				total = resp.data.total || 0;
				renderList(resp.data.items || []);
				var pages = Math.max(1, Math.ceil(total / perPage));
				$("#rsaip-lib-page-label").text("Page " + page + " / " + pages + " (" + total + ")");
				$("#rsaip-lib-prev").prop("disabled", page <= 1);
				$("#rsaip-lib-next").prop("disabled", page >= pages);
				setStatus("");
			})
			.fail(function () {
				setStatus(cfg.i18n.error);
			});
	}

	function downloadExport(format) {
		if (!current || !current.id) {
			return;
		}
		ajax(cfg.actions.export, { id: current.id, format: format }).done(function (resp) {
			if (!(resp && resp.success && resp.data)) {
				setStatus(cfg.i18n.error);
				return;
			}
			var data = resp.data;
			var content = data.content || "";
			var mime = data.mime || "text/plain";
			var filename = data.filename || "brief.txt";
			var blob;
			if (data.encoding === "base64") {
				var binary = atob(content);
				var bytes = new Uint8Array(binary.length);
				for (var i = 0; i < binary.length; i++) {
					bytes[i] = binary.charCodeAt(i);
				}
				blob = new Blob([bytes], { type: mime });
			} else {
				blob = new Blob([content], { type: mime });
			}
			var url = URL.createObjectURL(blob);
			var a = document.createElement("a");
			a.href = url;
			a.download = filename;
			document.body.appendChild(a);
			a.click();
			a.remove();
			URL.revokeObjectURL(url);
			setStatus("Exported.");
		});
	}

	function copyCurrent() {
		if (!current || !current.id) {
			return;
		}
		ajax(cfg.actions.export, { id: current.id, format: "text" }).done(function (resp) {
			if (!(resp && resp.success && resp.data)) {
				return;
			}
			var text = resp.data.content || "";
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					setStatus(cfg.i18n.copied);
				});
				return;
			}
			var $ta = $("<textarea>").val(text).appendTo("body").select();
			document.execCommand("copy");
			$ta.remove();
			setStatus(cfg.i18n.copied);
		});
	}

	$(function () {
		$("#rsaip-lib-refresh").on("click", function () {
			page = 1;
			loadList();
		});
		$("#rsaip-lib-q").on("keydown", function (e) {
			if (e.key === "Enter") {
				page = 1;
				loadList();
			}
		});
		$("#rsaip-lib-status, #rsaip-lib-sort, #rsaip-lib-order").on("change", function () {
			page = 1;
			loadList();
		});
		$("#rsaip-lib-prev").on("click", function () {
			if (page > 1) {
				page--;
				loadList();
			}
		});
		$("#rsaip-lib-next").on("click", function () {
			page++;
			loadList();
		});
		$("#rsaip-lib-save-update").on("click", function () {
			if (!current) {
				return;
			}
			ajax(cfg.actions.update, {
				id: current.id,
				title: $("#rsaip-lib-edit-title").val() || "",
				status: $("#rsaip-lib-edit-status").val() || "draft",
				brief: JSON.stringify(current)
			}).done(function (resp) {
				if (resp && resp.success) {
					current = resp.data;
					setStatus(cfg.i18n.updated);
					loadList();
				}
			});
		});
		$("#rsaip-lib-mark-complete").on("click", function () {
			if (!current) {
				return;
			}
			ajax(cfg.actions.complete, { id: current.id }).done(function (resp) {
				if (resp && resp.success) {
					current = resp.data;
					$("#rsaip-lib-edit-status").val("completed");
					setStatus("Marked completed.");
					loadList();
				}
			});
		});
		$("#rsaip-lib-export-md").on("click", function () {
			downloadExport("markdown");
		});
		$("#rsaip-lib-export-json").on("click", function () {
			downloadExport("json");
		});
		$("#rsaip-lib-export-pdf").on("click", function () {
			downloadExport("pdf");
		});
		$("#rsaip-lib-copy").on("click", copyCurrent);

		loadList();
	});
})(jQuery);
