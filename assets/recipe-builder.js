(function ($) {
	'use strict';

	var cfg = window.RSAIP_RECIPE_BUILDER || {};
	var state = {
		sections: [],
		ingredients: [],
		steps: [],
		baseServings: 4
	};

	function uid(prefix) {
		return (prefix || 'k') + '_' + Math.random().toString(36).slice(2, 9);
	}

	function msg(text, isError) {
		var $el = $('#rsaip-rb-status-msg');
		$el.text(text || '').toggleClass('rsaip-error', !!isError);
	}

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function parseInitial() {
		var el = document.getElementById('rsaip-rb-initial');
		if (!el || !el.textContent) {
			return null;
		}
		try {
			return JSON.parse(el.textContent);
		} catch (e) {
			return null;
		}
	}

	function equipmentLines() {
		return ($('#rsaip-rb-equipment').val() || '')
			.split(/\n+/)
			.map(function (s) { return $.trim(s); })
			.filter(Boolean);
	}

	function collectPayload() {
		var total = parseInt($('#rsaip-rb-total').val(), 10) || 0;
		var prep = parseInt($('#rsaip-rb-prep').val(), 10) || 0;
		var cook = parseInt($('#rsaip-rb-cook').val(), 10) || 0;
		if (total <= 0) {
			total = prep + cook;
		}
		return {
			id: parseInt($('#rsaip-rb-id').val(), 10) || 0,
			post_id: parseInt($('#rsaip-rb-post-id').val(), 10) || 0,
			title: $('#rsaip-rb-title').val() || '',
			description: $('#rsaip-rb-description').val() || '',
			servings: parseFloat($('#rsaip-rb-servings').val()) || 4,
			prep_time: prep,
			cook_time: cook,
			total_time: total,
			notes: $('#rsaip-rb-notes').val() || '',
			tips: $('#rsaip-rb-tips').val() || '',
			unit_system: $('#rsaip-rb-unit-system').val() || 'metric',
			status: $('#rsaip-rb-status').val() || 'draft',
			equipment: JSON.stringify(equipmentLines()),
			sections: JSON.stringify(state.sections),
			ingredients: JSON.stringify(state.ingredients),
			steps: JSON.stringify(state.steps)
		};
	}

	function syncFromDom() {
		state.sections = [];
		$('#rsaip-rb-sections .rsaip-rb-group').each(function (i) {
			var $g = $(this);
			state.sections.push({
				client_key: $g.data('key'),
				id: parseInt($g.data('id'), 10) || 0,
				title: $g.find('.rsaip-rb-group-title').val() || '',
				section_type: 'ingredient_group',
				sort_order: i
			});
		});

		state.ingredients = [];
		$('#rsaip-rb-sections .rsaip-rb-ingredient').each(function (i) {
			var $row = $(this);
			state.ingredients.push({
				id: parseInt($row.data('id'), 10) || 0,
				section_key: $row.closest('.rsaip-rb-group').data('key'),
				section_id: parseInt($row.closest('.rsaip-rb-group').data('id'), 10) || 0,
				quantity: parseFloat($row.find('.rsaip-rb-qty').val()) || 0,
				unit: $row.find('.rsaip-rb-unit').val() || '',
				name: $row.find('.rsaip-rb-name').val() || '',
				note: $row.find('.rsaip-rb-note').val() || '',
				sort_order: i
			});
		});

		state.steps = [];
		$('#rsaip-rb-steps .rsaip-rb-step').each(function (i) {
			var $row = $(this);
			state.steps.push({
				id: parseInt($row.data('id'), 10) || 0,
				instruction: $row.find('.rsaip-rb-instruction').val() || '',
				image_url: $row.find('.rsaip-rb-image-url').val() || '',
				sort_order: i
			});
		});
	}

	function ensureDefaultGroup() {
		if (!$('#rsaip-rb-sections .rsaip-rb-group').length) {
			addGroup({ title: (cfg.i18n && cfg.i18n.group) || 'Ingredient group' });
		}
	}

	function addGroup(data) {
		data = data || {};
		var key = data.client_key || uid('sec');
		var $g = $(
			'<div class="rsaip-rb-group" draggable="true">' +
				'<div class="rsaip-rb-group-head">' +
					'<span class="rsaip-rb-drag-handle" title="Drag">⋮⋮</span>' +
					'<input type="text" class="rsaip-rb-group-title" />' +
					'<button type="button" class="button-link-delete rsaip-rb-remove-group">Remove</button>' +
				'</div>' +
				'<ul class="rsaip-rb-ingredients"></ul>' +
			'</div>'
		);
		$g.attr('data-key', key);
		$g.attr('data-id', data.id || 0);
		$g.find('.rsaip-rb-group-title').val(data.title || ((cfg.i18n && cfg.i18n.group) || 'Group'));
		$('#rsaip-rb-sections').append($g);
		bindGroupDnD($g);
		return $g;
	}

	function addIngredient(data, $group) {
		data = data || {};
		ensureDefaultGroup();
		$group = $group || $('#rsaip-rb-sections .rsaip-rb-group').first();
		var $row = $(
			'<li class="rsaip-rb-ingredient" draggable="true">' +
				'<span class="rsaip-rb-drag-handle">⋮⋮</span>' +
				'<input type="number" step="any" class="rsaip-rb-qty" placeholder="Qty" />' +
				'<input type="text" class="rsaip-rb-unit" placeholder="Unit" />' +
				'<input type="text" class="rsaip-rb-name" placeholder="Ingredient" />' +
				'<input type="text" class="rsaip-rb-note" placeholder="Note" />' +
				'<button type="button" class="button-link-delete rsaip-rb-remove-ing">×</button>' +
			'</li>'
		);
		$row.attr('data-id', data.id || 0);
		$row.find('.rsaip-rb-qty').val(data.quantity != null ? data.quantity : '');
		$row.find('.rsaip-rb-unit').val(data.unit || '');
		$row.find('.rsaip-rb-name').val(data.name || '');
		$row.find('.rsaip-rb-note').val(data.note || '');
		$group.find('.rsaip-rb-ingredients').append($row);
		bindItemDnD($row, '.rsaip-rb-ingredient');
		return $row;
	}

	function addStep(data) {
		data = data || {};
		var $row = $(
			'<li class="rsaip-rb-step" draggable="true">' +
				'<span class="rsaip-rb-drag-handle">⋮⋮</span>' +
				'<div class="rsaip-rb-step-body">' +
					'<textarea class="rsaip-rb-instruction" rows="2" placeholder="Instruction"></textarea>' +
					'<input type="hidden" class="rsaip-rb-image-url" />' +
					'<div class="rsaip-rb-step-image-wrap"></div>' +
					'<button type="button" class="button rsaip-rb-pick-image">Step image</button>' +
				'</div>' +
				'<button type="button" class="button-link-delete rsaip-rb-remove-step">×</button>' +
			'</li>'
		);
		$row.attr('data-id', data.id || 0);
		$row.find('.rsaip-rb-instruction').val(data.instruction || '');
		$row.find('.rsaip-rb-image-url').val(data.image_url || '');
		if (data.image_url) {
			$row.find('.rsaip-rb-step-image-wrap').html('<img src="' + String(data.image_url).replace(/"/g, '&quot;') + '" alt="" />');
		}
		$('#rsaip-rb-steps').append($row);
		bindItemDnD($row, '.rsaip-rb-step');
		return $row;
	}

	function bindItemDnD($el, selector) {
		$el.on('dragstart', function (e) {
			$el.addClass('dragging');
			e.originalEvent.dataTransfer.setData('text/plain', 'item');
			e.originalEvent.dataTransfer.effectAllowed = 'move';
		});
		$el.on('dragend', function () {
			$el.removeClass('dragging');
			schedulePreview();
		});
		$el.on('dragover', function (e) {
			e.preventDefault();
			var $dragging = $(selector + '.dragging');
			if (!$dragging.length || $dragging[0] === this) {
				return;
			}
			var rect = this.getBoundingClientRect();
			var before = (e.originalEvent.clientY - rect.top) < rect.height / 2;
			if (before) {
				$dragging.insertBefore($el);
			} else {
				$dragging.insertAfter($el);
			}
		});
	}

	function bindGroupDnD($g) {
		$g.on('dragstart', function (e) {
			if ($(e.target).closest('.rsaip-rb-ingredient').length) {
				return;
			}
			$g.addClass('dragging');
			e.originalEvent.dataTransfer.setData('text/plain', 'group');
		});
		$g.on('dragend', function () {
			$g.removeClass('dragging');
			schedulePreview();
		});
		$g.on('dragover', function (e) {
			e.preventDefault();
			var $dragging = $('.rsaip-rb-group.dragging');
			if ($dragging.length && $dragging[0] !== this) {
				var rect = this.getBoundingClientRect();
				var before = (e.originalEvent.clientY - rect.top) < rect.height / 2;
				if (before) {
					$dragging.insertBefore($g);
				} else {
					$dragging.insertAfter($g);
				}
			}
			var $ing = $('.rsaip-rb-ingredient.dragging');
			if ($ing.length && !$(e.target).closest('.rsaip-rb-ingredient').length) {
				$g.find('.rsaip-rb-ingredients').append($ing);
			}
		});
	}

	function clearBuilder() {
		$('#rsaip-rb-id').val('0');
		$('#rsaip-rb-title').val('');
		$('#rsaip-rb-description').val('');
		$('#rsaip-rb-servings').val('4');
		$('#rsaip-rb-prep').val('0');
		$('#rsaip-rb-cook').val('0');
		$('#rsaip-rb-total').val('0');
		$('#rsaip-rb-notes').val('');
		$('#rsaip-rb-tips').val('');
		$('#rsaip-rb-equipment').val('');
		$('#rsaip-rb-post-id').val('0');
		$('#rsaip-rb-status').val('draft');
		$('#rsaip-rb-unit-system').val('metric');
		$('#rsaip-rb-preview-servings').val('4');
		$('#rsaip-rb-sections').empty();
		$('#rsaip-rb-steps').empty();
		state = { sections: [], ingredients: [], steps: [], baseServings: 4 };
		ensureDefaultGroup();
		$('#rsaip-rb-preview').empty();
	}

	function loadRecipe(recipe) {
		if (!recipe) {
			clearBuilder();
			return;
		}
		$('#rsaip-rb-id').val(recipe.id || 0);
		$('#rsaip-rb-title').val(recipe.title || '');
		$('#rsaip-rb-description').val(recipe.description || '');
		$('#rsaip-rb-servings').val(recipe.servings != null ? recipe.servings : 4);
		$('#rsaip-rb-preview-servings').val(recipe.servings != null ? recipe.servings : 4);
		$('#rsaip-rb-prep').val(recipe.prep_time || 0);
		$('#rsaip-rb-cook').val(recipe.cook_time || 0);
		$('#rsaip-rb-total').val(recipe.total_time || 0);
		$('#rsaip-rb-notes').val(recipe.notes || '');
		$('#rsaip-rb-tips').val(recipe.tips || '');
		$('#rsaip-rb-post-id').val(recipe.post_id || 0);
		$('#rsaip-rb-status').val(recipe.status || 'draft');
		$('#rsaip-rb-unit-system').val(recipe.unit_system || 'metric');
		$('#rsaip-rb-preview-units').val(recipe.unit_system || 'metric');
		$('#rsaip-rb-equipment').val((recipe.equipment || []).join('\n'));
		state.baseServings = parseFloat(recipe.servings) || 4;

		$('#rsaip-rb-sections').empty();
		$('#rsaip-rb-steps').empty();

		var sections = recipe.sections || [];
		var sectionMap = {};
		if (!sections.length) {
			addGroup({ title: (cfg.i18n && cfg.i18n.group) || 'Ingredient group', client_key: 'sec_default' });
			sectionMap[0] = $('#rsaip-rb-sections .rsaip-rb-group').first();
		} else {
			sections.forEach(function (sec) {
				if (sec.section_type === 'steps') {
					return;
				}
				var key = 'sec_' + (sec.id || uid('sec'));
				var $g = addGroup({
					id: sec.id,
					title: sec.title,
					client_key: key
				});
				sectionMap[sec.id] = $g;
			});
		}

		(recipe.ingredients || []).forEach(function (ing) {
			var $g = sectionMap[ing.section_id] || $('#rsaip-rb-sections .rsaip-rb-group').first();
			addIngredient(ing, $g);
		});

		(recipe.steps || []).forEach(function (step) {
			addStep(step);
		});

		schedulePreview();
	}

	var previewTimer = null;
	function schedulePreview() {
		clearTimeout(previewTimer);
		previewTimer = setTimeout(refreshPreview, 350);
	}

	function refreshPreview() {
		syncFromDom();
		var payload = collectPayload();
		payload.preview_servings = parseFloat($('#rsaip-rb-preview-servings').val()) || payload.servings;
		payload.preview_unit_system = $('#rsaip-rb-preview-units').val() || payload.unit_system;
		ajax(cfg.actions.preview, payload).done(function (res) {
			if (res && res.success && res.data && res.data.html) {
				$('#rsaip-rb-preview').html(res.data.html);
			}
		});
	}

	function applyRecipeToList(recipe) {
		var id = String(recipe.id);
		var $li = $('#rsaip-rb-recipe-list .rsaip-rb-open[data-id="' + id + '"]').closest('li');
		if ($li.length) {
			$li.find('.rsaip-rb-open').text(recipe.title || ((cfg.i18n && cfg.i18n.untitled) || 'Untitled'));
			$li.find('.rsaip-sub').text(recipe.status || 'draft');
		} else {
			$('#rsaip-rb-recipe-list').prepend(
				'<li><button type="button" class="button-link rsaip-rb-open" data-id="' + id + '">' +
				$('<div>').text(recipe.title || 'Untitled').html() +
				'</button><span class="rsaip-sub">' + (recipe.status || 'draft') + '</span></li>'
			);
		}
	}

	function bindEvents() {
		$('#rsaip-rb-new').on('click', function () {
			clearBuilder();
			msg('');
		});

		$(document).on('click', '.rsaip-rb-open', function () {
			var id = parseInt($(this).data('id'), 10) || 0;
			if (!id) {
				return;
			}
			ajax(cfg.actions.get, { id: id }).done(function (res) {
				if (res && res.success && res.data && res.data.recipe) {
					loadRecipe(res.data.recipe);
					msg('');
				} else {
					msg((cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			}).fail(function () {
				msg((cfg.i18n && cfg.i18n.error) || 'Error', true);
			});
		});

		$('#rsaip-rb-add-group').on('click', function () {
			addGroup();
			schedulePreview();
		});

		$('#rsaip-rb-add-ingredient').on('click', function () {
			addIngredient();
			schedulePreview();
		});

		$('#rsaip-rb-add-step').on('click', function () {
			addStep();
			schedulePreview();
		});

		$(document).on('click', '.rsaip-rb-remove-group', function () {
			$(this).closest('.rsaip-rb-group').remove();
			ensureDefaultGroup();
			schedulePreview();
		});

		$(document).on('click', '.rsaip-rb-remove-ing', function () {
			$(this).closest('.rsaip-rb-ingredient').remove();
			schedulePreview();
		});

		$(document).on('click', '.rsaip-rb-remove-step', function () {
			$(this).closest('.rsaip-rb-step').remove();
			schedulePreview();
		});

		$(document).on('click', '.rsaip-rb-pick-image', function (e) {
			e.preventDefault();
			var $step = $(this).closest('.rsaip-rb-step');
			if (!wp || !wp.media) {
				return;
			}
			var frame = wp.media({
				title: 'Step image',
				button: { text: 'Use image' },
				multiple: false
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				$step.find('.rsaip-rb-image-url').val(att.url || '');
				$step.find('.rsaip-rb-step-image-wrap').html(att.url ? '<img src="' + att.url + '" alt="" />' : '');
				schedulePreview();
			});
			frame.open();
		});

		$('#rsaip-rb-save').on('click', function () {
			syncFromDom();
			ajax(cfg.actions.save, collectPayload()).done(function (res) {
				if (res && res.success && res.data && res.data.recipe) {
					loadRecipe(res.data.recipe);
					applyRecipeToList(res.data.recipe);
					msg((cfg.i18n && cfg.i18n.saved) || 'Saved.');
				} else {
					msg((res && res.data && res.data.message) || ((cfg.i18n && cfg.i18n.error) || 'Error'), true);
				}
			}).fail(function (xhr) {
				var m = cfg.i18n && cfg.i18n.error;
				try {
					m = xhr.responseJSON.data.message || m;
				} catch (e) { /* ignore */ }
				msg(m, true);
			});
		});

		$('#rsaip-rb-delete').on('click', function () {
			var id = parseInt($('#rsaip-rb-id').val(), 10) || 0;
			if (!id) {
				clearBuilder();
				return;
			}
			if (!window.confirm((cfg.i18n && cfg.i18n.confirmDel) || 'Delete?')) {
				return;
			}
			ajax(cfg.actions.delete, { id: id }).done(function (res) {
				if (res && res.success) {
					$('#rsaip-rb-recipe-list .rsaip-rb-open[data-id="' + id + '"]').closest('li').remove();
					clearBuilder();
					msg((cfg.i18n && cfg.i18n.deleted) || 'Deleted.');
				} else {
					msg((cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			});
		});

		$('#rsaip-rb-refresh-preview').on('click', refreshPreview);

		$('#rsaip-rb-apply-servings').on('click', function () {
			syncFromDom();
			var from = state.baseServings || parseFloat($('#rsaip-rb-servings').val()) || 4;
			var to = parseFloat($('#rsaip-rb-preview-servings').val()) || from;
			ajax(cfg.actions.scaleServings, {
				ingredients: JSON.stringify(state.ingredients),
				from_servings: from,
				to_servings: to
			}).done(function (res) {
				if (res && res.success && res.data && res.data.ingredients) {
					var byIndex = res.data.ingredients;
					$('#rsaip-rb-sections .rsaip-rb-ingredient').each(function (i) {
						if (byIndex[i]) {
							$(this).find('.rsaip-rb-qty').val(byIndex[i].quantity);
						}
					});
					$('#rsaip-rb-servings').val(to);
					state.baseServings = to;
					schedulePreview();
				}
			});
		});

		$('#rsaip-rb-apply-units').on('click', function () {
			syncFromDom();
			var system = $('#rsaip-rb-preview-units').val() || 'metric';
			ajax(cfg.actions.convertUnits, {
				ingredients: JSON.stringify(state.ingredients),
				unit_system: system
			}).done(function (res) {
				if (res && res.success && res.data && res.data.ingredients) {
					var byIndex = res.data.ingredients;
					$('#rsaip-rb-sections .rsaip-rb-ingredient').each(function (i) {
						if (byIndex[i]) {
							$(this).find('.rsaip-rb-qty').val(byIndex[i].quantity);
							$(this).find('.rsaip-rb-unit').val(byIndex[i].unit);
						}
					});
					$('#rsaip-rb-unit-system').val(system);
					schedulePreview();
				}
			});
		});

		$('.rsaip-rb-main, .rsaip-rb-preview-panel').on('input change', 'input, textarea, select', function () {
			if ($(this).is('#rsaip-rb-preview-servings, #rsaip-rb-preview-units')) {
				schedulePreview();
				return;
			}
			schedulePreview();
		});
	}

	$(function () {
		if (!$('#rsaip-rb-sections').length) {
			return;
		}
		bindEvents();
		var initial = parseInitial();
		if (initial && initial.id) {
			loadRecipe(initial);
		} else {
			clearBuilder();
		}
	});
})(jQuery);
