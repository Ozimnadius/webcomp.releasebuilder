<?php

namespace Webcomp\ReleaseBuilder\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Webcomp\ReleaseBuilder\DTO\ArchiveRequest;
use Webcomp\ReleaseBuilder\DTO\ScanRequest;
use Webcomp\ReleaseBuilder\Enum\ModuleType;
use Webcomp\ReleaseBuilder\Infrastructure\LocalFilesystem;
use Webcomp\ReleaseBuilder\Infrastructure\ZipArchiveAdapter;
use Webcomp\ReleaseBuilder\Service\ArchiveBuilder;
use Webcomp\ReleaseBuilder\Service\FileCopier;
use Webcomp\ReleaseBuilder\Service\FileScanner;
use Webcomp\ReleaseBuilder\Service\FilterConfig;
use Webcomp\ReleaseBuilder\Service\ModuleService;
use Webcomp\ReleaseBuilder\Service\PathMapper;
use Webcomp\ReleaseBuilder\Service\ScanFilter;

class ReleaseBuilder extends Controller
{
    private ?ModuleService $moduleService = null;
    private ?FilterConfig  $filterConfig  = null;

    protected function getDefaultPreFilters(): array
    {
        return [
            new ActionFilter\Authentication(),
            new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
            new ActionFilter\Csrf(),
        ];
    }

    /**
     * Возвращает VERSION и VERSION_DATE для указанного модуля.
     *
     * @param string $module Идентификатор модуля (например, 'webcomp.market').
     * @return array{VERSION: string, VERSION_DATE: string}|null Данные версии или null при ошибке.
     */
    public function versionAction(string $module): ?array
    {
        $service = $this->moduleService();

        if (!in_array($module, $service->getAvailableModules(), true)) {
            $this->addError(new Error("Неверный модуль: {$module}"));
            return null;
        }

        try {
            return $service->getModuleVersion($module);
        } catch (\RuntimeException $e) {
            $this->addError(new Error($e->getMessage()));
            return null;
        }
    }

    /**
     * Возвращает конфиг модуля: паттерны, имя шаблона, доступные шаблоны, тип модуля.
     *
     * @param string $module Идентификатор модуля.
     * @return array{patterns: string[], templateName: string, availableTemplates: string[], suggestedTemplate: string, moduleType: string}|null Конфиг или null при ошибке.
     */
    public function getConfigAction(string $module): ?array
    {
        $service = $this->moduleService();

        if (!in_array($module, $service->getAvailableModules(), true)) {
            $this->addError(new Error("Неверный модуль: {$module}"));
            return null;
        }

        $config    = $this->filterConfig();
        $templates = $service->getAvailableTemplates();

        return [
            'patterns'           => $config->getPatterns($module),
            'templateName'       => $config->getTemplateName($module),
            'availableTemplates' => $templates['all'],
            'suggestedTemplate'  => $templates['suggestedTemplate'],
            'moduleType'         => $service->detectModuleType($module)->value,
        ];
    }

    /**
     * Сохраняет паттерны и имя шаблона для модуля.
     *
     * @param string $module       Идентификатор модуля.
     * @param string $patterns     Паттерны исключений, по одному на строку (разделитель \n).
     * @param string $templateName Название шаблона; допустимые символы: [a-zA-Z0-9_\-.]. Пустая строка допустима.
     * @return bool|null true при успехе, null при ошибке валидации.
     */
    public function saveConfigAction(
        string $module,
        string $patterns     = '',
        string $templateName = '',
    ): ?bool {
        $service = $this->moduleService();

        if (!in_array($module, $service->getAvailableModules(), true)) {
            $this->addError(new Error("Неверный модуль: {$module}"));
            return null;
        }

        if ($templateName !== '' && !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $templateName)) {
            $this->addError(new Error("Неверный формат имени шаблона: {$templateName}"));
            return null;
        }

        $patternList = array_values(array_filter(
            array_map('trim', explode("\n", $patterns)),
            fn(string $p) => $p !== ''
        ));

        $config = $this->filterConfig();
        $config->savePatterns($module, $patternList);
        $config->saveTemplateName($module, trim($templateName));

