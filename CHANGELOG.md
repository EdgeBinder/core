# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2025-08-02

### Added

#### Complete InMemory Adapter System
- **InMemoryAdapterFactory** - Full extensible adapter pattern implementation
- **Configuration-based creation** - `EdgeBinder::fromConfiguration(['adapter' => 'inmemory'])`
- **Framework integration** - Works with all PHP frameworks via AdapterRegistry
- **Production-ready features** - Advanced query support, comprehensive error handling

#### Professional Test Structure
- **Standard PHPUnit organization** - Reorganized tests into `tests/Unit/` and `tests/Integration/` directories
- **222 comprehensive tests** - Up from 210 tests with 643 assertions
- **Clean CI/CD pipeline** - Eliminated all PHPUnit warnings in GitHub Actions
- **Improved test coverage** - Enhanced InMemoryAdapter coverage with 12 additional tests

#### Enhanced Documentation
- **Complete llms.txt update** - Full InMemory adapter documentation and examples
- **Updated README.md** - New test structure, usage examples, framework integration patterns
- **Comprehensive test documentation** - Added `tests/README.md` with detailed testing guidance
- **Usage examples** - Multiple EdgeBinder instances, configuration patterns

### Changed

#### Code Organization
- **Namespace consistency** - Moved from `Storage` to `Persistence` namespace for clarity
- **Test structure** - Reorganized following standard PHPUnit conventions
- **File naming** - Renamed `EdgeBinderFactoryTest` to `EdgeBinderBuilderTest` for accuracy

#### Developer Experience
- **Multiple test execution options** - Unit tests, integration tests, coverage reports
- **Clean GitHub Actions** - Professional CI/CD pipeline without warnings
- **Better error messages** - Improved exception handling and validation

### Fixed

#### Code Quality
- **PHPStan Level 8 compliance** - Fixed all static analysis errors
- **Test reliability** - Fixed metadata ordering test for consistent results
- **CI/CD stability** - Resolved PHPUnit test suite overlap warnings

#### Coverage Improvements
- **InMemoryAdapter coverage** - Added comprehensive tests for edge cases and private methods
- **Overall test quality** - Better coverage of complex scenarios and error paths

### Technical Details

#### New Components
- `src/Persistence/InMemory/InMemoryAdapterFactory.php` - Factory implementation
- `tests/Unit/` - Complete unit test suite reorganization
- `tests/README.md` - Comprehensive testing documentation

#### Test Statistics
- **Total Tests**: 222 (was 210)
- **Total Assertions**: 643 (was 597)
- **Unit Tests**: 208 tests in `tests/Unit/`
- **Integration Tests**: 14 tests in `tests/Integration/`
- **Line Coverage**: 96.84% maintained

#### Framework Compatibility
- **Laravel** - Full service provider integration
- **Symfony** - Bundle and service configuration
- **Generic PHP** - PSR-11 container support
- **All frameworks** - Consistent adapter registration patterns

### Migration Guide

#### From v0.1.x to v0.2.0

**No Breaking Changes** - This release is fully backward compatible.

**Optional Improvements:**
```php
// Old way (still works)
$adapter = new InMemoryAdapter();
$edgeBinder = new EdgeBinder($adapter);

// New way (recommended)
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Persistence\InMemory\InMemoryAdapterFactory;

AdapterRegistry::register(new InMemoryAdapterFactory());
$edgeBinder = EdgeBinder::fromConfiguration(['adapter' => 'inmemory'], $container);
```

**Test Command Updates:**
```bash
# Old commands (still work)
vendor/bin/phpunit

# New organized commands
vendor/bin/phpunit tests/Unit      # Unit tests only
vendor/bin/phpunit tests/Integration  # Integration tests only
composer test-coverage             # Full coverage report
```

### Contributors

- Enhanced InMemory adapter system and factory implementation
- Reorganized test structure following PHP standards
- Improved documentation and developer experience
- Fixed code quality issues and enhanced CI/CD pipeline

---

## [0.1.0] - 2025-07-XX

### Added
- Initial EdgeBinder implementation
- Core binding management functionality
- InMemory adapter for testing and development
- Query builder with advanced filtering
- Comprehensive exception handling
- Framework-agnostic adapter registry system
- PSR-11 container integration
- Extensive test suite with high coverage

### Features
- Entity relationship binding and querying
- Flexible metadata support with validation
- Multiple entity extraction strategies
- Advanced query operations (filtering, ordering, pagination)
- Framework integration examples (Laravel, Symfony)
- Production-ready error handling and logging

[0.2.0]: https://github.com/EdgeBinder/edgebinder/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/EdgeBinder/edgebinder/releases/tag/v0.1.0
