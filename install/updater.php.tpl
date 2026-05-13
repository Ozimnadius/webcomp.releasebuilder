<?
if (IsModuleInstalled('{{MODULE}}')) {

    // Копировать компоненты
    if (is_dir(dirname(__FILE__) . '/install/components'))
        $updater->CopyFiles("install/components", "components/");

    // Копировать js
    if (is_dir(dirname(__FILE__) . '/install/js'))
        $updater->CopyFiles("install/js", "js/");

    // Копировать css
    if (is_dir(dirname(__FILE__) . '/install/css'))
        $updater->CopyFiles("install/css", "css/");

    // Копировать images
    if (is_dir(dirname(__FILE__) . '/install/images'))
        $updater->CopyFiles("install/images", "images/");

    // Копировать шаблон
    if (is_dir(dirname(__FILE__) . '/install/wizards/{{VENDOR}}/{{NAME}}/site/templates'))
        $updater->CopyFiles("install/wizards/{{VENDOR}}/{{NAME}}/site/templates/{{TEMPLATE_NAME}}", "templates/{{TEMPLATE_NAME}}/");

    // Копировать корневые файлы
    if (is_dir(dirname(__FILE__) . '/install/wizards/{{VENDOR}}/{{NAME}}/site/public/ru'))
        $updater->CopyFiles("install/wizards/{{VENDOR}}/{{NAME}}/site/public/ru", "../");
}
?>
