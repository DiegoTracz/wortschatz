<?php

use App\Services\KfxCoverExtractor;

/**
 * Monta um JPEG baseline mínimo (SOI, SOF0 com dimensões, SOS, EOI) — suficiente
 * para o extrator identificar e medir. Global: reusado no KindleCoverTest.
 */
function minimalJpeg(int $width, int $height): string
{
    return "\xFF\xD8"                                                     // SOI
        ."\xFF\xC0\x00\x0B\x08".pack('n', $height).pack('n', $width)."\x01\x01\x11\x00" // SOF0
        ."\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00"                       // SOS
        ."\x00\x00"                                                       // entropia mínima
        ."\xFF\xD9";                                                      // EOI
}

function kfxFileWith(string $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'kfx');
    file_put_contents($path, $data);

    return $path;
}

test('extrai o maior JPEG retrato e ignora paisagem e lixo', function () {
    $cover = minimalJpeg(400, 600);
    $menor = minimalJpeg(200, 300);
    $paisagem = minimalJpeg(600, 400);
    $lixo = "\xFF\xD8\xFFnaoehumjpeg\xFF\xD9";

    $path = kfxFileWith('KFX-HEADER'.$lixo.'....'.$paisagem.'....'.$menor.'....'.$cover.'....');

    expect((new KfxCoverExtractor)->extract($path))->toBe($cover);

    unlink($path);
});

test('extract devolve null sem nenhuma capa retrato válida', function () {
    $path = kfxFileWith('x'.minimalJpeg(600, 400).'y'."\xFF\xD8\xFFlixo\xFF\xD9");

    expect((new KfxCoverExtractor)->extract($path))->toBeNull();

    unlink($path);
});

test('extract devolve null quando o arquivo não existe', function () {
    expect((new KfxCoverExtractor)->extract(sys_get_temp_dir().'/nao_existe_'.uniqid().'.kfx'))->toBeNull();
});
