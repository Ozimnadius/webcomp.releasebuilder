<?php

namespace Webcomp\ReleaseBuilder\Contract;

/**
 * Абстрагирует создание ZIP-архивов для подмены реализации в тестах.
 *
 * Порядок вызовов: create() один раз, затем addFile() для каждого файла, затем close().
 */
interface ArchiveInterface
{
    /**
     * Создаёт (или перезаписывает) ZIP-архив по указанному пути.
     *
     * @param string $path Абсолютный путь, по которому будет создан архив.
     * @throws \RuntimeException Если архив не удалось открыть или создать.
     */
    public function create(string $path): void;

    /**
     * Добавляет файл с диска в открытый архив под указанным именем записи.
     *
     * @param string $filePath  Абсолютный путь к файлу на диске.
     * @param string $entryName Путь внутри архива (например, '1.1.18/lib/MyClass.php').
     * @throws \RuntimeException Если файл не удалось добавить в архив.
     */
    public function addFile(string $filePath, string $entryName): void;

    /**
     * Завершает работу с архивом и записывает все данные на диск.
     */
    public function close(): void;
}
