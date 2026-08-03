( function( $ ) {
	'use strict';

	// -----------------------------------------------------------------------
	// Utilities
	// -----------------------------------------------------------------------

	/**
	 * Returns the current menu ID from the hidden #menu input.
	 *
	 * @return {number} Term ID of the menu being edited, or 0 when none.
	 */
	function getCurrentMenuId() {
		return parseInt( $( 'input#menu[name="menu"]' ).val(), 10 ) || 0;
	}

	/**
	 * Base AJAX wrapper — returns a jQuery deferred.
	 *
	 * @param {string} action AJAX action name.
	 * @param {Object} data   Additional POST parameters.
	 * @return {jQuery.Deferred} Deferred for the AJAX request.
	 */
	function ajaxRequest( action, data ) {
		return $.ajax( {
			url: swmdData.ajaxUrl,
			method: 'POST',
			data: Object.assign( {}, {
				action,
				nonce: swmdData.nonce,
				menu_id: getCurrentMenuId(),
			}, data ),
		} );
	}

	/**
	 * Shows a temporary admin-notice-style toast inside the menu editor.
	 *
	 * @param {string}            message
	 * @param {'success'|'error'} type
	 * @return {void}
	 */
	function showToast( message, type ) {
		const $notice = $( '<div class="swmd-toast notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );

		$( '#swmd-toolbar' ).before( $notice );

		setTimeout( function() {
			$notice.fadeOut( 300, function() {
				$notice.remove();
			} );
		}, 4000 );
	}

	// -----------------------------------------------------------------------
	// Modal — custom name for menu duplication
	// -----------------------------------------------------------------------

	let $modal = null;

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
			'<div id="swmd-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="swmd-modal-heading">',
			'  <div id="swmd-modal">',
			'    <h2 id="swmd-modal-heading">' + swmdData.modalHeading + '</h2>',
			'    <label for="swmd-modal-name">' + swmdData.modalNameLabel + '</label>',
			'    <input type="text" id="swmd-modal-name" class="regular-text" autocomplete="off" />',
			'    <div class="swmd-modal-actions">',
			'      <button type="button" id="swmd-modal-confirm" class="button button-primary">' + swmdData.modalConfirmLabel + '</button>',
			'      <button type="button" id="swmd-modal-cancel"  class="button">' + swmdData.modalCancelLabel + '</button>',
			'    </div>',
			'  </div>',
			'</div>',
		].join( '\n' ) );

		$( 'body' ).append( $modal );

		$modal.find( '#swmd-modal-cancel' ).on( 'click', closeModal );

		// Close on overlay click (outside the modal box).
		$modal.on( 'click', function( e ) {
			if ( $( e.target ).is( '#swmd-modal-overlay' ) ) {
				closeModal();
			}
		} );

		// Close on Escape.
		$( document ).on( 'keydown.swmd-modal', function( e ) {
			if ( 27 === e.which && $modal.is( ':visible' ) ) {
				closeModal();
			}
		} );

		return $modal;
	}

	/**
	 * Opens the name modal prefilled with the suggested name.
	 *
	 * @param {string}   suggested Default name to pre-fill.
	 * @param {Function} onConfirm Called with the entered name string.
	 * @return {void}
	 */
	function openModal( suggested, onConfirm ) {
		const $m = getModal();

		$m.find( '#swmd-modal-name' ).val( suggested ).trigger( 'focus' ).trigger( 'select' );

		// Remove any previously bound confirm handler before attaching a new one.
		$m.find( '#swmd-modal-confirm' ).off( 'click.swmd-confirm' ).on( 'click.swmd-confirm', function() {
			const name = $.trim( $m.find( '#swmd-modal-name' ).val() );
			if ( '' === name ) {
				$m.find( '#swmd-modal-name' ).trigger( 'focus' );
				return;
			}
			closeModal();
			onConfirm( name );
		} );

		// Allow Enter key to confirm.
		$m.find( '#swmd-modal-name' ).off( 'keydown.swmd-confirm' ).on( 'keydown.swmd-confirm', function( e ) {
			if ( 13 === e.which ) {
				$m.find( '#swmd-modal-confirm' ).trigger( 'click' );
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
		const $footer = $( '#nav-menu-footer' );
		const $saveBtn = $footer.find( 'input#save_menu_footer' );

		if ( ! $footer.length || ! $saveBtn.length ) {
			return;
		}

		const $duplicateBtn = $( '<input>', {
			id: 'swmd-duplicate-menu',
			type: 'button',
			class: 'button button-secondary',
			value: swmdData.buttonLabel,
		} );

		$saveBtn.after( $duplicateBtn );

		$duplicateBtn.on( 'click', function() {
			const menuId = getCurrentMenuId();

			if ( menuId <= 0 ) {
				return;
			}

			const $sourceNameEl = $( 'input#menu[name="menu"]' ).closest( 'form' ).find( '#menu-name' );
			const sourceName = $.trim( $sourceNameEl.val() ) || '';
			const suggested = sourceName ? sourceName + ' (Copy)' : swmdData.buttonLabel;

			openModal( suggested, function( name ) {
				$duplicateBtn.prop( 'disabled', true ).val( swmdData.duplicatingLabel );

				ajaxRequest( 'swmd_duplicate_menu', { menu_name: name } )
					.done( function( response ) {
						if ( response.success && response.data && response.data.redirect ) {
							window.location.href = response.data.redirect;
							return;
						}

						const msg = ( response.data && response.data.message ) ? response.data.message : swmdData.errorMessage;
						showToast( msg, 'error' );
						$duplicateBtn.prop( 'disabled', false ).val( swmdData.buttonLabel );
					} )
					.fail( function() {
						showToast( swmdData.errorMessage, 'error' );
						$duplicateBtn.prop( 'disabled', false ).val( swmdData.buttonLabel );
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
		const $menuManagement = $( '#menu-management-liquid' );

		if ( ! $menuManagement.length ) {
			return;
		}

		// Insert link into a single item row.
		function addDuplicateLinkToItem( $item ) {
			// Avoid double-injection.
			if ( $item.find( '.swmd-duplicate-item' ).length ) {
				return;
			}

			const $removeLink = $item.find( '.item-delete' );

			if ( ! $removeLink.length ) {
				return;
			}

			const $link = $( '<a>', {
				href: '#',
				class: 'swmd-duplicate-item submitdelete',
				text: swmdData.duplicateItemLabel,
			} );

			$removeLink.after( $link );
		}

		// Inject into all current rows.
		$( '.menu-item' ).each( function() {
			addDuplicateLinkToItem( $( this ) );
		} );

		// Delegate for items added by WordPress JS after page load.
		$menuManagement.on( 'click', '.item-edit', function() {
			// WordPress reveals the item form on the first edit click.
			addDuplicateLinkToItem( $( this ).closest( '.menu-item' ) );
		} );

		// Handle duplicate click.
		$menuManagement.on( 'click', '.swmd-duplicate-item', function( e ) {
			e.preventDefault();

			const $link = $( this );
			const $item = $link.closest( '.menu-item' );
			const itemId = parseInt( $item.attr( 'id' ), 10 ) || 0;
			const menuId = getCurrentMenuId();

			if ( itemId <= 0 || menuId <= 0 ) {
				return;
			}

			$link.text( swmdData.duplicatingItemLabel ).css( 'pointer-events', 'none' );

			ajaxRequest( 'swmd_duplicate_item', { item_id: itemId } )
				.done( function( response ) {
					if ( response.success ) {
						// Reload the page so WordPress re-renders the full item tree
						// with the new item in its correct position.
						window.location.href = window.location.href;
						return;
					}

					const msg = ( response.data && response.data.message ) ? response.data.message : swmdData.errorMessage;
					showToast( msg, 'error' );
					$link.text( swmdData.duplicateItemLabel ).css( 'pointer-events', '' );
				} )
				.fail( function() {
					showToast( swmdData.errorMessage, 'error' );
					$link.text( swmdData.duplicateItemLabel ).css( 'pointer-events', '' );
				} );
		} );
	}

	// -----------------------------------------------------------------------
	// Feature 3 — Snapshot history panel
	// -----------------------------------------------------------------------

	let $snapshotPanel = null;
	let snapshotPanelVisible = false;

	/**
	 * Builds the snapshot panel sidebar DOM.
	 *
	 * @return {jQuery} The snapshot panel element.
	 */
	function getSnapshotPanel() {
		if ( $snapshotPanel ) {
			return $snapshotPanel;
		}

		$snapshotPanel = $( [
			'<div id="swmd-snapshot-panel" aria-label="' + swmdData.snapshotLabel + '">',
			'  <div id="swmd-snapshot-panel-header">',
			'    <span>' + swmdData.snapshotLabel + '</span>',
			'    <button type="button" id="swmd-snapshot-close" aria-label="Close" class="button-link">&times;</button>',
			'  </div>',
			'  <div id="swmd-snapshot-save-row">',
			'    <button type="button" id="swmd-save-snapshot" class="button button-secondary button-small">',
			'      ' + swmdData.saveSnapshotLabel,
			'    </button>',
			'  </div>',
			'  <ul id="swmd-snapshot-list"></ul>',
			'</div>',
		].join( '\n' ) );

		$( 'body' ).append( $snapshotPanel );

		$snapshotPanel.find( '#swmd-snapshot-close' ).on( 'click', hideSnapshotPanel );

		$snapshotPanel.find( '#swmd-save-snapshot' ).on( 'click', function() {
			const menuId = getCurrentMenuId();

			if ( menuId <= 0 ) {
				return;
			}

			const $btn = $( this );

			$btn.prop( 'disabled', true ).text( swmdData.savingSnapshotLabel );

			ajaxRequest( 'swmd_save_snapshot', { label: '' } )
				.done( function( response ) {
					if ( response.success ) {
						renderSnapshotList( response.data.snapshots );
						showToast( swmdData.snapshotSavedText, 'success' );
					} else {
						showToast( swmdData.errorMessage, 'error' );
					}
					$btn.prop( 'disabled', false ).text( swmdData.saveSnapshotLabel );
				} )
				.fail( function() {
					showToast( swmdData.errorMessage, 'error' );
					$btn.prop( 'disabled', false ).text( swmdData.saveSnapshotLabel );
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
		const $list = getSnapshotPanel().find( '#swmd-snapshot-list' );
		$list.empty();

		if ( ! snapshots || ! snapshots.length ) {
			$list.append( '<li class="swmd-snapshot-empty">' + swmdData.noSnapshotsText + '</li>' );
			return;
		}

		snapshots.forEach( function( snap ) {
			const $li = $( '<li class="swmd-snapshot-item"></li>' );

			$li.append(
				'<span class="swmd-snapshot-label">' + $( '<span>' ).text( snap.label ).html() + '</span>' +
				'<span class="swmd-snapshot-date">' + $( '<span>' ).text( snap.created_human ).html() + '</span>',
			);

			const $restore = $( '<button type="button" class="swmd-snapshot-restore button-link"></button>' ).text( swmdData.restoreLabel );

			$restore.on( 'click', function() {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( swmdData.confirmRestoreText ) ) {
					return;
				}

				$restore.prop( 'disabled', true ).text( swmdData.restoringLabel );

				ajaxRequest( 'swmd_restore_snapshot', { snapshot_id: snap.id } )
					.done( function( response ) {
						if ( response.success ) {
							showToast( swmdData.snapshotRestoredText, 'success' );
							// Reload so the menu editor re-renders the restored items.
							setTimeout( function() {
								window.location.reload();
							}, 800 );
							return;
						}

						const msg = ( response.data && response.data.message ) ? response.data.message : swmdData.errorMessage;
						showToast( msg, 'error' );
						$restore.prop( 'disabled', false ).text( swmdData.restoreLabel );
					} )
					.fail( function() {
						showToast( swmdData.errorMessage, 'error' );
						$restore.prop( 'disabled', false ).text( swmdData.restoreLabel );
					} );
			} );

			$li.append( $restore );

			const $del = $( '<button type="button" class="swmd-snapshot-delete button-link" aria-label="Delete">&times;</button>' );

			$del.on( 'click', function() {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( swmdData.confirmDeleteText ) ) {
					return;
				}

				ajaxRequest( 'swmd_delete_snapshot', { snapshot_id: snap.id } )
					.done( function( response ) {
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
		const menuId = getCurrentMenuId();

		if ( menuId <= 0 ) {
			return;
		}

		getSnapshotPanel().addClass( 'is-visible' );
		snapshotPanelVisible = true;

		// Load current snapshots on open.
		ajaxRequest( 'swmd_get_snapshots', {} )
			.done( function( response ) {
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
		const $footer = $( '#nav-menu-footer' );
		const $saveBtn = $footer.find( 'input#save_menu_footer' );

		if ( ! $footer.length || ! $saveBtn.length ) {
			return;
		}

		if ( getCurrentMenuId() <= 0 ) {
			return;
		}

		const $snapshotBtn = $( '<input>', {
			id: 'swmd-snapshot-toggle',
			type: 'button',
			class: 'button button-secondary',
			value: swmdData.snapshotLabel,
		} );

		$saveBtn.after( $snapshotBtn );

		$snapshotBtn.on( 'click', function() {
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
		const $footer = $( '#nav-menu-footer' );
		const $saveBtn = $footer.find( 'input#save_menu_footer' );

		if ( ! $footer.length || ! $saveBtn.length ) {
			return;
		}

		if ( getCurrentMenuId() <= 0 ) {
			return;
		}

		const $exportBtn = $( '<input>', {
			id: 'swmd-export-menu',
			type: 'button',
			class: 'button button-secondary',
			value: swmdData.exportLabel,
		} );

		$saveBtn.after( $exportBtn );

		$exportBtn.on( 'click', function() {
			const menuId = getCurrentMenuId();

			if ( menuId <= 0 ) {
				return;
			}

			$exportBtn.prop( 'disabled', true ).val( swmdData.exportingLabel );

			// Use a hidden form to trigger a file download response.
			const $form = $( '<form>', {
				method: 'POST',
				action: swmdData.ajaxUrl,
				target: '_self',
			} );

			[
				{ name: 'action', value: 'swmd_export_menu' },
				{ name: 'nonce', value: swmdData.nonce },
				{ name: 'menu_id', value: menuId },
			].forEach( function( field ) {
				$form.append( $( '<input type="hidden" />' ).attr( 'name', field.name ).val( field.value ) );
			} );

			$( 'body' ).append( $form );
			$form.trigger( 'submit' );
			$form.remove();

			// Re-enable the button after a short delay (download starts in background).
			setTimeout( function() {
				$exportBtn.prop( 'disabled', false ).val( swmdData.exportLabel );
			}, 2000 );
		} );
	}

	// -----------------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------------

	$( document ).ready( function() {
		initDuplicateMenuButton();
		initDuplicateItemLinks();
		initSnapshotButton();
		initExportButton();
	} );
}( jQuery ) );
