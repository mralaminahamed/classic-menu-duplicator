import { test, expect, Page } from '@playwright/test';
import path from 'node:path';

/**
 * Captures the WordPress.org screenshots from a real admin.
 *
 *   WP_LOGIN_URL="$(wp login create admin --url-only)" yarn assets:shots
 *
 * Every shot is taken at the 1440x900 viewport pinned in
 * playwright.wporg-shots.config.ts, so the listing gallery does not jump
 * between images. Captions live in readme.txt under == Screenshots == and
 * must stay in the same order as the numbers here.
 */

const OUT = path.resolve( __dirname, '..', '..', '..', '.wordpress-org' );

const shot = async ( page: Page, n: number ) => {
	// Let layout settle: notices and panels shift the page as they appear.
	await page.waitForTimeout( 500 );

	await page.screenshot( { path: path.join( OUT, `screenshot-${ n }.png` ) } );
};

/**
 * Collapses the admin menu and hides notices other plugins inject, so the
 * screenshots show this plugin rather than whatever else is installed.
 */
async function tidyChrome( page: Page ): Promise< void > {
	await page.addStyleTag( {
		content: `
			#wpfooter, #screen-meta-links .screen-meta-toggle:not(:first-child) { display: none !important; }
			.notice:not(.swmd-keep), .update-nag, .updated, .error { display: none !important; }
			#wpadminbar { opacity: .35; }
			/* Freeze motion: a capture mid-transition ghosts the whole page. */
			*, *::before, *::after {
				transition: none !important;
				animation: none !important;
			}
		`,
	} );
}

test.describe( 'WordPress.org screenshots', () => {
	test.describe.configure( { mode: 'serial' } );

	test( '1 — duplicate a menu from the editor', async ( { page } ) => {
		await page.goto( '/wp-admin/nav-menus.php' );
		await tidyChrome( page );

		const duplicate = page.locator( '#swmd-duplicate-menu' );
		await expect( duplicate ).toBeVisible();

		// Open the name dialog: the button alone is not a picture of a feature.
		await duplicate.click();
		await expect( page.locator( '#swmd-modal' ) ).toBeVisible();
		await page.waitForTimeout( 400 );

		await shot( page, 1 );

		await page.locator( '#swmd-modal-cancel' ).click();
	} );

	test( '2 — Menu Manager', async ( { page } ) => {
		await page.goto( '/wp-admin/themes.php?page=swmd-menu-manager' );
		await tidyChrome( page );

		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();

		await shot( page, 2 );
	} );

	test( '3 — snapshot panel', async ( { page } ) => {
		await page.goto( '/wp-admin/nav-menus.php' );
		await tidyChrome( page );

		// Opening the panel fetches the list; wait for that before deciding
		// whether anything needs to be created, or the check races the AJAX.
		const listed = page.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) && response.request().postData()?.includes( 'swmd_get_snapshots' ) === true
		);

		await page.locator( '#swmd-snapshot-toggle' ).click();
		await expect( page.locator( '#swmd-snapshot-panel' ) ).toHaveClass( /is-visible/ );
		await listed;

		// The panel is only worth photographing with something in it.
		if ( ! ( await page.locator( '.swmd-snapshot-item' ).count() ) ) {
			await page.locator( '#swmd-save-snapshot' ).click();
		}

		await expect( page.locator( '.swmd-snapshot-item' ).first() ).toBeVisible();

		await shot( page, 3 );
	} );

	test( '4 — JSON import with find and replace', async ( { page } ) => {
		await page.goto( '/wp-admin/themes.php?page=swmd-menu-manager&tab=import' );
		await tidyChrome( page );

		await page.fill( '#swmd_find', 'https://staging.example.com' );
		await page.fill( '#swmd_replace', 'https://example.com' );

		await expect( page.locator( '.swmd-import-form' ) ).toBeVisible();

		// The form is taller than the viewport; nudge it up so the submit
		// button is in frame rather than sliced off at the bottom edge.
		await page.evaluate( () => window.scrollBy( 0, 140 ) );
		await page.waitForTimeout( 200 );

		await shot( page, 4 );
	} );

	test( '5 — deleting a menu can be undone', async ( { page } ) => {
		const fixture = 'Screenshot Demo Menu';

		// Create a throwaway menu so the shot never deletes real content.
		await page.goto( '/wp-admin/nav-menus.php?action=edit&menu=0' );
		await tidyChrome( page );
		await page.fill( '#menu-name', fixture );
		await page.getByRole( 'button', { name: /create menu/i } ).first().click();
		await expect( page.locator( '#menu-name' ) ).toHaveValue( fixture );

		// Delete it from the Menu Manager, which is where the undo appears.
		await page.goto( '/wp-admin/themes.php?page=swmd-menu-manager' );

		const row = page.locator( 'tr', { hasText: fixture } ).first();
		await row.locator( 'input[name="menu_ids[]"]' ).check();

		await page.selectOption( '#bulk-action-selector-top', 'swmd_bulk_delete' );

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await page.locator( '#doaction' ).click();

		const undo = page.locator( '.notice-warning', { hasText: 'restored' } );
		await expect( undo ).toBeVisible();

		await tidyChrome( page );
		// tidyChrome hides notices wholesale; this one is the subject.
		await undo.evaluate( ( el ) => {
			el.classList.add( 'swmd-keep' );
			( el as HTMLElement ).style.display = '';
		} );

		await shot( page, 5 );
	} );
} );
