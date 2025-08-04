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

    public function testAutoRegistrationSupportedConstantExists(): void
    {
        $this->assertTrue(defined('EdgeBinder\EdgeBinder::AUTO_REGISTRATION_SUPPORTED'));
        $this->assertIsBool(EdgeBinder::AUTO_REGISTRATION_SUPPORTED);
    }

    public function testAutoRegistrationSupportedIsTrue(): void
    {
        // Auto-registration should be supported in this version
        $this->assertTrue(EdgeBinder::AUTO_REGISTRATION_SUPPORTED);
    }

    public function testVersionCompatibilityCheck(): void
    {
        // Test that version can be used for compatibility checks
        $currentVersion = EdgeBinder::VERSION;
        $minRequiredVersion = '2.0.0';

        $this->assertTrue(
            version_compare($currentVersion, $minRequiredVersion, '>='),
            "Current version {$currentVersion} should be >= {$minRequiredVersion}"
        );
    }

    public function testVersionConstantsAreAccessible(): void
    {
        // Ensure constants can be accessed statically
        $version = EdgeBinder::VERSION;
        $autoRegSupported = EdgeBinder::AUTO_REGISTRATION_SUPPORTED;

        $this->assertIsString($version);
        $this->assertIsBool($autoRegSupported);
    }
}
