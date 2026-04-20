<?php
declare(strict_types=1);

/**
 * Generates WordPress.org plugin screenshots as admin UI mockups.
 *
 * Requirements: PHP Imagick extension + Liberation Sans fonts.
 * Usage:  php resources/banners-icons/generate-screenshots.php
 *         composer run gen-screenshots
 *
 * Output: .wordpress-org/screenshot-{1..5}.png  (1280×800 each)
 */

$FONT_BOLD    = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
$FONT_REGULAR = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';
$FONT_MONO    = '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf';
$OUT_DIR      = dirname(__DIR__, 2) . '/.wordpress-org';

// ---------------------------------------------------------------------------
// WP admin colours
// ---------------------------------------------------------------------------
const C_BODY       = '#f0f0f1';
const C_SIDEBAR    = '#1d2327';
const C_SIDEBAR_HI = '#2c3338';
const C_TOPBAR     = '#1d2327';
const C_WHITE      = '#ffffff';
const C_BLUE       = '#2271b1';
const C_BLUE_DARK  = '#135e96';
const C_BLUE_LIGHT = '#d0e5f4';
const C_GREEN      = '#00a32a';
const C_BORDER     = '#c3c4c7';
const C_TEXT       = '#1d2327';
const C_MUTED      = '#646970';
const C_ACCENT     = '#3582c4';
const C_ROW_ALT    = '#f6f7f7';
const C_SWMD_BLUE  = '#3b82f6';

const W = 1280;
const H = 800;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function make_canvas(): Imagick {
    $img = new Imagick();
    $img->newImage(W, H, new ImagickPixel(C_BODY));
    $img->setImageFormat('png');
    return $img;
}

function draw(Imagick $img, callable $fn): void {
    $d = new ImagickDraw();
    $fn($d);
    $img->drawImage($d);
}

function text(Imagick $img, string $font, float $size, string $color, int $x, int $y, string $str): void {
    $d = new ImagickDraw();
    $d->setFont($font);
    $d->setFontSize($size);
    $d->setFillColor($color);
    $d->setTextAntialias(true);
    $img->annotateImage($d, $x, $y, 0, $str);
}

function tw(Imagick $img, string $font, float $size, string $str): int {
    $d = new ImagickDraw();
    $d->setFont($font);
    $d->setFontSize($size);
    return (int) $img->queryFontMetrics($d, $str)['textWidth'];
}

/** Draw the common WP admin chrome (topbar + sidebar + page title bar). */
function draw_chrome(
    Imagick $img,
    string $fontBold,
    string $fontRegular,
    string $pageTitle,
    string $activeMenu = 'Appearance'
): void {

    // Top bar
    draw($img, function (ImagickDraw $d) {
        $d->setFillColor(C_TOPBAR);
        $d->rectangle(0, 0, W, 32);
    });
    text($img, $fontBold, 13, '#a7aaad', 12, 21, 'WordPress');

    // Sidebar
    draw($img, function (ImagickDraw $d) {
        $d->setFillColor(C_SIDEBAR);
        $d->rectangle(0, 32, 160, H);
    });

    $menuItems = ['Dashboard', 'Posts', 'Media', 'Pages', 'Appearance', 'Plugins', 'Users', 'Tools', 'Settings'];
    $sy = 70;
    foreach ($menuItems as $item) {
        $active = $item === $activeMenu;
        if ($active) {
            draw($img, function (ImagickDraw $d) use ($sy) {
                $d->setFillColor(C_SIDEBAR_HI);
                $d->rectangle(0, $sy - 14, 160, $sy + 10);
            });
        }
        text($img, $fontRegular, 13, $active ? C_WHITE : '#a7aaad', 20, $sy, $item);
        $sy += 34;
    }

    // Content area background
    draw($img, function (ImagickDraw $d) {
        $d->setFillColor(C_BODY);
        $d->rectangle(160, 32, W, H);
    });

    // Page heading bar
    draw($img, function (ImagickDraw $d) {
        $d->setFillColor(C_WHITE);
        $d->rectangle(160, 32, W, 80);
        $d->setFillColor(C_BORDER);
        $d->rectangle(160, 79, W, 80);
    });
    text($img, $fontBold, 22, C_TEXT, 180, 66, $pageTitle);
}

