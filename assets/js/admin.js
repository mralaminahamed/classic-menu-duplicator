/* global wmdData, jQuery */
( function ( $ ) {
	'use strict';

	/**
	 * Inject the Duplicate Menu button immediately after the Select button
	 * in the "Select a menu to edit" toolbar on nav-menus.php.
	 *
	 * The Select button sits inside #select-nav-menu-actions as a submit
	 * input with name="selectmenu". We insert our button right after it
	 * and disable it whenever no menu is selected.
	 */
	function init() {
		var $selectBtn = $( '#select-nav-menu-actions input[name="selectmenu"]' );

		if ( ! $selectBtn.length ) {
			return;
		}

		var $duplicateBtn = $( '<button>', {
			id:    'wmd-duplicate-menu',
			type:  'button',
			class: 'button',
			text:  wmdData.buttonLabel,
		} ).css( { marginLeft: '4px' } );

		$selectBtn.after( $duplicateBtn );

		var $menuSelect = $( '#nav-menu-meta select[name="menu"]' );

		function syncButtonState() {
			var menuId = parseInt( $menuSelect.val(), 10 );
			$duplicateBtn.prop( 'disabled', ! menuId || menuId <= 0 );
		}

		$menuSelect.on( 'change', syncButtonState );
		syncButtonState();

		$duplicateBtn.on( 'click', function () {
			var menuId = parseInt( $menuSelect.val(), 10 );

			if ( ! menuId || menuId <= 0 ) {
				return;
			}

			$duplicateBtn
				.prop( 'disabled', true )
				.text( wmdData.duplicatingLabel );

			$.ajax( {
				url:    wmdData.ajaxUrl,
				method: 'POST',
				data:   {
					action:  'wmd_duplicate_menu',
					nonce:   wmdData.nonce,
					menu_id: menuId,
				},
			} )
				.done( function ( response ) {
					if ( response.success && response.data && response.data.redirect ) {
						window.location.href = response.data.redirect;
					} else {
						var msg = ( response.data && response.data.message )
							? response.data.message
							: wmdData.errorMessage;
						// eslint-disable-next-line no-alert
						window.alert( msg );
						$duplicateBtn
							.prop( 'disabled', false )
							.text( wmdData.buttonLabel );
					}
				} )
				.fail( function () {
					// eslint-disable-next-line no-alert
					window.alert( wmdData.errorMessage );
					$duplicateBtn
						.prop( 'disabled', false )
						.text( wmdData.buttonLabel );
				} );
		} );
	}

	$( document ).ready( init );

} )( jQuery );
