<?php
/**
 * Template: Menu Manager admin page.
 *
 * @package SwiftMenuDuplicator
 *
 * @var \SwiftMenuDuplicator\Admin\Menu_Table      $table                Prepared menus table.
 * @var \SwiftMenuDuplicator\Admin\Navigation_Table $navigation_table     Prepared navigation table, or null.
 * @var bool                                        $has_block_navigation  Whether to offer the navigation tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'menus'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$transient_key = 'swmd_import_state_' . get_current_user_id();
$import_state  = get_transient( $transient_key );
$deleted_count = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$duplicated    = isset( $_GET['duplicated'] ) ? absint( $_GET['duplicated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$nav_created   = isset( $_GET['nav_duplicated'] ) ? absint( $_GET['nav_duplicated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$nav_trashed   = isset( $_GET['nav_trashed'] ) ? absint( $_GET['nav_trashed'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$restored      = isset( $_GET['restored'] ) ? absint( $_GET['restored'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( $import_state ) {
	delete_transient( $transient_key );
}
?>
<div class="wrap swmd-manager-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Menu Manager', 'swift-menu-duplicator' ); ?></h1>
	<?php // action=edit&menu=0 is core's "create a new menu" screen; plain nav-menus.php just opens the last edited menu. ?>
	<a href="<?php echo esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '+ New Menu', 'swift-menu-duplicator' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( $deleted_count > 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of deleted menus */
					esc_html( _n( '%d menu deleted.', '%d menus deleted.', $deleted_count, 'swift-menu-duplicator' ) ),
					(int) $deleted_count
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $duplicated > 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of duplicated menus */
					esc_html( _n( '%d menu duplicated.', '%d menus duplicated.', $duplicated, 'swift-menu-duplicator' ) ),
					(int) $duplicated
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $restored > 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of restored menus */
					esc_html( _n( '%d menu restored. Check its theme locations.', '%d menus restored. Check their theme locations.', $restored, 'swift-menu-duplicator' ) ),
					(int) $restored
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $nav_created > 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of duplicated navigation menus */
					esc_html( _n( '%d navigation menu duplicated.', '%d navigation menus duplicated.', $nav_created, 'swift-menu-duplicator' ) ),
					(int) $nav_created
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $nav_trashed > 0 ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of navigation menus moved to trash */
					esc_html( _n( '%d navigation menu moved to the trash.', '%d navigation menus moved to the trash.', $nav_trashed, 'swift-menu-duplicator' ) ),
					(int) $nav_trashed
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<!-- ── Tab navigation ──────────────────────────────────── -->
	<nav class="nav-tab-wrapper swmd-tab-nav">
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=swmd-menu-manager&tab=menus' ) ); ?>"
			class="nav-tab <?php echo esc_attr( 'menus' === $active_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'All Menus', 'swift-menu-duplicator' ); ?>
		</a>
		<?php if ( $has_block_navigation ) : ?>
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=swmd-menu-manager&tab=navigation' ) ); ?>"
			class="nav-tab <?php echo esc_attr( 'navigation' === $active_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'Navigation (Block)', 'swift-menu-duplicator' ); ?>
		</a>
		<?php endif; ?>
		<?php if ( is_multisite() && current_user_can( 'manage_network' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=swmd-menu-manager&tab=multisite' ) ); ?>"
			class="nav-tab <?php echo esc_attr( 'multisite' === $active_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'Copy to Site', 'swift-menu-duplicator' ); ?>
		</a>
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=swmd-menu-manager&tab=import' ) ); ?>"
			class="nav-tab <?php echo esc_attr( 'import' === $active_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'Import JSON', 'swift-menu-duplicator' ); ?>
		</a>
		<?php
		/*
		 * Last, and deliberately so. The four before it are the work; this one
		 * is the author's other plugins, and a promotional tab that sits before
		 * the task somebody opened the screen to do is an interruption.
		 */
		?>
		<a href="<?php echo esc_url( admin_url( 'themes.php?page=swmd-menu-manager&tab=plugins' ) ); ?>"
			class="nav-tab <?php echo esc_attr( 'plugins' === $active_tab ? 'nav-tab-active' : '' ); ?>">
			<?php esc_html_e( 'Our Plugins', 'swift-menu-duplicator' ); ?>
		</a>
	</nav>

	<!-- ── All Menus tab ───────────────────────────────────── -->
	<?php if ( 'menus' === $active_tab ) : ?>
	<div class="swmd-tab-panel">
		<form id="swmd-menu-table-form" method="post">
			<?php wp_nonce_field( 'swmd_bulk_delete', 'swmd_bulk_nonce' ); ?>
			<?php
			$table->display();
			?>
		</form>
	</div>

	<!-- ── Import JSON tab ─────────────────────────────────── -->
	<?php elseif ( 'import' === $active_tab ) : ?>
	<div class="swmd-tab-panel">

		<?php if ( ! empty( $import_state['error'] ) ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $import_state['error'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $import_state['success'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: menu name, 2: edit link */
							__( 'Imported "%1$s" successfully. %2$s', 'swift-menu-duplicator' ),
							esc_html( $import_state['menu_name'] ?? '' ),
							sprintf(
								'<a href="%s">%s</a>',
								esc_url( admin_url( 'nav-menus.php?action=edit&menu=' . absint( $import_state['new_menu_id'] ?? 0 ) ) ),
								esc_html__( 'Edit menu →', 'swift-menu-duplicator' )
							)
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $import_state['preview'] ) ) : ?>
		<!-- Preview panel -->
		<div class="swmd-import-preview">
			<h2><?php esc_html_e( 'Import Preview', 'swift-menu-duplicator' ); ?></h2>
			<div class="swmd-preview-table-scroll">
			<table class="widefat swmd-preview-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'swift-menu-duplicator' ); ?></th>
						<th><?php esc_html_e( 'Type', 'swift-menu-duplicator' ); ?></th>
						<th><?php esc_html_e( 'URL / Object', 'swift-menu-duplicator' ); ?></th>
						<th><?php esc_html_e( 'Parent ID', 'swift-menu-duplicator' ); ?></th>
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
			</div>
			<p class="description">
				<?php
				printf(
					/* translators: %d: item count */
					esc_html__( '%1$d items will be imported as "%2$s".', 'swift-menu-duplicator' ),
					(int) $import_state['preview']['item_count'],
					esc_html( $import_state['preview']['menu_name'] )
				);
				?>
			</p>

			<!-- Confirm import form -->
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'swmd_import_menu' ); ?>
				<input type="hidden" name="_swmd_import_action" value="import" />
				<input type="hidden" name="swmd_json_data" value="<?php echo esc_attr( $import_state['json_data'] ?? '' ); ?>" />
				<input type="hidden" name="swmd_find"    value="<?php echo esc_attr( $import_state['find'] ?? '' ); ?>" />
				<input type="hidden" name="swmd_replace" value="<?php echo esc_attr( $import_state['replace'] ?? '' ); ?>" />
				<input type="hidden" name="swmd_menu_name" value="<?php echo esc_attr( $import_state['name'] ?? '' ); ?>" />
				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Confirm Import', 'swift-menu-duplicator' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'themes.php?page=swmd-menu-manager&tab=import' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'swift-menu-duplicator' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php else : ?>

		<!-- Upload form -->
		<div class="swmd-import-form-wrap">
			<h2><?php esc_html_e( 'Import Menu from JSON', 'swift-menu-duplicator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload a JSON file exported by Swift Menu Duplicator. Optionally replace URLs before importing to adapt menus from a different environment.', 'swift-menu-duplicator' ); ?>
			</p>

			<form method="post" enctype="multipart/form-data" class="swmd-import-form">
				<?php wp_nonce_field( 'swmd_import_menu' ); ?>
				<input type="hidden" name="_swmd_import_action" value="preview" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="swmd_json_file"><?php esc_html_e( 'JSON File', 'swift-menu-duplicator' ); ?></label>
						</th>
						<td>
							<input type="file" id="swmd_json_file" name="swmd_json_file" accept=".json" />
							<p class="description">
								<?php esc_html_e( 'Upload a file, or paste the JSON below.', 'swift-menu-duplicator' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="swmd_json_paste"><?php esc_html_e( 'Or Paste JSON', 'swift-menu-duplicator' ); ?></label>
						</th>
						<td>
							<textarea id="swmd_json_paste" name="swmd_json_paste" rows="8" class="large-text code"
									placeholder="<?php esc_attr_e( '{ "menu": { … }, "items": [ … ] }', 'swift-menu-duplicator' ); ?>"></textarea>
							<p class="description">
								<?php esc_html_e( 'Ignored when a file is uploaded.', 'swift-menu-duplicator' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="swmd_menu_name_import"><?php esc_html_e( 'Menu Name', 'swift-menu-duplicator' ); ?></label>
						</th>
						<td>
							<input type="text" id="swmd_menu_name_import" name="swmd_menu_name" class="regular-text"
									placeholder="<?php esc_attr_e( 'Leave blank to use name from file', 'swift-menu-duplicator' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'URL Replacement', 'swift-menu-duplicator' ); ?></th>
						<td>
							<p>
								<label for="swmd_find"><?php esc_html_e( 'Find:', 'swift-menu-duplicator' ); ?></label>
								<input type="text" id="swmd_find" name="swmd_find" class="regular-text"
										placeholder="https://staging.example.com" />
							</p>
							<p>
								<label for="swmd_replace"><?php esc_html_e( 'Replace:', 'swift-menu-duplicator' ); ?></label>
								<input type="text" id="swmd_replace" name="swmd_replace" class="regular-text"
										placeholder="https://example.com" />
							</p>
							<p class="description">
								<?php esc_html_e( 'Applied to all custom link item URLs in the imported menu. Leave both fields blank to skip URL replacement.', 'swift-menu-duplicator' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Preview Import', 'swift-menu-duplicator' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php endif; ?>
	</div>

	<!-- ── Navigation (Block) tab ──────────────────────────── -->
	<?php elseif ( 'navigation' === $active_tab && $has_block_navigation && $navigation_table ) : ?>
	<div class="swmd-tab-panel">
		<h2><?php esc_html_e( 'Block Navigation Menus', 'swift-menu-duplicator' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'These are the navigation menus a block theme renders. Duplicating one copies its block markup; edit the copy in the Site Editor.', 'swift-menu-duplicator' ); ?>
		</p>
		<form id="swmd-navigation-table-form" method="post">
			<?php wp_nonce_field( 'swmd_bulk_delete', 'swmd_bulk_nonce' ); ?>
			<?php $navigation_table->display(); ?>
		</form>
	</div>

	<!-- ── Copy to Site tab (multisite only) ───────────────── -->
	<?php elseif ( 'multisite' === $active_tab && is_multisite() && current_user_can( 'manage_network' ) ) : ?>
	<div class="swmd-tab-panel">
		<h2><?php esc_html_e( 'Copy Menu to Another Site', 'swift-menu-duplicator' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Copies the selected menu — including all items and hierarchy — to another site in this network. The copied menu is not assigned to any theme location on the destination site.', 'swift-menu-duplicator' ); ?>
		</p>
		<div id="swmd-copy-to-site-form">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="swmd-copy-source-menu"><?php esc_html_e( 'Source Menu', 'swift-menu-duplicator' ); ?></label>
					</th>
					<td>
						<select id="swmd-copy-source-menu" class="regular-text">
							<option value=""><?php esc_html_e( '— Select a menu —', 'swift-menu-duplicator' ); ?></option>
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
						<label for="swmd-copy-target-site"><?php esc_html_e( 'Destination Site', 'swift-menu-duplicator' ); ?></label>
					</th>
					<td>
						<select id="swmd-copy-target-site" class="regular-text">
							<option value=""><?php esc_html_e( '— Select a site —', 'swift-menu-duplicator' ); ?></option>
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

								$site_name = '' !== $site->blogname ? $site->blogname : sprintf(
									/* translators: %d: numeric site ID on a multisite network */
									__( 'Site %d', 'swift-menu-duplicator' ),
									$blog_id
								);

								echo '<option value="' . absint( $blog_id ) . '">' . esc_html( $site_name ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="swmd-copy-menu-name"><?php esc_html_e( 'Menu Name on Destination', 'swift-menu-duplicator' ); ?></label>
					</th>
					<td>
						<input type="text" id="swmd-copy-menu-name" class="regular-text"
								placeholder="<?php esc_attr_e( 'Leave blank to keep original name', 'swift-menu-duplicator' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'URL Replacement', 'swift-menu-duplicator' ); ?></th>
					<td>
						<p>
							<label for="swmd-copy-find"><?php esc_html_e( 'Find:', 'swift-menu-duplicator' ); ?></label>
							<input type="text" id="swmd-copy-find" class="regular-text"
									placeholder="<?php echo esc_attr( home_url() ); ?>" />
						</p>
						<p>
							<label for="swmd-copy-replace"><?php esc_html_e( 'Replace:', 'swift-menu-duplicator' ); ?></label>
							<input type="text" id="swmd-copy-replace" class="regular-text"
									placeholder="https://other-site.example.com" />
						</p>
						<p class="description">
							<?php esc_html_e( 'Applied to custom link item URLs on the destination site. Leave both fields blank to copy URLs unchanged.', 'swift-menu-duplicator' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" id="swmd-copy-to-site-submit" class="button button-primary">
					<?php esc_html_e( 'Copy Menu', 'swift-menu-duplicator' ); ?>
				</button>
			</p>
			<div id="swmd-copy-result"></div>
		</div>
	</div>

	<!-- ── Our Plugins tab ─────────────────────────────────── -->
	<?php elseif ( 'plugins' === $active_tab ) : ?>
		<?php
		/*
		 * `$plugins` and `$error` are what the partial reads. Unpacked here
		 * rather than inside it, so the partial stays a plain template with two
		 * documented variables instead of reaching into a differently-named
		 * array it did not ask for.
		 */
		$plugins = $our_plugins['plugins'];
		$error   = $our_plugins['error'];

		include SWIFT_MENU_DUPLICATOR_DIR . 'templates/admin/our-plugins.php';
		?>
	<?php endif; ?>

</div><!-- .wrap -->
