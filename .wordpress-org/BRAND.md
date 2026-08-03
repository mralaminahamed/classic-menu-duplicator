# Swift Menu Duplicator — Brand & Asset System

> Maintained by Al Amin Ahamed. This document is the single source of truth for the visual identity of the plugin. Any contributor producing new marketing or directory assets must follow the tokens and construction rules below so the asset family remains coherent.

---

## 1. Asset Inventory

The WordPress.org plugin directory consumes the following files from this folder:

| File | Dimensions | Purpose | Source |
|---|---|---|---|
| `icon.svg` | vector | Canonical vector icon. WordPress.org consumes this directly when present. | Authored here (production master) |
| `icon-256x256.png` | 256×256 | Directory hero icon (retina) | Rasterised from `icon.svg` |
| `icon-128x128.png` | 128×128 | Directory thumbnail | Rasterised from `icon.svg` |
| `banner-1544x500.png` | 1544×500 | Desktop directory banner (retina) | Rendered from `resources/brand/banner.html` |
| `banner-772x250.png` | 772×250 | Mobile / non-retina banner | Rendered from the same markup at the narrow variant |
| `screenshot-1.png` … `screenshot-5.png` | 1440×900 | Feature screenshots | Captured from a live admin |

Everything in this folder is generated — see §6. `icon.svg` and
`resources/brand/banner.html` are the only files edited by hand.

---|---|---|---|
| `icon.svg` | vector | Canonical vector icon. WordPress.org plugin directory consumes this directly when available. | Authored in this folder (production master) |
| `icon-256x256.png` | 256×256 | Directory hero icon (retina) | Rasterised from `icon.svg` |
| `icon-128x128.png` | 128×128 | Directory thumbnail | Rasterised from `icon.svg` |
| `banner-1544x500.png` | 1544×500 | Desktop directory banner (retina) | Rasterised from `banner-1544x500.draft.svg` |
| `banner-772x250.png` | 772×250 | Mobile / non-retina banner | Rasterised from `banner-772x250.draft.svg` *(paired composition — see §4)* |
| `screenshot-1.png` … `screenshot-5.png` | 1280×800 each | Feature screenshots | Hand-captured from admin UI |

All PNGs are exported by `bin/build-brand-assets.sh` (see §6). The SVG masters in this folder are the canonical source — do not hand-edit the PNGs.

---

## 2. Design Tokens

### 2.1 Colour

| Token | Hex | Usage |
|---|---|---|
| `--brand-surface-1` | `#0F172A` | Banner background top stop |
| `--brand-surface-2` | `#0B1220` | Banner background bottom stop, icon background |
| `--brand-primary-from` | `#3B82F6` | Primary gradient start (menu card top-left) |
| `--brand-primary-to` | `#1E40AF` | Primary gradient end (menu card bottom-right) |
| `--brand-primary-back-from` | `#1E3A8A` | Back card gradient start |
| `--brand-primary-back-to` | `#172554` | Back card gradient end |
| `--brand-accent-from` | `#22D3EE` | Cyan accent / duplicate-badge gradient start |
| `--brand-accent-to` | `#0EA5E9` | Cyan accent / duplicate-badge gradient end |
| `--brand-on-dark` | `#FFFFFF` | All foreground text on banner |
| `--brand-on-dark-muted` | `rgba(255,255,255,0.72)` | Tagline |
| `--brand-on-dark-faint` | `rgba(255,255,255,0.45)` | Credibility strip |

Rationale: the primary blue family sits in the same hue range as the WordPress admin chrome (`#2271B1`), which makes the icon feel native inside the admin Plugins list while still being distinct. Cyan is reserved exclusively as a *signal* colour — duplicate badge, accent rule under the wordmark, highlighted row in the banner UI fragment. It must never carry decoration that does not communicate the duplicate/copy action.

### 2.2 Typography

| Role | Family (production) | Weight | Size | Tracking |
|---|---|---|---|---|
| Wordmark (desktop banner) | Inter | 800 | 72px | -2% |
| Wordmark (mobile banner) | Inter | 800 | 40px | -1.2% |
| Tagline | Inter | 400 | 26px | 0 |
| Lead pill label | Inter | 600 | 20px | 0 |
| Secondary feature line | Inter | 500 | 18px | 0 |
| Credibility strip | Inter | 500 | 14px | +1.2% (letter-spacing) |
| Faux UI table headers | Inter | 600 | 11px | +0.6% |

