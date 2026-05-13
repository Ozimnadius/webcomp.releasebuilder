<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/webcomp.releasebuilder/prolog.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Webcomp\ReleaseBuilder\Infrastructure\LocalFilesystem;
use Webcomp\ReleaseBuilder\Service\ModuleService;

Loader::includeModule('webcomp.releasebuilder');
Loc::loadMessages(__FILE__);

$fs            = new LocalFilesystem();
$moduleService = new ModuleService($fs, $_SERVER['DOCUMENT_ROOT']);
$modules       = $moduleService->getAvailableModules();

$APPLICATION->SetTitle(Loc::getMessage('WEBCOMP_RELEASEBUILDER_PAGE_TITLE'));

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';
?>
<link rel="stylesheet" href="/bitrix/css/webcomp.releasebuilder/style.css">
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">

<div class="rb__container">

    <div class="rb__card">
        <div class="rb__card-body">
            <div class="rb__row">

                <div class="rb__col rb__col--4">
                    <label class="rb__label">Модуль</label>
                    <select class="rb__select" id="module" name="module" data-event="change.getModule">
                        <option value="">— выберите модуль —</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?= htmlspecialchars($module) ?>"><?= htmlspecialchars($module) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rb__col rb__col--4 rb__col--middle">
                    <label class="rb__label">Тип</label>
                    <div class="rb__radio-group">
                        <label class="rb__radio">
                            <input class="rb__radio-input" type="radio" name="type" id="typeRegular" value="regular" checked
                                   data-event="change.onTypeChange">
                            <span class="rb__radio-label">Обычный модуль</span>
                        </label>
                        <label class="rb__radio">
                            <input class="rb__radio-input" type="radio" name="type" id="typeTemplate" value="template"
                                   data-event="change.onTypeChange">
                            <span class="rb__radio-label">Шаблонное решение</span>
                        </label>
                    </div>
                </div>

                <div class="rb__col rb__col--2">
                    <label class="rb__label">Текущая версия</label>
                    <input type="text" class="rb__input" id="version" name="version" readonly>
                </div>

                <div class="rb__col rb__col--2">
                    <label class="rb__label">Новая версия</label>
                    <input type="text" class="rb__input" id="newVersion" name="newVersion" readonly>
                </div>

                <div class="rb__col rb__col--4">
                    <label class="rb__label">Дата последнего обновления</label>
                    <input type="datetime-local" class="rb__input" id="date" name="date">
                </div>

                <div class="rb__col rb__col--12">
                    <label class="rb__label">
                        Фильтры исключений
                        <small class="rb__label-hint">(один паттерн на строку)</small>
                    </label>
                    <textarea class="rb__textarea rb__textarea--mono" id="filterPatterns" name="filterPatterns"
                              rows="4" disabled
                              placeholder="*.log&#10;node_modules/&#10;.git/"></textarea>
                </div>

                <div class="rb__col rb__col--4 d-none" id="templateNameGroup">
                    <label class="rb__label">Название шаблона</label>
                    <input type="text" class="rb__input" id="templateName" name="templateName"
                           placeholder="webcomp_yellow" disabled list="templateSuggestions"
                           data-event="input.updateSearchButton">
                    <datalist id="templateSuggestions"></datalist>
                </div>

            </div>

            <div class="rb__actions">
                <button class="rb__btn rb__btn--primary" id="btnSearch" data-event="click.search" disabled>
                    Поиск
                </button>
                <button class="rb__btn rb__btn--success" id="btnArchive" data-event="click.prepareArchive" disabled>
                    Создать архив
                </button>
                <button class="rb__btn rb__btn--outline" id="btnSaveFilters" data-event="click.saveFilters" disabled>
                    Сохранить фильтры
                </button>
                <span id="filterSaveStatus" class="rb__status rb__status--success d-none">Сохранено ✓</span>
            </div>

            <div id="errorAlert" class="rb__alert rb__alert--danger d-none"></div>
        </div>
    </div>

    <div class="rb__files-card d-none" id="filesCard">
        <div class="rb__card-header">Найденные файлы</div>
        <table class="rb__table" id="filesTable">
            <thead>
                <tr>
                    <th class="rb__table-check">
                        <input class="rb__checkbox" type="checkbox" id="selectAllFiles"
                               data-event="change.onSelectAll" checked>
                    </th>
                    <th class="rb__table-num">#</th>
                    <th>Путь</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <textarea id="description" name="description" style="display:none"></textarea>

    <div class="rb__card">
        <div class="rb__card-header">Описание обновления</div>
        <div class="rb__card-body rb__card-body--flush">
            <div class="rb__editor" id="quillEditor"></div>
        </div>
    </div>

    <div id="downloadSection" class="rb__download d-none">
        <a id="downloadLink" href="#" class="rb__btn rb__btn--link">Скачать архив</a>
    </div>

</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="/bitrix/js/webcomp.releasebuilder/script.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
