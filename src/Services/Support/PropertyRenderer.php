<?php

namespace OiLab\OiLaravelTs\Services\Support;

/**
 * Property Renderer
 *
 * Renders a single TypeScript property line for a DTO or DataObject interface.
 *
 * A generated `I{X}Data` interface describes the JSON a DTO *produces*, not the
 * arguments its constructor *accepts*. Serializers emit every declared property,
 * so two independent axes must stay separate:
 *
 *   | Notation           | Meaning                                    |
 *   | ------------------ | ------------------------------------------ |
 *   | `name?: T`         | the key may be **absent** from the payload |
 *   | `name: T \| null`  | the key is present, the value may be null  |
 *
 * Therefore `| null` is emitted if and only if the property accepts null, and
 * `?` if and only if the property can genuinely be missing — which, for a DTO,
 * means it is declared through an `Optional` / `Lazy` marker. Having a default
 * value makes nothing optional on the output side.
 *
 * The legacy `optional` style collapses both axes onto `?` and never emits
 * `| null`. It is kept behind the `data_nullable_style` config key so existing
 * consumers (typically `useForm` inputs typed from a DTO interface) can migrate
 * on their own schedule.
 */
class PropertyRenderer
{
    /**
     * Distinguish absence (`?`) from nullity (`| null`). The default.
     */
    public const STYLE_NULL = 'null';

    /**
     * Legacy: mark nullable *and* defaulted properties as `?`, never emit `| null`.
     */
    public const STYLE_OPTIONAL = 'optional';

    public function __construct(private readonly string $style = self::STYLE_NULL) {}

    /**
     * Render a property as a TypeScript interface member.
     *
     * @param  array{name: string, type: string, nullable: bool, hasDefault: bool, optional?: bool}  $property
     */
    public function render(array $property): string
    {
        $name = $property['name'];
        $type = $property['type'];
        $optional = $property['optional'] ?? false;

        if ($this->style === self::STYLE_OPTIONAL) {
            $marker = ($property['nullable'] || $property['hasDefault'] || $optional) ? '?' : '';

            return "{$name}{$marker}: {$type};";
        }

        $marker = $optional ? '?' : '';

        // `unknown` already subsumes null; `unknown | null` would only add noise.
        $suffix = ($property['nullable'] && $type !== 'unknown') ? ' | null' : '';

        return "{$name}{$marker}: {$type}{$suffix};";
    }

    public function getStyle(): string
    {
        return $this->style;
    }
}
