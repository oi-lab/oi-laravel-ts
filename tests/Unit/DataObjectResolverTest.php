<?php

use OiLab\OiLaravelTs\Services\DataObjectResolver;
use OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\AddressData;

describe('DataObjectResolver', function () {
    describe('resolveDataObjectClass', function () {
        it('resolves a short name against the configured namespaces', function () {
            $resolver = new DataObjectResolver(['OiLab\\OiLaravelTs\\Tests\\Fixtures\\DataObjects']);

            expect($resolver->resolveDataObjectClass('AddressData'))->toBe(AddressData::class);
        });

        it('iterates over the namespaces and returns the first match', function () {
            $resolver = new DataObjectResolver([
                'App\\NonExistent',
                'OiLab\\OiLaravelTs\\Tests\\Fixtures\\DataObjects',
            ]);

            expect($resolver->resolveDataObjectClass('AddressData'))->toBe(AddressData::class);
        });

        it('accepts a fully qualified class name', function () {
            $resolver = new DataObjectResolver([]);

            expect($resolver->resolveDataObjectClass(AddressData::class))->toBe(AddressData::class);
        });

        it('returns null when the class is not a DataObject', function () {
            $resolver = new DataObjectResolver([]);

            expect($resolver->resolveDataObjectClass(stdClass::class))->toBeNull();
        });

        it('returns null when the class cannot be located in any namespace', function () {
            $resolver = new DataObjectResolver(['App\\Nope']);

            expect($resolver->resolveDataObjectClass('Missing'))->toBeNull();
        });

        it('reads the configured namespaces by default', function () {
            config()->set('oi-laravel-ts.dataobject_namespaces', [
                'OiLab\\OiLaravelTs\\Tests\\Fixtures\\DataObjects',
            ]);

            $resolver = new DataObjectResolver;

            expect($resolver->resolveDataObjectClass('AddressData'))->toBe(AddressData::class);
        });
    });

    describe('isDataObject', function () {
        it('detects DataObjects regardless of namespace', function () {
            $resolver = new DataObjectResolver([]);

            expect($resolver->isDataObject(AddressData::class))->toBeTrue()
                ->and($resolver->isDataObject(stdClass::class))->toBeFalse()
                ->and($resolver->isDataObject('Missing\\Class'))->toBeFalse();
        });
    });

    describe('getNamespaces', function () {
        it('normalizes the configured namespaces', function () {
            $resolver = new DataObjectResolver(['\\App\\Foo\\', '\\App\\Foo\\', 'App\\Bar']);

            expect($resolver->getNamespaces())->toBe(['App\\Foo', 'App\\Bar']);
        });
    });
});
