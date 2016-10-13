<?php
namespace pdquery\Sql\Dialects;
use pdquery\EntityModel\IEntityFieldType;

interface ISqlDialect {
    public function encodeColumnName($columnName);
    public function encodeTableName($tableName);
    public function encodeColumnAlias($columnAlias); // enkoduje część kolumna AS $columnAlias
    public function encodeTableAlias($tableAlias);
    public function valueToSql($value, $type);

    /**
     * Przekształca podane zapytanie dodając limit (i ewentualnie offset).
     * @param string $selectSql
     * @param int    $limit
     * @param int    $offset
     * @return string
     */
    public function sqlSetSelectLimit($selectSql, $limit, $offset = null);
    //public function getLimitAndOffsetSqlPart($limit, $offset);
}

class MySqlDialect implements ISqlDialect {
    public function encodeColumnName($columnName) {
        return '`' . $columnName . '`';
    }

    public function encodeTableName($tableName) {
        return '`' . $tableName . '`';
    }

    protected function escStr($str) {
        foreach (['r', 'n', 't'] as $char) {
            $str = str_replace("\\" . $char, "\\\\" . $char, $str);
        }
        return "'" . str_replace("'", "''", $str) . "'";
    }

    public function encodeColumnAlias($columnAlias) {
        return $this->escStr($columnAlias);
    }

    public function encodeTableAlias($tableAlias) {
        return '`' . $tableAlias . '`';
    }

    public function valueToSql($value, $type) {
        if (null === $value) return 'NULL';
        if ($type == IEntityFieldType::IMPLICIT) {
            if (is_int($value)) $type = IEntityFieldType::INT;
            elseif (is_float($value)) $type = IEntityFieldType::FLOAT;
            elseif (is_string($value)) $type = IEntityFieldType::STRING;
            elseif (is_bool($value)) $type = IEntityFieldType::BOOL;
        }
        switch ($type) {
            case IEntityFieldType::STRING:
                return $this->escStr($value);
            case IEntityFieldType::BOOL:
                return $value ? 1 : 0;
            case IEntityFieldType::INT:
                return (int)$value;
            case IEntityFieldType::FLOAT:
                return (float)$value;
            default:
                throw new \Exception("Mysqli unknown type $type");
        }
    }

    public function sqlSetSelectLimit($selectSql, $limit, $offset = null) {
        if (null === $limit) $limit = '18446744073709551615'; else $limit = (int)$limit;
        if (null === $offset) $offset = 0; else $offset = (int)$offset;
        return $selectSql . " LIMIT $offset, $limit";
    }

    /*public function getLimitAndOffsetSqlPart($limit, $offset) {
        if (null === $limit) $limit = '18446744073709551615'; else $limit = (int)$limit;
        if (null === $offset) $offset = 0; else $offset = (int)$offset;
        return " LIMIT $offset, $limit";
    }*/
}

class MySqliDialect extends MySqlDialect {
    /**
     * @var \mysqli
     */
    protected $mysqli;

    /**
     * MySqliDialect constructor.
     * @param \mysqli $mysqli
     */
    public function __construct(\mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    protected function escStr($str) {
        return "'" . $this->mysqli->real_escape_string($str) . "'";
    }
}
