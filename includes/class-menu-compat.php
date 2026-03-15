<?php
/**
 * Multilingual plugin compatibility layer.
 *
 * @package ClassicMenuDuplicator
 */

declare( strict_types=1 );

namespace ClassicMenuDuplicator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Compat
 *
 * Detects active multilingual plugins (WPML and Polylang) and hooks into
 * the plugin's action/filter surface to prevent duplication artefacts.
 *
 * Problem:
 *   WPML stores translation relationships on `nav_menu_item` posts via
 *   `icl_object_id` postmeta and entries in `wp_icl_translations`. When a
 *   menu item is duplicated these rows reference the original item, causing
 *   the duplicate to silently share translation metadata with its source —
 *   editing one can corrupt the other.
 *
 *   Polylang stores language associations via the `_pll_synced_taxonomies`,
 *   `term_language`, and `post_translations` term-meta structures. Copying
 *   these wholesale ties the duplicate's items to the source language object.
 *
 * Solution:
 *   This class registers itself on `cmd_after_duplicate_menu_item` and
 *   `cmd_after_import_item` to strip the problematic meta keys from every
 *   cloned/imported item immediately after insertion. It also registers on
 *   `cmd_item_meta_keys` to prevent those keys from being copied in the
 *   first place, providing defence in depth.
 *
 *   For WPML specifically, the class additionally fires
 *   `do_action('wpml_register_single_element', …)` so that WPML can assign
 *   the new item to the correct language context without manual intervention.
 */
class Menu_Compat {

	/**
	 * WPML postmeta keys that must not be copied to duplicated items.
	 *
	 * @var string[]
	 */
	private const WPML_META_KEYS = array(
		'_icl_lang_duplicate_of',
		'wpml_language',
	);

	/**
	 * Polylang postmeta/term-meta keys that must not be copied.
	 *
	 * @var string[]
	 */
	private const POLYLANG_META_KEYS = array(
		'_pll_synced_taxonomies',
		'_pll_menu_language',
	);

	/**
	 * Whether WPML is active on this installation.
	 *
	 * @var bool
	 */
	private bool $wpml_active = false;

	/**
	 * Whether Polylang is active on this installation.
	 *
	 * @var bool
	 */
	private bool $polylang_active = false;

	/**
	 * Detects active multilingual plugins and registers hooks when relevant.
	 *
	 * Safe to call on every request — hooks are only registered when a
	 * supported plugin is actually active.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$this->wpml_active     = $this->is_wpml_active();
		$this->polylang_active = $this->is_polylang_active();

		if ( ! $this->wpml_active && ! $this->polylang_active ) {
			return;
		}

		// Prevent incompatible meta keys from being copied during duplication.
		add_filter( 'classic_menu_duplicator_item_meta_keys', array( $this, 'filter_item_meta_keys' ) );

		// Strip any residual incompatible meta from a freshly duplicated item.
		add_action( 'classic_menu_duplicator_after_duplicate_menu_item', array( $this, 'clean_item_meta' ), 10, 2 );

		// Strip incompatible meta from freshly imported items.
		add_action( 'classic_menu_duplicator_after_import_item', array( $this, 'clean_item_meta' ), 10, 1 );

		if ( $this->wpml_active ) {
			// Ask WPML to register each new item in the translations table.
			add_action( 'classic_menu_duplicator_after_duplicate_menu_item', array( $this, 'wpml_register_item' ), 20, 2 );
			add_action( 'classic_menu_duplicator_after_import_item', array( $this, 'wpml_register_item' ), 20, 1 );

			// After a full menu is duplicated, register the new menu term itself.
			add_action( 'classic_menu_duplicator_after_duplicate_menu', array( $this, 'wpml_register_menu' ), 10, 2 );
			add_action( 'classic_menu_duplicator_after_import_menu', array( $this, 'wpml_register_menu' ), 10, 2 );
		}

		if ( $this->polylang_active ) {
			// Copy the language assignment of the source menu to the duplicate.
			add_action( 'classic_menu_duplicator_after_duplicate_menu', array( $this, 'polylang_copy_menu_language' ), 10, 2 );
		}
	}

	// -----------------------------------------------------------------------
	// Filter: cmd_item_meta_keys — preventive exclusion.
	// -----------------------------------------------------------------------

	/**
	 * Removes multilingual meta keys from the list of keys copied during
	 * duplication, preventing them from being written to the new item.
	 *
	 * @param string[] $keys Existing meta key list.
	 *
	 * @return string[] Filtered list.
	 */
	public function filter_item_meta_keys( array $keys ): array {
		$excluded = $this->get_excluded_meta_keys();

		return array_values( array_diff( $keys, $excluded ) );
	}

	// -----------------------------------------------------------------------
	// Action: clean_item_meta — defensive strip after insert.
	// -----------------------------------------------------------------------

	/**
	 * Deletes all multilingual meta from a newly created nav_menu_item post.
	 *
	 * Accepts both the two-argument form used by `cmd_after_duplicate_menu_item`
	 * (old_id, new_id) and the one-argument form used by `cmd_after_import_item`
	 * (new_id only). The $ignored parameter absorbs the second argument when
	 * called from the duplication hook.
	 *
	 * @param int $new_item_id Post ID of the newly created nav_menu_item.
	 * @param int $ignored     Unused — original item ID passed by the duplicate hook.
	 *
	 * @return void
	 */
	public function clean_item_meta( int $new_item_id, int $ignored = 0 ): void {
		foreach ( $this->get_excluded_meta_keys() as $key ) {
			delete_post_meta( $new_item_id, $key );
		}
	}