/** Draw a rounded button. */
function button(Imagick $img, string $font, int $x, int $y, int $bw, int $bh, string $bg, string $label, string $labelColor = C_WHITE): void {
    draw($img, function (ImagickDraw $d) use ($x, $y, $bw, $bh, $bg) {
        $d->setFillColor($bg);
        $d->roundRectangle($x, $y, $x + $bw, $y + $bh, 3, 3);
    });
    $lw = tw($img, $font, 13, $label);
    text($img, $font, 13, $labelColor, $x + (int)(($bw - $lw) / 2), $y + (int)($bh * 0.66), $label);
}

/** Draw a simple WP-style notice. */
function notice(Imagick $img, string $font, int $x, int $y, int $w, string $type, string $msg): void {
    $color = $type === 'success' ? C_GREEN : C_BLUE;
    draw($img, function (ImagickDraw $d) use ($x, $y, $w, $color) {
        $d->setFillColor(C_WHITE);
        $d->rectangle($x, $y, $x + $w, $y + 38);
        $d->setFillColor($color);
        $d->rectangle($x, $y, $x + 4, $y + 38);
    });
    text($img, $font, 13, C_TEXT, $x + 16, $y + 24, $msg);
}

/** Draw a simple WP table header row. */
function table_header(Imagick $img, string $font, int $x, int $y, int $w, array $cols): void {
    $totalW = array_sum(array_column($cols, 1));
    draw($img, function (ImagickDraw $d) use ($x, $y, $w) {
        $d->setFillColor('#f0f0f1');
        $d->rectangle($x, $y, $x + $w, $y + 34);
        $d->setFillColor(C_BORDER);
        $d->rectangle($x, $y + 33, $x + $w, $y + 34);
    });
    $cx = $x + 12;
    foreach ($cols as [$label, $colW]) {
        text($img, $font, 12, C_TEXT, $cx, $y + 22, strtoupper($label));
        $cx += (int)($w * $colW / $totalW);
    }
}

/** Draw a table data row. */
function table_row(Imagick $img, string $font, int $x, int $y, int $w, array $cols, array $values, bool $alt = false): void {
    $totalW = array_sum(array_column($cols, 1));
    draw($img, function (ImagickDraw $d) use ($x, $y, $w, $alt) {
        $d->setFillColor($alt ? C_ROW_ALT : C_WHITE);
        $d->rectangle($x, $y, $x + $w, $y + 36);
        $d->setFillColor(C_BORDER);
        $d->rectangle($x, $y + 35, $x + $w, $y + 36);
    });
    $cx = $x + 12;
    foreach ($cols as $i => [$label, $colW]) {
        $val   = $values[$i] ?? '';
        $color = $i === 0 ? C_BLUE : C_TEXT;
        text($img, $i === 0 ? $font : $GLOBALS['FONT_REGULAR'], 13, $color, $cx, $y + 23, $val);
        $cx += (int)($w * $colW / $totalW);
    }
}

