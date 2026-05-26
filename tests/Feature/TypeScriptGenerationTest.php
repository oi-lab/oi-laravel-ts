<?php

use Illuminate\Support\Collection;
use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Attachment;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Comment;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Event;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\HasUuidsModel;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Membership;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Role;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\UuidModel;

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
            ->and($userSchema['types'])->toBeInstanceOf(Collection::class);

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

    it('does not generate empty field when UPDATED_AT is null', function () {
        Eloquent::setAdditionalModels([Event::class]);

        $schema = Eloquent::getSchema();
        $types = $schema['Event']['types'];
        $fieldNames = $types->pluck('field')->toArray();

        expect($fieldNames)->toContain('created_at')
            ->and($fieldNames)->not->toContain('updated_at')
            ->and($fieldNames)->not->toContain(null);
    });

    it('includes deleted_at for models using SoftDeletes', function () {
        Eloquent::setAdditionalModels([Post::class]);
        $schema = Eloquent::getSchema();
        $fieldNames = $schema['Post']['types']->pluck('field')->toArray();

        expect($fieldNames)->toContain('deleted_at');

        $deletedAt = $schema['Post']['types']->firstWhere('field', 'deleted_at');
        expect($deletedAt['nullable'])->toBeTrue();
    });

    it('generates deleted_at as optional nullable string in TypeScript', function () {
        Eloquent::setAdditionalModels([Post::class]);
        $schema = Eloquent::getSchema();
        $output = (new Convert($schema, false))->toTypeScript();

        expect($output)->toContain('deleted_at?: string | null;');
    });

    it('includes appended attributes in schema', function () {
        Eloquent::setAdditionalModels([User::class]);
        $schema = Eloquent::getSchema();
        $types = $schema['User']['types'];

        $fullName = $types->firstWhere('field', 'full_name');
        expect($fullName)->not->toBeNull()
            ->and($fullName['type'])->toBe('string');
    });

    it('generates appended attributes in TypeScript output', function () {
        Eloquent::setAdditionalModels([User::class]);
        $schema = Eloquent::getSchema();
        $output = (new Convert($schema, false))->toTypeScript();

        expect($output)->toContain('full_name: string;');
    });

    it('does not generate empty field line in TypeScript output when UPDATED_AT is null', function () {
        Eloquent::setAdditionalModels([Event::class]);

        $schema = Eloquent::getSchema();
        $converter = new Convert($schema, false);
        $output = $converter->toTypeScript();

        // A null field name would produce "    : string;" (4 spaces + colon with no field name)
        expect($output)->not->toContain('    : string;')
            ->and($output)->toContain('created_at: string;')
            ->and($output)->not->toContain('updated_at');
    });

    it('resolves MorphOne relationship as singular interface type', function () {
        Eloquent::setAdditionalModels([Post::class, Attachment::class]);
        $schema = Eloquent::getSchema();

        $coverRelation = $schema['Post']['types']->firstWhere('field', 'cover');

        expect($coverRelation)->not->toBeNull()
            ->and($coverRelation['relation'])->toBeTrue()
            ->and($coverRelation['type'])->toBe('MorphOne');
    });

    it('generates MorphOne relationship as optional singular interface in TypeScript', function () {
        Eloquent::setAdditionalModels([Post::class, Attachment::class]);
        $schema = Eloquent::getSchema();
        $output = (new Convert($schema, false))->toTypeScript();

        expect($output)->toContain('cover?: IAttachment');
    });

    it('custom_props overwrite existing auto-detected fields', function () {
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setCustomProps([
            'User' => [
                'name' => 'CustomNameType',
            ],
        ]);

        $schema = Eloquent::getSchema();
        $types = $schema['User']['types'];

        $nameFields = $types->where('field', 'name');
        expect($nameFields->count())->toBe(1)
            ->and($nameFields->first()['type'])->toBe('CustomNameType');
    });

    it('custom_props overwrite existing relationship fields', function () {
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setCustomProps([
            'User' => [
                'posts' => 'IPost[]',
            ],
        ]);

        $schema = Eloquent::getSchema();
        $types = $schema['User']['types'];

        $postsFields = $types->where('field', 'posts');
        expect($postsFields->count())->toBe(1)
            ->and($postsFields->first()['type'])->toBe('IPost[]')
            ->and($postsFields->first()['relation'])->toBeFalse();
    });

    it('emits intersection type with pivot interface for BelongsToMany using a custom Pivot model', function () {
        Eloquent::setAdditionalModels([User::class, Role::class, Membership::class]);
        $schema = Eloquent::getSchema();
        $output = (new Convert($schema, false))->toTypeScript();

        expect($output)->toContain('memberships?: (IRole & { pivot?: IMembership })[]');
    });

    it('keeps plain array type for BelongsToMany without custom Pivot model', function () {
        Eloquent::setAdditionalModels([User::class, Role::class]);
        $schema = Eloquent::getSchema();
        $output = (new Convert($schema, false))->toTypeScript();

        expect($output)->toContain('roles?: IRole[]')
            ->and($output)->not->toContain('IPivot');
    });

    it('global custom_props overwrite existing fields on all models', function () {
        Eloquent::setAdditionalModels([User::class, Post::class]);
        Eloquent::setCustomProps([
            '?name' => 'TranslatableString',
        ]);

        $schema = Eloquent::getSchema();

        $userNameFields = $schema['User']['types']->where('field', 'name');
        expect($userNameFields->count())->toBe(1)
            ->and($userNameFields->first()['type'])->toBe('TranslatableString');
    });

    it('discovers models referenced by relationships even when not listed explicitly', function () {
        Eloquent::setCustomProps([]);
        Eloquent::setDiscoverRelatedModels(true);
        Eloquent::setAdditionalModels([User::class]);

        $schema = Eloquent::getSchema();

        // User reaches these models through relationships; none were listed explicitly.
        expect($schema)->toHaveKey('User')
            ->and($schema)->toHaveKey('Role')
            ->and($schema)->toHaveKey('Post')
            ->and($schema)->toHaveKey('Membership')
            ->and($schema)->toHaveKey('Comment')
            ->and($schema)->toHaveKey('Attachment');
    });

    it('generates interfaces for relationship-provided models in TypeScript output', function () {
        Eloquent::setCustomProps([]);
        Eloquent::setDiscoverRelatedModels(true);
        Eloquent::setAdditionalModels([User::class]);

        $output = (new Convert(Eloquent::getSchema(), false))->toTypeScript();

        // The relationship type IRole[] now has a matching interface definition.
        expect($output)->toContain('roles?: IRole[]')
            ->and($output)->toContain('export interface IRole');
    });

    it('discovers custom Pivot models referenced through using()', function () {
        Eloquent::setCustomProps([]);
        Eloquent::setDiscoverRelatedModels(true);
        Eloquent::setAdditionalModels([User::class]);

        $output = (new Convert(Eloquent::getSchema(), false))->toTypeScript();

        expect($output)->toContain('memberships?: (IRole & { pivot?: IMembership })[]')
            ->and($output)->toContain('export interface IMembership');
    });

    it('handles relationship cycles without infinite recursion', function () {
        Eloquent::setCustomProps([]);
        Eloquent::setDiscoverRelatedModels(true);
        // Comment -> Post -> User -> Post forms a cycle; discovery must terminate.
        Eloquent::setAdditionalModels([Comment::class]);

        $schema = Eloquent::getSchema();

        expect($schema)->toHaveKey('Comment')
            ->and($schema)->toHaveKey('Post')
            ->and($schema)->toHaveKey('User')
            ->and($schema['User']['types'])->toBeInstanceOf(Collection::class);
    });

    it('does not discover related models when discovery is disabled', function () {
        Eloquent::setCustomProps([]);
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setDiscoverRelatedModels(false);

        $schema = Eloquent::getSchema();

        expect($schema)->toHaveKey('User')
            ->and($schema)->not->toHaveKey('Role')
            ->and($schema)->not->toHaveKey('Post');

        // Restore the default so later tests are unaffected.
        Eloquent::setDiscoverRelatedModels(true);
    });

    it('types the primary key as string when keyType is string', function () {
        Eloquent::setAdditionalModels([UuidModel::class]);
        $schema = Eloquent::getSchema();

        $idField = $schema['UuidModel']['types']->firstWhere('field', 'id');

        expect($idField)->not->toBeNull()
            ->and($idField['type'])->toBe('string');
    });

    it('generates string id in TypeScript output for UUID models', function () {
        Eloquent::setAdditionalModels([UuidModel::class]);
        $output = (new Convert(Eloquent::getSchema(), false))->toTypeScript();

        expect($output)->toContain('id: string;')
            ->and($output)->not->toContain('id: number');
    });

    it('does not duplicate the primary key when it is in fillable', function () {
        Eloquent::setAdditionalModels([UuidModel::class]);
        $schema = Eloquent::getSchema();

        $idFields = $schema['UuidModel']['types']->where('field', 'id');

        expect($idFields->count())->toBe(1);
    });

    it('types the primary key as string for HasUuids models using casts() method', function () {
        Eloquent::setAdditionalModels([HasUuidsModel::class]);
        $schema = Eloquent::getSchema();

        $idField = $schema['HasUuidsModel']['types']->firstWhere('field', 'id');
        $idFields = $schema['HasUuidsModel']['types']->where('field', 'id');

        expect($idField)->not->toBeNull()
            ->and($idField['type'])->toBe('string')
            ->and($idFields->count())->toBe(1);
    });

    it('generates string id in TypeScript output for HasUuids models', function () {
        Eloquent::setAdditionalModels([HasUuidsModel::class]);
        $output = (new Convert(Eloquent::getSchema(), false))->toTypeScript();

        expect($output)->toContain('id: string;')
            ->and($output)->not->toContain('id: number');
    });
});
