/* global cmduManagerData, jQuery */
( function ( $ ) {
	'use strict';

	// -----------------------------------------------------------------------
	// Utilities
	// -----------------------------------------------------------------------

	/**
	 * Base AJAX wrapper.
	 *
	 * @param {string} action
	 * @param {Object} data
	 * @return {jQuery.Deferred}
	 */
	function ajax( action, data ) {
		return $.ajax( {
			url:    cmduManagerData.ajaxUrl,
			method: 'POST',
			data:   Object.assign( {}, { action, nonce: cmduManagerData.nonce }, data ),
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
		var $wrap   = $( '.cmdu-manager-wrap' );
		var $notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );

		$wrap.find( '.wp-header-end' ).after( $notice );
		$( document ).trigger( 'wp-updates-notice-added' );

		setTimeout( function () {
			$notice.fadeOut( 300, function () { $notice.remove(); } );
		}, 5000 );
	}

	// -----------------------------------------------------------------------
	// Row action: Duplicate (single menu via AJAX).
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.cmdu-row-duplicate', function ( e ) {
		e.preventDefault();

		var $link   = $( this );
		var menuId  = parseInt( $link.data( 'menu-id' ), 10 );
		var $row    = $link.closest( 'tr' );

		$link.text( cmduManagerData.duplicatingLabel );

		ajax( 'cmdu_bulk_duplicate', { menu_ids: [ menuId ] } )
			.done( function ( response ) {
				if ( response.success ) {
					notice( cmduManagerData.duplicatedLabel, 'success' );
					// Reload the table to show the new row.
					setTimeout( function () { window.location.reload(); }, 800 );
				} else {
					notice( ( response.data && response.data.message ) || cmduManagerData.errorMessage, 'error' );
					$link.text( 'Duplicate' );
				}
			} )
			.fail( function () {
				notice( cmduManagerData.errorMessage, 'error' );
				$link.text( 'Duplicate' );
			} );
	} );

	// -----------------------------------------------------------------------
	// Row action: Export JSON (single menu, hidden form download).
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.cmdu-row-export', function ( e ) {
		e.preventDefault();

		var $link  = $( this );
		var menuId = parseInt( $link.data( 'menu-id' ), 10 );
		var nonce  = $link.data( 'nonce' );

		$link.text( cmduManagerData.exportingLabel );

		var $form = $( '<form>', { method: 'POST', action: cmduManagerData.ajaxUrl, target: '_self' } );

		[
			{ name: 'action',   value: 'cmdu_bulk_export_zip' },
			{ name: 'nonce',    value: nonce },
			{ name: 'menu_ids[]', value: menuId },
		].forEach( function ( f ) {
			$form.append( $( '<input type="hidden" />' ).attr( 'name', f.name ).val( f.value ) );
		} );

		$( 'body' ).append( $form );
		$form.trigger( 'submit' );
		$form.remove();

		setTimeout( function () {
			$link.text( 'Export JSON' );
		}, 2000 );
	} );

	// -----------------------------------------------------------------------
	// Bulk action bar: Duplicate / Export selected.
	// -----------------------------------------------------------------------

	$( '#cmdu-menu-table-form' ).on( 'submit', function ( e ) {
		var action = $( this ).find( 'select[name="action"], select[name="action2"]' )
			.filter( function () { return $( this ).val() !== '-1'; } )
			.first()
			.val();

		if ( ! action || '-1' === action ) {
			return; // Let normal form submit handle it (e.g. bulk delete).
		}

		if ( 'cmdu_bulk_duplicate' === action || 'cmdu_bulk_export' === action ) {
			e.preventDefault();
		} else {
			// cmdu_bulk_delete: confirm before the native form submits.
			if ( ! window.confirm( cmduManagerData.confirmBulkDeleteText ) ) { // eslint-disable-line no-alert
				e.preventDefault();
			}
			return;
		}

		var ids = $( this ).find( 'input[name="menu_ids[]"]:checked' ).map( function () {
			return parseInt( $( this ).val(), 10 );
		} ).get();

		if ( ! ids.length ) {
			return;
		}

		if ( 'cmdu_bulk_duplicate' === action ) {
			ajax( 'cmdu_bulk_duplicate', { menu_ids: ids } )
				.done( function ( response ) {
					if ( response.success ) {
						notice( cmduManagerData.duplicatedLabel, 'success' );
						setTimeout( function () { window.location.reload(); }, 800 );
					} else {
						notice( ( response.data && response.data.message ) || cmduManagerData.errorMessage, 'error' );
					}
				} )
				.fail( function () {
					notice( cmduManagerData.errorMessage, 'error' );
				} );
		}

		if ( 'cmdu_bulk_export' === action ) {
			var $form = $( '<form>', { method: 'POST', action: cmduManagerData.ajaxUrl, target: '_self' } );

			$form.append( $( '<input type="hidden" />' ).attr( 'name', 'action' ).val( 'cmdu_bulk_export_zip' ) );
			$form.append( $( '<input type="hidden" />' ).attr( 'name', 'nonce' ).val( cmduManagerData.nonce ) );

			ids.forEach( function ( id ) {
				$form.append( $( '<input type="hidden" />' ).attr( 'name', 'menu_ids[]' ).val( id ) );
			} );

			$( 'body' ).append( $form );
			$form.trigger( 'submit' );
			$form.remove();
		}
	} );

	// -----------------------------------------------------------------------
	// Copy to Site (multisite tab).
	// -----------------------------------------------------------------------

	$( '#cmdu-copy-to-site-submit' ).on( 'click', function () {
		var $btn       = $( this );
		var menuId     = parseInt( $( '#cmdu-copy-source-menu' ).val(), 10 );
		var targetBlog = parseInt( $( '#cmdu-copy-target-site' ).val(), 10 );
		var menuName   = $.trim( $( '#cmdu-copy-menu-name' ).val() );
		var $result    = $( '#cmdu-copy-result' );

		if ( ! menuId || ! targetBlog ) {
			$result.html( '<p class="notice notice-warning inline">' + cmduManagerData.errorMessage + '</p>' );
			return;
		}

		$btn.prop( 'disabled', true ).text( cmduManagerData.copyingLabel );
		$result.empty();

		ajax( 'cmdu_copy_to_site', {
			menu_id:        menuId,
			target_blog_id: targetBlog,
			menu_name:      menuName,
		} )
			.done( function ( response ) {
				if ( response.success ) {
					var editUrl = response.data.edit_url || '';

					$result.html(
						'<p class="notice notice-success inline">' +
						cmduManagerData.copiedLabel +
						( editUrl
							? ' <a href="' + $( '<span>' ).text( editUrl ).html() + '" target="_blank">Edit menu &rarr;</a>'
							: '' ) +
						'</p>'
					);
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : cmduManagerData.errorMessage;
					$result.html( '<p class="notice notice-error inline">' + $( '<span>' ).text( msg ).html() + '</p>' );
				}

				$btn.prop( 'disabled', false ).text( 'Copy Menu' );
			} )
			.fail( function () {
				$result.html( '<p class="notice notice-error inline">' + cmduManagerData.errorMessage + '</p>' );
				$btn.prop( 'disabled', false ).text( 'Copy Menu' );
			} );
	} );

} )( jQuery );
