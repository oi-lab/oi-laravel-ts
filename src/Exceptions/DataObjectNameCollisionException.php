<?php

namespace OiLab\OiLaravelTs\Exceptions;

use RuntimeException;

/**
 * Thrown when two distinct DataObject classes resolve to the same short name
 * (and therefore the same `I{Name}` TypeScript interface), which would cause
 * one definition to silently overwrite the other.
 */
class DataObjectNameCollisionException extends RuntimeException
{
    /**
     * @param  string  $shortName  The colliding short class name (e.g. `Address`)
     * @param  array<int, string>  $fqcns  The fully qualified class names in conflict
     */
    public function __construct(
        public readonly string $shortName,
        public readonly array $fqcns,
    ) {
        $list = implode(', ', $fqcns);

        parent::__construct(
            "DataObject name collision: multiple classes resolve to interface I{$shortName} ({$list}). ".
            'Rename one of the classes or narrow the configured dataobject_namespaces.'
        );
    }
}
