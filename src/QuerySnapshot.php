<?php

namespace QueueSql;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class QuerySnapshot
{
    private function __construct(
        private ?string $model,
        private ?string $connection,
        private ?string $table,
        private string $whereSql,   // compiled WHERE fragment WITHOUT leading "where"
        private array $bindings,    // ordered scalar bindings for the fragment
        private string $keyName,
        private bool $fanOut,
    ) {}

    public static function capture(EloquentBuilder|QueryBuilder $builder): self
    {
        $query = $builder instanceof EloquentBuilder ? $builder->getQuery() : $builder;

        // Compile constraints to a portable SQL fragment + bindings.
        $whereSql = $query->getGrammar()->compileWheres($query);          // e.g. 'where "x" = ?'
        $whereSql = (string) preg_replace('/^where /i', '', $whereSql);   // strip keyword
        $bindings = $query->getRawBindings()['where'] ?? [];

        if ($builder instanceof EloquentBuilder) {
            $model = $builder->getModel();
            $key = $model->getKeyName();
            $fanOut = ! is_array($key)
                && $model->getIncrementing()
                && in_array($model->getKeyType(), ['int', 'integer'], true);

            return new self(
                model: get_class($model),
                connection: $query->getConnection()->getName(),
                table: $model->getTable(),
                whereSql: $whereSql,
                bindings: array_values($bindings),
                keyName: is_array($key) ? '' : $key,
                fanOut: $fanOut,
            );
        }

        return new self(
            model: null,
            connection: $query->getConnection()->getName(),
            table: $query->from,
            whereSql: $whereSql,
            bindings: array_values($bindings),
            keyName: 'id',
            fanOut: true,
        );
    }

    public function restore(): EloquentBuilder|QueryBuilder
    {
        if ($this->model !== null) {
            $builder = (new $this->model)->setConnection($this->connection)->newQuery();
        } else {
            $builder = DB::connection($this->connection)->table($this->table);
        }

        if ($this->whereSql !== '') {
            // Wrap in parens so an appended whereBetween(pk, ...) keeps AND/OR precedence.
            $builder->whereRaw('(' . $this->whereSql . ')', $this->bindings);
        }

        return $builder;
    }

    public function keyName(): string
    {
        return $this->keyName;
    }

    public function tableName(): ?string
    {
        return $this->table;
    }

    public function canFanOut(): bool
    {
        return $this->fanOut;
    }

    public function connectionName(): ?string
    {
        return $this->connection;
    }
}
