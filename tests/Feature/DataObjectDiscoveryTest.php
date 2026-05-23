<?php

use OiLab\OiLaravelTs\Exceptions\DataObjectNameCollisionException;
use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;

const STANDALONE_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\DataObjects\\Standalone';
const DATAOBJECTS_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\DataObjects';
const COLLISIONS_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\Collisions';

function generateWithDiscovery(array $namespaces, array $models, bool $discover): string
{
    config()->set('oi-laravel-ts.dataobject_namespaces', $namespaces);

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels($models);

    return (new Convert(Eloquent::getSchema(), false, $discover))->toTypeScript();
}

describe('DataObject autonomous discovery', function () {
    it('emits an interface for a DataObject referenced by no model', function () {
        $output = generateWithDiscovery([STANDALONE_NS], [User::class], true);

        expect($output)->toContain('export interface IOrderData');
    });

    it('chains nested DataObjects discovered through PHPDoc annotations', function () {
        $output = generateWithDiscovery([STANDALONE_NS], [User::class], true);

        expect($output)->toContain('export interface IOrderData')
            ->and($output)->toContain('export interface IOrderLineData')
            ->and($output)->toContain('lines?: IOrderLineData[];')
            ->and($output)->toContain('featuredLine?: IOrderLineData;');
    });

    it('discovers DataObjects living in sub-namespaces recursively', function () {
        $output = generateWithDiscovery([STANDALONE_NS], [User::class], true);

        expect($output)->toContain('export interface IShippingData');
    });

    it('skips DataObjects without a constructor instead of emitting an empty interface', function () {
        $output = generateWithDiscovery([STANDALONE_NS], [User::class], true);

        expect($output)->not->toContain('export interface INoConstructorData');
    });

    it('does not duplicate a DataObject exposed by a cast and present in the namespace', function () {
        $output = generateWithDiscovery([DATAOBJECTS_NS], [User::class], true);

        expect(substr_count($output, 'export interface IAddressData {'))->toBe(1);
    });

    it('throws when two classes resolve to the same short name', function () {
        generateWithDiscovery([COLLISIONS_NS], [User::class], true);
    })->throws(DataObjectNameCollisionException::class);

    it('leaves output unchanged when the flag is disabled', function () {
        $output = generateWithDiscovery([STANDALONE_NS], [User::class], false);

        expect($output)->not->toContain('export interface IOrderData')
            ->and($output)->not->toContain('export interface IShippingData');
    });
});
