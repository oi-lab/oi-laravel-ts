<?php

use OiLab\OiLaravelTs\Exceptions\DataObjectNameCollisionException;
use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\DataClassResolver;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Aliases\Vendor\ContentData as VendorContentData;

const ALIASES_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\Aliases';

function generateAliased(array $aliases): string
{
    config()->set('oi-laravel-ts.data_namespaces', [ALIASES_NS]);
    config()->set('oi-laravel-ts.data_aliases', $aliases);

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels([]);

    return (new Convert(
        Eloquent::getSchema(),
        false,
        false,
        [ALIASES_NS],
        false,
        [],
        'null',
        $aliases,
    ))->toTypeScript();
}

describe('data_aliases', function () {
    it('aborts on a short-name collision when no alias is configured', function () {
        generateAliased([]);
    })->throws(DataObjectNameCollisionException::class);

    it('points at data_aliases in the collision message', function () {
        expect(fn () => generateAliased([]))
            ->toThrow(DataObjectNameCollisionException::class, 'data_aliases');
    });

    it('emits the aliased DTO under its alias and leaves the plain name to the other', function () {
        $output = generateAliased([VendorContentData::class => 'VendorContentData']);

        expect($output)->toContain('export interface IVendorContentData')
            ->and($output)->toContain('html: string;')
            ->and($output)->toContain('export interface IContentData')
            ->and($output)->toContain('body: string;');
    });

    it('uses the alias for nested references from another DTO', function () {
        $output = generateAliased([VendorContentData::class => 'VendorContentData']);

        expect($output)->toContain('localContent: IContentData;')
            ->and($output)->toContain('vendorContent: IVendorContentData;');
    });

    it('emits each colliding DTO exactly once', function () {
        $output = generateAliased([VendorContentData::class => 'VendorContentData']);

        expect(substr_count($output, 'export interface IContentData {'))->toBe(1)
            ->and(substr_count($output, 'export interface IVendorContentData {'))->toBe(1);
    });

    it('resolves an aliased DTO by its alias and by its FQCN', function () {
        $resolver = new DataClassResolver(
            [ALIASES_NS],
            [],
            [VendorContentData::class => 'VendorContentData'],
        );

        expect($resolver->resolveDataClass('VendorContentData'))->toBe(VendorContentData::class)
            ->and($resolver->interfaceName(VendorContentData::class))->toBe('IVendorContentData')
            ->and($resolver->shortName(VendorContentData::class))->toBe('VendorContentData');
    });
});
