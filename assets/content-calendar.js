/**
 * AI Content Calendar admin UI (Phase 3.7).
 */
(function ($) {
	"use strict";

	if (typeof RSAIP_CONTENT_CALENDAR === "undefined") {
		return;
	}

	var cfg = RSAIP_CONTENT_CALENDAR;
	var actions = cfg.actions || {};
	var labels = cfg.labels || {};
	var state = {
		view: "month",
		anchor: toYmd(new Date()),
		events: [],
		groups: {},
		selectedId: 0,
		selectedIds: {}
	};

	function setStatus(msg, isErr) {
		var $el = $("#rsaip-cal-status-text");
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

	function toYmd(d) {
		var y = d.getUTCFullYear();
		var m = String(d.getUTCMonth() + 1).padStart(2, "0");
		var day = String(d.getUTCDate()).padStart(2, "0");
		return y + "-" + m + "-" + day;
	}

	function parseYmd(s) {
		var p = String(s || "").split("-");
		return new Date(Date.UTC(+p[0], (+p[1] || 1) - 1, +p[2] || 1));
	}

	function addDays(ymd, n) {
		var d = parseYmd(ymd);
		d.setUTCDate(d.getUTCDate() + n);
		return toYmd(d);
	}

	function projectId() {
		return parseInt($("#rsaip-cal-project").val(), 10) || 0;
	}

	function calendarId() {
		return parseInt($("#rsaip-cal-calendar").val(), 10) || 0;
	}

	function selectedIds() {
		return Object.keys(state.selectedIds)
			.filter(function (k) {
				return state.selectedIds[k];
			})
			.map(function (k) {
				return parseInt(k, 10);
			});
	}

	function updateLabel() {
		var d = parseYmd(state.anchor);
		var text = state.anchor;
		if (state.view === "month") {
			text = d.toLocaleString(undefined, { month: "long", year: "numeric", timeZone: "UTC" });
		} else if (state.view === "week") {
			text = "Week of " + state.anchor;
		} else if (state.view === "day") {
			text = state.anchor;
		} else if (state.view === "agenda") {
			text = "Upcoming agenda";
		} else if (state.view === "roadmap") {
			text = "Roadmap";
		}
		$("#rsaip-cal-label").text(text);
	}

	function eventChip(ev) {
		var overdue = ev.is_overdue ? " is-overdue" : "";
		var selected = state.selectedIds[ev.id] ? " is-selected" : "";
		return (
			'<button type="button" class="rsaip-cal-event' +
			overdue +
			selected +
			'" draggable="true" data-id="' +
			esc(ev.id) +
			'" title="' +
			esc(ev.status + " · " + (ev.publishing_channel || "")) +
			'">' +
			esc(ev.title) +
			"</button>"
		);
	}

	function bindDnD($root) {
		$root.find(".rsaip-cal-event").on("dragstart", function (e) {
			var id = $(this).data("id");
			e.originalEvent.dataTransfer.setData("text/plain", String(id));
			e.originalEvent.dataTransfer.effectAllowed = "move";
		});
		$root.find(".rsaip-cal-day").on("dragover", function (e) {
			e.preventDefault();
			$(this).addClass("is-drop-target");
		});
		$root.find(".rsaip-cal-day").on("dragleave", function () {
			$(this).removeClass("is-drop-target");
		});
		$root.find(".rsaip-cal-day").on("drop", function (e) {
			e.preventDefault();
			$(this).removeClass("is-drop-target");
			var id = parseInt(e.originalEvent.dataTransfer.getData("text/plain"), 10);
			var day = $(this).data("date");
			if (!id || !day) {
				return;
			}
			setStatus("Rescheduling…");
			post(actions.reschedule || "rsaip_calendar_reschedule", {
				id: id,
				publish_at: day + " 09:00:00"
			})
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					setStatus(cfg.i18n.saved || "Saved.");
					loadView();
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				});
		});
	}

	function renderMonth(groups) {
		var anchor = parseYmd(state.anchor);
		var first = new Date(Date.UTC(anchor.getUTCFullYear(), anchor.getUTCMonth(), 1));
		var startDow = (first.getUTCDay() + 6) % 7; // Monday=0
		var start = new Date(first);
		start.setUTCDate(1 - startDow);
		var today = toYmd(new Date());
		var html = '<div class="rsaip-cal-grid">';
		var names = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
		names.forEach(function (n) {
			html += '<div class="rsaip-cal-day-num" style="min-height:auto;border:0;background:transparent">' + n + "</div>";
		});
		for (var i = 0; i < 42; i++) {
			var d = new Date(start);
			d.setUTCDate(start.getUTCDate() + i);
			var ymd = toYmd(d);
			var outside = d.getUTCMonth() !== anchor.getUTCMonth() ? " is-outside" : "";
			var isToday = ymd === today ? " is-today" : "";
			html +=
				'<div class="rsaip-cal-day' +
				outside +
				isToday +
				'" data-date="' +
				esc(ymd) +
				'"><span class="rsaip-cal-day-num">' +
				d.getUTCDate() +
				"</span>";
			(groups[ymd] || []).forEach(function (ev) {
				html += eventChip(ev);
			});
			html += "</div>";
		}
		html += "</div>";
		return html;
	}

	function renderWeek(groups) {
		var d = parseYmd(state.anchor);
		var dow = (d.getUTCDay() + 6) % 7;
		var start = addDays(state.anchor, -dow);
		var html = '<div class="rsaip-cal-grid rsaip-cal-week-hours">';
		for (var i = 0; i < 7; i++) {
			var ymd = addDays(start, i);
			html +=
				'<div class="rsaip-cal-day" data-date="' +
				esc(ymd) +
				'"><span class="rsaip-cal-day-num">' +
				esc(ymd) +
				"</span>";
			(groups[ymd] || []).forEach(function (ev) {
				html += eventChip(ev);
			});
			html += "</div>";
		}
		html += "</div>";
		return html;
	}

	function renderDay(groups) {
		var ymd = state.anchor;
		var html =
			'<div class="rsaip-cal-grid" style="grid-template-columns:1fr"><div class="rsaip-cal-day" data-date="' +
			esc(ymd) +
			'"><span class="rsaip-cal-day-num">' +
			esc(ymd) +
			"</span>";
		(groups[ymd] || state.events || []).forEach(function (ev) {
			html += eventChip(ev);
		});
		html += "</div></div>";
		return html;
	}

	function renderAgenda(events) {
		var html = '<ul class="rsaip-cal-list">';
		if (!events.length) {
			html += "<li>No upcoming events.</li>";
		}
		events.forEach(function (ev) {
			html +=
				"<li><input type='checkbox' class='rsaip-cal-check' value='" +
				esc(ev.id) +
				"' " +
				(state.selectedIds[ev.id] ? "checked" : "") +
				"/> <strong>" +
				esc(ev.publish_at) +
				"</strong> — " +
				eventChip(ev) +
				"</li>";
		});
		html += "</ul>";
		return html;
	}

	function renderRoadmap(groups) {
		var phases = labels.phases || {
			now: "Now",
			next: "Next",
			later: "Later",
			backlog: "Backlog"
		};
		var html = '<div class="rsaip-cal-roadmap">';
		Object.keys(phases).forEach(function (phase) {
			html +=
				'<div class="rsaip-cal-roadmap-col" data-phase="' +
				esc(phase) +
				'"><h3>' +
				esc(phases[phase]) +
				"</h3>";
			(groups[phase] || []).forEach(function (ev) {
				html += eventChip(ev);
			});
			html += "</div>";
		});
		html += "</div>";
		return html;
	}

	function renderBoard(payload) {
		state.events = payload.events || [];
		state.groups = payload.groups || {};
		updateLabel();
		var html = "";
		if (state.view === "month") {
			html = renderMonth(state.groups);
		} else if (state.view === "week") {
			html = renderWeek(state.groups);
		} else if (state.view === "day") {
			html = renderDay(state.groups);
		} else if (state.view === "roadmap") {
			html = renderRoadmap(state.groups);
		} else {
			html = renderAgenda(state.events);
		}
		var $board = $("#rsaip-cal-board").html(html);
		bindDnD($board);
		$board.find(".rsaip-cal-event").on("click", function (e) {
			e.preventDefault();
			var id = parseInt($(this).data("id"), 10);
			if (e.shiftKey || e.metaKey || e.ctrlKey) {
				state.selectedIds[id] = !state.selectedIds[id];
				$(this).toggleClass("is-selected", !!state.selectedIds[id]);
				return;
			}
			openDetail(id);
		});
		$board.find(".rsaip-cal-check").on("change", function () {
			var id = parseInt($(this).val(), 10);
			state.selectedIds[id] = this.checked;
		});
	}

	function loadView() {
		setStatus("Loading…");
		return post(actions.view || "rsaip_calendar_view", {
			view: state.view,
			anchor_date: state.anchor,
			project_id: projectId(),
			calendar_id: calendarId(),
			status: $("#rsaip-cal-status").val() || "all"
		})
			.done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				renderBoard(res.data || {});
				setStatus((res.data.events || []).length + " events");
			})
			.fail(function () {
				setStatus(cfg.i18n.error, true);
			});
	}

	function openDetail(id) {
		state.selectedId = id;
		post(actions.get || "rsaip_calendar_get", { id: id }).done(function (res) {
			if (!res || !res.success) {
				setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
				return;
			}
			var ev = (res.data && res.data.event) || {};
			$("#rsaip-cal-detail").prop("hidden", false);
			$("#rsaip-cal-detail-title").text(ev.title || "Event");
			$("#rsaip-cal-detail-meta").html(
				"Publish: " +
					esc(ev.publish_at) +
					" · Status: " +
					esc(ev.status) +
					" · Priority: " +
					esc(ev.priority) +
					" · Channel: " +
					esc(ev.publishing_channel) +
					" · Project #" +
					esc(ev.project_id) +
					" · Keyword #" +
					esc(ev.keyword_id) +
					" · Brief #" +
					esc(ev.brief_id) +
					" · Article #" +
					esc(ev.article_id) +
					" · Assignee #" +
					esc(ev.assigned_user_id) +
					" · TZ: " +
					esc(ev.timezone) +
					(ev.notes ? "<br>" + esc(ev.notes) : "")
			);
			var local = String(ev.publish_at || "").replace(" ", "T").slice(0, 16);
			$("#rsaip-cal-reschedule").val(local);
		});
	}

	function showSide(title, items, formatter) {
		$("#rsaip-cal-side").prop("hidden", false);
		$("#rsaip-cal-side-title").text(title);
		if (!items || !items.length) {
			$("#rsaip-cal-side-body").html("<p>None.</p>");
			return;
		}
		var html = "<ul class='rsaip-cal-list'>";
		items.forEach(function (item) {
			html += "<li>" + formatter(item) + "</li>";
		});
		html += "</ul>";
		$("#rsaip-cal-side-body").html(html);
	}

	function toServerDatetime(localVal) {
		if (!localVal) {
			return "";
		}
		return String(localVal).replace("T", " ") + (localVal.length === 16 ? ":00" : "");
	}

	$(function () {
		if (cfg.projectId) {
			$("#rsaip-cal-project").val(String(cfg.projectId));
		}
		loadView();

		$(".rsaip-cal-view-btn").on("click", function () {
			$(".rsaip-cal-view-btn").removeClass("button-primary");
			$(this).addClass("button-primary");
			state.view = $(this).data("view") || "month";
			loadView();
		});

		$("#rsaip-cal-prev").on("click", function () {
			if (state.view === "month") {
				var d = parseYmd(state.anchor);
				d.setUTCMonth(d.getUTCMonth() - 1);
				state.anchor = toYmd(d);
			} else if (state.view === "week") {
				state.anchor = addDays(state.anchor, -7);
			} else if (state.view === "day") {
				state.anchor = addDays(state.anchor, -1);
			}
			loadView();
		});
		$("#rsaip-cal-next").on("click", function () {
			if (state.view === "month") {
				var d = parseYmd(state.anchor);
				d.setUTCMonth(d.getUTCMonth() + 1);
				state.anchor = toYmd(d);
			} else if (state.view === "week") {
				state.anchor = addDays(state.anchor, 7);
			} else if (state.view === "day") {
				state.anchor = addDays(state.anchor, 1);
			}
			loadView();
		});
		$("#rsaip-cal-today").on("click", function () {
			state.anchor = toYmd(new Date());
			loadView();
		});

		$("#rsaip-cal-project, #rsaip-cal-calendar, #rsaip-cal-status").on("change", loadView);

		$("#rsaip-cal-create").on("click", function () {
			var title = ($("#rsaip-cal-title").val() || "").trim();
			var publish = toServerDatetime($("#rsaip-cal-publish").val());
			if (!title || !publish) {
				setStatus("Title and publish date required", true);
				return;
			}
			setStatus("Saving…");
			post(actions.create || "rsaip_calendar_create", {
				title: title,
				publish_at: publish,
				status: $("#rsaip-cal-ev-status").val(),
				publishing_channel: $("#rsaip-cal-channel").val(),
				priority: $("#rsaip-cal-priority").val(),
				roadmap_phase: $("#rsaip-cal-phase").val(),
				project_id: projectId(),
				calendar_id: calendarId(),
				keyword_id: $("#rsaip-cal-keyword").val(),
				brief_id: $("#rsaip-cal-brief").val(),
				article_id: $("#rsaip-cal-article").val(),
				assigned_user_id: $("#rsaip-cal-assignee").val(),
				timezone: $("#rsaip-cal-tz").val() || "UTC",
				notes: $("#rsaip-cal-notes").val() || ""
			})
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					$("#rsaip-cal-title").val("");
					setStatus(cfg.i18n.saved || "Saved.");
					loadView();
				})
				.fail(function () {
					setStatus(cfg.i18n.error, true);
				});
		});

		$("#rsaip-cal-create-cal").on("click", function () {
			var name = ($("#rsaip-cal-new-name").val() || "").trim();
			if (!name) {
				setStatus("Calendar name required", true);
				return;
			}
			post(actions.createCal || "rsaip_calendar_create_cal", {
				name: name,
				project_id: projectId(),
				timezone: $("#rsaip-cal-tz").val() || "UTC"
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				var id = res.data.id;
				var $sel = $("#rsaip-cal-calendar");
				$sel.append($("<option>").val(id).text(name));
				$sel.val(String(id));
				$("#rsaip-cal-new-name").val("");
				setStatus("Calendar created");
			});
		});

		$("#rsaip-cal-dup").on("click", function () {
			if (!state.selectedId) {
				return;
			}
			post(actions.duplicate || "rsaip_calendar_duplicate", { id: state.selectedId }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				setStatus("Duplicated");
				loadView();
			});
		});

		$("#rsaip-cal-del").on("click", function () {
			if (!state.selectedId) {
				return;
			}
			if (!window.confirm(cfg.i18n.confirmDel || "Delete?")) {
				return;
			}
			post(actions.delete || "rsaip_calendar_delete", { id: state.selectedId }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				$("#rsaip-cal-detail").prop("hidden", true);
				setStatus(cfg.i18n.deleted || "Deleted.");
				loadView();
			});
		});

		$("#rsaip-cal-reschedule-btn").on("click", function () {
			if (!state.selectedId) {
				return;
			}
			post(actions.reschedule || "rsaip_calendar_reschedule", {
				id: state.selectedId,
				publish_at: toServerDatetime($("#rsaip-cal-reschedule").val())
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				setStatus(cfg.i18n.saved || "Saved.");
				openDetail(state.selectedId);
				loadView();
			});
		});

		$("#rsaip-cal-bulk-move").on("click", function () {
			var ids = selectedIds();
			if (!ids.length && state.selectedId) {
				ids = [state.selectedId];
			}
			if (!ids.length) {
				setStatus("Select events (Shift+click)", true);
				return;
			}
			post(actions.bulkMove || "rsaip_calendar_bulk_move", {
				ids: JSON.stringify(ids),
				days: 7
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				state.selectedIds = {};
				setStatus("Moved " + ((res.data && res.data.updated) || 0));
				loadView();
			});
		});

		$("#rsaip-cal-bulk-delete").on("click", function () {
			var ids = selectedIds();
			if (!ids.length && state.selectedId) {
				ids = [state.selectedId];
			}
			if (!ids.length) {
				setStatus("Select events (Shift+click)", true);
				return;
			}
			if (!window.confirm(cfg.i18n.confirmDel || "Delete?")) {
				return;
			}
			post(actions.bulkDelete || "rsaip_calendar_bulk_delete", {
				ids: JSON.stringify(ids)
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				state.selectedIds = {};
				$("#rsaip-cal-detail").prop("hidden", true);
				setStatus("Deleted " + ((res.data && res.data.deleted) || 0));
				loadView();
			});
		});

		$("#rsaip-cal-show-upcoming").on("click", function () {
			post(actions.upcoming || "rsaip_calendar_upcoming", { project_id: projectId() }).done(function (res) {
				if (!res || !res.success) {
					return;
				}
				showSide("Upcoming", res.data.items || [], function (ev) {
					return esc(ev.publish_at) + " — " + esc(ev.title);
				});
			});
		});
		$("#rsaip-cal-show-overdue").on("click", function () {
			post(actions.overdue || "rsaip_calendar_overdue", { project_id: projectId() }).done(function (res) {
				if (!res || !res.success) {
					return;
				}
				showSide("Overdue", res.data.items || [], function (ev) {
					return esc(ev.publish_at) + " — " + esc(ev.title);
				});
			});
		});
		$("#rsaip-cal-show-queue").on("click", function () {
			post(actions.queue || "rsaip_calendar_queue", { project_id: projectId() }).done(function (res) {
				if (!res || !res.success) {
					return;
				}
				showSide("Publishing Queue", res.data.items || [], function (q) {
					return (
						esc(q.scheduled_at) +
						" · event #" +
						esc(q.event_id) +
						" · " +
						esc(q.channel) +
						" · " +
						esc(q.status)
					);
				});
			});
		});
	});
})(jQuery);
