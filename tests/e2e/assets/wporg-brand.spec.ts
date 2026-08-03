import { test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

/**
 * Renders the WordPress.org icon and banner PNGs from their source markup.
 *
 *   yarn assets:brand
 *
 * Needs no running site — everything is local files. The SVG in
 * .wordpress-org/icon.svg is the icon master; the PNGs are rasterised from
 * it here so they can never drift from the vector.
 */

const ROOT = path.resolve( __dirname, '..', '..', '..' );
const OUT = path.join( ROOT, '.wordpress-org' );

const fileUrl = ( relative: string ) =>
	pathToFileURL( path.join( ROOT, relative ) ).toString();

test.describe( 'WordPress.org brand assets', () => {
	test( 'icon PNGs', async ( { page } ) => {
		// Inlined rather than loaded as a document: an SVG page has no <head>
		// to style, and as an <img> the gradients rasterise at the intrinsic
		// size before scaling. Inline markup renders crisply at any size.
		const svg = fs.readFileSync( path.join( OUT, 'icon.svg' ), 'utf8' );

		for ( const size of [ 256, 128 ] ) {
			await page.setViewportSize( { width: size, height: size } );
			await page.setContent(
				`<!DOCTYPE html><html><head><style>
					html,body{margin:0;padding:0;background:transparent}
					svg{display:block;width:${ size }px;height:${ size }px}
				</style></head><body>${ svg }</body></html>`
			);

			await page.screenshot( {
				path: path.join( OUT, `icon-${ size }x${ size }.png` ),
				omitBackground: true,
			} );
		}
	} );

	test( 'banner PNGs', async ( { page } ) => {
		const variants = [
			{ width: 1544, height: 500, narrow: false },
			{ width: 772, height: 250, narrow: true },
		];

		for ( const variant of variants ) {
			await page.setViewportSize( { width: variant.width, height: variant.height } );
			await page.goto( fileUrl( 'resources/brand/banner.html' ) );

			await page.evaluate( ( isNarrow ) => {
				document.getElementById( 'banner' )?.classList.toggle( 'is-narrow', isNarrow );
			}, variant.narrow );

			// Web fonts, if any resolved, must be settled before capture.
			await page.evaluate( () => document.fonts.ready );

			const banner = page.locator( '#banner' );

			await banner.screenshot( {
				path: path.join( OUT, `banner-${ variant.width }x${ variant.height }.png` ),
			} );
		}
	} );
} );
