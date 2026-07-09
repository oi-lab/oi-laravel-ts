<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;

const PACKAGE_MODELS_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\PackageModels';

beforeEach(function () {
    Eloquent::setAdditionalModels([]);
    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setWithCounts(true);
    Eloquent::setIncludedModelNamespaces([]);
    Eloquent::setExcludedNamespaces([]);
    Eloquent::setExtendedNamespaces([]);
});

describe('included_model_namespaces', function () {
    it('leaves unreferenced package models out of the schema by default', function () {
        Eloquent::setAdditionalModels([User::class]);

        expect(Eloquent::getSchema())->not->toHaveKey('PublishPage');
    });

    it('adds every model of an included namespace to the schema', function () {
        Eloquent::setIncludedModelNamespaces([PACKAGE_MODELS_NS]);

        $schema = Eloquent::getSchema();

        expect($schema)->toHaveKey('PublishPage')
            ->and($schema)->toHaveKey('PublishBlock');
    });

    it('skips abstract models and non-model classes in the namespace', function () {
        Eloquent::setIncludedModelNamespaces([PACKAGE_MODELS_NS]);

        $schema = Eloquent::getSchema();

        expect($schema)->not->toHaveKey('AbstractPublishable')
            ->and($schema)->not->toHaveKey('PublishHelper');
    });

    it('emits their interfaces with relationships and _count fields', function () {
        Eloquent::setIncludedModelNamespaces([PACKAGE_MODELS_NS]);

        $output = (new Convert(Eloquent::getSchema()))->toTypeScript();

        expect($output)->toContain('export interface IPublishPage {')
            ->and($output)->toContain('blocks?: IPublishBlock[];')
            ->and($output)->toContain('blocks_count?: number;')
            ->and($output)->toContain('export interface IPublishBlock {')
            ->and($output)->toContain('page?: IPublishPage;');
    });

    it('still honours excluded_namespaces over the included list', function () {
        Eloquent::setIncludedModelNamespaces([PACKAGE_MODELS_NS]);
        Eloquent::setExcludedNamespaces([PACKAGE_MODELS_NS]);

        expect(Eloquent::getSchema())->not->toHaveKey('PublishPage');
    });

    it('coexists with app models and additional models', function () {
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setIncludedModelNamespaces([PACKAGE_MODELS_NS]);

        $schema = Eloquent::getSchema();

        expect($schema)->toHaveKey('User')
            ->and($schema)->toHaveKey('PublishPage');
    });

    it('ignores a namespace with no PSR-4 mapping', function () {
        Eloquent::setAdditionalModels([User::class]);
        Eloquent::setIncludedModelNamespaces(['Totally\\Unmapped\\Models']);

        expect(Eloquent::getSchema())->toHaveKey('User');
    });
});
