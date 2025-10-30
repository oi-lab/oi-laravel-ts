<?php

namespace OiLab\OiLaravelTs\Services\Generators;

/**
 * JSON-LD Generator
 *
 * Generates TypeScript interface for JSON-LD data structures.
 * Provides the JsonLdRawNode interface needed for handling JSON-LD formatted data.
 */
class JsonLdGenerator
{
    /**
     * Generate the JsonLdRawNode TypeScript interface.
     *
     * Creates a TypeScript interface that represents the raw JSON-LD node
     * structure as received from the backend, with @ properties preserved.
     *
     * The interface includes:
     * - @type: The RDF type(s) of the resource
     * - @id: The IRI identifier of the resource
     * - @context: The JSON-LD context
     * - @graph: Optional graph of related nodes
     * - Index signature for additional properties
     *
     * Example usage in TypeScript:
     * ```typescript
     * const node: JsonLdRawNode = {
     *   '@type': 'Person',
     *   '@id': 'https://example.com/person/123',
     *   '@context': 'https://schema.org',
     *   'name': 'John Doe',
     *   'email': 'john@example.com'
     * };
     * ```
     *
     * @return string The TypeScript interface definition
     */
    public function generate(): string
    {
        $output = "\n\n";
        $output .= "/**\n";
        $output .= " * Raw JSON-LD Node (as received from backend with @ properties)\n";
        $output .= " *\n";
        $output .= " * This represents the actual JSON structure sent by the backend.\n";
        $output .= " * JSON-LD is a method of encoding Linked Data using JSON.\n";
        $output .= " *\n";
        $output .= " * @see https://json-ld.org/\n";
        $output .= " *\n";
        $output .= " * @example\n";
        $output .= " * ```typescript\n";
        $output .= " * const person: JsonLdRawNode = {\n";
        $output .= " *   '@type': 'Person',\n";
        $output .= " *   '@id': 'https://example.com/person/123',\n";
        $output .= " *   'name': 'John Doe'\n";
        $output .= " * };\n";
        $output .= " * ```\n";
        $output .= " */\n";
        $output .= "export interface JsonLdRawNode {\n";
        $output .= "    /**\n";
        $output .= "     * The RDF type of the resource.\n";
        $output .= "     * Can be a single type or an array of types.\n";
        $output .= "     */\n";
        $output .= "    '@type'?: string | string[];\n\n";
        $output .= "    /**\n";
        $output .= "     * The IRI (Internationalized Resource Identifier) of the resource.\n";
        $output .= "     * Uniquely identifies this JSON-LD node.\n";
        $output .= "     */\n";
        $output .= "    '@id'?: string;\n\n";
        $output .= "    /**\n";
        $output .= "     * The JSON-LD context.\n";
        $output .= "     * Defines how terms in the document map to IRIs.\n";
        $output .= "     */\n";
        $output .= "    '@context'?: string | Record<string, unknown> | Array<string | Record<string, unknown>>;\n\n";
        $output .= "    /**\n";
        $output .= "     * A graph of related JSON-LD nodes.\n";
        $output .= "     * Used for representing multiple related resources.\n";
        $output .= "     */\n";
        $output .= "    '@graph'?: JsonLdRawNode[];\n\n";
        $output .= "    /**\n";
        $output .= "     * Index signature to allow any additional properties.\n";
        $output .= "     * JSON-LD nodes can contain arbitrary properties beyond the standard @ properties.\n";
        $output .= "     */\n";
        $output .= "    [key: string]: unknown;\n";
        $output .= "}\n\n";

        return $output;
    }

    /**
     * Check if JSON-LD support is needed based on schema.
     *
     * Scans the schema to determine if any models use JsonLdData type,
     * which would require the JsonLdRawNode interface.
     *
     * @param array<string, array{
     *     model: string,
     *     types: array<int, array{
     *         type: string,
     *         isDataObject?: bool
     *     }>
     * }> $schema The complete schema definition
     * @return bool True if JSON-LD support is needed
     */
    public function isNeededInSchema(array $schema): bool
    {
        foreach ($schema as $model) {
            foreach ($model['types'] as $field) {
                if (isset($field['isDataObject']) && $field['isDataObject']) {
                    $dataObjectName = str_replace('[]', '', $field['type']);
                    if ($dataObjectName === 'JsonLdData') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
