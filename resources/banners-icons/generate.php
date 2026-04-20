<?php
declare(strict_types=1);

$FONT_BOLD    = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
$FONT_REGULAR = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';
$OUT_DIR      = dirname(__DIR__, 2) . '/.wordpress-org';

@mkdir($OUT_DIR, 0755, true);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function tw(Imagick $img, string $font, float $size, string $text): int {
    $d = new ImagickDraw();
    $d->setFont($font);
    $d->setFontSize($size);
    return (int) $img->queryFontMetrics($d, $text)['textWidth'];
}

function fit_font(Imagick $probe, string $font, float $size, string $text, int $maxW, float $step = 1.0): float {
    while ($size > 8 && tw($probe, $font, $size, $text) > $maxW) {
        $size -= $step;
    }
    return $size;
}

// ---------------------------------------------------------------------------
// Banner generator
// ---------------------------------------------------------------------------
function gen_banner(int $W, int $H, string $file, string $fontBold, string $fontRegular): void {

    // ── Layout constants (all proportional) ─────────────────────────────────
    $panelW   = (int) ($W * 0.30);   // left icon panel
    $sepW     = 4;                    // blue separator bar
    $textX    = $panelW + $sepW + (int) ($W * 0.034);
    $rightPad = (int) ($W * 0.04);
    $maxTextW = $W - $textX - $rightPad;

    // ── Font sizes ───────────────────────────────────────────────────────────
    $probe = new Imagick();
    $probe->newImage(1, 1, new ImagickPixel('white'));

    $titleSize   = fit_font($probe, $fontBold,    (float)($H * 0.165), 'Swift Menu Duplicator', $maxTextW, 1.0);
    $taglineSize = (float)($H * 0.076);
    $probe->destroy();

    // ── Canvas: deep navy gradient ───────────────────────────────────────────
    $img = new Imagick();
    $img->newPseudoImage($W, $H, 'gradient:#0f172a-#1e293b');
    $img->setImageFormat('png');

    // ── Dot-grid texture on right panel ─────────────────────────────────────
    $dot = new ImagickDraw();
    $dot->setFillColor('#ffffff');
    $dot->setFillOpacity(0.035);
    $spacing = (int)($H * 0.15);
    $dotR    = max(1, (int)($H * 0.012));
    for ($x = $panelW + $sepW + $spacing; $x < $W; $x += $spacing) {
        for ($y = $spacing / 2; $y < $H; $y += $spacing) {
            $dot->circle($x, $y, $x + $dotR, $y);
        }
    }
    $img->drawImage($dot);

    // ── Left panel: darker background + radial glow ─────────────────────────
    $panel = new ImagickDraw();
    $panel->setFillColor('#080f1e');
    $panel->setStrokeWidth(0);
    $panel->rectangle(0, 0, $panelW, $H);
    $img->drawImage($panel);

    // radial glow via stacked translucent circles (no composite artifacts)
    $glow = new ImagickDraw();
    $gcx = (int)($panelW / 2);
    $gcy = (int)($H / 2);
    $glowSteps = [0.55 => 0.07, 0.38 => 0.09, 0.22 => 0.07, 0.10 => 0.04];
    foreach ($glowSteps as $frac => $opacity) {
        $gr = (int)($H * $frac);
        $glow->setFillColor('#3b82f6');
        $glow->setFillOpacity($opacity);
        $glow->circle($gcx, $gcy, $gcx + $gr, $gcy);
    }
    $glow->setFillOpacity(1.0);
    $img->drawImage($glow);

    // ── Accent separator ────────────────────────────────────────────────────
    $sep = new ImagickDraw();
    // gradient-ish: bright in middle, dim at edges
    $sep->setFillColor('#3b82f6');
    $sep->rectangle($panelW, 0, $panelW + $sepW, $H);
    $img->drawImage($sep);

    // ── Icon: two layered menu cards ─────────────────────────────────────────
    $cW  = (int)($panelW * 0.62);
    $cH  = (int)($H * 0.50);
    $cR  = (int)($H * 0.055);
    $cX  = (int)(($panelW - $cW) / 2);
    $cY  = (int)(($H - $cH) / 2);
    $off = (int)($H * 0.10);

    $cards = new ImagickDraw();

    // shadow under back card
    $cards->setFillColor('#000000');
    $cards->setFillOpacity(0.25);
    $cards->roundRectangle($cX + $off + 3, $cY + $off + 4, $cX + $cW + $off + 3, $cY + $cH + $off + 4, $cR, $cR);
    $cards->setFillOpacity(1.0);

    // back card
    $cards->setFillColor('#1e3a6e');
    $cards->roundRectangle($cX + $off, $cY + $off, $cX + $cW + $off, $cY + $cH + $off, $cR, $cR);

    // front card — blue gradient via two-tone approximation
    $cards->setFillColor('#2563eb');
    $cards->roundRectangle($cX, $cY, $cX + $cW, $cY + $cH, $cR, $cR);

    // highlight strip on front card (top edge shimmer)
    $cards->setFillColor('#ffffff');
    $cards->setFillOpacity(0.12);
    $cards->roundRectangle($cX, $cY, $cX + $cW, $cY + (int)($cH * 0.25), $cR, $cR);
    $cards->setFillOpacity(1.0);

    // three menu lines on front card
    $cards->setFillColor('#ffffff');
    $lh  = max(2, (int)($H * 0.032));
    $lx1 = $cX + (int)($cW * 0.14);
    $lx2 = $cX + (int)($cW * 0.84);
    foreach ([0.26, 0.48, 0.70] as $fy) {
        $ly = $cY + (int)($cH * $fy);
        $cards->roundRectangle($lx1, $ly, $lx2, $ly + $lh, (int)($lh / 2), (int)($lh / 2));
    }

    // small copy arrow badge (bottom-right of front card)
    $bx = $cX + $cW - (int)($cW * 0.20);
    $by = $cY + $cH - (int)($cH * 0.22);
    $br = (int)($cH * 0.11);
    $cards->setFillColor('#f59e0b');   // amber accent
    $cards->circle($bx, $by, $bx + $br, $by);

    $img->drawImage($cards);

    // arrow symbol in the badge (drawn as annotated text "⊕"-like via lines)
    $arr = new ImagickDraw();
    $arr->setStrokeColor('#ffffff');
    $arr->setStrokeWidth(max(1, (int)($H * 0.012)));
    $arr->setStrokeAntialias(true);
    $arr->setFillColor('none');
    // horizontal bar
    $arr->line($bx - (int)($br * 0.5), $by, $bx + (int)($br * 0.5), $by);
    // vertical bar
    $arr->line($bx, $by - (int)($br * 0.5), $bx, $by + (int)($br * 0.5));
    $img->drawImage($arr);

    // ── Title ────────────────────────────────────────────────────────────────
    $titleY = (int)($H * 0.50);

    $dt = new ImagickDraw();
    $dt->setFont($fontBold);
    $dt->setFontSize($titleSize);
    $dt->setFillColor('#f8fafc');
    $dt->setTextAntialias(true);
    $img->annotateImage($dt, $textX, $titleY, 0, 'Swift Menu Duplicator');

    // underline accent
    $titleW = tw($img, $fontBold, $titleSize, 'Swift Menu Duplicator');
    $ul = new ImagickDraw();
    $ul->setFillColor('#3b82f6');
    $ulH = max(2, (int)($H * 0.018));
    $ul->roundRectangle(
        $textX,
        $titleY + (int)($titleSize * 0.14),
        $textX + $titleW,
        $titleY + (int)($titleSize * 0.14) + $ulH,
        (int)($ulH / 2), (int)($ulH / 2)
    );
    $img->drawImage($ul);

    // ── Feature pills ────────────────────────────────────────────────────────
    $pills    = ['Duplicate', 'Snapshot', 'Export/Import', 'WP-CLI', 'REST API'];
    $pillH    = max(14, (int)($H * 0.105));
    $pillR    = (int)($pillH / 2);
    $pillPad  = (int)($pillH * 0.45);
    $pillGap  = (int)($H * 0.016);
    $pillY    = (int)($H * 0.72);
    $fontSize = (float)($H * 0.060);

    $probe2  = new Imagick();
    $probe2->newImage(1, 1, new ImagickPixel('white'));

    $px = $textX;
    foreach ($pills as $i => $label) {
        $lw = tw($probe2, $fontRegular, $fontSize, $label);
        $pw = $lw + $pillPad * 2;

        // stop if pill would overflow
        if ($px + $pw > $W - $rightPad) break;

        $pill = new ImagickDraw();
        if ($i === 0) {
            // first pill: filled blue
            $pill->setFillColor('#2563eb');
        } else {
            // rest: outlined
            $pill->setFillColor('none');
            $pill->setStrokeColor('#334155');
            $pill->setStrokeWidth(max(1, (int)($H * 0.008)));
        }
        $pill->roundRectangle($px, $pillY - $pillH + 2, $px + $pw, $pillY + 2, $pillR, $pillR);
        $img->drawImage($pill);

        $dp = new ImagickDraw();
        $dp->setFont($fontRegular);
        $dp->setFontSize($fontSize);
        $dp->setFillColor($i === 0 ? '#ffffff' : '#94a3b8');
        $dp->setTextAntialias(true);
        $img->annotateImage($dp, $px + $pillPad, $pillY, 0, $label);

        $px += $pw + $pillGap;
    }
    $probe2->destroy();

    $img->writeImage($file);
    $img->destroy();

    echo "  ✓ {$file}\n";
}

