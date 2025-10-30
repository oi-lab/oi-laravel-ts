<?php

use OiLab\OiLaravelTs\Services\Eloquent\CastTypeResolver;
use OiLab\OiLaravelTs\Services\Eloquent\DataObjectAnalyzer;
use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;
use OiLab\OiLaravelTs\Tests\Fixtures\Casts\AddressCast;
use OiLab\OiLaravelTs\Tests\Fixtures\Casts\MetadataCast;

describe('CastTypeResolver', function () {
    beforeEach(function () {
        $converter = new PhpToTypeScriptConverter;
        $analyzer = new DataObjectAnalyzer($converter);
        $this->resolver = new CastTypeResolver($analyzer);
    });

    describe('resolve', function () {
        it('resolves DataObject cast correctly', function () {
            $result = $this->resolver->resolve(AddressCast::class, 'address');

            expect($result)->not->toBeNull()
                ->and($result['field'])->toBe('address')
                ->and($result['relation'])->toBeFalse()
                ->and($result['isDataObject'])->toBeTrue()
                ->and($result['dataObjectClass'])->toBe(\OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\AddressData::class)
                ->and($result['properties'])->toBeArray()
                ->and($result['properties'])->toHaveCount(4);
        });

        it('resolves MetadataCast correctly', function () {
            $result = $this->resolver->resolve(MetadataCast::class, 'metadata');

            expect($result)->not->toBeNull()
                ->and($result['field'])->toBe('metadata')
                ->and($result['relation'])->toBeFalse()
                ->and($result['isDataObject'])->toBeTrue()
                ->and($result['properties'])->toBeArray()
                ->and($result['properties'])->toHaveCount(3);
        });

        it('returns null for non-cast class', function () {
            $result = $this->resolver->resolve(stdClass::class, 'field');

            expect($result)->toBeNull();
        });

        it('returns null for non-existent class', function () {
            $result = $this->resolver->resolve('NonExistentClass', 'field');

            expect($result)->toBeNull();
        });
    });
});
