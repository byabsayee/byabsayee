<?php
namespace App\Helpers;

/**
 * Pure-PHP QR Code generator.
 * Generates a data URI (PNG via GD) for a given string.
 * Supports byte mode, error correction level M, versions 1-10.
 */
class QrCode
{
    // GF(256) tables for Reed-Solomon
    private static array $EXP = [];
    private static array $LOG = [];
    private static bool  $tablesBuilt = false;

    public static function dataUri(string $data, int $pixelSize = 200): string
    {
        try {
            $matrix  = self::generate($data);
            $n       = count($matrix);
            $quiet   = 4;
            $total   = $n + $quiet * 2;
            $cell    = max(1, intdiv($pixelSize, $total));
            $imgSize = $total * $cell;

            $img = imagecreatetruecolor($imgSize, $imgSize);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefill($img, 0, 0, $white);

            for ($r = 0; $r < $n; $r++) {
                for ($c = 0; $c < $n; $c++) {
                    if ($matrix[$r][$c]) {
                        $x = ($c + $quiet) * $cell;
                        $y = ($r + $quiet) * $cell;
                        imagefilledrectangle($img, $x, $y, $x + $cell - 1, $y + $cell - 1, $black);
                    }
                }
            }

            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);

            return 'data:image/png;base64,' . base64_encode($png);
        } catch (\Throwable $e) {
            error_log('[QrCode] ' . $e->getMessage());
            return 'https://quickchart.io/qr?text=' . rawurlencode($data) . '&size=' . $pixelSize;
        }
    }

    // ── Public entry: build QR matrix ─────────────────────────────────────────
    public static function generate(string $data): array
    {
        self::buildTables();

        // Version capacities for byte mode, EC level M (number of data codewords)
        $verCap = [16,28,44,64,86,108,124,154,182,216];
        $version = 1;
        $byteLen = strlen($data);
        foreach ($verCap as $i => $cap) {
            if ($byteLen <= $cap) { $version = $i + 1; break; }
        }

        $size = 17 + $version * 4;
        $m    = array_fill(0, $size, array_fill(0, $size, -1));

        // Place structural patterns
        self::placeFinderPattern($m, 0,        0);
        self::placeFinderPattern($m, $size-7,  0);
        self::placeFinderPattern($m, 0,        $size-7);
        self::placeSeparators($m, $size);
        self::placeTimingPatterns($m, $size);
        self::placeAlignmentPatterns($m, $version, $size);
        self::reserveFormatModules($m, $size);

        // Build codeword stream
        $codewords = self::buildCodewords($data, $version);

        // Place data
        self::placeCodewords($m, $codewords, $size);

        // Find best mask
        [$maskedMatrix, $mask] = self::chooseMask($m, $size);

        // Write format info
        self::writeFormatInfo($maskedMatrix, $mask, 0b01 /* EC level M */, $size);

        // Convert to 0/1
        return array_map(fn($row) => array_map(fn($v) => $v & 1, $row), $maskedMatrix);
    }

    // ── Finder pattern ────────────────────────────────────────────────────────
    private static function placeFinderPattern(array &$m, int $row, int $col): void
    {
        $pat = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1],
        ];
        $size = count($m);
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $rr = $row + $r; $cc = $col + $c;
                if ($rr >= 0 && $rr < $size && $cc >= 0 && $cc < $size) {
                    $m[$rr][$cc] = $pat[$r][$c] ? 0b11 : 0b10; // 3=dark fn, 2=light fn
                }
            }
        }
    }

    // ── Separators ────────────────────────────────────────────────────────────
    private static function placeSeparators(array &$m, int $size): void
    {
        for ($i = 0; $i < 8; $i++) {
            // TL
            self::setFn($m, 7, $i, 0, $size);
            self::setFn($m, $i, 7, 0, $size);
            // TR
            self::setFn($m, 7, $size-1-$i, 0, $size);
            self::setFn($m, $i, $size-8, 0, $size);
            // BL
            self::setFn($m, $size-8, $i, 0, $size);
            self::setFn($m, $size-1-$i, 7, 0, $size);
        }
    }

    private static function setFn(array &$m, int $r, int $c, int $v, int $size): void
    {
        if ($r >= 0 && $r < $size && $c >= 0 && $c < $size && $m[$r][$c] === -1) {
            $m[$r][$c] = $v ? 0b11 : 0b10;
        }
    }

    // ── Timing patterns ───────────────────────────────────────────────────────
    private static function placeTimingPatterns(array &$m, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 0b11 : 0b10;
            if ($m[6][$i] === -1) $m[6][$i] = $v;
            if ($m[$i][6] === -1) $m[$i][6] = $v;
        }
    }

    // ── Alignment patterns ────────────────────────────────────────────────────
    private static function placeAlignmentPatterns(array &$m, int $version, int $size): void
    {
        $table = [
            1  => [],
            2  => [6, 18],
            3  => [6, 22],
            4  => [6, 26],
            5  => [6, 30],
            6  => [6, 34],
            7  => [6, 22, 38],
            8  => [6, 24, 42],
            9  => [6, 28, 46],
            10 => [6, 26, 50],
        ];
        $coords = $table[$version] ?? [];
        foreach ($coords as $r) {
            foreach ($coords as $c) {
                if ($m[$r][$c] !== -1) continue; // overlaps finder
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $dark = (abs($dr) === 2 || abs($dc) === 2 || ($dr === 0 && $dc === 0));
                        $m[$r+$dr][$c+$dc] = $dark ? 0b11 : 0b10;
                    }
                }
            }
        }
    }

    // ── Reserve format modules ────────────────────────────────────────────────
    private static function reserveFormatModules(array &$m, int $size): void
    {
        // Horizontal strip: row 8, cols 0-8 and row 8, cols size-8..size-1
        for ($i = 0; $i <= 8; $i++) { if ($m[8][$i] === -1) $m[8][$i] = 0b10; }
        for ($i = $size-8; $i < $size; $i++) { if ($m[8][$i] === -1) $m[8][$i] = 0b10; }
        // Vertical strip: col 8, rows 0-8 and col 8, rows size-7..size-1
        for ($i = 0; $i <= 8; $i++) { if ($m[$i][8] === -1) $m[$i][8] = 0b10; }
        for ($i = $size-7; $i < $size; $i++) { if ($m[$i][8] === -1) $m[$i][8] = 0b10; }
        // Dark module
        $m[$size-8][8] = 0b11;
    }

    // ── Build codeword byte array ─────────────────────────────────────────────
    private static function buildCodewords(string $data, int $version): array
    {
        // Block structure for EC level M
        $blockDef = [
            1  => [[1,16,10]],
            2  => [[1,28,16]],
            3  => [[1,44,26]],
            4  => [[2,32,18]],
            5  => [[2,43,24]],
            6  => [[4,27,16]],
            7  => [[4,31,18]],
            8  => [[2,38,22],[2,39,22]],
            9  => [[3,36,20],[2,37,20]],
            10 => [[4,43,24],[1,44,24]],
        ];
        $blocks = $blockDef[$version] ?? [[1,44,26]];

        // Count total data codewords
        $totalData = 0;
        foreach ($blocks as [$cnt,$dCW]) $totalData += $cnt * $dCW;

        // Build bit string: mode(4) + length(8) + data bytes + terminator
        $bits = '0100' . str_pad(decbin(strlen($data)), 8, '0', STR_PAD_LEFT);
        for ($i = 0; $i < strlen($data); $i++) $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        $bits .= '0000'; // terminator
        // Pad to byte boundary
        $bits = str_pad($bits, (int)ceil(strlen($bits)/8)*8, '0');
        // Fill with pad codewords
        $pads = ['11101100','00010001'];
        $pi = 0;
        while (strlen($bits) < $totalData*8) { $bits .= $pads[$pi++ % 2]; }
        $bits = substr($bits, 0, $totalData*8);

        // Split into bytes
        $cws = [];
        for ($i = 0; $i < strlen($bits); $i += 8) $cws[] = bindec(substr($bits,$i,8));

        // Split into blocks and compute EC
        $dataBlocks = [];
        $pos = 0;
        foreach ($blocks as [$cnt,$dCW,$eCW]) {
            for ($b = 0; $b < $cnt; $b++) {
                $d = array_slice($cws, $pos, $dCW);
                $e = self::reedSolomon($d, $eCW);
                $dataBlocks[] = [$d, $e];
                $pos += $dCW;
            }
        }

        // Interleave data
        $result = [];
        $maxD = max(array_map(fn($b)=>count($b[0]), $dataBlocks));
        for ($i=0;$i<$maxD;$i++) foreach ($dataBlocks as $b) if (isset($b[0][$i])) $result[] = $b[0][$i];
        $maxE = max(array_map(fn($b)=>count($b[1]), $dataBlocks));
        for ($i=0;$i<$maxE;$i++) foreach ($dataBlocks as $b) if (isset($b[1][$i])) $result[] = $b[1][$i];

        return $result;
    }

    // ── Reed-Solomon over GF(256) ─────────────────────────────────────────────
    private static function reedSolomon(array $data, int $ecLen): array
    {
        $gen = self::rsGenerator($ecLen);
        $msg = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $msg[$i];
            if ($coef === 0) continue;
            for ($j = 0; $j < count($gen); $j++) {
                $msg[$i+$j] ^= self::gfMul($coef, $gen[$j]);
            }
        }
        return array_slice($msg, count($data));
    }

    private static function rsGenerator(int $degree): array
    {
        $g = [1];
        for ($i = 0; $i < $degree; $i++) {
            $g = self::polyMul($g, [1, self::$EXP[$i]]);
        }
        return $g;
    }

    private static function polyMul(array $a, array $b): array
    {
        $r = array_fill(0, count($a)+count($b)-1, 0);
        foreach ($a as $i => $av) foreach ($b as $j => $bv) $r[$i+$j] ^= self::gfMul($av,$bv);
        return $r;
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) return 0;
        return self::$EXP[(self::$LOG[$a] + self::$LOG[$b]) % 255];
    }

    private static function buildTables(): void
    {
        if (self::$tablesBuilt) return;
        self::$EXP = array_fill(0, 512, 0);
        self::$LOG = array_fill(0, 256, 0);
        $v = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$EXP[$i] = $v;
            self::$LOG[$v] = $i;
            $v <<= 1;
            if ($v >= 256) $v ^= 285;
        }
        for ($i = 255; $i < 512; $i++) self::$EXP[$i] = self::$EXP[$i-255];
        self::$tablesBuilt = true;
    }

    // ── Place codewords ───────────────────────────────────────────────────────
    private static function placeCodewords(array &$m, array $codewords, int $size): void
    {
        // Remainder bits per version (EC level M)
        $remainder = [0,7,7,7,7,7,0,0,0,0];
        $version   = intdiv($size - 17, 4);
        $bits      = '';
        foreach ($codewords as $cw) $bits .= str_pad(decbin($cw),8,'0',STR_PAD_LEFT);
        $bits .= str_repeat('0', $remainder[$version-1] ?? 0);

        $bi   = 0;
        $bLen = strlen($bits);
        $upward = true;
        $col    = $size - 1;

        while ($col > 0) {
            if ($col === 6) { $col--; continue; }
            for ($i = 0; $i < $size; $i++) {
                $row = $upward ? ($size-1-$i) : $i;
                foreach ([$col, $col-1] as $c) {
                    if ($m[$row][$c] === -1) {
                        $bit = ($bi < $bLen) ? (int)$bits[$bi++] : 0;
                        $m[$row][$c] = $bit;
                    }
                }
            }
            $upward = !$upward;
            $col -= 2;
        }
    }

    // ── Masking ───────────────────────────────────────────────────────────────
    private static function chooseMask(array $m, int $size): array
    {
        $best = null; $bestScore = PHP_INT_MAX; $bestMask = 0;
        for ($mask = 0; $mask < 8; $mask++) {
            $t = self::applyMask($m, $mask, $size);
            // Write tentative format info to score it
            self::writeFormatInfo($t, $mask, 0b01, $size);
            $score = self::penaltyScore($t, $size);
            if ($score < $bestScore) { $bestScore = $score; $bestMask = $mask; $best = $t; }
        }
        return [$best, $bestMask];
    }

    private static function applyMask(array $m, int $mask, int $size): array
    {
        $t = $m;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($t[$r][$c] > 1) continue; // function module (2=light fn, 3=dark fn)
                $flip = match($mask) {
                    0 => ($r+$c) % 2 === 0,
                    1 => $r % 2 === 0,
                    2 => $c % 3 === 0,
                    3 => ($r+$c) % 3 === 0,
                    4 => (intdiv($r,2)+intdiv($c,3)) % 2 === 0,
                    5 => ($r*$c%2 + $r*$c%3) === 0,
                    6 => ($r*$c%2 + $r*$c%3) % 2 === 0,
                    7 => (($r+$c)%2 + $r*$c%3) % 2 === 0,
                };
                if ($flip) $t[$r][$c] ^= 1;
            }
        }
        return $t;
    }

    private static function writeFormatInfo(array &$m, int $mask, int $ecIndicator, int $size): void
    {
        // ecIndicator: L=01,M=00,Q=11,H=10 — QR spec uses 2-bit indicator
        // For M: indicator = 00
        $format = ($ecIndicator << 3) | $mask;
        // BCH(15,5) error correction
        $g = 0b10100110111;
        $f = $format << 10;
        for ($i = 4; $i >= 0; $i--) {
            if ($f & (1 << ($i+10))) $f ^= ($g << $i);
        }
        $formatted = (($format << 10) | $f) ^ 0b101010000010010;
        $bits = str_pad(decbin($formatted), 15, '0', STR_PAD_LEFT);

        // Format string positions (two copies)
        $pos1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
        $pos2 = [
            [$size-1,8],[$size-2,8],[$size-3,8],[$size-4,8],[$size-5,8],[$size-6,8],[$size-7,8],
            [8,$size-8],[8,$size-7],[8,$size-6],[8,$size-5],[8,$size-4],[8,$size-3],[8,$size-2],[8,$size-1],
        ];
        foreach ($pos1 as $i => [$r,$c]) $m[$r][$c] = ((int)$bits[$i]) ? 0b11 : 0b10;
        foreach ($pos2 as $i => [$r,$c]) $m[$r][$c] = ((int)$bits[$i]) ? 0b11 : 0b10;
    }

    private static function penaltyScore(array $m, int $size): int
    {
        $p = 0;
        // Rule 1: 5+ consecutive same-color modules
        for ($r = 0; $r < $size; $r++) {
            $run = 1;
            for ($c = 1; $c < $size; $c++) {
                if (($m[$r][$c]&1) === ($m[$r][$c-1]&1)) { $run++; if($run===5) $p+=3; elseif($run>5) $p++; }
                else $run = 1;
            }
        }
        for ($c = 0; $c < $size; $c++) {
            $run = 1;
            for ($r = 1; $r < $size; $r++) {
                if (($m[$r][$c]&1) === ($m[$r-1][$c]&1)) { $run++; if($run===5) $p+=3; elseif($run>5) $p++; }
                else $run = 1;
            }
        }
        // Rule 2: 2x2 blocks
        for ($r = 0; $r < $size-1; $r++) {
            for ($c = 0; $c < $size-1; $c++) {
                $v = $m[$r][$c]&1;
                if (($m[$r][$c+1]&1)===$v && ($m[$r+1][$c]&1)===$v && ($m[$r+1][$c+1]&1)===$v) $p+=3;
            }
        }
        return $p;
    }
}
