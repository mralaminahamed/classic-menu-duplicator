import { defineConfig } from '@playwright/test';

/**
 * Base Playwright configuration.
 *
 * The plugin has no end-to-end test suite of its own — PHPUnit covers
 * behaviour. This exists so the WordPress.org listing assets can be
 * regenerated reproducibly; see playwright.wporg-shots.config.ts.
 *
 * WP_BASE_URL points at the site the screenshots are taken from. Local
 * WordPress installs typically serve .test domains over a self-signed
 * certificate, hence ignoreHTTPSErrors.
 */
export default defineConfig( {
	testDir: './tests/e2e',
	timeout: 60_000,
	fullyParallel: false,
	workers: 1,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_BASE_URL || 'https://wc-affiliate.test',
		ignoreHTTPSErrors: true,
		screenshot: 'off',
		video: 'off',
		trace: 'off',
	},
} );
