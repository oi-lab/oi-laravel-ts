<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | The path where the TypeScript interfaces file will be generated.
    | Default: resource_path('js/types/interfaces.ts')
    |
    */
    'output_path' => resource_path('js/types/interfaces.ts'),

    /*
    |--------------------------------------------------------------------------
    | Include Relationship Counts
    |--------------------------------------------------------------------------
    |
    | Whether to include _count fields for relationships (HasMany, BelongsToMany, etc.)
    |
    */
    'with_counts' => true,

    /*
    |--------------------------------------------------------------------------
    | Enable JSON-LD Support
    |--------------------------------------------------------------------------
    |
    | Whether to include JsonLdRawNode interface for JSON-LD support
    |
    */
    'with_json_ld' => false,

    /*
    |--------------------------------------------------------------------------
    | Save Schema
    |--------------------------------------------------------------------------
    |
    | Whether to save the intermediate schema.json file for debugging
    |
    */
    'save_schema' => false,

    /*
    |--------------------------------------------------------------------------
    | Props With Types
    |--------------------------------------------------------------------------
    |
    | Define specific types for model properties
    |
    */
    'props_with_types' => [
        // Example:
        // 'User' => [
        //     'email' => 'string',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Props
    |--------------------------------------------------------------------------
    |
    | Add custom properties to models that aren't in the database schema
    |
    | Format:
    | - Model-specific: 'ModelName' => ['field' => 'type']
    | - All models: '?field' => 'type'
    |
    */
    'custom_props' => [
        // Example:
        // 'Organization' => [
        //     'uuid' => 'string',
        // ],
        // 'Page' => [
        //     'uuid' => 'string',
        // ],
    ],
];
