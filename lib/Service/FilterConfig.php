<?php

namespace Webcomp\ReleaseBuilder\Service;

use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;

/**
 * Читает и записывает пользовательскую конфигурацию модуля (паттерны фильтров и имя шаблона) из config.json.
 *
 * config.json использует вложенную структуру:
 *   { "webcomp.market": { "patterns": ["node_modules"], "templateName": "webcomp_yellow" } }
 *
 * Все чтения и записи атомарны на уровне модуля: сохранение паттернов не затрагивает templateName и наоборот.
 */
class FilterConfig
{
    private readonly string $configPath;

    /**
     * @param FilesystemInterface $filesystem Абстракция файловой системы для чтения и записи config.json.
     * @param string              $baseDir    Директория, в которой находится config.json (обычно корень release-builder).
     */
    public function __construct(
        private readonly FilesystemInterface $filesystem,
        string $baseDir,
    ) {
        $this->configPath = rtrim($baseDir, '/') . '/config.json';
    }

    /**
     * Возвращает паттерны исключений, настроенные для указанного модуля.
     *
     * @param string $module Идентификатор модуля (например, 'webcomp.market').
     * @return string[] Список паттернов, или пустой массив, если ничего не настроено.
     * @throws \RuntimeException Если config.json содержит невалидный JSON.
     */
    public function getPatterns(string $module): array
    {
        return $this->readAllData()[$module]['patterns'] ?? [];
    }

    /**
     * Возвращает имя шаблона, настроенное для указанного модуля.
     *
     * @param string $module Идентификатор модуля (например, 'webcomp.market').
     * @return string Название директории шаблона (например, 'webcomp_yellow'), или '', если не настроено.
     * @throws \RuntimeException Если config.json содержит невалидный JSON.
     */
    public function getTemplateName(string $module): string
    {
        return $this->readAllData()[$module]['templateName'] ?? '';
    }

    /**
     * Сохраняет паттерны исключений для модуля, не затрагивая остальные поля.
     *
     * Создаёт config.json, если файл не существует. Сохраняет имеющийся templateName.
     *
     * @param string   $module   Идентификатор модуля.
     * @param string[] $patterns Паттерны для сохранения. Ключи массива сбрасываются.
     * @throws \RuntimeException Если config.json содержит невалидный JSON или файл не удалось записать.
     */
    public function savePatterns(string $module, array $patterns): void
    {
        $data                 = $this->readAllData();
        $existing             = $data[$module] ?? [];
        $existing['patterns'] = array_values($patterns);
        $data[$module]        = $existing;

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->filesystem->putContents($this->configPath, $encoded);
    }

    /**
     * Сохраняет имя шаблона для модуля, не затрагивая остальные поля.
     *
     * Создаёт config.json, если файл не существует. Сохраняет имеющиеся паттерны.
     *
     * @param string $module       Идентификатор модуля.
     * @param string $templateName Название директории шаблона (пустая строка для очистки).
     * @throws \RuntimeException Если config.json содержит невалидный JSON или файл не удалось записать.
     */
    public function saveTemplateName(string $module, string $templateName): void
    {
        $data                     = $this->readAllData();
        $existing                 = $data[$module] ?? [];
        $existing['templateName'] = $templateName;
        $data[$module]            = $existing;

        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->filesystem->putContents($this->configPath, $encoded);
    }

    /**
     * Читает и возвращает полный декодированный документ config.json.
     *
     * Возвращает пустой массив, если файл не существует.
     *
     * @return array<string, array{patterns: string[], templateName: string}>
     * @throws \RuntimeException Если файл содержит невалидный JSON.
     */
    private function readAllData(): array
    {
        if (!$this->filesystem->exists($this->configPath)) {
            return [];
        }

        $json = $this->filesystem->getContents($this->configPath);
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid JSON in config file: {$this->configPath}", 0, $e);
        }
    }
}
