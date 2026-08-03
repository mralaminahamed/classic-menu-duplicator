import base from './playwright.config';
import { defineConfig } from '@playwright/test';

/**
 * Config for regenerating the WordPress.org listing assets.
 *
 *   yarn assets:brand   — icon + banners (renders markup, no site needed)
 *   yarn assets:shots   — screenshots (drives a logged-in WordPress admin)
 *
 * Uses `channel: 'chrome'` so it drives an already-installed Chrome rather
 * than requiring `playwright install`, and pins the viewport to 1440x900 so
 * every screenshot in .wordpress-org/ comes out the same size.
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
				viewport: { width: 1440, height: 900 },
				storageState: 'tests/e2e/.auth/admin.json',
			},
		},
	],
} );
