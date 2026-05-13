<?php

namespace Webcomp\ReleaseBuilder\Service;

use Webcomp\ReleaseBuilder\DTO\ScanRequest;
use Webcomp\ReleaseBuilder\Enum\ModuleType;

/**
 * Сопоставляет пути источников с путями назначения внутри staging-директории версии.
 *
 * Для обычных модулей в карту включается только bitrix/modules/{module}/.
 * Для шаблонных решений дополнительно добавляются директории компонентов, JS, CSS,
 * изображений, шаблонов и корня сайта — каждая с соответствующим путём внутри install/.
 */
class PathMapper
{
    /**
     * @param string $docRoot Абсолютный путь к корневой директории веб-сервера.
     */
    public function __construct(private readonly string $docRoot) {}

    /**
     * Строит карту соответствия директорий источник → назначение для указанного запроса сканирования.
     *
     * Ключи — абсолютные пути исходных директорий (с завершающим слешем).
     * Значения — абсолютные пути директорий назначения внутри staging-директории версии.
     *
     * @param ScanRequest $request Параметры сканирования: модуль, тип, новая версия.
     * @return array<string, string> Карта: исходная директория → директория назначения.
     */
    public function buildMap(ScanRequest $request): array
    {
        [$vendor, $module] = explode('.', $request->module, 2);
        $base = $this->docRoot . '/release-builder/' . $request->newVersion . '/';

        $map = [
            $this->docRoot . '/bitrix/modules/' . $request->module . '/' => $base,
        ];

        if ($request->type === ModuleType::TEMPLATE_SOLUTION) {
            $wizardBase = $base . 'install/wizards/' . $vendor . '/' . $module . '/site/';

            $map[$this->docRoot . '/bitrix/components/' . $vendor . '/']      = $base . 'install/components/' . $vendor . '/';
            $map[$this->docRoot . '/bitrix/js/' . $request->module . '/']     = $base . 'install/js/' . $request->module . '/';
            $map[$this->docRoot . '/bitrix/css/' . $request->module . '/']    = $base . 'install/css/' . $request->module . '/';
            $map[$this->docRoot . '/bitrix/images/' . $request->module . '/'] = $base . 'install/images/' . $request->module . '/';
            $map[$this->docRoot . '/local/templates/']                        = $wizardBase . 'templates/';
            $map[$this->docRoot . '/']                                         = $wizardBase . 'public/ru/';

            if ($request->templateName !== '') {
                $map[$this->docRoot . '/bitrix/templates/' . $request->templateName . '/']
                    = $wizardBase . 'templates/' . $request->templateName . '/';
            }
        }

        return $map;
    }

    /**
     * Определяет путь назначения для одного файла-источника по карте директорий.
     *
     * Перебирает карту от наиболее специфичного к наименее специфичному ключу (от длинного к короткому)
     * и заменяет совпавший префикс источника соответствующим префиксом назначения.
     *
     * @param string                $sourcePath Абсолютный путь к файлу-источнику.
     * @param array<string, string> $map        Карта, полученная от buildMap().
     * @return string Абсолютный путь назначения для файла.
     * @throws \RuntimeException Если ни одна запись карты не совпала с путём источника.
     */
    public function resolveDestination(string $sourcePath, array $map): string
    {
        // Длинные ключи идут первыми — ищем наиболее специфичный путь
        krsort($map);

        foreach ($map as $sourceDir => $destDir) {
            if (str_starts_with($sourcePath, $sourceDir)) {
                return $destDir . substr($sourcePath, strlen($sourceDir));
            }
        }

        throw new \RuntimeException("Cannot map path: {$sourcePath}");
    }
}
