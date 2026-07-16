<?php

/**
 * Garante Electron >= 35 (que embarca Node 22) no driver vendorizado da NativePHP.
 *
 * A NativePHP 1.3 fixa Electron ^32.2.7, que embarca Node 20.18.x — cujo loader
 * ESM->CJS crasha ao iniciar o app desktop
 * (`cjsPreparseModuleExports: Cannot read properties of undefined`), antes de
 * abrir a janela. O Node 22 do Electron 35 não tem esse bug. Como isso vive em
 * node_modules vendorizado (resetado a cada `composer install`), este script
 * reaplica o ajuste. É idempotente e CI-safe: sai sem fazer nada se o driver não
 * estiver instalado ou se o npm/node não existir. Ver NATIVEPHP-MIGRATION.md.
 */
const REQUIRED_MAJOR = 35;

$jsDir = __DIR__.'/../vendor/nativephp/electron/resources/js';
$devNull = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

if (! is_dir($jsDir)) {
    // Driver não instalado (ex.: CI que só roda a suíte PHP) — nada a fazer.
    exit(0);
}

// O driver depende de `menubar`, cujo peer é electron <33; com o pin em 35 o
// `npm install` que o `native:build` roda aqui quebra com ERESOLVE. Ignorar os
// peer deps (comportamento do npm 6) resolve o conflito — o app já roda com
// Electron 35 em dev. Vive no vendor (resetado no composer install), então é
// reaplicado aqui a cada prebuild.
$npmrc = $jsDir.'/.npmrc';
if (! is_file($npmrc) || ! str_contains((string) file_get_contents($npmrc), 'legacy-peer-deps')) {
    file_put_contents($npmrc, "legacy-peer-deps=true\n", FILE_APPEND);
    echo "[native-electron] .npmrc: legacy-peer-deps=true aplicado.\n";
}

// O passo "composer install --no-dev" do native:build tem timeout fixo de 300s
// no driver — pouco para baixar o nativephp/php-bin (centenas de MB) em rede
// lenta. Estende para 3600s; vive no vendor, então é reaplicado a cada install.
$pruneTrait = __DIR__.'/../vendor/nativephp/electron/src/Traits/PrunesVendorDirectory.php';
if (is_file($pruneTrait) && str_contains($contents = (string) file_get_contents($pruneTrait), '->timeout(300)')) {
    file_put_contents($pruneTrait, str_replace('->timeout(300)', '->timeout(3600)', $contents));
    echo "[native-electron] Timeout do composer install do build estendido para 3600s.\n";
}

if (trim((string) shell_exec("npm --version 2>{$devNull}")) === '') {
    fwrite(STDERR, "[native-electron] npm não encontrado; pulei o ajuste do Electron.\n");
    exit(0);
}

$installed = $jsDir.'/node_modules/electron/package.json';
$current = is_file($installed)
    ? (int) (json_decode((string) file_get_contents($installed), true)['version'] ?? 0)
    : 0;

if ($current >= REQUIRED_MAJOR) {
    echo "[native-electron] Electron {$current}.x OK (>= ".REQUIRED_MAJOR.").\n";
    exit(0);
}

echo '[native-electron] Ajustando Electron para '.REQUIRED_MAJOR.".x (Node 22)...\n";

$cwd = getcwd();
chdir($jsDir);
passthru('npm install electron@'.REQUIRED_MAJOR.' --save-exact --ignore-scripts --no-fund --no-audit', $code);
if ($code === 0) {
    // O npm 11 bloqueia o postinstall do Electron; baixa o binário manualmente.
    passthru('node node_modules/electron/install.js', $code);
}
chdir($cwd);

exit($code);
