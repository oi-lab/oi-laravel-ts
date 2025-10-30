# Testing Strategy for OI Laravel TypeScript Generator

This document outlines the comprehensive testing strategy implemented for the `oi-laravel-ts` package.

## Overview

The package includes **39 tests** with **160 assertions**, providing comprehensive coverage of all core functionality. Tests are organized into **unit tests** (for individual components) and **feature tests** (for end-to-end integration).

## Test Organization

### Unit Tests (`tests/Unit/`)

Unit tests focus on testing individual service classes in isolation:

1. **PhpToTypeScriptConverterTest** (24 assertions)
   - Tests PHP to TypeScript type conversion
   - Covers basic types, union types, generics, and Record types
   - Validates complex nested type structures

2. **DataObjectAnalyzerTest** (9 assertions)
   - Tests DataObject identification and analysis
   - Validates property extraction from constructors
   - Tests PHPDoc annotation parsing

3. **CastTypeResolverTest** (4 assertions)
   - Tests Laravel custom cast resolution
   - Validates DataObject cast detection
   - Tests error handling for invalid casts

4. **RelationshipResolverTest** (10 assertions)
   - Tests Eloquent relationship detection
   - Validates HasMany, BelongsTo, BelongsToMany resolution
   - Tests pivot information extraction

### Feature Tests (`tests/Feature/`)

Feature tests validate end-to-end functionality:

1. **TypeScriptGenerationTest** (113 assertions)
   - Tests complete schema generation pipeline
   - Validates TypeScript interface output
   - Tests configuration options (withCounts, custom props)
   - Validates relationship type generation
   - Tests datetime cast handling

## Test Fixtures

Comprehensive fixtures support realistic testing scenarios:

### Models
- **User**: HasMany posts, BelongsToMany roles, custom AddressCast
- **Post**: BelongsTo user, HasMany comments, MetadataCast
- **Comment**: BelongsTo post and user
- **Role**: BelongsToMany users

### Custom Casts
- **AddressCast**: Returns AddressData DataObject
- **MetadataCast**: Returns MetadataData DataObject

### DataObjects
- **AddressData**: Basic properties with nullable and defaults
- **MetadataData**: Properties with PHPDoc type annotations

## Running Tests

### Basic Usage

```bash
# Run all tests
vendor/bin/pest

# Run with colors
vendor/bin/pest --colors=always

# Run compact output
vendor/bin/pest --compact

# Run specific suite
vendor/bin/pest tests/Unit
vendor/bin/pest tests/Feature
```

### Advanced Options

```bash
# Run with coverage (requires Xdebug or PCOV)
vendor/bin/pest --coverage

# Run with minimum coverage threshold
vendor/bin/pest --coverage --min=80

# Run in CI mode
vendor/bin/pest --ci

# Run specific test file
vendor/bin/pest tests/Unit/PhpToTypeScriptConverterTest.php

# Filter by test name
vendor/bin/pest --filter="converts basic types"
```

## Test Coverage Areas

### ✅ Type Conversion
- PHP native types → TypeScript
- Union types with multiple variants
- Generic array types (array<int, T>)
- Record types (array<string, T>)
- Complex nested structures
- PHPDoc annotation parsing

### ✅ Model Analysis
- Primary key detection
- Fillable attribute extraction
- Timestamp handling
- Custom cast resolution
- Relationship detection (all types)
- Pivot information extraction

### ✅ DataObject Handling
- DataObject identification
- Property extraction
- Type annotation parsing
- Nullable and default value handling
- Nested DataObject detection

### ✅ Schema Building
- Multi-model schema generation
- Custom property application
- Global property overrides
- Configuration handling (withCounts)
- Type metadata preservation

### ✅ TypeScript Generation
- Interface generation for models
- DataObject interface generation
- Import statement management
- Relationship type generation
- Optional field handling
- Comment and documentation generation

## Quality Metrics

- **39 tests** covering core functionality
- **160 assertions** validating behavior
- **Fast execution**: ~0.4s for full suite
- **Zero external dependencies** for testing
- **Comprehensive fixtures** for realistic scenarios

## Continuous Integration

Tests are designed to run in CI/CD pipelines. See `.github/workflows/tests.yml` for GitHub Actions configuration.

### Compatibility Matrix
- **PHP**: 8.2, 8.3, 8.4
- **Laravel**: 11.x, 12.x
- **Pest**: 4.x

## Test-Driven Development Workflow

When adding new features:

1. **Write failing tests first**
   ```bash
   vendor/bin/pest --filter="new feature"
   ```

2. **Implement the feature**
   - Follow existing architecture patterns
   - Maintain single responsibility principle
   - Use dependency injection

3. **Verify tests pass**
   ```bash
   vendor/bin/pest
   ```

4. **Check coverage** (if available)
   ```bash
   vendor/bin/pest --coverage
   ```

## Best Practices

### Writing Tests
- Use descriptive test names (`it converts basic types`)
- Group related tests with `describe()`
- Use `beforeEach()` for setup
- Test both happy paths and edge cases
- Keep tests isolated and independent

### Test Organization
- Unit tests in `tests/Unit/`
- Integration tests in `tests/Feature/`
- Fixtures in `tests/Fixtures/`
- One test class per service class

### Assertions
- Use Pest's fluent assertions
- Chain multiple expectations
- Be specific about expected values
- Test error conditions

## Troubleshooting

### Common Issues

**Tests not found**
```bash
# Ensure Pest is installed
composer install

# Check autoload
composer dump-autoload
```

**Database errors**
```bash
# Tests use in-memory arrays, no database needed
# Check TestCase.php environment configuration
```

**Fixtures not loading**
```bash
# Verify autoload-dev in composer.json
# Run composer dump-autoload
```

## Future Testing Enhancements

Potential areas for expanded test coverage:

- [ ] Edge cases for complex nested DataObjects
- [ ] Performance benchmarks
- [ ] Memory usage tests for large schemas
- [ ] TypeScript output validation (syntax checking)
- [ ] Additional relationship types (MorphTo, MorphMany)
- [ ] JSON-LD generator tests
- [ ] Command line interface tests

## Conclusion

The test suite provides comprehensive coverage of all package functionality, ensuring reliability and maintainability. All core features are validated through a combination of focused unit tests and realistic integration scenarios.

**Current Status**: ✅ All 39 tests passing with 160 assertions

For detailed test documentation, see `tests/README.md`.