// ===========================================================================
// Screenshot 1 — Duplicate Menu button in the menu editor footer
// ===========================================================================
function screenshot_1(string $out, string $fontBold, string $fontRegular): void {
    $img = make_canvas();
    draw_chrome($img, $fontBold, $fontRegular, 'Menus', 'Appearance');

    $lx = 180; $ly = 100; $cw = W - 180 - 20;

    // Menu name + save bar (top)
    draw($img, function (ImagickDraw $d) use ($lx, $ly, $cw) {
        $d->setFillColor(C_WHITE);
        $d->rectangle($lx, $ly, $lx + $cw, $ly + 56);
        $d->setFillColor(C_BORDER);
        $d->rectangle($lx, $ly + 55, $lx + $cw, $ly + 56);
    });
    text($img, $fontBold, 14, C_TEXT, $lx + 12, $ly + 22, 'Menu name');
    // input
    draw($img, function (ImagickDraw $d) use ($lx, $ly) {
        $d->setFillColor(C_WHITE);
        $d->setStrokeColor(C_BORDER);
        $d->setStrokeWidth(1);
        $d->rectangle($lx + 110, $ly + 8, $lx + 340, $ly + 34);
    });
    text($img, $fontRegular, 13, C_TEXT, $lx + 118, $ly + 27, 'Main Navigation');

    // Menu structure area (middle)
    draw($img, function (ImagickDraw $d) use ($lx, $ly, $cw) {
        $d->setFillColor(C_WHITE);
        $d->rectangle($lx, $ly + 56, $lx + $cw, $ly + 500);
    });
    text($img, $fontBold, 14, C_TEXT, $lx + 12, $ly + 88, 'Menu structure');
    text($img, $fontRegular, 12, C_MUTED, $lx + 12, $ly + 110, 'Drag items into the order you prefer. Click the arrow on the right of the item to reveal additional configuration options.');

    // Sample menu items
    $items = [
        ['Home',     0],
        ['About',    0],
        ['Services', 0],
        ['Blog',    40],
        ['Contact',  0],
    ];
    $iy = $ly + 136;
    foreach ($items as [$label, $indent]) {
        draw($img, function (ImagickDraw $d) use ($lx, $iy, $cw, $indent) {
            $d->setFillColor(C_WHITE);
            $d->setStrokeColor(C_BORDER);
            $d->setStrokeWidth(1);
            $d->rectangle($lx + 12 + $indent, $iy, $lx + $cw - 12, $iy + 40);
        });
        text($img, $fontBold, 13, C_TEXT, $lx + 24 + $indent, $iy + 25, $label);
        text($img, $fontRegular, 11, C_MUTED, $lx + $cw - 80, $iy + 25, 'Custom Link ▾');
        $iy += 48;
    }

    // Footer bar — the star of this screenshot
    $footerY = $ly + 505;
    draw($img, function (ImagickDraw $d) use ($lx, $footerY, $cw) {
        $d->setFillColor('#f6f7f7');
        $d->setStrokeColor(C_BORDER);
        $d->setStrokeWidth(1);
        $d->rectangle($lx, $footerY, $lx + $cw, $footerY + 56);
    });
    text($img, $fontRegular, 13, C_TEXT, $lx + 14, $footerY + 22, 'Select a menu structure to edit:');

    // Save Menu button
    button($img, $fontBold, $lx + $cw - 130, $footerY + 10, 110, 34, C_BLUE, 'Save Menu');

    // Duplicate Menu button — highlighted with glow
    $dupX = $lx + $cw - 260;
    draw($img, function (ImagickDraw $d) use ($dupX, $footerY) {
        $d->setFillColor('#e8f1fb');
        $d->roundRectangle($dupX - 4, $footerY + 6, $dupX + 120, $footerY + 48, 4, 4);
    });
    button($img, $fontBold, $dupX, $footerY + 10, 110, 34, C_SWMD_BLUE, 'Duplicate Menu');

    // Callout arrow pointing to the button
    draw($img, function (ImagickDraw $d) use ($dupX, $footerY) {
        $d->setFillColor(C_SWMD_BLUE);
        $ax = $dupX + 55; $ay = $footerY + 68;
        $d->polygon([
            ['x' => $ax,      'y' => $ay],
            ['x' => $ax - 10, 'y' => $ay + 20],
            ['x' => $ax + 10, 'y' => $ay + 20],
        ]);
    });
    draw($img, function (ImagickDraw $d) use ($dupX, $footerY) {
        $d->setFillColor(C_SWMD_BLUE);
        $d->roundRectangle($dupX - 20, $footerY + 86, $dupX + 140, $footerY + 114, 4, 4);
    });
    text($img, $fontBold, 12, C_WHITE, $dupX - 6, $footerY + 105, 'One-click duplication!');

    // Export JSON button
    button($img, $fontRegular, $dupX - 130, $footerY + 10, 110, 34, '#f0f0f1', 'Export JSON', C_TEXT);

    $img->writeImage($out);
    $img->destroy();
    echo "  ✓ $out\n";
}

