/**
 * ESLint flat configuration.
 *
 * ESLint 10 dropped support for `.eslintrc.*` entirely, and
 * @wordpress/eslint-plugin's eslintrc wrapper is deprecated, so the config
 * lives here in flat format.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-eslint-plugin/
 */

import wordpress from '@wordpress/eslint-plugin';

export default [
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'release/**',
			'languages/**',
			'.wordpress-org/**',
			'**/*.min.js',
		],
	},

	// WordPress house style. `recommended-with-formatting` keeps formatting on
	// native ESLint rules — the plugin ships no build step and no Prettier.
	...wordpress.configs[ 'recommended-with-formatting' ],
	...wordpress.configs.i18n,

	{
		files: [ 'assets/js/**/*.js' ],

		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'script',
			globals: {
				// Browser + WordPress admin environment.
				document: 'readonly',
				window: 'readonly',
				setTimeout: 'readonly',
				jQuery: 'readonly',
				// Localised by wp_localize_script().
				swmdData: 'readonly',
				swmdManagerData: 'readonly',
			},
		},

		rules: {
			'@wordpress/i18n-text-domain': [
				'error',
				{ allowedTextDomain: 'swift-menu-duplicator' },
			],
		},
	},
];
