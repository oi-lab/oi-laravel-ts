<?php

use OiLab\OiLaravelTs\Services\Support\InterfaceNaming;

it('converts interface names to kebab-case file names', function (string $interface, string $expected) {
    expect(InterfaceNaming::toFileName($interface))->toBe($expected);
})->with([
    'simple interface' => ['IUser', 'user'],
    'compound name' => ['IUserType', 'user-type'],
    'three words' => ['IOrderLineData', 'order-line-data'],
    'no I prefix kept as-is' => ['JsonLdRawNode', 'json-ld-raw-node'],
    'leading acronym' => ['IAPIToken', 'api-token'],
    'single letter after I' => ['IX', 'x'],
]);
