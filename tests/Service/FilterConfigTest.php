<?php
declare(strict_types=1);

namespace Webcomp\ReleaseBuilder\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\Service\FilterConfig;

class FilterConfigTest extends TestCase
{
    private FilesystemInterface&MockObject $fs;
    private FilterConfig $config;
    private string $configPath = '/upload/release-builder/config.json';

    protected function setUp(): void
    {
        $this->fs     = $this->createMock(FilesystemInterface::class);
        $this->config = new FilterConfig($this->fs, '/upload/release-builder');
    }

    // --- getPatterns ---

    public function testGetPatternsReturnsEmptyArrayWhenConfigDoesNotExist(): void
    {
        $this->fs->method('exists')->willReturn(false);

        $this->assertSame([], $this->config->getPatterns('webcomp.market'));
    }

    public function testGetPatternsReturnsSavedPatterns(): void
    {
        $json = json_encode(['webcomp.market' => ['patterns' => ['*.log', 'node_modules'], 'templateName' => '']]);
        $this->fs->method('exists')->willReturn(true);
        $this->fs->method('getContents')->willReturn($json);

        $this->assertSame(['*.log', 'node_modules'], $this->config->getPatterns('webcomp.market'));
    }

    public function testGetPatternsReturnsEmptyArrayForUnknownModule(): void
    {
        $json = json_encode(['webcomp.market' => ['patterns' => ['*.log'], 'templateName' => '']]);
        $this->fs->method('exists')->willReturn(true);
        $this->fs->method('getContents')->willReturn($json);

        $this->assertSame([], $this->config->getPatterns('webcomp.other'));
    }

    // --- getTemplateName ---

    public function testGetTemplateNameReturnsEmptyStringWhenNoConfig(): void
    {
        $this->fs->method('exists')->willReturn(false);

        $this->assertSame('', $this->config->getTemplateName('webcomp.market'));
    }

    public function testGetTemplateNameReturnsSavedName(): void
    {
        $json = json_encode(['webcomp.market' => ['patterns' => [], 'templateName' => 'webcomp_yellow']]);
        $this->fs->method('exists')->willReturn(true);
        $this->fs->method('getContents')->willReturn($json);

        $this->assertSame('webcomp_yellow', $this->config->getTemplateName('webcomp.market'));
    }

    // --- savePatterns ---

    public function testSavePatternsWritesCorrectJson(): void
    {
        $this->fs->method('exists')->willReturn(false);

        $written = null;
        $this->fs->expects($this->once())
            ->method('putContents')
            ->with($this->configPath, $this->callback(function (string $json) use (&$written) {
                $written = json_decode($json, true);
                return true;
            }));

        $this->config->savePatterns('webcomp.market', ['*.log', 'node_modules']);

        $this->assertSame(['*.log', 'node_modules'], $written['webcomp.market']['patterns']);
    }

    public function testSavePatternsPreservesExistingTemplateName(): void
    {
        $json = json_encode(['webcomp.market' => ['patterns' => [], 'templateName' => 'webcomp_yellow']]);
        $this->fs->method('exists')->willReturn(true);
        $this->fs->method('getContents')->willReturn($json);

        $written = null;
        $this->fs->expects($this->once())
            ->method('putContents')
            ->with($this->configPath, $this->callback(function (string $j) use (&$written) {
                $written = json_decode($j, true);
                return true;
            }));

        $this->config->savePatterns('webcomp.market', ['*.log']);

        $this->assertSame('webcomp_yellow', $written['webcomp.market']['templateName']);
        $this->assertSame(['*.log'], $written['webcomp.market']['patterns']);
    }

    // --- saveTemplateName ---

    public function testSaveTemplateNamePreservesExistingPatterns(): void
    {
        $json = json_encode(['webcomp.market' => ['patterns' => ['*.log'], 'templateName' => '']]);
        $this->fs->method('exists')->willReturn(true);
        $this->fs->method('getContents')->willReturn($json);

        $written = null;
        $this->fs->expects($this->once())
            ->method('putContents')
            ->with($this->configPath, $this->callback(function (string $j) use (&$written) {
                $written = json_decode($j, true);
                return true;
            }));

        $this->config->saveTemplateName('webcomp.market', 'webcomp_yellow');

        $this->assertSame(['*.log'], $written['webcomp.market']['patterns']);
        $this->assertSame('webcomp_yellow', $written['webcomp.market']['templateName']);
    }

    // --- Invalid JSON ---

    public function testThrowsRuntimeExceptionOnInvalidJson(): void
    {
        $this->fs->method('exists')->willReturn(true);
        $this->fs->method('getContents')->willReturn('{invalid json}');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->config->getPatterns('webcomp.market');
    }
}
