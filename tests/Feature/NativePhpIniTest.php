<?php

use App\Providers\NativeAppServiceProvider;

test('o php embutido recebe limites de upload compatíveis com o import de PDF', function () {
    $ini = (new NativeAppServiceProvider)->phpIni();

    // O import de PDF valida max:102400 (100M) — o runtime precisa acompanhar.
    expect($ini['upload_max_filesize'])->toBe('100M')
        ->and($ini['post_max_size'])->toBe('110M');
});
