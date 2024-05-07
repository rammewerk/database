# Rammewerk Database - A simple PHP database handler

The **Rammewerk Database** is a simple, yet effective, database helper library. Its functionality is meant to work with
MariaDB or MySQL.

## Installation

Install Rammewerk Database via composer:

```bash
composer require rammewerk/database
```

How to use
----
Create a database connector instance.

```php
$database = new Rammewerk\Component\Database\Database('host','database','user','password','charset');
```

Or, create some caching of instances

```php
use Rammewerk\Component\Database\Database;
use Rammewerk\Component\Environment;

class DatabaseConnector {

    /** @var Database[] */
    protected array $instances = [];

    public function __construct(protected readonly Environment $environment) {}

    public function instance(string $database): Database {
        if( !isset( $this->instances[$database] ) ) {
            try {
                $this->instances[$database] = new Database(
                    $this->environment->get( 'DB_HOST' ),
                    $database,
                    $this->environment->get( 'DB_USERNAME' ),
                    $this->environment->get( 'DB_PASSWORD' ),
                    $this->environment->get( 'DB_CHARSET' )
                );
            } catch( \PDOException $e ) {
                // Log exception here...
                throw new \RuntimeException( 'Unable to connect database: ' . $database );
            }
        }

        return $this->instances[$database];

    }

}
```

More details are coming in the future.