// ---------------------------------------------------------------------------
// Icon generator
// ---------------------------------------------------------------------------
function gen_icon(int $size, string $file, string $fontBold = ''): void {
    $img = new Imagick();
    $img->newImage($size, $size, new ImagickPixel('transparent'));
    $img->setImageFormat('png');

    $draw = new ImagickDraw();

    // rounded square bg
    $bgR = (int)($size * 0.22);
    $draw->setFillColor('#0f172a');
    $draw->roundRectangle(0, 0, $size - 1, $size - 1, $bgR, $bgR);

    $pad  = (int)($size * 0.14);
    $cW   = (int)($size * 0.64);
    $cH   = (int)($size * 0.56);
    $cR   = (int)($size * 0.09);
    $off  = (int)($size * 0.15);

    // back card shadow
    $draw->setFillColor('#000000');
    $draw->setFillOpacity(0.20);
    $draw->roundRectangle($pad + $off + 2, $pad + $off + 3, $pad + $cW + $off + 2, $pad + $cH + $off + 3, $cR, $cR);
    $draw->setFillOpacity(1.0);

    // back card
    $draw->setFillColor('#1e3a6e');
    $draw->roundRectangle($pad + $off, $pad + $off, $pad + $cW + $off, $pad + $cH + $off, $cR, $cR);

    // front card
    $frontY = $pad + (int)($size * 0.10);
    $draw->setFillColor('#2563eb');
    $draw->roundRectangle($pad, $frontY, $pad + $cW, $frontY + $cH, $cR, $cR);

    // highlight
    $draw->setFillColor('#ffffff');
    $draw->setFillOpacity(0.12);
    $draw->roundRectangle($pad, $frontY, $pad + $cW, $frontY + (int)($cH * 0.25), $cR, $cR);
    $draw->setFillOpacity(1.0);

    // menu lines
    $draw->setFillColor('#ffffff');
    $lh  = max(2, (int)($size * 0.058));
    $lx1 = $pad + (int)($cW * 0.14);
    $lx2 = $pad + (int)($cW * 0.84);
    foreach ([0.26, 0.48, 0.70] as $fy) {
        $ly = $frontY + (int)($cH * $fy);
        $draw->roundRectangle($lx1, $ly, $lx2, $ly + $lh, (int)($lh / 2), (int)($lh / 2));
    }

    // amber badge
    $bx = $pad + $cW - (int)($cW * 0.18);
    $by = $frontY + $cH - (int)($cH * 0.20);
    $br = (int)($cH * 0.12);
    $draw->setFillColor('#f59e0b');
    $draw->circle($bx, $by, $bx + $br, $by);

    $img->drawImage($draw);

    // plus sign in badge
    $arr = new ImagickDraw();
    $arr->setStrokeColor('#ffffff');
    $arr->setStrokeWidth(max(1, (int)($size * 0.025)));
    $arr->setStrokeAntialias(true);
    $arr->setFillColor('none');
    $arr->line($bx - (int)($br * 0.48), $by, $bx + (int)($br * 0.48), $by);
    $arr->line($bx, $by - (int)($br * 0.48), $bx, $by + (int)($br * 0.48));
    $img->drawImage($arr);

    $img->writeImage($file);
    $img->destroy();
    echo "  ✓ {$file}\n";
}

// ---------------------------------------------------------------------------
// Generate
// ---------------------------------------------------------------------------
echo "Generating banners & icons...\n";
gen_banner(772,  250, "{$OUT_DIR}/banner-772x250.png",  $FONT_BOLD, $FONT_REGULAR);
gen_banner(1544, 500, "{$OUT_DIR}/banner-1544x500.png", $FONT_BOLD, $FONT_REGULAR);
gen_icon(128, "{$OUT_DIR}/icon-128x128.png");
gen_icon(256, "{$OUT_DIR}/icon-256x256.png");
echo "Done.\n";
