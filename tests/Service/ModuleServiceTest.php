<?php
declare(strict_types=1);

namespace Webcomp\ReleaseBuilder\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webcomp\ReleaseBuilder\Contract\FilesystemInterface;
use Webcomp\ReleaseBuilder\Enum\ModuleType;
use Webcomp\ReleaseBuilder\Service\ModuleService;

class ModuleServiceTest extends TestCase
{
    private FilesystemInterface&MockObject $fs;
    private ModuleService $service;
    private string $docRoot = '/var/www';

    protected function setUp(): void
    {
        $this->fs      = $this->createMock(FilesystemInterface::class);
        $this->service = new ModuleService($this->fs, $this->docRoot);
    }

    // --- getAvailableModules ---

    public function testGetAvailableModulesReturnsOnlyVendorDirs(): void
    {
        $this->fs->method('scanDir')
            ->willReturn(['.', '..', 'main', 'iblock', 'webcomp.market', 'bitrix.sitecorporate', 'notadir']);

        $this->fs->method('isDir')->willReturnCallback(
            fn(string $path) => !str_ends_with($path, 'notadir/')
        );

        $result = $this->service->getAvailableModules();

        $this->assertSame(['webcomp.market', 'bitrix.sitecorporate'], $result);
    }

    // --- getModuleVersion ---

    public function testGetModuleVersionWithDoubleQuotes(): void
    {
        $path    = '/var/www/bitrix/modules/webcomp.market/install/version.php';
        $content = '<?php $arModuleVersion = ["VERSION" => "1.1.16", "VERSION_DATE" => "2026-05-06 17:17:00"]; ?>';

        $this->fs->method('exists')->with($path)->willReturn(true);
        $this->fs->method('getContents')->with($path)->willReturn($content);

        $result = $this->service->getModuleVersion('webcomp.market');

        $this->assertSame('1.1.16', $result['VERSION']);
        $this->assertSame('2026-05-06 17:17:00', $result['VERSION_DATE']);
    }

    public function testGetModuleVersionWithSingleQuotes(): void
    {
        $path    = '/var/www/bitrix/modules/webcomp.releasebuilder/install/version.php';
        $content = "<?php \$arModuleVersion = ['VERSION' => '1.0.0', 'VERSION_DATE' => '2026-05-12 12:00:00']; ?>";

        $this->fs->method('exists')->with($path)->willReturn(true);
        $this->fs->method('getContents')->with($path)->willReturn($content);

        $result = $this->service->getModuleVersion('webcomp.releasebuilder');

        $this->assertSame('1.0.0', $result['VERSION']);
        $this->assertSame('2026-05-12 12:00:00', $result['VERSION_DATE']);
    }

    public function testGetModuleVersionThrowsWhenFileNotFound(): void
    {
        $this->fs->method('exists')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('version.php not found');

        $this->service->getModuleVersion('webcomp.market');
    }

    public function testGetModuleVersionThrowsWhenVersionUnparseable(): void
    {
        $this->fs->method('exists')->willReturn(true);
        $this->fs->method('getContents')->willReturn('<?php // no version here ?>');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse VERSION');

        $this->service->getModuleVersion('webcomp.market');
    }

    public function testGetModuleVersionReturnsEmptyDateWhenMissing(): void
    {
        $path    = '/var/www/bitrix/modules/webcomp.market/install/version.php';
        $content = '<?php $arModuleVersion = ["VERSION" => "1.0.0"]; ?>';

        $this->fs->method('exists')->with($path)->willReturn(true);
        $this->fs->method('getContents')->with($path)->willReturn($content);

        $result = $this->service->getModuleVersion('webcomp.market');

        $this->assertSame('1.0.0', $result['VERSION']);
        $this->assertSame('', $result['VERSION_DATE']);
    }

    // --- detectModuleType ---

    public function testDetectModuleTypeReturnsTemplateSolutionWhenWizardsDirExists(): void
    {
        $this->fs->method('isDir')
            ->with('/var/www/bitrix/modules/webcomp.market/install/wizards/')
            ->willReturn(true);

        $this->assertSame(ModuleType::TEMPLATE_SOLUTION, $this->service->detectModuleType('webcomp.market'));
    }

    public function testDetectModuleTypeReturnsRegularModuleWhenNoWizardsDir(): void
    {
        $this->fs->method('isDir')
            ->with('/var/www/bitrix/modules/webcomp.releasebuilder/install/wizards/')
            ->willReturn(false);

        $this->assertSame(ModuleType::REGULAR_MODULE, $this->service->detectModuleType('webcomp.releasebuilder'));
    }
}
