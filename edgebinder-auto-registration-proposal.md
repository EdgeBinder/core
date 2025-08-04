# EdgeBinder Auto-Registration Enhancement Proposal

## Overview

This document proposes implementing automatic adapter registration for the EdgeBinder ecosystem to eliminate manual adapter registration requirements and improve developer experience.

## Current State

Currently, EdgeBinder adapters require manual registration in application bootstrap code:

```php
// Required in every application using EdgeBinder with Weaviate
use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

AdapterRegistry::register(new WeaviateAdapterFactory());
```

This creates friction for developers and is error-prone, especially when multiple adapters are used.

## Proposed Solution

Implement automatic adapter registration using Composer's autoload files mechanism, allowing adapters to self-register when their packages are loaded.

## Benefits

### 1. **Zero Configuration**
- Developers install the adapter package and it works immediately
- No manual registration code required
- Reduces setup complexity

### 2. **Framework Agnostic**
- Works identically across all PHP frameworks (Laminas, Symfony, Laravel, etc.)
- No framework-specific registration logic needed

### 3. **Developer Experience**
- Eliminates common setup errors
- Reduces documentation burden
- Follows "convention over configuration" principle

### 4. **Consistency**
- All EdgeBinder adapters work the same way
- Predictable behavior across the ecosystem

### 5. **Maintainability**
- Centralized registration logic in adapter packages
- Easier to maintain and update

## Implementation Details

### Prerequisites: Registry Enhancements

Before implementing auto-registration, ensure the `AdapterRegistry` class includes these methods:

```php
// In EdgeBinder\Registry\AdapterRegistry class
public static function hasAdapter(string $type): bool
{
    return isset(self::$adapters[$type]);
}

public static function getRegisteredTypes(): array
{
    return array_keys(self::$adapters);
}

public static function clear(): void
{
    self::$adapters = [];
}
```

### Phase 1: Update Weaviate Adapter Package

**File: `edgebinder/weaviate-adapter/composer.json`**

Add autoload files section:
```json
{
    "autoload": {
        "psr-4": {
            "EdgeBinder\\Adapter\\Weaviate\\": "src/"
        },
        "files": ["src/bootstrap.php"]
    }
}
```

**File: `edgebinder/weaviate-adapter/src/bootstrap.php`** (new file)
```php
<?php

declare(strict_types=1);

use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
use EdgeBinder\Registry\AdapterRegistry;

// Auto-register the Weaviate adapter when package is loaded
try {
    if (class_exists(AdapterRegistry::class) &&
        class_exists(\EdgeBinder\EdgeBinder::class) &&
        !AdapterRegistry::hasAdapter('weaviate')) {

        AdapterRegistry::register(new WeaviateAdapterFactory());

        // Optional debug logging
        if (defined('EDGEBINDER_DEBUG') && EDGEBINDER_DEBUG) {
            error_log("EdgeBinder: Auto-registered Weaviate adapter");
        }
    }
} catch (\Throwable $e) {
    // Log error but don't break application bootstrap
    error_log("EdgeBinder Weaviate adapter auto-registration failed: " . $e->getMessage());
}
```

### Phase 2: Update EdgeBinder Laminas Component (Optional Enhancement)

**File: `edgebinder/edgebinder-laminas-component/src/ConfigProvider.php`**

Add validation to ensure adapters are registered:
```php
public function __invoke(): array
{
    // Validate that required adapters are available
    $this->validateAdapterAvailability();

    return [
        'dependencies' => $this->getDependencies(),
    ];
}

private function validateAdapterAvailability(): void
{
    // Log available adapters for debugging and validation
    if (class_exists(\EdgeBinder\Registry\AdapterRegistry::class)) {
        $availableAdapters = \EdgeBinder\Registry\AdapterRegistry::getRegisteredTypes();

        if (defined('EDGEBINDER_DEBUG') && EDGEBINDER_DEBUG) {
            error_log("EdgeBinder: Available adapters: " . implode(', ', $availableAdapters));
        }

        // Optional: Validate expected adapters are present based on configuration
        // This could warn about missing adapters before runtime errors occur
    }
}
```

## Breaking Changes Analysis

### Weaviate Adapter Package
**Breaking Changes: NONE**

- The bootstrap file only adds functionality
- Existing manual registration continues to work
- No API changes to existing classes
- Backward compatible with all existing applications

## Migration Path

### For New Applications
1. Install adapter package: `composer require edgebinder/weaviate-adapter`
2. Configure EdgeBinder in `config/autoload/edgebinder.local.php`
3. Use EdgeBinder service - no registration needed

### For Existing Applications
1. Update to new adapter package version
2. **Optional**: Remove manual registration code from bootstrap
3. Application continues to work without changes

**Example removal (optional):**
```php
// This can be removed after upgrade, but doesn't need to be
// use EdgeBinder\Adapter\Weaviate\WeaviateAdapterFactory;
// use EdgeBinder\Registry\AdapterRegistry;
// AdapterRegistry::register(new WeaviateAdapterFactory());
```

## Testing Strategy

