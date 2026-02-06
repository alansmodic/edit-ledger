<?php
/**
 * Admin page template for Edit Ledger.
 *
 * @package Edit_Ledger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Edit Ledger', 'edit-ledger' ); ?></h1>

	<p><?php esc_html_e( 'View and compare recent revisions across all posts.', 'edit-ledger' ); ?></p>

	<!-- Filters -->
	<div class="edit-ledger-filters">
		<form id="edit-ledger-filter-form">
			<select id="filter-author" name="author">
				<option value=""><?php esc_html_e( 'All Authors', 'edit-ledger' ); ?></option>
				<?php
				$users = get_users( array( 'capability' => 'edit_posts' ) );
				foreach ( $users as $user ) :
					?>
					<option value="<?php echo esc_attr( $user->ID ); ?>">
						<?php echo esc_html( $user->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="filter-after" class="screen-reader-text"><?php esc_html_e( 'After date', 'edit-ledger' ); ?></label>
			<input type="date" id="filter-after" name="after" placeholder="<?php esc_attr_e( 'After', 'edit-ledger' ); ?>">

			<label for="filter-before" class="screen-reader-text"><?php esc_html_e( 'Before date', 'edit-ledger' ); ?></label>
			<input type="date" id="filter-before" name="before" placeholder="<?php esc_attr_e( 'Before', 'edit-ledger' ); ?>">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'edit-ledger' ); ?></button>
			<button type="button" id="filter-reset" class="button"><?php esc_html_e( 'Reset', 'edit-ledger' ); ?></button>
		</form>
	</div>

	<!-- Revisions Table -->
	<table class="wp-list-table widefat fixed striped" id="edit-ledger-table">
		<thead>
			<tr>
				<th class="column-post" style="width: 25%;"><?php esc_html_e( 'Post', 'edit-ledger' ); ?></th>
				<th class="column-author" style="width: 20%;"><?php esc_html_e( 'Author', 'edit-ledger' ); ?></th>
				<th class="column-date" style="width: 20%;"><?php esc_html_e( 'Date', 'edit-ledger' ); ?></th>
				<th class="column-type" style="width: 10%;"><?php esc_html_e( 'Type', 'edit-ledger' ); ?></th>
				<th class="column-actions" style="width: 25%;"><?php esc_html_e( 'Actions', 'edit-ledger' ); ?></th>
			</tr>
		</thead>
		<tbody id="edit-ledger-list">
			<tr class="edit-ledger-loading">
				<td colspan="5"><?php esc_html_e( 'Loading revisions...', 'edit-ledger' ); ?></td>
			</tr>
		</tbody>
	</table>

	<div id="edit-ledger-pagination" class="tablenav bottom">
		<div class="tablenav-pages">
			<button type="button" id="load-more" class="button" style="display: none;">
				<?php esc_html_e( 'Load More', 'edit-ledger' ); ?>
			</button>
		</div>
	</div>

	<!-- Diff Modal -->
	<div id="edit-ledger-modal" class="edit-ledger-modal" style="display: none;">
		<div class="edit-ledger-modal__overlay"></div>
		<div class="edit-ledger-modal__content">
			<div class="edit-ledger-modal__header">
				<h2><?php esc_html_e( 'Revision Diff', 'edit-ledger' ); ?></h2>
				<button type="button" class="edit-ledger-modal__close" aria-label="<?php esc_attr_e( 'Close', 'edit-ledger' ); ?>">&times;</button>
			</div>
			<div class="edit-ledger-modal__controls">
				<button type="button" class="button button-primary" data-mode="inline"><?php esc_html_e( 'Inline', 'edit-ledger' ); ?></button>
				<button type="button" class="button" data-mode="side-by-side"><?php esc_html_e( 'Side by Side', 'edit-ledger' ); ?></button>
			</div>
			<div class="edit-ledger-modal__body">
				<!-- Diff content loaded here -->
			</div>
		</div>
	</div>
</div>
