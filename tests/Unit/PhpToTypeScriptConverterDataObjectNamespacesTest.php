<?php

use OiLab\OiLaravelTs\Services\DataObjectResolver;
use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;
use OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\AddressData;

describe('PhpToTypeScriptConverter with configurable DataObject namespaces', function () {
    beforeEach(function () {
        $resolver = new DataObjectResolver([
            'OiLab\\OiLaravelTs\\Tests\\Fixtures\\DataObjects',
        ]);
        $this->converter = new PhpToTypeScriptConverter($resolver);
    });

    it('maps a short DataObject reference inside array<int, X> to IX[]', function () {
        expect($this->converter->convertPhpDocToTs('array<int, AddressData>'))->toBe('IAddressData[]');
    });

    it('maps a short DataObject reference inside array<string, X> to Record<string, IX>', function () {
        expect($this->converter->convertPhpDocToTs('array<string, AddressData>'))->toBe('Record<string, IAddressData>');
    });

    it('maps a short DataObject reference inside array<X> to IX[]', function () {
        expect($this->converter->convertPhpDocToTs('array<AddressData>'))->toBe('IAddressData[]');
    });

    it('maps a fully qualified DataObject FQCN to its interface even without PHPDoc', function () {
        expect($this->converter->phpTypeToTypeScript(AddressData::class))->toBe('IAddressData');
    });

    it('still returns unknown for non-DataObject class references', function () {
        expect($this->converter->phpTypeToTypeScript(stdClass::class))->toBe('unknown');
    });
});
