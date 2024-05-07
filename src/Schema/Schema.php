<?php

namespace Rammewerk\Component\Database\Schema;

interface Schema {

    public function schema(TableSchema $field): void;

    public static function table(): string;

    public static function primary(): string;

}