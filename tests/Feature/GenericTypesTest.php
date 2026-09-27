<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;

const GENERICS_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\Generics';

function generateGenerics(): string
{
    config()->set('oi-laravel-ts.data_namespaces', [GENERICS_NS]);

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels([]);

    return (new Convert(Eloquent::getSchema(), false, false, [GENERICS_NS]))->toTypeScript();
}

describe('list-like PHPDoc generics', function () {
    it('types list<T> as T[]', function () {
        expect(generateGenerics())->toContain('keywords: string[];');
    });

    it('types non-empty-list<Dto> as IDto[]', function () {
        expect(generateGenerics())->toContain('tags: ITagData[];');
    });

    it('types Collection<int, Dto> as IDto[]', function () {
        expect(generateGenerics())->toContain('collected: ITagData[];');
    });

    it('keeps a union nested in a generic inside the array', function () {
        expect(generateGenerics())->toContain('mixedIds: (string | number)[];');
    });

    it('nests lists', function () {
        expect(generateGenerics())->toContain('grid: string[][];');
    });

    it('types a string-keyed map of lists as a record', function () {
        expect(generateGenerics())->toContain('byKey: Record<string, number[]>;');
    });

    it('types non-empty-array<string, Dto> as a record of IDto', function () {
        expect(generateGenerics())->toContain('tagsByKey: Record<string, ITagData>;');
    });

    it('types iterable<T> as T[]', function () {
        expect(generateGenerics())->toContain('numbers: number[];');
    });

    it('never falls back to unknown for these spellings', function () {
        expect(generateGenerics())->not->toContain('unknown');
    });
});
