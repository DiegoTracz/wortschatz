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
