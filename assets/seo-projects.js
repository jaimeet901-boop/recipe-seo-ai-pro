/**
 * AI SEO Projects admin UI (Phase 3.3).
 */
(function ($) {
	"use strict";

	if (typeof RSAIP_SEO_PROJECTS === "undefined") {
		return;
	}

	var cfg = RSAIP_SEO_PROJECTS;
	var current = null;
	var page = 1;

	function setStatus(text) {
		$("#rsaip-proj-status").text(text || "");
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
		var $body = $("#rsaip-proj-body").empty();
		if (!items || !items.length) {
			$body.append("<tr><td colspan='8'>No projects yet.</td></tr>");
			return;
		}
		items.forEach(function (row) {
			var tr = $("<tr/>");
			tr.append("<td>" + escapeHtml(row.name || "") + "</td>");
			tr.append("<td>" + escapeHtml(row.niche || "—") + "</td>");
			tr.append("<td>" + escapeHtml(row.status || "") + "</td>");
			tr.append("<td>" + escapeHtml(String(row.briefs_count || 0)) + "</td>");
			tr.append("<td>" + escapeHtml(String(row.keywords_count || 0)) + "</td>");
			tr.append("<td>" + escapeHtml(String(row.articles_count || 0)) + "</td>");
			tr.append("<td>" + escapeHtml(String(row.completion_pct || 0)) + "%</td>");
			var actions = $("<td/>");
			actions.append(
				$("<button type='button' class='button button-small'/>")
					.text("Open")
					.on("click", function () {
						openProject(row.id);
					})
			);
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
							$("#rsaip-proj-dashboard").prop("hidden", true);
							loadList();
						});
					})
			);
			tr.append(actions);
			$body.append(tr);
		});
	}

	function renderDashboard(project) {
		current = project;
		$("#rsaip-proj-dashboard").prop("hidden", false);
		$("#rsaip-proj-dash-title").text(project.name || "Project dashboard");
		$("#rsaip-proj-edit-name").val(project.name || "");
		$("#rsaip-proj-edit-status").val(project.status || "active");
		$("#rsaip-proj-edit-niche").val(project.niche || "");
		$("#rsaip-proj-edit-country").val(project.target_country || "");
		$("#rsaip-proj-edit-language").val(project.language || "");
		$("#rsaip-proj-edit-description").val(project.description || "");

		var kwBase = cfg.keywordWorkspaceUrl || "admin.php?page=rsaip-keyword-workspace";
		var kwUrl = kwBase + (kwBase.indexOf("?") >= 0 ? "&" : "?") + "project_id=" + encodeURIComponent(String(project.id || 0));
		$("#rsaip-proj-open-keywords").attr("href", kwUrl);

		var stats = project.statistics || {};
		var pct = parseInt(stats.completion_pct || 0, 10) || 0;
		if (pct < 0) pct = 0;
		if (pct > 100) pct = 100;
		var cards = [
			{ label: "Briefs", value: stats.briefs_count || 0, tone: "purple" },
			{ label: "Keywords", value: stats.keywords_count || 0, tone: "blue" },
			{ label: "Articles", value: stats.articles_count || 0, tone: "green" }
		];
		var $cards = $("#rsaip-proj-cards").empty();
		$cards.append(
			'<div class="rsaip-card rsaip-kpi-card rsaip-fade-in rsaip-kpi-tone-blue rsaip-proj-completion">' +
				'<div class="rsaip-k">Completion</div>' +
				'<div class="rsaip-progress-ring" style="--p:' +
				pct +
				'" data-label="' +
				escapeHtml(String(pct)) +
				'%"></div>' +
				"</div>"
		);
		cards.forEach(function (c) {
			$cards.append(
				'<div class="rsaip-card rsaip-kpi-card rsaip-fade-in rsaip-kpi-tone-' +
					c.tone +
					'"><div class="rsaip-card-label">' +
					escapeHtml(c.label) +
					'</div><div class="rsaip-card-value">' +
					escapeHtml(String(c.value)) +
					"</div></div>"
			);
		});

		var $tasks = $("#rsaip-proj-tasks").empty();
		(project.upcoming_tasks || []).forEach(function (t) {
			$tasks.append(
				"<li>" +
					escapeHtml(t.title || "") +
					(t.due_at ? " <span class='rsaip-sub'>(" + escapeHtml(t.due_at) + ")</span>" : "") +
					"</li>"
			);
		});
		if (!$tasks.children().length) {
			$tasks.append(
				"<li class='rsaip-empty-state'><span class='dashicons dashicons-clock' aria-hidden='true'></span><h3>No upcoming tasks</h3><p>Add a task to keep this project moving.</p></li>"
			);
		}

		var $act = $("#rsaip-proj-activity").empty();
		(project.recent_activity || []).forEach(function (a) {
			$act.append(
				"<li><strong>" +
					escapeHtml(a.action || "") +
					"</strong> — " +
					escapeHtml(a.message || "") +
					" <span class='rsaip-sub'>" +
					escapeHtml(a.created_at || "") +
					"</span></li>"
			);
		});
		if (!$act.children().length) {
			$act.append(
				"<li class='rsaip-empty-state'><span class='dashicons dashicons-backup' aria-hidden='true'></span><h3>No activity yet</h3><p>Project actions will appear in this timeline.</p></li>"
			);
		}

		var $members = $("#rsaip-proj-members").empty();
		(project.members || []).forEach(function (m) {
			$members.append(
				"<li>#" +
					escapeHtml(String(m.user_id)) +
					" " +
					escapeHtml(m.display_name || "") +
					" <span class='rsaip-badge'>" +
					escapeHtml(m.role || "") +
					"</span></li>"
			);
		});
	}

	function openProject(id) {
		setStatus("Loading project…");
		ajax(cfg.actions.get, { id: id })
			.done(function (resp) {
				if (resp && resp.success && resp.data) {
					renderDashboard(resp.data);
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
		ajax(cfg.actions.list, {
			q: $("#rsaip-proj-q").val() || "",
			status: $("#rsaip-proj-filter-status").val() || "all",
			page: page,
			per_page: 20
		}).done(function (resp) {
			if (resp && resp.success && resp.data) {
				renderList(resp.data.items || []);
			}
		});
	}

	$(function () {
		$("#rsaip-proj-refresh").on("click", function () {
			page = 1;
			loadList();
		});
		$("#rsaip-proj-filter-status").on("change", function () {
			page = 1;
			loadList();
		});
		$("#rsaip-proj-q").on("keydown", function (e) {
			if (e.key === "Enter") {
				page = 1;
				loadList();
			}
		});

		$("#rsaip-proj-create").on("click", function () {
			ajax(cfg.actions.create, {
				name: $("#rsaip-proj-name").val() || "",
				description: $("#rsaip-proj-description").val() || "",
				niche: $("#rsaip-proj-niche").val() || "",
				target_country: $("#rsaip-proj-country").val() || "",
				language: $("#rsaip-proj-language").val() || "",
				status: "active"
			}).done(function (resp) {
				if (resp && resp.success && resp.data) {
					setStatus(cfg.i18n.saved);
					$("#rsaip-proj-name").val("");
					$("#rsaip-proj-description").val("");
					renderDashboard(resp.data);
					loadList();
				} else {
					setStatus((resp && resp.data && resp.data.message) || cfg.i18n.error);
				}
			});
		});

		$("#rsaip-proj-save").on("click", function () {
			if (!current) {
				return;
			}
			ajax(cfg.actions.update, {
				id: current.id,
				name: $("#rsaip-proj-edit-name").val() || "",
				description: $("#rsaip-proj-edit-description").val() || "",
				niche: $("#rsaip-proj-edit-niche").val() || "",
				target_country: $("#rsaip-proj-edit-country").val() || "",
				language: $("#rsaip-proj-edit-language").val() || "",
				status: $("#rsaip-proj-edit-status").val() || "active"
			}).done(function (resp) {
				if (resp && resp.success && resp.data) {
					renderDashboard(resp.data);
					setStatus(cfg.i18n.saved);
					loadList();
				}
			});
		});

		$("#rsaip-proj-refresh-stats").on("click", function () {
			if (!current) {
				return;
			}
			ajax(cfg.actions.refreshStats, { id: current.id }).done(function (resp) {
				if (resp && resp.success && resp.data) {
					renderDashboard(resp.data);
					loadList();
				}
			});
		});

		$("#rsaip-proj-attach-brief").on("click", function () {
			if (!current) {
				return;
			}
			ajax(cfg.actions.attachAsset, {
				id: current.id,
				asset_type: "brief",
				asset_id: $("#rsaip-proj-brief-id").val() || 0
			}).done(function (resp) {
				if (resp && resp.success && resp.data) {
					renderDashboard(resp.data);
					setStatus("Brief attached.");
					loadList();
				} else {
					setStatus((resp && resp.data && resp.data.message) || cfg.i18n.error);
				}
			});
		});

		$("#rsaip-proj-add-task").on("click", function () {
			if (!current) {
				return;
			}
			ajax(cfg.actions.addTask, {
				id: current.id,
				title: $("#rsaip-proj-task-title").val() || "",
				due_at: $("#rsaip-proj-task-due").val() || ""
			}).done(function (resp) {
				if (resp && resp.success) {
					$("#rsaip-proj-task-title").val("");
					openProject(current.id);
				}
			});
		});

		$("#rsaip-proj-add-member").on("click", function () {
			if (!current) {
				return;
			}
			ajax(cfg.actions.addMember, {
				id: current.id,
				user_id: $("#rsaip-proj-member-id").val() || 0,
				role: "member"
			}).done(function (resp) {
				if (resp && resp.success && resp.data) {
					renderDashboard(resp.data);
				} else {
					setStatus((resp && resp.data && resp.data.message) || cfg.i18n.error);
				}
			});
		});

		loadList();
	});
})(jQuery);