Inter is preferred. Manrope ExtraBold is an acceptable substitute for the wordmark if Inter is unavailable. The draft SVGs use a system-font fallback stack (`Inter, 'Helvetica Neue', Arial, sans-serif`) so they render anywhere; the production raster pipeline (§6) embeds Inter when present on the build host.

### 2.3 Geometry

- **Squircle radius (icon)**: 22% of the side length (256 → 56px, 128 → 28px). Matches the macOS Big Sur / iOS icon grid and looks deliberately modern next to flatter WP.org siblings.
- **Card corner radius (banner UI fragment)**: 14px window chrome, 6px row chips, 19px pill (half height).
- **Icon safe area**: 12% inset on all sides. Nothing semantic may touch the outer 8px of a 256px icon — the WP admin Plugins list adds its own 1px border that visually clips beyond that.
- **Banner safe area (desktop)**: 72px left/right gutter, 80px top, 60px bottom. The bottom credibility strip lives below the safe area and may be sacrificed in cropped previews without losing meaning.

### 2.4 Depth

A single soft shadow is permitted on cards and the central icon stack: `0 3–10px 0 rgba(0,0,0,0.35)` with a 3–14px Gaussian blur depending on element size. No second light source, no inner shadow, no glow.

---

## 3. Icon Construction

The icon direction is **Menu + Offset Copy**, locked in on 2026-08-03 and
stored as `.wordpress-org/icon.svg`. It is the production master; do not
author alternative icon directions in this folder.

### Why this direction was chosen

The mark combines the two glyphs its audience already knows:

1. **Three stacked bars on a card** — the menu idiom. Every WordPress user
   reads it as "a navigation menu" without being taught.
2. **A second card offset behind it** — the copy idiom every desktop operating
   system uses for duplicate.

Together they state the plugin's promise in one shape: *this menu, and a copy
of it*. The previous direction (Twin Chevron) was a competent abstract mark,
but it communicated "fast" and nothing else — neither *menu* nor *duplicate*
was legible in it, so the icon carried no meaning a first-time browser of the
directory could decode. Icon guidance is consistent on this point: if the
symbolic association does not land within about five seconds, the link between
word, action, and symbol is too weak to be worth the abstraction.

The trade is deliberate. This mark is less "logo-like" than a monogram, but a
plugin icon is not a company logo — it is a wayfinding device in a grid of
several thousand competitors, and legibility beats abstraction there.

### Construction rules

Built entirely from rounded rectangles, so it survives every size it will be
seen at — 256px in the directory hero, 128px in the listing grid, 32px in the
Plugins screen, 16px in a browser tab.

- **Background**: squircle, radius 56px at 256 (22% of the canvas), vertical
  gradient `#0F172A` → `#0B1220`.
- **Back card**: `x=96 y=52 w=108 h=132 r=16`, diagonal gradient `#3B82F6` →
  `#1E40AF` at 55% opacity. It reads as *behind*, never as a second subject.
- **Front card**: `x=52 y=72 w=124 h=132 r=18`, solid `#FFFFFF`. Pure white,
  not tinted — the contrast against the dark ground is what makes the mark
  visible at 32px.
- **Menu bars**: three rounded bars, 14px tall, `r=7`, at `y=104 / 132 / 160`,
  widths `80 / 56 / 80`, horizontal gradient `#22D3EE` → `#0EA5E9`. The short
  middle bar is load-bearing: with three equal bars the shape reads as a
  document, not a menu.
- **Negative space**: no element may sit closer than 40px to the squircle edge
  at the 256 master.

## 4. Banner Composition

Both banner variants — 1544×500 and 772×250 — use the **same brand-forward composition**. They share the 3.088:1 aspect ratio (the mobile is geometrically a 50% scale of the desktop) and the same four-element arrangement:

1. **Wordmark block** (left): plugin name, one-line tagline, and a row of capability pills. Held to 66% of the canvas width so it never collides with the visual.
2. **Atmospheric glow** centred on the mark's visual centre — two concentric circles in cyan and indigo at low opacity
3. **Two-line wordmark + cyan accent rule + single tagline** (right of the mark)
4. **Credibility strip** along the bottom edge

