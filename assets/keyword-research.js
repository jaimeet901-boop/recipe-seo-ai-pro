/**
 * AI Keyword Research admin UI (Phase 3.5).
 */
(function ($) {
	"use strict";

	if (typeof RSAIP_KEYWORD_RESEARCH === "undefined") {
		return;
	}

	var cfg = RSAIP_KEYWORD_RESEARCH;
	var actions = cfg.actions || {};
	var categories = {};
	var lastRows = [];

	function setStatus(msg, isErr) {
		var $el = $("#rsaip-kr-status");
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

	function projectId() {
		return parseInt($("#rsaip-kr-project").val(), 10) || 0;
	}

	function updateWorkspaceLink() {
		var base = cfg.workspaceUrl || "admin.php?page=rsaip-keyword-workspace";
		var url = base + (base.indexOf("?") >= 0 ? "&" : "?") + "project_id=" + encodeURIComponent(String(projectId()));
		$("#rsaip-kr-open-workspace").attr("href", url);
	}

	function selectedRows() {
		var out = [];
		$("#rsaip-kr-body input.rsaip-kr-check:checked").each(function () {
			var id = $(this).val();
			for (var i = 0; i < lastRows.length; i++) {
				if (String(lastRows[i].id) === String(id)) {
					out.push(lastRows[i]);
					break;
				}
			}
		});
		return out;
	}

	function renderPreview(payload) {
		var research = (payload && payload.research) || {};
		categories = (payload && payload.categories) || {};
		lastRows = research.keywords || [];

		$("#rsaip-kr-preview").prop("hidden", false);
		var entities = research.related_entities || [];
		$("#rsaip-kr-entities").html(
			entities.length
				? "<strong>Related entities:</strong> " + esc(entities.join(", "))
				: ""
		);

		var $body = $("#rsaip-kr-body").empty();
		if (!lastRows.length) {
			$body.append("<tr><td colspan='7'>No keywords returned.</td></tr>");
			return;
		}

		lastRows.forEach(function (row) {
			var catLabel = categories[row.category] || row.category || "";
			var tr =
				"<tr>" +
				'<td><input type="checkbox" class="rsaip-kr-check" value="' +
				esc(row.id) +
				'" checked /></td>' +
				"<td><strong>" +
				esc(row.keyword) +
				"</strong></td>" +
				"<td>" +
				esc(catLabel) +
				"</td>" +
				"<td>" +
				esc(row.intent) +
				"</td>" +
				"<td>" +
				esc(row.difficulty) +
				"</td>" +
				"<td>" +
				esc(row.priority) +
				"</td>" +
				"<td>" +
				esc(row.suggested_cluster) +
				"</td>" +
				"</tr>";
			$body.append(tr);
		});

		$("#rsaip-kr-check-all").prop("checked", true);
		setStatus(lastRows.length + " keywords ready — review and save.");
		updateWorkspaceLink();
	}

	$(function () {
		if (cfg.projectId) {
			$("#rsaip-kr-project").val(String(cfg.projectId));
		}
		updateWorkspaceLink();
		$("#rsaip-kr-project").on("change", updateWorkspaceLink);

		$("#rsaip-kr-generate").on("click", function () {
			var seed = ($("#rsaip-kr-seed").val() || "").trim();
			if (!seed) {
				setStatus("Seed topic is required", true);
				return;
			}
			setStatus(cfg.i18n.generating || "Generating…");
			$("#rsaip-kr-generate").prop("disabled", true);
			post(actions.generate || "rsaip_keyword_research_generate", {
				seed: seed,
				language: $("#rsaip-kr-language").val() || "",
				country: $("#rsaip-kr-country").val() || "",
				audience: $("#rsaip-kr-audience").val() || "",
				niche: $("#rsaip-kr-niche").val() || "",
				notes: $("#rsaip-kr-notes").val() || ""
			})
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					renderPreview(res.data);
				})
				.fail(function () {
					setStatus(cfg.i18n.error || "Request failed", true);
				})
				.always(function () {
					$("#rsaip-kr-generate").prop("disabled", false);
				});
		});

		$("#rsaip-kr-check-all").on("change", function () {
			$("#rsaip-kr-body .rsaip-kr-check").prop("checked", this.checked);
		});
		$("#rsaip-kr-select-all").on("click", function () {
			$("#rsaip-kr-body .rsaip-kr-check").prop("checked", true);
			$("#rsaip-kr-check-all").prop("checked", true);
		});
		$("#rsaip-kr-select-none").on("click", function () {
			$("#rsaip-kr-body .rsaip-kr-check").prop("checked", false);
			$("#rsaip-kr-check-all").prop("checked", false);
		});

		$("#rsaip-kr-save").on("click", function () {
			if (!projectId()) {
				setStatus(cfg.i18n.needProject || "Select a project first.", true);
				return;
			}
			var rows = selectedRows();
			if (!rows.length) {
				setStatus(cfg.i18n.selectOne || "Select at least one keyword.", true);
				return;
			}
			setStatus(cfg.i18n.saving || "Saving…");
			$("#rsaip-kr-save").prop("disabled", true);
			post(actions.save || "rsaip_keyword_research_save", {
				project_id: projectId(),
				language: $("#rsaip-kr-language").val() || "",
				country: $("#rsaip-kr-country").val() || "",
				keywords: JSON.stringify(rows)
			})
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					var saved = (res.data && res.data.saved) || 0;
					var clusters = (res.data && res.data.clusters_created) || 0;
					setStatus(
						(cfg.i18n.saved || "Saved.") +
							" (" +
							saved +
							" keywords, " +
							clusters +
							" new clusters)"
					);
					updateWorkspaceLink();
				})
				.fail(function () {
					setStatus(cfg.i18n.error || "Request failed", true);
				})
				.always(function () {
					$("#rsaip-kr-save").prop("disabled", false);
				});
		});
	});
})(jQuery);
