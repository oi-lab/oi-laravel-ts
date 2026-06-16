<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Data;

use OiLab\OiLaravelTs\Tests\Fixtures\Data\Nested\GeoData;
use OiLab\OiLaravelTs\Tests\Fixtures\Enums\UserLevel;
use OiLab\OiLaravelTs\Tests\Fixtures\Enums\UserStatus;
use OiLab\OiLaravelTs\Tests\Fixtures\Models\User;

/**
 * Top-level DTO covering the spatie/laravel-data feature surface:
 * camelCase props, nullable, default value, backed enums, a nested DTO and a
 * typed array declared through a property `@var` annotation.
 */
class UserData
{
    public function __construct(
        public readonly string $id,
        public readonly string $fullName,
        public readonly ?int $age,
        public readonly bool $isActive,
        public readonly UserStatus $status,
        public readonly UserLevel $level,
        public readonly ?GeoData $address,
        /** @var TagData[]|null */
        public readonly ?array $tags,
        /** @var array<int, string>|null */
        public readonly ?array $roles,
        public readonly string $version = 'v1',
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: (string) $user->id,
            fullName: $user->name,
            age: $user->age,
            isActive: true,
            status: UserStatus::Active,
            level: UserLevel::Bronze,
            address: null,
            tags: null,
            roles: null,
        );
    }
}
