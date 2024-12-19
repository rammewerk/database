<?php

namespace Rammewerk\Component\Database\Schema;

use LogicException;
use ReflectionClass;

readonly class ColumnAttributes {


    public function __construct(
        private ColumnSchema $column
    ) {
    }


    /** Sets column as unsigned if numeric type; throws exception otherwise. */
    public function unsigned(): static {
        $this->column->unsigned = match ($this->column->type) {
            ColumnType::INT, ColumnType::TINYINT, ColumnType::BIGINT, ColumnType::DECIMAL => true,
            default => throw new LogicException( 'Cannot assign unsigned to non-numeric column type' )
        };
        return $this;
    }


    /** Marks column as not nullable. */
    public function required(): static {
        $this->column->allowNull = false;
        return $this;
    }


    /* Sets a default value for the column. */
    public function defaultValue(string|int|null $value): static {
        $this->column->defaultValue = $value;
        return $this;
    }


    /* Marks the column for indexing. */
    public function index(): static {
        $this->column->index = true;
        return $this;
    }


    /* Marks the column as a unique index. */
    public function uniqueIndex(): static {
        $this->column->unique = true;
        return $this;
    }


    /* Enables automatic timestamp update on record modification for date columns; throws exception otherwise. */
    public function onUpdateTimestamp(): static {
        $this->column->updateTimestamp = match ($this->column->type) {
            ColumnType::DATETIME, ColumnType::DATE => true,
            default => throw new LogicException( 'Cannot set non-date column to onUpdateTimestamp' )
        };
        return $this;
    }


    /* Sets the default value of date columns to the current timestamp; throws exception for non-date columns. */
    public function currentTimestamp(): static {
        return $this->defaultValue( match ($this->column->type) {
            ColumnType::DATETIME, ColumnType::DATE => 'current_timestamp()',
            default => throw new LogicException( 'Cannot set non-date column to currentTimestamp' )
        } );
    }


    /**
     * @param class-string<Schema> $repository
     * @noinspection PhpDocMissingThrowsInspection PhpDocMissingThrowsInspection
     */
    public function foreign(string $repository, ?string $column = null, ?ColumnRelationAction $onDelete = null, ?ColumnRelationAction $onUpdate = null): static {
        /* @noinspection PhpUnhandledExceptionInspection PhpUnhandledExceptionInspection */
        $reflection = new ReflectionClass( $repository );
        if( $reflection->implementsInterface( Schema::class ) ) {
            $this->column->foreign_table = $repository::table();
            $this->column->foreign_column = $column ?? $repository::primary();
            $this->column->foreign_onUpdate = $onUpdate ?? ColumnRelationAction::CASCADE;
            $this->column->foreign_onDelete = $onDelete ?? ColumnRelationAction::SET_NULL;
            return $this->unsigned();
        }
        throw new LogicException( 'Invalid repository class for foreign key. Must implement schema' );
    }

}