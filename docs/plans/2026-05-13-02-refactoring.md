# ReleaseBuilder: Controller Refactoring

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Привести контроллер `lib/controller/releasebuilder.php` к production-качеству: убрать дублирование, сделать валидацию до I/O, обезопасить сборку архива от «мусора» на диске.

**Важно:** Выполнять только после того, как все тесты из плана `2026-05-13-01-tests.md` зелёные. После каждого шага рефакторинга — перезапускать `vendor/bin/phpunit --testdox` и убеждаться что ничего не сломалось.

**Файл для изменений:** `lib/controller/releasebuilder.php`

---

## Контекст: что не так с контроллером сейчас

### Проблема 1 — Дублирование фабрик (Task 10)

Сейчас в контроллере есть методы `makeModuleService()` и `makeFilterConfig()`. Каждый раз при вызове они создают **новый объект**, даже если в рамках одного HTTP-запроса он уже нужен. Это не вызывает ошибок, но избыточно и нарушает принцип единственного экземпляра на запрос.

```php
// Сейчас (плохо): новый объект на каждый вызов
private function makeModuleService(): ModuleService
{
    return new ModuleService(new LocalFilesystem(), Application::getDocumentRoot());
}
```

**Решение:** lazy-свойство через `??=` — создаём один раз, переиспользуем.

---

### Проблема 2 — Поздняя валидация типа модуля (Task 11)

`$type` приходит из браузера как строка (`'regular'` или `'template'`). `ModuleType::from($type)` может бросить `\ValueError` с некрасивым PHP-сообщением, если придёт мусор. Хуже — это происходит **после** того как уже началась I/O (чтение файловой системы).

```php
// Сейчас: ModuleType::from() вызывается глубоко внутри, без обработки ValueError
$scanRequest = new ScanRequest(module: $module, type: ModuleType::from($type), ...);
```

**Решение:** Валидировать `$type` в самом начале action-метода, до любого I/O. При невалидном значении — вернуть понятную ошибку пользователю.

---

### Проблема 3 — Staging-директория не чистится при ошибке (Task 12)

Когда `fileCopier->copy()` или `archiveBuilder->build()` бросают исключение, код прерывается. Временная директория `/release-builder/{version}/` остаётся на диске навсегда. При следующем запуске она будет мешать или содержать устаревшие файлы.

```php
// Сейчас: если build() бросит исключение, staging останется на диске
$fileCopier->copy($files, $scanRequest);
$archiveBuilder->build($archiveRequest);
$fs->deleteDir($stagingDir); // ← до сюда не дойдёт при исключении
```

**Решение:** Обернуть I/O-блок в `try/catch`, в `catch` — удалять staging и возвращать ошибку. После успешной сборки — удалять staging в любом случае.

---

### Проблема 4 — Нет валидации templateName (Task 13)

`$templateName` приходит из браузера как строка. Сейчас она сохраняется в конфиг без какой-либо проверки. Если туда попадёт `../../etc/passwd` или строка с пробелами — это не сломает систему (путь всё равно используется в read-only операциях), но это плохая практика для production-кода.

**Решение:** Допустимые символы — буквы, цифры, `_`, `-`, `.`. Пустая строка валидна (шаблон не задан). Всё остальное — ошибка.

---

## Tasks

### Task 10: Lazy-свойства вместо дублирующих фабрик

**Files:**
- Modify: `lib/controller/releasebuilder.php`

- [ ] **Step 1: Заменить фабричные методы на lazy-свойства**

В `lib/controller/releasebuilder.php` удалить методы `makeModuleService()` и `makeFilterConfig()`, добавить nullable-свойства и геттеры:

```php
// Добавить свойства (после объявления класса, перед getDefaultPreFilters):
private ?ModuleService $moduleService = null;
private ?FilterConfig  $filterConfig  = null;

// Заменить makeModuleService() на:
private function moduleService(): ModuleService
{
    return $this->moduleService ??= new ModuleService(
        new LocalFilesystem(),
        Application::getDocumentRoot(),
    );
}

// Заменить makeFilterConfig() на:
private function filterConfig(): FilterConfig
{
    return $this->filterConfig ??= new FilterConfig(
        new LocalFilesystem(),
        Application::getDocumentRoot() . '/upload/release-builder',
    );
}
```

- [ ] **Step 2: Обновить все вызовы в actions**

