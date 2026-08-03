import { test as setup, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Signs in to WordPress once and saves the session for the screenshot run.
 *
 * Two ways in, so this works on a laptop and in CI without hardcoding
 * anything into the repository:
 *
 *   WP_LOGIN_URL   a one-time magic link, e.g. from `wp login create <user>
 *                  --url-only` (wp-cli-login-server). Nothing to store.
 *   WP_ADMIN_USER  / WP_ADMIN_PASS — the ordinary login form.
 */

const AUTH_FILE = path.join( 'tests', 'e2e', '.auth', 'admin.json' );

setup( 'authenticate', async ( { page } ) => {
	fs.mkdirSync( path.dirname( AUTH_FILE ), { recursive: true } );

	const magicLink = process.env.WP_LOGIN_URL;

	if ( magicLink ) {
		await page.goto( magicLink );
	} else {
		const user = process.env.WP_ADMIN_USER;
		const pass = process.env.WP_ADMIN_PASS;

		if ( ! user || ! pass ) {
			throw new Error(
				'Set WP_LOGIN_URL, or WP_ADMIN_USER and WP_ADMIN_PASS, before running the shots project.'
			);
		}

		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', user );
		await page.fill( '#user_pass', pass );
		await page.click( '#wp-submit' );
	}

	// The admin bar only renders for a signed-in user.
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible( { timeout: 30_000 } );

	await page.context().storageState( { path: AUTH_FILE } );
} );
