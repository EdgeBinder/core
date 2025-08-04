# EdgeBinder Core Auto-Registration Enhancement

## Overview

This document outlines the core EdgeBinder library changes required to support automatic adapter registration. These changes provide the foundational registry enhancements that enable adapters to self-register when their packages are loaded.

## Current State

The current `AdapterRegistry` class provides basic registration functionality but lacks methods needed for auto-registration features:
- No way to check if an adapter is already registered
- No way to query registered adapter types
- No way to clear registry for testing

## Proposed Changes

### Registry Method Enhancements

Add the following methods to the `EdgeBinder\Registry\AdapterRegistry` class:

```php
/**
 * Check if an adapter of the given type is already registered
 */
public static function hasAdapter(string $type): bool
{
    return isset(self::$adapters[$type]);
}

/**
 * Get all registered adapter types
 */
public static function getRegisteredTypes(): array
{
    return array_keys(self::$adapters);
}

/**
 * Clear all registered adapters (primarily for testing)
 */
public static function clear(): void
{
    self::$adapters = [];
}
```

### Registry Registration Enhancement

Ensure the `register()` method is idempotent to handle multiple registration attempts:

```php
public static function register(AdapterFactoryInterface $factory): void
{
    $type = $factory->getType();
    
    // Only register if not already present
    if (!self::hasAdapter($type)) {
        self::$adapters[$type] = $factory;
    }
}
```

## Benefits

### 1. **Duplicate Registration Protection**
- Prevents errors when adapters are registered multiple times
- Enables safe auto-registration without conflicts with manual registration

### 2. **Registry Introspection**
- Allows adapters and applications to query what's available
- Enables debugging and validation features

### 3. **Testing Support**
- `clear()` method enables clean test environments
- Supports comprehensive testing of registration scenarios

### 4. **Backward Compatibility**
- All existing functionality continues to work unchanged
- New methods are additive only

## Breaking Changes Analysis

**Breaking Changes: NONE**

- All new methods are static and additive
- Existing `register()` method behavior is preserved
- No changes to existing public APIs
- Fully backward compatible with all existing code

## Testing Strategy

### Unit Tests

**File: `tests/Unit/AdapterRegistryTest.php`** (enhanced)

```php
<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Registry;

use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Tests\Fixtures\MockAdapterFactory;
use PHPUnit\Framework\TestCase;

class AdapterRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        AdapterRegistry::clear();
    }

    public function testHasAdapterReturnsFalseForUnregisteredAdapter(): void
    {
        $this->assertFalse(AdapterRegistry::hasAdapter('nonexistent'));
    }

    public function testHasAdapterReturnsTrueForRegisteredAdapter(): void
    {
        $factory = new MockAdapterFactory('test');
        AdapterRegistry::register($factory);
        
        $this->assertTrue(AdapterRegistry::hasAdapter('test'));
    }

    public function testGetRegisteredTypesReturnsEmptyArrayInitially(): void
    {
        $this->assertEmpty(AdapterRegistry::getRegisteredTypes());
    }

    public function testGetRegisteredTypesReturnsRegisteredTypes(): void
    {
        $factory1 = new MockAdapterFactory('type1');
        $factory2 = new MockAdapterFactory('type2');
        
        AdapterRegistry::register($factory1);
        AdapterRegistry::register($factory2);
        
        $types = AdapterRegistry::getRegisteredTypes();
        $this->assertContains('type1', $types);
        $this->assertContains('type2', $types);
        $this->assertCount(2, $types);
    }

    public function testClearRemovesAllRegisteredAdapters(): void
    {
        $factory = new MockAdapterFactory('test');
        AdapterRegistry::register($factory);
        
        $this->assertTrue(AdapterRegistry::hasAdapter('test'));
        
        AdapterRegistry::clear();
        
        $this->assertFalse(AdapterRegistry::hasAdapter('test'));
        $this->assertEmpty(AdapterRegistry::getRegisteredTypes());
    }

    public function testRegisterIsIdempotent(): void
    {
        $factory = new MockAdapterFactory('test');
        
        AdapterRegistry::register($factory);
        AdapterRegistry::register($factory);
        
        $types = AdapterRegistry::getRegisteredTypes();
        $this->assertCount(1, $types);
        $this->assertTrue(AdapterRegistry::hasAdapter('test'));
    }
}
```

## Implementation Timeline

### Phase 0: Core Registry Enhancements (Week 1)
1. **Day 1-2**: Implement new registry methods
2. **Day 3-4**: Add comprehensive unit tests
3. **Day 5**: Code review and refinement

### Dependencies
- This enhancement must be completed and released before adapter auto-registration can be implemented
- Version compatibility should be maintained for existing adapters

## Risk Assessment

**Low Risk Enhancement:**
- Purely additive changes
- No breaking changes to existing functionality
- Comprehensive test coverage
- Backward compatible design

**Potential Issues:**
- None identified - changes are purely additive and well-tested

## Version Compatibility

Consider adding a version constant to support adapter compatibility checks:

```php
class EdgeBinder
{
    public const VERSION = '2.1.0'; // Update as appropriate
    public const AUTO_REGISTRATION_SUPPORTED = true;
}
```

## Conclusion

These core registry enhancements provide the foundation for automatic adapter registration while maintaining full backward compatibility. The changes are minimal, well-tested, and enable significant improvements to the EdgeBinder developer experience.

Once implemented, these enhancements will allow adapter packages to implement auto-registration features safely and reliably.