**No faux UI fragment. No feature pills. No secondary feature line.** The brand-forward composition is the pattern Yoast and Elementor use: the mark IS the banner's hero, and brand recognition becomes the primary effect.

**Hierarchy rule:** the brand mark is the visual anchor. The wordmark is second; the tagline supports it; the credibility strip is tertiary.

### 4.1 Shared specification

The two banners are derived from a single specification. Every value is expressed proportionally so that the relationship between elements is identical at both sizes. Where a value cannot be linearly scaled (font weight, letter-spacing percentage, opacity), the value is fixed identically across both.

| Property | Desktop (1544×500) | Mobile (772×250) | Rule |
|---|---|---|---|
| Background gradient | `#1E1B4B → #0F172A (55%) → #0B1220` | identical | Fixed across both |
| Dot pattern | 36×36, r=1.2, opacity 0.05 | 24×24, r=0.8, opacity 0.05 | Opacity is fixed at 0.05 |
| Atmospheric glow centre | `cx: 380, cy: 250` (24.6% W, 50% H) | `cx: 190, cy: 125` (same proportions) | Centred on mark's visual centre |
| Glow outer | `r: 380` (76% of H), fill cyan, opacity 0.08 | `r: 190` (same %), same fill / opacity | Proportional radius, fixed opacity |
| Glow inner | `r: 220` (44% of H), fill blue, opacity 0.10 | `r: 110` (same %), same fill / opacity | Proportional radius, fixed opacity |
| Mark anchor | `translate(120, -10)` (7.8% W, -2% H) | `translate(60, -5)` (same %) | Mark vertical bleed proportional |
| Mark visual height | ~328px (~66% of H) | ~164px (~66% of H) | Constant percentage of canvas height |
| Card width | 210 | 105 | 50% scale |
| Card height | 262 | 131 | 50% scale |
| Back card opacity | 0.5 | 0.5 | Fixed across both |
| Wordmark anchor x | 720 (46.6% of W) | 360 (46.6% of W) | Same proportion |
| Wordmark anchor y | 178 (35.6% of H) | 89 (35.6% of H) | Same proportion |
| Wordmark font size | 86 | 43 | 50% scale |
| Wordmark line-height | 95 | 48 | 50% scale (≈1.1 × font size) |
| Wordmark tracking | -2.4 | -1.2 | 50% scale |
| Wordmark layout | Two lines: "Swift Menu" / "Duplicator" | identical | Same layout — never one-line on either |
| Accent rule | width 160, height 5, y-offset 25 below wordmark | width 80, height 3, y-offset 12 below wordmark | 50% scale (height rounds to nearest pixel) |
| Tagline | "Duplicate, snapshot, and migrate WordPress menus." 28px, opacity 0.75 | "Duplicate, snapshot, migrate WP menus." 14px, opacity 0.75 | 50% font scale; mobile abbreviates `WordPress → WP` and drops `and` for fit |
| Credibility strip | `PHP 7.4+ · WORDPRESS 6.0+ · HPOS-SAFE · WPML & POLYLANG · WP-CLI · REST API · MULTISITE` (14px) | `PHP 7.4+ · WP 6.0+ · HPOS-SAFE · WPML & POLYLANG · WP-CLI` (10px) | Mobile is a strict subset, abbreviated for fit; tokens must appear in the same order |
| Author signature | 13px, opacity 0.30 | 9px, opacity 0.30 | Same opacity, scaled font |

### 4.2 Why the wordmark is two-line on both

The wordmark text "Swift Menu Duplicator" is 21 characters. At 86px Inter ExtraBold with -2.4% tracking, the single-line rendered width is ~1010px. A single-line wordmark on the 1544×500 canvas would need to start at x=80 and run almost to the right edge, crowding out room for the brand mark on the left. By wrapping to two lines on **both** banners, the wordmark fits comfortably within the right column at any size and — more importantly — the two banners have the same vertical text mass relative to their canvases, which is the principal "feels consistent" signal.

### 4.3 Consistency rule

If a contributor changes any value above on one banner, they must update both banners and the table above. The two files are a paired set, not independent designs. A pull request that touches only one banner is incomplete.

---

## 5. Asset Workflow

