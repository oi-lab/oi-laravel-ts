<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Discriminated\BlockData;
use OiLab\OiLaravelTs\Tests\Fixtures\Discriminated\BlockPropsRegistry;
use OiLab\OiLaravelTs\Tests\Fixtures\Discriminated\GridPropsData;
use OiLab\OiLaravelTs\Tests\Fixtures\Discriminated\HeroPropsData;

const DISCRIMINATED_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\Discriminated';

function generateDiscriminated(mixed $map): string
{
    config()->set('oi-laravel-ts.data_namespaces', [DISCRIMINATED_NS]);
    config()->set('oi-laravel-ts.data_discriminators', [
        BlockData::class => [
            'discriminant' => 'template_key',
            'property' => 'props',
            'map' => $map,
        ],
    ]);

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels([]);

    return (new Convert(Eloquent::getSchema(), false, false, [DISCRIMINATED_NS]))->toTypeScript();
}

$literalMap = [
    'hero' => HeroPropsData::class,
    'grid' => GridPropsData::class,
];

it('emits the shared properties as a base', function () use ($literalMap) {
    $output = generateDiscriminated($literalMap);

    preg_match('/export interface IBlockDataBase \{.*?\n\}/s', $output, $match);

    expect($match[0] ?? '')->toContain('id: number;')
        ->and($match[0])->toContain('name: string | null;')
        ->and($match[0])->not->toContain('template_key')
        ->and($match[0])->not->toContain('props');
});

it('emits one union member per discriminant value', function () use ($literalMap) {
    expect(generateDiscriminated($literalMap))->toContain(
        "export type IBlockData = IBlockDataBase & (\n"
        ."    | { template_key: 'hero'; props: IHeroPropsData }\n"
        ."    | { template_key: 'grid'; props: IGridPropsData }\n"
        .');'
    );
});

it('emits the discriminant map', function () use ($literalMap) {
    expect(generateDiscriminated($literalMap))->toContain(
        "export type IBlockDataPropsMap = {\n"
        ."    'hero': IHeroPropsData;\n"
        ."    'grid': IGridPropsData;\n"
        .'};'
    );
});

it('drops the Record<string, unknown> member that absorbed the union', function () use ($literalMap) {
    expect(generateDiscriminated($literalMap))->not->toContain('Record<string, unknown>');
});

it('emits the mapped DTOs', function () use ($literalMap) {
    $output = generateDiscriminated($literalMap);

    expect($output)->toContain('export interface IHeroPropsData {')
        ->and($output)->toContain('export interface IGridPropsData {');
});

it('resolves the map from a callable at generation time', function () {
    expect(generateDiscriminated([BlockPropsRegistry::class, 'propsClasses']))
        ->toContain("    | { template_key: 'grid'; props: IGridPropsData }\n");
});

it('falls back to a plain interface when the map is empty', function () {
    $output = generateDiscriminated([]);

    expect($output)->toContain('export interface IBlockData {')
        ->and($output)->not->toContain('IBlockDataBase');
});
