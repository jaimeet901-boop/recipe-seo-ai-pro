/**
 * AI Content Optimizer admin UI (Phase 4.1).
 */
(function ($) {
	"use strict";

	if (typeof RSAIP_CONTENT_OPTIMIZER === "undefined") {
		return;
	}

	var cfg = RSAIP_CONTENT_OPTIMIZER;
	var actions = cfg.actions || {};
	var currentId = 0;
	var current = null;

	function setStatus(msg, isErr) {
		var $el = $("#rsaip-opt-status");
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

	function payloadBase() {
		return {
			post_id: parseInt($("#rsaip-opt-post-id").val(), 10) || 0,
			title: $("#rsaip-opt-title").val() || "",
			meta: $("#rsaip-opt-meta").val() || "",
			content: $("#rsaip-opt-content").val() || "",
			keywords: $("#rsaip-opt-keywords").val() || "",
			notes: $("#rsaip-opt-notes").val() || "",
			selected_text: $("#rsaip-opt-selected").val() || "",
			workflow: $("#rsaip-opt-workflow").val() || "human_rewrite",
			scope: $("#rsaip-opt-scope").val() || "full_article"
		};
	}

	function renderScores(opt) {
		var a = (opt && opt.analysis) || {};
		$("#rsaip-opt-scores").prop("hidden", false);
		$("#rsaip-opt-score-cards").html(
			card("SEO", opt.seo_score || a.seo_score) +
				card("Readability", opt.readability_score || a.readability_score) +
				card("EEAT", opt.eeat_score || a.eeat_score) +
				card("Recipe", opt.recipe_score || a.recipe_quality_score)
		);
		var issues = a.issues || [];
		var $ul = $("#rsaip-opt-issues").empty();
		if (!issues.length) {
			$ul.append("<li>No major issues detected.</li>");
		} else {
			issues.forEach(function (i) {
				$ul.append("<li>" + esc(i) + "</li>");
			});
		}
	}

	function card(label, value) {
		return (
			'<div class="rsaip-card rsaip-opt-score"><div class="rsaip-card-label">' +
			esc(label) +
			'</div><div class="rsaip-card-value">' +
			esc(String(value == null ? 0 : value)) +
			"</div></div>"
		);
	}

	function renderCompare(opt) {
		current = opt;
		currentId = opt.id || 0;
		$("#rsaip-opt-compare").prop("hidden", false);
		$("#rsaip-opt-pane-original").text(
			(opt.original_title ? "Title: " + opt.original_title + "\n\n" : "") +
				(opt.original_meta ? "Meta: " + opt.original_meta + "\n\n" : "") +
				(opt.original_content || "")
		);
		$("#rsaip-opt-pane-optimized").text(
			(opt.optimized_title ? "Title: " + opt.optimized_title + "\n\n" : "") +
				(opt.optimized_meta ? "Meta: " + opt.optimized_meta + "\n\n" : "") +
				(opt.optimized_content || "")
		);
		renderDiff(opt.diff || []);
		showTab("optimized");
	}

	function renderDiff(diff) {
		var html = "";
		if (!diff.length) {
			html = "<span class='rsaip-sub'>No diff available.</span>";
		} else {
			diff.forEach(function (token) {
				var cls = "rsaip-diff-same";
				if (token.type === "added") {
					cls = "rsaip-diff-added";
				} else if (token.type === "removed") {
					cls = "rsaip-diff-removed";
				}
				html += '<span class="' + cls + '">' + esc(token.text) + "</span>";
			});
		}
		$("#rsaip-opt-pane-diff").html(html);
	}

	function showTab(tab) {
		$(".rsaip-opt-tab").removeClass("button-primary");
		$('.rsaip-opt-tab[data-tab="' + tab + '"]').addClass("button-primary");
		$("#rsaip-opt-pane-original, #rsaip-opt-pane-optimized, #rsaip-opt-pane-diff").prop("hidden", true);
		$("#rsaip-opt-pane-" + tab).prop("hidden", false);
	}

	function renderHistory(data) {
		var runs = (data && data.runs) || [];
		var versions = (data && data.versions) || [];
		var html = "<h3>Runs</h3><ul>";
		if (!runs.length) {
			html += "<li>No runs yet.</li>";
		}
		runs.forEach(function (r) {
			html +=
				"<li><button type='button' class='button-link rsaip-opt-open-run' data-id='" +
				esc(r.id) +
				"'>#" +
				esc(r.id) +
				"</button> " +
				esc(r.workflow) +
				" / " +
				esc(r.scope) +
				" · " +
				esc(r.status) +
				" · SEO " +
				esc(r.seo_score) +
				"</li>";
		});
		html += "</ul><h3>Versions</h3><ul>";
		if (!versions.length) {
			html += "<li>No versions yet.</li>";
		}
		versions.forEach(function (v) {
			html +=
				"<li>#" +
				esc(v.id) +
				" <strong>" +
				esc(v.label) +
				"</strong> · run #" +
				esc(v.optimization_id) +
				" · " +
				esc(v.created_at) +
				" <button type='button' class='button button-small rsaip-opt-restore' data-id='" +
				esc(v.id) +
				"'>Restore</button></li>";
		});
		html += "</ul>";
		$("#rsaip-opt-history-body").html(html);
	}

	function loadPost(id) {
		if (!id) {
			return $.Deferred().reject().promise();
		}
		setStatus("Loading post…");
		return post(actions.loadPost || "rsaip_optimizer_load_post", { post_id: id })
			.done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				$("#rsaip-opt-title").val(res.data.title || "");
				$("#rsaip-opt-meta").val(res.data.meta || "");
				$("#rsaip-opt-content").val(res.data.content || "");
				$("#rsaip-opt-post-label").text(res.data.title || "");
				setStatus("Post loaded");
				refreshHistory();
			})
			.fail(function () {
				setStatus(cfg.i18n.error, true);
			});
	}

	function refreshHistory() {
		var id = parseInt($("#rsaip-opt-post-id").val(), 10) || 0;
		if (!id) {
			return;
		}
		post(actions.history || "rsaip_optimizer_history", { post_id: id }).done(function (res) {
			if (res && res.success) {
				renderHistory(res.data);
			}
		});
	}

	$(function () {
		if (cfg.postId) {
			$("#rsaip-opt-post-id").val(String(cfg.postId));
		}
		if (cfg.workflow) {
			$("#rsaip-opt-workflow").val(cfg.workflow);
		}

		$("#rsaip-opt-load-post").on("click", function () {
			loadPost(parseInt($("#rsaip-opt-post-id").val(), 10) || 0);
		});

		$(".rsaip-opt-tab").on("click", function () {
			showTab($(this).data("tab"));
		});

		$("#rsaip-opt-analyze").on("click", function () {
			setStatus(cfg.i18n.analyzing || "Analyzing…");
			var data = payloadBase();
			data.enrich = 1;
			post(actions.analyze || "rsaip_optimizer_analyze", data)
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					var opt = (res.data && res.data.optimization) || {};
					currentId = opt.id || 0;
					renderScores(opt);
					setStatus("Analysis complete");
					refreshHistory();
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				});
		});

		$("#rsaip-opt-run").on("click", function () {
			setStatus(cfg.i18n.optimizing || "Optimizing…");
			post(actions.optimize || "rsaip_optimizer_optimize", payloadBase())
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					var opt = (res.data && res.data.optimization) || {};
					renderScores(opt);
					renderCompare(opt);
					setStatus("Workflow complete — review Diff View before apply");
					refreshHistory();
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				});
		});

		$("#rsaip-opt-apply").on("click", function () {
			if (!currentId) {
				setStatus("Run a workflow first", true);
				return;
			}
			setStatus(cfg.i18n.applying || "Applying…");
			post(actions.apply || "rsaip_optimizer_apply", { id: currentId })
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					setStatus("Applied with backup");
					refreshHistory();
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				});
		});

		$("#rsaip-opt-undo").on("click", function () {
			if (!currentId) {
				setStatus("Nothing to undo", true);
				return;
			}
			post(actions.undo || "rsaip_optimizer_undo", { id: currentId }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				setStatus("Undo complete");
				loadPost(parseInt($("#rsaip-opt-post-id").val(), 10) || 0);
			});
		});

		$("#rsaip-opt-history").on("click", refreshHistory);

		$("#rsaip-opt-history-body").on("click", ".rsaip-opt-open-run", function () {
			var id = parseInt($(this).data("id"), 10);
			post(actions.get || "rsaip_optimizer_get", { id: id }).done(function (res) {
				if (!res || !res.success) {
					return;
				}
				var opt = (res.data && res.data.optimization) || {};
				renderScores(opt);
				if (opt.optimized_content || opt.optimized_meta) {
					renderCompare(opt);
				}
			});
		});

		$("#rsaip-opt-history-body").on("click", ".rsaip-opt-restore", function () {
			var id = parseInt($(this).data("id"), 10);
			post(actions.restore || "rsaip_optimizer_restore", { version_id: id }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				setStatus("Version restored");
				loadPost(parseInt($("#rsaip-opt-post-id").val(), 10) || 0);
			});
		});

		var boot = $.Deferred().resolve();
		if (cfg.postId) {
			boot = loadPost(cfg.postId);
		}
		$.when(boot).always(function () {
			if (cfg.autoload && cfg.postId) {
				if (cfg.workflow && cfg.workflow !== "analyze") {
					$("#rsaip-opt-run").trigger("click");
				} else {
					$("#rsaip-opt-analyze").trigger("click");
				}
			}
		});
	});
})(jQuery);