```
SVG masters (this folder, *.draft.svg)
        │
        ▼
bin/build-brand-assets.sh         ← run locally OR in CI (brand-assets.yml)
        │
        ▼
PNG production assets (this folder, *.png)
        │
        ▼
bin/stage-wp-org-assets.sh        ← runs inside the SVN workflows
        │
        ▼
.wp-org-staged/   (curated payload — drafts and docs excluded)
        │
        ▼
SVN commit to assets/ in WordPress.org plugin repo
```

The SVG masters and PNG outputs are both versioned in git so a contributor can see the rendered result without re-running the build. They are regenerated from the SVG before an SVN release. The staging step then produces a **clean** asset folder that contains only the files WordPress.org will actually display.

### 5.1 Files that ship to WordPress.org SVN

Only the following file patterns are eligible to be published to the SVN `assets/` directory:

| Pattern | Purpose |
|---|---|
| `icon-*.png` / `icon-*.svg` | Directory icons (128×128, 256×256) |
| `banner-*.png` / `banner-*.svg` | Directory banners (772×250, 1544×500) |
| `screenshot-*.png` / `screenshot-*.jpg` | Feature screenshots |

### 5.2 Files that NEVER ship to WordPress.org SVN

The following are excluded by `bin/stage-wp-org-assets.sh` and must never appear in the SVN `assets/` directory:

| Pattern | Reason |
|---|---|
| `*.draft.svg` / `*.draft.*` | Concept drafts — kept in git for design iteration only |
| `BRAND.md` | Internal design system documentation |
| `README.md` | Any internal README files |
| `.DS_Store`, `._*`, `Thumbs.db` | OS noise |
| `.gitkeep`, `.svn`, `.git` | VCS metadata |

The staging script uses both an allow-list (files must match one of §5.1) and a deny-list (files must not match any pattern above) as defence-in-depth — an unexpected file dropped into `.wordpress-org/` will be rejected even if it does not match the explicit exclusions.

Run a dry-run from the project root at any time to see exactly what would be published:

```sh
bash bin/stage-wp-org-assets.sh --list
```

The plugin code ZIP is a separate concern: `/.wordpress-org/` is already excluded in its entirety from the plugin ZIP via `.distignore`, so none of the design files (drafts, BRAND.md, or the production PNGs) ship with the plugin itself. They reach WordPress.org only via the SVN `assets/` folder, and only through the staging script.

---

## 6. Building the Assets

Everything in this folder is produced by Playwright, driving a Chrome that is
already installed — nothing to rasterise by hand, and no ImageMagick or
librsvg dependency. The two commands are independent: the brand assets need
no running site, the screenshots need a logged-in one.

```sh
yarn install

# icon-*.png and banner-*.png — renders local markup only
yarn assets:brand

# screenshot-*.png — drives a real WordPress admin
WP_LOGIN_URL="$(wp login create admin --url-only)" yarn assets:shots
```

### 6.1 How it is wired

| File | Role |
|---|---|
| `playwright.config.ts` | Base config: `WP_BASE_URL`, self-signed certs tolerated |
| `playwright.wporg-shots.config.ts` | The `brand` and `shots` projects, viewport pinned to 1440×900 |
| `tests/e2e/auth.setup.ts` | Signs in once, stores the session for `shots` |
| `tests/e2e/assets/wporg-brand.spec.ts` | Icon PNGs from `icon.svg`; banners from `resources/brand/banner.html` |
| `tests/e2e/assets/wporg-shots.spec.ts` | The five listing screenshots |

`channel: 'chrome'` is deliberate: it uses the installed browser instead of
requiring `playwright install`, so the pipeline works on a laptop that
already has Chrome.

### 6.2 Authentication

`auth.setup.ts` takes either a one-time magic link (`WP_LOGIN_URL`, e.g. from
`wp login create <user> --url-only`) or `WP_ADMIN_USER` + `WP_ADMIN_PASS`. No
credential is ever written to the repository; the session lands in
`tests/e2e/.auth/`, which is git-ignored.

### 6.3 Regenerating after a UI change

Any change to the Menu Manager, the snapshot panel, or the import screen
invalidates the screenshots. Re-run `yarn assets:shots` and commit whatever
changed — the captions in `readme.txt` are positional, so if a shot is added
or removed the list there has to move with it.

## 7. Screenshot Standards

When refreshing `screenshot-1.png` … `screenshot-5.png`:

