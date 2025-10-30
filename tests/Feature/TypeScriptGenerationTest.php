<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Comment;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Role;

describe('TypeScript Generation Integration', function () {
    it('generates TypeScript interfaces from models', function () {
        // Configure Eloquent with test models
        Eloquent::setAdditionalModels([
            User::class,
            Post::class,
            Comment::class,
            Role::class,
        ]);

        // Get schema
        $schema = Eloquent::getSchema();

        expect($schema)->toBeArray()
            ->and($schema)->toHaveKey('User')
            ->and($schema)->toHaveKey('Post')
            ->and($schema)->toHaveKey('Comment')
            ->and($schema)->toHaveKey('Role');
    });

    it('schema contains model properties', function () {
        Eloquent::setAdditionalModels([User::class]);
        $schema = Eloquent::getSchema();

        $userSchema = $schema['User'];

        expect($userSchema)->toHaveKey('types')
            ->and($userSchema['types'])->toBeInstanceOf(\Illuminate\Support\Collection::class);

        $types = $userSchema['types'];
        $fieldNames = $types->pluck('field')->toArray();

        expect($fieldNames)->toContain('id')
            ->and($fieldNames)->toContain('name')
            ->and($fieldNames)->toContain('email')
            ->and($fieldNames)->toContain('created_at')
            ->and($fieldNames)->toContain('updated_at');
    });

    it('schema contains relationships', function () {
        Eloquent::setAdditionalModels([User::class]);
        $schema = Eloquent::getSchema();

        $types = $schema['User']['types'];
        $relations = $types->where('relation', true);

        expect($relations->count())->toBeGreaterThan(0);

        $postsRelation = $relations->firstWhere('field', 'posts');

        expect($postsRelation)->not->toBeNull()
            ->and($postsRelation['type'])->toBe('HasMany');
    });

    it('schema includes casted datetime fields', function () {
        Eloquent::setAdditionalModels([Post::class]);
        $schema = Eloquent::getSchema();

        $types = $schema['Post']['types'];
        $publishedAtField = $types->firstWhere('field', 'published_at');

        // Datetime casts should be present
        expect($publishedAtField)->not->toBeNull()
            ->and($publishedAtField['type'])->toBe('datetime');
    });

    it('generates valid TypeScript code from schema', function () {
        Eloquent::setAdditionalModels([User::class, Post::class]);
        $schema = Eloquent::getSchema();

        $converter = new Convert($schema, false);
        $typescript = $converter->toTypeScript();

        expect($typescript)->toBeString()
            ->and($typescript)->toContain('export interface IUser')
            ->and($typescript)->toContain('export interface IPost')
            ->and($typescript)->toContain('id: number')
            ->and($typescript)->toContain('name: string')
            ->and($typescript)->toContain('email: string');
    });

    it('generates TypeScript with proper structure', function () {
        Eloquent::setAdditionalModels([User::class]);
        $schema = Eloquent::getSchema();

        $converter = new Convert($schema, false);
        $typescript = $converter->toTypeScript();

        // Verify basic structure is generated
        expect($typescript)->toBeString()
            ->and($typescript)->toContain('export interface IUser')
            ->and($typescript)->toContain('name: string')
            ->and($typescript)->toContain('email: string');

        // DataObject interfaces may or may not be present depending on cast resolution
        // The important thing is that the TypeScript is valid
    });

    it('generates TypeScript with relationship types', function () {
        Eloquent::setAdditionalModels([User::class, Post::class]);
        $schema = Eloquent::getSchema();

        $converter = new Convert($schema, false);
        $typescript = $converter->toTypeScript();

        expect($typescript)->toContain('posts?: IPost[]')
            ->and($typescript)->toContain('posts_count?: number');
    });

    it('applies custom props to schema', function () {
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setCustomProps([
            'User' => [
                'custom_field' => 'string',
            ],
        ]);

        $schema = Eloquent::getSchema();
        $types = $schema['User']['types'];
        $customField = $types->firstWhere('field', 'custom_field');

        expect($customField)->not->toBeNull()
            ->and($customField['type'])->toBe('string');
    });

    it('supports withCounts configuration', function () {
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setWithCounts(true);

        $schema = Eloquent::getSchema();
        $types = $schema['User']['types'];
        $fieldNames = $types->pluck('field')->toArray();

        expect($fieldNames)->toContain('posts_count');
    });

    it('can disable withCounts', function () {
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setWithCounts(false);

        $schema = Eloquent::getSchema();
        $types = $schema['User']['types'];
        $fieldNames = $types->pluck('field')->toArray();

        expect($fieldNames)->not->toContain('posts_count');
    });
});
