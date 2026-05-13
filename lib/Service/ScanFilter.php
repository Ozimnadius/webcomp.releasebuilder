<?php

namespace Webcomp\ReleaseBuilder\Service;

/**
 * Решает, нужно ли исключить запись файловой системы из сканирования.
 *
 * Правила исключения применяются последовательно:
 *   1. Правило точки: любая запись, имя которой начинается с «.», всегда пропускается (.git, .idea, .osp и т.д.).
 *   2. Публичный чёрный список: директории верхнего уровня, не входящие в релиз (bitrix, upload и др.),
 *      пропускаются при $applyPublicBlacklist = true (только для сканирования корня сайта).
 *   3. Пользовательские паттерны: glob-паттерны (fnmatch) и регулярные выражения (preg_match).
 *      Паттерны, начинающиеся с «/» или «~», считаются регулярными выражениями; остальные — glob.
 */
class ScanFilter
{
    /**
     * Директории верхнего уровня в корне сайта, которые никогда не входят в релиз.
     */
    private const PUBLIC_BLACKLIST = [
        'bitrix', 'upload', 'local', 'updater', 'release-builder',
    ];

    /**
     * @param string[] $userPatterns Паттерны исключений, настроенные пользователем для модуля.
     *                               Поддерживает glob (например, '*.log') и regex (например, '/\.cache$/').
     */
    public function __construct(
        private readonly array $userPatterns = [],
    ) {}

    /**
     * Возвращает true, если указанную запись файловой системы нужно исключить из сканирования.
     *
     * @param string $entry               Имя файла или директории (не полный путь).
     * @param bool   $applyPublicBlacklist Применять ли чёрный список корня сайта.
     *                                    Передавайте true только при сканировании корня сайта напрямую.
     * @return bool true — запись нужно пропустить; false — включить в обработку.
     */
    public function shouldSkip(string $entry, bool $applyPublicBlacklist = false): bool
    {
        if (str_starts_with($entry, '.')) {
            return true;
        }

        if ($applyPublicBlacklist && in_array($entry, self::PUBLIC_BLACKLIST, true)) {
            return true;
        }

        foreach ($this->userPatterns as $pattern) {
            if (str_starts_with($pattern, '/') || str_starts_with($pattern, '~')) {
                if (preg_match($pattern, $entry) === 1) {
                    return true;
                }
            } else {
                if (fnmatch($pattern, $entry)) {
                    return true;
                }
            }
        }

        return false;
    }
}
