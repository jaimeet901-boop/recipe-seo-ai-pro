(function ($) {
	'use strict';

	if (typeof RSAIP_AI_HUB === 'undefined') {
		return;
	}

	function toast(msg, isError) {
		if (window.rsaipToast) {
			window.rsaipToast(msg, isError ? 'error' : 'success');
			return;
		}
		window.console && console.log(msg);
	}

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = RSAIP_AI_HUB.nonce;
		return $.post(RSAIP_AI_HUB.ajaxUrl, data);
	}

	function updateDashboard(dash) {
		if (!dash) return;
		var $m = $('#rsaip-ai-hub-metrics');
		$m.find('[data-metric="current_provider"]').text(dash.current_provider || '—');
		$m.find('[data-metric="current_model"]').text(dash.current_model || '—');
		$m.find('[data-metric="average_latency"]').text((dash.average_latency || 0) + ' ms');
		$m.find('[data-metric="today_requests"]').text(dash.today_requests || 0);
		$m.find('[data-metric="token_usage"]').text(dash.token_usage || 0);
		$m.find('[data-metric="estimated_cost"]').text('$' + Number(dash.estimated_cost || 0).toFixed(4));
		$m.find('[data-metric="provider_health"]').text(dash.provider_health || 'unknown');
		$m.find('[data-metric="last_error"]').text(dash.last_error || '—');
	}

	function updateCards(cards) {
		if (!cards || !cards.length) return;
		cards.forEach(function (card) {
			var $card = $('.rsaip-ai-provider-card[data-provider="' + card.id + '"]');
			if (!$card.length) return;
			$card.toggleClass('is-active', !!card.is_active);
			$card.attr('data-status', card.status || 'disconnected');
			$card.find('.rsaip-ai-provider-status').text(
				(card.status || 'disconnected') + (card.is_active ? ' · Active' : '')
			);
			$card.find('.js-card-model').text(card.model || '—');
			$card.find('.js-card-latency').text((card.latency_ms || 0) + ' ms');
			$card.find('.js-card-last-check').text(card.last_check || '—');
		});
	}

	function formPayload($form, provider) {
		var manual = ($form.find('[name="manual_model"]').val() || '').trim();
		var model = ($form.find('[name="model"]').val() || '').trim();
		if (manual) {
			model = manual;
		}
		var data = {
			provider: provider,
			api_key: $form.find('[name="api_key"]').val(),
			endpoint: $form.find('[name="endpoint"]').val(),
			model: model,
			manual_model: manual,
			temperature: $form.find('[name="temperature"]').val(),
			top_p: $form.find('[name="top_p"]').val(),
			max_tokens: $form.find('[name="max_tokens"]').val(),
			timeout: $form.find('[name="timeout"]').val(),
			organization: $form.find('[name="organization"]').val(),
			retry_count: $form.find('[name="retry_count"]').val(),
			custom_headers: $form.find('[name="custom_headers"]').val() || '{}',
			streaming: $form.find('[name="streaming"]').is(':checked') ? 1 : 0,
			activate: $form.find('[name="activate"]').is(':checked') ? 1 : 0
		};
		return data;
	}

	function capBadges(caps) {
		caps = caps || {};
		var bits = [];
		if (caps.chat) bits.push('<span class="rsaip-cap is-chat">Chat</span>');
		if (caps.vision) bits.push('<span class="rsaip-cap is-vision">Vision</span>');
		if (caps.reasoning) bits.push('<span class="rsaip-cap is-reason">Reasoning</span>');
		if (caps.embeddings) bits.push('<span class="rsaip-cap is-embed">Embeddings</span>');
		return bits.join(' ');
	}

	function formatContext(n) {
		if (!n) return '—';
		if (n >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
		if (n >= 1000) return Math.round(n / 1000) + 'k';
		return String(n);
	}

	function normalizeModels(raw) {
		if (!raw || !raw.length) return [];
		return raw.map(function (m) {
			if (typeof m === 'string') {
				return {
					id: m,
					name: m,
					provider: '',
					provider_label: '',
					context_window: null,
					capabilities: { chat: true, vision: false, reasoning: false, embeddings: false },
					deprecated: false
				};
			}
			return m;
		});
	}

	function renderModelList($card) {
		var state = $card.data('modelState') || { models: [], favorites: [], selected: '' };
		var q = String($card.find('.js-ai-model-search').val() || '').toLowerCase();
		var favOnly = $card.find('.js-ai-fav-only').is(':checked');
		var favs = state.favorites || [];
		var $list = $card.find('.js-ai-model-list');
		$list.empty();

		var shown = 0;
		(state.models || []).forEach(function (m) {
			var id = m.id || m.name || '';
			if (!id) return;
			var hay = (id + ' ' + (m.name || '') + ' ' + (m.provider_label || '')).toLowerCase();
			if (q && hay.indexOf(q) === -1) return;
			var isFav = favs.indexOf(id) !== -1;
			if (favOnly && !isFav) return;

			shown += 1;
			var $row = $('<div class="rsaip-ai-model-row" role="option"/>')
				.attr('data-model', id)
				.toggleClass('is-selected', id === state.selected)
				.toggleClass('is-deprecated', !!m.deprecated);

			$row.append(
				$('<div class="rsaip-ai-model-main"/>').append(
					$('<strong class="rsaip-ai-model-id"/>').text(m.name || id),
					$('<span class="rsaip-ai-model-provider"/>').text(m.provider_label || m.provider || $card.data('provider')),
					$('<div class="rsaip-ai-model-caps"/>').html(capBadges(m.capabilities))
				)
			);
			$row.append(
				$('<div class="rsaip-ai-model-side"/>').append(
					$('<span class="rsaip-ai-model-ctx"/>').text(formatContext(m.context_window) + ' ctx'),
					m.deprecated ? $('<span class="rsaip-cap is-deprecated">Deprecated</span>') : null,
					$('<button type="button" class="button-link js-ai-fav-toggle"/>')
						.text(isFav ? '★' : '☆')
						.attr('title', isFav ? 'Unfavorite' : 'Favorite'),
					$('<button type="button" class="button button-small js-ai-set-default"/>').text('Default')
				)
			);
			$list.append($row);
		});

		if (!shown) {
			$list.append($('<div class="rsaip-ai-model-empty"/>').text(RSAIP_AI_HUB.i18n.no_models));
		}
	}

	$(document).on('click', '.js-ai-configure', function () {
		var $card = $(this).closest('.rsaip-ai-provider-card');
		$card.find('.rsaip-ai-provider-config').prop('hidden', false);
	});

	$(document).on('click', '.js-ai-config-close', function () {
		$(this).closest('.rsaip-ai-provider-config').prop('hidden', true);
	});

	$(document).on('submit', '.rsaip-ai-provider-config', function (e) {
		e.preventDefault();
		var $form = $(this);
		var provider = $form.closest('.rsaip-ai-provider-card').data('provider');
		post('rsaip_ai_hub_save', formPayload($form, provider))
			.done(function (res) {
				if (!res || !res.success) {
					toast((res && res.data && res.data.message) || RSAIP_AI_HUB.i18n.error, true);
					return;
				}
				toast(RSAIP_AI_HUB.i18n.saved);
				updateDashboard(res.data.dashboard);
				updateCards(res.data.cards);
				$form.find('[name="api_key"]').val('');
			})
			.fail(function () {
				toast(RSAIP_AI_HUB.i18n.error, true);
			});
	});

	$(document).on('click', '.js-ai-test', function () {
		var $card = $(this).closest('.rsaip-ai-provider-card');
		var provider = $card.data('provider');
		var $out = $card.find('.rsaip-ai-test-result');
		$out.prop('hidden', false).text(RSAIP_AI_HUB.i18n.testing);

		post('rsaip_ai_hub_test', { provider: provider })
			.done(function (res) {
				if (!res || !res.success) {
					var msg = (res && res.data && res.data.message) || RSAIP_AI_HUB.i18n.error;
					$out.text(msg);
					updateCards(res && res.data && res.data.cards);
					toast(msg, true);
					return;
				}
				$out.text(JSON.stringify(res.data.test, null, 2));
				updateDashboard(res.data.dashboard);
				updateCards(res.data.cards);
			})
			.fail(function (xhr) {
				var msg = RSAIP_AI_HUB.i18n.error;
				try {
					msg = xhr.responseJSON.data.message || msg;
				} catch (err) {}
				$out.text(msg);
				toast(msg, true);
			});
	});

	$(document).on('click', '.js-ai-disconnect', function () {
		var $card = $(this).closest('.rsaip-ai-provider-card');
		var provider = $card.data('provider');
		post('rsaip_ai_hub_disconnect', { provider: provider })
			.done(function (res) {
				if (!res || !res.success) {
					toast(RSAIP_AI_HUB.i18n.error, true);
					return;
				}
				toast(RSAIP_AI_HUB.i18n.disconnected);
				updateDashboard(res.data.dashboard);
				updateCards(res.data.cards);
			});
	});

	$(document).on('click', '.js-ai-activate', function () {
		var provider = $(this).closest('.rsaip-ai-provider-card').data('provider');
		post('rsaip_ai_hub_activate', { provider: provider })
			.done(function (res) {
				if (!res || !res.success) {
					toast(RSAIP_AI_HUB.i18n.error, true);
					return;
				}
				toast(RSAIP_AI_HUB.i18n.activated);
				updateDashboard(res.data.dashboard);
				updateCards(res.data.cards);
			});
	});

	function loadModels($card, refresh) {
		var provider = $card.data('provider');
		var $browser = $card.find('.rsaip-ai-model-browser');
		var $err = $card.find('.js-ai-model-error');
		var $meta = $card.find('.js-ai-model-meta');

		$browser.prop('hidden', false);
		$err.prop('hidden', true).text('');
		$meta.text(RSAIP_AI_HUB.i18n.fetching);

		post('rsaip_ai_hub_models', { provider: provider, refresh: refresh ? 1 : 0 })
			.done(function (res) {
				if (!res || !res.success) {
					var failMsg = (res && res.data && res.data.message) || RSAIP_AI_HUB.i18n.error;
					$err.prop('hidden', false).text(failMsg);
					$card.find('.rsaip-ai-manual-entry').prop('hidden', false);
					$meta.text('');
					toast(failMsg, true);
					return;
				}

				var data = res.data || {};
				var models = normalizeModels(data.models);
				var flat = data.models_flat || models.map(function (m) { return m.id; });

				$card.data('modelState', {
					models: models,
					favorites: data.favorites || [],
					selected: data.selected || $card.find('.js-ai-selected-model').val() || ''
				});

				var $datalist = $card.find('datalist');
				$datalist.empty();
				flat.forEach(function (id) {
					$datalist.append($('<option/>').attr('value', id));
				});

				if (data.error) {
					$err.prop('hidden', false).text(data.error);
				}
				if (data.manual_required) {
					$card.find('.rsaip-ai-manual-entry').prop('hidden', false);
				}

				var src = data.discovery === 'api' ? 'API' : (data.discovery === 'fallback' ? 'fallback' : 'manual');
				var cacheNote = data.cached ? ' · cached' : '';
				$meta.text(models.length + ' models · ' + src + cacheNote);
				renderModelList($card);

				if (!models.length) {
					toast(RSAIP_AI_HUB.i18n.no_models, true);
				} else {
					toast(RSAIP_AI_HUB.i18n.loaded + ' (' + models.length + ')');
				}
			})
			.fail(function (xhr) {
				var msg = RSAIP_AI_HUB.i18n.error;
				try {
					msg = xhr.responseJSON.data.message || msg;
				} catch (err) {}
				$err.prop('hidden', false).text(msg);
				$card.find('.rsaip-ai-manual-entry').prop('hidden', false);
				$meta.text('');
				toast(msg, true);
			});
	}

	$(document).on('click', '.js-ai-fetch-models', function () {
		var $card = $(this).closest('.rsaip-ai-provider-card');
		$card.find('.rsaip-ai-provider-config').prop('hidden', false);
		loadModels($card, false);
	});

	$(document).on('click', '.js-ai-refresh-models', function () {
		var $card = $(this).closest('.rsaip-ai-provider-card');
		$card.find('.rsaip-ai-provider-config').prop('hidden', false);
		loadModels($card, true);
	});

	$(document).on('click', '.js-ai-toggle-manual', function () {
		var $card = $(this).closest('.rsaip-ai-provider-card');
		$card.find('.rsaip-ai-provider-config').prop('hidden', false);
		var $manual = $card.find('.rsaip-ai-manual-entry');
		var show = $manual.prop('hidden');
		$manual.prop('hidden', !show);
		if (show) {
			$card.find('.js-ai-selected-model').prop('readonly', false);
			$card.find('.js-ai-manual-model').trigger('focus');
			toast(RSAIP_AI_HUB.i18n.manual_on);
		} else {
			$card.find('.js-ai-selected-model').prop('readonly', true);
		}
	});

	$(document).on('input', '.js-ai-manual-model', function () {
		var $card = $(this).closest('.rsaip-ai-provider-card');
		$card.find('.js-ai-selected-model').val($(this).val());
	});

	$(document).on('input', '.js-ai-model-search', function () {
		renderModelList($(this).closest('.rsaip-ai-provider-card'));
	});

	$(document).on('change', '.js-ai-fav-only', function () {
		renderModelList($(this).closest('.rsaip-ai-provider-card'));
	});

	$(document).on('click', '.js-ai-set-default', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $card = $(this).closest('.rsaip-ai-provider-card');
		var model = $(this).closest('.rsaip-ai-model-row').data('model');
		var provider = $card.data('provider');
		$card.find('.js-ai-selected-model').val(model);
		$card.find('[name="manual_model"]').val('');
		$card.find('[name="activate"]').prop('checked', true);

		var state = $card.data('modelState') || {};
		state.selected = model;
		$card.data('modelState', state);
		renderModelList($card);

		post('rsaip_ai_hub_save', {
			provider: provider,
			model: model,
			activate: 1
		}).done(function (res) {
			if (!res || !res.success) {
				toast((res && res.data && res.data.message) || RSAIP_AI_HUB.i18n.error, true);
				return;
			}
			toast(RSAIP_AI_HUB.i18n.default_set);
			updateDashboard(res.data.dashboard);
			updateCards(res.data.cards);
		});
	});

	$(document).on('click', '.js-ai-fav-toggle', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $card = $(this).closest('.rsaip-ai-provider-card');
		var model = $(this).closest('.rsaip-ai-model-row').data('model');
		var state = $card.data('modelState') || { favorites: [] };
		var favs = state.favorites || [];
		var isFav = favs.indexOf(model) !== -1;
		post('rsaip_ai_hub_favorite', { model: model, add: isFav ? 0 : 1 })
			.done(function (res) {
				if (!res || !res.success) {
					toast(RSAIP_AI_HUB.i18n.error, true);
					return;
				}
				state.favorites = res.data.favorites || [];
				$card.data('modelState', state);
				renderModelList($card);
				toast(isFav ? RSAIP_AI_HUB.i18n.unfavorited : RSAIP_AI_HUB.i18n.favorited);
			});
	});

	$(document).on('click', '.rsaip-ai-model-row', function (e) {
		if ($(e.target).closest('button').length) return;
		var $card = $(this).closest('.rsaip-ai-provider-card');
		var model = $(this).data('model');
		$card.find('.js-ai-selected-model').val(model);
		var state = $card.data('modelState') || {};
		state.selected = model;
		$card.data('modelState', state);
		renderModelList($card);
	});

	$(document).on('change', '[name="endpoint"]', function () {
		var endpoint = $(this).val();
		if (!endpoint) return;
		post('rsaip_ai_hub_detect', { endpoint: endpoint }).done(function (res) {
			if (res && res.success && res.data.provider) {
				toast('Detected: ' + (res.data.label || res.data.provider));
			}
		});
	});

	$('#rsaip-ai-failover-save').on('click', function () {
		var chain = ($('#rsaip-ai-failover-chain').val() || '')
			.split(',')
			.map(function (s) { return s.trim(); })
			.filter(Boolean);
		post('rsaip_ai_hub_failover', {
			failover_enabled: $('#rsaip-ai-failover-enabled').is(':checked') ? 1 : 0,
			failover: chain.join(',')
		}).done(function (res) {
			if (!res || !res.success) {
				toast(RSAIP_AI_HUB.i18n.error, true);
				return;
			}
			toast(RSAIP_AI_HUB.i18n.saved);
			updateDashboard(res.data.dashboard);
		});
	});
})(jQuery);
