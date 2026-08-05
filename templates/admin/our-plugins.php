<?php
/**
 * Partial: the Menu Manager's "Our Plugins" tab.
 *
 * Included by templates/admin/menu-manager.php, so it opens no wrapper and
 * prints no page heading of its own.
 *
 * @package SwiftMenuDuplicator
 *
 * @var array<int, array<string, mixed>> $plugins Plugins with their state on this site.
 * @var string                           $error   Why the directory listing is incomplete, if it is.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="swmd-our-plugins">
	<p class="description">
		<?php esc_html_e( 'Other plugins from the people who made this one, listed straight from the WordPress.org directory.', 'swift-menu-duplicator' ); ?>
	</p>

	<?php if ( '' !== $error ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'The plugin directory could not be reached, so this list may be incomplete.', 'swift-menu-duplicator' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $plugins ) ) : ?>
		<p><?php esc_html_e( 'Nothing to show yet.', 'swift-menu-duplicator' ); ?></p>
	<?php else : ?>
		<div class="swmd-plugin-grid">
			<?php foreach ( $plugins as $swmd_plugin ) : ?>
				<div class="swmd-plugin-card">
					<div class="swmd-plugin-head">
						<?php if ( '' !== $swmd_plugin['icon'] ) : ?>
							<?php
							/*
							 * Decorative: the plugin's name is right beside it, and
							 * a screen reader repeating it is noise. The directory
							 * reports an icon URL even for plugins that never
							 * uploaded one, and those 404 — hence the onerror,
							 * which hides the box rather than leaving a torn-paper
							 * glyph in the grid.
							 */
							?>
							<img
								src="<?php echo esc_url( $swmd_plugin['icon'] ); ?>"
								alt=""
								class="swmd-plugin-icon"
								loading="lazy"
								onerror="this.style.display='none'"
							>
						<?php endif; ?>

						<div class="swmd-plugin-heading">
							<h2 class="swmd-plugin-name"><?php echo esc_html( $swmd_plugin['name'] ); ?></h2>

							<p class="swmd-plugin-meta">
								<span><?php echo esc_html( 'v' . $swmd_plugin['version'] ); ?></span>

								<?php if ( $swmd_plugin['active_installs'] > 0 ) : ?>
									<span aria-hidden="true">&middot;</span>
									<span>
										<?php
										printf(
											/* translators: %s: a rounded number of sites, e.g. "1,000". */
											esc_html__( '%s+ active installs', 'swift-menu-duplicator' ),
											esc_html( number_format_i18n( $swmd_plugin['active_installs'] ) )
										);
										?>
									</span>
								<?php endif; ?>
							</p>

							<?php
							/*
							 * The count travels with the stars. Four stars from
							 * three people and four from four hundred are not the
							 * same claim, and stars alone imply the second. A
							 * plugin nobody has rated shows nothing at all rather
							 * than five empty stars, which reads as a bad score
							 * instead of no score.
							 */
							if ( $swmd_plugin['num_ratings'] > 0 ) :
								$swmd_filled = (int) round( $swmd_plugin['rating'] );
								?>
								<p class="swmd-plugin-rating">
									<span aria-hidden="true">
										<?php echo esc_html( str_repeat( '★', $swmd_filled ) . str_repeat( '☆', 5 - $swmd_filled ) ); ?>
									</span>
									<span>
										<?php
										printf(
											/* translators: 1: rating out of five, 2: number of ratings. */
											esc_html__( '%1$s out of 5 from %2$s ratings', 'swift-menu-duplicator' ),
											esc_html( number_format_i18n( $swmd_plugin['rating'], 1 ) ),
											esc_html( number_format_i18n( $swmd_plugin['num_ratings'] ) )
										);
										?>
									</span>
								</p>
							<?php endif; ?>
						</div>

						<?php if ( 'active' === $swmd_plugin['state'] ) : ?>
							<span class="swmd-plugin-state is-active"><?php esc_html_e( 'Active', 'swift-menu-duplicator' ); ?></span>
						<?php elseif ( 'inactive' === $swmd_plugin['state'] ) : ?>
							<span class="swmd-plugin-state"><?php esc_html_e( 'Installed', 'swift-menu-duplicator' ); ?></span>
						<?php endif; ?>
					</div>

					<p class="swmd-plugin-desc"><?php echo esc_html( $swmd_plugin['description'] ); ?></p>

					<p class="swmd-plugin-actions">
						<?php if ( 'missing' === $swmd_plugin['state'] && '' !== $swmd_plugin['action_url'] ) : ?>
							<a href="<?php echo esc_url( $swmd_plugin['action_url'] ); ?>" class="button button-primary">
								<?php esc_html_e( 'Install now', 'swift-menu-duplicator' ); ?>
							</a>
						<?php elseif ( 'inactive' === $swmd_plugin['state'] && '' !== $swmd_plugin['action_url'] ) : ?>
							<a href="<?php echo esc_url( $swmd_plugin['action_url'] ); ?>" class="button button-primary">
								<?php esc_html_e( 'Activate', 'swift-menu-duplicator' ); ?>
							</a>
						<?php endif; ?>

						<a
							href="<?php echo esc_url( $swmd_plugin['url'] ); ?>"
							class="button"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php
							echo 'active' === $swmd_plugin['state']
								? esc_html__( 'Documentation', 'swift-menu-duplicator' )
								: esc_html__( 'Learn more', 'swift-menu-duplicator' );
							?>
						</a>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
