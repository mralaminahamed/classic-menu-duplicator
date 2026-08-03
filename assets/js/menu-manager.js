( function( $ ) {
	'use strict';

	// -----------------------------------------------------------------------
	// Utilities
	// -----------------------------------------------------------------------

	/**
	 * Base AJAX wrapper.
	 *
	 * @param {string} action
	 * @param {Object} data
	 * @return {jQuery.Deferred} Deferred for the AJAX request.
	 */
	function ajax( action, data ) {
		return $.ajax( {
			url: swmdManagerData.ajaxUrl,
			method: 'POST',
			data: Object.assign( {}, { action, nonce: swmdManagerData.nonce }, data ),
		} );
	}

	/**
	 * Shows an admin notice banner inside .wrap.
	 *
	 * @param {string}            message
	 * @param {'success'|'error'} type
	 * @return {void}
	 */
	function notice( message, type ) {
		const $wrap = $( '.swmd-manager-wrap' );
		const $notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );

		$wrap.find( '.wp-header-end' ).after( $notice );
		$( document ).trigger( 'wp-updates-notice-added' );

		setTimeout( function() {
			$notice.fadeOut( 300, function() {
				$notice.remove();
			} );
		}, 5000 );
	}

	// -----------------------------------------------------------------------
	// Row action: Duplicate (single menu via AJAX).
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.swmd-row-duplicate', function( e ) {
		e.preventDefault();

		const $link = $( this );
		const menuId = parseInt( $link.data( 'menu-id' ), 10 );

		$link.text( swmdManagerData.duplicatingLabel );

		ajax( 'swmd_bulk_duplicate', { menu_ids: [ menuId ] } )
			.done( function( response ) {
				if ( response.success ) {
					notice( swmdManagerData.duplicatedLabel, 'success' );
					// Reload the table to show the new row.
					setTimeout( function() {
						window.location.reload();
					}, 800 );
				} else {
					notice( ( response.data && response.data.message ) || swmdManagerData.errorMessage, 'error' );
					$link.text( swmdManagerData.duplicateLabel );
				}
			} )
			.fail( function() {
				notice( swmdManagerData.errorMessage, 'error' );
				$link.text( swmdManagerData.duplicateLabel );
			} );
	} );

	/**
	 * Posts a hidden form so the browser treats the response as a download.
	 *
	 * @param {string} action AJAX action name.
	 * @param {Array}  fields Extra hidden fields as {name, value} pairs.
	 * @param {string} nonce  Nonce to send; falls back to the localized one.
	 * @return {void}
	 */
	function submitDownloadForm( action, fields, nonce ) {
		const $form = $( '<form>', { method: 'POST', action: swmdManagerData.ajaxUrl, target: '_self' } );

		[ { name: 'action', value: action }, { name: 'nonce', value: nonce || swmdManagerData.nonce } ]
			.concat( fields )
			.forEach( function( field ) {
				$form.append( $( '<input type="hidden" />' ).attr( 'name', field.name ).val( field.value ) );
			} );

		$( 'body' ).append( $form );
		$form.trigger( 'submit' );
		$form.remove();
	}

	// -----------------------------------------------------------------------
	// Row action: Delete (confirm before following the core delete URL).
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.swmd-row-delete', function( e ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( $( this ).data( 'confirm' ) ) ) {
			e.preventDefault();
		}
	} );

	// -----------------------------------------------------------------------
	// Row action: Export JSON (single menu, hidden form download).
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.swmd-row-export', function( e ) {
		e.preventDefault();

		const $link = $( this );
		const menuId = parseInt( $link.data( 'menu-id' ), 10 );
		const nonce = $link.data( 'nonce' );

		$link.text( swmdManagerData.exportingLabel );

		submitDownloadForm( 'swmd_bulk_export_zip', [ { name: 'menu_ids[]', value: menuId } ], nonce );

		setTimeout( function() {
			$link.text( swmdManagerData.exportLabel );
		}, 2000 );
	} );

	// -----------------------------------------------------------------------
	// Bulk action bar: Duplicate / Export selected.
	// -----------------------------------------------------------------------

	$( '#swmd-menu-table-form' ).on( 'submit', function( e ) {
		const action = $( this ).find( 'select[name="action"], select[name="action2"]' )
			.filter( function() {
				return $( this ).val() !== '-1';
			} )
			.first()
			.val();

		if ( ! action || '-1' === action ) {
			return; // Let normal form submit handle it (e.g. bulk delete).
		}

		if ( 'swmd_bulk_duplicate' === action || 'swmd_bulk_export' === action ) {
			e.preventDefault();
		} else {
			// swmd_bulk_delete: confirm before the native form submits.
			if ( ! window.confirm( swmdManagerData.confirmBulkDeleteText ) ) { // eslint-disable-line no-alert
				e.preventDefault();
			}
			return;
		}

		const ids = $( this ).find( 'input[name="menu_ids[]"]:checked' ).map( function() {
			return parseInt( $( this ).val(), 10 );
		} ).get();

		if ( ! ids.length ) {
			return;
		}

		if ( 'swmd_bulk_duplicate' === action ) {
			ajax( 'swmd_bulk_duplicate', { menu_ids: ids } )
				.done( function( response ) {
					if ( response.success ) {
						notice( swmdManagerData.duplicatedLabel, 'success' );
						setTimeout( function() {
							window.location.reload();
						}, 800 );
					} else {
						notice( ( response.data && response.data.message ) || swmdManagerData.errorMessage, 'error' );
					}
				} )
				.fail( function() {
					notice( swmdManagerData.errorMessage, 'error' );
				} );
		}

		if ( 'swmd_bulk_export' === action ) {
			submitDownloadForm(
				'swmd_bulk_export_zip',
				ids.map( function( id ) {
					return { name: 'menu_ids[]', value: id };
				} ),
			);
		}
	} );

	// -----------------------------------------------------------------------
	// Navigation (Block) tab: duplicate / export row actions.
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.swmd-row-duplicate-navigation', function( e ) {
		e.preventDefault();

		const $link = $( this );
		const navigationId = parseInt( $link.data( 'navigation-id' ), 10 );

		$link.text( swmdManagerData.duplicatingLabel );

		ajax( 'swmd_duplicate_navigation', { navigation_id: navigationId } )
			.done( function( response ) {
				if ( response.success ) {
					notice( swmdManagerData.duplicatedLabel, 'success' );
					setTimeout( function() {
						window.location.reload();
					}, 800 );
					return;
				}

				notice( ( response.data && response.data.message ) || swmdManagerData.errorMessage, 'error' );
				$link.text( swmdManagerData.duplicateLabel );
			} )
			.fail( function() {
				notice( swmdManagerData.errorMessage, 'error' );
				$link.text( swmdManagerData.duplicateLabel );
			} );
	} );

	$( document ).on( 'click', '.swmd-row-export-navigation', function( e ) {
		e.preventDefault();

		const $link = $( this );
		const navigationId = parseInt( $link.data( 'navigation-id' ), 10 );

		$link.text( swmdManagerData.exportingLabel );

		submitDownloadForm( 'swmd_export_navigation', [
			{ name: 'navigation_id', value: navigationId },
		], $link.data( 'nonce' ) );

		setTimeout( function() {
			$link.text( swmdManagerData.exportLabel );
		}, 2000 );
	} );

	// -----------------------------------------------------------------------
	// Copy to Site (multisite tab).
	// -----------------------------------------------------------------------

	$( '#swmd-copy-to-site-submit' ).on( 'click', function() {
		const $result = $( '#swmd-copy-result' );
		const menuId = parseInt( $( '#swmd-copy-source-menu' ).val(), 10 );
		const targetBlog = parseInt( $( '#swmd-copy-target-site' ).val(), 10 );

		if ( ! menuId || ! targetBlog ) {
			$result.html( '<p class="notice notice-warning inline">' + swmdManagerData.errorMessage + '</p>' );
			return;
		}

		const $btn = $( this );
		const menuName = $.trim( $( '#swmd-copy-menu-name' ).val() );

		$btn.prop( 'disabled', true ).text( swmdManagerData.copyingLabel );
		$result.empty();

		ajax( 'swmd_copy_to_site', {
			menu_id: menuId,
			target_blog_id: targetBlog,
			menu_name: menuName,
			find: $.trim( $( '#swmd-copy-find' ).val() ),
			replace: $.trim( $( '#swmd-copy-replace' ).val() ),
		} )
			.done( function( response ) {
				if ( response.success ) {
					const editUrl = response.data.edit_url || '';

					$result.html(
						'<p class="notice notice-success inline">' +
						swmdManagerData.copiedLabel +
						( editUrl
							? ' <a href="' + $( '<span>' ).text( editUrl ).html() + '" target="_blank">Edit menu &rarr;</a>'
							: '' ) +
						'</p>',
					);
				} else {
					const msg = ( response.data && response.data.message ) ? response.data.message : swmdManagerData.errorMessage;
					$result.html( '<p class="notice notice-error inline">' + $( '<span>' ).text( msg ).html() + '</p>' );
				}

				$btn.prop( 'disabled', false ).text( 'Copy Menu' );
			} )
			.fail( function() {
				$result.html( '<p class="notice notice-error inline">' + swmdManagerData.errorMessage + '</p>' );
				$btn.prop( 'disabled', false ).text( 'Copy Menu' );
			} );
	} );
}( jQuery ) );
