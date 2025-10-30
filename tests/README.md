# Tests Suite for OI Laravel TypeScript Generator

This directory contains comprehensive test coverage for the `oi-laravel-ts` package.

## Test Structure

```
tests/
├── Unit/                              # Unit tests for individual components
│   ├── PhpToTypeScriptConverterTest.php
│   ├── DataObjectAnalyzerTest.php
│   ├── CastTypeResolverTest.php
│   └── RelationshipResolverTest.php
├── Feature/                           # Integration tests
│   └── TypeScriptGenerationTest.php
├── Fixtures/                          # Test fixtures
│   ├── Models/                        # Sample Eloquent models
│   ├── Casts/                         # Sample custom casts
│   └── DataObjects/                   # Sample DataObject classes
├── Pest.php                           # Pest configuration
└── TestCase.php                       # Base test case
```

## Running Tests

### Run all tests
```bash
vendor/bin/pest
```

### Run specific test suite
```bash
vendor/bin/pest tests/Unit
vendor/bin/pest tests/Feature
```

### Run specific test file
```bash
vendor/bin/pest tests/Unit/PhpToTypeScriptConverterTest.php
```

### Run with coverage (requires Xdebug or PCOV)
```bash
vendor/bin/pest --coverage
```

## Test Coverage

### Unit Tests

#### PhpToTypeScriptConverterTest
Tests for PHP to TypeScript type conversion:
- ✅ Basic type conversion (int → number, string → string, etc.)
- ✅ Array notation types
- ✅ Union type splitting
- ✅ Union type conversion
- ✅ Generic array types (array<int, T>)
- ✅ Record types (array<string, T>)
- ✅ Complex union types with generics

#### DataObjectAnalyzerTest
Tests for DataObject analysis:
- ✅ DataObject identification (fromArray/toArray methods)
- ✅ Property extraction from constructor
- ✅ PHPDoc type annotation parsing
- ✅ Nullable and default value handling

#### CastTypeResolverTest
Tests for custom cast resolution:
- ✅ DataObject cast resolution
- ✅ Cast property extraction
- ✅ Invalid cast handling
- ✅ Non-existent class handling

#### RelationshipResolverTest
Tests for Eloquent relationship resolution:
- ✅ HasMany relationship detection
- ✅ BelongsTo relationship detection
- ✅ BelongsToMany with pivot information
- ✅ Multiple relationships on same model

### Integration Tests

#### TypeScriptGenerationTest
End-to-end tests for the full conversion pipeline:
- ✅ Schema generation from multiple models
- ✅ Model properties extraction
- ✅ Relationship detection and inclusion
- ✅ Custom cast handling
- ✅ TypeScript interface generation
- ✅ Relationship type generation (IPost[], posts_count)
- ✅ Custom props application
- ✅ withCounts configuration
- ✅ Datetime cast handling

## Test Fixtures

### Models
- **User**: Model with relationships (HasMany, BelongsToMany) and custom casts
- **Post**: Model with BelongsTo and HasMany relationships
- **Comment**: Model with multiple BelongsTo relationships
- **Role**: Model with BelongsToMany relationship

### Casts
- **AddressCast**: Custom cast returning AddressData DataObject
- **MetadataCast**: Custom cast returning MetadataData DataObject

### DataObjects
- **AddressData**: DataObject with basic properties (street, city, state, zipCode)
- **MetadataData**: DataObject with PHPDoc annotations (title, description, tags)

## Writing New Tests

### Unit Test Example
```php
use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;

describe('PhpToTypeScriptConverter', function () {
    beforeEach(function () {
        $this->converter = new PhpToTypeScriptConverter;
    });

    it('converts basic types', function () {
        expect($this->converter->phpTypeToTypeScript('int'))->toBe('number')
            ->and($this->converter->phpTypeToTypeScript('string'))->toBe('string');
    });
});
```

### Integration Test Example
```php
use OiLab\OiLaravelTs\Services\Eloquent;

it('generates schema from models', function () {
    Eloquent::setAdditionalModels([User::class]);
    $schema = Eloquent::getSchema();

    expect($schema)->toHaveKey('User')
        ->and($schema['User']['types'])->not->toBeEmpty();
});
```

## Test Configuration

### Environment Variables
Tests use the following environment configuration (see `TestCase.php`):
- `APP_ENV=testing`
- `CACHE_DRIVER=array`
- `SESSION_DRIVER=array`
- `QUEUE_CONNECTION=sync`

### PHPUnit Configuration
Configuration is defined in `phpunit.xml`:
- Bootstrap: `vendor/autoload.php`
- Test suites: Unit and Feature
- Coverage source: `src/` directory

## Continuous Integration

These tests are designed to run in CI/CD pipelines. Recommended setup:

```yaml
# Example GitHub Actions workflow
- name: Run tests
  run: vendor/bin/pest --ci

- name: Generate coverage
  run: vendor/bin/pest --coverage --min=80
```

## Dependencies

Tests require:
- `pestphp/pest`: ^4.0
- `pestphp/pest-plugin-laravel`: ^4.0
- `orchestra/testbench`: ^9.0

## Contributing

When adding new features:
1. Write unit tests for new services/components
2. Add integration tests for end-to-end functionality
3. Ensure all tests pass: `vendor/bin/pest`
4. Aim for high test coverage (>80%)

## Test Results Summary

Current test results:
- **39 tests passing** ✅
- **160 assertions** ✅
- **Duration**: ~0.38s ⚡

All core functionality is covered and validated!
