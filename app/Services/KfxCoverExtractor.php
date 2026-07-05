<?php

namespace App\Services;

/**
 * Extrai a capa embutida num arquivo KFX do Kindle.
 *
 * Livros sideloaded não têm miniatura em `system/thumbnails`, mas a capa está
 * dentro do próprio `.kfx` como um JPEG. Varremos o binário atrás de JPEGs
 * (FFD8…FFD9), validando cada um por um passeio real pelos segmentos — o que
 * descarta os falsos positivos (sequências de bytes que só começam como JPEG).
 * A capa é o maior JPEG com dimensões sãs e formato retrato.
 */
class KfxCoverExtractor
{
    /** Marcadores SOF (start of frame) que carregam largura/altura. */
    private const SOF_MARKERS = [0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF];

    /**
     * Devolve os bytes do JPEG da capa, ou null se o arquivo não existir ou não
     * tiver nenhuma imagem plausível de capa.
     */
    public function extract(string $kfxPath): ?string
    {
        $data = @file_get_contents($kfxPath);

        if ($data === false || $data === '') {
            return null;
        }

        $best = null;
        $bestArea = 0;
        $offset = 0;

        while (($start = strpos($data, "\xFF\xD8\xFF", $offset)) !== false) {
            $parsed = $this->parseJpeg($data, $start);

            if ($parsed === null) {
                $offset = $start + 3;

                continue;
            }

            [$width, $height, $end] = $parsed;
            $offset = $end;

            // Capa: dimensões sãs e retrato (mais alta que larga). Isso corta os
            // JPEGs falsos (dimensões absurdas) e ilustrações em paisagem.
            if ($width < 100 || $width > 2000 || $height < 100 || $height > 3000 || $height <= $width) {
                continue;
            }

            $area = $width * $height;

            if ($area > $bestArea) {
                $bestArea = $area;
                $best = substr($data, $start, $end - $start);
            }
        }

        return $best;
    }

    /**
     * Passeia pelos segmentos de um JPEG a partir do SOI em $start. Retorna
     * [largura, altura, offsetFinal] quando encontra o SOF e o EOI, ou null se a
     * estrutura não bate (não era JPEG de verdade).
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseJpeg(string $data, int $start): ?array
    {
        $length = strlen($data);
        $i = $start + 2; // pula o SOI (FFD8)
        $width = null;
        $height = null;

        while ($i < $length - 1) {
            if ($data[$i] !== "\xFF") {
                return null;
            }

            $marker = ord($data[$i + 1]);

            // Preenchimento (FF repetido) ou marcadores sem payload.
            if ($marker === 0xFF) {
                $i++;

                continue;
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $i += 2;

                continue;
            }

            if ($marker === 0xD9) { // EOI
                return $width !== null ? [$width, $height, $i + 2] : null;
            }

            if ($i + 3 >= $length) {
                return null;
            }

            $segmentLength = (ord($data[$i + 2]) << 8) + ord($data[$i + 3]);

            if ($segmentLength < 2) {
                return null;
            }

            if (in_array($marker, self::SOF_MARKERS, true)) {
                if ($i + 8 >= $length) {
                    return null;
                }

                $height = (ord($data[$i + 5]) << 8) + ord($data[$i + 6]);
                $width = (ord($data[$i + 7]) << 8) + ord($data[$i + 8]);
                $i += 2 + $segmentLength;

                continue;
            }

            if ($marker === 0xDA) { // SOS: início dos dados comprimidos
                $i += 2 + $segmentLength;
                $i = $this->skipEntropy($data, $i, $length);

                continue;
            }

            $i += 2 + $segmentLength;
        }

        return null;
    }

    /**
     * Após o SOS, varre os dados comprimidos até o próximo marcador real
     * (ignorando bytes "stuffed" FF00 e marcadores de restart FFD0–FFD7).
     */
    private function skipEntropy(string $data, int $i, int $length): int
    {
        while ($i < $length - 1) {
            if ($data[$i] === "\xFF") {
                $marker = ord($data[$i + 1]);

                if ($marker === 0x00 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                    $i += 2;

                    continue;
                }

                return $i; // marcador de verdade (EOI ou próximo scan)
            }

            $i++;
        }

        return $i;
    }
}