// ===========================================================================
// Screenshot 2 — Menu Manager page with bulk actions
// ===========================================================================
function screenshot_2(string $out, string $fontBold, string $fontRegular): void {
    $img = make_canvas();
    draw_chrome($img, $fontBold, $fontRegular, 'Menu Manager', 'Appearance');

    $lx = 180; $ly = 100; $cw = W - 180 - 20;

    // Tabs
    $tabs = ['All Menus', 'Import JSON', 'Copy to Site'];
    $tx = $lx;
    foreach ($tabs as $i => $tab) {
        $active = $i === 0;
        draw($img, function (ImagickDraw $d) use ($tx, $ly, $tab, $active, $fontRegular) {
            $d->setFillColor($active ? C_WHITE : C_BODY);
            $d->setStrokeColor(C_BORDER);
            $d->setStrokeWidth(1);
            $bw = strlen($tab) * 9 + 24;
            $d->rectangle($tx, $ly, $tx + $bw, $ly + 34);
        });
        $bw = strlen($tab) * 9 + 24;
        text($img, $active ? $fontBold : $fontRegular, 13, $active ? C_BLUE : C_TEXT, $tx + 12, $ly + 22, $tab);
        $tx += $bw + 2;
    }

    // Toolbar (bulk action + search)
    $toolY = $ly + 34;
    draw($img, function (ImagickDraw $d) use ($lx, $toolY, $cw) {
        $d->setFillColor(C_WHITE);
        $d->rectangle($lx, $toolY, $lx + $cw, $toolY + 44);
        $d->setFillColor(C_BORDER);
        $d->rectangle($lx, $toolY + 43, $lx + $cw, $toolY + 44);
    });

    // Bulk action dropdown
    draw($img, function (ImagickDraw $d) use ($lx, $toolY) {
        $d->setFillColor(C_WHITE);
        $d->setStrokeColor(C_BORDER);
        $d->setStrokeWidth(1);
        $d->rectangle($lx + 8, $toolY + 8, $lx + 160, $toolY + 34);
    });
    text($img, $fontRegular, 13, C_TEXT, $lx + 16, $toolY + 27, 'Bulk actions  ▾');
    button($img, $fontRegular, $lx + 168, $toolY + 8, 70, 26, '#f0f0f1', 'Apply', C_TEXT);

    // Search box
    draw($img, function (ImagickDraw $d) use ($lx, $toolY, $cw) {
        $d->setFillColor(C_WHITE);
        $d->setStrokeColor(C_BORDER);
        $d->setStrokeWidth(1);
        $d->rectangle($lx + $cw - 220, $toolY + 8, $lx + $cw - 80, $toolY + 34);
    });
    text($img, $fontRegular, 12, C_MUTED, $lx + $cw - 212, $toolY + 26, 'Search menus…');
    button($img, $fontRegular, $lx + $cw - 72, $toolY + 8, 60, 26, C_BLUE, 'Search');

    // Table
    $cols = [['', 4], ['Menu Name', 28], ['Items', 10], ['Locations', 22], ['Created', 18], ['Actions', 18]];
    $tableY = $toolY + 44;
    table_header($img, $fontBold, $lx, $tableY, $cw, $cols);

    $rows = [
        ['Main Navigation',    '8',  'Primary Menu',          '2 weeks ago'],
        ['Footer Links',       '5',  '—',                     '1 week ago'],
        ['Mobile Menu',        '6',  'Mobile Navigation',     '3 days ago'],
        ['Landing Page Nav',   '3',  '—',                     'Yesterday'],
        ['Holiday Special',    '12', 'Secondary Menu',        '5 days ago'],
        ['Sidebar Navigation', '4',  '—',                     '1 month ago'],
    ];

    $ry = $tableY + 34;
    foreach ($rows as $i => $row) {
        $alt = $i % 2 === 1;
        draw($img, function (ImagickDraw $d) use ($lx, $ry, $cw, $alt) {
            $d->setFillColor($alt ? C_ROW_ALT : C_WHITE);
            $d->rectangle($lx, $ry, $lx + $cw, $ry + 38);
            $d->setFillColor(C_BORDER);
            $d->rectangle($lx, $ry + 37, $lx + $cw, $ry + 38);
            // checkbox
            $d->setFillColor(C_WHITE);
            $d->setStrokeColor(C_BORDER);
            $d->setStrokeWidth(1);
            $d->rectangle($lx + 10, $ry + 11, $lx + 28, $ry + 27);
        });

        $totalW = array_sum(array_column($cols, 1));
        $cx = $lx + 10 + (int)($cw * 4 / $totalW);
        text($img, $fontBold, 13, C_BLUE, $cx + 4, $ry + 24, $row[0]);

        $cx += (int)($cw * 28 / $totalW);
        text($img, $fontRegular, 13, C_TEXT, $cx + 4, $ry + 24, $row[1]);
        $cx += (int)($cw * 10 / $totalW);
        text($img, $fontRegular, 12, C_MUTED, $cx + 4, $ry + 24, $row[2]);
        $cx += (int)($cw * 22 / $totalW);
        text($img, $fontRegular, 12, C_MUTED, $cx + 4, $ry + 24, $row[3]);
        $cx += (int)($cw * 18 / $totalW);

        // Action links
        text($img, $fontRegular, 12, C_BLUE, $cx + 4, $ry + 24, 'Edit');
        text($img, $fontRegular, 12, C_MUTED, $cx + 36, $ry + 24, '|');
        text($img, $fontRegular, 12, C_BLUE, $cx + 48, $ry + 24, 'Duplicate');
        text($img, $fontRegular, 12, C_MUTED, $cx + 112, $ry + 24, '|');
        text($img, $fontRegular, 12, C_BLUE, $cx + 120, $ry + 24, 'Export');

        $ry += 38;
    }

    $img->writeImage($out);
    $img->destroy();
    echo "  ✓ $out\n";
}

