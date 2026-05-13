<?php

namespace Webcomp\ReleaseBuilder\Service;

use Webcomp\ReleaseBuilder\Contract\ArchiveInterface;
use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\DTO\ArchiveRequest;

/**
 * Собирает ZIP-архив релиза из staging-директории версии.
 *
 * Рендерит updater.php из шаблона, записывает description.ru в кодировке Windows-1251,
 * затем рекурсивно добавляет все файлы из staging-директории в ZIP-архив.
 */
class ArchiveBuilder
{
    /**
     * @param ArchiveInterface    $archive             Реализация архива для создания ZIP.
     * @param FilesystemInterface $filesystem          Абстракция файловой системы для чтения и записи файлов.
     * @param string              $docRoot             Абсолютный путь к корневой директории веб-сервера.
     * @param string|null         $updaterTemplatePath Путь к шаблону updater.php.tpl (null — использовать путь по умолчанию).
     */
    public function __construct(
        private readonly ArchiveInterface $archive,
        private readonly FilesystemInterface $filesystem,
        private readonly string $docRoot,
        private readonly ?string $updaterTemplatePath = null,
    ) {}

    /**
     * Собирает архив релиза для указанного запроса.
     *
     * Создаёт staging-директорию, если она отсутствует, рендерит updater.php из шаблона,
     * записывает description.ru в кодировке Windows-1251, затем упаковывает всё в ZIP.
     *
     * @param ArchiveRequest $request Параметры архива (version, description, module, templateName).
     * @return string Абсолютный путь к созданному ZIP-файлу.
     * @throws \RuntimeException Если не удалось перекодировать описание или выполнить операцию с файловой системой/архивом.
     */
    public function build(ArchiveRequest $request): string
    {
        $docRoot     = rtrim($this->docRoot, '/');
        $versionDir  = $docRoot . '/release-builder/' . $request->version . '/';
        $uploadDir   = $docRoot . '/upload/release-builder/';
        $archivePath = $uploadDir . $request->module . '-' . $request->version . '.zip';

        $this->filesystem->makeDir($versionDir, 0775, true);
        $this->filesystem->makeDir($uploadDir, 0775, true);

        $updaterContent = $this->renderUpdaterTemplate($request);
        $this->filesystem->putContents($versionDir . 'updater.php', $updaterContent);

        $description = iconv('utf-8', 'windows-1251', $request->description);
        if ($description === false) {
            throw new \RuntimeException('Failed to encode description to Windows-1251');
        }
        $this->filesystem->putContents($versionDir . 'description.ru', $description);

        $this->archive->create($archivePath);
        $this->addDirectoryToArchive($versionDir, $request->version);
        $this->archive->close();

        return $archivePath;
    }

    /**
     * Читает updater.php.tpl и подставляет все четыре плейсхолдера значениями из запроса.
     *
     * Плейсхолдеры: {{MODULE}}, {{VENDOR}}, {{NAME}}, {{TEMPLATE_NAME}}.
     * VENDOR и NAME извлекаются из $request->module разбиением по первой точке.
     *
     * @param ArchiveRequest $request Предоставляет module и templateName для подстановки.
     * @return string Готовый PHP-код для записи в updater.php.
     */
    private function renderUpdaterTemplate(ArchiveRequest $request): string
    {
        $docRoot  = rtrim($this->docRoot, '/');
        $tplPath  = $this->updaterTemplatePath
            ?? $docRoot . '/release-builder/updater.php.tpl';
        $template = $this->filesystem->getContents($tplPath);

        $parts  = explode('.', $request->module, 2);
        $vendor = $parts[0];
        $name   = $parts[1] ?? '';

        return str_replace(
            ['{{MODULE}}', '{{VENDOR}}', '{{NAME}}', '{{TEMPLATE_NAME}}'],
            [$request->module, $vendor, $name, $request->templateName],
            $template
        );
    }

    /**
     * Рекурсивно добавляет все файлы из директории в открытый архив.
     *
     * @param string $dir           Абсолютный путь к директории (с завершающим слешем).
     * @param string $archivePrefix Префикс пути внутри архива (например, '1.1.18').
     */
    private function addDirectoryToArchive(string $dir, string $archivePrefix): void
    {
        $entries = $this->filesystem->scanDir($dir);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath    = $dir . $entry;
            $archiveName = $archivePrefix . '/' . $entry;

            if ($this->filesystem->isDir($fullPath)) {
                $this->addDirectoryToArchive($fullPath . '/', $archiveName);
                continue;
            }

            $this->archive->addFile($fullPath, $archiveName);
        }
    }
}
