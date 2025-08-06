<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit;

use EdgeBinder\EdgeBinder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EdgeBinder version constants.
 */
class EdgeBinderVersionTest extends TestCase
{
    public function testVersionConstantExists(): void
    {
        $this->assertTrue(defined('EdgeBinder\EdgeBinder::VERSION'));
        $this->assertIsString(EdgeBinder::VERSION);
        $this->assertNotEmpty(EdgeBinder::VERSION);
    }

    public function testVersionConstantFormat(): void
    {
        // Version should follow semantic versioning format (e.g., "2.1.0")
        $version = EdgeBinder::VERSION;
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function testVersionCompatibilityCheck(): void
    {
        // Test that version can be used for compatibility checks
        $currentVersion = EdgeBinder::VERSION;
        $minRequiredVersion = '0.2.0';

        $this->assertTrue(
            version_compare($currentVersion, $minRequiredVersion, '>='),
            "Current version {$currentVersion} should be >= {$minRequiredVersion}"
        );
    }

    public function testVersionConstantIsAccessible(): void
    {
        // Ensure version constant can be accessed statically
        $version = EdgeBinder::VERSION;

        $this->assertIsString($version);
    }
}