Заменить все `$this->makeModuleService()` → `$this->moduleService()` и `$this->makeFilterConfig()` → `$this->filterConfig()` во всех action-методах.

Проверка — в файле не должно остаться старых имён:

```bash
grep -n "makeModuleService\|makeFilterConfig" lib/controller/releasebuilder.php
```

Ожидаемый вывод: пустой (ни одного совпадения).

- [ ] **Step 3: Запустить тесты**

```bash
vendor/bin/phpunit --testdox
```

Ожидаемый вывод: те же результаты что на baseline из Task 9.

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: lazy-initialize ModuleService and FilterConfig in controller"
```

---

### Task 11: Ранняя валидация ModuleType

**Files:**
- Modify: `lib/controller/releasebuilder.php`

- [ ] **Step 1: Добавить валидацию в `searchAction`**

В начало `searchAction`, сразу после проверки `$newVersion`, добавить:

```php
try {
    $moduleType = ModuleType::from($type);
} catch (\ValueError) {
    $this->addError(new Error("Неверный тип модуля: {$type}"));
    return null;
}
```

Затем передавать `$moduleType` (уже провалидированный) в `ScanRequest`:

```php
$scanRequest = new ScanRequest(
    module:         $module,
    type:           $moduleType,
    version:        $version,
    newVersion:     $newVersion,
    sinceTimestamp: $timestamp,
    templateName:   $templateName,
);
```

- [ ] **Step 2: Добавить валидацию в `prepareArchiveAction`**

Аналогично — в начало `prepareArchiveAction`, до I/O операций:

```php
try {
    $moduleType = ModuleType::from($type);
} catch (\ValueError) {
    $this->addError(new Error("Неверный тип модуля: {$type}"));
    return null;
}
```

Использовать `$moduleType` в `ScanRequest`:

```php
$scanRequest = new ScanRequest(
    module:         $module,
    type:           $moduleType,
    version:        $version,
    newVersion:     $newVersion,
    sinceTimestamp: $timestamp,
    templateName:   $templateName,
);
```

- [ ] **Step 3: Запустить тесты**

```bash
vendor/bin/phpunit --testdox
```

Ожидаемый вывод: 0 failures.

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: validate ModuleType before I/O in search and prepareArchive actions"
```

---

### Task 12: try/catch в prepareArchiveAction со staging cleanup

**Files:**
- Modify: `lib/controller/releasebuilder.php`

- [ ] **Step 1: Обернуть I/O-блок в try/catch**

В `prepareArchiveAction`, перед `$fileCopier->copy(...)` добавить переменную для staging-пути, затем обернуть всё I/O в try/catch:

```php
$stagingDir = $docRoot . '/release-builder/' . $newVersion . '/';

// Удаляем старые архивы этого модуля (до try — если упадёт, staging ещё не начата)
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

// Удаляем staging после успешной сборки
$fs->deleteDir($stagingDir);

return str_replace($docRoot, '', $archivePath);
```

- [ ] **Step 2: Убедиться что старый `$fs->deleteDir` вне блока удалён**

```bash
grep -n "deleteDir" lib/controller/releasebuilder.php
```

Ожидаемый вывод: ровно две строки — одна в `catch`, одна после `try/catch`.

- [ ] **Step 3: Запустить тесты**

```bash
vendor/bin/phpunit --testdox
```

Ожидаемый вывод: 0 failures.

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: wrap copy/build in try/catch with staging cleanup on failure"
```

---

### Task 13: Валидация templateName в saveConfigAction

**Files:**
- Modify: `lib/controller/releasebuilder.php`

- [ ] **Step 1: Добавить валидацию в `saveConfigAction`**

После существующей проверки модуля (`!in_array($module, ...)`), добавить:

```php
if ($templateName !== '' && !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $templateName)) {
    $this->addError(new Error("Неверный формат имени шаблона: {$templateName}"));
    return null;
}
```

- [ ] **Step 2: Запустить тесты**

```bash
vendor/bin/phpunit --testdox
```

Ожидаемый вывод: 0 failures.

- [ ] **Step 3: Финальный запуск всего suite**

```bash
vendor/bin/phpunit --testdox 2>&1
```

Зафиксировать итог: X tests, X assertions, 0 failures, 0 errors.

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: validate templateName format in saveConfigAction"
```