- Target 1280×800 px exactly. Use a 1× device pixel ratio capture, not retina — WP.org displays them at smaller sizes and a non-pixel-perfect source makes the chrome blurry.
- Frame each screenshot in a consistent Chromeless / Browser-Mockup style with the same 24px outer margin.
- Keep file size under 250 KB per image. Run `oxipng -o 4 --strip safe` after capture.
- Caption order in `readme.txt` must match the file numbering — never rearrange one without the other.

---

## 8. Forbidden

The following are explicitly outside the system. Reject any contribution that introduces them:

- Orange or amber as a primary accent (reads as a warning state; conflicts with the duplicate semantic).
- The `+` glyph in any badge position (means *create*, not *duplicate*).
- Photographic textures, noise, or grain on any surface.
- Decorative typography (script, serif display, condensed). Inter only.
- Drop shadows beyond the single soft shadow described in §2.4.
- Bullet-point lists of feature names in the banner. Feature names go in the muted secondary line or in `readme.txt`; the banner promotes *one* feature.
- Adding an element to one banner and not the other. The two banners are a paired set and must remain compositionally consistent.
- Reintroducing a faux UI fragment, feature pills, or feature-name lists into the banner. The brand-forward composition is the system; secondary chrome is forbidden.
- Treating the icon as a small decorative element on the banner. The mark is the hero — it must dominate the left third of the canvas.

---

## 9. Change Log

| Date | Change | Author |
|---|---|---|
| 2026-08-03 | Icon redirected to **Menu + Offset Copy**. The Twin Chevron read as "fast" but carried neither *menu* nor *duplicate*, so it told a directory browser nothing about the plugin. The new mark composes the two idioms its audience already knows — stacked bars for a menu, an offset card for a copy — and is built from primitives so it holds together down to 16px. Banners rebuilt around a wordmark block plus a single card-stack visual, rendered from HTML rather than hand-authored SVG. Asset pipeline moved to Playwright (see §6); the PHP/Imagick generators and the rejected concept drafts were removed. | Al Amin Ahamed |
| 2026-05-22 | Initial brand system. Replaced misleading orange `+` icon with Hierarchy Clone direction. Banner restructured around proof (faux Menu Manager UI fragment) rather than feature-name pills. | Al Amin Ahamed |
| 2026-05-22 | Added `bin/stage-wp-org-assets.sh` and wired it into both SVN workflows (`svn-readme-assets-update.yml`, `svn-deploy.yml`) so design drafts (`*.draft.svg`) and internal docs (`BRAND.md`) never reach WordPress.org SVN. | Al Amin Ahamed |
| 2026-05-22 | Unified banner composition. Rebuilt the mobile banner to mirror the desktop layout (wordmark + tagline + lead pill + faux Menu Manager fragment + credibility strip) with size-tuned adaptations. The two banner files are now a paired set. | Al Amin Ahamed |
| 2026-05-22 | Finalised the icon direction. Concept 1 (Hierarchy Clone) promoted to `.wordpress-org/icon.svg` as the production vector master; concepts 2 (Arrow Cycle) and 3 (Stacked Sheets) rejected. `bin/build-brand-assets.sh` and `bin/stage-wp-org-assets.sh` updated to consume the new master. | Al Amin Ahamed |
| 2026-05-22 | Pivoted to a brand-monogram direction after a competitive teardown of Yoast, Elementor, and Duplicator showed that top plugins use abstract brand marks rather than UI illustrations. Twin Chevron mark replaces Hierarchy Clone as `.wordpress-org/icon.svg`. Banners rebuilt brand-forward: oversized mark on the left, large wordmark on the right, no faux UI fragment, no feature pills. The mark now carries dual semantics — "Swift" (fast-forward chevron) + "Duplicate" (doubled form). | Al Amin Ahamed |
| 2026-05-22 | Resolved seven UI inconsistencies between the two banners. Critical fix: desktop wordmark was overflowing the canvas at 86px single-line — converted to two-line composition matching mobile. Also harmonised dot-pattern opacity (0.05 across both), tagline opacity (0.75 across both), credibility-strip token sets (mobile is now a strict subset of desktop), atmospheric-glow position and radii (now in exact proportional 50% scale on mobile), mark vertical translate (-5 on mobile = 50% of -10 on desktop), and mark-to-wordmark gap (both at 46.6% of canvas width). Replaced the loose "size adaptation" table in §4 with a strict shared-spec table that lists every proportional value. | Al Amin Ahamed |
