<?php
declare(strict_types=1);

namespace Webcomp\ReleaseBuilder\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\DTO\ScanRequest;
use Webcomp\ReleaseBuilder\Enum\ModuleType;
use Webcomp\ReleaseBuilder\Service\FileCopier;
use Webcomp\ReleaseBuilder\Service\PathMapper;

class FileCopierTest extends TestCase
{
    private FilesystemInterface&MockObject $fs;
    private FileCopier $copier;
    private string $docRoot = '/var/www';

    protected function setUp(): void
    {
        $this->fs     = $this->createMock(FilesystemInterface::class);
        $this->copier = new FileCopier($this->fs, new PathMapper($this->docRoot), $this->docRoot);
    }

    private function makeRequest(string $newVersion = '1.0.1'): ScanRequest
    {
        return new ScanRequest(
            module:         'webcomp.market',
            type:           ModuleType::REGULAR_MODULE,
            version:        '1.0.0',
            newVersion:     $newVersion,
            sinceTimestamp: 0,
        );
    }

    public function testCopyClearsStagingDirBeforeCopying(): void
    {
        $stagingDir = '/var/www/release-builder/1.0.1/';

        $this->fs->expects($this->once())
            ->method('deleteDir')
            ->with($stagingDir);

        $this->fs->method('makeDir');
        $this->fs->method('copy');

        $this->copier->copy([], $this->makeRequest());
    }

    public function testCopyCreatesDestDirAndCopiesFile(): void
    {
        $sourceFile = '/var/www/bitrix/modules/webcomp.market/install/version.php';
        $destFile   = '/var/www/release-builder/1.0.1/install/version.php';
        $destDir    = '/var/www/release-builder/1.0.1/install/';

        $this->fs->method('deleteDir');
        $this->fs->expects($this->once())->method('makeDir')->with($destDir, 0775, true);
        $this->fs->expects($this->once())->method('copy')->with($sourceFile, $destFile);

        $this->copier->copy([$sourceFile], $this->makeRequest());
    }

    public function testCopyReturnsDestinationPaths(): void
    {
        $sourceFile = '/var/www/bitrix/modules/webcomp.market/index.php';

        $this->fs->method('deleteDir');
        $this->fs->method('makeDir');
        $this->fs->method('copy');

        $result = $this->copier->copy([$sourceFile], $this->makeRequest());

        $this->assertContains('/var/www/release-builder/1.0.1/index.php', $result);
    }

    public function testWriteVersionFileWritesCorrectContent(): void
    {
        $versionDir = '/var/www/release-builder/1.0.1/install/';

        $this->fs->expects($this->once())->method('makeDir')->with($versionDir, 0775, true);

        $written = null;
        $this->fs->expects($this->once())
            ->method('putContents')
            ->with(
                $versionDir . 'version.php',
                $this->callback(function (string $content) use (&$written) {
                    $written = $content;
                    return true;
                })
            );

        $this->copier->writeVersionFile('1.0.1');

        $this->assertStringContainsString('"VERSION" => "1.0.1"', $written);
        $this->assertStringContainsString('"VERSION_DATE"', $written);
    }
}
