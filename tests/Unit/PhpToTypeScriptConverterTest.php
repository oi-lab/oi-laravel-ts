<?php

use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;

describe('PhpToTypeScriptConverter', function () {
    beforeEach(function () {
        $this->converter = new PhpToTypeScriptConverter;
    });

    describe('phpTypeToTypeScript', function () {
        it('converts basic PHP types to TypeScript', function () {
            expect($this->converter->phpTypeToTypeScript('int'))->toBe('number')
                ->and($this->converter->phpTypeToTypeScript('integer'))->toBe('number')
                ->and($this->converter->phpTypeToTypeScript('float'))->toBe('number')
                ->and($this->converter->phpTypeToTypeScript('double'))->toBe('number')
                ->and($this->converter->phpTypeToTypeScript('string'))->toBe('string')
                ->and($this->converter->phpTypeToTypeScript('bool'))->toBe('boolean')
                ->and($this->converter->phpTypeToTypeScript('boolean'))->toBe('boolean')
                ->and($this->converter->phpTypeToTypeScript('array'))->toBe('unknown[]')
                ->and($this->converter->phpTypeToTypeScript('mixed'))->toBe('unknown')
                ->and($this->converter->phpTypeToTypeScript('object'))->toBe('Record<string, unknown>');
        });

        it('converts array notation types', function () {
            expect($this->converter->phpTypeToTypeScript('string[]'))->toBe('string[]')
                ->and($this->converter->phpTypeToTypeScript('int[]'))->toBe('number[]')
                ->and($this->converter->phpTypeToTypeScript('bool[]'))->toBe('boolean[]');
        });

        it('handles unknown types', function () {
            expect($this->converter->phpTypeToTypeScript('CustomUnknownType'))->toBe('unknown');
        });
    });

    describe('splitUnionType', function () {
        it('splits simple union types', function () {
            $result = $this->converter->splitUnionType('string|int|null');

            expect($result)->toBe(['string', 'int', 'null']);
        });

        it('splits union types with generic arrays', function () {
            $result = $this->converter->splitUnionType('string|array<int, User>|null');

            expect($result)->toBe(['string', 'array<int, User>', 'null']);
        });

        it('splits union types with nested generics', function () {
            $result = $this->converter->splitUnionType('array<string, mixed>|null');

            expect($result)->toBe(['array<string, mixed>', 'null']);
        });

        it('handles single type without pipe', function () {
            $result = $this->converter->splitUnionType('string');

            expect($result)->toBe(['string']);
        });
    });

    describe('convertPhpDocToTs', function () {
        it('converts simple PHP types', function () {
            expect($this->converter->convertPhpDocToTs('string'))->toBe('string')
                ->and($this->converter->convertPhpDocToTs('int'))->toBe('number')
                ->and($this->converter->convertPhpDocToTs('bool'))->toBe('boolean');
        });

        it('converts union types', function () {
            expect($this->converter->convertPhpDocToTs('string|int'))->toBe('string | number')
                ->and($this->converter->convertPhpDocToTs('string|null'))->toBe('string')
                ->and($this->converter->convertPhpDocToTs('int|float|null'))->toBe('number');
        });

        it('converts array generic types', function () {
            expect($this->converter->convertPhpDocToTs('array<int, string>'))->toBe('string[]')
                ->and($this->converter->convertPhpDocToTs('array<int, int>'))->toBe('number[]');
        });

        it('converts Record types', function () {
            expect($this->converter->convertPhpDocToTs('array<string, mixed>'))->toBe('Record<string, unknown>')
                ->and($this->converter->convertPhpDocToTs('array<string, string>'))->toBe('Record<string, string>')
                ->and($this->converter->convertPhpDocToTs('array<string, int>'))->toBe('Record<string, number>');
        });

        it('converts simple array generic', function () {
            expect($this->converter->convertPhpDocToTs('array<string>'))->toBe('string[]')
                ->and($this->converter->convertPhpDocToTs('array<int>'))->toBe('number[]');
        });

        it('handles complex union types with generics', function () {
            $result = $this->converter->convertPhpDocToTs('string|array<int, string>|null');

            expect($result)->toBe('string | string[]');
        });

        it('converts union types in Record values', function () {
            $result = $this->converter->convertPhpDocToTs('array<string, string|int>');

            expect($result)->toBe('Record<string, string | number>');
        });

        it('converts union types in array elements', function () {
            $result = $this->converter->convertPhpDocToTs('array<int, string|int>');

            expect($result)->toBe('(string | number)[]');
        });
    });
});
