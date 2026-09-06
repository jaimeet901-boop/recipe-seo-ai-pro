(function ($) {
	'use strict';

	var cfg = window.RSAIP_KEYWORD_WORKSPACE || {};
	var ajaxUrl = cfg.ajaxUrl || (window.ajaxurl || '');
	var nonce = cfg.nonce || '';
	var actions = cfg.actions || {};
	var selectedId = 0;

	function setStatus(msg, isErr) {
		var $el = $('#rsaip-kw-status-text');
		$el.text(msg || '');
		$el.toggleClass('rsaip-error', !!isErr);
	}

	function projectId() {
		return parseInt($('#rsaip-kw-project').val(), 10) || 0;
	}

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = nonce;
		return $.post(ajaxUrl, data);
	}

	function selectedIds() {
		var ids = [];
		$('#rsaip-kw-body input.rsaip-kw-check:checked').each(function () {
			ids.push(parseInt($(this).val(), 10));
		});
		return ids;
	}

	function esc(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	function renderRows(items) {
		var $body = $('#rsaip-kw-body');
		$body.empty();
		if (!items || !items.length) {
			$body.append(
				'<tr><td colspan="9"><div class="rsaip-empty-state"><span class="dashicons dashicons-tag" aria-hidden="true"></span><h3>' +
					esc(cfg.i18n && cfg.i18n.empty ? cfg.i18n.empty : 'No keywords yet') +
					'</h3><p>Add a keyword or run Keyword Research to populate this workspace.</p></div></td></tr>'
			);
			return;
		}
		items.forEach(function (row) {
			var intent = String(row.intent || '');
			var status = String(row.status || '');
			var priority = String(row.priority || '');
			var priClass = 'rsaip-pill';
			if (priority === 'high' || priority === '1' || priority === 'urgent') {
				priClass += ' rsaip-pill-warn';
			} else if (priority === 'low') {
				priClass += ' rsaip-pill-ok';
			}
			var tr =
				'<tr data-id="' +
				esc(row.id) +
				'" data-intent="' +
				esc(intent) +
				'">' +
				'<td><input type="checkbox" class="rsaip-kw-check" value="' +
				esc(row.id) +
				'" /></td>' +
				'<td><strong>' +
				esc(row.primary_keyword) +
				'</strong><br><span class="rsaip-sub">' +
				esc(row.roadmap_phase || '') +
				'</span></td>' +
				'<td><span class="rsaip-badge rsaip-badge-provider rsaip-kw-intent">' +
				esc(intent) +
				'</span></td>' +
				'<td><span class="rsaip-badge">' +
				esc(status) +
				'</span></td>' +
				'<td><span class="' +
				priClass +
				'">' +
				esc(priority) +
				'</span></td>' +
				'<td><span class="rsaip-badge rsaip-badge-project">' +
				esc(row.cluster_id || '—') +
				'</span></td>' +
				'<td>' +
				esc(row.brief_id || '—') +
				'</td>' +
				'<td>' +
				esc(row.article_id || '—') +
				'</td>' +
				'<td><button type="button" class="button button-small rsaip-kw-open">' +
				esc(cfg.i18n && cfg.i18n.open ? cfg.i18n.open : 'Open') +
				'</button></td>' +
				'</tr>';
			$body.append(tr);
		});
	}

	function loadList() {
		setStatus('Loading…');
		return post(actions.list || 'rsaip_keyword_list', {
			project_id: projectId(),
			q: $('#rsaip-kw-q').val() || '',
			status: $('#rsaip-kw-status').val() || 'all',
			intent: $('#rsaip-kw-intent').val() || 'all',
		})
			.done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				renderRows(res.data.items || []);
				setStatus((res.data.items || []).length + ' keywords');
			})
			.fail(function () {
				setStatus('Request failed', true);
			});
	}

	function openDetail(id) {
		selectedId = id;
		post(actions.get || 'rsaip_keyword_get', { id: id })
			.done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				var k = res.data.keyword || res.data || {};
				var notes = res.data.notes || k.notes || [];
				var hist = res.data.history || k.history || [];
				$('#rsaip-kw-detail').prop('hidden', false);
				$('#rsaip-kw-detail-title').text(k.primary_keyword || 'Keyword');
				$('#rsaip-kw-brief-id').val(k.brief_id || 0);
				$('#rsaip-kw-article-id').val(k.article_id || 0);
				$('#rsaip-kw-detail-status').val(k.status || 'idea');
				var meta =
					'Intent: ' +
					(k.intent || '') +
					' · Difficulty: ' +
					(k.difficulty || 0) +
					' · Priority: ' +
					(k.priority || 0) +
					' · URL: ' +
					(k.target_url || '—') +
					' · Lang/Country: ' +
					(k.language || '') +
					'/' +
					(k.country || '') +
					' · Updated: ' +
					(k.updated_at || '');
				if (notes.length) {
					meta += '<br><br><strong>Notes</strong><ul>';
					notes.forEach(function (n) {
						meta += '<li>' + esc(n.note) + ' <span class="rsaip-sub">(' + esc(n.created_at) + ')</span></li>';
					});
					meta += '</ul>';
				}
				if (hist.length) {
					meta += '<br><strong>History</strong><ul>';
					hist.slice(0, 20).forEach(function (h) {
						meta +=
							'<li>' +
							esc(h.action) +
							': ' +
							esc(h.field_name || '') +
							' ' +
							esc(h.old_value || '') +
							' → ' +
							esc(h.new_value || '') +
							' <span class="rsaip-sub">(' +
							esc(h.created_at) +
							')</span></li>';
					});
					meta += '</ul>';
				}
				$('#rsaip-kw-detail-meta').html(meta);
			})
			.fail(function () {
				setStatus('Request failed', true);
			});
	}

	function showSide(title, html) {
		$('#rsaip-kw-side-view').prop('hidden', false);
		$('#rsaip-kw-side-title').text(title);
		$('#rsaip-kw-side-body').html(html);
	}

	$(function () {
		if (cfg.projectId) {
			$('#rsaip-kw-project').val(String(cfg.projectId));
		}

		loadList();

		$('#rsaip-kw-refresh, #rsaip-kw-project, #rsaip-kw-status, #rsaip-kw-intent').on('change click', function (e) {
			if (e.type === 'click' && this.id !== 'rsaip-kw-refresh') {
				return;
			}
			loadList();
		});
		$('#rsaip-kw-q').on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				loadList();
			}
		});

		$('#rsaip-kw-check-all').on('change', function () {
			$('#rsaip-kw-body .rsaip-kw-check').prop('checked', this.checked);
		});

		$('#rsaip-kw-body').on('click', '.rsaip-kw-open', function () {
			var id = parseInt($(this).closest('tr').data('id'), 10);
			if (id) {
				openDetail(id);
			}
		});

		$('#rsaip-kw-create').on('click', function () {
			var primary = ($('#rsaip-kw-primary').val() || '').trim();
			if (!primary) {
				setStatus('Primary keyword required', true);
				return;
			}
			if (!projectId()) {
				setStatus('Select a project first', true);
				return;
			}
			setStatus('Saving…');
			post(actions.create || 'rsaip_keyword_create', {
				project_id: projectId(),
				primary_keyword: primary,
				intent: $('#rsaip-kw-add-intent').val(),
				priority: $('#rsaip-kw-priority').val(),
				difficulty: $('#rsaip-kw-difficulty').val(),
				roadmap_phase: $('#rsaip-kw-phase').val(),
				target_url: $('#rsaip-kw-url').val(),
			})
				.done(function (res) {
					if (!res || !res.success) {
						setStatus((res && res.data && res.data.message) || 'Failed', true);
						return;
					}
					$('#rsaip-kw-primary').val('');
					setStatus('Keyword added');
					loadList();
				})
				.fail(function () {
					setStatus('Request failed', true);
				});
		});

		$('#rsaip-kw-assign-brief').on('click', function () {
			if (!selectedId) {
				return;
			}
			post(actions.assignBrief || 'rsaip_keyword_assign_brief', {
				id: selectedId,
				brief_id: $('#rsaip-kw-brief-id').val(),
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				setStatus('Brief assigned');
				openDetail(selectedId);
				loadList();
			});
		});

		$('#rsaip-kw-assign-article').on('click', function () {
			if (!selectedId) {
				return;
			}
			post(actions.assignArticle || 'rsaip_keyword_assign_article', {
				id: selectedId,
				article_id: $('#rsaip-kw-article-id').val(),
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				setStatus('Article assigned');
				openDetail(selectedId);
				loadList();
			});
		});

		$('#rsaip-kw-save-status').on('click', function () {
			if (!selectedId) {
				return;
			}
			post(actions.changeStatus || 'rsaip_keyword_change_status', {
				id: selectedId,
				status: $('#rsaip-kw-detail-status').val(),
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				setStatus('Status updated');
				openDetail(selectedId);
				loadList();
			});
		});

		$('#rsaip-kw-add-note').on('click', function () {
			if (!selectedId) {
				return;
			}
			var note = ($('#rsaip-kw-note').val() || '').trim();
			if (!note) {
				return;
			}
			post(actions.addNote || 'rsaip_keyword_add_note', { id: selectedId, note: note }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				$('#rsaip-kw-note').val('');
				setStatus('Note saved');
				openDetail(selectedId);
			});
		});

		$('#rsaip-kw-bulk-status').on('click', function () {
			var ids = selectedIds();
			if (!ids.length) {
				setStatus('Select keywords first', true);
				return;
			}
			post(actions.bulk || 'rsaip_keyword_bulk', {
				ids: ids,
				bulk_action: 'status',
				status: $('#rsaip-kw-bulk-status-value').val(),
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				setStatus('Bulk status updated');
				loadList();
			});
		});

		$('#rsaip-kw-bulk-phase').on('click', function () {
			var ids = selectedIds();
			if (!ids.length) {
				setStatus('Select keywords first', true);
				return;
			}
			post(actions.bulk || 'rsaip_keyword_bulk', {
				ids: ids,
				bulk_action: 'roadmap_phase',
				roadmap_phase: $('#rsaip-kw-bulk-phase-value').val(),
			}).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				setStatus('Bulk phase updated');
				loadList();
			});
		});

		$('#rsaip-kw-bulk-delete').on('click', function () {
			var ids = selectedIds();
			if (!ids.length) {
				setStatus('Select keywords first', true);
				return;
			}
			if (!window.confirm('Delete selected keywords?')) {
				return;
			}
			post(actions.bulk || 'rsaip_keyword_bulk', { ids: ids, bulk_action: 'delete' }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				setStatus('Deleted');
				$('#rsaip-kw-detail').prop('hidden', true);
				loadList();
			});
		});

		$('#rsaip-kw-create-cluster').on('click', function () {
			var name = ($('#rsaip-kw-cluster-name').val() || '').trim();
			if (!name || !projectId()) {
				setStatus('Project + cluster name required', true);
				return;
			}
			post(actions.createCluster || 'rsaip_keyword_create_cluster', { project_id: projectId(), name: name }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				$('#rsaip-kw-cluster-name').val('');
				setStatus('Cluster created');
			});
		});

		$('#rsaip-kw-view-clusters').on('click', function () {
			if (!projectId()) {
				setStatus('Select a project', true);
				return;
			}
			post(actions.clusterView || 'rsaip_keyword_cluster_view', { project_id: projectId() }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				var groups = res.data.groups || {};
				var names = Object.keys(groups);
				if (!names.length) {
					showSide('Cluster View', '<p>No keywords in clusters yet.</p>');
					return;
				}
				var html = '';
				names.forEach(function (name) {
					html += '<h3>' + esc(name) + '</h3><ul>';
					(groups[name] || []).forEach(function (k) {
						html +=
							'<li>' +
							esc(k.primary_keyword || '') +
							' <span class="rsaip-sub">(' +
							esc(k.status || '') +
							')</span></li>';
					});
					html += '</ul>';
				});
				showSide('Cluster View', html);
			});
		});

		$('#rsaip-kw-view-roadmap').on('click', function () {
			if (!projectId()) {
				setStatus('Select a project', true);
				return;
			}
			post(actions.roadmap || 'rsaip_keyword_roadmap', { project_id: projectId() }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				var phases = res.data.phases || {};
				var html = '';
				Object.keys(phases).forEach(function (phase) {
					html += '<h3>' + esc(phase) + '</h3><ul>';
					(phases[phase] || []).forEach(function (k) {
						html +=
							'<li>' +
							esc(k.primary_keyword) +
							' <span class="rsaip-sub">(' +
							esc(k.status) +
							')</span></li>';
					});
					html += '</ul>';
				});
				showSide('Roadmap', html || '<p>Empty roadmap.</p>');
			});
		});

		$('#rsaip-kw-view-timeline').on('click', function () {
			if (!projectId()) {
				setStatus('Select a project', true);
				return;
			}
			post(actions.timeline || 'rsaip_keyword_timeline', { project_id: projectId() }).done(function (res) {
				if (!res || !res.success) {
					setStatus((res && res.data && res.data.message) || 'Failed', true);
					return;
				}
				var items = res.data.items || [];
				if (!items.length) {
					showSide('Timeline', '<p>No history yet.</p>');
					return;
				}
				var html = '<ul>';
				items.forEach(function (h) {
					html +=
						'<li><span class="rsaip-sub">' +
						esc(h.created_at) +
						'</span> — ' +
						esc(h.action) +
						' #' +
						esc(h.keyword_id) +
						' ' +
						esc(h.field_name || '') +
						'</li>';
				});
				html += '</ul>';
				showSide('Timeline', html);
			});
		});
	});
})(jQuery);
