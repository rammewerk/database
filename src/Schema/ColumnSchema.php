<?php

namespace Rammewerk\Component\Database\Schema;

class ColumnSchema {

    public string $name;
    public ColumnType $type;
    public ?int $length;
    public ?int $precision;
    public bool $unsigned = false;

    public bool $allowNull = true;
    public string|int|null|float|bool $defaultValue = null;

    public bool $index = false;
    public bool $unique = false;

    public bool $updateTimestamp = false;

    public ?string $foreign_table = null;
    public ?string $foreign_column = null;
    public ?ColumnRelationAction $foreign_onDelete = null;
    public ?ColumnRelationAction $foreign_onUpdate = null;

    public function __construct(string $name, ColumnType $type, ?int $length = null, ?int $precision = null) {
        $this->name = $name;
        $this->type = $type;
        $this->length = $length;
        $this->precision = $precision;
    }

    public function getFieldType(): string {
        return match ($this->type) {
            ColumnType::VARCHAR => "varchar($this->length)",
            ColumnType::INT => "int($this->length)" . ($this->unsigned ? " unsigned" : ""),
            ColumnType::TINYINT => "tinyint($this->length)" . ($this->unsigned ? " unsigned" : ""),
            ColumnType::TEXT => "text",
            ColumnType::DATE => "date",
            ColumnType::DATETIME => "datetime",
            ColumnType::DECIMAL => "decimal($this->length,$this->precision)",
        };
    }

}