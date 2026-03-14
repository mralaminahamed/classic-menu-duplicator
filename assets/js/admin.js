/* global cmdData, jQuery */
( function ( $ ) {
	'use strict';

	/**
	 * Returns the current menu ID from the hidden #menu input that WordPress
	 * populates when a menu is loaded for editing. Returns 0 when no menu is
	 * open (i.e. the "create new menu" state is active).
	 *
	 * @return {number}
	 */
	function getCurrentMenuId() {
		return parseInt( $( 'input#menu[name="menu"]' ).val(), 10 ) || 0;
	}

	/**
	 * Sends the AJAX duplication request and redirects to the new menu on
	 * success. Re-enables the button and shows an alert on failure.
	 *
	 * @param {jQuery} $btn    The Duplicate Menu button element.
	 * @param {number} menuId  Term ID of the menu to duplicate.
	 *
	 * @return {void}
	 */
	function sendDuplicateRequest( $btn, menuId ) {
		$btn
			.prop( 'disabled', true )
			.val( cmdData.duplicatingLabel );

		$.ajax( {
			url:    cmdData.ajaxUrl,
			method: 'POST',
			data:   {
				action:  'cmd_duplicate_menu',
				nonce:   cmdData.nonce,
				menu_id: menuId,
			},
		} )
			.done( function ( response ) {
				if ( response.success && response.data && response.data.redirect ) {
					window.location.href = response.data.redirect;
					return;
				}

				var msg = ( response.data && response.data.message )
					? response.data.message
					: cmdData.errorMessage;

				// eslint-disable-next-line no-alert
				window.alert( msg );
				$btn
					.prop( 'disabled', false )
					.val( cmdData.buttonLabel );
			} )
			.fail( function () {
				// eslint-disable-next-line no-alert
				window.alert( cmdData.errorMessage );
				$btn
					.prop( 'disabled', false )
					.val( cmdData.buttonLabel );
			} );
	}

	/**
	 * Injects the Duplicate Menu button into the submit footer of the menu
	 * editor (#save_menu_footer), positioned after the Save Menu button.
	 *
	 * WordPress renders #save_menu_footer as:
	 *
	 *   <div id="save_menu_footer">
	 *     <span class="delete-action">        ← Delete Menu link (left)
	 *       <a id="delete-nav-menu" …>Delete Menu</a>
	 *     </span>
	 *     <span class="submit-btn">            ← Save Menu button (right)
	 *       <input id="save_menu" name="save_menu" type="submit" …>
	 *     </span>
	 *   </div>
	 *
	 * The Duplicate button is appended after .submit-btn so it sits to the
	 * right of Save Menu, maintaining the left-delete / right-actions
	 * visual convention used by core WordPress admin screens.
	 *
	 * The button is rendered as an <input type="button"> to match the visual
	 * weight and styling of the adjacent Save Menu submit input without
	 * requiring any custom CSS.
	 *
	 * @return {void}
	 */
	function init() {
		// #save_menu_footer is only present when a menu is loaded for editing.
		var $footer = $( '#nav-menu-footer' );

		console.log('$footer', $footer)

		if ( ! $footer.length ) {
			return;
		}

		var $saveBtn = $footer.find( 'input#save_menu_footer' );

		if ( ! $saveBtn.length ) {
			return;
		}

		// Build the button as <input type="button"> to inherit core .button
		// styles without additional CSS specificity conflicts.
		var $duplicateBtn = $( '<input>', {
			id:    'cmd-duplicate-menu',
			type:  'button',
			class: 'button button-secondary',
			value: cmdData.buttonLabel,
		} ).css( { marginLeft: '6px' } );

		// Insert immediately after the Save Menu button.
		$saveBtn.after( $duplicateBtn );

		$duplicateBtn.on( 'click', function () {
			var menuId = getCurrentMenuId();

			if ( menuId <= 0 ) {
				return;
			}

			sendDuplicateRequest( $duplicateBtn, menuId );
		} );
	}

	$( document ).ready( init );

} )( jQuery );
