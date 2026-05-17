<?php

use OiLab\OiLaravelTs\Services\Converters\TypeScriptTypeConverter;

describe('TypeScriptTypeConverter', function () {
    beforeEach(function () {
        $this->converter = new TypeScriptTypeConverter;
    });

    describe('convertColumnType', function () {
        it('maps Laravel column types to TypeScript types', function (string $columnType, string $expected) {
            expect($this->converter->convertColumnType($columnType))->toBe($expected);
        })->with([
            ['string', 'string'],
            ['integer', 'number'],
            ['boolean', 'boolean'],
            ['datetime', 'string'],
            ['json', 'Record<string, never>'],
            ['unknown_type', 'never'],
        ]);

        it('passes native TypeScript types through untouched', function (string $tsType) {
            expect($this->converter->convertColumnType($tsType))->toBe($tsType);
        })->with([
            'number[]',
            'string[]',
            'boolean[]',
            'string | number',
            'Record<string, number>',
        ]);
    });
});
