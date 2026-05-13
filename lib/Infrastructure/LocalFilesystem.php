<?php

namespace Webcomp\ReleaseBuilder\Infrastructure;

use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;

/**
 * Продакшен-реализация файловой системы на основе встроенных функций PHP.
 *
 * Большинство мутирующих операций бросают \RuntimeException при ошибке.
 * Исключение: deleteDir() — сбои внутри рекурсивного удаления игнорируются,
 * так как метод предназначен для cleanup-операций staging-директории.
 */
class LocalFilesystem implements FilesystemInterface
{
    /** {@inheritDoc} */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /** {@inheritDoc} */
    public function isDir(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если mkdir() завершился ошибкой и директория по-прежнему не существует.
     */
    public function makeDir(string $path, int $mode = 0775, bool $recursive = true): void
    {
        if (!is_dir($path) && !mkdir($path, $mode, $recursive) && !is_dir($path)) {
            throw new \RuntimeException("Failed to create directory: {$path}");
        }
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если copy() вернул false.
     */
    public function copy(string $from, string $to): void
    {
        if (!copy($from, $to)) {
            throw new \RuntimeException("Failed to copy {$from} to {$to}");
        }
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если file_put_contents() вернул false.
     */
    public function putContents(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Failed to write to {$path}");
        }
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если file_get_contents() вернул false.
     */
    public function getContents(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Failed to read {$path}");
        }

        return $content;
    }

    /** {@inheritDoc} */
    public function getModifiedTime(string $path): int
    {
        return filemtime($path) ?: 0;
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если scandir() вернул false.
     */
    public function scanDir(string $path): array
    {
        $entries = scandir($path);

        if ($entries === false) {
            throw new \RuntimeException("Cannot scan directory: {$path}");
        }

        return $entries;
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если файл существует, но unlink() завершился ошибкой.
     */
    public function delete(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (!unlink($path)) {
            throw new \RuntimeException("Failed to delete file: {$path}");
        }
    }

    /**
     * {@inheritDoc}
     *
     * Реализация: ошибки внутри рекурсивного обхода (unlink(), rmdir(), scandir())
     * не проверяются и исключения не бросаются. Это допустимо для cleanup-операций
     * staging-директории, где неполное удаление не является критичной ошибкой.
     */
    public function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = rtrim($path, '/') . '/' . $entry;
            if (is_dir($fullPath)) {
                $this->deleteDir($fullPath);
            } else {
                unlink($fullPath);
            }
        }
        rmdir($path);
    }
}
