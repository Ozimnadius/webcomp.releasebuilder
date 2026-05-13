<?php

namespace Webcomp\ReleaseBuilder\Contract;

/**
 * Абстрагирует операции с файловой системой для тестирования без реального диска.
 *
 * Все методы, изменяющие файловую систему, бросают \RuntimeException при ошибке.
 * Реализации должны быть полностью взаимозаменяемы с LocalFilesystem.
 */
interface FilesystemInterface
{
    /**
     * Проверяет, существует ли файл или директория по указанному пути.
     *
     * @param string $path Абсолютный путь для проверки.
     * @return bool true, если путь существует; false — иначе.
     */
    public function exists(string $path): bool;

    /**
     * Проверяет, является ли указанный путь директорией.
     *
     * @param string $path Абсолютный путь для проверки.
     * @return bool true, если путь — директория; false, если это файл или путь не существует.
     */
    public function isDir(string $path): bool;

    /**
     * Создаёт директорию по указанному пути.
     *
     * @param string $path      Абсолютный путь создаваемой директории.
     * @param int    $mode      Права доступа (восьмеричное число), например 0775.
     * @param bool   $recursive Создавать ли промежуточные директории.
     * @throws \RuntimeException Если директорию не удалось создать.
     */
    public function makeDir(string $path, int $mode = 0775, bool $recursive = true): void;

    /**
     * Копирует файл из одного места в другое.
     *
     * @param string $from Абсолютный путь источника.
     * @param string $to   Абсолютный путь назначения.
     * @throws \RuntimeException Если операция копирования завершилась ошибкой.
     */
    public function copy(string $from, string $to): void;

    /**
     * Записывает строку в файл, заменяя существующее содержимое.
     *
     * @param string $path     Абсолютный путь к целевому файлу.
     * @param string $contents Содержимое для записи.
     * @throws \RuntimeException Если файл не удалось записать.
     */
    public function putContents(string $path, string $contents): void;

    /**
     * Читает всё содержимое файла в строку.
     *
     * @param string $path Абсолютный путь к файлу.
     * @return string Содержимое файла.
     * @throws \RuntimeException Если файл не удалось прочитать.
     */
    public function getContents(string $path): string;

    /**
     * Возвращает Unix-метку времени последнего изменения файла.
     *
     * @param string $path Абсолютный путь к файлу.
     * @return int Unix-timestamp, или 0, если время определить не удалось.
     */
    public function getModifiedTime(string $path): int;

    /**
     * Возвращает список записей (файлы и поддиректории) в указанной директории.
     *
     * Возвращаемый массив включает записи «.» и «..».
     *
     * @param string $path Абсолютный путь к директории.
     * @return string[] Список имён записей (не полные пути).
     * @throws \RuntimeException Если директорию не удалось просканировать.
     */
    public function scanDir(string $path): array;

    /**
     * Удаляет один файл.
     *
     * Ничего не делает, если файл не существует.
     *
     * @param string $path Абсолютный путь к удаляемому файлу.
     * @throws \RuntimeException Если файл существует, но удалить его не удалось.
     */
    public function delete(string $path): void;

    /**
     * Рекурсивно удаляет директорию со всем её содержимым.
     *
     * Ничего не делает, если путь не существует или не является директорией.
     *
     * @param string $path Абсолютный путь к удаляемой директории.
     * @throws \RuntimeException Если какой-либо файл или директорию не удалось удалить.
     */
    public function deleteDir(string $path): void;
}
