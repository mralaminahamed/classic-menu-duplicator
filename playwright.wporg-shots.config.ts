import base from './playwright.config';
import { defineConfig } from '@playwright/test';

/**
 * Config for regenerating the WordPress.org listing assets.
 *
 *   yarn assets:brand   — icon + banners (renders markup, no site needed)
 *   yarn assets:shots   — screenshots (drives a logged-in WordPress admin)
 *
 * Uses `channel: 'chrome'` so it drives an already-installed Chrome rather
 * than requiring `playwright install`. Screenshots are captured at 1720x1010
 * and composed onto a 1200x900 branded canvas, so every image in
 * .wordpress-org/ comes out the same size whatever the source page.
 *
 * The two projects differ in what they need: `shots` drives a logged-in
 * WordPress install, so it depends on `setup`; `brand` only renders local
 * markup, so it needs neither auth nor a running site.
 */
export default defineConfig( {
	...base,
	projects: [
		{ name: 'setup', testMatch: 'auth.setup.ts', use: { channel: 'chrome' } },
		{
			name: 'brand',
			testMatch: 'assets/wporg-brand.spec.ts',
			use: { channel: 'chrome' },
		},
		{
			name: 'shots',
			testMatch: 'assets/wporg-shots.spec.ts',
			dependencies: [ 'setup' ],
			use: {
				channel: 'chrome',
				// The capture viewport, wider than the 1200x900 canvas the frames
				// compose onto: the card fills with `object-fit: cover`, so a
				// source much wider than ~1.83:1 loses its left and right edges.
				// wporg-shots.spec.ts re-asserts this before every test, since
				// composing a frame changes the viewport.
				viewport: { width: 1720, height: 1010 },
				storageState: 'tests/e2e/.auth/admin.json',
			},
		},
	],
} );
