<?php
declare(strict_types=1);

namespace Webcomp\ReleaseBuilder\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\DTO\ScanRequest;
use Webcomp\ReleaseBuilder\Enum\ModuleType;
use Webcomp\ReleaseBuilder\Service\FileScanner;
use Webcomp\ReleaseBuilder\Service\PathMapper;
use Webcomp\ReleaseBuilder\Service\ScanFilter;

class FileScannerTest extends TestCase
{
    private FilesystemInterface&MockObject $fs;
    private string $docRoot = '/var/www';

    protected function setUp(): void
    {
        $this->fs = $this->createMock(FilesystemInterface::class);
    }

    private function makeScanner(array $patterns = []): FileScanner
    {
        return new FileScanner(
            $this->fs,
            new PathMapper($this->docRoot),
            new ScanFilter($patterns),
        );
    }

    private function makeRequest(int $sinceTimestamp = 1000): ScanRequest
    {
        return new ScanRequest(
            module:         'webcomp.market',
            type:           ModuleType::REGULAR_MODULE,
            version:        '1.0.0',
            newVersion:     '1.0.1',
            sinceTimestamp: $sinceTimestamp,
        );
    }

    public function testScanReturnsFilesNewerThanTimestamp(): void
    {
        $moduleDir = '/var/www/bitrix/modules/webcomp.market/';

        $this->fs->method('isDir')->willReturnCallback(
            fn(string $path) => $path === $moduleDir
        );
        $this->fs->method('scanDir')->willReturn(['.', '..', 'index.php', 'old.php']);
        $this->fs->method('getModifiedTime')->willReturnMap([
            [$moduleDir . 'index.php', 2000],
            [$moduleDir . 'old.php',   500],
        ]);

        $result = $this->makeScanner()->scan($this->makeRequest(1000));

        $this->assertContains($moduleDir . 'index.php', $result);
        $this->assertNotContains($moduleDir . 'old.php', $result);
    }

    public function testScanSkipsDotFiles(): void
    {
        $moduleDir = '/var/www/bitrix/modules/webcomp.market/';

        $this->fs->method('isDir')->willReturnCallback(
            fn(string $path) => $path === $moduleDir
        );
        $this->fs->method('scanDir')->willReturn(['.git', 'index.php']);
        $this->fs->method('getModifiedTime')->willReturn(2000);

        $result = $this->makeScanner()->scan($this->makeRequest());

        $this->assertNotContains($moduleDir . '.git', $result);
        $this->assertContains($moduleDir . 'index.php', $result);
    }

    public function testScanSkipsNonExistingSourceDirs(): void
    {
        $this->fs->method('isDir')->willReturn(false);

        $result = $this->makeScanner()->scan($this->makeRequest());

        $this->assertSame([], $result);
    }

    public function testScanAppliesUserPatterns(): void
    {
        $moduleDir = '/var/www/bitrix/modules/webcomp.market/';

        $this->fs->method('isDir')->willReturnCallback(
            fn(string $path) => $path === $moduleDir
        );
        $this->fs->method('scanDir')->willReturn(['index.php', 'debug.log']);
        $this->fs->method('getModifiedTime')->willReturn(2000);

        $result = $this->makeScanner(['*.log'])->scan($this->makeRequest());

        $this->assertContains($moduleDir . 'index.php', $result);
        $this->assertNotContains($moduleDir . 'debug.log', $result);
    }

    public function testScanDeduplicatesResults(): void
    {
        $moduleDir = '/var/www/bitrix/modules/webcomp.market/';

        $this->fs->method('isDir')->willReturnCallback(
            fn(string $path) => $path === $moduleDir
        );
        $this->fs->method('scanDir')->willReturn(['index.php']);
        $this->fs->method('getModifiedTime')->willReturn(2000);

        $result = $this->makeScanner()->scan($this->makeRequest());

        $this->assertSame(array_unique($result), $result);
    }
}
