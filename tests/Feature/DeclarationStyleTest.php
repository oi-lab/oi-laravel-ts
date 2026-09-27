<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;

const DECLARATION_NS = 'OiLab\\OiLaravelTs\\Tests\\Fixtures\\Nullability';

function generateWithStyle(?string $style): string
{
    if ($style !== null) {
        config()->set('oi-laravel-ts.declaration_style', $style);
    }

    config()->set('oi-laravel-ts.data_namespaces', [DECLARATION_NS]);

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels([Post::class]);

    return (new Convert(Eloquent::getSchema(), false, false, [DECLARATION_NS]))->toTypeScript();
}

it('declares interfaces by default', function () {
    $output = generateWithStyle(null);

    expect($output)->toContain('export interface IPost {')
        ->and($output)->toContain('export interface ISignatureData {')
        ->and($output)->not->toContain('export type I');
});

it('declares type aliases in the type style, for models and DTOs alike', function () {
    $output = generateWithStyle('type');

    expect($output)->toContain('export type IPost = {')
        ->and($output)->toContain('export type ISignatureData = {')
        ->and($output)->toContain('export type IFooData = {')
        ->and($output)->not->toMatch('/export interface I(Post|SignatureData|FooData)\b/');
});

it('closes each type alias with a semicolon', function () {
    $output = generateWithStyle('type');

    preg_match('/export type IFooData = \{.*?\n\};/s', $output, $match);

    expect($match)->not->toBeEmpty();
});

it('falls back to interfaces for an unknown style', function () {
    expect(generateWithStyle('class'))->toContain('export interface IPost {');
});
