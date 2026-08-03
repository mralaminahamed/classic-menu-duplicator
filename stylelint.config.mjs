/**
 * Stylelint configuration.
 *
 * The plugin ships plain CSS (no SCSS, no build step), so it extends the base
 * @wordpress/stylelint-config rather than its /scss variant.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-stylelint-config/
 */

export default {
	extends: [ '@wordpress/stylelint-config' ],

	ignoreFiles: [
		'node_modules/**',
		'vendor/**',
		'release/**',
		'**/*.min.css',
	],

	rules: {
		// Admin CSS targets WordPress core markup, whose class and ID names
		// follow core's conventions rather than ours.
		'selector-class-pattern': null,
		'selector-id-pattern': null,
		'no-descending-specificity': null,
	},
};
