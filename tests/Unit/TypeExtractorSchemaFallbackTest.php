<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OiLab\OiLaravelTs\Services\Eloquent\CastTypeResolver;
use OiLab\OiLaravelTs\Services\Eloquent\DataObjectAnalyzer;
use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;
use OiLab\OiLaravelTs\Services\Eloquent\RelationshipResolver;
use OiLab\OiLaravelTs\Services\Eloquent\TypeExtractor;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\GuardedModel;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\Role;

describe('TypeExtractor schema fallback for guarded models', function () {
    beforeEach(function () {
        $converter = new PhpToTypeScriptConverter;
        $analyzer = new DataObjectAnalyzer($converter);

        $this->extractor = new TypeExtractor(
            new CastTypeResolver($analyzer),
            new RelationshipResolver,
            $converter,
        );

        Schema::create('guarded_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    });

    afterEach(function () {
        Schema::dropIfExists('guarded_models');
    });

    it('emits database columns when the model relies on $guarded', function () {
        $fields = $this->extractor->extractTypes(GuardedModel::class)->pluck('field')->all();

        expect($fields)->toContain('id', 'name', 'guard_name', 'is_active', 'created_at', 'updated_at');
    });

    it('applies casts to schema-derived columns', function () {
        $isActive = $this->extractor->extractTypes(GuardedModel::class)->firstWhere('field', 'is_active');

        expect($isActive['type'])->toBe('boolean');
    });

    it('does not duplicate the primary key or timestamp columns', function () {
        $fields = $this->extractor->extractTypes(GuardedModel::class)->pluck('field')->all();
        $counts = array_count_values($fields);

        expect($counts['id'])->toBe(1)
            ->and($counts['created_at'])->toBe(1)
            ->and($counts['updated_at'])->toBe(1);
    });

    it('still prefers explicit $fillable over schema introspection', function () {
        // Role declares $fillable = ['name', 'slug'] and has no table here, so a
        // non-empty fillable must short-circuit before any schema lookup.
        $fields = $this->extractor->extractTypes(Role::class)->pluck('field')->all();

        expect($fields)->toContain('name', 'slug')
            ->and($fields)->not->toContain('guard_name');
    });
});
