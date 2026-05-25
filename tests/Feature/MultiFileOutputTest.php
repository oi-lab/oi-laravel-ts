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

function standardSchema(): array
{
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

    return Eloquent::getSchema();
}

beforeEach(function () {
    $this->outDir = sys_get_temp_dir().'/oi-ts-multi-'.uniqid();
});

afterEach(function () {
    if (is_dir($this->outDir)) {
        array_map('unlink', glob($this->outDir.'/*'));
        rmdir($this->outDir);
    }
});

describe('multi-file output', function () {
    it('writes one file per interface plus an index barrel', function () {
        (new Convert(standardSchema(), false))->generateFiles($this->outDir);

        $files = array_map('basename', glob($this->outDir.'/*.ts'));
        sort($files);

        expect($files)->toBe([
            'attachment.ts',
            'comment.ts',
            'event.ts',
            'index.ts',
            'membership.ts',
            'post.ts',
            'role.ts',
            'user.ts',
        ]);
    });

    it('resolves cross-references as relative type imports', function () {
        (new Convert(standardSchema(), false))->generateFiles($this->outDir);

        $user = file_get_contents($this->outDir.'/user.ts');

        expect($user)->toContain("import type { IPost } from './post';")
            ->and($user)->toContain("import type { IRole } from './role';")
            ->and($user)->toContain('export interface IUser {');
    });

    it('imports both interfaces of a pivot intersection', function () {
        (new Convert(standardSchema(), false))->generateFiles($this->outDir);

        $user = file_get_contents($this->outDir.'/user.ts');

        // memberships?: (IRole & { pivot?: IMembership })[]
        expect($user)->toContain("import type { IMembership } from './membership';")
            ->and($user)->toContain("import type { IRole } from './role';");
    });

    it('does not import an interface into its own file', function () {
        (new Convert(standardSchema(), false))->generateFiles($this->outDir);

        $role = file_get_contents($this->outDir.'/role.ts');

        // Role references IUser (users?: IUser[]) but never itself.
        expect($role)->toContain("import type { IUser } from './user';")
            ->and($role)->not->toContain("from './role'");
    });

    it('re-exports every interface from the index barrel', function () {
        (new Convert(standardSchema(), false))->generateFiles($this->outDir);

        $index = file_get_contents($this->outDir.'/index.ts');

        expect($index)->toContain("export * from './user';")
            ->and($index)->toContain("export * from './post';")
            ->and($index)->toContain("export * from './membership';");
    });

    it('emits JsonLdRawNode as its own file when JSON-LD is enabled', function () {
        (new Convert(standardSchema(), true))->generateFiles($this->outDir);

        expect(file_exists($this->outDir.'/json-ld-raw-node.ts'))->toBeTrue();

        $jsonLd = file_get_contents($this->outDir.'/json-ld-raw-node.ts');
        expect($jsonLd)->toContain('export interface JsonLdRawNode {');
    });

    it('uses a custom barrel file name when specified', function () {
        (new Convert(standardSchema(), false))->generateFiles($this->outDir, 'interfaces.ts');

        expect(file_exists($this->outDir.'/interfaces.ts'))->toBeTrue()
            ->and(file_exists($this->outDir.'/index.ts'))->toBeFalse();

        $barrel = file_get_contents($this->outDir.'/interfaces.ts');
        expect($barrel)->toContain("export * from './user';");
    });

    it('re-emits external imports only in the files that use them', function () {
        Eloquent::setCustomProps([
            'User' => [
                'external_meta' => '@/types/user-meta|UserMeta',
            ],
        ]);
        Eloquent::setWithCounts(true);
        Eloquent::setDiscoverRelatedModels(false);
        Eloquent::setAdditionalModels([User::class, Post::class]);

        (new Convert(Eloquent::getSchema(), false))->generateFiles($this->outDir);

        $user = file_get_contents($this->outDir.'/user.ts');
        $post = file_get_contents($this->outDir.'/post.ts');

        expect($user)->toContain("import type { UserMeta } from '@/types/user-meta';")
            ->and($user)->toContain('external_meta: UserMeta;')
            ->and($post)->not->toContain('UserMeta');
    });
});
