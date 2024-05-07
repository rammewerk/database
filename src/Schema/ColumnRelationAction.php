<?php

namespace Rammewerk\Component\Database\Schema;

enum ColumnRelationAction: string {
    case RESTRICT = 'RESTRICT';
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
}
