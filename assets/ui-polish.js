/**
 * UI polish helpers: toast notifications for admin status messages.
 * Does not change AJAX actions or business logic.
 */
(function ($) {
	"use strict";

	function host() {
		var $h = $("#rsaip-toast-host");
		if (!$h.length) {
			$h = $('<div id="rsaip-toast-host" class="rsaip-toast-host" aria-live="polite" aria-relevant="additions"></div>');
			$("body").append($h);
		}
		return $h;
	}

	function iconFor(type) {
		if (type === "success") return "dashicons-yes-alt";
		if (type === "error") return "dashicons-warning";
		if (type === "warn") return "dashicons-flag";
		return "dashicons-info";
	}

	window.rsaipToast = function (message, type) {
		var text = (message || "").toString().trim();
		if (!text) return;
		type = type || "info";
		var $toast = $(
			'<div class="rsaip-toast rsaip-toast-' +
				type +
				'" role="status"><span class="dashicons ' +
				iconFor(type) +
				'" aria-hidden="true"></span><div></div></div>'
		);
		$toast.find("div").text(text);
		host().append($toast);
		window.setTimeout(function () {
			$toast.addClass("is-leaving");
			window.setTimeout(function () {
				$toast.remove();
			}, 200);
		}, 3200);
	};

	function classify(text) {
		var t = (text || "").toLowerCase();
		if (!t) return null;
		if (t.indexOf("fail") >= 0 || t.indexOf("error") >= 0 || t.indexOf("required") >= 0) {
			return "error";
		}
		if (t.indexOf("…") >= 0 || t.indexOf("...") >= 0 || t.indexOf("loading") >= 0 || t.indexOf("progress") >= 0) {
			return "info";
		}
		if (t.indexOf("done") >= 0 || t.indexOf("saved") >= 0 || t.indexOf("complete") >= 0 || t.indexOf("applied") >= 0) {
			return "success";
		}
		return "info";
	}

	function bindStatus($el) {
		if (!$el.length || $el.data("rsaipToastBound")) return;
		$el.data("rsaipToastBound", 1);
		var last = "";
		var obs = new MutationObserver(function () {
			var text = ($el.text() || "").trim();
			if (!text || text === last) return;
			last = text;
			if ($el.hasClass("rsaip-error")) {
				window.rsaipToast(text, "error");
				return;
			}
			var type = classify(text);
			if (type) {
				window.rsaipToast(text, type);
			}
		});
		obs.observe($el.get(0), { childList: true, characterData: true, subtree: true });
	}

	$(function () {
		bindStatus($("#rsaip-status"));
		bindStatus($("#rsaip-opt-status"));
		bindStatus($("#rsaip-rai-status"));
		bindStatus($("#rsaip-kw-status-text"));
		bindStatus($("#rsaip-cal-status-text"));
		bindStatus($("#rsaip-serp-status"));
		bindStatus($("#rsaip-proj-status"));
	});
})(jQuery);
