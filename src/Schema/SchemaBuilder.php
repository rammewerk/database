<?php

namespace Rammewerk\Component\Database\Schema;

use Rammewerk\Component\Database\Database;
use LogicException;
use RuntimeException;

class SchemaBuilder {

    /** @var string[] */
    private array $reports = [];

    private SchemaUtility $schema;

    public function __construct(
        Database $database
    ) {
        $this->schema = new SchemaUtility( $database );
    }


    private function constraint_name(string $table, string $column): string {
        return "fk_{$table}_$column";
    }


    public function build(Schema $repositorySchema): void {

        $table = $repositorySchema::table();
        $tableSchema = new TableSchema();

        # Get define schema
        $repositorySchema->schema( $tableSchema );

        # Create table if not exist
        if( !$this->schema->tableExist( $table ) ) {
            $this->schema->createTable( $table, $table . '_id' );
            $this->report( "Created table: $table" );
        }

        # Drop columns
        foreach( $tableSchema->getDroppedColumns() as $column_name ) {
            if( $this->schema->columnExist( $table, $column_name ) ) {

                # If index foreign key exist, this must first be dropped:
                if( $this->schema->foreignConstraintExist( $table, $this->constraint_name( $table, $column_name ) ) ) {
                    $this->schema->dropForeignKey( $table, $this->constraint_name( $table, $column_name ) );
                    $this->report( "Dropped foreign key for column «$table.{$column_name}»" );
                }

                $this->schema->dropColumn( $table, $column_name );
                $this->report( "Removed column $column_name from $table" );
            }
        }

        # Rename columns
        foreach( $tableSchema->getRenamedColumns() as $current => $new ) {
            if( $this->schema->columnExist( $table, $current ) ) {
                $this->schema->renameColumn( $table, $current, $new );
                $this->report( "Renamed column $current in $table to $new" );
            }
        }

        $previous_column = null;

        foreach( $tableSchema->getColumns() as $column ) {
            if( $this->schema->columnExist( $table, $column->name ) ) {
                $this->updateModifiedColumn( $table, $column, $previous_column );
            } else {
                $this->schema->createColumn( $table, $column, $previous_column );
                $this->report( "Successfully created the column «$table.{$column->name}»" );
            }
            $previous_column = $column;
            $this->setIndexes( $table, $column );
            $this->setForeignConstraints( $table, $column );
        }

    }


    private function updateModifiedColumn(string $table, ColumnSchema $column, ?ColumnSchema $previous): void {

        $isModified = false;
        $details = $this->schema->getColumnDetails( $table, $column->name );

        if( !$details ) {
            throw new LogicException( 'Unable to get column details' );
        }

        $reportName = "«$table.{$column->name}»";

        if( $details['Type'] !== $column->getFieldType() ) {
            $this->report( "$reportName has been modified to type: {$column->getFieldType()}" );
            $isModified = true;
        }

        if( $column->allowNull && ($details['Null'] === 'NO') ) {
            $this->report( "$reportName is no longer required field" );
            $isModified = true;
        }

        if( !$column->allowNull && ($details['Null'] === 'YES') ) {
            $this->report( "$reportName is now a required field" );
            $isModified = true;
        }

        if( is_null( $column->defaultValue ) && !is_null( $details['Default'] ) ) {
            $this->report( "$reportName has now default value: NULL" );
            $isModified = true;
        }

        if( !is_null( $column->defaultValue ) && (string)$column->defaultValue !== $details['Default'] ) {
            $this->report( "$reportName has now default value: $column->defaultValue" );
            $isModified = true;
        }

        if( $column->updateTimestamp && (!is_string( $details['Extra'] ) || !str_contains( $details['Extra'], 'current_timestamp' )) ) {
            $this->report( "$reportName is now adding current timestamp on update" );
            $isModified = true;
        }

        if( !$column->updateTimestamp && is_string( $details['Extra'] ) && str_contains( $details['Extra'], 'current_timestamp' ) ) {
            $this->report( "$reportName is no longer adding current timestamp on update" );
            $isModified = true;
        }

        if( $previous && !$this->schema->isColumnAfterPrevious( $table, $column->name, $previous->name ) ) {
            $this->report( "$reportName is has changed position in database and is moved right after column: $previous->name" );
            $isModified = true;
        }

        if( !$isModified ) return;

        $this->schema->modifyColumn( $table, $column, $previous );
        $this->report( "Successfully modified the column «$table.{$column->name}»" );

    }


    private function setIndexes(string $table, ColumnSchema $column): void {

        if( !$column->index && !$column->unique ) return;

        $name = $column->name;
        $index = $this->schema->getIndexByName( $table, $name );

        if( $index && $index['Non_unique'] !== ($column->unique ? 0 : 1) ) {
            $this->schema->dropIndex( $table, $name );
            $this->report( "Dropped index for modification for: «$table.{$column->name}»" );
            $index = null;
        }

        if( !$index && $column->index ) {
            $this->schema->createIndex( $table, $column->name );
            $this->report( "Created index for: «$table.{$column->name}»" );
        }

        if( !$index && $column->unique ) {
            $this->schema->createUniqueIndex( $table, $column->name );
            $this->report( "Created unique index for: «$table.{$column->name}»" );
        }

    }


    private function setForeignConstraints(string $table, ColumnSchema $column): void {

        if( !$column->foreign_table ) return;

        if( !$column->foreign_column ) {
            throw new RuntimeException( "Foreign column is missing for «$table.{$column->name}»" );
        }

        if( !$column->foreign_onDelete || !$column->foreign_onUpdate ) {
            throw new RuntimeException( "On delete or update action for foreign key $column->foreign_column in table $table is not set" );
        }

        if( $column->foreign_onDelete === ColumnRelationAction::SET_NULL && !$column->allowNull ) {
            throw new RuntimeException( "Cannot use SET NULL or foreign key $column->name as long as column is set to not allow null" );
        }

        $name = $this->constraint_name( $table, $column->name );
        $constraint = $this->schema->getConstraintQueryLine( $table, $name );
        $updateAction = $column->foreign_onUpdate->value;
        $deleteAction = $column->foreign_onDelete->value;

        # Check if constraint is correct
        if( $constraint && (!str_contains( $constraint, "UPDATE $updateAction" ) || !str_contains( $constraint, "DELETE $deleteAction" )) ) {
            $this->schema->dropForeignKey( $table, $name );
            $constraint = null;
        }

        if( !$constraint ) {
            $this->schema->createForeignKey(
                $table,
                $name,
                $column->name,
                $column->foreign_table,
                $column->foreign_column,
                $column->foreign_onUpdate,
                $column->foreign_onDelete
            );
            $this->report( "Created foreign key for: «$table.{$column->name}»" );
        }


    }


    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    private function report(string $message): void {
        $this->reports[] = $message;
    }

    /**
     * @return string[]
     */
    public function getReport(): array {
        return $this->reports;
    }


}