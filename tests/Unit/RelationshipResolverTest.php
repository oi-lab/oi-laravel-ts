<?php

use OiLab\OiLaravelTs\Services\Eloquent\RelationshipResolver;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Comment;

describe('RelationshipResolver', function () {
    beforeEach(function () {
        $this->resolver = new RelationshipResolver;
    });

    describe('resolveRelationships', function () {
        it('resolves HasMany relationships', function () {
            $user = new User;
            $relationships = $this->resolver->resolveRelationships($user);

            $postsRelation = collect($relationships)->firstWhere('name', 'posts');

            expect($postsRelation)->not->toBeNull()
                ->and($postsRelation['type'])->toBe('HasMany')
                ->and($postsRelation['model'])->toBe(Post::class);
        });

        it('resolves BelongsToMany relationships with pivot', function () {
            $user = new User;
            $relationships = $this->resolver->resolveRelationships($user);

            $rolesRelation = collect($relationships)->firstWhere('name', 'roles');

            expect($rolesRelation)->not->toBeNull()
                ->and($rolesRelation['type'])->toBe('BelongsToMany')
                ->and($rolesRelation)->toHaveKey('pivot');
        });

        it('resolves BelongsTo relationships', function () {
            $post = new Post;
            $relationships = $this->resolver->resolveRelationships($post);

            $userRelation = collect($relationships)->firstWhere('name', 'user');

            expect($userRelation)->not->toBeNull()
                ->and($userRelation['type'])->toBe('BelongsTo')
                ->and($userRelation['model'])->toBe(User::class);
        });

        it('resolves multiple relationships on same model', function () {
            $post = new Post;
            $relationships = $this->resolver->resolveRelationships($post);

            expect($relationships)->toHaveCount(2);

            $relationNames = collect($relationships)->pluck('name')->toArray();

            expect($relationNames)->toContain('user')
                ->and($relationNames)->toContain('comments');
        });

        it('resolves relationships for model with multiple BelongsTo', function () {
            $comment = new Comment;
            $relationships = $this->resolver->resolveRelationships($comment);

            expect($relationships)->toHaveCount(2);

            $postRelation = collect($relationships)->firstWhere('name', 'post');
            $userRelation = collect($relationships)->firstWhere('name', 'user');

            expect($postRelation)->not->toBeNull()
                ->and($postRelation['type'])->toBe('BelongsTo')
                ->and($userRelation)->not->toBeNull()
                ->and($userRelation['type'])->toBe('BelongsTo');
        });
    });
});
