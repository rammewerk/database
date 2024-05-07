<?php

namespace Rammewerk\Component\Database\Schema;

use App\Database\Database\Database;
use LogicException;
use RuntimeException;

readonly class SchemaUtility {

    public function __construct(
        private Database $db,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Checks
    |--------------------------------------------------------------------------
    */

    public function tableExist(string $table): bool {
        return $this->db->fetch( "SHOW TABLES LIKE '$table' " ) !== null;
    }

    public function columnExist(string $table, string $column): bool {
        return $this->db->fetch( "SHOW COLUMNS FROM `$table` LIKE '$column'" ) !== null;
    }

    public function foreignConstraintExist(string $table, string $constraint_name): bool {
        return !is_null( $this->getConstraintQueryLine( $table, $constraint_name ) );
    }

    public function isColumnAfterPrevious(string $table, string $column, string $previous): bool {

        $columns = array_map( static function($row) {
            return $row['Field'] ?? throw new RuntimeException( 'Invalid key Field from query result' );
        }, $this->db->fetchAll( "SHOW COLUMNS FROM `$table`" ) );

        $previousIndex = array_search( $previous, $columns, true );

        if( $previousIndex === false ) {
            throw new LogicException( 'Previous column not found in the column list' );
        }

        $currentIndex = array_search( $column, $columns, true );

        if( $currentIndex === false ) {
            throw new LogicException( 'Current column not found in the column list' );
        }

        return $currentIndex === ($previousIndex + 1);

    }


    /*
    |--------------------------------------------------------------------------
    | Info
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    public function getColumnDetails(string $table, string $column): array {
        return $this->db->fetch( "SHOW COLUMNS FROM `$table` LIKE '$column'" ) ?? [];
    }


    /**
     * @param string $table
     * @param string $index_name
     * @return array<string, mixed>
     */
    public function getIndexByName(string $table, string $index_name): array {
        return $this->db->fetch( "SHOW INDEX FROM `$table` WHERE `Key_name` = '$index_name'" ) ?? [];
    }


    public function showCreateTableQuery(string $table): ?string {
        $createQuery = $this->db->fetch( "SHOW CREATE TABLE `$table`" )['Create Table'] ?? null;
        return $createQuery ? (string)$createQuery : null;
    }


    /*
    |--------------------------------------------------------------------------
    | Creation
    |--------------------------------------------------------------------------
    */

    public function createTable(string $table, string $primary_column): void {
        $this->db->run( "CREATE TABLE IF NOT EXISTS `$table` (`$primary_column` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY)" );
    }

    public function createColumn(string $table, ColumnSchema $column, ?ColumnSchema $previous): void {
        $query = "ALTER TABLE `$table` ADD COLUMN {$this->defineColumnAttributeQuery($column)}";
        if( $previous ) $query .= " AFTER `$previous->name`";
        $this->db->run( $query );
    }

    public function createIndex(string $table, string $column): void {
        $this->db->run( "CREATE INDEX `$column` ON `$table` (`$column`)" );
    }

    public function createUniqueIndex(string $table, string $column): void {
        $this->db->run( "CREATE UNIQUE INDEX `$column` ON `$table` (`$column`)" );
    }

    public function createForeignKey(string $table, string $key, string $column, string $foreign_table, string $foreign_column, ColumnRelationAction $update, ColumnRelationAction $delete): void {
        $this->db->run( "
            ALTER TABLE `$table` 
            ADD CONSTRAINT `$key` FOREIGN KEY (`$column`)
            REFERENCES `$foreign_table` (`$foreign_column`)
            ON UPDATE $update->value ON DELETE $delete->value
        " );
    }

    /*
    |--------------------------------------------------------------------------
    | Rename
    |--------------------------------------------------------------------------
    */

    public function renameTable(string $old_table_name, string $new_table_name): void {
        if( $this->tableExist( $old_table_name ) ) $this->db->run( "ALTER TABLE `$old_table_name` RENAME `$new_table_name`" );
    }

    public function renameColumn(string $table, string $old_column, string $new_column): void {
        $this->db->run( "ALTER TABLE `$table` RENAME COLUMN `$old_column` TO `$new_column`" );
    }

    /*
    |--------------------------------------------------------------------------
    | Deletions
    |--------------------------------------------------------------------------
    */

    public function dropTable(string $table): void {
        if( $this->tableExist( $table ) ) $this->db->run( "DROP TABLE `$table`" );
    }

    public function dropColumn(string $table, string $column): void {
        $this->db->run( "ALTER TABLE `$table` DROP COLUMN `$column`" );
    }

    public function dropIndex(string $table, string $index_name): void {
        $this->db->run( "DROP INDEX `$index_name` ON `$table`" );
    }

    public function dropForeignKey(string $table, string $constraint_name): void {
        $this->db->run( "ALTER TABLE `$table` DROP FOREIGN KEY `$constraint_name`" );
    }

    /*
    |--------------------------------------------------------------------------
    | Modifications
    |--------------------------------------------------------------------------
    */

    public function modifyColumn(string $table, ColumnSchema $column, ?ColumnSchema $previous): void {
        $query = "ALTER TABLE `$table` MODIFY COLUMN {$this->defineColumnAttributeQuery($column)}";
        if( $previous ) $query .= " AFTER `$previous->name`";
        $this->db->run( $query );
    }

    private function defineColumnAttributeQuery(ColumnSchema $column): string {
        $DEFAULT = match ($column->defaultValue) {
            null, 'null' => $column->allowNull ? "DEFAULT NULL" : "",
            'current_timestamp()', 'current_timestamp' => "DEFAULT current_timestamp()",
            default => "DEFAULT '$column->defaultValue'",
        };
        $NULL = $column->allowNull ? 'NULL' : 'NOT NULL';
        $EXTRA = $column->updateTimestamp ? "on update current_timestamp()" : "";
        return "`$column->name` {$column->getFieldType()} $DEFAULT $NULL $EXTRA";
    }

    /*
    |--------------------------------------------------------------------------
    | Foreign Constraint Check
    |--------------------------------------------------------------------------
    */

    /**
     * Will check if a constraint exist by name
     *
     * @param string $table
     * @param string $constraint_name
     * @return string|null Constraint query string or null if constraint does not exist
     */
    public function getConstraintQueryLine(string $table, string $constraint_name): string|null {

        $createTableQuery = $this->showCreateTableQuery( $table );
        if( !$createTableQuery ) throw new \RuntimeException( 'Unable to get create table query' );

        # Get each line as array
        $lines = explode( "\n", $createTableQuery );

        # Keep only the one matching constraint name.
        $filtered_constraints = array_filter( $lines, static function($line) use ($constraint_name) {
            return str_contains( $line, 'CONSTRAINT' ) && str_contains( $line, $constraint_name );
        } );

        if( count( $filtered_constraints ) > 1 ) {
            throw new RuntimeException( 'Multiple constraints found with same name. Should not happen' );
        }

        return current( $filtered_constraints ) ?: null;
    }


}