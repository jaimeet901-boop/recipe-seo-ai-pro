/**
 * Professional WordPress post picker for Recipe AI (UI only).
 * Does not alter load/run/apply AJAX contracts.
 */
(function ($) {
	'use strict';

	var cfg = window.RSAIP_RECIPE_AI || {};
	var state = {
		page: 1,
		totalPages: 1,
		activeId: 0,
		activeIndex: -1,
		items: [],
		reqToken: 0
	};
	var searchTimer = null;

	function i18n(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback || key;
	}

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function setSpinner(on) {
		$('#rsaip-rai-picker-spinner').prop('hidden', !on);
	}

	function yesNo(flag) {
		return flag ? i18n('recipeYes', 'Yes') : i18n('recipeNo', 'No');
	}

	function ensurePostOption(id, title) {
		var $sel = $('#rsaip-rai-post');
		if (!$sel.length) return;
		if (!$sel.find('option[value="' + id + '"]').length) {
			$sel.append($('<option/>').val(String(id)).text(title || ('Post #' + id)));
		}
		$sel.val(String(id));
		$('#rsaip-rai-post-id').val(String(id));
	}

	function skeleton(count) {
		var html = '';
		for (var i = 0; i < count; i++) {
			html += '<div class="rsaip-rai-picker-card is-skeleton" aria-hidden="true">' +
				'<div class="rsaip-rai-picker-thumb sk"></div>' +
				'<div class="rsaip-rai-picker-body"><div class="sk sk-line"></div><div class="sk sk-line short"></div><div class="sk sk-line"></div></div>' +
				'</div>';
		}
		return html;
	}

	function renderCard(item, index) {
		var thumb = item.thumbnail
			? '<img src="' + String(item.thumbnail).replace(/"/g, '&quot;') + '" alt="" loading="lazy" width="56" height="56" />'
			: '<span class="rsaip-rai-picker-thumb-ph" aria-hidden="true"></span>';
		var seo = item.seo_score != null ? String(item.seo_score) : '—';
		var opt = item.last_optimized ? String(item.last_optimized) : '—';
		var fav = item.is_favorite ? '★' : '☆';
		var selected = Number(item.id) === Number(state.activeId) ? ' is-selected' : '';
		return $(
			'<div class="rsaip-rai-picker-card' + selected + '" role="option" tabindex="-1" />'
		)
			.attr('data-id', item.id)
			.attr('data-index', index)
			.attr('aria-selected', selected ? 'true' : 'false')
			.append(
				$('<div class="rsaip-rai-picker-thumb"/>').html(thumb),
				$('<div class="rsaip-rai-picker-body"/>').append(
					$('<div class="rsaip-rai-picker-title-row"/>').append(
						$('<strong class="rsaip-rai-picker-title"/>').text(item.title || ('#' + item.id)),
						$('<button type="button" class="button-link rsaip-rai-picker-fav"/>')
							.attr('aria-label', i18n('favorites', 'Favorites'))
							.attr('aria-pressed', item.is_favorite ? 'true' : 'false')
							.text(fav)
					),
					$('<div class="rsaip-rai-picker-meta"/>').html(
						'<span class="status status-' + (item.status || '') + '">' + (item.status_label || item.status || '') + '</span>' +
						'<span>' + (item.date_display || item.date || '') + '</span>' +
						'<span>' + (item.author || '') + '</span>' +
						'<span>' + (item.category || '—') + '</span>'
					),
					$('<div class="rsaip-rai-picker-flags"/>').html(
						'<span>' + (item.word_count || 0) + ' words</span>' +
						'<span class="' + (item.recipe_detected ? 'is-yes' : 'is-no') + '">Recipe: ' + yesNo(!!item.recipe_detected) + '</span>' +
						'<span>SEO: ' + seo + '</span>' +
						'<span>Optimized: ' + opt + '</span>'
					)
				)
			);
	}

	function showEmpty(text) {
		$('#rsaip-rai-picker-results').html(
			$('<div class="rsaip-rai-picker-empty" id="rsaip-rai-picker-empty"/>').text(text)
		);
		$('#rsaip-rai-picker-pager').prop('hidden', true);
	}

	function renderResults(payload) {
		state.items = payload.items || payload.posts || [];
		state.page = payload.page || 1;
		state.totalPages = payload.total_pages || 1;

		var $box = $('#rsaip-rai-picker-results').empty();
		if (!state.items.length) {
			showEmpty(i18n('noPosts', 'No posts found.'));
			return;
		}

		state.items.forEach(function (item, idx) {
			$box.append(renderCard(item, idx));
		});

		if (payload.categories && payload.categories.length) {
			var $cat = $('#rsaip-rai-filter-category');
			var cur = $cat.val();
			var keep = $cat.find('option').first().clone();
			$cat.empty().append(keep);
			payload.categories.forEach(function (c) {
				$cat.append($('<option/>').val(String(c.id)).text(c.name));
			});
			if (cur) $cat.val(cur);
		}

		$('#rsaip-rai-picker-pager').prop('hidden', state.totalPages <= 1);
		$('#rsaip-rai-picker-page-label').text(state.page + ' / ' + state.totalPages);
		$('#rsaip-rai-picker-prev').prop('disabled', state.page <= 1);
		$('#rsaip-rai-picker-next').prop('disabled', state.page >= state.totalPages);
	}

	function search(page) {
		page = page || 1;
		var token = ++state.reqToken;
		var q = ($('#rsaip-rai-post-search').val() || '').trim();

		setSpinner(true);
		$('#rsaip-rai-picker-results').html(skeleton(4));
		$('#rsaip-rai-picker-empty').remove();

		ajax(cfg.actions.searchPosts, {
			q: q,
			page: page,
			per_page: 10,
			status: $('#rsaip-rai-filter-status').val() || '',
			category: $('#rsaip-rai-filter-category').val() || 0,
			date: $('#rsaip-rai-filter-date').val() || '',
			recipe_only: $('#rsaip-rai-filter-recipe').is(':checked') ? 1 : 0
		}).done(function (res) {
			if (token !== state.reqToken) return;
			setSpinner(false);
			if (!res || !res.success) {
				showEmpty(i18n('error', 'Request failed.'));
				return;
			}
			renderResults(res.data || {});
		}).fail(function () {
			if (token !== state.reqToken) return;
			setSpinner(false);
			showEmpty(i18n('error', 'Request failed.'));
		});
	}

	function renderPreview(p) {
		if (!p) {
			$('#rsaip-rai-picker-preview').prop('hidden', true);
			return;
		}
		state.activeId = p.id;
		ensurePostOption(p.id, p.title);

		var $img = $('#rsaip-rai-preview-img');
		var $ph = $('#rsaip-rai-preview-ph');
		if (p.image || p.thumbnail) {
			$img.attr('src', p.image || p.thumbnail).prop('hidden', false);
			$ph.prop('hidden', true);
		} else {
			$img.prop('hidden', true).attr('src', '');
			$ph.prop('hidden', false);
		}

		$('#rsaip-rai-preview-title').text(p.title || ('#' + p.id));
		$('#rsaip-rai-preview-excerpt').text(p.excerpt || '');
		$('#rsaip-rai-preview-meta').html(
			'<li><strong>Recipe detected</strong> ' + yesNo(!!p.recipe_detected) + '</li>' +
			'<li><strong>Schema detected</strong> ' + yesNo(!!p.schema_detected) + '</li>' +
			'<li><strong>Word count</strong> ' + (p.word_count || 0) + '</li>' +
			'<li><strong>Status</strong> ' + (p.status_label || p.status || '') + '</li>' +
			'<li><strong>SEO score</strong> ' + (p.seo_score != null ? p.seo_score : '—') + '</li>'
		);
		$('#rsaip-rai-preview-open').attr('href', p.edit_url || p.view_url || '#');
		$('#rsaip-rai-preview-fav')
			.text(p.is_favorite ? '★' : '☆')
			.attr('aria-pressed', p.is_favorite ? 'true' : 'false');
		$('#rsaip-rai-picker-preview').prop('hidden', false);

		$('#rsaip-rai-picker-results .rsaip-rai-picker-card')
			.removeClass('is-selected')
			.attr('aria-selected', 'false');
		$('#rsaip-rai-picker-results .rsaip-rai-picker-card[data-id="' + p.id + '"]')
			.addClass('is-selected')
			.attr('aria-selected', 'true');
	}

	function loadPreview(id) {
		if (!id) return;
		ajax(cfg.actions.postPreview, { post_id: id }).done(function (res) {
			if (res && res.success && res.data && res.data.preview) {
				renderPreview(res.data.preview);
			}
		});
	}

	function renderShelf(title, items) {
		if (!items || !items.length) return null;
		var $sec = $('<section class="rsaip-rai-shelf"/>');
		$sec.append($('<h4/>').text(title));
		var $row = $('<div class="rsaip-rai-shelf-row"/>');
		items.forEach(function (item) {
			var $chip = $('<button type="button" class="rsaip-rai-shelf-chip"/>')
				.attr('data-id', item.id)
				.append(
					item.thumbnail
						? $('<img loading="lazy" alt="" />').attr('src', item.thumbnail)
						: $('<span class="ph" aria-hidden="true"/>'),
					$('<span/>').text(item.title || ('#' + item.id))
				);
			$row.append($chip);
		});
		$sec.append($row);
		return $sec;
	}

	function loadShelves() {
		if (!cfg.actions.pickerShelves) return;
		ajax(cfg.actions.pickerShelves, {}).done(function (res) {
			if (!res || !res.success || !res.data) return;
			var $box = $('#rsaip-rai-picker-shelves').empty().prop('hidden', false);
			var recent = renderShelf(i18n('recent', 'Recently opened'), res.data.recent || []);
			var opt = renderShelf(i18n('optimized', 'Recently optimized'), res.data.optimized || []);
			var fav = renderShelf(i18n('favorites', 'Favorites'), res.data.favorites || []);
			if (recent) $box.append(recent);
			if (opt) $box.append(opt);
			if (fav) $box.append(fav);
			if (!recent && !opt && !fav) $box.prop('hidden', true);
		});
	}

	function bindPicker() {
		if (!$('#rsaip-rai-post-picker').length) return;

		$('#rsaip-rai-post-search').on('input', function () {
			clearTimeout(searchTimer);
			var q = ($(this).val() || '').trim();
			searchTimer = setTimeout(function () {
				if (q.length === 0 && !$('#rsaip-rai-filter-status').val() && !$('#rsaip-rai-filter-recipe').is(':checked')) {
					showEmpty(i18n('emptySearch', 'Start typing to search posts by title, ID, or slug.'));
					loadShelves();
					return;
				}
				search(1);
			}, 300);
		});

		$('#rsaip-rai-post-search').on('keydown', function (e) {
			var $cards = $('#rsaip-rai-picker-results .rsaip-rai-picker-card');
			if (!$cards.length) {
				if (e.key === 'Enter') {
					e.preventDefault();
					search(1);
				}
				return;
			}
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				state.activeIndex = Math.min($cards.length - 1, state.activeIndex + 1);
				$cards.eq(state.activeIndex).trigger('focus');
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				state.activeIndex = Math.max(0, state.activeIndex - 1);
				$cards.eq(state.activeIndex).trigger('focus');
			} else if (e.key === 'Enter') {
				e.preventDefault();
				if (state.activeIndex >= 0) {
					$cards.eq(state.activeIndex).trigger('click');
				} else {
					search(1);
				}
			}
		});

		$('#rsaip-rai-filter-status, #rsaip-rai-filter-category, #rsaip-rai-filter-date, #rsaip-rai-filter-recipe')
			.on('change', function () { search(1); });

		$('#rsaip-rai-picker-prev').on('click', function () {
			if (state.page > 1) search(state.page - 1);
		});
		$('#rsaip-rai-picker-next').on('click', function () {
			if (state.page < state.totalPages) search(state.page + 1);
		});

		$(document).on('click', '.rsaip-rai-picker-card', function (e) {
			if ($(e.target).closest('.rsaip-rai-picker-fav').length) return;
			var id = parseInt($(this).data('id'), 10) || 0;
			state.activeIndex = parseInt($(this).data('index'), 10) || 0;
			loadPreview(id);
		});

		$(document).on('keydown', '.rsaip-rai-picker-card', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				$(this).trigger('click');
			}
		});

		$(document).on('click', '.rsaip-rai-picker-fav, #rsaip-rai-preview-fav', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var id = parseInt($(this).closest('[data-id]').data('id'), 10) || state.activeId;
			if (!id) return;
			ajax(cfg.actions.pickerFav, { post_id: id }).done(function (res) {
				if (res && res.success) {
					loadShelves();
					if (state.activeId === id) loadPreview(id);
					search(state.page);
				}
			});
		});

		$(document).on('click', '.rsaip-rai-shelf-chip', function () {
			var id = parseInt($(this).data('id'), 10) || 0;
			loadPreview(id);
		});

		$('#rsaip-rai-preview-load').on('click', function () {
			if (!state.activeId) return;
			ensurePostOption(state.activeId, $('#rsaip-rai-preview-title').text());
			$('#rsaip-rai-load').trigger('click');
		});

		$('input[name="rsaip_rai_source"]').on('change', function () {
			if ($(this).val() === 'post') {
				loadShelves();
				showEmpty(i18n('emptySearch', 'Start typing to search posts by title, ID, or slug.'));
			}
		});

		if ($('input[name="rsaip_rai_source"]:checked').val() === 'post') {
			loadShelves();
			if (cfg.postId) {
				ensurePostOption(cfg.postId, 'Post #' + cfg.postId);
				loadPreview(cfg.postId);
			} else {
				showEmpty(i18n('emptySearch', 'Start typing to search posts by title, ID, or slug.'));
			}
		}
	}

	window.RSAIP_RAI_PICKER = {
		bind: bindPicker,
		search: search,
		ensurePostOption: ensurePostOption,
		loadPreview: loadPreview
	};

	$(function () {
		bindPicker();
	});
})(jQuery);
