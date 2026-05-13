<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Инсталлятор модуля webcomp.releasebuilder.
 *
 * Регистрирует модуль в Bitrix, копирует файлы интерфейса в публичные директории
 * и заполняет поля вендорной конфигурации для Bitrix Marketplace.
 */
class webcomp_releasebuilder extends CModule
{
    public $MODULE_ID = 'webcomp.releasebuilder';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    const partnerName  = 'webcomp';
    const solutionName = 'releasebuilder';

    /**
     * Инициализирует поля модуля из языкового файла и `install/version.php`.
     */
    public function __construct()
    {
        $this->MODULE_NAME        = Loc::getMessage('WEBCOMP_RELEASEBUILDER_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('WEBCOMP_RELEASEBUILDER_MODULE_DESC');
        $this->PARTNER_NAME       = Loc::getMessage('WEBCOMP_RELEASEBUILDER_PARTNER_NAME');
        $this->PARTNER_URI        = Loc::getMessage('WEBCOMP_RELEASEBUILDER_PARTNER_URI');

        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
        $this->MODULE_GROUP_RIGHTS           = 'Y';

        include __DIR__ . '/version.php';

        if (isset($arModuleVersion['VERSION'])) {
            $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? null;
        }
    }

    /**
     * Устанавливает модуль: копирует файлы и регистрирует его в системе Bitrix.
     */
    public function DoInstall(): void
    {
        $this->InstallFiles();
        RegisterModule($this->MODULE_ID);
    }

    /**
     * Деинсталлирует модуль: удаляет файлы из публичных директорий и снимает регистрацию.
     */
    public function DoUninstall(): void
    {
        $this->UnInstallFiles();
        UnRegisterModule($this->MODULE_ID);
    }

    /**
     * Копирует admin-страницы и JS/CSS-ресурсы из install/ в публичные директории Bitrix.
     */
    public function InstallFiles(): void
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true);
        CopyDirFiles(__DIR__ . '/js',  $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/'  . $this->MODULE_ID, true);
        CopyDirFiles(__DIR__ . '/css', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID, true);
    }

    /**
     * Удаляет admin-страницы и JS/CSS-ресурсы из публичных директорий Bitrix.
     */
    public function UnInstallFiles(): void
    {
        DeleteDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
        DeleteDirFiles(__DIR__ . '/js',  $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/'  . $this->MODULE_ID);
        DeleteDirFiles(__DIR__ . '/css', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID);
    }
}