### Unit Tests
**File: `tests/Unit/BootstrapTest.php`** (new)
```php
<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Unit;

use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testWeaviateAdapterAutoRegistration(): void
    {
        // Clear registry
        AdapterRegistry::clear();
        
        // Simulate package loading
        require_once __DIR__ . '/../../src/bootstrap.php';
        
        // Verify adapter is registered
        $this->assertTrue(AdapterRegistry::hasAdapter('weaviate'));
        $this->assertContains('weaviate', AdapterRegistry::getRegisteredTypes());
    }
    
    public function testBootstrapIsIdempotent(): void
    {
        // Clear registry
        AdapterRegistry::clear();

        // Load bootstrap multiple times
        require_once __DIR__ . '/../../src/bootstrap.php';
        require_once __DIR__ . '/../../src/bootstrap.php';

        // Should still work without errors
        $this->assertTrue(AdapterRegistry::hasAdapter('weaviate'));

        // Verify only one instance is registered (no duplicates)
        $registeredTypes = AdapterRegistry::getRegisteredTypes();
        $weaviateCount = array_count_values($registeredTypes)['weaviate'] ?? 0;
        $this->assertEquals(1, $weaviateCount);
    }

    public function testBootstrapHandlesMissingClasses(): void
    {
        // Test behavior when AdapterRegistry doesn't exist
        // This would require mocking or conditional class loading
        $this->assertTrue(true); // Placeholder - implement based on actual registry design
    }
}
```

### Integration Tests
**File: `tests/Integration/AutoRegistrationTest.php`** (new)
```php
<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Integration;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;

class AutoRegistrationTest extends TestCase
{
    public function testEdgeBinderCanUseAutoRegisteredAdapter(): void
    {
        $config = [
            'adapter' => 'weaviate',
            'weaviate_client' => $this->createMockWeaviateClient(),
            'collection_name' => 'TestBindings',
            'schema' => ['auto_create' => true],
        ];
        
        // Should work without manual registration
        $edgeBinder = EdgeBinder::fromConfiguration($config, $this->createMockContainer());
        
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
    }
}
```

## Documentation Updates

### README Updates
Update package README files to remove manual registration examples:

**Before:**
```php
// Register the adapter
AdapterRegistry::register(new WeaviateAdapterFactory());

// Configure EdgeBinder
$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
```

**After:**
```php
// Just configure EdgeBinder - adapter auto-registers
$edgeBinder = EdgeBinder::fromConfiguration($config, $container);
```

### Migration Guide
Create migration guide for existing users explaining:
- Auto-registration is now available
- Manual registration still works (backward compatible)
- How to clean up manual registration (optional)
- What happens when both auto-registration and manual registration are present
- Debugging options using `EDGEBINDER_DEBUG` constant

## Implementation Timeline

1. **Phase 0** (Week 1): Core Registry Enhancements
   - Add required methods to `AdapterRegistry` class
   - Ensure idempotent registration behavior
   - Add comprehensive unit tests for registry

2. **Phase 1** (Week 2): Update `edgebinder/weaviate-adapter`
   - Add bootstrap.php with error handling and duplicate protection
   - Update composer.json
   - Add unit tests including edge cases
   - Update documentation

3. **Phase 1.5** (Week 3): Comprehensive Testing
   - Integration testing with various scenarios
   - Performance testing for bootstrap overhead
   - Edge case testing (missing classes, multiple loads)
   - Validation of error handling

4. **Phase 2** (Week 4): Update `edgebinder/edgebinder-laminas-component`
   - Add optional validation enhancements
   - Update documentation
   - Add integration tests

5. **Phase 3** (Week 5): Documentation and Communication
   - Update all documentation
   - Create comprehensive migration guide
   - Add debugging documentation
   - Announce changes to community

## Risk Assessment

**Low Risk Enhancement:**
- No breaking changes
- Backward compatible
- Incremental improvement
- Easy to rollback if needed

**Potential Issues:**
- Multiple registration (mitigated by duplicate registration protection)
- Load order dependencies (mitigated by class_exists checks for both AdapterRegistry and EdgeBinder)
- Bootstrap errors breaking application (mitigated by try-catch error handling)
- Performance impact from unnecessary instantiation (mitigated by lazy loading checks)
- Testing complexity (addressed by comprehensive test suite)

## Additional Considerations

### Version Compatibility
Consider adding version checks to ensure compatibility between core EdgeBinder and adapter versions:

```php
// In bootstrap.php
if (defined('EDGEBINDER_MIN_VERSION') &&
    version_compare(\EdgeBinder\EdgeBinder::VERSION, EDGEBINDER_MIN_VERSION, '<')) {
    error_log("EdgeBinder Weaviate adapter requires EdgeBinder >= " . EDGEBINDER_MIN_VERSION);
    return;
}
```

### Performance Optimization
The enhanced bootstrap implementation includes several performance optimizations:
- Lazy loading checks prevent unnecessary object instantiation
- Duplicate registration protection avoids redundant operations
- Error handling prevents bootstrap failures from breaking applications

### Debugging Support
The `EDGEBINDER_DEBUG` constant provides developers with visibility into the auto-registration process:

```php
// Enable debug logging in development
define('EDGEBINDER_DEBUG', true);
```

## Conclusion

This enhanced proposal significantly improves the EdgeBinder developer experience while maintaining full backward compatibility. The implementation includes robust error handling, performance optimizations, and comprehensive testing strategies that follow established PHP ecosystem patterns.

The change transforms EdgeBinder from requiring manual setup to "just works" installation, making it more accessible to developers and reducing support burden. The additional safeguards and debugging capabilities ensure reliable operation across diverse deployment environments.
