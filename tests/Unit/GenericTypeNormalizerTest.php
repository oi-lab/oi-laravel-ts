<?php

use OiLab\OiLaravelTs\Support\GenericTypeNormalizer;

it('normalizes list-like generics onto array<int, T>', function (string $input, string $expected) {
    expect(GenericTypeNormalizer::normalize($input))->toBe($expected);
})->with([
    ['list<string>', 'array<int, string>'],
    ['non-empty-list<FooData>', 'array<int, FooData>'],
    ['iterable<int>', 'array<int, int>'],
    ['Collection<int, FooData>', 'array<int, FooData>'],
    ['\\Illuminate\\Support\\Collection<FooData>', 'array<int, FooData>'],
    ['non-empty-array<string, FooData>', 'array<string, FooData>'],
    ['array<array-key, Foo>', 'array<int, Foo>'],
    ['list<array<string, mixed>>', 'array<int, array<string, mixed>>'],
]);

it('leaves other types untouched', function (string $input) {
    expect(GenericTypeNormalizer::normalize($input))->toBe($input);
})->with([
    'string',
    'FooData[]',
    'array<string, mixed>',
    'Foo<Bar>',
]);