// ===========================================================================
// Screenshot 3 — Snapshot panel
// ===========================================================================
function screenshot_3(string $out, string $fontBold, string $fontRegular): void {
    $img = make_canvas();
    draw_chrome($img, $fontBold, $fontRegular, 'Menus', 'Appearance');

    $lx = 180; $cw = W - 180 - 20;

    // Menu editor — left column (narrow menu structure)
    $structW = (int)($cw * 0.62);
    $panelX  = $lx + $structW + 12;
    $panelW  = $cw - $structW - 20;

    // Structure pane
    draw($img, function (ImagickDraw $d) use ($lx, $structW) {
        $d->setFillColor(C_WHITE);
        $d->rectangle($lx, 100, $lx + $structW, H - 20);
    });
    text($img, $fontBold, 15, C_TEXT, $lx + 12, 128, 'Menu structure  —  Main Navigation');

    $iy = 150;
    foreach (['Home', 'About', 'Services', '⤷ Web Design', '⤷ SEO', 'Contact'] as $item) {
        $indent = str_starts_with($item, '⤷') ? 30 : 0;
        draw($img, function (ImagickDraw $d) use ($lx, $iy, $structW, $indent) {
            $d->setFillColor(C_WHITE);
            $d->setStrokeColor(C_BORDER);
            $d->setStrokeWidth(1);
            $d->rectangle($lx + 10 + $indent, $iy, $lx + $structW - 10, $iy + 36);
        });
        text($img, $fontBold, 13, C_TEXT, $lx + 20 + $indent, $iy + 23, $item);
        $iy += 44;
    }

    // Right column — snapshot panel
    draw($img, function (ImagickDraw $d) use ($panelX, $panelW) {
        $d->setFillColor(C_WHITE);
        $d->setStrokeColor(C_BORDER);
        $d->setStrokeWidth(1);
        $d->rectangle($panelX, 100, $panelX + $panelW, H - 20);
    });

    // Panel header
    draw($img, function (ImagickDraw $d) use ($panelX, $panelW) {
        $d->setFillColor('#f6f7f7');
        $d->rectangle($panelX, 100, $panelX + $panelW, 134);
        $d->setFillColor(C_BORDER);
        $d->rectangle($panelX, 133, $panelX + $panelW, 134);
    });
    text($img, $fontBold, 14, C_TEXT, $panelX + 12, 122, '📷  Snapshots');

    // Save Snapshot button
    button($img, $fontRegular, $panelX + 8, 142, $panelW - 16, 32, C_SWMD_BLUE, 'Save Snapshot');

    // Snapshot list
    $snaps = [
        ['Auto-snapshot (before save)', 'Just now',     true],
        ['Auto-snapshot (before save)', '10 min ago',   false],
        ['Before holiday update',       '2 hours ago',  false],
        ['Auto-snapshot (before save)', 'Yesterday',    false],
        ['Pre-redesign backup',         '3 days ago',   false],
    ];

    $sy = 188;
    foreach ($snaps as [$label, $time, $recent]) {
        draw($img, function (ImagickDraw $d) use ($panelX, $sy, $panelW, $recent) {
            $d->setFillColor($recent ? C_BLUE_LIGHT : C_WHITE);
            $d->rectangle($panelX + 1, $sy, $panelX + $panelW - 1, $sy + 54);
            $d->setFillColor(C_BORDER);
            $d->rectangle($panelX + 8, $sy + 53, $panelX + $panelW - 8, $sy + 54);
        });
        text($img, $fontBold, 12, C_TEXT, $panelX + 10, $sy + 18, $label);
        text($img, $fontRegular, 11, C_MUTED, $panelX + 10, $sy + 35, $time);
        text($img, $fontRegular, 11, C_BLUE, $panelX + $panelW - 70, $sy + 18, 'Restore');
        text($img, $fontRegular, 11, '#b32d2e', $panelX + $panelW - 70, $sy + 35, 'Delete');
        $sy += 56;
    }

    $img->writeImage($out);
    $img->destroy();
    echo "  ✓ $out\n";
}

