<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Services\Support\PropertyRenderer;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;

const NULLABILITY_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\Nullability';

function generateNullability(string $style = PropertyRenderer::STYLE_NULL, array $models = []): string
{
    config()->set('oi-laravel-ts.data_namespaces', [NULLABILITY_NS]);
    config()->set('oi-laravel-ts.data_nullable_style', $style);

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels($models);

    return (new Convert(
        Eloquent::getSchema(),
        false,
        false,
        [NULLABILITY_NS],
        false,
        [],
        $style,
    ))->toTypeScript();
}

describe('DTO nullability describes the produced JSON', function () {
    it('emits `| null` for a nullable property with a null default', function () {
        expect(generateNullability())->toContain('a: string | null;');
    });

    it('emits `| null` for a nullable property without a default', function () {
        expect(generateNullability())->toContain('b: string | null;');
    });

    it('emits a bare type for a required non-nullable property', function () {
        expect(generateNullability())->toContain('c: string;');
    });

    it('does not mark a defaulted non-nullable scalar as optional or nullable', function () {
        $output = generateNullability();

        expect($output)->toContain('d: number;')
            ->and($output)->not->toContain('d?:')
            ->and($output)->not->toContain('d: number | null;');
    });

    it('types a defaulted array of DTOs as a required IFoo[]', function () {
        $output = generateNullability();

        expect($output)->toContain('e: IFooData[];')
            ->and($output)->toContain('export interface IFooData');
    });

    it('emits `IFoo | null` for a nullable nested DTO', function () {
        expect(generateNullability())->toContain('f: IFooData | null;');
    });

    it('marks an Optional-declared property as absent-able, never nullable', function () {
        $output = generateNullability();

        expect($output)->toContain('g?: string;')
            ->and($output)->not->toContain('g?: string | null;');
    });

    it('marks a Lazy-declared property as absent-able', function () {
        expect(generateNullability())->toContain('lazyCount?: number;');
    });

    it('keeps the null member of a native union out of the type and on the suffix', function () {
        expect(generateNullability())->toContain('h: IAlphaData | IBetaData | null;');
    });

    it('renders a @param union without null as a plain union', function () {
        $output = generateNullability();

        expect($output)->toContain('props: IAlphaData | IBetaData | Record<string, unknown>;')
            ->and($output)->not->toContain('props?:');
    });

    it('leaves model interfaces on their own convention', function () {
        $output = generateNullability(models: [Post::class]);

        expect($output)->toContain('deleted_at?: string | null;');
    });
});

describe('legacy `optional` style', function () {
    it('collapses nullable and defaulted properties back onto `?`', function () {
        $output = generateNullability(PropertyRenderer::STYLE_OPTIONAL);

        expect($output)->toContain('a?: string;')
            ->and($output)->toContain('b?: string;')
            ->and($output)->toContain('d?: number;')
            ->and($output)->toContain('e?: IFooData[];')
            ->and($output)->toContain('f?: IFooData;')
            ->and($output)->not->toContain('| null;');
    });

    it('still requires a non-nullable, non-defaulted property', function () {
        expect(generateNullability(PropertyRenderer::STYLE_OPTIONAL))->toContain('c: string;');
    });
});
