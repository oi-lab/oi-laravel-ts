<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Model Column Reader
 *
 * Reads a model's table columns from the live database schema, so an
 * attribute's TypeScript type and nullability follow the column rather than a
 * guess made from `$fillable` alone.
 *
 * Generation must never depend on a database being reachable: when the
 * connection fails or the table does not exist, the reader returns nothing and
 * the extractor falls back to the model's own declarations.
 */
class ModelColumnReader
{
    /**
     * Columns already read, keyed by connection and table.
     *
     * @var array<string, array<string, array{name: string, type_name: string, type: string, nullable: bool}>>
     */
    private array $cache = [];

    /**
     * The model's table columns, keyed by column name, in table order.
     *
     * @return array<string, array{name: string, type_name: string, type: string, nullable: bool}>
     */
    public function columns(Model $model): array
    {
        $table = $model->getTable();
        $connectionName = $model->getConnectionName() ?? 'default';
        $cacheKey = $connectionName.'.'.$table;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        try {
            $builder = $model->getConnection()->getSchemaBuilder();

            $columns = $builder->hasTable($table) ? $builder->getColumns($table) : [];
        } catch (Throwable) {
            $columns = [];
        }

        $keyed = [];

        foreach ($columns as $column) {
            $keyed[$column['name']] = [
                'name' => (string) $column['name'],
                'type_name' => strtolower((string) ($column['type_name'] ?? '')),
                'type' => strtolower((string) ($column['type'] ?? '')),
                'nullable' => (bool) ($column['nullable'] ?? false),
            ];
        }

        return $this->cache[$cacheKey] = $keyed;
    }
}
