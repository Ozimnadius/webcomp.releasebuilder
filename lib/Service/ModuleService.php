<?php

namespace Webcomp\ReleaseBuilder\Service;

use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\Enum\ModuleType;

/**
 * Обнаруживает установленные вендорные модули и читает их метаданные версий.
 *
 * «Вендорный модуль» — любая поддиректория bitrix/modules/, в имени которой есть точка.
 * Это отличает их от стандартных модулей Bitrix (например, 'main', 'iblock').
 */
class ModuleService
{
    /**
     * @param FilesystemInterface $filesystem Абстракция файловой системы для чтения директорий модулей.
     * @param string              $docRoot    Абсолютный путь к корневой директории веб-сервера.
     */
    public function __construct(
        private readonly FilesystemInterface $filesystem,
        private readonly string $docRoot,
    ) {}

    /**
     * Возвращает список идентификаторов всех установленных вендорных модулей.
     *
     * Фильтрует bitrix/modules/: оставляет только записи, содержащие точку в имени, и только директории.
     * Стандартные модули Bitrix (без точки в имени) исключаются автоматически.
     *
     * @return string[] Идентификаторы модулей, например ['webcomp.catalog', 'webcomp.market'].
     * @throws \RuntimeException Если директорию модулей не удалось просканировать.
     */
    public function getAvailableModules(): array
    {
        $path = $this->docRoot . '/bitrix/modules/';
        $dirs = $this->filesystem->scanDir($path);

        return array_values(array_filter($dirs, function (string $dir) use ($path): bool {
            return $dir !== '.' && $dir !== '..'
                && str_contains($dir, '.')
                && $this->filesystem->isDir($path . $dir);
        }));
    }

    /**
     * Читает VERSION и VERSION_DATE из install/version.php указанного модуля.
     *
     * @param string $module Идентификатор модуля в формате vendor.name (например, 'webcomp.market').
     * @return array{VERSION: string, VERSION_DATE: string}
     * @throws \RuntimeException Если version.php не существует или VERSION не удалось разобрать.
     */
    public function getModuleVersion(string $module): array
    {
        $path = $this->docRoot . '/bitrix/modules/' . $module . '/install/version.php';

        if (!$this->filesystem->exists($path)) {
            throw new \RuntimeException("version.php not found for module: {$module}");
        }

        $content = $this->filesystem->getContents($path);

        preg_match('/[\'"]VERSION[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $versionMatch);
        preg_match('/[\'"]VERSION_DATE[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $dateMatch);

        if (empty($versionMatch[1])) {
            throw new \RuntimeException("Cannot parse VERSION from: {$path}");
        }

        return [
            'VERSION'      => $versionMatch[1],
            'VERSION_DATE' => $dateMatch[1] ?? '',
        ];
    }

    /**
     * Возвращает список доступных шаблонов из local/templates/ и bitrix/templates/.
     *
     * Шаблоны из local/ идут первыми. Дубликаты (одинаковые имена) исключаются.
     *
     * suggestedTemplate определяется в порядке приоритета:
     *   1. local/templates/ — если там ровно одна директория.
     *   2. bitrix/templates/ — если local/ пуста и среди записей bitrix/ ровно одна
     *      не начинается с точки (системные шаблоны вроде .default исключаются).
     *
     * @return array{all: string[], suggestedTemplate: string}
     */
    public function getAvailableTemplates(): array
    {
        $local  = $this->scanTemplateDir($this->docRoot . '/local/templates/');
        $bitrix = $this->scanTemplateDir($this->docRoot . '/bitrix/templates/');

        $all = $local;
        foreach ($bitrix as $tpl) {
            if (!in_array($tpl, $all, true)) {
                $all[] = $tpl;
            }
        }

        if (count($local) === 1) {
            $suggestedTemplate = $local[0];
        } elseif (count($local) === 0) {
            // Отфильтровываем системные шаблоны Bitrix (.default, .default_simple и т.д.)
            $bitrixCustom = array_values(array_filter(
                $bitrix,
                fn(string $tpl) => !str_starts_with($tpl, '.')
            ));
            $suggestedTemplate = count($bitrixCustom) === 1 ? $bitrixCustom[0] : '';
        } else {
            $suggestedTemplate = '';
        }

        return [
            'all'              => $all,
            'suggestedTemplate' => $suggestedTemplate,
        ];
    }

    /**
     * Возвращает имена поддиректорий в указанной директории шаблонов.
     *
     * Возвращает пустой массив, если директория не существует.
     *
     * @param string $path Абсолютный путь к директории шаблонов (с завершающим слешем).
     * @return string[] Имена директорий шаблонов.
     */
    private function scanTemplateDir(string $path): array
    {
        if (!$this->filesystem->isDir($path)) {
            return [];
        }

        $entries = $this->filesystem->scanDir($path);

        return array_values(array_filter($entries, function (string $entry) use ($path): bool {
            return $entry !== '.' && $entry !== '..'
                && $this->filesystem->isDir($path . $entry);
        }));
    }

    /**
     * Определяет тип модуля по наличию директории install/wizards/.
     *
     * Шаблонные решения всегда содержат wizard-директорию; обычные модули — никогда.
     *
     * @param string $module Идентификатор модуля в формате vendor.name.
     * @return ModuleType
     */
    public function detectModuleType(string $module): ModuleType
    {
        $wizardDir = $this->docRoot . '/bitrix/modules/' . $module . '/install/wizards/';

        return $this->filesystem->isDir($wizardDir)
            ? ModuleType::TEMPLATE_SOLUTION
            : ModuleType::REGULAR_MODULE;
    }

    /**
     * Увеличивает патч-сегмент строки семантической версии.
     *
     * @param string $version Строка версии в формате major.minor.patch (например, '1.1.17').
     * @return string Увеличенная версия (например, '1.1.18').
     * @throws \InvalidArgumentException Если строка версии содержит не ровно три сегмента.
     */
    public function incrementVersion(string $version): string
    {
        $parts = explode('.', $version);

        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid version format: {$version}");
        }

        $parts[2] = (int) $parts[2] + 1;

        return implode('.', $parts);
    }
}
