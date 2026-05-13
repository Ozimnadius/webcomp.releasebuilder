<?php

namespace Webcomp\ReleaseBuilder\Enum;

/**
 * Различает обычный модуль Bitrix и шаблонное решение.
 *
 * Тип определяет, какие исходные директории сканирует FileScanner
 * и какие пути назначения формирует PathMapper.
 */
enum ModuleType: string
{
    /** Обычный модуль: сканируется только bitrix/modules/{module}/. */
    case REGULAR_MODULE = 'regular';

    /**
     * Шаблонное решение: помимо директории модуля, сканируются также компоненты,
     * JS, CSS, изображения, шаблоны и файлы публичного корня сайта.
     */
    case TEMPLATE_SOLUTION = 'template';
}
