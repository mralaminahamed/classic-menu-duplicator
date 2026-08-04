/**
 * The brand palette the listing assets are painted with.
 *
 * One module, imported by the banner renderer and the screenshot frames, so a
 * colour change reaches every surface at once. `.wordpress-org/icon.svg` still
 * carries its own copy because SVG cannot import; its header names this file as
 * canonical.
 *
 * The palette itself is unchanged — deep navy with a blue-to-cyan accent. It is
 * this plugin's identity and there was no reason to touch it; what changed is
 * that the screenshots now use it too, rather than being raw admin captures
 * with nothing of the plugin's own around them.
 */
export const BRAND = {
	/** Ground, darkest first. The banner ramps between these two. */
	ink: '#0B1220',
	inkLift: '#0F172A',

	/** Accent, deep to bright. The wordmark ramps between the last two. */
	deep: '#1E40AF',
	blue: '#3B82F6',
	sky: '#38BDF8',
	cyan: '#22D3EE',

	/** Shadow colour under white cards, so shadows read as the same navy. */
	shadow: '4, 10, 26',
} as const;

/**
 * The field, as stacked CSS backgrounds.
 *
 * Reproduces the banner's own treatment — a cyan bloom over a navy ramp — so a
 * frame and a banner look cut from the same material. `angle` tilts the ramp: a
 * wide banner reads better on a diagonal, a 4:3 frame on something closer to
 * vertical.
 */
export function field( angle = 160 ): string {
	return [
		// Cyan bloom, low and to the right.
		'radial-gradient(70% 90% at 80% 106%, rgba(34, 211, 238, .20) 0%, rgba(34, 211, 238, 0) 68%)',
		// Specular sweep off the top edge, spent by the top third.
		'linear-gradient(to bottom, rgba(255, 255, 255, .08) 0%, rgba(255, 255, 255, 0) 34%)',
		// Ground.
		`linear-gradient(${ angle }deg, ${ BRAND.inkLift } 0%, ${ BRAND.ink } 100%)`,
	].join( ', ' );
}
