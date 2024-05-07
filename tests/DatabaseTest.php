<?php

namespace Rammewerk\Component\Database\Tests;

use PHPUnit\Framework\TestCase;
use Rammewerk\Component\Hydrator\Hydrator;

class DatabaseTest extends TestCase {

    private function getEntitySource(): array {
        return [
            'id' => 12,
            'string' => 'hello',
            'nullableString' => null,
            'integer' => '2',
            'boolean' => 'false',
        ];
    }

    public function testDatabaseConnection(): void {

    }

}
