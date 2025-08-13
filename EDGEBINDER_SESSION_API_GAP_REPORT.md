# EdgeBinder v0.8.0 Session API Gap Report

## Summary

The Session interface in EdgeBinder v0.8.0 is missing several critical methods from the main EdgeBinder class, preventing complete feature parity and forcing developers to implement inefficient workarounds for common operations.

## Issue Description

While EdgeBinder v0.8.0's session implementation successfully solves the database timing and consistency issues, the Session interface only provides 9 methods compared to EdgeBinder's 20 public methods. This creates an incomplete API that limits session adoption.

## Missing Methods Analysis

### Critical Missing Methods (High Priority)

#### 1. `unbindEntities(object $from, object $to, ?string $type = null): int`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Forces inefficient query + loop + individual unbind pattern

```php
// Current workaround (inefficient):
$session = $edgeBinder->session();
$result = $session->query()->from($profile)->to($organization)->type('member_of')->get();
$deletedCount = 0;
foreach ($result->getBindings() as $binding) {
    if ($session->unbind($binding->getId())) {
        $deletedCount++;
    }
}

// Ideal API (should exist):
$deletedCount = $session->unbindEntities($profile, $organization, 'member_of');
```

#### 2. `findBindingsFor(object $entity): array`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Common query pattern requires manual query building

#### 3. `areBound(object $from, object $to, ?string $type = null): bool`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Essential relationship checking requires manual implementation

### Important Missing Methods (Medium Priority)

#### 4. `findBinding(string $bindingId): ?BindingInterface`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Direct binding lookup not available

#### 5. `findBindingsBetween(object $from, object $to, ?string $type = null): array`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Relationship discovery requires manual query building

#### 6. `hasBindings(object $entity): bool`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Entity relationship checking requires manual implementation

#### 7. `countBindingsFor(object $entity, ?string $type = null): int`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Counting operations require manual query + count

#### 8. `unbindEntity(object $entity): int`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Bulk entity cleanup requires manual implementation

### Convenience Missing Methods (Low Priority)

#### 9. `bindMany(array $bindings): array`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Bulk creation optimization not available

#### 10. `updateMetadata(string $bindingId, array $metadata): BindingInterface`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Metadata updates require find + unbind + bind pattern

#### 11. `replaceMetadata(string $bindingId, array $metadata): BindingInterface`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Metadata replacement requires find + unbind + bind pattern

#### 12. `getMetadata(string $bindingId): array`
**Current EdgeBinder**: ✅ Available
**Session**: ❌ Missing
**Impact**: Metadata access requires find + property access

## Current Session Interface

```php
interface SessionInterface
{
    // ✅ Core operations (available)
    public function bind(object $from, object $to, string $type, array $metadata = []): BindingInterface;
    public function unbind(string $bindingId): bool;
    public function query(): QueryBuilderInterface;
    
    // ✅ Session management (available)
    public function flush(): void;
    public function clear(): void;
    public function close(): void;
    
    // ✅ State inspection (available)
    public function isDirty(): bool;
    public function getPendingOperations(): array;
    public function getTrackedBindings(): array;
    
    // ❌ Missing 12 methods from EdgeBinder class
}
```

## Recommended Complete Session Interface

```php
interface SessionInterface
{
    // ✅ Existing methods (keep as-is)
    public function bind(object $from, object $to, string $type, array $metadata = []): BindingInterface;
    public function unbind(string $bindingId): bool;
    public function query(): QueryBuilderInterface;
    public function flush(): void;
    public function clear(): void;
    public function close(): void;
    public function isDirty(): bool;
    public function getPendingOperations(): array;
    public function getTrackedBindings(): array;
    
    // ❌ Add missing methods for feature parity
    public function unbindEntities(object $from, object $to, ?string $type = null): int;
    public function unbindEntity(object $entity): int;
    public function findBinding(string $bindingId): ?BindingInterface;
    public function findBindingsFor(object $entity): array;
    public function findBindingsBetween(object $from, object $to, ?string $type = null): array;
    public function areBound(object $from, object $to, ?string $type = null): bool;
    public function hasBindings(object $entity): bool;
    public function countBindingsFor(object $entity, ?string $type = null): int;
    public function bindMany(array $bindings): array;
    public function updateMetadata(string $bindingId, array $metadata): BindingInterface;
    public function replaceMetadata(string $bindingId, array $metadata): BindingInterface;
    public function getMetadata(string $bindingId): array;
}
```

## Business Impact

### Current State
- **Session adoption limited** by incomplete API
- **Performance degradation** due to workarounds
- **Developer confusion** about which methods are available
- **Inconsistent experience** between EdgeBinder and Session

### With Complete API
- **Full session adoption** possible
- **Optimal performance** with native session methods
- **Consistent developer experience** across APIs
- **Simplified migration** from direct EdgeBinder to sessions

## Implementation Priority

### Phase 1 (Critical - Immediate Need)
1. `unbindEntities()` - Required for bulk operations
2. `findBindingsFor()` - Common query pattern
3. `areBound()` - Essential relationship checking

### Phase 2 (Important - Near Term)
4. `findBinding()` - Direct binding access
5. `findBindingsBetween()` - Relationship discovery
6. `hasBindings()` - Entity relationship checking
7. `countBindingsFor()` - Counting operations

### Phase 3 (Enhancement - Future)
8. `unbindEntity()` - Bulk entity cleanup
9. `bindMany()` - Bulk creation optimization
10. `updateMetadata()` - Metadata management
11. `replaceMetadata()` - Metadata management
12. `getMetadata()` - Metadata access

## Recommendation

**Priority**: High - This affects session adoption and API consistency

**Scope**: Session interface enhancement for feature parity

**Effort**: Medium - Most methods can delegate to existing adapter methods with session cache integration

**Benefit**: Complete session API enables full adoption and eliminates performance workarounds

---

**Reporter**: Corvus Meliora (corvus@zestic.com)
**Date**: 2025-08-13
**EdgeBinder Version**: v0.8.0
**Context**: Real-world implementation feedback during session adoption
