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

    // ── Layout ───────────────────────────────────────────────────────────────
    $panelW   = (int)($W * 0.30);
    $sepW     = max(3, (int)($W * 0.004));
    $textX    = $panelW + $sepW + (int)($W * 0.034);
    $rightPad = (int)($W * 0.04);
    $maxTextW = $W - $textX - $rightPad;

    // ── Probe canvas for font metrics ────────────────────────────────────────
    $probe = new Imagick();
    $probe->newImage(1, 1, new ImagickPixel('white'));

    // Font sizes proportional to height, capped so text fits
    $titleSize = fit_font($probe, $fontBold, (float)($H * 0.170), 'Swift Menu Duplicator', $maxTextW, 1.0);

    // Pill metrics — adaptive: shrink font until all 5 pills fit on one row
    $labels       = ['Duplicate', 'Snapshot', 'Export/Import', 'WP-CLI', 'REST API'];
    $pillFontSize = round($titleSize * 0.40, 1);   // start at 40% of title

    $pillH = $pillPad = $pillGap = $pillWidths = 0;
    while ($pillFontSize >= 8) {
        $pillH   = (int)($pillFontSize * 2.20);
        $pillPad = (int)($pillFontSize * 0.68);
        $pillGap = (int)($pillFontSize * 0.44);

        $pillWidths = [];
        $totalW = 0;
        foreach ($labels as $i => $lbl) {
            $pw = tw($probe, $fontRegular, $pillFontSize, $lbl) + $pillPad * 2;
            $pillWidths[] = $pw;
            $totalW += $pw + ($i < count($labels) - 1 ? $pillGap : 0);
        }
        if ($textX + $totalW <= $W - $rightPad) break;
        $pillFontSize -= 0.5;
    }

    $pillR = (int)($pillH / 2);
    $probe->destroy();

    // ── Vertical centring of content block ───────────────────────────────────
    // block = title + underline gap + spacing + pills row
    $ulGap      = (int)($titleSize * 0.18);   // space below title baseline to underline top
    $ulH        = max(2, (int)($titleSize * 0.10));
    $blockGap   = (int)($titleSize * 0.55);   // gap between underline bottom and pill top

    // Total block height: from title cap-height top to pill bottom
    // Imagick annotate y = baseline; cap-height ≈ 72% of font size above baseline
    $titleCapH  = (int)($titleSize * 0.72);   // approx distance from baseline to top of caps
    $blockH     = $titleCapH + $ulGap + $ulH + $blockGap + $pillH;

    // Centre the block vertically
    $blockTop   = (int)(($H - $blockH) / 2);
    $titleY     = $blockTop + $titleCapH;           // baseline of title
    $ulTop      = $titleY + $ulGap;
    $pillTop    = $ulTop + $ulH + $blockGap;        // top edge of pill row
    $pillBaseline = $pillTop + (int)($pillH * 0.68); // baseline: text centred vertically

    // ── Canvas ───────────────────────────────────────────────────────────────
    $img = new Imagick();
    $img->newPseudoImage($W, $H, 'gradient:#0f172a-#1e293b');
    $img->setImageFormat('png');

    // ── Dot-grid on right panel — fixed pixel spacing, consistent across sizes
    $dotSpacing = 28;   // fixed px — same visual density at both resolutions
    $dotR       = max(1, (int)round($dotSpacing * 0.07));  // ~7% of spacing
    $dot = new ImagickDraw();
    $dot->setFillColor('#ffffff');
    $dot->setFillOpacity(0.045);
    $startX = $panelW + $sepW + $dotSpacing;
    for ($x = $startX; $x < $W; $x += $dotSpacing) {
        for ($y = (int)($dotSpacing * 0.5); $y < $H; $y += $dotSpacing) {
            $dot->circle($x, $y, $x + $dotR, $y);
        }
    }
    $img->drawImage($dot);

    // ── Left panel ───────────────────────────────────────────────────────────
    $panel = new ImagickDraw();
    $panel->setFillColor('#080f1e');
    $panel->setStrokeWidth(0);
    $panel->rectangle(0, 0, $panelW, $H);
    $img->drawImage($panel);

    // Radial glow — radius capped to panel width, not canvas height
    $glow = new ImagickDraw();
    $gcx  = (int)($panelW / 2);
    $gcy  = (int)($H / 2);
    $maxR = (int)($panelW * 0.46);   // never exceed panel bounds
    foreach ([1.00 => 0.035, 0.72 => 0.045, 0.48 => 0.040, 0.28 => 0.030, 0.12 => 0.015] as $frac => $opacity) {
        $gr = (int)($maxR * $frac);
        $glow->setFillColor('#3b82f6');
        $glow->setFillOpacity($opacity);
        $glow->circle($gcx, $gcy, $gcx + $gr, $gcy);
    }
    $glow->setFillOpacity(1.0);
    $img->drawImage($glow);

    // ── Accent separator ─────────────────────────────────────────────────────
    $sep = new ImagickDraw();
    $sep->setFillColor('#3b82f6');
    $sep->rectangle($panelW, 0, $panelW + $sepW, $H);
    $img->drawImage($sep);

    // ── Icon: two layered menu cards ─────────────────────────────────────────
    $cW  = (int)($panelW * 0.62);
    $cH  = (int)($H * 0.50);
    $cR  = (int)(min($cW, $cH) * 0.11);
    $cX  = (int)(($panelW - $cW) / 2);
    $cY  = (int)(($H - $cH) / 2);
    $off = (int)($cH * 0.18);

    $cards = new ImagickDraw();

    // shadow
    $cards->setFillColor('#000000');
    $cards->setFillOpacity(0.22);
    $cards->roundRectangle($cX + $off + 3, $cY + $off + 4, $cX + $cW + $off + 3, $cY + $cH + $off + 4, $cR, $cR);
    $cards->setFillOpacity(1.0);

    // back card
    $cards->setFillColor('#1e3a6e');
    $cards->roundRectangle($cX + $off, $cY + $off, $cX + $cW + $off, $cY + $cH + $off, $cR, $cR);

    // front card
    $cards->setFillColor('#2563eb');
    $cards->roundRectangle($cX, $cY, $cX + $cW, $cY + $cH, $cR, $cR);

    // top shimmer
    $cards->setFillColor('#ffffff');
    $cards->setFillOpacity(0.10);
    $cards->roundRectangle($cX, $cY, $cX + $cW, $cY + (int)($cH * 0.28), $cR, $cR);
    $cards->setFillOpacity(1.0);

    // menu lines — rounded rects
    $cards->setFillColor('#ffffff');
    $lh  = max(2, (int)($cH * 0.065));
    $lR  = (int)($lh / 2);
    $lx1 = $cX + (int)($cW * 0.14);
    $lx2 = $cX + (int)($cW * 0.84);
    foreach ([0.26, 0.48, 0.70] as $fy) {
        $ly = $cY + (int)($cH * $fy);
        $cards->roundRectangle($lx1, $ly, $lx2, $ly + $lh, $lR, $lR);
    }

    // amber + badge
    $bx = $cX + $cW - (int)($cW * 0.19);
    $by = $cY + $cH - (int)($cH * 0.21);
    $br = (int)($cH * 0.12);
    $cards->setFillColor('#f59e0b');
    $cards->circle($bx, $by, $bx + $br, $by);

    $img->drawImage($cards);

    // plus in badge
    $arr = new ImagickDraw();
    $arr->setStrokeColor('#ffffff');
    $arr->setStrokeWidth(max(1.0, $br * 0.22));
    $arr->setStrokeAntialias(true);
    $arr->setFillColor('none');
    $hs = (int)($br * 0.50);
    $arr->line($bx - $hs, $by, $bx + $hs, $by);
    $arr->line($bx, $by - $hs, $bx, $by + $hs);
    $img->drawImage($arr);

    // ── Title ────────────────────────────────────────────────────────────────
    $dt = new ImagickDraw();
    $dt->setFont($fontBold);
    $dt->setFontSize($titleSize);
    $dt->setFillColor('#f8fafc');
    $dt->setTextAntialias(true);
    $img->annotateImage($dt, $textX, $titleY, 0, 'Swift Menu Duplicator');

    // underline
    $titleW = tw($img, $fontBold, $titleSize, 'Swift Menu Duplicator');
    $ul = new ImagickDraw();
    $ul->setFillColor('#3b82f6');
    $ul->roundRectangle($textX, $ulTop, $textX + $titleW, $ulTop + $ulH, (int)($ulH / 2), (int)($ulH / 2));
    $img->drawImage($ul);

    // ── Pills ────────────────────────────────────────────────────────────────
    // Scale-aware stroke: 1 screen-pixel = W/772 physical pixels (1.0 std, 2.0 retina)
    $strokePx = $W / 772.0;

    $px = $textX;
    foreach ($labels as $i => $label) {
        $pw = $pillWidths[$i];

        // Background layer (subtle dark fill on outlined pills for readability)
        $bg = new ImagickDraw();
        $bg->setStrokeWidth(0);
        if ($i === 0) {
            $bg->setFillColor('#2563eb');
        } else {
            $bg->setFillColor('#1e2d45');   // subtle tinted dark fill
            $bg->setFillOpacity(0.55);
        }
        $bg->roundRectangle($px, $pillTop, $px + $pw, $pillTop + $pillH, $pillR, $pillR);
        $img->drawImage($bg);

        // Border layer (outlined pills only)
        if ($i !== 0) {
            $border = new ImagickDraw();
            $border->setFillColor('none');
            $border->setStrokeColor('#4a6080');   // medium-blue slate, softer than #334155
            $border->setStrokeWidth($strokePx);
            $border->setStrokeAntialias(true);
            $border->roundRectangle($px, $pillTop, $px + $pw, $pillTop + $pillH, $pillR, $pillR);
            $img->drawImage($border);
        }

        // Label
        $dp = new ImagickDraw();
        $dp->setFont($fontRegular);
        $dp->setFontSize($pillFontSize);
        $dp->setFillColor($i === 0 ? '#ffffff' : '#94a3b8');
        $dp->setTextAntialias(true);
        $img->annotateImage($dp, $px + $pillPad, $pillBaseline, 0, $label);

        $px += $pw + $pillGap;
    }

    $img->writeImage($file);
    $img->destroy();

    echo "  ✓ {$file}\n";
}

