<?php

namespace Webcomp\ReleaseBuilder\Infrastructure;

use Webcomp\ReleaseBuilder\Contract\ArchiveInterface;

/**
 * Оборачивает встроенный класс PHP ZipArchive для реализации ArchiveInterface.
 */
class ZipArchiveAdapter implements ArchiveInterface
{
    private \ZipArchive $zip;

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если ZipArchive::open() завершился ошибкой.
     */
    public function create(string $path): void
    {
        $this->zip = new \ZipArchive();

        if ($this->zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create archive: {$path}");
        }
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException Если ZipArchive::addFile() вернул false.
     */
    public function addFile(string $filePath, string $entryName): void
    {
        if (!$this->zip->addFile($filePath, $entryName)) {
            throw new \RuntimeException("Cannot add file to archive: {$filePath}");
        }
    }

    /** {@inheritDoc} */
    public function close(): void
    {
        $this->zip->close();
    }
}
