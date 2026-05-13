<?php

namespace Webcomp\ReleaseBuilder\DTO;

use Webcomp\ReleaseBuilder\Enum\ModuleType;

/**
 * Хранит все параметры, необходимые для сканирования модуля на предмет изменённых файлов.
 *
 * Неизменяемый объект-значение, создаётся напрямую или через fromPost().
 */
class ScanRequest
{
    /**
     * @param string     $module         Идентификатор модуля в формате vendor.name (например, 'webcomp.market').
     * @param ModuleType $type           Тип модуля — определяет, какие исходные директории будут сканироваться.
     * @param string     $version        Текущая установленная версия модуля (например, '1.1.17').
     * @param string     $newVersion     Целевая версия создаваемого релиза (например, '1.1.18').
     * @param int        $sinceTimestamp Unix-timestamp; в сборку попадают только файлы, изменённые позже этой отметки.
     * @param string     $templateName   Имя шаблона сайта (например, 'webcomp_yellow'). Используется для сканирования
     *                                   bitrix/templates/{templateName}/ в шаблонных решениях.
     */
    public function __construct(
        public readonly string $module,
        public readonly ModuleType $type,
        public readonly string $version,
        public readonly string $newVersion,
        public readonly int $sinceTimestamp,
        public readonly string $templateName = '',
    ) {}

    /**
     * Создаёт ScanRequest из сырого массива $_POST.
     *
     * @internal Метод не используется в текущей реализации контроллера. Контроллер
     *           выполняет валидацию и создаёт ScanRequest вручную.
     * @param array $post Данные POST-запроса. Ожидаемые ключи: module, type, version, newVersion, date.
     * @return self
     * @throws \InvalidArgumentException Если значение 'date' не удаётся преобразовать в Unix-timestamp.
     */
    public static function fromPost(array $post): self
    {
        $timestamp = strtotime($post['date'] ?? '');

        if ($timestamp === false) {
            throw new \InvalidArgumentException('Invalid date format: ' . ($post['date'] ?? ''));
        }

        return new self(
            module:         $post['module'] ?? '',
            type:           ModuleType::from($post['type'] ?? 'regular'),
            version:        $post['version'] ?? '',
            newVersion:     $post['newVersion'] ?? '',
            sinceTimestamp: $timestamp,
            templateName:   $post['templateName'] ?? '',
        );
    }
}
