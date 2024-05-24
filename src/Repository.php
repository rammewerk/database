<?php

/**
 * @noinspection PhpMultipleClassDeclarationsInspection PhpMultipleClassDeclarationsInspection
 * @noinspection SqlNoDataSourceInspection SqlNoDataSourceInspection
 * @noinspection PhpUnused PhpUnused
 */

namespace Rammewerk\Component\Database;

use Rammewerk\Component\Database\Schema\Schema;
use Rammewerk\Component\Database\Schema\TableSchema;
use Rammewerk\Component\Hydrator\Hydrator;
use Rammewerk\Component\Hydrator\HydratorCollection;
use BackedEnum;
use DateTime;
use JsonException;
use LogicException;
use RuntimeException;
use UnitEnum;

abstract class Repository implements Schema {

    protected string $table;
    protected string $primary;

    /** @var string[] List of schema column names */
    private array $column_list = [];

    public function __construct(protected readonly Database $db) {
        $this->table = static::table();
        $this->primary = static::primary();
    }


    /**
     * Get table name
     * Will define table name as classname without «Repository» and with underscore instead of camelCase.
     */
    public static function table(): string {
        $parts = explode( '\\', static::class );
        $className = str_replace( 'Repository', '', array_pop( $parts ) );
        return strtolower( (string)preg_replace( '/(.)(?=[A-Z])/u', '$1_', $className ) );
    }


    public static function primary(): string {
        return static::table() . '_id';
    }

    /**
     * Get array of all table columns
     *
     * @return string[] List of schema column names
     */
    protected function getSchemaColumns(): array {
        if( empty( $this->column_list ) ) {
            $schema = new TableSchema();
            $this->schema( $schema );
            $this->column_list = array_merge( array_keys( $schema->getColumns() ), [$this->primary] );
        }
        return $this->column_list;
    }


    public function beginTransaction(): bool {
        return $this->db->beginTransaction();
    }


    public function rollBackTransaction(): bool {
        return $this->db->rollBackTransaction();
    }


    public function commitTransaction(): bool {
        return $this->db->commitTransaction();
    }


    /**
     * @template TEntity of object
     * @param class-string<TEntity> $entity
     * @param array<string, string|int|float|null>|null $data
     * @return TEntity|null
     */
    protected function hydrate(string $entity, array|null $data) {
        return $data ? (new Hydrator( $entity ))->hydrate( (array)$data ) : null;
    }

    /**
     * Create collection from fetched data
     * @template TEntity of object
     * @param class-string<TEntity> $entity
     * @param array<int, array<string, mixed>> $data
     * @return HydratorCollection<TEntity>
     */
    protected function collection(string $entity, array $data): HydratorCollection {
        return new HydratorCollection( new Hydrator( $entity ), $data );
    }


    /**
     * @template T
     * @param object<T> $entity
     * @param string[] $filter // list of keys to save on update
     * @param array<string, mixed> $include Include in save
     * @return object<T>
     * @phpstan-ignore-next-line
     */
    public function save(object $entity, array $filter = [], array $include = []): object {

        # Validate that entity does contain the primary column as property
        if( !property_exists( $entity, $this->primary ) ) {
            throw new LogicException( "Primary field «{$this->primary}» is not defined in entity " . gettype( $entity ) );
        }

        // Convert entity to array data
        $data = $this->convertEntityToArrayOfSchema( $entity, $filter, $include );

        # Update record if entity has primary key value.
        if( $id = $entity->{$this->primary} ) {
            $this->db->update( $this->table, $data, $this->primary, $id );
            return $entity;
        }

        # Remove empty values and create new record
        $entity->{$this->primary} = (int)$this->db->insert( $this->table, array_filter( $data, static function($v) {
            return !is_null( $v ) && (!is_string( $v ) || trim( $v ) !== '');
        } ) );

        return $entity;

    }


    /**
     * Convert entity to array only containing items that are present in schema.
     * @param string[] $filter
     * @param array<string, mixed> $include
     * @return array<string, scalar|null>
     */
    protected function convertEntityToArrayOfSchema(object $model, array $filter = [], array $include = []): array {

        # Get the valid columns defined in the schema for this repository
        $columns = $this->getSchemaColumns();

        # If filter is defined, reduce column list to those defined
        if( $filter ) $columns = array_intersect( $columns, $filter );

        # Set included data if present in the schema
        $data = array_intersect_key( $include, array_flip( $columns ) );

        foreach( $columns as $column ) {
            # If property not found in entity, skip
            if( !property_exists( $model, $column ) ) continue;
            # Get the value
            $value = $model->{$column};
            # Convert DateTime to string
            if( $value instanceof DateTime ) $value = $value->format( 'Y-m-d H:i:s' );
            if( $value instanceof BackedEnum ) $value = $value->value;
            if( $value instanceof UnitEnum ) $value = $value->name;
            if( is_bool( $value ) ) $value = (int)$value;
            # Set empty string for empty scalar (object/array)
            if( is_array( $value ) && empty( $value ) ) $value = null;
            # Support for array -> json
            if( is_array( $value ) || is_object( $value ) ) try {
                $value = json_encode( $value, JSON_THROW_ON_ERROR );
            } catch( JsonException $e ) {
                throw new RuntimeException( "Unable to convert repository array to json for column: $column in table $this->table", 0, $e );
            }
            if( is_string( $value ) && trim( $value ) === '' ) $value = null;
            # Will save value, but use the included if the value was null - and go back to null if the included value didn't include this.
            $data[$column] = $value ?? $data[$column] ?? null;
        }
        return $data;
    }


    /**
     * @param int $id The primary ID
     * @return null|array<string, string|int|float|null>
     */
    protected function fetchById(int $id): ?array {
        return $this->fetch( [$this->primary => $id] );
    }


    public function deleteById(int $id): void {
        $this->db->run( "DELETE FROM `$this->table` WHERE `$this->primary` = ?", [$id] );
    }


    /**
     * Generate the select query with where clause based from array
     *
     * @param string[] $where List of columns to generate where clause from
     * @return string
     */
    private function generateWhereQuery(array $where): string {
        if( !$where ) return "SELECT * FROM `$this->table`";
        $where = array_map( fn($column) => $this->db->secureIdentifier( $column ) . ' = ?', $where );
        return "SELECT * FROM `$this->table` WHERE " . implode( ' AND ', $where );
    }

    /**
     * @param array<string, string|float|int> $where Array where key is column and value is the where check
     * @return null|array<string, string|int|float|null>
     */
    protected function fetch(array $where): ?array {
        $where = array_intersect_key( $where, array_flip( $this->getSchemaColumns() ) );
        return $this->db->fetch( $this->generateWhereQuery( array_keys( $where ) ), array_values( $where ) );
    }


    /**
     * @param array<string, string|float|int> $where
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(array $where): array {
        $where = array_intersect_key( $where, array_flip( $this->getSchemaColumns() ) );
        return $this->db->fetchAll( $this->generateWhereQuery( array_keys( $where ) ), array_values( $where ) );
    }


}