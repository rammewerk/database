<?php

namespace Rammewerk\Component\Database\Schema;

enum ColumnType: string {

    case VARCHAR = 'varchar';
    case INT = 'int';
    case TINYINT = 'tinyint';
    case BIGINT = 'bigint';
    case TEXT = 'text';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case DECIMAL = 'decimal';

}
