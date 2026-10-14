<?php

namespace App\Services;

/**
 * Extrait les images JPEG embarquées (DCTDecode) d'un PDF, sans Imagick.
 */
class PdfEmbeddedImageExtractor
{
    /**
     * @return array<int, string> Contenu binaire JPEG
     */
    public function extractJpegs(string $pdfPath): array
    {
        $binary = @file_get_contents($pdfPath);
        if ($binary === false || $binary === '') {
            return [];
        }

        $images = [];

        // Streams PDF classiques : stream ... endstream avec filtre DCTDecode à proximité
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $payload = $match[0];
                $offset = $match[1];
                $headerStart = max(0, $offset - 500);
                $header = substr($binary, $headerStart, $offset - $headerStart);

                if (! preg_match('/\/DCTDecode\b/', $header) && ! preg_match('/\/Filter\s*\/DCTDecode/', $header)) {
                    // JPEG brut éventuel dans le stream
                    if (! str_starts_with($payload, "\xFF\xD8")) {
                        continue;
                    }
                }

                $jpeg = $this->normalizeJpeg($payload);
                if ($jpeg !== null) {
                    $images[] = $jpeg;
                }
            }
        }

        // Fallback : tous les blocs JPEG complets dans le fichier
        if (count($images) === 0 && preg_match_all('/\xFF\xD8\xFF[\s\S]*?\xFF\xD9/', $binary, $jpegMatches)) {
            foreach ($jpegMatches[0] as $jpeg) {
                if (strlen($jpeg) > 2000) {
                    $images[] = $jpeg;
                }
            }
        }

        return $this->uniqueBinaries($images);
    }

    protected function normalizeJpeg(string $payload): ?string
    {
        // Les streams PDF peuvent avoir un saut de ligne initial
        $payload = ltrim($payload, "\r\n");

        $start = strpos($payload, "\xFF\xD8");
        if ($start === false) {
            return null;
        }

        $payload = substr($payload, $start);
        $end = strrpos($payload, "\xFF\xD9");
        if ($end === false) {
            return null;
        }

        $jpeg = substr($payload, 0, $end + 2);

        return strlen($jpeg) > 1500 ? $jpeg : null;
    }

    /**
     * @param  array<int, string>  $images
     * @return array<int, string>
     */
    protected function uniqueBinaries(array $images): array
    {
        $seen = [];
        $unique = [];

        foreach ($images as $image) {
            $hash = md5($image);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $unique[] = $image;
        }

        return $unique;
    }
}
