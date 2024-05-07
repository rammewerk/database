<?php

namespace Rammewerk\Component\Database;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database {

    public PDO $pdo;

    /** @var array<string, PDOStatement> */
    private array $prepared_stmt_cache = [];


    public function __construct(string $host, ?string $database, string $user, string $password, string $charset, array $options = []) {
        $dsn = is_null( $database ) ? "mysql:host=$host;charset=$charset" : "mysql:host=$host;dbname=$database;charset=$charset";
        $this->pdo = new PDO( $dsn, $user, $password, array_replace( [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ], $options ) );
    }


    /**
     * Run Query or Prepared Statements
     *
     * @param string $query
     * @param array<int|string, scalar|null> $args
     * @param string|null $cache_key
     *
     * @return PDOStatement
     * @throws PDOException
     */
    public function run(string $query, array $args = [], string $cache_key = null): PDOStatement {

        if( !$args ) return $this->pdo->query( $query ) ?: throw new PDOException( 'no result from query' );

        $stmt = ($cache_key) ? $this->prepared_stmt_cache[$cache_key] : $this->pdo->prepare( $query );

        if( $cache_key && !isset( $this->prepared_stmt_cache[$cache_key] ) ) {
            $this->prepared_stmt_cache[$cache_key] = $stmt;
        }

        $stmt->execute( $args );
        return $stmt;
    }


    /**
     * Fetch Single Row
     *
     * @param string $query
     * @param array<int|string, scalar|null> $args
     * @param string|null $cache_key
     *
     * @return null|array<string, string|int|float|null>
     */
    public function fetch(string $query, array $args = [], string $cache_key = null): ?array {
        /** @phpstan-ignore-next-line */
        return $this->run( $query, $args, $cache_key )->fetch( PDO::FETCH_ASSOC ) ?: null;
    }


    /**
     * Fetch All Rows
     *
     * @param string $query
     * @param array<int|string, scalar|null> $args
     * @param string|null $save_key
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $query, array $args = [], string $save_key = null): array {
        return $this->run( $query, $args, $save_key )->fetchAll( PDO::FETCH_ASSOC ) ?: [];
    }


    /**
     * Returns a single column from the next row of a result set
     *
     * PDOStatement::fetchColumn() should not be used to retrieve boolean columns,
     * as it is impossible to distinguish a value of false from there being no more
     * rows to retrieve. Use PDOStatement::fetch() instead.
     *
     * @param string $query
     * @param array<int|string, scalar|null> $args
     *
     * @return string|int|float|null
     */
    public function fetchColumn(string $query, array $args = []): string|int|float|null {
        return $this->run( $query, $args )->fetchColumn() ?: null;
    }


    /**
     * Fetch a two-column result into an array.
     *
     * First column is a key and the second column is the value.
     *
     * @param string $query
     * @param array<int|string, scalar|null> $args
     *
     * @return array<string, string|int|float|null>
     */
    public function fetchKeyPair(string $query, array $args = []): array {
        return $this->run( $query, $args )->fetchAll( PDO::FETCH_KEY_PAIR ) ?: [];
    }


    /**
     * Fetch only the unique values.
     *
     * First column is a key and the other columns are the values.
     *
     * @param string $query
     * @param array<int|string, scalar|null> $args
     *
     * @return array<string, string|int|float|null>
     */
    public function fetchAllUnique(string $query, array $args = []): array {
        return $this->run( $query, $args )->fetchAll( PDO::FETCH_UNIQUE ) ?: [];
    }


    /**
     * Group return by values.
     *
     * PDO::FETCH_GROUP will group rows into a nested array, where indexes will
     * be unique values from the first columns, and values will be arrays similar
     * to ones returned by regular fetchAll()
     *
     * @param string $query
     * @param array<int|string, scalar|null> $args
     *
     * @return array<string, array<int, array<string, string|float|int|null>>>
     */
    public function fetchAllGroup(string $query, array $args = []): array {
        return $this->run( $query, $args )->fetchAll( PDO::FETCH_GROUP ) ?: [];
    }


    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    */

    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }


    public function rollBackTransaction(): bool {
        return $this->pdo->rollBack();
    }


    public function commitTransaction(): bool {
        return $this->pdo->commit();
    }



    /*
    |--------------------------------------------------------------------------
    | Validate and clean
    |--------------------------------------------------------------------------
    */


    /**
     * Validates and escapes an SQL identifier to ensure it is safe for use in queries.
     *
     * @param string $value The identifier to be validated and escaped.
     * @return string The secure, escaped identifier.
     * @throws InvalidArgumentException If the identifier is not valid.
     * @noinspection NotOptimalRegularExpressionsInspection NotOptimalRegularExpressionsInspection
     */
    public function secureIdentifier(string $value): string {
        if( !preg_match( '/^[a-zA-Z0-9_]+$/', $value ) ) {
            throw new InvalidArgumentException( 'Invalid column name: ' . $value );
        }
        return trim( '`' . str_replace( '`', '``', $value ) . '`' );
    }



    /*
    |---------------------------------------------------------------------------
    | Insert to database
    |---------------------------------------------------------------------------
    */

    /**
     * Builds a part of an SQL SET clause from given data array.
     *
     * @param array<string, mixed> $data The data to be transformed into a part of the SQL SET clause.
     * @return string A string representing part of the SQL SET clause.
     */
    private function prepareSetClause(array $data): string {
        if( empty( $data ) ) throw new InvalidArgumentException( 'Trying to insert or update data with empty column list' );
        return implode( ', ', array_map( fn($column) => $this->secureIdentifier( $column ) . "=:$column", array_keys( $data ) ) );
    }

    /**
     * Insert Data
     *
     * @param string $table
     * @param array<string, scalar|null> $data
     * @param string|null $cache_key
     *
     * @return string
     */
    public function insert(string $table, array $data, string $cache_key = null): string {

        $table = $this->secureIdentifier( $table );
        $set = $this->prepareSetClause( $data );

        $this->run( "INSERT INTO $table SET $set", $data, $cache_key );

        return $this->pdo->lastInsertId() ?: throw new RuntimeException( 'Unable to retrieve last inserted ID.' );

    }


    /*
    |---------------------------------------------------------------------------
    | Update database
    |---------------------------------------------------------------------------
    */

    /**
     * Update data
     *
     * @param string $table
     * @param array<string, scalar|null> $data
     * @param string $match_column
     * @param int|string $match_value
     * @param string|null $cache_key
     */
    public function update(string $table, array $data, string $match_column, int|string $match_value, string $cache_key = null): void {

        # Remove idColumn from update
        unset( $data[$match_column] );

        $table = $this->secureIdentifier( $table );
        $match_column = $this->secureIdentifier( $match_column );
        $set = $this->prepareSetClause( $data );

        $data[':matchColumnSetClause'] = $match_value;

        $this->run( "UPDATE $table SET $set WHERE $match_column=:matchColumnSetClause", $data, $cache_key );

    }


}
