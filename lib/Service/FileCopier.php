<?php

namespace Webcomp\ReleaseBuilder\Service;

use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\DTO\ScanRequest;

/**
 * Копирует отсканированные файлы-источники в staging-директорию версии и генерирует version.php.
 *
 * Перед копированием существующая staging-директория целевой версии удаляется,
 * чтобы гарантировать отсутствие устаревших файлов от предыдущих запусков.
 */
class FileCopier
{
    /**
     * @param FilesystemInterface $filesystem Абстракция файловой системы для операций с директориями и файлами.
     * @param PathMapper          $pathMapper Вычисляет пути назначения для каждого файла-источника.
     * @param string              $docRoot    Абсолютный путь к корневой директории веб-сервера.
     */
    public function __construct(
        private readonly FilesystemInterface $filesystem,
        private readonly PathMapper $pathMapper,
        private readonly string $docRoot,
    ) {}

    /**
     * Копирует указанные файлы-источники в staging-директорию версии.
     *
     * Полностью очищает директорию версии перед копированием, чтобы убрать устаревшие файлы.
     * При необходимости создаёт поддиректории назначения.
     *
     * @param string[]    $sourceFiles Абсолютные пути к файлам для копирования.
     * @param ScanRequest $request     Предоставляет строку новой версии и метаданные модуля.
     * @return string[] Абсолютные пути назначения скопированных файлов.
     * @throws \RuntimeException Если директорию не удалось создать или файл — скопировать.
     */
    public function copy(array $sourceFiles, ScanRequest $request): array
    {
        $versionDir = $this->docRoot . '/release-builder/' . $request->newVersion . '/';
        $this->filesystem->deleteDir($versionDir);

        $map         = $this->pathMapper->buildMap($request);
        $copiedPaths = [];

        foreach ($sourceFiles as $sourcePath) {
            $destPath = $this->pathMapper->resolveDestination($sourcePath, $map);
            $destDir  = dirname($destPath) . '/';

            $this->filesystem->makeDir($destDir, 0775, true);
            $this->filesystem->copy($sourcePath, $destPath);

            $copiedPaths[] = $destPath;
        }

        return $copiedPaths;
    }

    /**
     * Генерирует install/version.php внутри staging-директории версии.
     *
     * Файл содержит массив $arModuleVersion с VERSION и VERSION_DATE
     * в формате, ожидаемом установщиком модулей Bitrix.
     *
     * @param string $version Строка версии (например, '1.1.18').
     * @throws \RuntimeException Если директорию или файл не удалось создать.
     */
    public function writeVersionFile(string $version): void
    {
        $dir = $this->docRoot . '/release-builder/' . $version . '/install/';
        $this->filesystem->makeDir($dir, 0775, true);

        $content = '<?php $arModuleVersion = [ "VERSION" => "' . $version
            . '", "VERSION_DATE" => "' . date('Y-m-d H:i:s') . '" ]; ?>';

        $this->filesystem->putContents($dir . 'version.php', $content);
    }
}
