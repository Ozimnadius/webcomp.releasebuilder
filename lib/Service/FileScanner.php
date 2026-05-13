<?php

namespace Webcomp\ReleaseBuilder\Service;

use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\DTO\ScanRequest;

/**
 * Рекурсивно сканирует исходные директории модуля на предмет файлов, изменённых после заданной отметки времени.
 *
 * Использует PathMapper для определения директорий сканирования и ScanFilter для фильтрации записей.
 * Результирующий список файлов дедуплицируется перед возвратом.
 */
class FileScanner
{
    /**
     * @param FilesystemInterface $filesystem Абстракция файловой системы для обхода директорий.
     * @param PathMapper          $pathMapper Предоставляет список исходных директорий для сканирования.
     * @param ScanFilter          $filter     Определяет, какие записи нужно исключить.
     */
    public function __construct(
        private readonly FilesystemInterface $filesystem,
        private readonly PathMapper $pathMapper,
        private readonly ScanFilter $filter,
    ) {}

    /**
     * Сканирует все исходные директории на предмет файлов, изменённых после timestamp из $request.
     *
     * @param ScanRequest $request Параметры сканирования (модуль, тип, sinceTimestamp).
     * @return string[] Дедуплицированный список абсолютных путей к изменённым файлам.
     */
    public function scan(ScanRequest $request): array
    {
        $map   = $this->pathMapper->buildMap($request);
        $files = [];

        foreach (array_keys($map) as $sourceDir) {
            if (!$this->filesystem->isDir($sourceDir)) {
                continue;
            }
            $isPublicRoot = $this->isPublicRoot($sourceDir);
            $found = $this->scanDirectory($sourceDir, $request->sinceTimestamp, $isPublicRoot);
            $files = array_merge($files, $found);
        }

        return array_unique($files);
    }

    /**
     * Рекурсивно собирает файлы в одной директории, которые новее $sinceTimestamp.
     *
     * @param string $dir                  Абсолютный путь к директории (с завершающим слешем).
     * @param int    $sinceTimestamp       Unix-timestamp — порог времени изменения.
     * @param bool   $applyPublicBlacklist Передавать true при сканировании корня сайта напрямую,
     *                                     чтобы исключить системные директории верхнего уровня.
     * @return string[] Абсолютные пути к подходящим файлам.
     */
    private function scanDirectory(string $dir, int $sinceTimestamp, bool $applyPublicBlacklist = false): array
    {
        $files   = [];
        $entries = $this->filesystem->scanDir($dir);

        foreach ($entries as $entry) {
            if ($this->filter->shouldSkip($entry, $applyPublicBlacklist)) {
                continue;
            }

            $fullPath = $dir . $entry;

            if ($this->filesystem->isDir($fullPath)) {
                $found = $this->scanDirectory($fullPath . '/', $sinceTimestamp, $applyPublicBlacklist);
                $files = array_merge($files, $found);
                continue;
            }

            if ($this->filesystem->getModifiedTime($fullPath) > $sinceTimestamp) {
                $files[] = $fullPath;
            }
        }

        return $files;
    }

    /**
     * Возвращает true, если директория является корнем сайта, а не поддиректорией bitrix/ или local/.
     *
     * Используется для определения, нужно ли применять публичный чёрный список при сканировании.
     *
     * @param string $sourceDir Абсолютный путь к директории.
     * @return bool
     */
    private function isPublicRoot(string $sourceDir): bool
    {
        return !str_contains($sourceDir, '/bitrix/')
            && !str_contains($sourceDir, '/local/');
    }
}
