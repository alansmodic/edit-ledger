/**
 * Block editor integration for Edit Ledger plugin.
 *
 * Two integration points:
 * 1. PluginDocumentSettingPanel — compact summary in the native document
 *    sidebar (latest revision type, changed fields, media indicator,
 *    "View in Revisions" button). This is the primary touch point.
 * 2. PluginSidebar — full revision timeline with expanded cards and
 *    inline media changes. Power-user detail view.
 */
(function () {
	'use strict';

	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem, PluginDocumentSettingPanel } = wp.editPost;
	const { useSelect } = wp.data;
	const { useState, useEffect } = wp.element;
	const {
		Button,
		PanelBody,
		Spinner,
	} = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const config = window.editLedgerData || {};
	const { strings } = config;

	/**
	 * Open WP 7.0's native visual revisions mode by clicking the
	 * built-in "Revisions" button in the document sidebar.
	 */
	function openNativeRevisions() {
		// WP 7.0 uses two variants of the revisions button:
		// - Public: .editor-post-last-revision__title (PanelBody context)
		// - Private: .editor-private-post-last-revision__button (PostPanelRow context)
		const revisionsBtn = document.querySelector(
			'.editor-post-last-revision__title, .editor-private-post-last-revision__button'
		);
		if (revisionsBtn) {
			revisionsBtn.click();
		}
	}

	/**
	 * Media Changes Component - Shows added/removed images visually.
	 */
	function MediaChanges({ mediaChanges }) {
		if (!mediaChanges) {
			return null;
		}

		const { added, removed } = mediaChanges;
		const hasChanges = (added && added.length > 0) || (removed && removed.length > 0);

		if (!hasChanges) {
			return null;
		}

		return wp.element.createElement(
			'div',
			{ className: 'edit-ledger-media-changes' },
			wp.element.createElement('h3', null, 'Media Changes'),
			removed && removed.length > 0 && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-media-section edit-ledger-media-removed' },
				wp.element.createElement('div', { className: 'edit-ledger-media-label' }, 'Removed'),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-media-grid' },
					removed.map(function (item, index) {
						return wp.element.createElement(
							'div',
							{ key: index, className: 'edit-ledger-media-item' },
							item.type === 'image' && wp.element.createElement('img', {
								src: item.src,
								alt: item.alt || item.name,
							}),
							item.type === 'youtube' && wp.element.createElement('img', {
								src: item.thumbnail,
								alt: item.name,
							}),
							(item.type === 'video' || item.type === 'vimeo') && wp.element.createElement(
								'div',
								{ className: 'edit-ledger-media-placeholder' },
								item.type === 'video' ? '🎬' : '▶️',
								wp.element.createElement('span', null, item.name)
							),
							wp.element.createElement('div', { className: 'edit-ledger-media-name' }, item.name)
						);
					})
				)
			),
			added && added.length > 0 && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-media-section edit-ledger-media-added' },
				wp.element.createElement('div', { className: 'edit-ledger-media-label' }, 'Added'),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-media-grid' },
					added.map(function (item, index) {
						return wp.element.createElement(
							'div',
							{ key: index, className: 'edit-ledger-media-item' },
							item.type === 'image' && wp.element.createElement('img', {
								src: item.src,
								alt: item.alt || item.name,
							}),
							item.type === 'youtube' && wp.element.createElement('img', {
								src: item.thumbnail,
								alt: item.name,
							}),
							(item.type === 'video' || item.type === 'vimeo') && wp.element.createElement(
								'div',
								{ className: 'edit-ledger-media-placeholder' },
								item.type === 'video' ? '🎬' : '▶️',
								wp.element.createElement('span', null, item.name)
							),
							wp.element.createElement('div', { className: 'edit-ledger-media-name' }, item.name)
						);
					})
				)
			)
		);
	}

	/**
	 * Timeline Card Component - Single revision entry.
	 *
	 * Shows author, timestamp, change type, and changed fields.
	 * Expanded view includes a "View in Revisions" button and
	 * inline media changes fetched from the diff endpoint.
	 */
	function TimelineCard({ revision, isExpanded, onToggle }) {
		var author = revision.author;
		var date_relative = revision.date_relative;
		var type = revision.type;
		var changes = revision.changes;

		var _useState = useState(null);
		var mediaData = _useState[0];
		var setMediaData = _useState[1];

		var _useLoadingState = useState(false);
		var isLoadingMedia = _useLoadingState[0];
		var setIsLoadingMedia = _useLoadingState[1];

		// AI summary state.
		var _summaryState = useState(revision.ai_summary || null);
		var localSummary = _summaryState[0];
		var setLocalSummary = _summaryState[1];

		var _summarizingState = useState(false);
		var isSummarizing = _summarizingState[0];
		var setIsSummarizing = _summarizingState[1];

		var _summaryErrorState = useState(null);
		var summaryError = _summaryErrorState[0];
		var setSummaryError = _summaryErrorState[1];

		function handleSummarize(e) {
			if (e) { e.stopPropagation(); }
			setIsSummarizing(true);
			setSummaryError(null);
			apiFetch({
				path: '/edit-ledger/v1/revisions/' + revision.id + '/summary',
				method: 'POST',
			})
				.then(function (data) {
					setLocalSummary(data.summary);
				})
				.catch(function () {
					setSummaryError(strings.summaryError || 'Could not generate summary.');
				})
				.finally(function () {
					setIsSummarizing(false);
				});
		}

		// Fetch media changes when card is expanded.
		useEffect(function () {
			if (!isExpanded || mediaData !== null) {
				return;
			}

			setIsLoadingMedia(true);
			apiFetch({ path: '/edit-ledger/v1/revisions/' + revision.id + '/diff' })
				.then(function (data) {
					setMediaData(data.media_changes || null);
				})
				.catch(function () {
					setMediaData(null);
				})
				.finally(function () {
					setIsLoadingMedia(false);
				});
		}, [isExpanded]);

		// Build a compact media summary string (e.g. "+2 images, -1 video").
		function getMediaSummary(media) {
			if (!media) {
				return null;
			}
			var parts = [];
			if (media.added && media.added.length > 0) {
				parts.push('+' + media.added.length + ' added');
			}
			if (media.removed && media.removed.length > 0) {
				parts.push('-' + media.removed.length + ' removed');
			}
			return parts.length > 0 ? parts.join(', ') : null;
		}

		var mediaSummary = getMediaSummary(mediaData);

		return wp.element.createElement(
			'div',
			{
				className: 'edit-ledger-card edit-ledger-card--' + type + (isExpanded ? ' is-expanded' : ''),
				onClick: onToggle,
			},
			wp.element.createElement(
				'div',
				{ className: 'edit-ledger-card__header' },
				wp.element.createElement('img', {
					src: author.avatar,
					alt: author.name,
					className: 'edit-ledger-card__avatar',
				}),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__meta' },
					wp.element.createElement('span', { className: 'edit-ledger-card__author' }, author.name),
					wp.element.createElement('span', { className: 'edit-ledger-card__time' }, date_relative + ' ' + strings.ago)
				),
				wp.element.createElement(
					'span',
					{ className: 'edit-ledger-card__type edit-ledger-card__type--' + type },
					type === 'autosave' ? strings.auto : strings.save
				)
			),
			isExpanded && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-card__details' },
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__changes' },
					strings.changed,
					' ',
					changes.length > 0 ? changes.join(', ') : strings.noChanges
				),
				// AI Summary block.
				localSummary && wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__ai-summary' },
					wp.element.createElement('span', { className: 'edit-ledger-card__ai-label' }, (strings.aiSummary || 'AI Summary') + ':'),
					' ' + localSummary
				),
				!localSummary && !isSummarizing && !summaryError && config.aiAvailable && wp.element.createElement(
					Button,
					{
						variant: 'tertiary',
						isSmall: true,
						onClick: handleSummarize,
					},
					strings.summarize || 'Summarize'
				),
				isSummarizing && wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__ai-loading' },
					wp.element.createElement(Spinner),
					wp.element.createElement('span', null, strings.summarizing || 'Summarizing...')
				),
				summaryError && wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__ai-error' },
					summaryError,
					' ',
					wp.element.createElement('a', {
						onClick: handleSummarize,
						role: 'button',
						tabIndex: 0,
					}, strings.retry || 'Retry')
				),
				mediaSummary && wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__media-summary' },
					'Media: ' + mediaSummary
				),
				isLoadingMedia && wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__media-loading' },
					wp.element.createElement(Spinner)
				),
				mediaData && wp.element.createElement(MediaChanges, {
					mediaChanges: mediaData,
				}),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__actions' },
					wp.element.createElement(Button, {
						variant: 'secondary',
						isSmall: true,
						onClick: function (e) {
							e.stopPropagation();
							openNativeRevisions();
						},
					}, strings.viewInRevisions || 'View in Revisions')
				)
			)
		);
	}

	/**
	 * Document Setting Panel — compact summary injected into the native
	 * document sidebar. Shows the latest revision's type badge, changed
	 * fields, media change count, and action buttons.
	 */
	function DocumentPanelSummary({ revisions, isLoading }) {
		if (isLoading) {
			return wp.element.createElement(
				PluginDocumentSettingPanel,
				{ name: 'edit-ledger-summary', title: strings.title, className: 'edit-ledger-doc-panel' },
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-doc-panel__loading' },
					wp.element.createElement(Spinner),
					wp.element.createElement('span', null, strings.loading)
				)
			);
		}

		if (!revisions || revisions.length === 0) {
			return wp.element.createElement(
				PluginDocumentSettingPanel,
				{ name: 'edit-ledger-summary', title: strings.title, className: 'edit-ledger-doc-panel' },
				wp.element.createElement(
					'p',
					{ className: 'edit-ledger-doc-panel__empty' },
					strings.noRevisions
				)
			);
		}

		var latest = revisions[0];
		var author = latest.author;
		var type = latest.type;
		var changes = latest.changes;

		// Lazy-load media changes for the latest revision.
		var _mediaState = useState(null);
		var mediaData = _mediaState[0];
		var setMediaData = _mediaState[1];

		var _mediaLoading = useState(false);
		var isLoadingMedia = _mediaLoading[0];
		var setIsLoadingMedia = _mediaLoading[1];

		useEffect(function () {
			if (!latest || mediaData !== null) {
				return;
			}
			setIsLoadingMedia(true);
			apiFetch({ path: '/edit-ledger/v1/revisions/' + latest.id + '/diff' })
				.then(function (data) {
					setMediaData(data.media_changes || { added: [], removed: [] });
				})
				.catch(function () {
					setMediaData({ added: [], removed: [] });
				})
				.finally(function () {
					setIsLoadingMedia(false);
				});
		}, [latest && latest.id]);

		var mediaAdded = mediaData && mediaData.added ? mediaData.added.length : 0;
		var mediaRemoved = mediaData && mediaData.removed ? mediaData.removed.length : 0;
		var hasMediaChanges = mediaAdded > 0 || mediaRemoved > 0;

		return wp.element.createElement(
			PluginDocumentSettingPanel,
			{ name: 'edit-ledger-summary', title: strings.title, className: 'edit-ledger-doc-panel' },
			// Latest revision row: avatar + author + type badge
			wp.element.createElement(
				'div',
				{ className: 'edit-ledger-doc-panel__latest' },
				wp.element.createElement('img', {
					src: author.avatar,
					alt: author.name,
					className: 'edit-ledger-doc-panel__avatar',
				}),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-doc-panel__info' },
					wp.element.createElement('span', { className: 'edit-ledger-doc-panel__author' }, author.name),
					wp.element.createElement('span', { className: 'edit-ledger-doc-panel__time' }, latest.date_relative + ' ' + strings.ago)
				),
				wp.element.createElement(
					'span',
					{ className: 'edit-ledger-card__type edit-ledger-card__type--' + type },
					type === 'autosave' ? strings.auto : strings.save
				)
			),
			// Changed fields
			changes.length > 0 && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-doc-panel__changes' },
				strings.changed + ' ' + changes.join(', ')
			),
			// AI Summary
			latest.ai_summary && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-doc-panel__ai-summary' },
				latest.ai_summary
			),
			// Media indicator
			isLoadingMedia && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-doc-panel__media-loading' },
				wp.element.createElement(Spinner)
			),
			hasMediaChanges && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-doc-panel__media' },
				mediaAdded > 0 && wp.element.createElement(
					'span',
					{ className: 'edit-ledger-doc-panel__media-added' },
					'+' + mediaAdded + ' media'
				),
				mediaRemoved > 0 && wp.element.createElement(
					'span',
					{ className: 'edit-ledger-doc-panel__media-removed' },
					'-' + mediaRemoved + ' media'
				)
			),
			// Revision count
			wp.element.createElement(
				'div',
				{ className: 'edit-ledger-doc-panel__footer' },
				wp.element.createElement(
					'span',
					{ className: 'edit-ledger-doc-panel__count' },
					revisions.length + ' ' + (strings.revisions || 'revisions')
				)
			),
			// Action buttons
			wp.element.createElement(
				'div',
				{ className: 'edit-ledger-doc-panel__actions' },
				wp.element.createElement(Button, {
					variant: 'secondary',
					isSmall: true,
					onClick: function () {
						openNativeRevisions();
					},
				}, strings.viewInRevisions),
				wp.element.createElement(Button, {
					variant: 'tertiary',
					isSmall: true,
					onClick: function () {
						wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-ledger/edit-ledger-sidebar');
					},
				}, strings.viewTimeline || 'Full Timeline')
			)
		);
	}

	/**
	 * Main Edit Ledger Plugin Component.
	 */
	function EditLedgerPlugin() {
		var _revState = useState([]);
		var revisions = _revState[0];
		var setRevisions = _revState[1];

		var _loadState = useState(false);
		var isLoading = _loadState[0];
		var setIsLoading = _loadState[1];

		var _expandState = useState(null);
		var expandedId = _expandState[0];
		var setExpandedId = _expandState[1];

		var postIdSelect = useSelect(function (select) {
			var editor = select('core/editor');
			return {
				postId: editor.getCurrentPostId(),
			};
		});
		var postId = postIdSelect.postId;

		useEffect(function () {
			if (!postId) {
				return;
			}

			setIsLoading(true);
			apiFetch({ path: '/edit-ledger/v1/posts/' + postId + '/revisions?per_page=50' })
				.then(function (response) {
					setRevisions(response);
				})
				.catch(function (err) {
					console.error('Failed to load revisions:', err);
					setRevisions([]);
				})
				.finally(function () {
					setIsLoading(false);
				});
		}, [postId]);

		var sidebarContent = wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement(
				PanelBody,
				{ title: strings.revisionHistory, initialOpen: true },
				isLoading && wp.element.createElement(
					'div',
					{ className: 'edit-ledger-loading' },
					wp.element.createElement(Spinner),
					wp.element.createElement('span', null, strings.loading)
				),
				!isLoading && revisions.length === 0 && wp.element.createElement(
					'p',
					{ className: 'edit-ledger-empty' },
					strings.noRevisions
				),
				!isLoading && revisions.length > 0 && wp.element.createElement(
					'div',
					{ className: 'edit-ledger-timeline' },
					revisions.map(function (revision) {
						return wp.element.createElement(TimelineCard, {
							key: revision.id,
							revision: revision,
							isExpanded: expandedId === revision.id,
							onToggle: function () {
								setExpandedId(expandedId === revision.id ? null : revision.id);
							},
						});
					})
				)
			)
		);

		return wp.element.createElement(
			wp.element.Fragment,
			null,
			// Document panel — compact summary in the native sidebar
			wp.element.createElement(DocumentPanelSummary, {
				revisions: revisions,
				isLoading: isLoading,
			}),
			// Full sidebar — power-user detail view
			wp.element.createElement(
				PluginSidebarMoreMenuItem,
				{ target: 'edit-ledger-sidebar' },
				strings.title
			),
			wp.element.createElement(
				PluginSidebar,
				{
					name: 'edit-ledger-sidebar',
					title: strings.title,
					icon: 'clock',
				},
				sidebarContent
			)
		);
	}

	registerPlugin('edit-ledger', {
		render: EditLedgerPlugin,
		icon: 'clock',
	});
})();
