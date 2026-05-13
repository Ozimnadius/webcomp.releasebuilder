<?php
declare(strict_types=1);

namespace Webcomp\ReleaseBuilder\Tests\Service;

use PHPUnit\Framework\TestCase;
use Webcomp\ReleaseBuilder\Service\ScanFilter;

class ScanFilterTest extends TestCase
{
    // --- Dot rule ---

    public function testSkipsDotFiles(): void
    {
        $filter = new ScanFilter();
        $this->assertTrue($filter->shouldSkip('.git'));
        $this->assertTrue($filter->shouldSkip('.idea'));
        $this->assertTrue($filter->shouldSkip('.htaccess'));
    }

    public function testDoesNotSkipNormalEntries(): void
    {
        $filter = new ScanFilter();
        $this->assertFalse($filter->shouldSkip('src'));
        $this->assertFalse($filter->shouldSkip('index.php'));
        $this->assertFalse($filter->shouldSkip('README.md'));
    }

    // --- Public blacklist ---

    public function testSkipsBlacklistedDirWhenFlagIsTrue(): void
    {
        $filter = new ScanFilter();
        $this->assertTrue($filter->shouldSkip('bitrix', true));
        $this->assertTrue($filter->shouldSkip('upload', true));
        $this->assertTrue($filter->shouldSkip('local', true));
        $this->assertTrue($filter->shouldSkip('release-builder', true));
    }

    public function testDoesNotSkipBlacklistedDirWhenFlagIsFalse(): void
    {
        $filter = new ScanFilter();
        $this->assertFalse($filter->shouldSkip('bitrix', false));
        $this->assertFalse($filter->shouldSkip('upload', false));
    }

    // --- Glob patterns ---

    public function testSkipsEntryMatchingGlobPattern(): void
    {
        $filter = new ScanFilter(['*.log', 'node_modules']);
        $this->assertTrue($filter->shouldSkip('debug.log'));
        $this->assertTrue($filter->shouldSkip('error.log'));
        $this->assertTrue($filter->shouldSkip('node_modules'));
    }

    public function testDoesNotSkipEntryNotMatchingGlobPattern(): void
    {
        $filter = new ScanFilter(['*.log']);
        $this->assertFalse($filter->shouldSkip('index.php'));
        $this->assertFalse($filter->shouldSkip('style.css'));
    }

    // --- Regex patterns ---

    public function testSkipsEntryMatchingRegexPattern(): void
    {
        $filter = new ScanFilter(['/cache/']);
        $this->assertTrue($filter->shouldSkip('cache'));
    }

    public function testSkipsEntryMatchingTildeRegexPattern(): void
    {
        $filter = new ScanFilter(['~\.tmp$~']);
        $this->assertTrue($filter->shouldSkip('file.tmp'));
        $this->assertFalse($filter->shouldSkip('file.php'));
    }

    public function testDoesNotSkipEntryNotMatchingRegexPattern(): void
    {
        $filter = new ScanFilter(['/cache/']);
        $this->assertFalse($filter->shouldSkip('src'));
    }

    // --- Empty patterns ---

    public function testEmptyPatternsSkipNothing(): void
    {
        $filter = new ScanFilter([]);
        $this->assertFalse($filter->shouldSkip('anything'));
        $this->assertFalse($filter->shouldSkip('node_modules'));
    }
}