	// -----------------------------------------------------------------------
	// WPML-specific hooks.
	// -----------------------------------------------------------------------

	/**
	 * Asks WPML to register a duplicated/imported nav_menu_item post in the
	 * translations table so it appears in the correct language context.
	 *
	 * The `wpml_register_single_element` action is the documented WPML API
	 * for programmatically registering new content. When WPML is not active
	 * this method is never called.
	 *
	 * @param int $new_item_id Post ID of the new nav_menu_item.
	 * @param int $ignored     Unused (original item ID from duplication hook).
	 *
	 * @return void
	 */
	public function wpml_register_item( int $new_item_id, int $ignored = 0 ): void {
		if ( ! $this->wpml_active ) {
			return;
		}

		/**
		 * @see https://wpml.org/wpml-hook/wpml_register_single_element/
		 */
		do_action(
			'wpml_register_single_element',
			array(
				'element_type' => 'post_nav_menu_item',
				'element_id'   => $new_item_id,
			)
		);
	}

	/**
	 * Asks WPML to register a duplicated/imported nav_menu term.
	 *
	 * @param int $source_menu_id Original menu term ID.
	 * @param int $new_menu_id    Duplicate/imported menu term ID.
	 *
	 * @return void
	 */
	public function wpml_register_menu( int $source_menu_id, int $new_menu_id ): void {
		if ( ! $this->wpml_active ) {
			return;
		}

		/**
		 * Retrieve the language of the source menu so WPML can assign the
		 * duplicate to the same language by default.
		 *
		 * @see https://wpml.org/wpml-hook/wpml_element_language_code/
		 */
		$lang = apply_filters(
			'wpml_element_language_code',
			null,
			array(
				'element_id'   => $source_menu_id,
				'element_type' => 'nav_menu',
			)
		);

		if ( null === $lang ) {
			return;
		}

		/**
		 * @see https://wpml.org/wpml-hook/wpml_set_element_language_details/
		 */
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $new_menu_id,
				'element_type'         => 'tax_nav_menu',
				'language_code'        => $lang,
				'source_language_code' => null,
			)
		);
	}

	// -----------------------------------------------------------------------
	// Polylang-specific hooks.
	// -----------------------------------------------------------------------

	/**
	 * Copies the language assignment of the source menu to the duplicate
	 * using Polylang's `pll_set_term_language` function when available.
	 *
	 * @param int $source_menu_id Original menu term ID.
	 * @param int $new_menu_id    Duplicate menu term ID.
	 *
	 * @return void
	 */
	public function polylang_copy_menu_language( int $source_menu_id, int $new_menu_id ): void {
		if ( ! $this->polylang_active || ! function_exists( 'pll_get_term_language' ) ) {
			return;
		}

		$lang = pll_get_term_language( $source_menu_id, 'slug' );

		if ( ! $lang || ! function_exists( 'pll_set_term_language' ) ) {
			return;
		}

		pll_set_term_language( $new_menu_id, $lang );
	}

	// -----------------------------------------------------------------------
	// Detection helpers.
	// -----------------------------------------------------------------------

	/**
	 * Returns true when WPML (SitePress) is active.
	 *
	 * @return bool
	 */
	private function is_wpml_active(): bool {
		/**
		 * Filters whether WPML compatibility is considered active.
		 *
		 * Useful for unit testing or when running a WPML-compatible plugin
		 * that does not define ICL_SITEPRESS_VERSION.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $active Whether WPML is detected as active.
		 */
		return (bool) apply_filters(
			'classic_menu_duplicator_compat_wpml_active',
			defined( 'ICL_SITEPRESS_VERSION' )
		);
	}

	/**
	 * Returns true when Polylang (free or Pro) is active.
	 *
	 * @return bool
	 */
	private function is_polylang_active(): bool {
		/**
		 * Filters whether Polylang compatibility is considered active.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $active Whether Polylang is detected as active.
		 */
		return (bool) apply_filters(
			'classic_menu_duplicator_compat_polylang_active',
			defined( 'POLYLANG_VERSION' )
		);
	}

	/**
	 * Builds the combined list of meta keys to exclude based on which
	 * multilingual plugins are active.
	 *
	 * @return string[]
	 */
	private function get_excluded_meta_keys(): array {
		$keys = array();

		if ( $this->wpml_active ) {
			$keys = array_merge( $keys, self::WPML_META_KEYS );
		}

		if ( $this->polylang_active ) {
			$keys = array_merge( $keys, self::POLYLANG_META_KEYS );
		}

		/**
		 * Filters the list of postmeta keys that Menu_Compat will strip from
		 * duplicated and imported nav_menu_item posts.
		 *
		 * Third-party integrations can append their own keys here.
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $keys    Combined exclusion list.
		 * @param bool     $wpml    Whether WPML is active.
		 * @param bool     $polylang Whether Polylang is active.
		 */
		return (array) apply_filters(
			'classic_menu_duplicator_compat_excluded_meta_keys',
			$keys,
			$this->wpml_active,
			$this->polylang_active
		);
	}
}
