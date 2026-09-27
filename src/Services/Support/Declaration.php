<?php

namespace OiLab\OiLaravelTs\Services\Support;

/**
 * Declaration
 *
 * Opens and closes a generated declaration in the configured style.
 *
 * - `interface` (default): `export interface IFoo { ... }`.
 * - `type`: `export type IFoo = { ... };`. A type alias gets an implicit index
 *   signature where an interface never does, so it is assignable to
 *   `Record<string, T>` — what form helpers such as Inertia's `useForm` /
 *   `useHttp` require of their data. With interfaces, every consumer has to
 *   restate the shape through a mapped type first.
 */
class Declaration
{
    public const STYLE_INTERFACE = 'interface';

    public const STYLE_TYPE = 'type';

    public function __construct(
        private readonly string $style = self::STYLE_INTERFACE,
    ) {}

    /**
     * Build a declaration from the `declaration_style` config key.
     */
    public static function fromConfig(): self
    {
        $style = function_exists('config')
            ? config('oi-laravel-ts.declaration_style', self::STYLE_INTERFACE)
            : self::STYLE_INTERFACE;

        return new self($style === self::STYLE_TYPE ? self::STYLE_TYPE : self::STYLE_INTERFACE);
    }

    /**
     * The opening line of a declaration, `{` included, followed by a newline.
     */
    public function open(string $name, ?string $extends = null): string
    {
        if ($this->style === self::STYLE_TYPE) {
            return $extends === null
                ? "export type {$name} = {\n"
                : "export type {$name} = {$extends} & {\n";
        }

        return $extends === null
            ? "export interface {$name} {\n"
            : "export interface {$name} extends {$extends} {\n";
    }

    /**
     * The closing of a declaration opened by open().
     */
    public function close(): string
    {
        return $this->style === self::STYLE_TYPE ? '};' : '}';
    }
}
