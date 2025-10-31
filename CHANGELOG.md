# Changelog

All notable changes to `oi-laravel-ts` will be documented in this file.

## [1.0.0] - 2025-01-30

Initial release of OI Laravel TypeScript Generator - a comprehensive Laravel package that automatically generates TypeScript interfaces from Eloquent models.

### Core Features
- **Automatic Interface Generation**: Converts Eloquent models to TypeScript interfaces with full type safety
- **Relationship Support**: Handles all Laravel relationship types (HasOne, HasMany, BelongsTo, BelongsToMany, MorphTo, MorphMany, etc.)
- **Custom Casts**: Automatic detection and conversion of Laravel custom casts
- **DataObject Support**: Analyzes and generates interfaces for PHP DataObject classes
- **PHPDoc Support**: Reads PHPDoc annotations for complex types
- **Watch Mode**: Monitor models directory and automatically regenerate on changes
- **Configurable Options**: Extensive configuration for customization
- **JSON-LD Support**: Optional support for JSON-LD data structures
- **Relationship Counts**: Automatic generation of `_count` fields for relationships
- **External Type Imports**: Reference and import external TypeScript types

### Architecture
Built with clean architecture principles and separation of concerns:

#### Analysis Pipeline (Eloquent)
- `Eloquent`: Facade for model analysis and schema generation
- `ModelDiscovery`: Discovers all Eloquent models in the application
- `TypeExtractor`: Extracts type information from model properties
- `RelationshipResolver`: Detects and extracts relationship metadata
- `CastTypeResolver`: Resolves custom Laravel casts to TypeScript types
- `DataObjectAnalyzer`: Analyzes PHP DataObject classes
- `PhpToTypeScriptConverter`: Converts PHP types to TypeScript
- `SchemaBuilder`: Orchestrates complete schema building

#### Generation Pipeline (Convert)
- `Convert`: Main orchestrator coordinating TypeScript generation
- `TypeScriptTypeConverter`: Handles schema to TypeScript type conversion
- `DataObjectProcessor`: Processes PHP DataObjects and generates interfaces
- `ModelInterfaceGenerator`: Generates TypeScript interfaces for models
- `ImportManager`: Manages TypeScript import statements
- `JsonLdGenerator`: Generates JSON-LD support interfaces

### Technical Excellence
- **SOLID Principles**: Each class follows Single Responsibility Principle
- **Dependency Injection**: Used throughout for better testability
- **Comprehensive Documentation**: ~900+ lines of PHPDoc documentation
- **Type Safety**: Full PHP type hints with structured array shapes
- **Modular Design**: Plugin-like architecture for easy extension
- **Error Handling**: Robust error handling throughout pipelines

### Requirements
- PHP 8.2, 8.3, or 8.4
- Laravel 11.0+ or 12.0+

### Testing
- 39 comprehensive tests with 160 assertions
- Unit tests for all components
- Feature tests for integration scenarios
- Architecture tests for code quality

### Command Line Interface
- `php artisan oi:gen-ts` - Generate TypeScript interfaces
- `php artisan oi:gen-ts --watch` - Watch mode for automatic regeneration

### Configuration Options
- `output_path` - Custom output path for generated TypeScript
- `with_counts` - Include relationship count fields
- `with_json_ld` - Enable JSON-LD support
- `save_schema` - Save intermediate schema.json for debugging
- `props_with_types` - Define specific types for properties
- `custom_props` - Add custom properties to models
