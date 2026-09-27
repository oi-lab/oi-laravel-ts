<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use OiLab\OiLaravelTs\Tests\Fixtures\SchemaModels\Widget;

beforeEach(function () {
    Schema::create('widgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('description')->nullable();
        $table->unsignedInteger('sort')->default(0);
        $table->foreignId('owner_id')->nullable();
        $table->string('status');
        $table->unsignedTinyInteger('level')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('is_featured')->default(false);
        $table->decimal('price', 8, 2)->nullable();
        $table->json('options')->nullable();
        $table->json('raw_json')->nullable();
        $table->timestamp('seen_at')->nullable();
        $table->string('secret')->nullable();
        $table->json('settings')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Eloquent::setCustomProps([]);
    Eloquent::setDiscoverRelatedModels(false);
});

afterEach(function () {
    Eloquent::setUseDatabaseSchema(true);
});

function widgetInterface(): string
{
    Eloquent::setAdditionalModels([Widget::class]);

    $output = (new Convert(Eloquent::getSchema(), false))->toTypeScript();

    preg_match('/export interface IWidget \{.*?\n\}/s', $output, $match);

    return $match[0] ?? '';
}

describe('model attributes read from the database schema', function () {
    it('types every column, fillable or not', function () {
        $interface = widgetInterface();

        expect($interface)->toContain('description?: string | null;')
            ->and($interface)->toContain('sort: number;')
            ->and($interface)->toContain('owner_id?: number | null;');
    });

    it('keeps a non-nullable column required and non-null', function () {
        expect(widgetInterface())->toContain('name: string;');
    });

    it('turns enum casts into literal unions', function () {
        $interface = widgetInterface();

        expect($interface)->toContain("status: 'active' | 'suspended' | 'pending';")
            ->and($interface)->toContain('level?: 1 | 2 | 3 | null;');
    });

    it('types built-in casts by what they serialize to', function () {
        $interface = widgetInterface();

        expect($interface)->toContain('is_active: boolean;')
            ->and($interface)->toContain('price?: string | null;')
            ->and($interface)->toContain('options?: unknown;')
            ->and($interface)->toContain('seen_at?: string | null;');
    });

    it('types an uncast boolean column as boolean and an uncast json column as string', function () {
        $interface = widgetInterface();

        expect($interface)->toContain('is_featured: boolean;')
            ->and($interface)->toContain('raw_json?: string | null;');
    });

    it('types an untyped class cast as unknown, not as its column', function () {
        expect(widgetInterface())->toContain('settings?: unknown;');
    });

    it('leaves hidden attributes out', function () {
        expect(widgetInterface())->not->toContain('secret');
    });

    it('keeps timestamps required and deleted_at nullable', function () {
        $interface = widgetInterface();

        expect($interface)->toContain('created_at: string;')
            ->and($interface)->toContain('updated_at: string;')
            ->and($interface)->toContain('deleted_at?: string | null;');
    });

    it('types Attribute accessors from their @return annotation', function () {
        $interface = widgetInterface();

        expect($interface)->toContain('label: string;')
            ->and($interface)->toContain('slug_or_null?: string | null;');
    });

    it('types an unannotated Attribute accessor from its getter closure', function () {
        expect(widgetInterface())->toContain('closure_typed: number;');
    });

    it('never emits never for a column', function () {
        expect(widgetInterface())->not->toContain(': never');
    });
});

describe('without the database schema', function () {
    it('falls back to the fillable attributes', function () {
        Eloquent::setUseDatabaseSchema(false);

        $interface = widgetInterface();

        expect($interface)->toContain('name: string;')
            ->and($interface)->toContain("status: 'active' | 'suspended' | 'pending';")
            ->and($interface)->not->toContain('description');
    });

    it('falls back to the fillable attributes when the table does not exist', function () {
        Schema::drop('widgets');

        $interface = widgetInterface();

        expect($interface)->toContain('name: string;')
            ->and($interface)->not->toContain('description');
    });
});
