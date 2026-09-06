(function ($) {
	'use strict';

	var cfg = window.RSAIP_RECIPE_AI || {};
	var modeMaps = {};
	var currentRecipe = null;
	var currentSource = cfg.sourceType || 'builder';

	function msg(text, isError) {
		$('#rsaip-rai-status').text(text || '').toggleClass('rsaip-error', !!isError);
	}

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function pretty(obj) {
		try {
			return JSON.stringify(obj || {}, null, 2);
		} catch (e) {
			return '';
		}
	}

	function sourceType() {
		return $('input[name="rsaip_rai_source"]:checked').val() || 'builder';
	}

	function syncSourcePanels() {
		currentSource = sourceType();
		$('#rsaip-rai-source-builder').prop('hidden', currentSource !== 'builder');
		$('#rsaip-rai-source-post').prop('hidden', currentSource !== 'post');
	}

	function loadModeMaps() {
		var el = document.getElementById('rsaip-rai-mode-maps');
		if (!el) {
			return;
		}
		try {
			modeMaps = JSON.parse(el.textContent || '{}') || {};
		} catch (e) {
			modeMaps = {};
		}
	}

	function refreshModes() {
		var action = $('#rsaip-rai-action').val() || 'analyze';
		var map = modeMaps[action] || { '': 'Default' };
		var $mode = $('#rsaip-rai-mode').empty();
		Object.keys(map).forEach(function (key) {
			$mode.append($('<option/>').val(key).text(map[key]));
		});
	}

	function renderScores(analysis) {
		var $box = $('#rsaip-rai-scores').empty().prop('hidden', true);
		if (!analysis || typeof analysis !== 'object') {
			return;
		}
		var keys = [
			['quality_score', 'Quality'],
			['ingredient_completeness', 'Ingredients'],
			['instruction_quality', 'Instructions'],
			['seo_score', 'SEO'],
			['schema_completeness', 'Schema']
		];
		var has = false;
		keys.forEach(function (pair) {
			if (analysis[pair[0]] == null) {
				return;
			}
			has = true;
			$box.append(
				$('<div class="rsaip-rai-score"/>').append(
					$('<span/>').text(pair[1]),
					$('<strong/>').text(String(analysis[pair[0]]))
				)
			);
		});
		if (analysis.cooking_difficulty) {
			has = true;
			$box.append(
				$('<div class="rsaip-rai-score"/>').append(
					$('<span/>').text('Difficulty'),
					$('<strong/>').text(String(analysis.cooking_difficulty))
				)
			);
		}
		if (has) {
			$box.prop('hidden', false);
		}
	}

	function showRun(run) {
		if (!run) {
			return;
		}
		$('#rsaip-rai-run-id').val(run.id || 0);
		$('#rsaip-rai-original').text(pretty(run.original));
		$('#rsaip-rai-optimized').text(pretty(run.optimized));
		$('#rsaip-rai-diff').text(pretty(run.diff));
		$('#rsaip-rai-analysis').text(pretty(run.analysis));
		renderScores(run.analysis || {});
		$('#rsaip-rai-apply').prop('disabled', !run.optimized || run.action_type === 'analyze');
		$('#rsaip-rai-undo').prop('disabled', !(run.id > 0));

		var src = (run.optimized && run.optimized.source_type) || (run.original && run.original.source_type) || 'builder';
		if (src === 'post') {
			$('input[name="rsaip_rai_source"][value="post"]').prop('checked', true);
			syncSourcePanels();
			var pid = (run.optimized && run.optimized.post_id) || (run.original && run.original.post_id) || 0;
			if (pid) {
				ensurePostOption(pid, (run.original && run.original.title) || ('Post #' + pid));
				$('#rsaip-rai-post').val(String(pid));
				$('#rsaip-rai-post-id').val(String(pid));
			}
		} else if (run.rb_recipe_id) {
			$('input[name="rsaip_rai_source"][value="builder"]').prop('checked', true);
			syncSourcePanels();
			$('#rsaip-rai-recipe').val(String(run.rb_recipe_id));
		}

		if (run.summary) {
			msg(run.summary);
		}
		prependRun(run);
	}

	function ensurePostOption(id, title) {
		var $sel = $('#rsaip-rai-post');
		if (!$sel.find('option[value="' + id + '"]').length) {
			$sel.append($('<option/>').val(String(id)).text(title || ('Post #' + id)));
		}
	}

	function prependRun(run) {
		var id = String(run.id || 0);
		if (!id || id === '0') {
			return;
		}
		var $existing = $('#rsaip-rai-runs .rsaip-rai-open-run[data-id="' + id + '"]');
		if ($existing.length) {
			$existing.closest('li').find('.rsaip-sub').text(run.status || '');
			return;
		}
		$('#rsaip-rai-runs').prepend(
			$('<li/>').append(
				$('<button type="button" class="button-link rsaip-rai-open-run"/>')
					.attr('data-id', id)
					.text('#' + id + ' ' + (run.action_type || '') + (run.mode ? ' (' + run.mode + ')' : '')),
				$('<span class="rsaip-sub"/>').text(run.status || '')
			)
		);
	}

	function renderVersions(versions) {
		var $list = $('#rsaip-rai-versions').empty();
		(versions || []).forEach(function (v) {
			var id = v.id;
			$list.append(
				$('<li/>').append(
					$('<button type="button" class="button-link rsaip-rai-restore-ver"/>')
						.attr('data-id', id)
						.text('#' + id + ' ' + (v.label || '') + ' v' + (v.version_no || '')),
					$('<span class="rsaip-sub"/>').text(v.created_at || '')
				)
			);
		});
	}

	function loadSource() {
		var source = sourceType();
		if (source === 'post') {
			var postId = parseInt($('#rsaip-rai-post').val(), 10) || parseInt($('#rsaip-rai-post-id').val(), 10) || 0;
			if (!postId) {
				msg((cfg.i18n && cfg.i18n.selectSource) || 'Select a post first.', true);
				return;
			}
			ajax(cfg.actions.load, { source_type: 'post', post_id: postId, rb_recipe_id: 0 }).done(function (res) {
				if (res && res.success && res.data && res.data.recipe) {
					currentRecipe = res.data.recipe;
					currentSource = 'post';
					$('#rsaip-rai-post-id').val(String(currentRecipe.post_id || postId));
					var mode = currentRecipe.parse_mode || 'article';
					var note = currentRecipe.has_recipe_card
						? ' (detected recipe content: ' + mode + ')'
						: ' (article content — no recipe card detected)';
					$('#rsaip-rai-recipe-label').text((currentRecipe.title || ('Post #' + postId)) + note);
					$('#rsaip-rai-original').text(pretty(currentRecipe));
					msg((cfg.i18n && cfg.i18n.loadedPost) || 'Post loaded.');
				} else {
					msg((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			}).fail(function () {
				msg((cfg.i18n && cfg.i18n.error) || 'Error', true);
			});
			return;
		}

		var id = parseInt($('#rsaip-rai-recipe').val(), 10) || 0;
		if (!id) {
			msg((cfg.i18n && cfg.i18n.selectSource) || 'Select a recipe first.', true);
			return;
		}
		ajax(cfg.actions.load, { source_type: 'builder', rb_recipe_id: id, post_id: 0 }).done(function (res) {
			if (res && res.success && res.data && res.data.recipe) {
				currentRecipe = res.data.recipe;
				currentSource = 'builder';
				$('#rsaip-rai-recipe-label').text(currentRecipe.title || ('Recipe #' + id));
				$('#rsaip-rai-original').text(pretty(currentRecipe));
				msg('Recipe loaded.');
			} else {
				msg((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error', true);
			}
		}).fail(function () {
			msg((cfg.i18n && cfg.i18n.error) || 'Error', true);
		});
	}

	function bind() {
		$('#rsaip-rai-action').on('change', refreshModes);

		$('input[name="rsaip_rai_source"]').on('change', function () {
			currentRecipe = null;
			syncSourcePanels();
			$('#rsaip-rai-recipe-label').text('');
		});

		$('#rsaip-rai-load').on('click', loadSource);

		$('#rsaip-rai-run').on('click', function () {
			var source = sourceType();
			msg((cfg.i18n && cfg.i18n.running) || 'Running…');
			var payload = {
				ai_action: $('#rsaip-rai-action').val(),
				mode: $('#rsaip-rai-mode').val() || '',
				extra: $('#rsaip-rai-extra').val() || '',
				source_type: source,
				rb_recipe_id: 0,
				post_id: 0
			};

			if (source === 'post') {
				var postId = parseInt($('#rsaip-rai-post').val(), 10) || parseInt($('#rsaip-rai-post-id').val(), 10) || 0;
				payload.post_id = postId;
				if (currentRecipe && currentRecipe.source_type === 'post' && Number(currentRecipe.post_id) === postId) {
					payload.recipe = JSON.stringify(currentRecipe);
				}
			} else {
				var rbId = parseInt($('#rsaip-rai-recipe').val(), 10) || 0;
				payload.rb_recipe_id = rbId;
				if (currentRecipe && Number(currentRecipe.id) === rbId) {
					payload.recipe = JSON.stringify(currentRecipe);
				}
			}

			ajax(cfg.actions.run, payload).done(function (res) {
				if (res && res.success && res.data && res.data.run) {
					showRun(res.data.run);
				} else {
					msg((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			}).fail(function (xhr) {
				var m = cfg.i18n && cfg.i18n.error;
				try { m = xhr.responseJSON.data.message || m; } catch (e) { /* ignore */ }
				msg(m, true);
			});
		});

		$('#rsaip-rai-apply').on('click', function () {
			var id = parseInt($('#rsaip-rai-run-id').val(), 10) || 0;
			if (!id) {
				return;
			}
			var source = sourceType();
			var confirmMsg = source === 'post'
				? ((cfg.i18n && cfg.i18n.confirmApplyPost) || cfg.i18n.confirmApply)
				: ((cfg.i18n && cfg.i18n.confirmApplyBuilder) || cfg.i18n.confirmApply);
			if (!window.confirm(confirmMsg || 'Apply?')) {
				return;
			}
			ajax(cfg.actions.apply, { id: id }).done(function (res) {
				if (res && res.success && res.data && res.data.run) {
					showRun(res.data.run);
					var done = source === 'post'
						? ((cfg.i18n && cfg.i18n.appliedPost) || cfg.i18n.applied)
						: ((cfg.i18n && cfg.i18n.appliedBuilder) || cfg.i18n.applied);
					msg(done || 'Applied.');
				} else {
					msg((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			}).fail(function () {
				msg((cfg.i18n && cfg.i18n.error) || 'Error', true);
			});
		});

		$('#rsaip-rai-undo').on('click', function () {
			var id = parseInt($('#rsaip-rai-run-id').val(), 10) || 0;
			if (!id) {
				return;
			}
			ajax(cfg.actions.undo, { id: id }).done(function (res) {
				if (res && res.success && res.data && res.data.run) {
					showRun(res.data.run);
					msg((cfg.i18n && cfg.i18n.undone) || 'Undone.');
				} else {
					msg((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			}).fail(function () {
				msg((cfg.i18n && cfg.i18n.error) || 'Error', true);
			});
		});

		$('#rsaip-rai-history').on('click', function () {
			ajax(cfg.actions.history, {
				rb_recipe_id: parseInt($('#rsaip-rai-recipe').val(), 10) || 0,
				id: parseInt($('#rsaip-rai-run-id').val(), 10) || 0
			}).done(function (res) {
				if (res && res.success && res.data) {
					renderVersions(res.data.versions || []);
					(res.data.runs || []).forEach(prependRun);
					msg('History loaded.');
				}
			});
		});

		$(document).on('click', '.rsaip-rai-open-run', function () {
			var id = parseInt($(this).data('id'), 10) || 0;
			if (!id) {
				return;
			}
			ajax(cfg.actions.get, { id: id }).done(function (res) {
				if (res && res.success && res.data && res.data.run) {
					showRun(res.data.run);
				}
			});
		});

		$(document).on('click', '.rsaip-rai-restore-ver', function () {
			var versionId = parseInt($(this).data('id'), 10) || 0;
			if (!versionId) {
				return;
			}
			ajax(cfg.actions.restore, {
				version_id: versionId,
				id: parseInt($('#rsaip-rai-run-id').val(), 10) || 0
			}).done(function (res) {
				if (res && res.success && res.data && res.data.run) {
					showRun(res.data.run);
					msg('Version restored (backup created first).');
				} else {
					msg((res && res.data && res.data.message) || (cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			});
		});
	}

	$(function () {
		if (!$('#rsaip-rai-action').length) {
			return;
		}
		loadModeMaps();
		refreshModes();
		syncSourcePanels();
		bind();

		if (cfg.sourceType === 'post' && cfg.postId) {
			$('input[name="rsaip_rai_source"][value="post"]').prop('checked', true);
			syncSourcePanels();
			ensurePostOption(cfg.postId, 'Post #' + cfg.postId);
			$('#rsaip-rai-post').val(String(cfg.postId));
			$('#rsaip-rai-post-id').val(String(cfg.postId));
			$('#rsaip-rai-load').trigger('click');
		} else if (cfg.rbRecipeId) {
			$('input[name="rsaip_rai_source"][value="builder"]').prop('checked', true);
			syncSourcePanels();
			$('#rsaip-rai-recipe').val(String(cfg.rbRecipeId));
			$('#rsaip-rai-load').trigger('click');
		}
	});
})(jQuery);
