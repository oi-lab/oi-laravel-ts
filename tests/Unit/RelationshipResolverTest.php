<?php

use OiLab\OiLaravelTs\Services\Eloquent\RelationshipResolver;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Article;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Post;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Comment;

describe('RelationshipResolver', function () {
    beforeEach(function () {
        $this->resolver = new RelationshipResolver;
    });

    describe('resolveRelationships', function () {
        it('lists own, then trait, then inherited relationships whatever the PHP version', function () {
            // PHP 8.5 lists trait methods before inherited ones, PHP 8.4 after:
            // the order must not follow get_class_methods().
            $names = array_column($this->resolver->resolveRelationships(new Article), 'name');

            expect($names)->toBe(['reviewer', 'editor', 'comments', 'author']);
        });

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

            expect($relationships)->toHaveCount(3);

            $relationNames = collect($relationships)->pluck('name')->toArray();

            expect($relationNames)->toContain('user')
                ->and($relationNames)->toContain('comments')
                ->and($relationNames)->toContain('cover');
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
