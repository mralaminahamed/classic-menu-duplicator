<?php
declare(strict_types=1);

$FONT_BOLD    = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
$FONT_REGULAR = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';
$OUT_DIR      = dirname(__DIR__, 2) . '/.wordpress-org';

@mkdir($OUT_DIR, 0755, true);

// ---------------------------------------------------------------------------
// Measure text width via Imagick so we can verify nothing overflows
// ---------------------------------------------------------------------------
function text_width(Imagick $img, string $font, float $size, string $text): int {
    $d = new ImagickDraw();
    $d->setFont($font);
    $d->setFontSize($size);
    $metrics = $img->queryFontMetrics($d, $text);
    return (int) $metrics['textWidth'];
}

// ---------------------------------------------------------------------------
// Banner generator — fully proportional, nothing bleeds off canvas
// ---------------------------------------------------------------------------
function gen_banner(int $w, int $h, string $file, string $fontBold, string $fontRegular): void {

    // All measurements as fractions of canvas dimensions
    $panelW  = (int) ($w * 0.28);   // left icon panel — 28% of width
    $margin  = (int) ($w * 0.032);  // gap between separator and text
    $textX   = $panelW + 4 + $margin;
    $rightPad = (int) ($w * 0.03);  // minimum right margin

    // Font sizes — relative to height so both banner sizes look identical
    $titleSize   = (float) ($h * 0.155);  // ~39px @ 250h, ~77px @ 500h
    $taglineSize = (float) ($h * 0.072);  // ~18px @ 250h, ~36px @ 500h

    // ── Measure title to ensure it fits; shrink if needed ──────────────────
    $probe = new Imagick();
    $probe->newImage(1, 1, new ImagickPixel('white'));
    $title = 'Swift Menu Duplicator';
    while ($titleSize > 10) {
        $tw = text_width($probe, $fontBold, $titleSize, $title);
        if ($textX + $tw + $rightPad <= $w) break;
        $titleSize -= 1.0;
    }
    $tagline = 'Duplicate · Snapshot · Export/Import · WP-CLI · REST API';
    while ($taglineSize > 8) {
        $tw = text_width($probe, $fontRegular, $taglineSize, $tagline);
        if ($textX + $tw + $rightPad <= $w) break;
        $taglineSize -= 0.5;
    }
    $probe->destroy();

    // ── Build image ─────────────────────────────────────────────────────────
    $img = new Imagick();
    $img->newPseudoImage($w, $h, 'gradient:#1a2744-#0d1b2e');
    $img->setImageFormat('png');

    $draw = new ImagickDraw();

    // Left panel (darker)
    $draw->setFillColor('#0a1220');
    $draw->setStrokeWidth(0);
    $draw->rectangle(0, 0, $panelW, $h);

    // Subtle glow circles in panel
    $draw->setFillColor('#3b82f6');
    $draw->setFillOpacity(0.07);
    $cx = (int) ($panelW / 2);
    $draw->circle($cx, (int) ($h / 2), $cx + $h, (int) ($h / 2));
    $draw->setFillOpacity(1.0);

    // Accent separator
    $draw->setFillColor('#3b82f6');
    $draw->rectangle($panelW, 0, $panelW + 3, $h);

    // ── Icon: two overlapping menu cards ────────────────────────────────────
    $cW  = (int) ($panelW * 0.60);
    $cH  = (int) ($h * 0.48);
    $cR  = (int) ($h * 0.050);
    $cX  = (int) (($panelW - $cW) / 2);
    $cY  = (int) (($h - $cH) / 2);
    $off = (int) ($h * 0.09);

    // back card
    $draw->setFillColor('#1d3f8a');
    $draw->roundRectangle($cX + $off, $cY + $off, $cX + $cW + $off, $cY + $cH + $off, $cR, $cR);

    // front card
    $draw->setFillColor('#3b82f6');
    $draw->roundRectangle($cX, $cY, $cX + $cW, $cY + $cH, $cR, $cR);

    // menu lines
    $draw->setFillColor('#ffffff');
    $lh  = max(2, (int) ($h * 0.030));
    $lx1 = $cX + (int) ($cW * 0.13);
    $lx2 = $cX + (int) ($cW * 0.82);
    foreach ([0.25, 0.47, 0.69] as $fy) {
        $ly = $cY + (int) ($cH * $fy);
        $draw->rectangle($lx1, $ly, $lx2, $ly + $lh);
    }

    $img->drawImage($draw);

    // ── Title ────────────────────────────────────────────────────────────────
    $titleY = (int) ($h * 0.495);

    $dt = new ImagickDraw();
    $dt->setFont($fontBold);
    $dt->setFontSize($titleSize);
    $dt->setFillColor('#f1f5f9');
    $dt->setTextAntialias(true);
    $img->annotateImage($dt, $textX, $titleY, 0, $title);

    // underline
    $titleW = text_width($img, $fontBold, $titleSize, $title);
    $du = new ImagickDraw();
    $du->setFillColor('#3b82f6');
    $lineH = max(2, (int) ($h * 0.020));
    $du->rectangle($textX, $titleY + (int) ($titleSize * 0.15), $textX + $titleW, $titleY + (int) ($titleSize * 0.15) + $lineH);
    $img->drawImage($du);

    // ── Tagline ──────────────────────────────────────────────────────────────
    $dg = new ImagickDraw();
    $dg->setFont($fontRegular);
    $dg->setFontSize($taglineSize);
    $dg->setFillColor('#64748b');
    $dg->setTextAntialias(true);
    $img->annotateImage($dg, $textX, (int) ($h * 0.755), 0, $tagline);

    $img->writeImage($file);
    $img->destroy();

    echo "  ✓ {$file}  (title={$titleSize}px  tagline={$taglineSize}px)\n";
}

