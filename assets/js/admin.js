/**
 * Admin page JavaScript for Edit Ledger.
 */
(function ($) {
	'use strict';

	const { apiBase, strings } = window.editLedgerAdmin || {};

	let currentPage = 1;
	let currentFilters = {};

	$(document).ready(function () {
		loadRevisions();
		bindEvents();
	});

	function bindEvents() {
		$(document).on('submit', '#edit-ledger-filter-form', function (e) {
			e.preventDefault();
			currentPage = 1;
			currentFilters = {
				author: $('#filter-author').val(),
				after: $('#filter-after').val(),
				before: $('#filter-before').val(),
			};
			loadRevisions();
		});

		$(document).on('click', '#filter-reset', function () {
			$('#filter-author').val('');
			$('#filter-after').val('');
			$('#filter-before').val('');
			currentPage = 1;
			currentFilters = {};
			loadRevisions();
		});

		$(document).on('click', '#load-more', function () {
			currentPage++;
			loadRevisions(true);
		});

		$(document).on('click', '.edit-ledger-view-diff', function () {
			const revisionId = $(this).data('revision');
			openDiffModal(revisionId);
		});

		$(document).on('click', '.edit-ledger-modal__close, .edit-ledger-modal__overlay', function () {
			closeModal();
		});

		$(document).on('click', '.edit-ledger-modal__controls button', function () {
			const mode = $(this).data('mode');
			$('.edit-ledger-modal__controls button').removeClass('button-primary');
			$(this).addClass('button-primary');
			toggleDiffMode(mode);
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') {
				closeModal();
			}
		});
	}

	function loadRevisions(append = false) {
		const $list = $('#edit-ledger-list');
		const $loadMore = $('#load-more');

		if (!append) {
			$list.html('<tr class="edit-ledger-loading"><td colspan="5">' + strings.loading + '</td></tr>');
		}

		const params = new URLSearchParams({
			page: currentPage,
			per_page: 20,
			...currentFilters,
		});

		for (const [key, value] of params.entries()) {
			if (!value) {
				params.delete(key);
			}
		}

		wp.apiFetch({ path: 'edit-ledger/v1/recent?' + params.toString() })
			.then(function (revisions) {
				if (!append) {
					$list.empty();
				}

				if (revisions.length === 0 && currentPage === 1) {
					$list.html('<tr><td colspan="5">' + strings.noRevisions + '</td></tr>');
					$loadMore.hide();
					return;
				}

				revisions.forEach(function (revision) {
					$list.append(renderRevisionRow(revision));
				});

				if (revisions.length >= 20) {
					$loadMore.show();
				} else {
					$loadMore.hide();
				}
			})
			.catch(function (error) {
				console.error('Failed to load revisions:', error);
				$list.html('<tr><td colspan="5">' + strings.error + '</td></tr>');
				$loadMore.hide();
			});
	}

	function renderRevisionRow(revision) {
		const typeClass = revision.type === 'autosave' ? 'autosave' : 'manual';
		const typeLabel = revision.type === 'autosave' ? 'Auto' : 'Save';

		return `
			<tr>
				<td class="column-post">
					<strong>${escapeHtml(revision.parent_title)}</strong>
					<span class="post-type">(${escapeHtml(revision.parent_type)})</span>
				</td>
				<td class="column-author">
					<img src="${escapeHtml(revision.author.avatar)}" alt="" class="avatar" width="24" height="24">
					${escapeHtml(revision.author.name)}
				</td>
				<td class="column-date">
					<span title="${escapeHtml(revision.date)}">${escapeHtml(revision.date_relative)} ago</span>
				</td>
				<td class="column-type">
					<span class="revision-type revision-type--${typeClass}">${typeLabel}</span>
				</td>
				<td class="column-actions">
					<button type="button" class="button button-small edit-ledger-view-diff" data-revision="${revision.id}">
						${strings.viewDiff}
					</button>
					<a href="${escapeHtml(revision.edit_url)}" class="button button-small" target="_blank">
						${strings.editPost}
					</a>
				</td>
			</tr>
		`;
	}

	function openDiffModal(revisionId) {
		const $modal = $('#edit-ledger-modal');
		const $body = $modal.find('.edit-ledger-modal__body');

		$body.html('<div class="edit-ledger-loading">' + strings.loading + '</div>');
		$modal.show();

		wp.apiFetch({ path: 'edit-ledger/v1/revisions/' + revisionId + '/diff' })
			.then(function (data) {
				renderDiffContent(data);
			})
			.catch(function (error) {
				console.error('Failed to load diff:', error);
				$body.html('<p class="error">' + strings.error + '</p>');
			});
	}

	function closeModal() {
		$('#edit-ledger-modal').hide();
	}

	function renderComparisonHeader(comparison) {
		if (!comparison) {
			return '';
		}

		const { from, to } = comparison;

		return `
			<div class="edit-ledger-comparison-header">
				<div class="edit-ledger-comparison-side edit-ledger-comparison-from">
					<img src="${escapeHtml(from.author.avatar)}" alt="${escapeHtml(from.author.name)}" class="edit-ledger-comparison-avatar">
					<div class="edit-ledger-comparison-info">
						<div class="edit-ledger-comparison-author">${escapeHtml(from.author.name)}</div>
						<div class="edit-ledger-comparison-date">${from.is_current ? 'Current version' : escapeHtml(from.date_relative) + ' ago'}</div>
					</div>
					<div class="edit-ledger-comparison-label">Before</div>
				</div>
				<div class="edit-ledger-comparison-arrow">→</div>
				<div class="edit-ledger-comparison-side edit-ledger-comparison-to">
					<img src="${escapeHtml(to.author.avatar)}" alt="${escapeHtml(to.author.name)}" class="edit-ledger-comparison-avatar">
					<div class="edit-ledger-comparison-info">
						<div class="edit-ledger-comparison-author">${escapeHtml(to.author.name)}</div>
						<div class="edit-ledger-comparison-date">${escapeHtml(to.date_relative)} ago</div>
					</div>
					<div class="edit-ledger-comparison-label">After</div>
				</div>
			</div>
		`;
	}

	function renderDiffContent(data) {
		const $body = $('#edit-ledger-modal .edit-ledger-modal__body');
		let html = '';

		html += renderComparisonHeader(data.comparison);

		const fields = ['title', 'content', 'excerpt'];

		fields.forEach(function (field) {
			const fieldData = data.fields[field];
			if (fieldData.from === fieldData.to) {
				return;
			}

			const label = field.charAt(0).toUpperCase() + field.slice(1);

			html += `
				<div class="edit-ledger-diff-section" data-field="${field}">
					<h3>${label}</h3>
					<div class="diff-inline">
						${fieldData.diff_html || '<p>No visual diff available</p>'}
					</div>
					<div class="diff-side-by-side" style="display: none;">
						<div class="diff-left">
							<div class="diff-label">Before</div>
							<pre>${escapeHtml(fieldData.from) || '(empty)'}</pre>
						</div>
						<div class="diff-right">
							<div class="diff-label">After</div>
							<pre>${escapeHtml(fieldData.to) || '(empty)'}</pre>
						</div>
					</div>
				</div>
			`;
		});

		html += renderMediaChanges(data.media_changes);

		if (!html || html.trim() === '') {
			html = '<p>No changes detected in this revision.</p>';
		}

		$body.html(html);
	}

	function renderMediaChanges(mediaChanges) {
		if (!mediaChanges) {
			return '';
		}

		const { added, removed } = mediaChanges;
		const hasChanges = (added && added.length > 0) || (removed && removed.length > 0);

		if (!hasChanges) {
			return '';
		}

		let html = '<div class="edit-ledger-media-changes"><h3>Media Changes</h3>';

		if (removed && removed.length > 0) {
			html += '<div class="edit-ledger-media-section edit-ledger-media-removed">';
			html += '<div class="edit-ledger-media-label">Removed</div>';
			html += '<div class="edit-ledger-media-grid">';
			removed.forEach(function (item) {
				html += '<div class="edit-ledger-media-item">';
				if (item.type === 'image') {
					html += `<img src="${escapeHtml(item.src)}" alt="${escapeHtml(item.alt || item.name)}">`;
				} else if (item.type === 'youtube' && item.thumbnail) {
					html += `<img src="${escapeHtml(item.thumbnail)}" alt="${escapeHtml(item.name)}">`;
				} else {
					html += `<div class="edit-ledger-media-placeholder">
						${item.type === 'video' ? '🎬' : '▶️'}
						<span>${escapeHtml(item.name)}</span>
					</div>`;
				}
				html += `<div class="edit-ledger-media-name">${escapeHtml(item.name)}</div>`;
				html += '</div>';
			});
			html += '</div></div>';
		}

		if (added && added.length > 0) {
			html += '<div class="edit-ledger-media-section edit-ledger-media-added">';
			html += '<div class="edit-ledger-media-label">Added</div>';
			html += '<div class="edit-ledger-media-grid">';
			added.forEach(function (item) {
				html += '<div class="edit-ledger-media-item">';
				if (item.type === 'image') {
					html += `<img src="${escapeHtml(item.src)}" alt="${escapeHtml(item.alt || item.name)}">`;
				} else if (item.type === 'youtube' && item.thumbnail) {
					html += `<img src="${escapeHtml(item.thumbnail)}" alt="${escapeHtml(item.name)}">`;
				} else {
					html += `<div class="edit-ledger-media-placeholder">
						${item.type === 'video' ? '🎬' : '▶️'}
						<span>${escapeHtml(item.name)}</span>
					</div>`;
				}
				html += `<div class="edit-ledger-media-name">${escapeHtml(item.name)}</div>`;
				html += '</div>';
			});
			html += '</div></div>';
		}

		html += '</div>';
		return html;
	}

	function toggleDiffMode(mode) {
		const $sections = $('.edit-ledger-diff-section');

		if (mode === 'side-by-side') {
			$sections.find('.diff-inline').hide();
			$sections.find('.diff-side-by-side').show();
		} else {
			$sections.find('.diff-inline').show();
			$sections.find('.diff-side-by-side').hide();
		}
	}

	function escapeHtml(text) {
		if (!text) {
			return '';
		}
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

})(jQuery);
