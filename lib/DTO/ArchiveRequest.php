<?php

namespace Webcomp\ReleaseBuilder\DTO;

/**
 * Хранит параметры, необходимые для сборки архива релиза.
 *
 * Неизменяемый объект-значение, передаётся в ArchiveBuilder::build().
 */
readonly class ArchiveRequest
{
    /**
     * @param string $version      Строка версии релиза (например, '1.1.18'). Используется как имя архива.
     * @param string $description  Текстовое описание обновления. Кодируется в Windows-1251 и записывается в description.ru.
     * @param string $module       Идентификатор модуля в формате vendor.name. Используется при рендере updater.php.tpl.
     * @param string $templateName Название директории шаблона Bitrix (например, 'webcomp_yellow'). Пустая строка для обычных модулей.
     */
    public function __construct(
        public string $version,
        public string $description,
        public string $module       = '',
        public string $templateName = '',
    ) {}
}
