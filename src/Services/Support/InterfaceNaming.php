<?php

namespace OiLab\OiLaravelTs\Services\Support;

/**
 * Centralizes the interface-name ↔ file-name mapping used by the multi-file
 * writer. Both the written file name and every relative import path must agree,
 * so the conversion lives in a single place to avoid divergence.
 */
class InterfaceNaming
{
    /**
     * Convert a TypeScript interface name to its kebab-case file base name.
     *
     * Examples:
     * - IUser           → user
     * - IUserType       → user-type
     * - JsonLdRawNode   → json-ld-raw-node
     * - IAPIToken       → api-token
     *
     * A single leading `I` followed by an uppercase letter is treated as the
     * interface-naming convention and stripped (IUser → User). Names that do
     * not follow that convention (e.g. JsonLdRawNode) are kept as-is.
     */
    public static function toFileName(string $interfaceName): string
    {
        $name = self::stripInterfacePrefix($interfaceName);

        $name = preg_replace(
            ['/([a-z\d])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/'],
            '$1-$2',
            $name
        );

        return strtolower($name);
    }

    /**
     * Strip a leading `I` when it is immediately followed by another uppercase
     * letter (the `IUser` interface convention). Returns the name untouched
     * otherwise.
     */
    private static function stripInterfacePrefix(string $interfaceName): string
    {
        if (preg_match('/^I[A-Z]/', $interfaceName)) {
            return substr($interfaceName, 1);
        }

        return $interfaceName;
    }
}
