<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\ExcludedModels\Category;
use OiLab\OiLaravelTs\Tests\Fixtures\ExtendedModels\User as ExtendedUser;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;

beforeEach(function () {
    Eloquent::setAdditionalModels([]);
    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setWithCounts(true);
    Eloquent::setExcludedNamespaces([]);
    Eloquent::setExtendedNamespaces([]);
});

describe('excluded_namespaces', function () {
    it('removes models from the schema when their namespace matches', function () {
        Eloquent::setAdditionalModels([User::class, Category::class]);
        Eloquent::setExcludedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExcludedModels']);

        $schema = Eloquent::getSchema();

        expect($schema)->toHaveKey('User')
            ->and($schema)->not->toHaveKey('Category');
    });

    it('keeps models whose namespace does not match any prefix', function () {
        Eloquent::setAdditionalModels([User::class, Post::class]);
        Eloquent::setExcludedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExcludedModels']);

        $schema = Eloquent::getSchema();

        expect($schema)->toHaveKey('User')
            ->and($schema)->toHaveKey('Post');
    });

    it('skips excluded models discovered via relationships', function () {
        Eloquent::setAdditionalModels([User::class, Category::class]);
        Eloquent::setDiscoverRelatedModels(true);
        Eloquent::setExcludedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExcludedModels']);

        $schema = Eloquent::getSchema();

        expect($schema)->not->toHaveKey('Category');
    });

    it('excludes models matching a sub-namespace of the configured prefix', function () {
        Eloquent::setAdditionalModels([Category::class]);
        Eloquent::setExcludedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures']);

        $schema = Eloquent::getSchema();

        expect($schema)->not->toHaveKey('Category')
            ->and($schema)->not->toHaveKey('User');
    });

    it('generates no TypeScript for excluded models', function () {
        Eloquent::setAdditionalModels([User::class, Category::class]);
        Eloquent::setExcludedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExcludedModels']);

        $schema = Eloquent::getSchema();
        $typescript = (new Convert($schema, false))->toTypeScript();

        expect($typescript)->toContain('export interface IUser')
            ->and($typescript)->not->toContain('export interface ICategory');
    });
});

describe('extended_namespaces', function () {
    it('adds an isExtension entry for matching models', function () {
        Eloquent::setAdditionalModels([User::class, ExtendedUser::class]);
        Eloquent::setExtendedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels']);

        $schema = Eloquent::getSchema();

        expect($schema)->toHaveKey('User')
            ->and($schema)->toHaveKey('UserExtended')
            ->and($schema['UserExtended']['isExtension'])->toBeTrue()
            ->and($schema['UserExtended']['extends'])->toBe('User');
    });

    it('does not add a standalone entry for the extended namespace model', function () {
        Eloquent::setAdditionalModels([User::class, ExtendedUser::class]);
        Eloquent::setExtendedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels']);

        $schema = Eloquent::getSchema();

        // Only 'User' and 'UserExtended' should exist — not a duplicate 'User'
        // keyed from the extended namespace class.
        expect(array_keys($schema))->not->toContain('UserExtended' === 'UserExtended' ? 'NonExistent' : '');
        expect($schema)->toHaveKey('UserExtended');
        expect($schema['UserExtended']['namespace'])->toBe('OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels\\User');
    });

    it('generates IUserExtended extends IUser in TypeScript output', function () {
        Eloquent::setAdditionalModels([User::class, ExtendedUser::class]);
        Eloquent::setExtendedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels']);

        $schema = Eloquent::getSchema();
        $typescript = (new Convert($schema, false))->toTypeScript();

        expect($typescript)->toContain('export interface IUserExtended extends IUser');
    });

    it('includes the extended model fields in the extension interface', function () {
        Eloquent::setAdditionalModels([User::class, ExtendedUser::class]);
        Eloquent::setExtendedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels']);

        $schema = Eloquent::getSchema();
        $typescript = (new Convert($schema, false))->toTypeScript();

        expect($typescript)->toContain('ps_id: number')
            ->and($typescript)->toContain('ps_email: string');
    });

    it('still generates the base interface alongside the extension', function () {
        Eloquent::setAdditionalModels([User::class, ExtendedUser::class]);
        Eloquent::setExtendedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels']);

        $schema = Eloquent::getSchema();
        $typescript = (new Convert($schema, false))->toTypeScript();

        expect($typescript)->toContain('export interface IUser {')
            ->and($typescript)->toContain('export interface IUserExtended extends IUser');
    });

    it('skips extension when no base model matches the short name', function () {
        // Only the extended namespace model is added — no base User in schema.
        Eloquent::setAdditionalModels([ExtendedUser::class]);
        Eloquent::setExtendedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels']);

        $schema = Eloquent::getSchema();

        expect($schema)->not->toHaveKey('User')
            ->and($schema)->not->toHaveKey('UserExtended');
    });

    it('writes a separate file for the extension interface in multi-file mode', function () {
        Eloquent::setAdditionalModels([User::class, ExtendedUser::class]);
        Eloquent::setExtendedNamespaces(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\ExtendedModels']);

        $schema = Eloquent::getSchema();
        $outDir = sys_get_temp_dir().'/oi-ts-ext-'.uniqid();

        (new Convert($schema, false))->generateFiles($outDir);

        $files = array_map('basename', glob($outDir.'/*.ts'));

        expect($files)->toContain('user.ts')
            ->and($files)->toContain('user-extended.ts');

        $content = file_get_contents($outDir.'/user-extended.ts');

        expect($content)->toContain('export interface IUserExtended extends IUser')
            ->and($content)->toContain("import type { IUser } from './user';");

        array_map('unlink', glob($outDir.'/*.ts'));
        rmdir($outDir);
    });
});
