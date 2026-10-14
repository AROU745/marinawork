<?php

namespace App\Services;

/**
 * Découpe une image de page en grille de tickets (GD).
 */
class TicketGridSplitter
{
    /**
     * @return array<int, string> PNG binaires
     */
    public function split(string $imageBinary, int $cols = 2, int $rows = 0, float $marginRatio = 0.01): array
    {
        if (! extension_loaded('gd')) {
            return [];
        }

        $src = @imagecreatefromstring($imageBinary);
        if ($src === false) {
            return [];
        }

        $width = imagesx($src);
        $height = imagesy($src);

        if ($cols < 1) {
            $cols = 1;
        }

        // Ratio voucher MikroTik ≈ 1.9 (largeur / hauteur)
        if ($rows < 1) {
            $cellWidth = $width / $cols;
            $estimatedCellHeight = $cellWidth / 1.9;
            $rows = max(1, (int) round($height / $estimatedCellHeight));
        }

        $marginX = (int) round($width * $marginRatio);
        $marginY = (int) round($height * $marginRatio);
        $usableW = $width - (2 * $marginX);
        $usableH = $height - (2 * $marginY);
        $cellW = (int) floor($usableW / $cols);
        $cellH = (int) floor($usableH / $rows);

        $tickets = [];

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $x = $marginX + ($c * $cellW);
                $y = $marginY + ($r * $cellH);

                $tile = imagecreatetruecolor($cellW, $cellH);
                if ($tile === false) {
                    continue;
                }

                imagecopy($tile, $src, 0, 0, $x, $y, $cellW, $cellH);

                if ($this->isMostlyBlank($tile)) {
                    imagedestroy($tile);
                    continue;
                }

                ob_start();
                imagepng($tile);
                $png = ob_get_clean();
                imagedestroy($tile);

                if (is_string($png) && $png !== '') {
                    $tickets[] = $png;
                }
            }
        }

        imagedestroy($src);

        return $tickets;
    }

    /**
     * Ignore les cases quasi vides (bas de page).
     */
    protected function isMostlyBlank(\GdImage $tile): bool
    {
        $w = imagesx($tile);
        $h = imagesy($tile);
        $sample = 0;
        $dark = 0;

        for ($y = 0; $y < $h; $y += max(1, (int) floor($h / 20))) {
            for ($x = 0; $x < $w; $x += max(1, (int) floor($w / 20))) {
                $rgb = imagecolorat($tile, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $lum = ($r + $g + $b) / 3;
                $sample++;
                if ($lum < 230) {
                    $dark++;
                }
            }
        }

        if ($sample === 0) {
            return true;
        }

        return ($dark / $sample) < 0.02;
    }
}
