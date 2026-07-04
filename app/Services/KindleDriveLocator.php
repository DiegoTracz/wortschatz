<?php

namespace App\Services;

/**
 * Localiza o "My Clippings.txt" num Kindle conectado por USB.
 *
 * O Kindle monta como uma unidade removível com o arquivo em
 * `<unidade>:\documents\My Clippings.txt`. No app desktop (NativePHP) temos
 * acesso direto ao sistema de arquivos, então varremos as letras de unidade
 * procurando esse caminho — dispensando o upload manual e o token da API.
 */
class KindleDriveLocator
{
    /** Caminho do arquivo relativo à raiz do volume do Kindle. */
    private const RELATIVE_PATH = 'documents'.DIRECTORY_SEPARATOR.'My Clippings.txt';

    /**
     * Retorna o caminho absoluto do My Clippings.txt do Kindle, ou null se
     * nenhum Kindle conectado for encontrado.
     */
    public function locate(): ?string
    {
        return $this->locateIn($this->candidateRoots());
    }

    /**
     * Procura o arquivo sob uma lista de raízes de volume. Exposto para os
     * testes — a varredura real de unidades vem de candidateRoots().
     *
     * @param  iterable<string>  $roots
     */
    public function locateIn(iterable $roots): ?string
    {
        foreach ($roots as $root) {
            $path = rtrim($root, '/\\').DIRECTORY_SEPARATOR.self::RELATIVE_PATH;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Raízes a inspecionar. No Windows, as letras de unidade D:–Z: (pula A:/B:
     * de disquete e C: do sistema, onde o Kindle nunca monta). Em outros SOs,
     * os pontos de montagem removíveis usuais.
     *
     * @return list<string>
     */
    protected function candidateRoots(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return array_map(fn (string $letter) => $letter.':\\', range('D', 'Z'));
        }

        // macOS: /Volumes/*  ·  Linux: /media/<user>/*, /mnt/*
        return array_merge(
            glob('/Volumes/*', GLOB_ONLYDIR) ?: [],
            glob('/media/*/*', GLOB_ONLYDIR) ?: [],
            glob('/mnt/*', GLOB_ONLYDIR) ?: [],
        );
    }
}
