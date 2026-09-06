/**
 * AI Content Brief admin UI (isolated from assets/admin.js).
 */
(function ($) {
	"use strict";

	if (typeof RSAIP_CONTENT_BRIEF === "undefined") {
		return;
	}

	var cfg = RSAIP_CONTENT_BRIEF;
	var labels = cfg.labels || {};
	var lastBrief = null;

	function setStatus(text) {
		$("#rsaip-brief-status").text(text || "");
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function renderList(items) {
		if (!items || !items.length) {
			return '<p class="rsaip-sub">—</p>';
		}
		var html = "<ul>";
		items.forEach(function (item) {
			html += "<li>" + escapeHtml(item) + "</li>";
		});
		html += "</ul>";
		return html;
	}

	function renderField(key, value) {
		var label = labels[key] || key;
		var body;
		if (Array.isArray(value)) {
			body = renderList(value);
		} else if (typeof value === "number") {
			body = "<p><strong>" + escapeHtml(String(value)) + "</strong></p>";
		} else {
			body = "<p>" + escapeHtml(value || "—") + "</p>";
		}
		return (
			'<div class="rsaip-brief-section" data-key="' +
			escapeHtml(key) +
			'"><h3>' +
			escapeHtml(label) +
			"</h3>" +
			body +
			"</div>"
		);
	}

	function renderBrief(brief) {
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
			html += renderField(key, brief[key]);
		});
		$("#rsaip-brief-empty").hide();
		$("#rsaip-brief-result").html(html).prop("hidden", false);
		$("#rsaip-brief-copy").prop("disabled", false);
	}

	function briefToText(brief) {
		var lines = [];
		if (brief.topic) {
			lines.push("Topic: " + brief.topic);
			lines.push("");
		}
		Object.keys(labels).forEach(function (key) {
			if (typeof brief[key] === "undefined") {
				return;
			}
			lines.push(labels[key] + ":");
			var val = brief[key];
			if (Array.isArray(val)) {
				val.forEach(function (item) {
					lines.push("  - " + item);
				});
			} else {
				lines.push("  " + String(val));
			}
			lines.push("");
		});
		return lines.join("\n");
	}

	$(function () {
		$("#rsaip-brief-generate").on("click", function () {
			var topic = ($("#rsaip-brief-topic").val() || "").trim();
			if (!topic) {
				setStatus("Topic is required.");
				return;
			}

			var $btn = $(this);
			$btn.prop("disabled", true);
			setStatus(cfg.i18n.generating || "Generating…");

			$.ajax({
				url: cfg.ajaxUrl,
				method: "POST",
				dataType: "json",
				data: {
					action: cfg.action,
					nonce: cfg.nonce,
					topic: topic,
					audience: $("#rsaip-brief-audience").val() || "",
					locale: $("#rsaip-brief-locale").val() || "",
					notes: $("#rsaip-brief-notes").val() || ""
				}
			})
				.done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.brief) {
						lastBrief = resp.data.brief;
						if (resp.data.labels) {
							labels = resp.data.labels;
						}
						renderBrief(lastBrief);
						$("#rsaip-brief-save-library").prop("disabled", false);
						setStatus("Done.");
					} else {
						var msg =
							(resp && resp.data && resp.data.message) ||
							cfg.i18n.error ||
							"Error";
						setStatus(msg);
					}
				})
				.fail(function (xhr) {
					var msg = cfg.i18n.error || "Error";
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					setStatus(msg);
				})
				.always(function () {
					$btn.prop("disabled", false);
				});
		});

		$("#rsaip-brief-copy").on("click", function () {
			if (!lastBrief) {
				return;
			}
			var text = briefToText(lastBrief);
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					setStatus(cfg.i18n.copied || "Copied.");
				});
				return;
			}
			var $ta = $("<textarea>").val(text).appendTo("body").select();
			try {
				document.execCommand("copy");
				setStatus(cfg.i18n.copied || "Copied.");
			} catch (e) {
				setStatus("Copy failed.");
			}
			$ta.remove();
		});

		// Phase 3.2: Save to library (does not alter generation).
		$("#rsaip-brief-save-library").on("click", function () {
			if (!lastBrief) {
				return;
			}
			if (typeof RSAIP_BRIEF_LIBRARY === "undefined") {
				setStatus("Library unavailable.");
				return;
			}
			var lib = RSAIP_BRIEF_LIBRARY;
			var $btn = $(this);
			$btn.prop("disabled", true);
			setStatus("Saving…");
			$.ajax({
				url: lib.ajaxUrl,
				method: "POST",
				dataType: "json",
				data: {
					action: lib.actions.save,
					nonce: lib.nonce,
					title: lastBrief.h1 || lastBrief.primary_keyword || lastBrief.topic || "",
					status: "draft",
					brief: JSON.stringify(lastBrief)
				}
			})
				.done(function (resp) {
					if (resp && resp.success) {
						setStatus((lib.i18n && lib.i18n.saved) || "Saved.");
					} else {
						setStatus(
							(resp && resp.data && resp.data.message) ||
								(lib.i18n && lib.i18n.error) ||
								"Save failed."
						);
					}
				})
				.fail(function (xhr) {
					var msg = (lib.i18n && lib.i18n.error) || "Save failed.";
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					setStatus(msg);
				})
				.always(function () {
					$btn.prop("disabled", false);
				});
		});
	});
})(jQuery);
