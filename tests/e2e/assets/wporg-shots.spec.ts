import { test, expect, Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { BRAND, field } from '../brand';

/**
 * Captures the WordPress.org screenshots from a real admin and composes each
 * one into a branded frame.
 *
 *   WP_LOGIN_URL="$(wp login create admin --url-only)" yarn assets:shots
 *
 * The capture is the plugin's real UI — nothing is mocked or drawn. What
 * changed is what surrounds it: these used to be raw admin screenshots, which
 * on a listing page are five pictures of wp-admin with some of this plugin in
 * them, and nothing in the carousel saying whose they are. The frame carries
 * the mark, names the screen, and puts the capture on a card.
 *
 * Captions live in readme.txt under == Screenshots == and must stay in the same
 * order as the numbers here.
 */

const OUT = path.resolve( __dirname, '..', '..', '..', '.wordpress-org' );

/** Final canvas. The plugin directory renders screenshots in a fixed carousel. */
const CANVAS = { width: 1200, height: 900 };

/**
 * Wider viewport for the capture itself, so the real UI is not cramped.
 *
 * The card is 1108x604, near enough 1.83:1, and fills with `object-fit: cover`
 * — so a source much wider than that ratio loses its left and right edges.
 */
const CAPTURE_VIEWPORT = { width: 1720, height: 1010 };

/**
 * Composes one listing image: the real capture inset in a branded frame.
 *
 * Field, icon and wordmark, a kicker plus title, the capture on a white card,
 * and a footer line. The field is `field()` from ../brand — the banner's own
 * treatment, tilted closer to vertical for a 4:3 canvas.
 */
function frame( options: {
	shotBase64: string;
	iconSvg: string;
	kicker: string;
	title: string;
} ): string {
	const { shotBase64, iconSvg, kicker, title } = options;

	return `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
	*, *::before, *::after { box-sizing: border-box; }
	html, body { margin: 0; padding: 0; }

	body {
		position: relative;
		width: ${ CANVAS.width }px;
		height: ${ CANVAS.height }px;
		overflow: hidden;
		font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
			Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
		background: ${ field( 172 ) };
		-webkit-font-smoothing: antialiased;
	}

	/* Rim light along the top edge, as on the banners. */
	body::after {
		content: ''; position: absolute; inset: 0; pointer-events: none;
		border-top: 1px solid rgba(255, 255, 255, .18);
	}

	/* Dot texture, mirrored top-right and bottom-left. */
	.dots {
		position: absolute;
		background-image: radial-gradient(rgba(255,255,255,.22) 1.6px, transparent 1.6px);
		background-size: 18px 18px;
	}
	.dots.tr { top: 38px; right: 44px; width: 168px; height: 96px; }
	.dots.bl { bottom: 54px; left: 44px; width: 96px; height: 60px; opacity: .5; }

	.brand {
		position: absolute; top: 44px; left: 52px;
		display: flex; align-items: center; gap: 16px;
	}

	/* No plate behind the icon — it sits straight on the field, and gets no
	   CSS radius: the squircle and the shadow's shape come from the artwork's
	   own alpha. A radius here would re-clip corners the artwork has already
	   rounded. */
	.brand svg {
		width: 58px; height: 58px; display: block;
		filter: drop-shadow(0 6px 14px rgba(${ BRAND.shadow }, .55));
	}

	/* Wordmark and tagline stack, so the mark reads as a lockup rather than a
	   floating word. The tagline is the one line that says what the plugin is —
	   the frame's heading names the screen, not the product. */
	.brand-text { display: flex; flex-direction: column; gap: 3px; }
	.brand-name { color: #fff; font-size: 27px; font-weight: 700; letter-spacing: -.01em; line-height: 1; }
	.brand-tagline {
		color: rgba(255,255,255,.66); font-size: 13.5px; font-weight: 500;
		letter-spacing: .01em; line-height: 1;
	}

	.head { position: absolute; top: 126px; left: 0; right: 0; text-align: center; }
	.kicker {
		color: ${ BRAND.cyan };
		font-size: 15px; font-weight: 700; letter-spacing: .22em; text-transform: uppercase;
	}
	.title {
		color: #fff; font-size: 52px; font-weight: 800; letter-spacing: -.025em;
		margin-top: 8px; line-height: 1.05;
	}

	.card {
		position: absolute; left: 46px; right: 46px; top: 244px; height: 604px;
		background: #fff; border-radius: 22px; padding: 12px;
		box-shadow:
			0 30px 70px -20px rgba(${ BRAND.shadow }, .7),
			0 10px 24px -12px rgba(${ BRAND.shadow }, .5);
		overflow: hidden;
	}
	.card img {
		width: 100%; height: 100%; display: block;
		object-fit: cover; object-position: top center;
		border-radius: 12px;
	}

	.foot {
		position: absolute; left: 0; right: 0; bottom: 26px; text-align: center;
		color: rgba(255,255,255,.62); font-size: 15px;
	}
</style>
</head>
<body>
	<div class="dots tr"></div>
	<div class="dots bl"></div>

	<div class="brand">
		${ iconSvg }
		<div class="brand-text">
			<div class="brand-name">Swift Menu Duplicator</div>
			<div class="brand-tagline">Duplicate, export and restore WordPress menus</div>
		</div>
	</div>

	<div class="head">
		<div class="kicker">${ kicker }</div>
		<div class="title">${ title }</div>
	</div>

	<div class="card">
		<img src="data:image/png;base64,${ shotBase64 }" alt="">
	</div>

	<div class="foot">Swift Menu Duplicator &middot; Menus, duplicated in one click</div>
</body>
</html>`;
}

// The listing icon itself, so the frames cannot show a stale copy of the mark.
const iconSvg = readFileSync( path.join( OUT, 'icon.svg' ), 'utf8' );

/**
 * Captures the current page and writes the composed frame to disk.
 *
 * The viewport is put back afterwards: these run serially in one page, and the
 * next test would otherwise open at the 1200x900 canvas size.
 */
const shot = async (
	page: Page,
	n: number,
	kicker: string,
	title: string
): Promise< void > => {
	// Let layout settle: notices and panels shift the page as they appear.
	await page.waitForTimeout( 500 );

	const shotBase64 = ( await page.screenshot() ).toString( 'base64' );

	// Compose on a blank page so the frame's styles cannot inherit anything
	// from wp-admin.
	await page.setViewportSize( CANVAS );
	await page.setContent( frame( { shotBase64, iconSvg, kicker, title } ) );
	await page.waitForTimeout( 300 );

	await page.screenshot( { path: path.join( OUT, `screenshot-${ n }.png` ) } );

	await page.setViewportSize( CAPTURE_VIEWPORT );
};

/**
 * Strips the surrounding WordPress chrome so each shot is about this plugin.
 *
 * The admin bar and the admin menu are removed rather than dimmed: they take
 * roughly a quarter of a 1440x900 frame, and in a directory listing that is a
 * quarter spent on furniture every WordPress user has already seen. Notices
 * other plugins inject go too, for the same reason.
 */
async function tidyChrome( page: Page ): Promise< void > {
	await page.addStyleTag( {
		content: `
			#wpadminbar,
			#adminmenumain, #adminmenuback, #adminmenuwrap,
			#wpfooter,
			#screen-meta-links .screen-meta-toggle:not(:first-child) {
				display: none !important;
			}

			.notice:not(.swmd-keep), .update-nag, .updated, .error { display: none !important; }

			/* Reclaim the space the removed chrome was holding. */
			html.wp-toolbar { padding-top: 0 !important; }
			#wpcontent, #wpbody-content, #wpfooter { margin-left: 0 !important; }
			#wpcontent { padding-left: 24px !important; }
			#wpbody { padding-top: 12px !important; }

			/* The snapshot panel offsets itself by the admin bar height. */
			:root { --wp-admin--admin-bar--height: 0px !important; }

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

	test.beforeEach( async ( { page } ) => {
		await page.setViewportSize( CAPTURE_VIEWPORT );
	} );

	test( '1 — duplicate a menu from the editor', async ( { page } ) => {
		await page.goto( '/wp-admin/nav-menus.php' );
		await tidyChrome( page );

		const duplicate = page.locator( '#swmd-duplicate-menu' );
		await expect( duplicate ).toBeVisible();

		// Open the name dialog: the button alone is not a picture of a feature.
		await duplicate.click();
		await expect( page.locator( '#swmd-modal' ) ).toBeVisible();
		await page.waitForTimeout( 400 );

		/*
		 * Nothing may touch the admin after this point.
		 *
		 * shot() composes the frame by replacing the document, so the page is
		 * no longer wp-admin when it returns — the modal's cancel button used
		 * to be clicked here for tidiness and now resolves to nothing. There is
		 * nothing to tidy either way: the next test navigates, and the dialog
		 * was never submitted.
		 */
		await shot( page, 1, 'One click', 'Duplicate a menu' );
	} );

	test( '2 — Menu Manager', async ( { page } ) => {
		await page.goto( '/wp-admin/themes.php?page=swmd-menu-manager' );
		await tidyChrome( page );

		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();

		await shot( page, 2, 'Every menu', 'Menu Manager' );
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

		await shot( page, 3, 'Before you edit', 'Snapshots' );
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

		await shot( page, 4, 'Move between sites', 'JSON import' );
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

		await shot( page, 5, 'Nothing is lost', 'Undo a delete' );
	} );
} );
