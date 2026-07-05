<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Resolve capas de livros a partir do próprio Kindle conectado, sem rede.
 *
 * O "My Clippings.txt" não guarda imagem nem ASIN — só título e autor. Mas o
 * Kindle mantém as capas prontas em `system/thumbnails/thumbnail_<ASIN>_*_portrait.jpg`
 * e o ASIN aparece no nome da pasta `.sdr` de cada livro
 * (`<Título> -_<ASIN>.sdr`, na pasta `documents/Downloads/Items*`). Cruzando os dois
 * conseguimos a capa localmente, casando pelo título — o que ainda ignora o
 * autor (que algumas fontes gravam errado). Livros sem correspondência (ex.:
 * sideloaded sem ASIN real) ficam de fora e seguem sem capa.
 */
class KindleCoverResolver
{
    public function __construct(private KfxCoverExtractor $kfx) {}

    /**
     * Preenche `cover_url` dos livros do usuário que ainda não têm capa, usando
     * o Kindle conectado: a miniatura pronta (livros comprados na Amazon) quando
     * existe e, senão, a capa embutida no `.kfx` (cobre os sideloaded). Devolve
     * quantos casaram.
     */
    public function syncCovers(User $user, string $kindleRoot): int
    {
        $thumbnails = $this->index($kindleRoot);
        $kfxFiles = $this->kfxIndex($kindleRoot);

        if ($thumbnails === [] && $kfxFiles === []) {
            return 0;
        }

        $applied = 0;

        foreach ($user->books()->whereNull('cover_url')->get() as $book) {
            $jpeg = $this->jpegFor($book->title, $thumbnails, $kfxFiles);

            if ($jpeg === null) {
                continue;
            }

            Storage::disk('local')->put("covers/{$book->id}.jpg", $jpeg);

            $book->forceFill([
                'cover_url' => route('books.cover.image', $book, absolute: false),
                'cover_fetched_at' => now(),
            ])->save();

            $applied++;
        }

        return $applied;
    }

    /**
     * Bytes JPEG da capa de um título: miniatura Amazon (certeira) e, na falta,
     * a capa extraída do `.kfx`.
     *
     * @param  array<string, string>  $thumbnails
     * @param  array<string, string>  $kfxFiles
     */
    private function jpegFor(string $title, array $thumbnails, array $kfxFiles): ?string
    {
        $thumbnail = $this->coverFor($title, $thumbnails);

        if ($thumbnail !== null && is_file($thumbnail)) {
            return (string) file_get_contents($thumbnail);
        }

        $kfxFile = $this->coverFor($title, $kfxFiles);

        if ($kfxFile !== null && is_file($kfxFile)) {
            return $this->kfx->extract($kfxFile);
        }

        return null;
    }

    /**
     * Índice título-normalizado → caminho absoluto da miniatura, montado a
     * partir das pastas `.sdr` que têm ASIN com miniatura correspondente.
     *
     * @return array<string, string>
     */
    public function index(string $kindleRoot): array
    {
        $thumbnailsDir = rtrim($kindleRoot, '/\\').'/system/thumbnails';

        if (! is_dir($thumbnailsDir)) {
            return [];
        }

        $index = [];

        foreach ($this->sdrDirectories($kindleRoot) as $directory) {
            $name = preg_replace('/\.sdr$/i', '', basename($directory));

            // Nome da pasta: "<Título> -_<ASIN>.sdr". ASIN = 10 chars alfanuméricos
            // maiúsculos no fim (padrão Amazon B0…); hashes de sideload não casam
            // com miniatura e caem fora naturalmente.
            if (! preg_match('/^(.+?)[\s\-_]*([A-Z0-9]{10})$/', $name, $matches)) {
                continue;
            }

            $thumbnail = $this->thumbnailFor($thumbnailsDir, $matches[2]);

            if ($thumbnail === null) {
                continue;
            }

            $key = $this->normalize($matches[1]);

            if ($key !== '' && ! isset($index[$key])) {
                $index[$key] = $thumbnail;
            }
        }

        return $index;
    }

    /**
     * Índice título-normalizado → caminho do `.kfx` do livro. O nome do arquivo
     * começa com o título e termina com o content-id (hash/ASIN), que removemos.
     *
     * @return array<string, string>
     */
    public function kfxIndex(string $kindleRoot): array
    {
        $root = rtrim($kindleRoot, '/\\');
        $index = [];

        foreach (glob($root.'/documents/Downloads/Items*/*.kfx') ?: [] as $file) {
            $base = basename($file, '.kfx');

            if ($base === 'metadata') {
                continue;
            }

            // "<Título>_<CONTENTID>" → tira o id (alfanumérico de 10+ chars no fim).
            $title = preg_replace('/_[A-Za-z0-9]{10,}$/', '', $base);
            $key = $this->normalize((string) $title);

            if ($key !== '' && ! isset($index[$key])) {
                $index[$key] = $file;
            }
        }

        return $index;
    }

    /**
     * Encontra a miniatura para um título: casa exato pela forma normalizada e,
     * na ausência, por prefixo (um título é começo do outro) para tolerar o
     * subtítulo que uma das fontes traz e a outra não. Prefixo evita os falsos
     * positivos que continência no meio da string causaria.
     *
     * @param  array<string, string>  $index
     */
    public function coverFor(string $title, array $index): ?string
    {
        $key = $this->normalize($title);

        if ($key === '') {
            return null;
        }

        if (isset($index[$key])) {
            return $index[$key];
        }

        foreach ($index as $candidate => $thumbnail) {
            $shorter = strlen($candidate) <= strlen($key) ? $candidate : $key;
            $longer = strlen($candidate) <= strlen($key) ? $key : $candidate;

            if (strlen($shorter) >= 6 && str_starts_with($longer, $shorter)) {
                return $thumbnail;
            }
        }

        return null;
    }

    /**
     * Pastas `.sdr` de livros. Modelos novos guardam em
     * `documents/Downloads/Items*`; modelos antigos, direto na pasta documents.
     *
     * @return list<string>
     */
    private function sdrDirectories(string $kindleRoot): array
    {
        $root = rtrim($kindleRoot, '/\\');

        return array_merge(
            glob($root.'/documents/Downloads/Items*/*.sdr', GLOB_ONLYDIR) ?: [],
            glob($root.'/documents/*.sdr', GLOB_ONLYDIR) ?: [],
        );
    }

    private function thumbnailFor(string $thumbnailsDir, string $asin): ?string
    {
        $matches = glob($thumbnailsDir.'/thumbnail_'.$asin.'_*portrait.jpg') ?: [];

        return $matches[0] ?? null;
    }

    /** Reduz o título a letras/números minúsculos, sem espaços nem pontuação. */
    private function normalize(string $title): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($title));
    }
}
