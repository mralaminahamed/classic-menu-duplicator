/* global cmduData, jQuery */
( function ( $ ) {
	'use strict';

	// -----------------------------------------------------------------------
	// Utilities
	// -----------------------------------------------------------------------

	/**
	 * Returns the current menu ID from the hidden #menu input.
	 *
	 * @return {number}
	 */
	function getCurrentMenuId() {
		return parseInt( $( 'input#menu[name="menu"]' ).val(), 10 ) || 0;
	}

	/**
	 * Base AJAX wrapper — returns a jQuery deferred.
	 *
	 * @param {string} action  AJAX action name.
	 * @param {Object} data    Additional POST parameters.
	 * @return {jQuery.Deferred}
	 */
	function ajaxRequest( action, data ) {
		return $.ajax( {
			url:    cmduData.ajaxUrl,
			method: 'POST',
			data:   Object.assign( {}, {
				action,
				nonce:   cmduData.nonce,
				menu_id: getCurrentMenuId(),
			}, data ),
		} );
	}

	/**
	 * Shows a temporary admin-notice-style toast inside the menu editor.
	 *
	 * @param {string}  message
	 * @param {'success'|'error'} type
	 * @return {void}
	 */
	function showToast( message, type ) {
		var $notice = $( '<div class="cmdu-toast notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );

		$( '#cmdu-toolbar' ).before( $notice );

		setTimeout( function () {
			$notice.fadeOut( 300, function () { $notice.remove(); } );
		}, 4000 );
	}

	// -----------------------------------------------------------------------
	// Modal — custom name for menu duplication
	// -----------------------------------------------------------------------

	var $modal = null;

	/**
	 * Builds and caches the name-input modal DOM (created once, reused).
	 *
	 * @return {jQuery} The modal overlay element.
	 */
	function getModal() {
		if ( $modal ) {
			return $modal;
		}

		$modal = $( [
			'<div id="cmdu-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cmdu-modal-heading">',
			'  <div id="cmdu-modal">',
			'    <h2 id="cmdu-modal-heading">' + cmduData.modalHeading + '</h2>',
			'    <label for="cmdu-modal-name">' + cmduData.modalNameLabel + '</label>',
			'    <input type="text" id="cmdu-modal-name" class="regular-text" autocomplete="off" />',
			'    <div class="cmdu-modal-actions">',
			'      <button type="button" id="cmdu-modal-confirm" class="button button-primary">' + cmduData.modalConfirmLabel + '</button>',
			'      <button type="button" id="cmdu-modal-cancel"  class="button">' + cmduData.modalCancelLabel + '</button>',
			'    </div>',
			'  </div>',
			'</div>',
		].join( '\n' ) );

		$( 'body' ).append( $modal );

		$modal.find( '#cmdu-modal-cancel' ).on( 'click', closeModal );

		// Close on overlay click (outside the modal box).
		$modal.on( 'click', function ( e ) {
			if ( $( e.target ).is( '#cmdu-modal-overlay' ) ) {
				closeModal();
			}
		} );

		// Close on Escape.
		$( document ).on( 'keydown.cmdu-modal', function ( e ) {
			if ( 27 === e.which && $modal.is( ':visible' ) ) {
				closeModal();
			}
		} );

		return $modal;
	}

	/**
	 * Opens the name modal prefilled with the suggested name.
	 *
	 * @param {string}   suggested  Default name to pre-fill.
	 * @param {Function} onConfirm  Called with the entered name string.
	 * @return {void}
	 */
	function openModal( suggested, onConfirm ) {
		var $m = getModal();

		$m.find( '#cmdu-modal-name' ).val( suggested ).trigger( 'focus' ).trigger( 'select' );

		// Remove any previously bound confirm handler before attaching a new one.
		$m.find( '#cmdu-modal-confirm' ).off( 'click.cmdu-confirm' ).on( 'click.cmdu-confirm', function () {
			var name = $.trim( $m.find( '#cmdu-modal-name' ).val() );
			if ( '' === name ) {
				$m.find( '#cmdu-modal-name' ).trigger( 'focus' );
				return;
			}
			closeModal();
			onConfirm( name );
		} );

		// Allow Enter key to confirm.
		$m.find( '#cmdu-modal-name' ).off( 'keydown.cmdu-confirm' ).on( 'keydown.cmdu-confirm', function ( e ) {
			if ( 13 === e.which ) {
				$m.find( '#cmdu-modal-confirm' ).trigger( 'click' );
			}
		} );

		$m.fadeIn( 150 );
	}

	/**
	 * Closes and hides the modal.
	 *
	 * @return {void}
	 */
	function closeModal() {
		if ( $modal ) {
			$modal.fadeOut( 150 );
		}
	}

	// -----------------------------------------------------------------------
	// Feature 1 — Duplicate Menu button (with name modal)
	// -----------------------------------------------------------------------

	/**
	 * Injects the Duplicate Menu button and wires the modal flow.
	 *
	 * @return {void}
	 */
	function initDuplicateMenuButton() {
		var $footer  = $( '#nav-menu-footer' );
		var $saveBtn = $footer.find( 'input#save_menu_footer' );

		if ( ! $footer.length || ! $saveBtn.length ) {
			return;
		}

		var $duplicateBtn = $( '<input>', {
			id:    'cmdu-duplicate-menu',
			type:  'button',
			class: 'button button-secondary',
			value: cmduData.buttonLabel,
		} );

		$saveBtn.after( $duplicateBtn );

		$duplicateBtn.on( 'click', function () {
			var menuId = getCurrentMenuId();

			if ( menuId <= 0 ) {
				return;
			}

			var $sourceNameEl = $( 'input#menu[name="menu"]' ).closest( 'form' ).find( '#menu-name' );
			var sourceName    = $.trim( $sourceNameEl.val() ) || '';
			var suggested     = sourceName ? sourceName + ' (Copy)' : cmduData.buttonLabel;

			openModal( suggested, function ( name ) {
				$duplicateBtn.prop( 'disabled', true ).val( cmduData.duplicatingLabel );

				ajaxRequest( 'cmdu_duplicate_menu', { menu_name: name } )
					.done( function ( response ) {
						if ( response.success && response.data && response.data.redirect ) {
							window.location.href = response.data.redirect;
							return;
						}

						var msg = ( response.data && response.data.message ) ? response.data.message : cmduData.errorMessage;
						showToast( msg, 'error' );
						$duplicateBtn.prop( 'disabled', false ).val( cmduData.buttonLabel );
					} )
					.fail( function () {
						showToast( cmduData.errorMessage, 'error' );
						$duplicateBtn.prop( 'disabled', false ).val( cmduData.buttonLabel );
					} );
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Feature 2 — Duplicate individual menu items
	// -----------------------------------------------------------------------

	/**
	 * Injects a "Duplicate" action link after the "Remove" link of every
	 * existing menu item row, and delegates future additions via event
	 * delegation on the sortable container.
	 *
	 * @return {void}
	 */
	function initDuplicateItemLinks() {
		var $menuManagement = $( '#menu-management-liquid' );

		if ( ! $menuManagement.length ) {
			return;
		}

		// Insert link into a single item row.
		function addDuplicateLinkToItem( $item ) {
			// Avoid double-injection.
			if ( $item.find( '.cmdu-duplicate-item' ).length ) {
				return;
			}

			var $removeLink = $item.find( '.item-delete' );

			if ( ! $removeLink.length ) {
				return;
			}

			var $link = $( '<a>', {
				href:  '#',
				class: 'cmdu-duplicate-item submitdelete',
				text:  cmduData.duplicateItemLabel,
			} ).css( { marginLeft: '8px' } );

			$removeLink.after( $link );
		}

		// Inject into all current rows.
		$( '.menu-item' ).each( function () {
			addDuplicateLinkToItem( $( this ) );
		} );

		// Delegate for items added by WordPress JS after page load.
		$menuManagement.on( 'click', '.item-edit', function () {
			// WordPress reveals the item form on the first edit click.
			addDuplicateLinkToItem( $( this ).closest( '.menu-item' ) );
		} );

		// Handle duplicate click.
		$menuManagement.on( 'click', '.cmdu-duplicate-item', function ( e ) {
			e.preventDefault();

			var $link   = $( this );
			var $item   = $link.closest( '.menu-item' );
			var itemId  = parseInt( $item.attr( 'id' ), 10 ) || 0;
			var menuId  = getCurrentMenuId();

			if ( itemId <= 0 || menuId <= 0 ) {
				return;
			}

			$link.text( cmduData.duplicatingItemLabel ).css( 'pointer-events', 'none' );

			ajaxRequest( 'cmdu_duplicate_item', { item_id: itemId } )
				.done( function ( response ) {
					if ( response.success ) {
						// Reload the page so WordPress re-renders the full item tree
						// with the new item in its correct position.
						window.location.href = window.location.href;
						return;
					}

					var msg = ( response.data && response.data.message ) ? response.data.message : cmduData.errorMessage;
					showToast( msg, 'error' );
					$link.text( cmduData.duplicateItemLabel ).css( 'pointer-events', '' );
				} )
				.fail( function () {
					showToast( cmduData.errorMessage, 'error' );
					$link.text( cmduData.duplicateItemLabel ).css( 'pointer-events', '' );
				} );
		} );
	}

	// -----------------------------------------------------------------------
	// Feature 3 — Snapshot history panel
	// -----------------------------------------------------------------------

	var $snapshotPanel = null;
	var snapshotPanelVisible = false;

	/**
	 * Builds the snapshot panel sidebar DOM.
	 *
	 * @return {jQuery}
	 */
	function getSnapshotPanel() {
		if ( $snapshotPanel ) {
			return $snapshotPanel;
		}

		$snapshotPanel = $( [
			'<div id="cmdu-snapshot-panel" aria-label="' + cmduData.snapshotLabel + '">',
			'  <div id="cmdu-snapshot-panel-header">',
			'    <span>' + cmduData.snapshotLabel + '</span>',
			'    <button type="button" id="cmdu-snapshot-close" aria-label="Close" class="button-link">&times;</button>',
			'  </div>',
			'  <div id="cmdu-snapshot-save-row">',
			'    <button type="button" id="cmdu-save-snapshot" class="button button-secondary button-small">',
			'      ' + cmduData.saveSnapshotLabel,
			'    </button>',
			'  </div>',
			'  <ul id="cmdu-snapshot-list"></ul>',
			'</div>',
		].join( '\n' ) );

		$( 'body' ).append( $snapshotPanel );

		$snapshotPanel.find( '#cmdu-snapshot-close' ).on( 'click', hideSnapshotPanel );

		$snapshotPanel.find( '#cmdu-save-snapshot' ).on( 'click', function () {
			var $btn   = $( this );
			var menuId = getCurrentMenuId();

			if ( menuId <= 0 ) {
				return;
			}

			$btn.prop( 'disabled', true ).text( cmduData.savingSnapshotLabel );

			ajaxRequest( 'cmdu_save_snapshot', { label: '' } )
				.done( function ( response ) {
					if ( response.success ) {
						renderSnapshotList( response.data.snapshots );
						showToast( cmduData.snapshotSavedText, 'success' );
					} else {
						showToast( cmduData.errorMessage, 'error' );
					}
					$btn.prop( 'disabled', false ).text( cmduData.saveSnapshotLabel );
				} )
				.fail( function () {
					showToast( cmduData.errorMessage, 'error' );
					$btn.prop( 'disabled', false ).text( cmduData.saveSnapshotLabel );
				} );
		} );

		return $snapshotPanel;
	}

	/**
	 * Renders the snapshot list into the panel.
	 *
	 * @param {Array} snapshots
	 * @return {void}
	 */
	function renderSnapshotList( snapshots ) {
		var $list = getSnapshotPanel().find( '#cmdu-snapshot-list' );
		$list.empty();

		if ( ! snapshots || ! snapshots.length ) {
			$list.append( '<li class="cmdu-snapshot-empty">' + cmduData.noSnapshotsText + '</li>' );
			return;
		}

		snapshots.forEach( function ( snap ) {
			var $li = $( '<li class="cmdu-snapshot-item"></li>' );

			$li.append(
				'<span class="cmdu-snapshot-label">' + $( '<span>' ).text( snap.label ).html() + '</span>' +
				'<span class="cmdu-snapshot-date">' + $( '<span>' ).text( snap.created_human ).html() + '</span>'
			);

			var $del = $( '<button type="button" class="cmdu-snapshot-delete button-link" aria-label="Delete">&times;</button>' );

			$del.on( 'click', function () {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( cmduData.confirmDeleteText ) ) {
					return;
				}

				ajaxRequest( 'cmdu_delete_snapshot', { snapshot_id: snap.id } )
					.done( function ( response ) {
						if ( response.success ) {
							renderSnapshotList( response.data.snapshots );
						}
					} );
			} );

			$li.append( $del );
			$list.append( $li );
		} );
	}

	/**
	 * Shows the snapshot panel and loads the snapshot list.
	 *
	 * @return {void}
	 */
	function showSnapshotPanel() {
		var menuId = getCurrentMenuId();

		if ( menuId <= 0 ) {
			return;
		}

		getSnapshotPanel().addClass( 'is-visible' );
		snapshotPanelVisible = true;

		// Load current snapshots on open.
		ajaxRequest( 'cmdu_get_snapshots', {} )
			.done( function ( response ) {
				if ( response.success ) {
					renderSnapshotList( response.data.snapshots );
				}
			} );
	}

	/**
	 * Hides the snapshot panel.
	 *
	 * @return {void}
	 */
	function hideSnapshotPanel() {
		if ( $snapshotPanel ) {
			$snapshotPanel.removeClass( 'is-visible' );
		}
		snapshotPanelVisible = false;
	}

	/**
	 * Injects the Snapshots toolbar button.
	 *
	 * @return {void}
	 */
	function initSnapshotButton() {
		var $footer  = $( '#nav-menu-footer' );
		var $saveBtn = $footer.find( 'input#save_menu_footer' );

		if ( ! $footer.length || ! $saveBtn.length ) {
			return;
		}

		if ( getCurrentMenuId() <= 0 ) {
			return;
		}

		var $snapshotBtn = $( '<input>', {
			id:    'cmdu-snapshot-toggle',
			type:  'button',
			class: 'button button-secondary',
			value: cmduData.snapshotLabel,
		} );

		$saveBtn.after( $snapshotBtn );

		$snapshotBtn.on( 'click', function () {
			if ( snapshotPanelVisible ) {
				hideSnapshotPanel();
			} else {
				showSnapshotPanel();
			}
		} );
	}

	// -----------------------------------------------------------------------
	// Feature 4 — Export to JSON
	// -----------------------------------------------------------------------

	/**
	 * Injects the Export JSON button into the menu editor footer.
	 *
	 * Triggers a file download via a hidden form submission (avoids
	 * pop-up blockers and keeps the current page in place).
	 *
	 * @return {void}
	 */
	function initExportButton() {
		var $footer  = $( '#nav-menu-footer' );
		var $saveBtn = $footer.find( 'input#save_menu_footer' );

		if ( ! $footer.length || ! $saveBtn.length ) {
			return;
		}

		if ( getCurrentMenuId() <= 0 ) {
			return;
		}

		var $exportBtn = $( '<input>', {
			id:    'cmdu-export-menu',
			type:  'button',
			class: 'button button-secondary',
			value: cmduData.exportLabel,
		} );

		$saveBtn.after( $exportBtn );

		$exportBtn.on( 'click', function () {
			var menuId = getCurrentMenuId();

			if ( menuId <= 0 ) {
				return;
			}

			$exportBtn.prop( 'disabled', true ).val( cmduData.exportingLabel );

			// Use a hidden form to trigger a file download response.
			var $form = $( '<form>', {
				method: 'POST',
				action: cmduData.ajaxUrl,
				target: '_self',
			} );

			[
				{ name: 'action',  value: 'cmdu_export_menu' },
				{ name: 'nonce',   value: cmduData.nonce },
				{ name: 'menu_id', value: menuId },
			].forEach( function ( field ) {
				$form.append( $( '<input type="hidden" />' ).attr( 'name', field.name ).val( field.value ) );
			} );

			$( 'body' ).append( $form );
			$form.trigger( 'submit' );
			$form.remove();

			// Re-enable the button after a short delay (download starts in background).
			setTimeout( function () {
				$exportBtn.prop( 'disabled', false ).val( cmduData.exportLabel );
			}, 2000 );
		} );
	}

	// -----------------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------------

	$( document ).ready( function () {
		initDuplicateMenuButton();
		initDuplicateItemLinks();
		initSnapshotButton();
		initExportButton();
	} );

} )( jQuery );