// ---------------------------------------------------------------------------
// Icon generator (unchanged — icons look fine)
// ---------------------------------------------------------------------------
function gen_icon(int $size, string $file): void {
    $img = new Imagick();
    $img->newImage($size, $size, new ImagickPixel('transparent'));
    $img->setImageFormat('png');

    $draw = new ImagickDraw();
    $bgR = (int) ($size * 0.20);
    $draw->setFillColor('#0f172a');
    $draw->roundRectangle(0, 0, $size - 1, $size - 1, $bgR, $bgR);

    $pad  = (int) ($size * 0.15);
    $cW   = (int) ($size * 0.62);
    $cH   = (int) ($size * 0.54);
    $cR   = (int) ($size * 0.08);
    $off  = (int) ($size * 0.14);

    $draw->setFillColor('#1d3f8a');
    $draw->roundRectangle($pad + $off, $pad + $off, $pad + $cW + $off, $pad + $cH + $off, $cR, $cR);

    $frontY = $pad + (int) ($size * 0.12);
    $draw->setFillColor('#3b82f6');
    $draw->roundRectangle($pad, $frontY, $pad + $cW, $frontY + $cH, $cR, $cR);

    $draw->setFillColor('#ffffff');
    $lh  = max(2, (int) ($size * 0.055));
    $lx1 = $pad + (int) ($cW * 0.13);
    $lx2 = $pad + (int) ($cW * 0.85);
    foreach ([0.25, 0.47, 0.69] as $fy) {
        $ly = $frontY + (int) ($cH * $fy);
        $draw->rectangle($lx1, $ly, $lx2, $ly + $lh);
    }

    $img->drawImage($draw);
    $img->writeImage($file);
    $img->destroy();
    echo "  ✓ {$file}\n";
}

// ---------------------------------------------------------------------------
// Generate
// ---------------------------------------------------------------------------
echo "Generating...\n";
gen_banner(772,  250, "{$OUT_DIR}/banner-772x250.png",  $FONT_BOLD, $FONT_REGULAR);
gen_banner(1544, 500, "{$OUT_DIR}/banner-1544x500.png", $FONT_BOLD, $FONT_REGULAR);
gen_icon(128, "{$OUT_DIR}/icon-128x128.png");
gen_icon(256, "{$OUT_DIR}/icon-256x256.png");
echo "Done.\n";
