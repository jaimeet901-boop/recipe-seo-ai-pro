(function () {
	function getRecipeCard($button) {
		return $button.closest('.rsaip-recipe-card');
	}

	function setStatus(card, text) {
		var $status = card.querySelector('.rsaip-recipe-action-status');
		if (!$status) {
			$status = document.createElement('div');
			$status.className = 'rsaip-recipe-action-status';
			card.appendChild($status);
		}
		$status.textContent = text;
	}

	function handleSave(card) {
		var title = card.dataset.recipeTitle || document.title;
		var url = card.dataset.recipeUrl || window.location.href;
		var data = {
			url: url,
			title: title,
		};
		var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = 'recipe-' + title.replace(/[^a-z0-9_-]+/gi, '_').toLowerCase() + '.json';
		link.style.display = 'none';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		setStatus(card, 'Saved locally.');
	}

	function handlePrint(card) {
		var printWindow = window.open('', '_blank');
		if (!printWindow) {
			setStatus(card, 'Unable to open print window.');
			return;
		}
		printWindow.document.write('<!doctype html><html><head><title>' + document.title + '</title>');
		printWindow.document.write('<style>body{font-family:sans-serif;padding:24px;color:#111;} img{max-width:100%;height:auto;border-radius:14px;} .rsaip-recipe-card{max-width:720px;margin:0 auto;} .rsaip-recipe-card__title{font-size:28px;margin-bottom:12px;} .rsaip-recipe-card__desc{font-size:16px;line-height:1.65;} .rsaip-recipe-card__stats{display:flex;flex-wrap:wrap;gap:12px;margin:20px 0;} .rsaip-recipe-card__stat{border:1px solid #ddd;padding:12px;border-radius:12px;}</style>');
		printWindow.document.write('</head><body>');
		printWindow.document.write(card.outerHTML);
		printWindow.document.write('</body></html>');
		printWindow.document.close();
		printWindow.focus();
		printWindow.print();
	}

	function handleShare(card) {
		var url = card.dataset.recipeUrl || window.location.href;
		var title = card.dataset.recipeTitle || document.title;
		if (navigator.share) {
			navigator.share({
				title: title,
				text: title,
				url: url,
			}).then(function () {
				setStatus(card, 'Shared successfully.');
			}).catch(function () {
				setStatus(card, 'Share canceled.');
			});
			return;
		}
		var textarea = document.createElement('textarea');
		textarea.value = url;
		document.body.appendChild(textarea);
		textarea.select();
		document.execCommand('copy');
		document.body.removeChild(textarea);
		setStatus(card, 'Link copied to clipboard.');
	}

	function handleRate(card) {
		if (typeof RSAIP === 'undefined' || !RSAIP.ajaxUrl || !RSAIP.nonce) {
			setStatus(card, 'Rating unavailable.');
			return;
		}
		var rating = prompt('Please enter a rating from 1 to 5:');
		if (!rating) {
			setStatus(card, 'Rating canceled.');
			return;
		}
		rating = String(rating).trim();
		if (!/^[1-5]$/.test(rating)) {
			setStatus(card, 'Rating must be 1-5.');
			return;
		}
		var postId = card.dataset.postId;
		if (!postId) {
			setStatus(card, 'Recipe ID unavailable.');
			return;
		}

		var formData = new FormData();
		formData.append('action', RSAIP.ratingAction || 'rsaip_public_save_recipe_rating');
		formData.append('nonce', RSAIP.nonce);
		formData.append('post_id', postId);
		formData.append('rating', rating);
		// Honeypot field — leave empty.
		formData.append('website', '');

		fetch(RSAIP.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				if (data && data.success) {
					var msg = 'Thanks for rating ' + rating + ' stars.';
					if (data.data && data.data.rating_value) {
						msg += ' Average: ' + data.data.rating_value + ' (' + data.data.review_count + ' reviews).';
					}
					setStatus(card, msg);
				} else {
					setStatus(card, (data && data.data && data.data.message) ? data.data.message : 'Unable to save rating.');
				}
			})
			.catch(function () {
				setStatus(card, 'Unable to save rating.');
			});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.rsaip-recipe-action');
		if (!button) {
			return;
		}
		event.preventDefault();
		var card = getRecipeCard(button);
		if (!card) {
			return;
		}
		var action = button.dataset.action;
		switch (action) {
			case 'save-recipe':
				handleSave(card);
				break;
			case 'print-recipe':
				handlePrint(card);
				break;
			case 'share-recipe':
				handleShare(card);
				break;
			case 'rate-recipe':
				handleRate(card);
				break;
			default:
				break;
		}
	});
})();