        return true;
    }

    /**
     * Сканирует файлы модуля и возвращает список изменённых путей.
     *
     * @param string $module       Идентификатор модуля.
     * @param string $newVersion   Целевая версия в формате `\d+\.\d+\.\d+` (например, '1.2.0').
     * @param string $version      Текущая установленная версия.
     * @param string $type         Тип модуля; допустимые значения определены в {@see ModuleType}.
     * @param string $date         Дата отсечки; файлы, изменённые позже, попадут в результат.
     * @param string $templateName Название шаблона (только для type='template').
     * @return string[]|null Список абсолютных путей изменённых файлов или null при ошибке.
     */
    public function searchAction(
        string $module,
        string $newVersion,
        string $version,
        string $type,
        string $date,
        string $templateName = '',
    ): ?array {
        $service = $this->moduleService();

        if (!in_array($module, $service->getAvailableModules(), true)) {
            $this->addError(new Error("Неверный модуль: {$module}"));
            return null;
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $newVersion)) {
            $this->addError(new Error("Неверный формат версии: {$newVersion}"));
            return null;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false || $timestamp === 0) {
            $this->addError(new Error("Неверный формат даты: {$date}"));
            return null;
        }

        try {
            $moduleType = ModuleType::from($type);
        } catch (\ValueError) {
            $this->addError(new Error("Неверный тип модуля: {$type}"));
            return null;
        }

        $docRoot    = Application::getDocumentRoot();
        $fs         = new LocalFilesystem();
        $pathMapper = new PathMapper($docRoot);
        $config     = $this->filterConfig();
        $filter     = new ScanFilter($config->getPatterns($module));
        $scanner    = new FileScanner($fs, $pathMapper, $filter);

        $scanRequest = new ScanRequest(
            module:         $module,
            type:           $moduleType,
            version:        $version,
            newVersion:     $newVersion,
            sinceTimestamp: $timestamp,
            templateName:   $templateName,
        );

        return $scanner->scan($scanRequest);
    }

    /**
     * Копирует выбранные файлы, создаёт архив, удаляет staging-директорию.
     *
     * @param string $module        Идентификатор модуля.
     * @param string $newVersion    Целевая версия в формате `\d+\.\d+\.\d+` (например, '1.2.0').
     * @param string $version       Текущая установленная версия.
     * @param string $type          Тип модуля; допустимые значения определены в {@see ModuleType}.
     * @param string $date          Дата отсечки для версии файлов.
     * @param string $description   Текст описания обновления (кодируется в Windows-1251).
     * @param string $templateName  Название шаблона (только для type='template').
     * @param string $selectedFiles JSON-массив абсолютных путей к выбранным файлам.
     * @return string|null Путь к архиву относительно docRoot или null при ошибке.
     */
    public function prepareArchiveAction(
        string $module,
        string $newVersion,
        string $version,
        string $type,
        string $date,
        string $description   = '',
        string $templateName  = '',
        string $selectedFiles = '[]',
    ): ?string {
        $service = $this->moduleService();

        if (!in_array($module, $service->getAvailableModules(), true)) {
            $this->addError(new Error("Неверный модуль: {$module}"));
            return null;
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $newVersion)) {
            $this->addError(new Error("Неверный формат версии: {$newVersion}"));
            return null;
        }

        $files = json_decode($selectedFiles, true);
        if (!is_array($files) || $files === []) {
            $this->addError(new Error('Не выбраны файлы'));
            return null;
        }

        $docRoot       = Application::getDocumentRoot();
        $normalDocRoot = str_replace('\\', '/', (string) realpath($docRoot));

        foreach ($files as $path) {
            if (!is_string($path)) {
                $this->addError(new Error('Неверный путь файла'));
                return null;
            }
            $real = realpath($path);
            if (!$real || !str_starts_with(str_replace('\\', '/', $real), $normalDocRoot . '/')) {
                $this->addError(new Error("Путь вне docRoot: {$path}"));
                return null;
            }
        }

        $timestamp = strtotime($date);
        if ($timestamp === false || $timestamp === 0) {
            $this->addError(new Error("Неверный формат даты: {$date}"));
            return null;
        }

        try {
            $moduleType = ModuleType::from($type);
        } catch (\ValueError) {
            $this->addError(new Error("Неверный тип модуля: {$type}"));
            return null;
        }

        $fs         = new LocalFilesystem();
        $pathMapper = new PathMapper($docRoot);
        $fileCopier = new FileCopier($fs, $pathMapper, $docRoot);

        $scanRequest = new ScanRequest(
            module:         $module,
            type:           $moduleType,
            version:        $version,
            newVersion:     $newVersion,
            sinceTimestamp: $timestamp,
            templateName:   $templateName,
        );

        $stagingDir = $docRoot . '/release-builder/' . $newVersion . '/';

        // Удаляем старые архивы этого модуля
        $uploadDir = $docRoot . '/upload/release-builder/';
        if ($fs->isDir($uploadDir)) {
            foreach ($fs->scanDir($uploadDir) as $entry) {
                if (str_starts_with($entry, $module . '-') && str_ends_with($entry, '.zip')) {
                    $fs->delete($uploadDir . $entry);
                }
            }
        }

        try {
            $fileCopier->copy($files, $scanRequest);
            $fileCopier->writeVersionFile($newVersion);

            $tplPath        = $docRoot . '/bitrix/modules/webcomp.releasebuilder/install/updater.php.tpl';
            $archiveBuilder = new ArchiveBuilder(
                new ZipArchiveAdapter(),
                $fs,
                $docRoot,
                $tplPath,
            );

            $config        = $this->filterConfig();
            $savedTemplate = $config->getTemplateName($module);
            $archiveRequest = new ArchiveRequest(
                version:      $newVersion,
                description:  $description,
                module:       $module,
                templateName: $templateName ?: $savedTemplate,
            );

            $archivePath = $archiveBuilder->build($archiveRequest);
        } catch (\Throwable $e) {
            $fs->deleteDir($stagingDir);
            $this->addError(new Error('Ошибка сборки архива: ' . $e->getMessage()));
            return null;
        }

        $fs->deleteDir($stagingDir);

        return str_replace($docRoot, '', $archivePath);
    }

    // -------------------------------------------------------------------------

    /**
     * Возвращает (или создаёт) единственный экземпляр ModuleService для текущего запроса.
     *
     * @return ModuleService
     */
    private function moduleService(): ModuleService
    {
        return $this->moduleService ??= new ModuleService(
            new LocalFilesystem(),
            Application::getDocumentRoot(),
        );
    }

    /**
     * Возвращает (или создаёт) единственный экземпляр FilterConfig для текущего запроса.
     *
     * @return FilterConfig
     */
    private function filterConfig(): FilterConfig
    {
        return $this->filterConfig ??= new FilterConfig(
            new LocalFilesystem(),
            Application::getDocumentRoot() . '/upload/release-builder',
        );
    }
}
