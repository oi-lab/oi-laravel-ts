<?php

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Attachment;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Comment;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Event;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Membership;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Role;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;

/**
 * Regression safety net (action map Phase 0).
 *
 * Captures the full generated interfaces.ts for the standard fixture set and
 * compares it byte-for-byte against a committed baseline. The `@generated`
 * timestamp is masked before comparison since it changes on every run.
 *
 * This snapshot MUST stay identical after Phase 1 (discovery) and Phase 2
 * (multi-file) refactors when run in single-file mode with discovery off.
 */
function normalizeGeneratedHeader(string $output): string
{
    return preg_replace('/@generated .*/', '@generated <normalized>', $output);
}

it('generates the expected interfaces.ts for the standard fixture set', function () {
    Eloquent::setCustomProps([]);
    Eloquent::setWithCounts(true);
    Eloquent::setDiscoverRelatedModels(false);
    Eloquent::setAdditionalModels([
        User::class,
        Post::class,
        Comment::class,
        Role::class,
        Membership::class,
        Attachment::class,
        Event::class,
    ]);

    $output = normalizeGeneratedHeader((new Convert(Eloquent::getSchema(), false))->toTypeScript());

    $baselinePath = __DIR__.'/../Snapshots/interfaces.baseline.ts';

    if (! file_exists($baselinePath)) {
        if (! is_dir(dirname($baselinePath))) {
            mkdir(dirname($baselinePath), 0755, true);
        }
        file_put_contents($baselinePath, $output);
    }

    expect($output)->toBe(file_get_contents($baselinePath));
});