// ---------------------------------------------------------------------------
// Icon generator
// ---------------------------------------------------------------------------
function gen_icon(int $size, string $file): void {
    $img = new Imagick();
    $img->newImage($size, $size, new ImagickPixel('transparent'));
    $img->setImageFormat('png');

    $draw = new ImagickDraw();

    $bgR = (int)($size * 0.22);
    $draw->setFillColor('#0f172a');
    $draw->roundRectangle(0, 0, $size - 1, $size - 1, $bgR, $bgR);

    $pad  = (int)($size * 0.14);
    $cW   = (int)($size * 0.64);
    $cH   = (int)($size * 0.56);
    $cR   = (int)(min($cW, $cH) * 0.11);
    $off  = (int)($cH * 0.18);

    // shadow
    $draw->setFillColor('#000000');
    $draw->setFillOpacity(0.22);
    $draw->roundRectangle($pad + $off + 2, $pad + $off + 3, $pad + $cW + $off + 2, $pad + $cH + $off + 3, $cR, $cR);
    $draw->setFillOpacity(1.0);

    // back card
    $draw->setFillColor('#1e3a6e');
    $draw->roundRectangle($pad + $off, $pad + $off, $pad + $cW + $off, $pad + $cH + $off, $cR, $cR);

    // front card
    $frontY = $pad + (int)($size * 0.10);
    $draw->setFillColor('#2563eb');
    $draw->roundRectangle($pad, $frontY, $pad + $cW, $frontY + $cH, $cR, $cR);

    // shimmer
    $draw->setFillColor('#ffffff');
    $draw->setFillOpacity(0.10);
    $draw->roundRectangle($pad, $frontY, $pad + $cW, $frontY + (int)($cH * 0.28), $cR, $cR);
    $draw->setFillOpacity(1.0);

    // menu lines
    $draw->setFillColor('#ffffff');
    $lh  = max(2, (int)($cH * 0.065));
    $lR  = (int)($lh / 2);
    $lx1 = $pad + (int)($cW * 0.14);
    $lx2 = $pad + (int)($cW * 0.84);
    foreach ([0.26, 0.48, 0.70] as $fy) {
        $ly = $frontY + (int)($cH * $fy);
        $draw->roundRectangle($lx1, $ly, $lx2, $ly + $lh, $lR, $lR);
    }

    // amber badge
    $bx = $pad + $cW - (int)($cW * 0.19);
    $by = $frontY + $cH - (int)($cH * 0.21);
    $br = (int)($cH * 0.12);
    $draw->setFillColor('#f59e0b');
    $draw->circle($bx, $by, $bx + $br, $by);

    $img->drawImage($draw);

    // plus
    $arr = new ImagickDraw();
    $arr->setStrokeColor('#ffffff');
    $arr->setStrokeWidth(max(1.0, $br * 0.22));
    $arr->setStrokeAntialias(true);
    $arr->setFillColor('none');
    $hs = (int)($br * 0.50);
    $arr->line($bx - $hs, $by, $bx + $hs, $by);
    $arr->line($bx, $by - $hs, $bx, $by + $hs);
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
