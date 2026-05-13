<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/webcomp.releasebuilder/prolog.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle(Loc::getMessage('WEBCOMP_RELEASEBUILDER_OPTIONS_TITLE'));

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';
?>

<p><?= Loc::getMessage('WEBCOMP_RELEASEBUILDER_OPTIONS_DESCRIPTION') ?></p>
<p>
    <a href="/bitrix/admin/webcomp_releasebuilder.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn">
        <?= Loc::getMessage('WEBCOMP_RELEASEBUILDER_OPTIONS_OPEN_TOOL') ?>
    </a>
</p>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
