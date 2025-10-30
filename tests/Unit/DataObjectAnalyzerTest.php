<?php

use OiLab\OiLaravelTs\Services\Eloquent\DataObjectAnalyzer;
use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;
use OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\AddressData;
use OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\MetadataData;

describe('DataObjectAnalyzer', function () {
    beforeEach(function () {
        $converter = new PhpToTypeScriptConverter;
        $this->analyzer = new DataObjectAnalyzer($converter);
    });

    describe('isDataObject', function () {
        it('identifies valid DataObjects', function () {
            $reflection = new ReflectionClass(AddressData::class);

            expect($this->analyzer->isDataObject($reflection))->toBeTrue();
        });

        it('identifies classes without fromArray', function () {
            $reflection = new ReflectionClass(stdClass::class);

            expect($this->analyzer->isDataObject($reflection))->toBeFalse();
        });
    });

    describe('extractProperties', function () {
        it('extracts properties from AddressData', function () {
            $reflection = new ReflectionClass(AddressData::class);
            $properties = $this->analyzer->extractProperties($reflection);

            expect($properties)->toHaveCount(4)
                ->and($properties[0])->toMatchArray([
                    'name' => 'street',
                    'type' => 'string',
                    'nullable' => false,
                    'hasDefault' => false,
                ])
                ->and($properties[1])->toMatchArray([
                    'name' => 'city',
                    'type' => 'string',
                    'nullable' => false,
                    'hasDefault' => false,
                ])
                ->and($properties[2])->toMatchArray([
                    'name' => 'state',
                    'type' => 'string',
                    'nullable' => true,
                    'hasDefault' => true,
                ])
                ->and($properties[3])->toMatchArray([
                    'name' => 'zipCode',
                    'type' => 'string',
                    'nullable' => false,
                    'hasDefault' => true,
                ]);
        });

        it('extracts properties with PHPDoc types from MetadataData', function () {
            $reflection = new ReflectionClass(MetadataData::class);
            $properties = $this->analyzer->extractProperties($reflection);

            expect($properties)->toHaveCount(3)
                ->and($properties[0])->toMatchArray([
                    'name' => 'title',
                    'type' => 'string',
                    'nullable' => false,
                    'hasDefault' => false,
                ])
                ->and($properties[1])->toMatchArray([
                    'name' => 'description',
                    'type' => 'string',
                    'nullable' => true,
                    'hasDefault' => true,
                ])
                ->and($properties[2])->toMatchArray([
                    'name' => 'tags',
                    'type' => 'string[]',
                    'nullable' => false,
                    'hasDefault' => true,
                ]);
        });

        it('returns empty array for class without constructor', function () {
            $reflection = new ReflectionClass(stdClass::class);
            $properties = $this->analyzer->extractProperties($reflection);

            expect($properties)->toBeEmpty();
        });
    });
});
