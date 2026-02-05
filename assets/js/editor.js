/**
 * Block editor integration for Edit Ledger plugin.
 */
(function () {
	'use strict';

	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { useSelect } = wp.data;
	const { useState, useEffect } = wp.element;
	const {
		Button,
		PanelBody,
		Spinner,
		Modal,
	} = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const config = window.editLedgerData || {};
	const { strings } = config;

	/**
	 * Timeline Card Component - Single revision entry.
	 */
	function TimelineCard({ revision, isExpanded, onToggle, onViewDiff }) {
		const { author, date_relative, type, changes, id } = revision;

		return wp.element.createElement(
			'div',
			{
				className: `edit-ledger-card edit-ledger-card--${type}${isExpanded ? ' is-expanded' : ''}`,
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
					{ className: `edit-ledger-card__type edit-ledger-card__type--${type}` },
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
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-card__actions' },
					wp.element.createElement(Button, {
						variant: 'secondary',
						isSmall: true,
						onClick: (e) => {
							e.stopPropagation();
							onViewDiff(revision);
						},
					}, strings.viewDiff),
					wp.element.createElement(Button, {
						variant: 'tertiary',
						isSmall: true,
						onClick: (e) => {
							e.stopPropagation();
							window.open(config.postPermalink, '_blank');
						},
					}, strings.preview),
					wp.element.createElement(Button, {
						variant: 'tertiary',
						isSmall: true,
						onClick: (e) => {
							e.stopPropagation();
							window.open(`${config.siteUrl}/wp-admin/revision.php?revision=${revision.id}`, '_blank');
						},
					}, strings.wpRevisions)
				)
			)
		);
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
					removed.map((item, index) =>
						wp.element.createElement(
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
						)
					)
				)
			),
			added && added.length > 0 && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-media-section edit-ledger-media-added' },
				wp.element.createElement('div', { className: 'edit-ledger-media-label' }, 'Added'),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-media-grid' },
					added.map((item, index) =>
						wp.element.createElement(
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
						)
					)
				)
			)
		);
	}

	/**
	 * Diff View Component - Renders inline or side-by-side diff.
	 */
	function DiffView({ field, data, mode }) {
		const { from, to, diff_html } = data;
		const hasChanges = from !== to;
		const fieldLabel = field.charAt(0).toUpperCase() + field.slice(1);

		if (!hasChanges) {
			return null;
		}

		return wp.element.createElement(
			'div',
			{ className: 'edit-ledger-diff-section' },
			wp.element.createElement('h3', null, fieldLabel),
			mode === 'side-by-side'
				? wp.element.createElement(
					'div',
					{ className: 'edit-ledger-diff-side-by-side' },
					wp.element.createElement(
						'div',
						{ className: 'edit-ledger-diff-left' },
						wp.element.createElement('div', { className: 'edit-ledger-diff-label' }, 'Before'),
						wp.element.createElement('pre', null, from || '(empty)')
					),
					wp.element.createElement(
						'div',
						{ className: 'edit-ledger-diff-right' },
						wp.element.createElement('div', { className: 'edit-ledger-diff-label' }, 'After'),
						wp.element.createElement('pre', null, to || '(empty)')
					)
				)
				: wp.element.createElement(
					'div',
					{
						className: 'edit-ledger-diff-inline',
						dangerouslySetInnerHTML: { __html: diff_html },
					}
				)
		);
	}

	/**
	 * Comparison Header Component - Shows the two versions being compared.
	 */
	function ComparisonHeader({ comparison }) {
		if (!comparison) {
			return null;
		}

		const { from, to } = comparison;

		return wp.element.createElement(
			'div',
			{ className: 'edit-ledger-comparison-header' },
			wp.element.createElement(
				'div',
				{ className: 'edit-ledger-comparison-side edit-ledger-comparison-from' },
				wp.element.createElement('img', {
					src: from.author.avatar,
					alt: from.author.name,
					className: 'edit-ledger-comparison-avatar',
				}),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-comparison-info' },
					wp.element.createElement(
						'div',
						{ className: 'edit-ledger-comparison-author' },
						from.author.name
					),
					wp.element.createElement(
						'div',
						{ className: 'edit-ledger-comparison-date' },
						from.is_current ? 'Current version' : from.date_relative + ' ago'
					)
				),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-comparison-label' },
					'Before'
				)
			),
			wp.element.createElement(
				'div',
				{ className: 'edit-ledger-comparison-arrow' },
				'→'
			),
			wp.element.createElement(
				'div',
				{ className: 'edit-ledger-comparison-side edit-ledger-comparison-to' },
				wp.element.createElement('img', {
					src: to.author.avatar,
					alt: to.author.name,
					className: 'edit-ledger-comparison-avatar',
				}),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-comparison-info' },
					wp.element.createElement(
						'div',
						{ className: 'edit-ledger-comparison-author' },
						to.author.name
					),
					wp.element.createElement(
						'div',
						{ className: 'edit-ledger-comparison-date' },
						to.date_relative + ' ago'
					)
				),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-comparison-label' },
					'After'
				)
			)
		);
	}

	/**
	 * Diff Modal Component - Visual diff viewer.
	 */
	function DiffModal({ isOpen, onClose, diffData, isLoading, revisionId }) {
		const [viewMode, setViewMode] = useState('inline');
		const [isRestoring, setIsRestoring] = useState(false);

		const handleRestore = async () => {
			if (!revisionId) {
				return;
			}

			if (!window.confirm(strings.restoreConfirm)) {
				return;
			}

			setIsRestoring(true);

			try {
				const response = await apiFetch({
					path: `/edit-ledger/v1/revisions/${revisionId}/restore`,
					method: 'POST',
				});

				if (response.success) {
					window.alert(strings.restoreSuccess);
					window.location.reload();
				}
			} catch (err) {
				console.error('Failed to restore revision:', err);
				window.alert(strings.restoreError);
				setIsRestoring(false);
			}
		};

		if (!isOpen) {
			return null;
		}

		return wp.element.createElement(
			Modal,
			{
				title: strings.diffTitle,
				onRequestClose: onClose,
				className: 'edit-ledger-diff-modal',
				isFullScreen: true,
			},
			isLoading && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-diff-modal__loading' },
				wp.element.createElement(Spinner)
			),
			!isLoading && diffData && wp.element.createElement(
				'div',
				{ className: 'edit-ledger-diff-modal__content' },
				wp.element.createElement(ComparisonHeader, {
					comparison: diffData.comparison,
				}),
				wp.element.createElement(
					'div',
					{ className: 'edit-ledger-diff-modal__controls' },
					wp.element.createElement(Button, {
						variant: viewMode === 'inline' ? 'primary' : 'secondary',
						onClick: () => setViewMode('inline'),
					}, strings.inline),
					wp.element.createElement(Button, {
						variant: viewMode === 'side-by-side' ? 'primary' : 'secondary',
						onClick: () => setViewMode('side-by-side'),
					}, strings.sideBySide),
					wp.element.createElement('div', { style: { flex: 1 } }),
					wp.element.createElement(Button, {
						variant: 'primary',
						isDestructive: true,
						onClick: handleRestore,
						disabled: isRestoring,
					}, isRestoring ? strings.restoring : strings.restore)
				),
				diffData.fields && Object.entries(diffData.fields).map(([field, data]) =>
					wp.element.createElement(DiffView, {
						key: field,
						field,
						data,
						mode: viewMode,
					})
				),
				wp.element.createElement(MediaChanges, {
					mediaChanges: diffData.media_changes,
				})
			)
		);
	}

	/**
	 * Main Edit Ledger Plugin Component.
	 */
	function EditLedgerPlugin() {
		const [revisions, setRevisions] = useState([]);
		const [isLoading, setIsLoading] = useState(false);
		const [expandedId, setExpandedId] = useState(null);
		const [diffModal, setDiffModal] = useState({
			isOpen: false,
			data: null,
			isLoading: false,
			revisionId: null,
		});

		const { postId } = useSelect((select) => {
			const editor = select('core/editor');
			return {
				postId: editor.getCurrentPostId(),
			};
		});

		useEffect(() => {
			if (!postId) {
				return;
			}

			setIsLoading(true);
			apiFetch({ path: `/edit-ledger/v1/posts/${postId}/revisions?per_page=50` })
				.then((response) => {
					setRevisions(response);
				})
				.catch((err) => {
					console.error('Failed to load revisions:', err);
					setRevisions([]);
				})
				.finally(() => {
					setIsLoading(false);
				});
		}, [postId]);

		useEffect(() => {
			const interceptRevisionsLink = () => {
				const selectors = [
					'.editor-post-last-revision__title',
					'.edit-post-last-revision__title',
					'[class*="post-last-revision"] a',
					'[class*="post-last-revision"] button',
					'a[href*="revision.php"]',
				];

				const revisionsLinks = document.querySelectorAll(selectors.join(', '));

				revisionsLinks.forEach((link) => {
					if (link.dataset.editLedgerHooked) {
						return;
					}
					link.dataset.editLedgerHooked = 'true';

					link.addEventListener('click', (e) => {
						e.preventDefault();
						e.stopPropagation();
						wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-ledger/edit-ledger-sidebar');
					}, true);
				});

				const sidebar = document.querySelector('.edit-post-sidebar, .interface-complementary-area');
				if (sidebar) {
					const allClickables = sidebar.querySelectorAll('a, button');
					allClickables.forEach((el) => {
						if (el.dataset.editLedgerHooked) {
							return;
						}
						const text = el.textContent || '';
						if (text.toLowerCase().includes('revision') && !text.toLowerCase().includes('ledger')) {
							el.dataset.editLedgerHooked = 'true';
							el.addEventListener('click', (e) => {
								e.preventDefault();
								e.stopPropagation();
								wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-ledger/edit-ledger-sidebar');
							}, true);
						}
					});
				}
			};

			interceptRevisionsLink();
			const timeoutId = setTimeout(interceptRevisionsLink, 1000);

			const observer = new MutationObserver(() => {
				interceptRevisionsLink();
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true,
			});

			return () => {
				clearTimeout(timeoutId);
				observer.disconnect();
			};
		}, []);

		const handleViewDiff = async (revision) => {
			setDiffModal({ isOpen: true, data: null, isLoading: true, revisionId: revision.id });

			try {
				const data = await apiFetch({
					path: `/edit-ledger/v1/revisions/${revision.id}/diff`,
				});
				setDiffModal({ isOpen: true, data, isLoading: false, revisionId: revision.id });
			} catch (err) {
				console.error('Failed to load diff:', err);
				setDiffModal({ isOpen: true, data: null, isLoading: false, revisionId: revision.id });
			}
		};

		const sidebarContent = wp.element.createElement(
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
					revisions.map((revision) =>
						wp.element.createElement(TimelineCard, {
							key: revision.id,
							revision,
							isExpanded: expandedId === revision.id,
							onToggle: () => setExpandedId(expandedId === revision.id ? null : revision.id),
							onViewDiff: handleViewDiff,
						})
					)
				)
			)
		);

		return wp.element.createElement(
			wp.element.Fragment,
			null,
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
			),
			wp.element.createElement(DiffModal, {
				isOpen: diffModal.isOpen,
				onClose: () => setDiffModal({ isOpen: false, data: null, isLoading: false, revisionId: null }),
				diffData: diffModal.data,
				isLoading: diffModal.isLoading,
				revisionId: diffModal.revisionId,
			})
		);
	}

	registerPlugin('edit-ledger', {
		render: EditLedgerPlugin,
		icon: 'clock',
	});
})();
