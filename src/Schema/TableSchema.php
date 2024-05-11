<?php

namespace Rammewerk\Component\Database\Schema;

class TableSchema {

    /** @var ColumnSchema[] */
    private array $columns = [];
    /** @var string[] */
    private array $droppedColumns = [];
    /** @var array<string, string> */
    private array $renamedColumns = [];

    /*
    |--------------------------------------------------------------------------
    | Getters for table schema
    |--------------------------------------------------------------------------
    */

    /** @return ColumnSchema[] */
    public function getColumns(): array {
        return $this->columns;
    }

    /** @return string[] */
    public function getDroppedColumns(): array {
        return $this->droppedColumns;
    }

    /** @return array<string, string> */
    public function getRenamedColumns(): array {
        return $this->renamedColumns;
    }

    /*
    |--------------------------------------------------------------------------
    | Modifications
    |--------------------------------------------------------------------------
    */


    /**
     * @param string|string[] $name
     */
    public function drop(string|array $name): void {
        $names = is_string( $name ) ? [$name] : $name;
        foreach( $names as $column_name ) {
            unset( $this->columns[$column_name], $this->renamedColumns[$column_name] );
            $this->droppedColumns[] = $column_name;
        }
    }

    /** @noinspection PhpUnused PhpUnused */
    public function rename(string $current_name, string $new_name): void {
        $this->renamedColumns[$current_name] = $new_name;
    }


    /*
    |--------------------------------------------------------------------------
    | Define schema
    |--------------------------------------------------------------------------
    */

    private function set(ColumnSchema $field): ColumnAttributes {
        $this->columns[$field->name] = $field;
        return new ColumnAttributes( $field );
    }


    public function string(string $name, int $length = 255): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::VARCHAR, $length ) );
    }

    public function int(string $name, int $length = 11): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::INT, $length ) );
    }

    public function bigint(string $name, int $length = 20): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::BIGINT, $length ) );
    }

    public function float(string $name, int $length = 11, int $precision = 2): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::DECIMAL, $length, $precision ) );
    }

    public function boolean(string $name, bool $defaultActive = false): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::TINYINT, 1 ) )->defaultValue( $defaultActive ? '1' : '0' );
    }

    public function text(string $name): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::TEXT ) );
    }

    public function date(string $name): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::DATE ) );
    }

    public function dateTime(string $name): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::DATETIME ) );
    }

    /**
     * @param class-string<Schema> $repository
     */
    public function foreign(string $name, string $repository, string $column = null, ColumnRelationAction $onDelete = null, ColumnRelationAction $onUpdate = null): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::INT, 11 ) )->foreign( $repository, $column, $onDelete, $onUpdate );
    }

    /*
    |--------------------------------------------------------------------------
    | Special Helper Definitions
    |--------------------------------------------------------------------------
    */

    public function timestamps(): void {
        $this->set( new ColumnSchema( 'created_at', ColumnType::DATETIME ) )->currentTimestamp();
        $this->set( new ColumnSchema( 'updated_at', ColumnType::DATETIME ) )->currentTimestamp()->onUpdateTimestamp();
    }

    public function softDelete(): void {
        $this->set( new ColumnSchema( 'deleted_at', ColumnType::DATETIME ) );
    }


    public function email(string $name): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::VARCHAR, 80 ) );
    }

    public function password(string $name): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::VARCHAR, 80 ) );
    }

    public function token(string $name): ColumnAttributes {
        return $this->set( new ColumnSchema( $name, ColumnType::VARCHAR, 64 ) );
    }

}