// ===========================================================================
// Screenshot 4 — JSON import form
// ===========================================================================
function screenshot_4(string $out, string $fontBold, string $fontRegular): void {
    $img = make_canvas();
    draw_chrome($img, $fontBold, $fontRegular, 'Menu Manager', 'Appearance');

    $lx = 180; $ly = 100; $cw = W - 180 - 20;

    // Tabs
    $tabs = ['All Menus', 'Import JSON', 'Copy to Site'];
    $tx = $lx;
    foreach ($tabs as $i => $tab) {
        $active = $i === 1;
        $bw = strlen($tab) * 9 + 24;
        draw($img, function (ImagickDraw $d) use ($tx, $ly, $bw, $active) {
            $d->setFillColor($active ? C_WHITE : C_BODY);
            $d->setStrokeColor(C_BORDER);
            $d->setStrokeWidth(1);
            $d->rectangle($tx, $ly, $tx + $bw, $ly + 34);
        });
        text($img, $active ? $fontBold : $fontRegular, 13, $active ? C_BLUE : C_TEXT, $tx + 12, $ly + 22, $tab);
        $tx += $bw + 2;
    }

    // Form card
    $fy = $ly + 34;
    draw($img, function (ImagickDraw $d) use ($lx, $fy, $cw) {
        $d->setFillColor(C_WHITE);
        $d->rectangle($lx, $fy, $lx + $cw, $fy + 520);
        $d->setFillColor(C_BORDER);
        $d->rectangle($lx, $fy + 519, $lx + $cw, $fy + 520);
    });

    text($img, $fontBold, 16, C_TEXT, $lx + 20, $fy + 36, 'Import Menu from JSON');
    text($img, $fontRegular, 13, C_MUTED, $lx + 20, $fy + 56,
        'Upload a JSON file exported by Swift Menu Duplicator, or paste JSON directly.');

    // Field: JSON File
    $row = function (int $y, string $label, string $hint = '') use ($img, $lx, $cw, $fontBold, $fontRegular): void {
        text($img, $fontBold, 13, C_TEXT, $lx + 20, $y + 16, $label);
        draw($img, function (ImagickDraw $d) use ($lx, $y, $cw) {
            $d->setFillColor(C_WHITE);
            $d->setStrokeColor(C_BORDER);
            $d->setStrokeWidth(1);
            $d->rectangle($lx + 200, $y, $lx + $cw - 20, $y + 32);
        });
        if ($hint) text($img, $fontRegular, 12, C_MUTED, $lx + 208, $y + 21, $hint);
    };

    $row($fy + 76,  'JSON File',   'Choose file…   No file selected');
    $row($fy + 128, 'Menu Name',   'Leave blank to use name from file');
    // URL replacement
    text($img, $fontBold, 13, C_TEXT, $lx + 20, $fy + 185, 'URL Replacement');
    text($img, $fontRegular, 12, C_TEXT, $lx + 200, $fy + 183, 'Find:');
    draw($img, function (ImagickDraw $d) use ($lx, $fy, $cw) {
        $d->setFillColor(C_WHITE);
        $d->setStrokeColor(C_BORDER);
        $d->setStrokeWidth(1);
        $d->rectangle($lx + 240, $fy + 168, $lx + $cw - 20, $fy + 192);
    });
    text($img, $fontRegular, 13, C_TEXT, $lx + 248, $fy + 186, 'https://staging.example.com');

    text($img, $fontRegular, 12, C_TEXT, $lx + 200, $fy + 216, 'Replace:');
    draw($img, function (ImagickDraw $d) use ($lx, $fy, $cw) {
        $d->setFillColor(C_WHITE);
        $d->setStrokeColor(C_BORDER);
        $d->setStrokeWidth(1);
        $d->rectangle($lx + 240, $fy + 200, $lx + $cw - 20, $fy + 224);
    });
    text($img, $fontRegular, 13, C_TEXT, $lx + 248, $fy + 218, 'https://production.example.com');

    text($img, $fontRegular, 11, C_MUTED, $lx + 200, $fy + 240,
        'Applied to all custom link item URLs. Leave blank to skip URL replacement.');

    // Preview import button
    button($img, $fontBold, $lx + 200, $fy + 264, 140, 36, C_SWMD_BLUE, 'Preview Import');

    // Preview result card
    $previewY = $fy + 316;
    draw($img, function (ImagickDraw $d) use ($lx, $previewY, $cw) {
        $d->setFillColor('#f0f6fc');
        $d->setStrokeColor(C_SWMD_BLUE);
        $d->setStrokeWidth(1);
        $d->rectangle($lx + 20, $previewY, $lx + $cw - 20, $previewY + 170);
    });
    text($img, $fontBold, 14, C_TEXT, $lx + 32, $previewY + 28, 'Import Preview');
    text($img, $fontRegular, 13, C_TEXT, $lx + 32, $previewY + 54, 'Menu name:');
    text($img, $fontBold, 13, C_TEXT, $lx + 160, $previewY + 54, 'Production Navigation');
    text($img, $fontRegular, 13, C_TEXT, $lx + 32, $previewY + 78, 'Items:');
    text($img, $fontBold, 13, C_TEXT, $lx + 160, $previewY + 78, '8 items will be imported');
    text($img, $fontRegular, 13, C_TEXT, $lx + 32, $previewY + 102, 'URL replacement:');
    text($img, $fontBold, 13, C_GREEN, $lx + 160, $previewY + 102, '✓ Applied to 5 URLs');

    button($img, $fontBold, $lx + 32, $previewY + 126, 140, 34, C_GREEN, 'Confirm Import');
    button($img, $fontRegular, $lx + 182, $previewY + 126, 80, 34, '#f0f0f1', 'Cancel', C_TEXT);

    $img->writeImage($out);
    $img->destroy();
    echo "  ✓ $out\n";
}

