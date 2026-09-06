(function ($) {
	function rsaipAjax(action, data, method) {
		var payload = $.extend(
			{
				action: action,
				nonce: RSAIP.nonce
			},
			data || {}
		);
		return $.ajax({
			url: RSAIP.ajaxUrl,
			method: method || "POST",
			dataType: "json",
			data: payload
		});
	}

	function setStatus(text) {
		var $s = $("#rsaip-status");
		if (!$s.length) {
			$(".rsaip-actions").first().append('<span id="rsaip-status" class="rsaip-sub" role="status" aria-live="polite"></span>');
			$s = $("#rsaip-status");
		}
		$s.text(text || "");
	}

	/**
	 * Safely render server-sanitized article HTML without jQuery .html() / innerHTML injection of scripts.
	 * Whitelists common article tags and strips event handlers / javascript: URLs.
	 */
	function rsaipSafeAppendArticleHtml($target, html) {
		var allowed = {
			P: 1, H2: 1, H3: 1, UL: 1, OL: 1, LI: 1, STRONG: 1, EM: 1, B: 1, I: 1, BR: 1,
			BLOCKQUOTE: 1, A: 1, IMG: 1, FIGURE: 1, FIGCAPTION: 1, DIV: 1, SPAN: 1, HR: 1
		};
		var wrapper = document.createElement("div");
		var parsed;
		try {
			parsed = new DOMParser().parseFromString("<div id='rsaip-root'>" + String(html || "") + "</div>", "text/html");
		} catch (e) {
			$target.append($("<div/>", { "class": "rsaip-note", text: "Could not render article HTML safely." }));
			return;
		}
		var root = parsed.getElementById("rsaip-root");
		if (!root) {
			$target.append($("<div/>", { "class": "rsaip-note", text: "Empty article content." }));
			return;
		}

		function cleanNode(node) {
			if (node.nodeType === 3) {
				return document.createTextNode(node.nodeValue || "");
			}
			if (node.nodeType !== 1) {
				return null;
			}
			var tag = String(node.tagName || "").toUpperCase();
			if (!allowed[tag]) {
				var frag = document.createDocumentFragment();
				Array.prototype.forEach.call(node.childNodes || [], function (child) {
					var c = cleanNode(child);
					if (c) {
						frag.appendChild(c);
					}
				});
				return frag;
			}
			var el = document.createElement(tag.toLowerCase());
			Array.prototype.forEach.call(node.attributes || [], function (attr) {
				var name = String(attr.name || "").toLowerCase();
				var val = String(attr.value || "");
				if (name.indexOf("on") === 0) {
					return;
				}
				if (name === "href" || name === "src") {
					if (/^\s*javascript:/i.test(val) || /^\s*data:text\/html/i.test(val)) {
						return;
					}
					if (name === "href" && !/^\s*https?:\/\//i.test(val) && val.charAt(0) !== "#" && val.charAt(0) !== "/") {
						return;
					}
					if (name === "src" && !/^\s*https?:\/\//i.test(val)) {
						return;
					}
					el.setAttribute(name, val);
					return;
				}
				if (name === "alt" || name === "title" || name === "class") {
					el.setAttribute(name, val);
				}
			});
			Array.prototype.forEach.call(node.childNodes || [], function (child) {
				var c = cleanNode(child);
				if (c) {
					el.appendChild(c);
				}
			});
			return el;
		}

		Array.prototype.forEach.call(root.childNodes || [], function (child) {
			var cleaned = cleanNode(child);
			if (cleaned) {
				wrapper.appendChild(cleaned);
			}
		});
		$target.append(wrapper);
	}

	function rsaipRenderArticlePreview(data) {
		var $box = $("#rsaip-new-article-preview");
		var $draftBtn = $("#rsaip-new-article-create-draft-btn");
		$box.empty();
		$("#rsaip-new-article-proposal-id").val("");
		$("#rsaip-new-article-fingerprint").val("");
		$draftBtn.prop("disabled", true).attr("hidden", true);
		$("#rsaip-new-article-draft-status").text("");

		if (!data || !data.ok) {
			$box.append($("<div/>", { "class": "rsaip-note", text: (data && data.message) || "Preview failed." }));
			return;
		}

		$("#rsaip-new-article-proposal-id").val(String(data.proposal_id || ""));
		$("#rsaip-new-article-fingerprint").val(String(data.fingerprint || ""));
		if (data.proposal_id && data.fingerprint) {
			$draftBtn.prop("disabled", false).removeAttr("hidden");
		}

		$box.append($("<h3/>", { text: "Article Preview" }));
		$box.append($("<div/>", { "class": "rsaip-sub", text: "Preview only until you click Create Draft (NEW Generator). Expires: " + String(data.expires_at || "—") }));
		$box.append($("<div/>", { "class": "rsaip-sub", text: "Proposal ID: " + String(data.proposal_id || "—") }));

		function section(label, value) {
			$box.append($("<div/>", { "class": "rsaip-sub" }).append($("<strong/>", { text: label + ": " })).append(document.createTextNode(String(value || "—"))));
		}

		section("Title", data.title);
		section("Excerpt", data.excerpt);
		section("Primary Focus Keyword", data.primary_focus_keyword);
		section("Secondary Keywords", (data.secondary_keywords || []).join(", "));
		section("Entities", (data.entities || []).join(", "));
		section("Search Intent", data.search_intent);
		section("Recipe Entity", data.recipe_entity || data.recipe_name);
		section("Meta Description", data.meta_description);
		section("Template", data.template);
		section("Word Count (requested / actual / in band)", String(data.requested_word_count || "—") + " / " + String(data.actual_word_count || "—") + " / " + String(!!data.word_count_in_band));

		if (data.content_gaps && data.content_gaps.length) {
			section("Content Gaps (recommendations)", data.content_gaps.join("; "));
		}

		$box.append($("<h4/>", { text: "Recipe Facts" }));
		var facts = data.recipe_facts || {};
		var known = $("<ul/>");
		var unavailable = $("<ul/>");
		Object.keys(facts).forEach(function (key) {
			var row = facts[key] || {};
			var status = String(row.status || "unavailable");
			var val = row.value;
			if (status === "known") {
				var textVal = Array.isArray(val) ? val.join("; ") : String(val || "");
				known.append($("<li/>", { text: key + ": " + textVal }));
			} else {
				unavailable.append($("<li/>", { text: key + ": Not available from the source data." }));
			}
		});
		$box.append($("<div/>", { "class": "rsaip-sub", text: "Known:" }));
		$box.append(known.children().length ? known : $("<div/>", { "class": "rsaip-sub", text: "(none)" }));
		$box.append($("<div/>", { "class": "rsaip-sub", text: "Unavailable:" }));
		$box.append(unavailable.children().length ? unavailable : $("<div/>", { "class": "rsaip-sub", text: "(none)" }));

		$box.append($("<h4/>", { text: "Headings" }));
		var $heads = $("<ul/>");
		(data.headings || []).forEach(function (h) {
			$heads.append($("<li/>", { text: String((h && h.level) || "h2").toUpperCase() + ": " + String((h && h.text) || "") }));
		});
		$box.append($heads.children().length ? $heads : $("<div/>", { "class": "rsaip-sub", text: "(none)" }));

		$box.append($("<h4/>", { text: "Article Content" }));
		var $content = $("<div/>", { "class": "rsaip-article-preview-content" });
		rsaipSafeAppendArticleHtml($content, data.content_html || "");
		$box.append($content);

		$box.append($("<h4/>", { text: "FAQ" }));
		var $faq = $("<ol/>");
		(data.faq || []).forEach(function (row) {
			$faq.append($("<li/>").append($("<div/>", { text: "Q: " + String((row && row.q) || "") })).append($("<div/>", { text: "A: " + String((row && row.a) || "") })));
		});
		$box.append($faq.children().length ? $faq : $("<div/>", { "class": "rsaip-sub", text: "(none)" }));

		$box.append($("<h4/>", { text: "Image Plans" }));
		var $plans = $("<ul/>");
		(data.image_plans || []).forEach(function (p) {
			$plans.append($("<li/>", { text: String((p && p.placeholder) || "") + " — " + String((p && p.alt) || "") + (p && p.note ? " (" + p.note + ")" : "") }));
		});
		$box.append($plans.children().length ? $plans : $("<div/>", { "class": "rsaip-sub", text: "(none)" }));

		// Never claim Rank Math scores.
		$box.append($("<div/>", { "class": "rsaip-sub", text: "Rank Math scoring is not simulated. This preview shows proposal data only." }));
	}

	function getAiPostId($statusTarget) {
		var postId = $(".rsaip-input[data-key='ai_post_id']").val();
		if (!postId) {
			if ($statusTarget && $statusTarget.length) {
				$statusTarget.text("Please enter a valid Post ID first.");
			}
			setStatus("Post ID required");
			return "";
		}
		return postId;
	}

	function formatProposalValue(value) {
		if (value === null || typeof value === "undefined") {
			return "—";
		}
		if (Object.prototype.toString.call(value) === "[object Array]") {
			return value.join(", ");
		}
		var text = String(value);
		return text === "" ? "—" : text;
	}

	function escHtml(text) {
		return $("<div>").text(String(text == null ? "" : text)).html();
	}

	/**
	 * Milestone 5B/5D: read-only SEO proposal display. Does not fill Apply inputs or tickets.
	 */
	function renderSeoOptimizationAnalysis(data) {
		data = data || {};
		var html = "";
		html += '<div class="rsaip-sub">Source: OpenAI SEO Optimization (read-only · Apply not connected)</div>';
		if (data.recipe_schema) {
			var rs = data.recipe_schema || {};
			html += "<div><strong>Recipe Schema:</strong> " + escHtml(rs.label || rs.status || "—");
			if (rs.source) {
				html += " <span class=\"rsaip-sub\">(source: " + escHtml(rs.source) + ")</span>";
			}
			html += "</div>";
			if (rs.details) {
				html += "<div class=\"rsaip-sub\">" + escHtml(rs.details) + "</div>";
			}
			if (String(rs.status || "") === "missing") {
				html += '<div class="rsaip-sub">Create/Repair Recipe Schema is not enabled in this milestone (detection only). Analyze never writes schema.</div>';
			} else if (String(rs.status || "") === "valid_recipe") {
				html += '<div class="rsaip-sub">Valid Recipe Schema present — leave untouched.</div>';
			} else if (String(rs.status || "") === "external_or_unknown" || String(rs.status || "") === "multiple") {
				html += '<div class="rsaip-sub">Do not create another Recipe Schema.</div>';
			}
		}
		html += "<div><strong>Topic / Recipe:</strong> " + escHtml(data.topic || "") ;
		if (data.recipe_name) {
			html += " <span class=\"rsaip-sub\">(" + escHtml(data.recipe_name) + ")</span>";
		}
		html += "</div>";
		if (data.canonical_recipe_entity) {
			html += "<div><strong>Canonical recipe entity:</strong> " + escHtml(data.canonical_recipe_entity) + "</div>";
		}
		html += "<div><strong>Search intent:</strong> " + escHtml(data.search_intent || "—") + "</div>";
		html += "<div><strong>Primary focus keyword:</strong> " + escHtml(data.primary_focus_keyword || "—") + "</div>";
		html += "<div><strong>Secondary keywords:</strong> " + escHtml(formatProposalValue(data.secondary_keywords || [])) + "</div>";
		html += "<div><strong>Entities:</strong> " + escHtml(formatProposalValue(data.entities || [])) + " <span class=\"rsaip-sub\">(display only)</span></div>";
		if (data.recipe_ingredients && data.recipe_ingredients.length) {
			html += "<div><strong>Known ingredients (server):</strong> " + escHtml(formatProposalValue(data.recipe_ingredients)) + "</div>";
		}
		html += "<div><strong>Recommended title:</strong> " + escHtml(data.recommended_title || "—") + "</div>";

		var titles = data.seo_title_suggestions || [];
		if (titles.length) {
			html += "<div><strong>Title suggestions:</strong><ol class=\"rsaip-list\">";
			titles.forEach(function (t) {
				html += "<li>" + escHtml(t) + "</li>";
			});
			html += "</ol></div>";
		}

		html += "<div><strong>Meta description:</strong> " + escHtml(data.meta_description || "—") + "</div>";

		var issues = data.seo_issues || [];
		if (issues.length) {
			html += "<div><strong>SEO issues:</strong><ul class=\"rsaip-list\">";
			issues.forEach(function (issue) {
				html += "<li>" + escHtml((issue && issue.code) || "issue") +
					" [" + escHtml((issue && issue.category) || "other") +
					" · " + escHtml((issue && issue.severity) || "low") + "]</li>";
			});
			html += "</ul></div>";
		} else {
			html += "<div><strong>SEO issues:</strong> None reported</div>";
		}

		if (typeof data.confidence === "number") {
			html += "<div><strong>Confidence:</strong> " + escHtml(String(data.confidence)) + "</div>";
		}
		if (data.reasoning_summary) {
			html += "<div><strong>Reasoning:</strong> " + escHtml(data.reasoning_summary) + "</div>";
		}

		html += '<div class="rsaip-sub">Analyze does not modify the post, meta, recipe cards, or schema. Heading / FAQ / ALT / link / gap suggestions are listed below (suggestions only — no changes are applied). Generate Titles / Keywords / Meta can reuse this proposal when still fresh.</div>';
		return html;
	}

	function suggestionsOnlyBanner() {
		return '<div class="rsaip-sub"><em>Suggestions only — no changes are applied.</em></div>';
	}

	function renderHeadingSuggestions(data) {
		var items = (data && data.heading_suggestions) || [];
		var html = "<strong>Heading Suggestions</strong>" + suggestionsOnlyBanner();
		if (!items.length) {
			html += '<div class="rsaip-sub">None in this proposal.</div>';
			return html;
		}
		html += "<ul class=\"rsaip-list\">";
		items.forEach(function (h) {
			html +=
				"<li><strong>" +
				escHtml(String((h && h.level) || "").toUpperCase()) +
				"</strong> " +
				escHtml((h && h.text) || "") +
				'<div class="rsaip-sub">' +
				escHtml((h && h.rationale) || "") +
				"</div></li>";
		});
		html += "</ul>";
		return html;
	}

	function renderFaqSuggestions(data) {
		var items = (data && data.faq_suggestions) || [];
		var html = "<strong>FAQ Suggestions</strong>" + suggestionsOnlyBanner();
		if (!items.length) {
			html += '<div class="rsaip-sub">None in this proposal.</div>';
			return html;
		}
		html += "<ol class=\"rsaip-list\">";
		items.forEach(function (f) {
			html +=
				"<li><strong>Q:</strong> " +
				escHtml((f && f.q) || "") +
				"<br/><strong>A:</strong> " +
				escHtml((f && f.a) || "") +
				"</li>";
		});
		html += "</ol>";
		return html;
	}

	function renderAltSuggestions(data) {
		var items = (data && data.image_alt_suggestions) || [];
		var html = "<strong>Image ALT Suggestions</strong>" + suggestionsOnlyBanner();
		if (!items.length) {
			html += '<div class="rsaip-sub">None in this proposal.</div>';
			return html;
		}
		html += "<ul class=\"rsaip-list\">";
		items.forEach(function (a) {
			html +=
				"<li><strong>" +
				escHtml((a && a.target) || "") +
				":</strong> " +
				escHtml((a && a.alt) || "") +
				"</li>";
		});
		html += "</ul>";
		return html;
	}

	function renderLinkSuggestions(data) {
		var items = (data && data.internal_link_suggestions) || [];
		var html = "<strong>Internal Link Suggestions</strong>" + suggestionsOnlyBanner();
		if (!items.length) {
			html += '<div class="rsaip-sub">None in this proposal.</div>';
			return html;
		}
		html += "<ul class=\"rsaip-list\">";
		items.forEach(function (l) {
			html +=
				"<li><strong>Anchor:</strong> " +
				escHtml((l && l.anchor) || "") +
				"<br/><strong>Target hint:</strong> " +
				escHtml((l && l.target_hint) || "") +
				'<div class="rsaip-sub">' +
				escHtml((l && l.reason) || "") +
				"</div></li>";
		});
		html += "</ul>";
		return html;
	}

	function renderContentGaps(data) {
		var gaps = (data && data.content_gaps) || [];
		var html = "<strong>Content Gaps</strong>" + suggestionsOnlyBanner();
		if (!gaps.length) {
			html += '<div class="rsaip-sub">None reported (or none observable from context).</div>';
			return html;
		}
		html += "<ul class=\"rsaip-list\">";
		gaps.forEach(function (g) {
			html += "<li>" + escHtml(g) + "</li>";
		});
		html += "</ul>";
		return html;
	}

	function renderSeoRecommendationsReadonly(data) {
		var recs = (data && data.recommendations) || [];
		var html = "<strong>SEO Recommendations</strong>" + suggestionsOnlyBanner();
		html +=
			'<div class="rsaip-sub">Only title / meta_description / keywords may use existing Preview → Apply. Headings, FAQ, ALT, links, content, and schema have no Apply.</div>';
		if (!recs.length) {
			html += '<div class="rsaip-sub">None in this proposal.</div>';
			return html;
		}
		html += "<ul class=\"rsaip-list\">";
		recs.forEach(function (rec) {
			html +=
				"<li>" +
				escHtml((rec && rec.action) || "") +
				' <span class="rsaip-sub">(via ' +
				escHtml((rec && rec.applies_via) || "none") +
				")</span>";
			if (rec && rec.note) {
				html += " — " + escHtml(rec.note);
			}
			html += "</li>";
		});
		html += "</ul>";
		return html;
	}

	function populateReadonlySuggestions(data) {
		$("#rsaip-ai-suggest-headings").html(renderHeadingSuggestions(data));
		$("#rsaip-ai-suggest-faq").html(renderFaqSuggestions(data));
		$("#rsaip-ai-suggest-alts").html(renderAltSuggestions(data));
		$("#rsaip-ai-suggest-links").html(renderLinkSuggestions(data));
		$("#rsaip-ai-suggest-gaps").html(renderContentGaps(data));
		$("#rsaip-ai-suggest-recs").html(renderSeoRecommendationsReadonly(data));
	}

	/**
	 * Milestone 5C: sync #rsaip-ai-keywords from primary + checked secondaries only.
	 * Entities are never included. Rank Math Apply uses this CSV via existing Propose path.
	 */
	function syncKeywordsApplyCandidate() {
		var primary = String($("#rsaip-ai-kw-primary").data("value") || "").trim();
		var selected = [];
		$("#rsaip-ai-keywords-picker .rsaip-kw-secondary:checked").each(function () {
			var v = String($(this).val() || "").trim();
			if (v) {
				selected.push(v);
			}
		});
		var list = [];
		if (primary) {
			list.push(primary);
		}
		selected.forEach(function (kw) {
			if (list.indexOf(kw) === -1) {
				list.push(kw);
			}
		});
		$("#rsaip-ai-keywords").val(list.join(", "));
		clearProposalTicket("keywords");
	}

	function renderKeywordsPicker(data) {
		data = data || {};
		var primary = String(data.primary_focus_keyword || "").trim();
		var secondaries = data.secondary_keywords || [];
		var entities = data.entities || [];
		var html = "";
		html += '<div class="rsaip-kw-block"><strong>PRIMARY FOCUS KEYWORD</strong>';
		if (primary) {
			html +=
				'<div class="rsaip-kw-primary" id="rsaip-ai-kw-primary" data-value="' +
				escHtml(primary) +
				'">' +
				escHtml(primary) +
				"</div>";
		} else {
			html += '<div class="rsaip-sub">No primary keyword in proposal.</div>';
		}
		html += "</div>";

		html += '<div class="rsaip-kw-block"><strong>SECONDARY KEYWORDS</strong> <span class="rsaip-sub">(optional — check to include in Apply)</span>';
		if (secondaries.length) {
			html += "<ul class=\"rsaip-list rsaip-kw-secondary-list\">";
			secondaries.forEach(function (kw, idx) {
				var id = "rsaip-kw-sec-" + idx;
				html +=
					"<li><label for=\"" +
					id +
					'"><input type="checkbox" class="rsaip-kw-secondary" id="' +
					id +
					'" value="' +
					escHtml(kw) +
					'"/> ' +
					escHtml(kw) +
					"</label></li>";
			});
			html += "</ul>";
		} else {
			html += '<div class="rsaip-sub">None suggested.</div>';
		}
		html += "</div>";

		if (entities.length) {
			html +=
				'<div class="rsaip-kw-block"><strong>ENTITIES</strong> <span class="rsaip-sub">(display only — not applied as keywords)</span><div>' +
				escHtml(formatProposalValue(entities)) +
				"</div></div>";
		}

		html +=
			'<div class="rsaip-sub">Recipe: ' +
			escHtml(data.recipe_name || data.topic || "—") +
			". Owner remains server-controlled (Rank Math &gt; Yoast &gt; RSAIP).</div>";
		return html;
	}

	function renderTitleSuggestions(data) {
		data = data || {};
		var recommended = String(data.recommended_title || "").trim();
		var titles = data.titles || data.seo_title_suggestions || [];
		var html = "";
		if (data.recipe_name || data.topic) {
			html +=
				'<div class="rsaip-sub">Recipe entity: ' +
				escHtml(data.recipe_name || data.topic) +
				"</div>";
		}
		if (recommended) {
			html +=
				"<div><strong>Recommended:</strong> <button type=\"button\" class=\"button-link rsaip-fill-title\" data-title=\"" +
				escHtml(recommended) +
				'">' +
				escHtml(recommended) +
				"</button></div>";
		}
		html += "<div><strong>Alternatives:</strong><ol class='rsaip-list'>";
		titles.forEach(function (t) {
			if (recommended && String(t) === recommended) {
				return;
			}
			html +=
				"<li><button type='button' class='button-link rsaip-fill-title' data-title=\"" +
				escHtml(t) +
				'">' +
				escHtml(t) +
				"</button></li>";
		});
		html += "</ol>";
		html += "<div class='rsaip-sub'>Source: " + escHtml(data.source || "—") + "</div>";
		return html;
	}

	function isSafeArticleModeOn() {
		return String((window.RSAIP && RSAIP.neverModifyPosts) || "1") !== "0";
	}

	function isFixWithAiBlocked() {
		return String((window.RSAIP && RSAIP.fixWithAiBlocked) || "1") !== "0";
	}

	var proposalTickets = {
		title: null,
		meta_description: null,
		keywords: null
	};

	function clearProposalTicket(type) {
		if (type && Object.prototype.hasOwnProperty.call(proposalTickets, type)) {
			proposalTickets[type] = null;
		}
		syncApplyButtons();
	}

	function clearAllProposalTickets() {
		proposalTickets.title = null;
		proposalTickets.meta_description = null;
		proposalTickets.keywords = null;
		syncApplyButtons();
	}

	function storeProposalTicket(type, data, postId, sourceValue) {
		proposalTickets[type] = null;
		if (
			data &&
			data.ok &&
			data.apply_allowed &&
			data.proposal_ticket &&
			!isSafeArticleModeOn()
		) {
			proposalTickets[type] = {
				ticket: String(data.proposal_ticket),
				postId: String(postId || ""),
				sourceValue: String(sourceValue || "")
			};
		}
		syncApplyButtons();
	}

	function syncApplyButtons() {
		var postId = String($(".rsaip-input[data-key='ai_post_id']").val() || "");
		$(".rsaip-apply-btn").each(function () {
			var $btn = $(this);
			var type = String($btn.data("apply-type") || "");
			var ticket = proposalTickets[type];
			var allow =
				!isSafeArticleModeOn() &&
				ticket &&
				ticket.ticket &&
				ticket.postId === postId;
			$btn.prop("disabled", !allow);
		});
		$("[data-action='rsaip_ai_fix_post']").prop("disabled", true);
		$("[data-action='rsaip_ai_bulk_apply_meta_desc']").prop("disabled", true);
	}

	function renderProposalPreview(data) {
		if (!data || !data.ok) {
			return (
				'<div class="rsaip-note">Preview failed' +
				(data && data.message ? ": " + $("<div>").text(String(data.message)).html() : "") +
				"</div>"
			);
		}
		var safeMode = data.safe_mode ? "ON" : "OFF";
		var applyAllowed = data.apply_allowed ? "yes" : "no";
		var blocked = data.apply_blocked_reason ? String(data.apply_blocked_reason) : "—";
		var owner = data.owner_label || data.owner || "—";
		return (
			'<div class="rsaip-proposal-preview">' +
			"<div><strong>Original:</strong> " +
			$("<div>").text(formatProposalValue(data.original_value)).html() +
			"</div>" +
			"<div><strong>Suggestion:</strong> " +
			$("<div>").text(formatProposalValue(data.proposed_value)).html() +
			"</div>" +
			"<div><strong>Normalized:</strong> " +
			$("<div>").text(formatProposalValue(data.normalized_value)).html() +
			"</div>" +
			"<div><strong>Owner:</strong> " +
			$("<div>").text(String(owner)).html() +
			"</div>" +
			"<div><strong>Safe Article Mode:</strong> " +
			safeMode +
			" · <strong>Apply allowed:</strong> " +
			applyAllowed +
			" (" +
			$("<div>").text(blocked).html() +
			")</div>" +
			'<div class="rsaip-sub">Expires: ' +
			$("<div>").text(String(data.expires_at || "—")).html() +
			"</div>" +
			"</div>"
		);
	}

	function renderDashboardCards(stats) {
		var items = [
			{ key: "total_posts", label: "Total Posts", icon: "dashicons-admin-post", tone: "blue" },
			{ key: "indexed_posts", label: "Indexed Posts", icon: "dashicons-yes-alt", tone: "green" },
			{ key: "non_indexed_posts", label: "Non Indexed Posts", icon: "dashicons-hidden", tone: "amber" },
			{ key: "orphan_posts", label: "Orphan Posts", icon: "dashicons-editor-unlink", tone: "red" },
			{ key: "missing_meta_description", label: "Missing Meta Description", icon: "dashicons-text-page", tone: "purple" },
			{ key: "missing_featured_images", label: "Missing Featured Images", icon: "dashicons-format-image", tone: "blue" },
			{ key: "missing_h2", label: "Missing H2", icon: "dashicons-editor-ol", tone: "amber" },
			{ key: "missing_h3", label: "Missing H3", icon: "dashicons-editor-textcolor", tone: "amber" },
			{ key: "missing_alt_text", label: "Missing Alt Text", icon: "dashicons-accessibility", tone: "red" },
			{ key: "low_internal_links", label: "Low Internal Links", icon: "dashicons-admin-links", tone: "blue" },
			{ key: "missing_external_links", label: "Missing External Links", icon: "dashicons-external", tone: "slate" },
			{ key: "thin_content_pages", label: "Thin Content Pages", icon: "dashicons-media-text", tone: "red" }
		];

		var html = "";
		items.forEach(function (it) {
			var v = stats && typeof stats[it.key] !== "undefined" ? stats[it.key] : "—";
			html +=
				'<div class="rsaip-card rsaip-kpi-card rsaip-fade-in rsaip-kpi-tone-' +
				it.tone +
				'">' +
				'<div class="rsaip-k"><span class="dashicons ' +
				it.icon +
				'" aria-hidden="true"></span>' +
				it.label +
				"</div>" +
				'<div class="rsaip-v">' +
				String(v) +
				"</div>" +
				"</div>";
		});
		return html;
	}

	function loadElement($el, action) {
		if (!action) return;
		if (action === "rsaip_get_dashboard_stats") {
			rsaipAjax(action, {}).done(function (resp) {
				if (!resp || !resp.success) {
					$el.removeClass("rsaip-loading").html('<div class="rsaip-note">Error</div>');
					return;
				}
				$el.removeClass("rsaip-loading").html(renderDashboardCards(resp.data));
			});
			return;
		}

		rsaipAjax(action, {}).done(function (resp) {
			if (!resp || !resp.success) {
				$el.removeClass("rsaip-loading").html('<tr><td colspan="10">Error</td></tr>');
				return;
			}
			if (resp.data && typeof resp.data.html !== "undefined") {
				$el.removeClass("rsaip-loading").html(resp.data.html);
			}
		});
	}

	var inlineSettingTimers = {};

	function saveInlineSetting($input) {
		var key = $input.data("setting");
		var value = $input.val();
		if (!key) return;
		rsaipAjax("rsaip_update_settings_inline", { key: key, value: value }).fail(function () {
			setStatus("Settings save failed");
		});
	}

	function pollProgress(action, data, onDone) {
		rsaipAjax(action, data || {}).done(function (resp) {
			if (!resp || !resp.success) {
				setStatus("Error");
				return;
			}
			var d = resp.data || {};
			if (typeof d.processed !== "undefined" && typeof d.total !== "undefined") {
				setStatus("Progress: " + d.processed + " / " + d.total);
			}
			if (d.done) {
				setStatus("Done");
				if (onDone) onDone(resp);
				return;
			}
			window.setTimeout(function () {
				pollProgress(action, data, onDone);
			}, 1200);
		});
	}

	$(function () {
		$(document).on("change", "#rsaip-article-template", function () {
			var t = $(this).val() || "general";
			if (t === "recipe_seo_midjourney") {
				$("#rsaip-article-word-count").val("1400");
			}
		});

		$(document).on("input", "#rsaip-article-title", function () {
			var title = String($(this).val() || "");
			var currentTemplate = $("#rsaip-article-template").val() || "general";
			if (currentTemplate !== "general") {
				return;
			}
			if (/(recipe|how to make|copycat|ingredients|instructions|sandwich|pasta|salad|soup)/i.test(title)) {
				$("#rsaip-article-template").val("recipe_seo_midjourney").trigger("change");
			}
		});

		$("[data-load]").each(function () {
			var $el = $(this);
			var action = $el.data("load");
			loadElement($el, action);
		});

		$(document).on("change", ".rsaip-input[data-setting]", function () {
			var $i = $(this);
			var key = $i.data("setting");
			if (!key) return;
			if (inlineSettingTimers[key]) {
				window.clearTimeout(inlineSettingTimers[key]);
			}
			inlineSettingTimers[key] = window.setTimeout(function () {
				saveInlineSetting($i);
			}, 350);
		});

		$(document).on("click", ".rsaip-btn", function (e) {
			e.preventDefault();
			var $b = $(this);
			if ($b.is(":disabled") || $b.prop("disabled")) {
				return;
			}
			var action = $b.data("action");
			if (!action) return;

			if (action === "rsaip_rebuild_link_graph" || action === "rsaip_run_full_audit") {
				setStatus("Starting…");
				pollProgress(action, {}, function () {
					loadElement($("#rsaip-dashboard-cards"), "rsaip_get_dashboard_stats");
					loadElement($("#rsaip-orphans-body"), "rsaip_list_orphans");
					loadElement($("#rsaip-audit-body"), "rsaip_list_audit_issues");
				});
				return;
			}

			if (action === "rsaip_generate_suggestions") {
				var limit = $(".rsaip-input[data-setting='link_suggestion_limit']").val();
				setStatus("Generating…");
				rsaipAjax(action, { limit: limit }).done(function () {
					setStatus("Done");
					loadElement($("#rsaip-suggestions-body"), "rsaip_list_suggestions");
				});
				return;
			}

			if (action === "rsaip_insert_links_for_post") {
				var pid = $b.data("post-id");
				if (!pid) return;
				$b.prop("disabled", true);
				rsaipAjax(action, { post_id: pid }).done(function (resp) {
					$b.prop("disabled", false);
					if (resp && resp.success) {
						setStatus("Inserted links: " + (resp.data.inserted || 0));
					}
				});
				return;
			}

			if (action === "rsaip_auto_link_run_batch") {
				setStatus("Running batch…");
				rsaipAjax(action, {}).done(function (resp) {
					if (resp && resp.success) {
						setStatus("Processed: " + resp.data.processed + ", updated: " + resp.data.updated);
					}
				});
				return;
			}

			if (action === "rsaip_generate_alt_text") {
				var aid = $b.data("attachment-id");
				var postId = $b.data("post-id") || 0;
				if (!aid) return;
				$b.prop("disabled", true);
				rsaipAjax(action, { attachment_id: aid, post_id: postId }).done(function (resp) {
					$b.prop("disabled", false);
					if (resp && resp.success) {
						setStatus("Alt updated: " + (resp.data.alt || ""));
						loadElement($("#rsaip-images-body"), "rsaip_list_image_issues");
					}
				});
				return;
			}

			if (action === "rsaip_sitemap_audit") {
				var sUrl = $(".rsaip-input[data-key='sitemap_url']").val();
				setStatus("Auditing…");
				rsaipAjax(action, { sitemap_url: sUrl }).done(function (resp) {
					if (resp && resp.success && resp.data && typeof resp.data.html !== "undefined") {
						$("#rsaip-sitemap-body").html(resp.data.html);
						setStatus("Done");
					}
				});
				return;
			}

			if (action === "rsaip_performance_analyze") {
				var pUrl = $(".rsaip-input[data-key='perf_url']").val();
				var strategy = $(".rsaip-input[data-key='perf_strategy']").val();
				setStatus("Analyzing…");
				rsaipAjax(action, { url: pUrl, strategy: strategy }).done(function (resp) {
					if (resp && resp.success && resp.data) {
						if (resp.data.ok && resp.data.html) {
							$("#rsaip-performance-result").html(resp.data.html);
							setStatus("Done");
						} else {
							$("#rsaip-performance-result").html('<div class="rsaip-note">' + (resp.data.message || "Error") + "</div>");
							setStatus("Error");
						}
					}
				});
				return;
			}

			if (action === "rsaip_ai_recommendations") {
				var postId = $(".rsaip-input[data-key='ai_post_id']").val();
				setStatus("Generating…");
				rsaipAjax(action, { post_id: postId }).done(function (resp) {
					if (resp && resp.success && resp.data) {
						$("#rsaip-ai-result").html(resp.data.html || "");
						setStatus("Done");
					}
				});
				return;
			}

			if (action === "rsaip_ai_analyze_post") {
				var pidAnalyze = $(".rsaip-input[data-key='ai_post_id']").val();
				setStatus("Analyzing…");
				rsaipAjax(action, { post_id: pidAnalyze }).done(function (resp) {
					var data = resp && resp.success ? (resp.data || {}) : {};
					if (data && data.ok) {
						$("#rsaip-ai-analysis").html(renderSeoOptimizationAnalysis(data));
						populateReadonlySuggestions(data);
						setStatus("Done");
					} else {
						var errMsg = (data && data.message) ? String(data.message) : "Analysis failed";
						var errCode = (data && data.code) ? String(data.code) : "";
						$("#rsaip-ai-analysis").html(
							'<div class="rsaip-note"><strong>Error</strong>' +
							(errCode ? " (" + $("<div>").text(errCode).html() + ")" : "") +
							": " + $("<div>").text(errMsg).html() +
							"<div class=\"rsaip-sub\">No changes were written. Safe Article Mode does not block Analyze.</div></div>"
						);
						populateReadonlySuggestions({});
						setStatus("Error");
					}
				}).fail(function () {
					$("#rsaip-ai-analysis").html('<div class="rsaip-note">Error: request failed. No changes were written.</div>');
					setStatus("Error");
				});
				return;
			}

			if (action === "rsaip_ai_generate_titles") {
				var pidTitles = $(".rsaip-input[data-key='ai_post_id']").val();
				clearProposalTicket("title");
				setStatus("Generating…");
				rsaipAjax(action, { post_id: pidTitles }).done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.ok) {
						$("#rsaip-ai-title-list").html(renderTitleSuggestions(resp.data));
						var pick = resp.data.recommended_title || (resp.data.titles && resp.data.titles[0]) || "";
						if (pick) {
							$("#rsaip-ai-title-input").val(pick);
						}
						setStatus("Done");
					} else {
						var tErr = (resp && resp.data && resp.data.message) ? String(resp.data.message) : "Error";
						var tCode = (resp && resp.data && resp.data.code) ? String(resp.data.code) : "";
						$("#rsaip-ai-title-list").html(
							'<div class="rsaip-note"><strong>Error</strong>' +
								(tCode ? " (" + escHtml(tCode) + ")" : "") +
								": " +
								escHtml(tErr) +
								"</div>"
						);
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_propose_title") {
				var pidProposeTitle = getAiPostId($("#rsaip-ai-title-preview"));
				if (!pidProposeTitle) {
					return;
				}
				var proposeTitleVal = $("#rsaip-ai-title-input").val() || "";
				if (!String(proposeTitleVal).trim()) {
					$("#rsaip-ai-title-preview").html('<div class="rsaip-note">Enter or generate a title first.</div>');
					setStatus("Title required");
					return;
				}
				setStatus("Previewing…");
				rsaipAjax(action, { post_id: pidProposeTitle, title: proposeTitleVal }).done(function (resp) {
					var data = resp && resp.success ? resp.data || {} : { ok: false, message: "Error" };
					$("#rsaip-ai-title-preview").html(renderProposalPreview(data));
					storeProposalTicket("title", data, pidProposeTitle, proposeTitleVal);
					setStatus(data.ok ? "Preview ready (Apply frozen)" : "Preview error");
					if (data.ok && data.apply_allowed) {
						setStatus("Preview ready");
					}
				});
				return;
			}

			if (action === "rsaip_ai_apply_title") {
				if (isSafeArticleModeOn()) {
					setStatus("Apply frozen: Safe Article Mode");
					$("#rsaip-ai-title-preview").append(
						'<div class="rsaip-sub">Apply Title is disabled while Safe Article Mode is enabled.</div>'
					);
					return;
				}
				var pidApplyTitle = getAiPostId($("#rsaip-ai-title-preview"));
				var titleTicket = proposalTickets.title;
				var applyTitleVal = $("#rsaip-ai-title-input").val() || "";
				if (
					!pidApplyTitle ||
					!titleTicket ||
					titleTicket.postId !== String(pidApplyTitle) ||
					titleTicket.sourceValue !== String(applyTitleVal)
				) {
					clearProposalTicket("title");
					$("#rsaip-ai-title-preview").html('<div class="rsaip-note">Preview the title again before Apply.</div>');
					setStatus("Preview required");
					return;
				}
				setStatus("Applying…");
				rsaipAjax(action, {
					post_id: pidApplyTitle,
					title: applyTitleVal,
					proposal_ticket: titleTicket.ticket
				}).done(function (resp) {
					var data = resp && resp.success ? resp.data || {} : { ok: false, message: "Error" };
					clearProposalTicket("title");
					if (data.ok && data.applied) {
						$("#rsaip-ai-title-preview").html(
							'<div class="rsaip-proposal-preview"><div><strong>Applied:</strong> ' +
								$("<div>").text(formatProposalValue(data.new_value)).html() +
								"</div><div class='rsaip-sub'>Owner: " +
								$("<div>").text(String(data.owner_label || data.owner || "—")).html() +
								"</div></div>"
						);
						setStatus("Applied and verified");
					} else {
						$("#rsaip-ai-title-preview").html(
							'<div class="rsaip-note">Apply failed' +
								(data.message ? ": " + $("<div>").text(String(data.message)).html() : "") +
								"</div>"
						);
						setStatus("Apply error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_generate_keywords") {
				var pidKeywords = getAiPostId($("#rsaip-ai-keywords-status"));
				if (!pidKeywords) {
					return;
				}
				clearProposalTicket("keywords");
				setStatus("Generating…");
				rsaipAjax(action, { post_id: pidKeywords }).done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.ok) {
						$("#rsaip-ai-keywords-picker").html(renderKeywordsPicker(resp.data));
						syncKeywordsApplyCandidate();
						$("#rsaip-ai-keywords-status").text(
							"Source: " +
								(resp.data.source || "—") +
								". Primary required; select secondary keywords before Preview if desired."
						);
						setStatus("Done");
					} else {
						$("#rsaip-ai-keywords-picker").html(
							'<div class="rsaip-note">' +
								escHtml((resp && resp.data && resp.data.message) || "Error") +
								"</div>"
						);
						$("#rsaip-ai-keywords").val("");
						$("#rsaip-ai-keywords-status").text((resp && resp.data && resp.data.message) || "Error");
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_propose_keywords") {
				var pidProposeKeywords = getAiPostId($("#rsaip-ai-keywords-status"));
				if (!pidProposeKeywords) {
					return;
				}
				var proposeKeywordsVal = $("#rsaip-ai-keywords").val() || "";
				var proposeKeywordsTarget = $("#rsaip-ai-keywords-target").val() || "auto";
				if (!proposeKeywordsVal.trim()) {
					$("#rsaip-ai-keywords-status").text("Generate keywords first, then preview them.");
					setStatus("Keywords required");
					return;
				}
				setStatus("Previewing…");
				rsaipAjax(action, {
					post_id: pidProposeKeywords,
					keywords: proposeKeywordsVal,
					target: proposeKeywordsTarget
				}).done(function (resp) {
					var data = resp && resp.success ? resp.data || {} : { ok: false, message: "Error" };
					$("#rsaip-ai-keywords-preview").html(renderProposalPreview(data));
					storeProposalTicket("keywords", data, pidProposeKeywords, proposeKeywordsVal);
					$("#rsaip-ai-keywords-status").text(
						data.ok
							? data.apply_allowed
								? "Preview ready."
								: "Preview ready. Apply frozen under Safe Article Mode."
							: data.message || "Error"
					);
					setStatus(data.ok ? (data.apply_allowed ? "Preview ready" : "Preview ready (Apply frozen)") : "Preview error");
				});
				return;
			}

			if (action === "rsaip_ai_apply_keywords") {
				if (isSafeArticleModeOn()) {
					setStatus("Apply frozen: Safe Article Mode");
					$("#rsaip-ai-keywords-status").text("Apply Keywords is disabled while Safe Article Mode is enabled.");
					return;
				}
				var pidApplyKeywords = getAiPostId($("#rsaip-ai-keywords-status"));
				var kwTicket = proposalTickets.keywords;
				var applyKeywordsVal = $("#rsaip-ai-keywords").val() || "";
				var applyKeywordsTarget = $("#rsaip-ai-keywords-target").val() || "auto";
				if (
					!pidApplyKeywords ||
					!kwTicket ||
					kwTicket.postId !== String(pidApplyKeywords) ||
					kwTicket.sourceValue !== String(applyKeywordsVal)
				) {
					clearProposalTicket("keywords");
					$("#rsaip-ai-keywords-status").text("Preview keywords again before Apply.");
					setStatus("Preview required");
					return;
				}
				setStatus("Applying…");
				rsaipAjax(action, {
					post_id: pidApplyKeywords,
					keywords: applyKeywordsVal,
					target: applyKeywordsTarget,
					proposal_ticket: kwTicket.ticket
				}).done(function (resp) {
					var data = resp && resp.success ? resp.data || {} : { ok: false, message: "Error" };
					clearProposalTicket("keywords");
					if (data.ok && data.applied) {
						$("#rsaip-ai-keywords-preview").html(
							'<div class="rsaip-proposal-preview"><div><strong>Applied:</strong> ' +
								$("<div>").text(formatProposalValue(data.new_value)).html() +
								"</div></div>"
						);
						$("#rsaip-ai-keywords-status").text("Applied and verified.");
						setStatus("Applied and verified");
					} else {
						$("#rsaip-ai-keywords-status").text(data.message || "Apply failed");
						setStatus("Apply error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_generate_meta_desc") {
				var pidMeta = $(".rsaip-input[data-key='ai_post_id']").val();
				clearProposalTicket("meta_description");
				setStatus("Generating…");
				rsaipAjax(action, { post_id: pidMeta }).done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.ok) {
						$("#rsaip-ai-metadesc").val(resp.data.metadesc || resp.data.meta_description || "");
						$("#rsaip-ai-meta-status").text("Source: " + (resp.data.source || "—"));
						setStatus("Done");
					} else {
						$("#rsaip-ai-meta-status").text((resp && resp.data && resp.data.message) || "Error");
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_propose_meta_desc") {
				var pidProposeMeta = getAiPostId($("#rsaip-ai-meta-status"));
				if (!pidProposeMeta) {
					return;
				}
				var proposeMd = $("#rsaip-ai-metadesc").val() || "";
				var proposeMetaTarget = $("#rsaip-ai-metadesc-target").val() || "auto";
				if (!String(proposeMd).trim()) {
					$("#rsaip-ai-meta-status").text("Generate a meta description first, then preview.");
					setStatus("Meta required");
					return;
				}
				setStatus("Previewing…");
				rsaipAjax(action, {
					post_id: pidProposeMeta,
					metadesc: proposeMd,
					target: proposeMetaTarget
				}).done(function (resp) {
					var data = resp && resp.success ? resp.data || {} : { ok: false, message: "Error" };
					$("#rsaip-ai-meta-preview").html(renderProposalPreview(data));
					storeProposalTicket("meta_description", data, pidProposeMeta, proposeMd);
					$("#rsaip-ai-meta-status").text(
						data.ok
							? data.apply_allowed
								? "Preview ready."
								: "Preview ready. Apply frozen under Safe Article Mode."
							: data.message || "Error"
					);
					setStatus(data.ok ? (data.apply_allowed ? "Preview ready" : "Preview ready (Apply frozen)") : "Preview error");
				});
				return;
			}

			if (action === "rsaip_ai_apply_meta_desc") {
				if (isSafeArticleModeOn()) {
					setStatus("Apply frozen: Safe Article Mode");
					$("#rsaip-ai-meta-status").text("Meta Apply is disabled while Safe Article Mode is enabled.");
					return;
				}
				var pidApplyMeta = getAiPostId($("#rsaip-ai-meta-status"));
				var metaTicket = proposalTickets.meta_description;
				var applyMd = $("#rsaip-ai-metadesc").val() || "";
				var applyMetaTarget = $("#rsaip-ai-metadesc-target").val() || "auto";
				if (
					!pidApplyMeta ||
					!metaTicket ||
					metaTicket.postId !== String(pidApplyMeta) ||
					metaTicket.sourceValue !== String(applyMd)
				) {
					clearProposalTicket("meta_description");
					$("#rsaip-ai-meta-status").text("Preview meta again before Apply.");
					setStatus("Preview required");
					return;
				}
				setStatus("Applying…");
				rsaipAjax(action, {
					post_id: pidApplyMeta,
					metadesc: applyMd,
					target: applyMetaTarget,
					proposal_ticket: metaTicket.ticket
				}).done(function (resp) {
					var data = resp && resp.success ? resp.data || {} : { ok: false, message: "Error" };
					clearProposalTicket("meta_description");
					if (data.ok && data.applied) {
						$("#rsaip-ai-meta-preview").html(
							'<div class="rsaip-proposal-preview"><div><strong>Applied:</strong> ' +
								$("<div>").text(formatProposalValue(data.new_value)).html() +
								"</div></div>"
						);
						$("#rsaip-ai-meta-status").text("Applied and verified.");
						setStatus("Applied and verified");
					} else {
						$("#rsaip-ai-meta-status").text(data.message || "Apply failed");
						setStatus("Apply error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_bulk_apply_meta_desc") {
				setStatus("Bulk Apply is not enabled in Milestone 4");
				$("#rsaip-ai-meta-status").text("Bulk meta Apply is not enabled in Milestone 4.");
				return;
			}

			if (action === "rsaip_ai_generate_faq") {
				var pidFaq = $(".rsaip-input[data-key='ai_post_id']").val();
				setStatus("Generating…");
				rsaipAjax(action, { post_id: pidFaq }).done(function (resp) {
					var $faqBox = $("#rsaip-ai-faq-result");
					$faqBox.empty();
					if (resp && resp.success && resp.data && resp.data.ok) {
						var faqs = resp.data.faqs || [];
						if (!faqs.length) {
							$faqBox.append($("<div/>", { "class": "rsaip-note", text: "No FAQ suggestions." }));
							setStatus("Done");
							return;
						}
						var $ol = $("<ol/>", { "class": "rsaip-list" });
						faqs.forEach(function (f) {
							var $li = $("<li/>");
							$li.append($("<div/>", { "class": "rsaip-li-title", text: String(f.q || "") }));
							$li.append($("<div/>", { "class": "rsaip-sub", text: String(f.a || "") }));
							$ol.append($li);
						});
						$faqBox.append($ol);
						$faqBox.append($("<div/>", { "class": "rsaip-sub", text: "Source: " + String(resp.data.source || "—") }));
						setStatus("Done");
					} else {
						$faqBox.append($("<div/>", { "class": "rsaip-note", text: "Error" }));
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_article_preview_generate") {
				var newTitle = $("#rsaip-new-article-title").val() || "";
				var newKeywords = $("#rsaip-new-article-keywords").val() || "";
				var newImages = $("#rsaip-new-article-images").val() || "";
				var newContent = $("#rsaip-new-article-content").val() || "";
				var newTemplate = $("#rsaip-new-article-template").val() || "general";
				var newWords = parseInt($("#rsaip-new-article-word-count").val() || "1000", 10);
				if (!newWords || isNaN(newWords)) {
					newWords = 1000;
				}
				newWords = Math.max(400, Math.min(4000, newWords));
				if (!String(newTitle).trim()) {
					$("#rsaip-new-article-preview-status").text("Title is required.");
					setStatus("Title required");
					return;
				}
				var $previewBtn = $("#rsaip-new-article-preview-btn");
				if ($previewBtn.data("busy")) {
					return;
				}
				$previewBtn.data("busy", true).prop("disabled", true);
				$("#rsaip-new-article-create-draft-btn").prop("disabled", true).attr("hidden", true);
				$("#rsaip-new-article-preview-status").text("Generating preview…");
				setStatus("Generating preview…");
				rsaipAjax("rsaip_ai_article_preview_generate", {
					title: newTitle,
					keywords: newKeywords,
					images: newImages,
					content: newContent,
					word_count: newWords,
					template: newTemplate
				})
					.done(function (resp) {
						var data = (resp && resp.data) || {};
						if (resp && resp.success && data.ok) {
							rsaipRenderArticlePreview(data);
							$("#rsaip-new-article-preview-status").text("Preview ready. Click Create Draft (NEW Generator) to save a draft.");
							setStatus("Preview ready");
						} else {
							rsaipRenderArticlePreview(data && data.ok === false ? data : { ok: false, message: (data && data.message) || "Preview failed." });
							$("#rsaip-new-article-preview-status").text((data && data.message) || "Error");
							setStatus("Error");
						}
					})
					.fail(function () {
						$("#rsaip-new-article-preview-status").text("Request failed.");
						$("#rsaip-new-article-preview").empty().append($("<div/>", { "class": "rsaip-note", text: "Request failed." }));
						setStatus("Error");
					})
					.always(function () {
						$previewBtn.data("busy", false).prop("disabled", false);
					});
				return;
			}

			if (action === "rsaip_ai_article_create_draft") {
				var proposalId = $("#rsaip-new-article-proposal-id").val() || "";
				var fingerprint = $("#rsaip-new-article-fingerprint").val() || "";
				var $draftBtn = $("#rsaip-new-article-create-draft-btn");
				if (!proposalId || !fingerprint) {
					$("#rsaip-new-article-draft-status").text("Generate a Preview first.");
					setStatus("Preview required");
					return;
				}
				if ($draftBtn.data("busy")) {
					return;
				}
				$draftBtn.data("busy", true).prop("disabled", true);
				$("#rsaip-new-article-draft-status").text("Creating draft…");
				setStatus("Creating draft…");
				rsaipAjax("rsaip_ai_article_create_draft", {
					proposal_id: proposalId,
					fingerprint: fingerprint
				})
					.done(function (resp) {
						var data = (resp && resp.data) || {};
						if (resp && resp.success && data.ok) {
							var msg = "Draft #" + String(data.draft_id || data.post_id || "") + " created (status: draft).";
							if (data.already_consumed) {
								msg = "Draft already existed for this preview: #" + String(data.draft_id || "") + ".";
							}
							if (data.partial || data.seo_status === "blocked_by_safe_mode") {
								msg += " SEO metadata blocked by Safe Article Mode.";
							} else if (data.seo_ok) {
								msg += " SEO metadata applied via PMS.";
							}
							$("#rsaip-new-article-draft-status").text(msg);
							if (data.edit_url) {
								$("#rsaip-new-article-draft-status").append(
									$("<div/>").append(
										$("<a/>", { href: String(data.edit_url), target: "_blank", rel: "noopener", text: "Edit Draft" })
									)
								);
							}
							setStatus("Draft created");
						} else {
							$("#rsaip-new-article-draft-status").text((data && data.message) || "Create Draft failed.");
							if (data && data.partial && data.draft_id) {
								$("#rsaip-new-article-draft-status").append(
									document.createTextNode(" Draft ID: " + String(data.draft_id))
								);
							}
							// Re-enable if claim released (not consumed).
							if (!(data && data.proposal_consumed)) {
								$draftBtn.prop("disabled", false);
							}
							setStatus("Error");
						}
					})
					.fail(function () {
						$("#rsaip-new-article-draft-status").text("Request failed.");
						$draftBtn.prop("disabled", false);
						setStatus("Error");
					})
					.always(function () {
						$draftBtn.data("busy", false);
					});
				return;
			}

			if (action === "rsaip_ai_generate_article" || action === "rsaip_ai_create_article_draft") {
				var articleTitle = $("#rsaip-article-title").val() || "";
				var articleKeywords = $("#rsaip-article-keywords").val() || "";
				var articleImages = $("#rsaip-article-images").val() || "";
				var articleTemplate = $("#rsaip-article-template").val() || "general";
				var articleWordCount = parseInt($("#rsaip-article-word-count").val() || "1000", 10);
				if (!articleWordCount || isNaN(articleWordCount)) {
					articleWordCount = 1000;
				}
				articleWordCount = Math.max(400, Math.min(4000, articleWordCount));
				if (!articleTitle.trim()) {
					$("#rsaip-article-generator-status").text("Title is required.");
					setStatus("Title required");
					return;
				}
				setStatus(action === "rsaip_ai_generate_article" ? "Generating…" : "Creating draft…");
				rsaipAjax(action, { title: articleTitle, keywords: articleKeywords, images: articleImages, word_count: articleWordCount, template: articleTemplate }).done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.ok) {
						var htmlArticle = "";
						if (action === "rsaip_ai_create_article_draft") {
							htmlArticle += "<div class='rsaip-sub'><strong>Draft created:</strong> #" + String(resp.data.post_id || 0) + "</div>";
							if (resp.data.edit_url) {
								htmlArticle += "<div class='rsaip-sub'><a href='" + String(resp.data.edit_url) + "' target='_blank' rel='noopener'>Edit Draft</a></div>";
							}
						}
						htmlArticle += "<h3>" + String(resp.data.title || articleTitle) + "</h3>";
						htmlArticle += "<div class='rsaip-sub'>Source: " + String(resp.data.source || "—") + "</div>";
						if (typeof resp.data.word_count !== "undefined") {
							htmlArticle += "<div class='rsaip-sub'><strong>Words:</strong> " + String(resp.data.word_count) + "</div>";
						}
						if ((!articleKeywords || !articleKeywords.trim()) && resp.data.keywords && resp.data.keywords.length) {
							$("#rsaip-article-keywords").val((resp.data.keywords || []).join(", "));
						}
						htmlArticle += "<div class='rsaip-sub'><strong>Keywords:</strong> " + String((resp.data.keywords || []).join(", ")) + "</div>";
						htmlArticle += "<div class='rsaip-sub'><strong>Meta:</strong> " + String(resp.data.meta_description || "") + "</div>";
						htmlArticle += "<hr>" + String(resp.data.content_html || "");
						$("#rsaip-article-generator-result").html(htmlArticle);
						$("#rsaip-article-generator-status").text(action === "rsaip_ai_generate_article" ? "Article generated." : "Draft created successfully.");
						setStatus("Done");
					} else {
						$("#rsaip-article-generator-status").text((resp && resp.data && resp.data.message) || "Error");
						$("#rsaip-article-generator-result").html('<div class="rsaip-note">Error</div>');
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_ai_fix_post") {
				setStatus("Fix With AI is disabled in Milestone 4");
				$("#rsaip-ai-result")
					.removeAttr("hidden")
					.html(
						'<div class="rsaip-note">Fix With AI remains blocked independently of Safe Article Mode.</div>'
					);
				return;
			}

			if (action === "rsaip_recipe_generate_card" || action === "rsaip_recipe_insert_card") {
				var recipePostId = $b.data("post-id") || $(".rsaip-input[data-key='ai_post_id']").val();
				if (!recipePostId) {
					setStatus("Post ID required");
					return;
				}
				setStatus(action === "rsaip_recipe_generate_card" ? "Generating…" : "Inserting…");
				rsaipAjax(action, { post_id: recipePostId }).done(function (resp) {
					var target = $b.data("source") === "ai-post" ? $("#rsaip-ai-recipe-card") : $("#rsaip-recipe-card-preview");
					if (resp && resp.success && resp.data && resp.data.ok) {
						target.html(resp.data.html || "");
						setStatus(action === "rsaip_recipe_generate_card" ? "Done" : "Inserted");
						if (action === "rsaip_recipe_insert_card") {
							loadElement($("#rsaip-recipe-body"), "rsaip_recipe_scan");
						}
					} else {
						target.html('<div class="rsaip-note">' + ((resp && resp.data && resp.data.message) || "Error") + "</div>");
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_schema_fix_post") {
				var schemaPostId = $b.data("post-id");
				if (!schemaPostId) {
					setStatus("Post ID required");
					return;
				}
				setStatus("Fixing…");
				rsaipAjax(action, { post_id: schemaPostId }).done(function (resp) {
					if (resp && resp.success && resp.data && resp.data.ok) {
						$("#rsaip-schema-fix-result").html(resp.data.html || "Done");
						loadElement($("#rsaip-schema-body"), "rsaip_schema_scan");
						setStatus("Done");
					} else {
						$("#rsaip-schema-fix-result").html('<div class="rsaip-note">' + ((resp && resp.data && resp.data.message) || "Error") + "</div>");
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_schema_fix_batch") {
				var schemaLimit = $b.data("limit") || 50;
				setStatus("Fixing…");
				rsaipAjax(action, { limit: schemaLimit }).done(function (resp) {
					if (resp && resp.success && resp.data) {
						$("#rsaip-schema-fix-result").html(
							"Processed: " +
								String(resp.data.processed || 0) +
								", Updated: " +
								String(resp.data.updated || 0) +
								", Skipped: " +
								String(resp.data.skipped || 0)
						);
						loadElement($("#rsaip-schema-body"), "rsaip_schema_scan");
						setStatus("Done");
					} else {
						$("#rsaip-schema-fix-result").html("Error");
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_schema_enqueue_queue" || action === "rsaip_schema_process_queue" || action === "rsaip_schema_queue_stats") {
				var schemaQueueLimit = $b.data("limit") || 50;
				setStatus("Working…");
				rsaipAjax(action, { limit: schemaQueueLimit }).done(function (resp) {
					if (resp && resp.success && resp.data) {
						var statsSchema = resp.data.stats || resp.data;
						var htmlSchemaQueue =
							"Pending: " +
							String(statsSchema.pending || 0) +
							" | Processing: " +
							String(statsSchema.processing || 0) +
							" | Done: " +
							String(statsSchema.done || 0) +
							" | Failed: " +
							String(statsSchema.failed || 0);
						if (typeof resp.data.enqueued !== "undefined") {
							htmlSchemaQueue = "Enqueued: " + resp.data.enqueued + "<br>" + htmlSchemaQueue;
						}
						if (typeof resp.data.processed !== "undefined") {
							htmlSchemaQueue = "Processed: " + resp.data.processed + ", Done: " + resp.data.done + ", Failed: " + resp.data.failed + "<br>" + htmlSchemaQueue;
						}
						$("#rsaip-schema-fix-result").html(htmlSchemaQueue);
						loadElement($("#rsaip-schema-body"), "rsaip_schema_scan");
						setStatus("Done");
					} else {
						$("#rsaip-schema-fix-result").html("Error");
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_report_preview") {
				var type = $(".rsaip-input[data-key='report_type']").val() || "weekly";
				setStatus("Building…");
				rsaipAjax(action, { type: type }).done(function (resp) {
					if (resp && resp.success && resp.data) {
						$("#rsaip-report-preview").html(resp.data.html || "");
						setStatus("Done");
					}
				});
				return;
			}

			if (action === "rsaip_bulk_enqueue_fix_queue" || action === "rsaip_bulk_process_queue" || action === "rsaip_bulk_queue_stats") {
				var bulkLimit = $(".rsaip-input[data-key='bulk_limit']").val() || 100;
				setStatus("Working…");
				rsaipAjax(action, { limit: bulkLimit }).done(function (resp) {
					if (resp && resp.success && resp.data) {
						var stats = resp.data.stats || resp.data;
						var htmlQueue = "Pending: " + String(stats.pending || 0) + " | Processing: " + String(stats.processing || 0) + " | Done: " + String(stats.done || 0) + " | Failed: " + String(stats.failed || 0);
						if (typeof resp.data.enqueued !== "undefined") {
							htmlQueue = "Enqueued: " + resp.data.enqueued + "<br>" + htmlQueue;
						}
						if (typeof resp.data.processed !== "undefined") {
							htmlQueue = "Processed: " + resp.data.processed + ", Done: " + resp.data.done + ", Failed: " + resp.data.failed + "<br>" + htmlQueue;
						}
						$("#rsaip-bulk-queue-status").html(htmlQueue);
						setStatus("Done");
					} else {
						$("#rsaip-bulk-queue-status").html("Error");
						setStatus("Error");
					}
				});
				return;
			}

			if (action === "rsaip_report_export_csv" || action === "rsaip_report_export_xlsx" || action === "rsaip_report_export_pdf") {
				var t = $(".rsaip-input[data-key='report_type']").val() || "weekly";
				var url =
					RSAIP.ajaxUrl +
					"?action=" +
					encodeURIComponent(action) +
					"&nonce=" +
					encodeURIComponent(RSAIP.nonce) +
					"&type=" +
					encodeURIComponent(t);
				window.location.href = url;
				return;
			}

			rsaipAjax(action, {}).done(function (resp) {
				if (resp && resp.success && resp.data && typeof resp.data.html !== "undefined") {
					var targets = {
						rsaip_list_suggestions: "#rsaip-suggestions-body",
						rsaip_list_orphans: "#rsaip-orphans-body",
						rsaip_list_audit_issues: "#rsaip-audit-body",
						rsaip_list_image_issues: "#rsaip-images-body",
						rsaip_gsc_top_opportunities: "#rsaip-gsc-body",
						rsaip_gsc_low_hanging: "#rsaip-low-hanging-body",
						rsaip_recipe_scan: "#rsaip-recipe-body",
						rsaip_schema_scan: "#rsaip-schema-body"
					};
					if (targets[action]) {
						$(targets[action]).html(resp.data.html);
					}
				}
			});
		});

		$(document).on("click", ".rsaip-fill-title", function (e) {
			e.preventDefault();
			var t = $(this).attr("data-title") || $(this).text();
			$("#rsaip-ai-title-input").val(t);
			clearProposalTicket("title");
		});

		$(document).on("change", "#rsaip-ai-keywords-picker .rsaip-kw-secondary", function () {
			syncKeywordsApplyCandidate();
		});

		$(document).on("input change", "#rsaip-ai-title-input", function () {
			clearProposalTicket("title");
		});
		$(document).on("input change", "#rsaip-ai-keywords, #rsaip-ai-keywords-target", function () {
			clearProposalTicket("keywords");
		});
		$(document).on("input change", "#rsaip-ai-metadesc, #rsaip-ai-metadesc-target", function () {
			clearProposalTicket("meta_description");
		});
		$(document).on("input change", ".rsaip-input[data-key='ai_post_id']", function () {
			clearAllProposalTickets();
		});
		syncApplyButtons();
	});
})(jQuery);
