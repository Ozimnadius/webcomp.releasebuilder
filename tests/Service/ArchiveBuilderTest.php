<?php
declare(strict_types=1);

namespace Webcomp\ReleaseBuilder\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webcomp\ReleaseBuilder\Contract\ArchiveInterface;
use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\DTO\ArchiveRequest;
use Webcomp\ReleaseBuilder\Service\ArchiveBuilder;

class ArchiveBuilderTest extends TestCase
{
    private FilesystemInterface&MockObject $fs;
    private ArchiveInterface&MockObject $archive;
    private ArchiveBuilder $builder;
    private string $docRoot = '/var/www';
    private string $tplPath = '/var/www/tpl/updater.php.tpl';

    protected function setUp(): void
    {
        $this->fs      = $this->createMock(FilesystemInterface::class);
        $this->archive = $this->createMock(ArchiveInterface::class);
        $this->builder = new ArchiveBuilder(
            $this->archive,
            $this->fs,
            $this->docRoot,
            $this->tplPath,
        );
    }

    private function makeRequest(
        string $module = 'webcomp.market',
        string $templateName = '',
        string $description = 'Test description',
    ): ArchiveRequest {
        return new ArchiveRequest(
            version:      '1.1.18',
            description:  $description,
            module:       $module,
            templateName: $templateName,
        );
    }

    private function stubFsForBuild(array $dirEntries = []): void
    {
        $this->fs->method('makeDir');
        $this->fs->method('getContents')->with($this->tplPath)
            ->willReturn('MODULE={{MODULE}} VENDOR={{VENDOR}} NAME={{NAME}} TPL={{TEMPLATE_NAME}}');
        $this->fs->method('putContents');
        $this->fs->method('scanDir')->willReturn($dirEntries);
        $this->fs->method('isDir')->willReturn(false);
    }

    public function testBuildRendersUpdaterTemplateWithCorrectSubstitutions(): void
    {
        $this->stubFsForBuild();
        $this->archive->method('create');
        $this->archive->method('close');

        $capturedUpdater = null;
        $this->fs->expects($this->atLeast(1))
            ->method('putContents')
            ->willReturnCallback(function (string $path, string $content) use (&$capturedUpdater) {
                if (str_ends_with($path, 'updater.php')) {
                    $capturedUpdater = $content;
                }
            });

        $this->builder->build($this->makeRequest('webcomp.market', 'webcomp_yellow'));

        $this->assertSame(
            'MODULE=webcomp.market VENDOR=webcomp NAME=market TPL=webcomp_yellow',
            $capturedUpdater
        );
    }

    public function testBuildWritesDescriptionInWindows1251(): void
    {
        $this->stubFsForBuild();
        $this->archive->method('create');
        $this->archive->method('close');

        $capturedDescription = null;
        $this->fs->expects($this->atLeast(1))
            ->method('putContents')
            ->willReturnCallback(function (string $path, string $content) use (&$capturedDescription) {
                if (str_ends_with($path, 'description.ru')) {
                    $capturedDescription = $content;
                }
            });

        $this->builder->build($this->makeRequest(description: 'Описание обновления'));

        $this->assertNotNull($capturedDescription);
        $this->assertSame(
            iconv('utf-8', 'windows-1251', 'Описание обновления'),
            $capturedDescription
        );
    }

    public function testBuildCreatesAndClosesArchive(): void
    {
        $this->stubFsForBuild();

        $this->archive->expects($this->once())->method('create')
            ->with('/var/www/upload/release-builder/webcomp.market-1.1.18.zip');
        $this->archive->expects($this->once())->method('close');

        $this->builder->build($this->makeRequest());
    }

    public function testBuildAddsFilesToArchive(): void
    {
        $stagingDir = '/var/www/release-builder/1.1.18/';
        $this->fs->method('makeDir');
        $this->fs->method('getContents')->with($this->tplPath)->willReturn('tpl');
        $this->fs->method('putContents');
        $this->fs->method('scanDir')->willReturnCallback(function (string $dir) use ($stagingDir) {
            return $dir === $stagingDir ? ['updater.php'] : [];
        });
        $this->fs->method('isDir')->willReturn(false);

        $this->archive->method('create');
        $this->archive->method('close');
        $this->archive->expects($this->once())
            ->method('addFile')
            ->with($stagingDir . 'updater.php', '1.1.18/updater.php');

        $this->builder->build($this->makeRequest());
    }

    public function testBuildThrowsOnDescriptionEncodingFailure(): void
    {
        $invalidUtf8 = "\xFF\xFE invalid";

        $this->stubFsForBuild();

        // iconv() emits a PHP notice before returning false; suppress it so the
        // test focuses solely on the RuntimeException that ArchiveBuilder throws.
        set_error_handler(static fn() => true);
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to encode description to Windows-1251');

            $this->builder->build($this->makeRequest(description: $invalidUtf8));
        } finally {
            restore_error_handler();
        }
    }
}