// ===========================================================================
// Screenshot 5 — WP-CLI commands in terminal
// ===========================================================================
function screenshot_5(string $out, string $fontBold, string $fontRegular, string $fontMono): void {
    $img = make_canvas();

    // Terminal window background
    draw($img, function (ImagickDraw $d) {
        $d->setFillColor('#1e1e2e');
        $d->rectangle(0, 0, W, H);
    });

    // Terminal chrome (title bar)
    draw($img, function (ImagickDraw $d) {
        $d->setFillColor('#2a2a3e');
        $d->rectangle(0, 0, W, 36);
        // Traffic light buttons
        foreach (['#ff5f56', '#ffbd2e', '#27c93f'] as $i => $c) {
            $d->setFillColor($c);
            $d->circle(18 + $i * 22, 18, 26 + $i * 22, 18);
        }
    });
    text($img, $fontRegular, 13, '#a6a6b8', (int)(W / 2) - 60, 23, 'bash — 120×40');

    // Terminal content
    $mono   = $fontMono;
    $sz     = 14.5;
    $prompt = '$ ';
    $green  = '#a6e3a1';
    $blue   = '#89b4fa';
    $yellow = '#f9e2af';
    $white  = '#cdd6f4';
    $muted  = '#6c7086';
    $cyan   = '#89dceb';

    $lines = [
        ['prompt', 'wp swift-menu-duplicator duplicate 42 --name="Holiday Copy"'],
        ['out',    'Success: Menu "Main Navigation" duplicated as "Holiday Copy" (ID: 57).'],
        ['blank',  ''],
        ['prompt', 'wp swift-menu-duplicator export 42 --output=./main-nav.json'],
        ['out',    'Success: Menu exported to ./main-nav.json (8 items).'],
        ['blank',  ''],
        ['prompt', 'wp swift-menu-duplicator import ./main-nav.json --find=https://staging.example.com --replace=https://example.com --dry-run'],
        ['out',    'Menu name   : Main Navigation'],
        ['out',    'Items       : 8'],
        ['out',    'Source site : https://staging.example.com'],
        ['out',    'Exported at : 2026-04-20T06:00:00+00:00'],
        ['out',    ''],
        ['out',    '(Dry run — no changes were made)'],
        ['blank',  ''],
        ['prompt', 'wp swift-menu-duplicator import ./main-nav.json --find=https://staging.example.com --replace=https://example.com'],
        ['out',    'Success: Menu "Main Navigation" imported (ID: 61, 8 items).'],
        ['blank',  ''],
        ['prompt', 'wp swift-menu-duplicator copy-to-site 42 --target-blog=3 --name="Copied Menu"'],
        ['out',    'Success: Menu copied to site 3 as "Copied Menu" (ID: 12).'],
        ['blank',  ''],
        ['prompt', '█'],
    ];

    $ty = 70;
    foreach ($lines as [$type, $content]) {
        if ($type === 'blank') { $ty += 8; continue; }
        if ($type === 'prompt') {
            text($img, $mono, $sz, $green,  60, $ty, $prompt);
            text($img, $mono, $sz, $white,  60 + 18, $ty, $content);
        } else {
            $color = str_starts_with($content, 'Success') ? $green
                   : (str_starts_with($content, '(Dry') ? $yellow : $cyan);
            text($img, $mono, $sz, $color, 60, $ty, $content);
        }
        $ty += 26;
    }

    // Label at bottom
    draw($img, function (ImagickDraw $d) {
        $d->setFillColor('#2a2a3e');
        $d->rectangle(0, H - 36, W, H);
    });
    text($img, $fontRegular, 12, '#6c7086', 20, H - 12, 'wp swift-menu-duplicator  —  duplicate · export · import · copy-to-site');

    $img->writeImage($out);
    $img->destroy();
    echo "  ✓ $out\n";
}

// ---------------------------------------------------------------------------
// Run — screenshots 1-4 are real browser captures; only 5 is generated here
// ---------------------------------------------------------------------------
echo "Generating screenshots...\n";
screenshot_5("{$OUT_DIR}/screenshot-5.png", $FONT_BOLD, $FONT_REGULAR, $FONT_MONO);
echo "Done. Output → {$OUT_DIR}/\n";
