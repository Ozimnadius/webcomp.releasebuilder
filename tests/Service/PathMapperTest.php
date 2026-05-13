<?php
declare(strict_types=1);

namespace Webcomp\ReleaseBuilder\Tests\Service;

use PHPUnit\Framework\TestCase;
use Webcomp\ReleaseBuilder\DTO\ScanRequest;
use Webcomp\ReleaseBuilder\Enum\ModuleType;
use Webcomp\ReleaseBuilder\Service\PathMapper;

class PathMapperTest extends TestCase
{
    private PathMapper $mapper;
    private string $docRoot = '/var/www';

    protected function setUp(): void
    {
        $this->mapper = new PathMapper($this->docRoot);
    }

    private function makeRequest(
        ModuleType $type,
        string $module = 'webcomp.market',
        string $newVersion = '1.1.18',
        string $templateName = '',
    ): ScanRequest {
        return new ScanRequest(
            module:         $module,
            type:           $type,
            version:        '1.1.17',
            newVersion:     $newVersion,
            sinceTimestamp: 0,
            templateName:   $templateName,
        );
    }

    // --- REGULAR_MODULE ---

    public function testBuildMapRegularModuleContainsOnlyModuleDir(): void
    {
        $request = $this->makeRequest(ModuleType::REGULAR_MODULE);
        $map = $this->mapper->buildMap($request);

        $this->assertCount(1, $map);
        $this->assertArrayHasKey('/var/www/bitrix/modules/webcomp.market/', $map);
        $this->assertSame(
            '/var/www/release-builder/1.1.18/',
            $map['/var/www/bitrix/modules/webcomp.market/']
        );
    }

    // --- TEMPLATE_SOLUTION ---

    public function testBuildMapTemplateSolutionIncludesExtraDirs(): void
    {
        $request = $this->makeRequest(ModuleType::TEMPLATE_SOLUTION);
        $map = $this->mapper->buildMap($request);

        $this->assertArrayHasKey('/var/www/bitrix/modules/webcomp.market/', $map);
        $this->assertArrayHasKey('/var/www/bitrix/components/webcomp/', $map);
        $this->assertArrayHasKey('/var/www/bitrix/js/webcomp.market/', $map);
        $this->assertArrayHasKey('/var/www/bitrix/css/webcomp.market/', $map);
        $this->assertArrayHasKey('/var/www/bitrix/images/webcomp.market/', $map);
        $this->assertArrayHasKey('/var/www/local/templates/', $map);
        $this->assertArrayHasKey('/var/www/', $map);
    }

    public function testBuildMapTemplateSolutionWithTemplateNameAddsEntry(): void
    {
        $request = $this->makeRequest(ModuleType::TEMPLATE_SOLUTION, templateName: 'webcomp_yellow');
        $map = $this->mapper->buildMap($request);

        $this->assertArrayHasKey('/var/www/bitrix/templates/webcomp_yellow/', $map);
    }

    public function testBuildMapTemplateSolutionWithoutTemplateNameHasNoTemplateEntry(): void
    {
        $request = $this->makeRequest(ModuleType::TEMPLATE_SOLUTION, templateName: '');
        $map = $this->mapper->buildMap($request);

        $templateKeys = array_filter(array_keys($map), fn($k) => str_contains($k, '/bitrix/templates/'));
        $this->assertEmpty($templateKeys);
    }

    // --- resolveDestination ---

    public function testResolveDestinationMapsCorrectly(): void
    {
        $map = [
            '/var/www/bitrix/modules/webcomp.market/' => '/var/www/release-builder/1.1.18/',
        ];

        $result = $this->mapper->resolveDestination(
            '/var/www/bitrix/modules/webcomp.market/install/version.php',
            $map
        );

        $this->assertSame('/var/www/release-builder/1.1.18/install/version.php', $result);
    }

    public function testResolveDestinationPrefersLongerPrefix(): void
    {
        $map = [
            '/var/www/'                                => '/dest/short/',
            '/var/www/bitrix/modules/webcomp.market/' => '/dest/long/',
        ];

        $result = $this->mapper->resolveDestination(
            '/var/www/bitrix/modules/webcomp.market/index.php',
            $map
        );

        $this->assertSame('/dest/long/index.php', $result);
    }

    public function testResolveDestinationThrowsForUnmappedPath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot map path');

        $this->mapper->resolveDestination('/some/random/path.php', []);
    }
}
