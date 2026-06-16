<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;

const DATA_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\Data';

function generateData(array $dataNamespaces, array $models = [], bool $replaces = false, array $forModel = []): string
{
    config()->set('oi-laravel-ts.data_namespaces', $dataNamespaces);
    config()->set('oi-laravel-ts.data_replaces_model', $replaces);
    config()->set('oi-laravel-ts.data_for_model', $forModel);

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels($models);

    return (new Convert(
        Eloquent::getSchema(),
        false,
        false,
        $dataNamespaces,
        $replaces,
        $forModel,
    ))->toTypeScript();
}

describe('Data class (DTO) generation', function () {
    it('emits an I{Dto} interface with verbatim camelCase property names', function () {
        $output = generateData([DATA_NS]);

        expect($output)->toContain('export interface IUserData')
            ->and($output)->toContain('id: string;')
            ->and($output)->toContain('fullName: string;')
            ->and($output)->toContain('isActive: boolean;');
    });

    it('marks nullable and defaulted properties as optional', function () {
        $output = generateData([DATA_NS]);

        expect($output)->toContain('age?: number;')
            ->and($output)->toContain('version?: string;');
    });

    it('converts a backed string enum to a literal union', function () {
        $output = generateData([DATA_NS]);

        expect($output)->toContain("status: 'active' | 'suspended' | 'pending';");
    });

    it('converts a backed int enum to a numeric literal union', function () {
        $output = generateData([DATA_NS]);

        expect($output)->toContain('level: 1 | 2 | 3;');
    });

    it('references a nested DTO and emits it (including sub-namespaces)', function () {
        $output = generateData([DATA_NS]);

        expect($output)->toContain('address?: IGeoData;')
            ->and($output)->toContain('export interface IGeoData');
    });

    it('types a property @var array of DTOs as IFoo[]', function () {
        $output = generateData([DATA_NS]);

        expect($output)->toContain('tags?: ITagData[];')
            ->and($output)->toContain('export interface ITagData');
    });

    it('types a property @var array of primitives natively', function () {
        $output = generateData([DATA_NS]);

        expect($output)->toContain('roles?: string[];');
    });

    it('coexists with the Eloquent model interface by default', function () {
        $output = generateData([DATA_NS], [User::class]);

        expect($output)->toContain('export interface IUser {')
            ->and($output)->toContain('export interface IUserData');
    });

    it('suppresses the mapped Eloquent interface when data_replaces_model is enabled', function () {
        $output = generateData([DATA_NS], [User::class], replaces: true);

        expect($output)->toContain('export interface IUserData')
            ->and($output)->not->toContain('export interface IUser {');
    });

    it('emits nothing DTO-related when no data namespace is configured', function () {
        $output = generateData([], [User::class]);

        expect($output)->not->toContain('IUserData')
            ->and($output)->toContain('export interface IUser {');
    });
});
