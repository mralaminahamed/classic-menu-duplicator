<?php
/**
 * Template: Menu Manager admin page.
 *
 * @package ClassicMenuDuplicator
 *
 * @var \ClassicMenuDuplicator\Menu_Table $table   Prepared list table instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'menus'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$transient_key = 'cmd_import_state_' . get_current_user_id();
$import_state  = get_transient( $transient_key );
$deleted_count = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( $import_state ) {
	delete_transient( $transient_key );
}
?>
<div class="wrap cmd-manager-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Menu Manager', 'classic-menu-duplicator' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '+ New Menu', 'classic-menu-duplicator' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( $deleted_count > 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of deleted menus */
					esc_html( _n( '%d menu deleted.', '%d menus deleted.', $deleted_count, 'classic-menu-duplicator' ) ),
					(int) $deleted_count
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<!-- ── Tab navigation ──────────────────────────────────── -->
	<nav class="nav-tab-wrapper cmd-tab-nav">
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=cmd-menu-manager&tab=menus' ) ); ?>"
			class="nav-tab <?php echo 'menus' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'All Menus', 'classic-menu-duplicator' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=cmd-menu-manager&tab=import' ) ); ?>"
			class="nav-tab <?php echo 'import' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Import JSON', 'classic-menu-duplicator' ); ?>
		</a>
		<?php if ( is_multisite() && current_user_can( 'manage_network' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=cmd-menu-manager&tab=multisite' ) ); ?>"
			class="nav-tab <?php echo 'multisite' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Copy to Site', 'classic-menu-duplicator' ); ?>
		</a>
		<?php endif; ?>
	</nav>

	<!-- ── All Menus tab ───────────────────────────────────── -->
	<?php if ( 'menus' === $active_tab ) : ?>
	<div class="cmd-tab-panel">
		<form id="cmd-menu-table-form" method="post">
			<?php wp_nonce_field( 'cmd_bulk_delete', 'cmd_bulk_nonce' ); ?>
			<input type="hidden" name="action" value="cmd_bulk_delete" />
			<?php
			$table->display();
			?>
		</form>
	</div>

	<!-- ── Import JSON tab ─────────────────────────────────── -->
	<?php elseif ( 'import' === $active_tab ) : ?>
	<div class="cmd-tab-panel">

		<?php if ( ! empty( $import_state['error'] ) ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $import_state['error'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $import_state['success'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: menu name, 2: edit link */
						esc_html__( 'Imported "%1$s" successfully. %2$s', 'classic-menu-duplicator' ),
						esc_html( $import_state['menu_name'] ?? '' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'nav-menus.php?action=edit&menu=' . absint( $import_state['new_menu_id'] ?? 0 ) ) ),
							esc_html__( 'Edit menu →', 'classic-menu-duplicator' )
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $import_state['preview'] ) ) : ?>
		<!-- Preview panel -->
		<div class="cmd-import-preview">
			<h2><?php esc_html_e( 'Import Preview', 'classic-menu-duplicator' ); ?></h2>
			<table class="widefat cmd-preview-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'classic-menu-duplicator' ); ?></th>
						<th><?php esc_html_e( 'Type', 'classic-menu-duplicator' ); ?></th>
						<th><?php esc_html_e( 'URL / Object', 'classic-menu-duplicator' ); ?></th>
						<th><?php esc_html_e( 'Parent ID', 'classic-menu-duplicator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $import_state['preview']['items'] as $prev_item ) : ?>
					<tr>
						<td><?php echo esc_html( $prev_item['title'] ); ?></td>
						<td><?php echo esc_html( $prev_item['type'] ); ?></td>
						<td><?php echo esc_html( $prev_item['url'] ?: '—' ); ?></td>
						<td><?php echo esc_html( $prev_item['parent'] > 0 ? $prev_item['parent'] : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php
				printf(
					/* translators: %d: item count */
					esc_html__( '%1$d items will be imported as "%2$s".', 'classic-menu-duplicator' ),
					(int) $import_state['preview']['item_count'],
					esc_html( $import_state['preview']['menu_name'] )
				);
				?>
			</p>

			<!-- Confirm import form -->
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'cmd_import_menu' ); ?>
				<input type="hidden" name="_cmd_import_action" value="import" />
				<input type="hidden" name="cmd_json_data" value="<?php echo esc_attr( $import_state['json_data'] ?? '' ); ?>" />
				<input type="hidden" name="cmd_find"    value="<?php echo esc_attr( $import_state['find'] ?? '' ); ?>" />
				<input type="hidden" name="cmd_replace" value="<?php echo esc_attr( $import_state['replace'] ?? '' ); ?>" />
				<input type="hidden" name="cmd_menu_name" value="<?php echo esc_attr( $import_state['name'] ?? '' ); ?>" />
				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Confirm Import', 'classic-menu-duplicator' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'themes.php?page=cmd-menu-manager&tab=import' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'classic-menu-duplicator' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php else : ?>

		<!-- Upload form -->
		<div class="cmd-import-form-wrap">
			<h2><?php esc_html_e( 'Import Menu from JSON', 'classic-menu-duplicator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload a JSON file exported by Classic Menu Duplicator. Optionally replace URLs before importing to adapt menus from a different environment.', 'classic-menu-duplicator' ); ?>
			</p>

			<form method="post" enctype="multipart/form-data" class="cmd-import-form">
				<?php wp_nonce_field( 'cmd_import_menu' ); ?>
				<input type="hidden" name="_cmd_import_action" value="preview" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cmd_json_file"><?php esc_html_e( 'JSON File', 'classic-menu-duplicator' ); ?></label>
						</th>
						<td>
							<input type="file" id="cmd_json_file" name="cmd_json_file" accept=".json" required />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cmd_menu_name_import"><?php esc_html_e( 'Menu Name', 'classic-menu-duplicator' ); ?></label>
						</th>
						<td>
							<input type="text" id="cmd_menu_name_import" name="cmd_menu_name" class="regular-text"
									placeholder="<?php esc_attr_e( 'Leave blank to use name from file', 'classic-menu-duplicator' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'URL Replacement', 'classic-menu-duplicator' ); ?></th>
						<td>
							<p>
								<label for="cmd_find"><?php esc_html_e( 'Find:', 'classic-menu-duplicator' ); ?></label>
								<input type="text" id="cmd_find" name="cmd_find" class="regular-text"
										placeholder="https://staging.example.com" />
							</p>
							<p>
								<label for="cmd_replace"><?php esc_html_e( 'Replace:', 'classic-menu-duplicator' ); ?></label>
								<input type="text" id="cmd_replace" name="cmd_replace" class="regular-text"
										placeholder="https://example.com" />
							</p>
							<p class="description">
								<?php esc_html_e( 'Applied to all custom link item URLs in the imported menu. Leave both fields blank to skip URL replacement.', 'classic-menu-duplicator' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Preview Import', 'classic-menu-duplicator' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<!-- ── Copy to Site tab (multisite only) ───────────────── -->
	<?php elseif ( 'multisite' === $active_tab && is_multisite() && current_user_can( 'manage_network' ) ) : ?>
	<div class="cmd-tab-panel">
		<h2><?php esc_html_e( 'Copy Menu to Another Site', 'classic-menu-duplicator' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Copies the selected menu — including all items and hierarchy — to another site in this network. The copied menu is not assigned to any theme location on the destination site.', 'classic-menu-duplicator' ); ?>
		</p>
		<div id="cmd-copy-to-site-form">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="cmd-copy-source-menu"><?php esc_html_e( 'Source Menu', 'classic-menu-duplicator' ); ?></label>
					</th>
					<td>
						<select id="cmd-copy-source-menu" class="regular-text">
							<option value=""><?php esc_html_e( '— Select a menu —', 'classic-menu-duplicator' ); ?></option>
							<?php foreach ( wp_get_nav_menus() as $m ) : ?>
								<option value="<?php echo esc_attr( (string) $m->term_id ); ?>">
									<?php echo esc_html( $m->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cmd-copy-target-site"><?php esc_html_e( 'Destination Site', 'classic-menu-duplicator' ); ?></label>
					</th>
					<td>
						<select id="cmd-copy-target-site" class="regular-text">
							<option value=""><?php esc_html_e( '— Select a site —', 'classic-menu-duplicator' ); ?></option>
							<?php
							$sites = get_sites(
								array(
									'number'   => 100,
									'spam'     => 0,
									'deleted'  => 0,
									'archived' => 0,
								)
							);

							foreach ( $sites as $site ) {
								$blog_id = (int) $site->blog_id;

								if ( $blog_id === get_current_blog_id() ) {
									continue;
								}

								$details = get_blog_details( $blog_id );
								printf(
									'<option value="%d">%s</option>',
									$blog_id,
									esc_html( $details->blogname ?? "Site {$blog_id}" )
								);
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cmd-copy-menu-name"><?php esc_html_e( 'Menu Name on Destination', 'classic-menu-duplicator' ); ?></label>
					</th>
					<td>
						<input type="text" id="cmd-copy-menu-name" class="regular-text"
								placeholder="<?php esc_attr_e( 'Leave blank to keep original name', 'classic-menu-duplicator' ); ?>" />
					</td>
				</tr>
			</table>
			<p>
				<button type="button" id="cmd-copy-to-site-submit" class="button button-primary">
					<?php esc_html_e( 'Copy Menu', 'classic-menu-duplicator' ); ?>
				</button>
			</p>
			<div id="cmd-copy-result"></div>
		</div>
	</div>
	<?php endif; ?>

</div><!-- .wrap -->
