<?php
namespace pdquery\Sql;
use pdquery\Sql\Dialects\ISqlDialect;
use pdquery\EntityModel\IEntityModel;
use pdquery\EntityModel\IEntityRelation;
use pdquery\EntityModel\IEntityFieldType;

class QueryTagsReplacer {
    protected $currentQuery;
    protected $originalQuery;
    protected $replacements = array();
    protected $replNum = 0;

    public function __construct($query) {
        $this->currentQuery = $query;
        $this->originalQuery = $query;
    }

    protected function findTag($str, $tag, &$startPos, &$len, &$args, &$prefix) {
        $p = strpos($str, '%(' . $tag);
        if (false === $p) return false;

        $tagLen = strlen($tag);
        $pAfterTag = $p + 2 + $tagLen;
        if (!isset($str[$pAfterTag]) || ($str[$pAfterTag] != ')' && $str[$pAfterTag] != ':')) return false;

        $pend = strpos($str, ')', $p + 2);
        if (false === $pend) return false;

        $startPos = $p;
        $args = [];

        if (':' == $str[$pAfterTag]) {
            $args = substr($str, $pAfterTag + 1, $pend - $pAfterTag - 1);
            $args = array_map('trim', explode(',', $args));
        }
        $len = $pend - $p + 1;

        $prefix = null;
        // spr, czy istnieje prefix
        for ($pp = $p - 1; $pp > 0; $pp--) {
            if (' ' == $str[$pp]) continue;
            if (']' == $str[$pp]) {
                for ($pps = $pp - 1; $pps > 0; $pps--) {
                    if ('[' == $str[$pps]) {
                        $prefix = substr($str, $pps + 1, $pp - $pps - 1);
                        $startPos = $pps;
                        $len += $p - $pps;
                        return true;
                    }
                }
            }
            break;
        }
        return true;
    }

    public function replaceTags($tagName, $callback) {
        while ($this->findTag($this->currentQuery, $tagName, $startPos, $len, $args, $prefix)) {

            $replacement = $callback($tagName, $args);

            if (null !== $replacement && '' !== $replacement) {
                $replSymbol = '#repl' . ($this->replNum++) . 'lper#';
                if (!empty($prefix)) {
                    $replacement = $prefix . ' ' . $replacement;
                }
                $this->replacements[$replSymbol] = $replacement;
            } else {
                $replSymbol = '';
            }
            $this->currentQuery = substr_replace($this->currentQuery, $replSymbol, $startPos, $len);
        }
    }

    public function getResult() {
        return strtr($this->currentQuery, $this->replacements);
    }
}


class BaseQueryBuilder {
    protected $sqlTemplate;
    protected $sqlTemplateParams = [];

    protected $entityName;

    protected $complexFields;

    /**
     *
     * @var IEntityModel
     */
    protected $entityModel;

    protected $sourcesByRelationPath;

    protected $joins;

    protected $uniqId = 1;

    /**
     * @var \pdquery\IBaseQueryProperties
     */
    protected $query;

    public function __construct(\pdquery\IBaseQueryProperties $query, $entityName, IEntityModel $entityModel, $complexFields = [], $sqlTemplate = null, $sqlTemplateParams = []) {
        if (null !== $sqlTemplate) {
            $this->sqlTemplate = $sqlTemplate;
            $this->sqlTemplateParams = $sqlTemplateParams;
        }
        $this->entityName = $entityName;
        $this->entityModel = $entityModel;
        $this->query = $query;
        $this->complexFields = $complexFields;

        $this->calcSources();
    }

    protected function uniqId() {
        return $this->uniqId++;
    }

    protected function calcSources() {
        $entityAlias = $this->entityName;
        $p = strrpos($entityAlias, '\\');
        if (false !== $p) {
            $entityAlias = substr($entityAlias, $p + 1);
        }
        $this->sourcesByRelationPath = [];
        $this->joins = [];
        $this->sourcesByRelationPath[''] = array(
            'relationPath' => '',
            'entityName' => $this->entityName,
            //'entityAlias' => $entityAlias,
            'entityAlias' => $this->entityModel->getDbTableName($this->entityName),
        );
        $entityRelations = $this->query->getIncludeRelations();
        if (!empty($entityRelations)) {
            foreach ($entityRelations as $relationName) {
                $rparts = explode('.', $relationName);
                $relPath = '';
                foreach ($rparts as $rname) {
                    $lastRelPath = $relPath;
                    if ($relPath !== '') $relPath .= '.';
                    $relPath .= $rname;
                    if (!isset($this->sourcesByRelationPath[$relPath])) {
                        $relation = $this->entityModel->getRelation($this->sourcesByRelationPath[$lastRelPath]['entityName'], $rname);

                        if (IEntityRelation::ONE_TO_MANY == $relation->getType()) {
                            throw new \Exception("Build select query: cannot use one-to-many relation ({$this->entityName} -> $relPath) in single select query");
                        }

                        $relTargetEntity = $relation->getTargetEntity();
                        $relTblAlias = $this->sourcesByRelationPath[$lastRelPath]['entityAlias'] . '_' . $rname;

                        $this->sourcesByRelationPath[$relPath] = array(
                            'relationPath' => $relPath,
                            'entityName' => $relTargetEntity,
                            'entityAlias' => $relTblAlias,
                        );
                        $this->joins[] = array(
                            'fromRelPath' => $lastRelPath,
                            'toRelPath' => $relPath,
                            'entityName' => $relTargetEntity,
                            'sourceField' => $relation->getSourceFieldName(),
                            'targetField' => $relation->getTargetFieldName(),
                        );
                    }
                }
            }
        }
    }

    protected function sourcesToSql(ISqlDialect $sqlDialect) {
        $sql = $sqlDialect->encodeTableName($this->entityModel->getDbTableName($this->entityName));
        //$sql .= ' ' . $sqlDialect->encodeTableAlias($this->sourcesByRelationPath['']['entityAlias']); // nie używamy, bo aliasem jest nazwa tabeli
        foreach ($this->joins as $join) {
            $jLeftSource = $this->sourcesByRelationPath[$join['fromRelPath']];
            $jRightSource = $this->sourcesByRelationPath[$join['toRelPath']];
            $sql .= ' LEFT JOIN ';
            $sql .= $sqlDialect->encodeTableName($this->entityModel->getDbTableName($join['entityName']));
            $sql .= ' ' . $sqlDialect->encodeTableAlias($jRightSource['entityAlias']);
            $sql .= ' ON (';
            $sql .= $sqlDialect->encodeTableAlias($jLeftSource['entityAlias']);
            $sql .= '.' . $sqlDialect->encodeColumnName($this->entityModel->getDbColumnName($jLeftSource['entityName'], $join['sourceField']));
            $sql .= ' = ';
            $sql .= $sqlDialect->encodeTableAlias($jRightSource['entityAlias']);
            $sql .= '.' . $sqlDialect->encodeColumnName($this->entityModel->getDbColumnName($jRightSource['entityName'], $join['targetField']));
            $sql .= ')';
        }
        return $sql;
    }

    protected function getFieldType($field) {
        $entityName = $this->entityName;
        while (true) {
            $p = strpos($field, '.');
            if (false === $p) break;

            $relName = substr($field, 0, $p);
            $field = substr($field, $p + 1);

            $relation = $this->entityModel->getRelation($entityName, $relName);
            if (null === $relation) {
                throw new \Exception("Unknown entity $entityName relation $relName");
            }
            $entityName = $relation->getTargetEntity();
        }
        return $this->entityModel->getFieldType($entityName, $field);
    }

    protected function whereConditionsToSql(array $conditions, ISqlDialect $sqlDialect, &$whereParams) {
        $whereSql = '';
        $isFirst = true;
        foreach ($conditions as $wherePart) {
            if (!$isFirst) {
                $whereSql .= ' ' . $wherePart['join'] . ' ';
            }
            $isFirst = false;
            if (isset($wherePart['subQuery']) && $wherePart['subQuery'] instanceof \pdquery\IWhereConditionsProperties) {
                $subConditions = $wherePart['subQuery']->getWhere();
                $subWhereSql = $this->whereConditionsToSql($subConditions, $sqlDialect, $whereParams);
                $whereSql .= '(' . $subWhereSql . ')';
                continue;
            }
            if (isset($wherePart['id'])) {
                $wherePart['field'] = $this->entityModel->getKeyFieldName($this->entityName);
                $wherePart['value'] = $wherePart['id'];
                unset($wherePart['id']);
            }

            $operator = $wherePart['operator'];
            $value = $wherePart['value'];

            if ($value instanceof ISqlQuery) {
                $subQuery = $value->getSelectSql();
                if ('=' == $operator) $operator = 'IN';
                if ('<>' == $operator) $operator = 'NOT IN';
                $value = '(' . $subQuery . ')';
            } elseif (is_array($value) || $value instanceof \Iterator || $value instanceof \IteratorAggregate) {
                if ($value instanceof \Iterator || $value instanceof \IteratorAggregate) {
                    $value = iterator_to_array($value);
                }
                if (count($value) == 0) {
                    // nie ma żadnych wartości (value IN (pusty zbiór)) - warunek zawsze fałszywy
                    // ze względu na uniknięcie błędu składni SQL zamieniamy go na 1 > 2
                    $whereSql .= '1 > 2';
                    continue;
                } else {
                    $fieldType = $this->getFieldType($wherePart['field']);
                    if ('=' == $operator) $operator = 'IN';
                    if ('<>' == $operator) $operator = 'NOT IN';
                    $mappedVals = [];
                    foreach ($value as $val) {
                        if (is_array($val)) {
                            // wybieramy pierwszy klucz
                            $val = reset($val);
                        }
                        $mappedVals[] = $sqlDialect->valueToSql($val, $fieldType);
                    }
                    $value = '(' . implode(',', $mappedVals) . ')';
                }
            } else {
                if (null === $value && '=' == $operator) {
                    $operator = 'IS';
                    $value = 'NULL';
                } else if (null === $value && '<>' == $operator) {
                    $operator = 'IS';
                    $value = 'NOT NULL';
                } else {
                    $fieldType = $this->getFieldType($wherePart['field']);
                    $value = $sqlDialect->valueToSql($value, $fieldType);
                }
            }
            if (isset($this->complexFields[$wherePart['field']])) {
                $whereSql .= '(' . $this->complexFields[$wherePart['field']] . ')';
            } else {
                $whereSql .= '%(field:' . $wherePart['field'] . ')';
            }

            $vpk = 'p' . $this->uniqId();
            $whereParams[$vpk] = $value;

            $whereSql .= ' ' . $operator . " %(whereParam:$vpk)";
        }
        return $whereSql;
    }

    /**
     * Podmienia "proste tagi":
     *  - tblalias
     *  - field
     *  - whereParam
     *  - param
     * @param string      $sql Aktualnie budowane zapytanie.
     * @param ISqlDialect $sqlDialect
     * @param array       $sqlTemplateParams
     * @param array       $whereParams
     * @return string Uzupełnione zapytanie.
     */
    protected function replaceSimpleTags($sql, ISqlDialect $sqlDialect, $sqlTemplateParams, $whereParams) {
        $sqlTemplate = $sql;
        $replacer = new QueryTagsReplacer($sql);

        $replacer->replaceTags('tblalias', function($tagName, $args) use ($sqlTemplate, $sqlDialect) {
            $relPath = !empty($args) ? $args[0] : '';
            if (!isset($this->sourcesByRelationPath[$relPath])) {
                throw new \Exception("Build select query: unknown entity $relPath in query template $sqlTemplate");
            }
            return $this->sourcesByRelationPath[$relPath]['entityAlias'];
        });

        $replacer->replaceTags('field', function($tagName, $args) use ($sqlTemplate, $sqlDialect) {
            $fieldName = $args[0];
            $relPath = '';
            $p = strrpos($fieldName, '.');
            if (false !== $p) {
                $relPath = substr($fieldName, 0, $p);
                $fieldName = substr($fieldName, $p + 1);
            }
            if (!isset($this->sourcesByRelationPath[$relPath])) {
                throw new \Exception("Build select query: unknown entity $relPath in query template $sqlTemplate");
            }
            $sql = $sqlDialect->encodeTableAlias($this->sourcesByRelationPath[$relPath]['entityAlias']) . '.';
            $sql .= $sqlDialect->encodeColumnName($this->entityModel->getDbColumnName($this->sourcesByRelationPath[$relPath]['entityName'], $fieldName));
            return $sql;
        });
        // na koniec uzupełniamy parametry
        $replacer->replaceTags('whereParam', function($tagName, $args) use ($sqlTemplate, $sqlDialect, $whereParams) {
            return $whereParams[$args[0]];
        });
        $replacer->replaceTags('param', function($tagName, $args) use ($sqlTemplate, $sqlTemplateParams, $sqlDialect) {
            $value = $sqlTemplateParams[$args[0]];
            $type = IEntityFieldType::IMPLICIT;
            if (isset($args[1])) {
                // podano typ
                switch ($args[1]) {
                    case 's':
                    case 'str':
                        $type = IEntityFieldType::STRING;
                        break;
                    case 'i':
                    case 'int':
                    case 'integer':
                        $type = IEntityFieldType::INT;
                        break;
                    case 'f':
                    case 'float':
                        $type = IEntityFieldType::FLOAT;
                        break;
                }
            }
            $value = $sqlDialect->valueToSql($value, $type);
            return $value;
        });
        $sql = $replacer->getResult();
        return $sql;
    }
}

class SelectQueryBuilder extends BaseQueryBuilder {
    /**
     *
     * @var \pdquery\IQueryProperties
     */
    protected $query;

    public function __construct(\pdquery\IQueryProperties $query, $entityName, IEntityModel $entityModel, $complexFields = [], $sqlTemplate = null, $sqlTemplateParams = []) {
        if (empty($sqlTemplate)) {
            $sqlTemplate = 'SELECT %(columns) FROM %(tables) [WHERE]%(where) [GROUP BY]%(groupBy) [HAVING]%(having) [ORDER BY]%(orderBy)';
            $sqlTemplateParams = [];
        }
        parent::__construct($query, $entityName, $entityModel, $complexFields, $sqlTemplate, $sqlTemplateParams);
        $this->query = $query;
    }

    public function getSql(ISqlDialect $sqlDialect) {
        return $this->buildSql($this->sqlTemplate, $this->sqlTemplateParams, $sqlDialect);
    }

    protected function buildSql($sqlTemplate, $sqlTemplateParams, ISqlDialect $sqlDialect) {
        $replacer = new QueryTagsReplacer($sqlTemplate);

        $whereParams = [];
        $replacer->replaceTags('where', function($tagName, $args) use ($sqlDialect, &$whereParams) {
            $whereConditions = $this->query->getWhere();
            if (empty($whereConditions)) return '';
            $sql = $this->whereConditionsToSql($whereConditions, $sqlDialect, $whereParams);
            return $sql;
        });

        $replacer->replaceTags('orderBy', function($tagName, $args) use ($sqlDialect) {
            $orderBy = $this->query->getOrderBy();
            if (empty($orderBy)) return '';

            $orderBySql = '';
            $isFirst = true;
            foreach ($orderBy as $ob) {
                if (!$isFirst) $orderBySql .= ',';
                if (isset($this->complexFields[$ob['field']])) {
                    $orderBySql .= $this->complexFields[$ob['field']] . ' ' . $ob['direction'];
                } else {
                    $orderBySql .= '%(field:' . $ob['field'] . ') ' . $ob['direction'];
                }
                $isFirst = false;
            }
            return $orderBySql;
        });

        $replacer->replaceTags('groupBy', function($tagName, $args) use ($sqlDialect) {
            $groupBy = $this->query->getGroupBy();
            if (empty($groupBy)) return '';

            $groupBySql = '';
            $isFirst = true;
            foreach ($groupBy as $gb) {
                if (!$isFirst) $groupBySql .= ',';
                if (isset($this->complexFields[$gb])) {
                    $groupBySql .= $this->complexFields[$gb];
                } else {
                    $groupBySql .= '%(field:' . $gb . ') ';
                }
                $isFirst = false;
            }
            return $groupBySql;
        });
        $replacer->replaceTags('having', function($tagName, $args) use ($sqlDialect) {
            // TODO: doimplementować
            return '';
        });

        $replacer->replaceTags('tables', function($tagName, $args) use ($sqlDialect) {
            return $this->sourcesToSql($sqlDialect);
        });

        $replacer->replaceTags('columns', function($tagName, $args) use ($sqlDialect, $sqlTemplate) {
            $selectFields = $this->query->getFields();
            if ('*' == $selectFields) {
                $sql = '';
                foreach ($this->sourcesByRelationPath as $relPath => $src) {
                    if (!empty($sql)) $sql .= ',';
                    $fieldNames = $this->entityModel->getEntityFieldNames($this->sourcesByRelationPath[$relPath]['entityName']);
                    if (null === $fieldNames) {
                        $sql .= $sqlDialect->encodeTableAlias($this->sourcesByRelationPath[$relPath]['entityAlias']) . '.*';
                    } else {
                        $sql .= $this->getSelectColumns($fieldNames, $relPath, $sqlDialect);
                    }
                }
                return $sql;
            } else {
                $sql = '';
                foreach ($selectFields as $sf) {
                    if (!empty($sql)) $sql .= ',';
                    if ('.*' == substr($sf, -2)) {
                        // pola dla całej relacji
                        $relPath = substr($sf, 0, -2);
                        if (!isset($this->sourcesByRelationPath[$relPath])) {
                            throw new \Exception("Build select query for $sqlTemplate: Invalid field $sf - unknown relation $relPath");
                        }
                        $fieldNames = $this->entityModel->getEntityFieldNames($this->sourcesByRelationPath[$relPath]['entityName']);
                        if (null === $fieldNames) {
                            $sql .= $sqlDialect->encodeTableAlias($this->sourcesByRelationPath[$relPath]['entityAlias']) . '.*';
                        } else {
                            $sql .= $this->getSelectColumns($fieldNames, $relPath, $sqlDialect);
                        }
                    } else {
                        // pojedyncze pole
                        if (isset($this->complexFields[$sf])) {
                            $sql .= $this->complexFields[$sf] . ' AS ' . $sqlDialect->encodeColumnAlias($sf);
                        } else {
                            $relPath = '';
                            $fieldName = $sf;
                            $p = strrpos($sf, '.');
                            if (false !== $p) {
                                $relPath = substr($sf, 0, $p);
                                $fieldName = substr($sf, $p + 1);
                            }
                            if (!isset($this->sourcesByRelationPath[$relPath])) {
                                throw new \Exception("Build select query for $sqlTemplate: Invalid field $sf - unknown relation $relPath");
                            }
                            $columnName = $this->entityModel->getDbColumnName($this->sourcesByRelationPath[$relPath]['entityName'], $fieldName);
                            $sql .= $sqlDialect->encodeTableAlias($this->sourcesByRelationPath[$relPath]['entityAlias']) . '.' . $sqlDialect->encodeColumnName($columnName) . ' AS ';
                            if (empty($relPath)) {
                                $sql .= $sqlDialect->encodeColumnAlias($fieldName);
                            } else {
                                $sql .= $sqlDialect->encodeColumnAlias($relPath . '.' . $fieldName);
                            }
                        }
                    }
                }
                return $sql;
            }
        });

        $sql = $replacer->getResult();
        $sql = $this->replaceSimpleTags($sql, $sqlDialect, $sqlTemplateParams, $whereParams);

        $limit = $this->query->getLimit();
        $offset = $this->query->getOffset();
        if (null !== $limit || null !== $offset) {
            //$sql .= $sqlDialect->getLimitAndOffsetSqlPart($limit, $offset);
            $sql = $sqlDialect->sqlSetSelectLimit($sql, $limit, $offset);
        }
        return $sql;
    }


    protected function getSelectColumns(array $fieldNames, $relationPath, ISqlDialect $sqlDialect) {
        $sql = '';
        if (!isset($this->sourcesByRelationPath[$relationPath])) {
            throw new \Exception("Unable to build query - unknown relation path $relationPath");
        }
        $entityName = $this->sourcesByRelationPath[$relationPath]['entityName'];
        $entityAlias = $this->sourcesByRelationPath[$relationPath]['entityAlias'];
        foreach ($fieldNames as $fieldName) {
            if ($sql !== '') $sql .= ',';
            $sql .= $sqlDialect->encodeTableAlias($entityAlias);
            $sql .= '.' . $sqlDialect->encodeColumnName($this->entityModel->getDbColumnName($entityName, $fieldName));
            $sql .= ' AS ' . $sqlDialect->encodeColumnAlias(!empty($relationPath) ? $relationPath . '.' . $fieldName : $fieldName);
        }
        return $sql;
    }
}

class CountQueryBuilder extends BaseQueryBuilder {
    /**
     *
     * @var \pdquery\IQueryProperties
     */
    protected $query;

    protected $countFieldAlias;

    public function __construct(\pdquery\IQueryProperties $query, $entityName, IEntityModel $entityModel, $countFieldAlias = 'count', $complexFields = [], $sqlTemplate = null, $sqlTemplateParams = []) {
        if (empty($sqlTemplate)) {
            $sqlTemplate = 'SELECT %(columns) FROM %(tables) [WHERE]%(where)';
            $sqlTemplateParams = [];
        }
        $this->countFieldAlias = $countFieldAlias;
        parent::__construct($query, $entityName, $entityModel, $complexFields, $sqlTemplate, $sqlTemplateParams);
        $this->query = $query;
    }

    public function getSql(ISqlDialect $sqlDialect) {
        return $this->buildSql($this->sqlTemplate, $this->sqlTemplateParams, $sqlDialect);
    }

    protected function buildSql($sqlTemplate, $sqlTemplateParams, ISqlDialect $sqlDialect) {
        $replacer = new QueryTagsReplacer($sqlTemplate);

        $whereParams = [];
        $replacer->replaceTags('where', function($tagName, $args) use ($sqlDialect, &$whereParams) {
            $whereConditions = $this->query->getWhere();
            if (empty($whereConditions)) return '';
            $sql = $this->whereConditionsToSql($whereConditions, $sqlDialect, $whereParams);
            return $sql;
        });

        $replacer->replaceTags('tables', function($tagName, $args) use ($sqlDialect) {
            return $this->sourcesToSql($sqlDialect);
        });

        $replacer->replaceTags('columns', function($tagName, $args) use ($sqlDialect, $sqlTemplate) {
            return "count(*) AS " . $sqlDialect->encodeColumnAlias($this->countFieldAlias);
        });

        $sql = $replacer->getResult();
        $sql = $this->replaceSimpleTags($sql, $sqlDialect, $sqlTemplateParams, $whereParams);
        return $sql;
    }
}


class UpdateQueryBuilder extends BaseQueryBuilder {
    /**
     *
     * @var \pdquery\IUpdateQueryProperties
     */
    protected $query;

    public function __construct(\pdquery\IUpdateQueryProperties $query, $entityName, IEntityModel $entityModel, $complexFields = [], $sqlTemplate = null, $sqlTemplateParams = []) {
        if (empty($sqlTemplate)) {
            $sqlTemplate = 'UPDATE %(tables) SET %(set) [WHERE]%(where)';
            $sqlTemplateParams = [];
        }
        parent::__construct($query, $entityName, $entityModel, $complexFields, $sqlTemplate, $sqlTemplateParams);
        $this->query = $query;
    }

    public function getSql(ISqlDialect $sqlDialect) {
        return $this->buildSql($this->sqlTemplate, $this->sqlTemplateParams, $sqlDialect);
    }

    protected function buildSql($sqlTemplate, $sqlTemplateParams, ISqlDialect $sqlDialect) {
        $replacer = new QueryTagsReplacer($sqlTemplate);

        $whereParams = [];
        $replacer->replaceTags('where', function($tagName, $args) use ($sqlDialect, &$whereParams) {
            $whereConditions = $this->query->getWhere();
            if (empty($whereConditions)) return '';
            $sql = $this->whereConditionsToSql($whereConditions, $sqlDialect, $whereParams);
            return $sql;
        });

        $replacer->replaceTags('tables', function($tagName, $args) use ($sqlDialect) {
            return $this->sourcesToSql($sqlDialect);
        });

        $replacer->replaceTags('set', function($tagName, $args) use ($sqlDialect) {
            $setFields = $this->query->getUpdateFields();
            $set = '';
            foreach ($setFields as $field => $value) {
                if ($set != '') $set .= ', ';
                $set .= '%(field:' . $field . ') = ' . $sqlDialect->valueToSql($value, $this->getFieldType($field));
            }
            return $set;
        });

        $sql = $replacer->getResult();
        $sql = $this->replaceSimpleTags($sql, $sqlDialect, $sqlTemplateParams, $whereParams);
        return $sql;
    }
}


class DeleteQueryBuilder extends BaseQueryBuilder {
    /**
     *
     * @var \pdquery\IDeleteQueryProperties
     */
    protected $query;

    public function __construct(\pdquery\IDeleteQueryProperties $query, $entityName, IEntityModel $entityModel, $complexFields = [], $sqlTemplate = null, $sqlTemplateParams = []) {
        if (empty($sqlTemplate)) {
            $sqlTemplate = 'DELETE FROM %(tables) [WHERE]%(where)';
            $sqlTemplateParams = [];
        }
        parent::__construct($query, $entityName, $entityModel, $complexFields, $sqlTemplate, $sqlTemplateParams);
        $this->query = $query;
    }

    public function getSql(ISqlDialect $sqlDialect) {
        return $this->buildSql($this->sqlTemplate, $this->sqlTemplateParams, $sqlDialect);
    }

    protected function buildSql($sqlTemplate, $sqlTemplateParams, ISqlDialect $sqlDialect) {
        $replacer = new QueryTagsReplacer($sqlTemplate);

        $whereParams = [];
        $replacer->replaceTags('where', function($tagName, $args) use ($sqlDialect, &$whereParams) {
            $whereConditions = $this->query->getWhere();
            if (empty($whereConditions)) return '';
            $sql = $this->whereConditionsToSql($whereConditions, $sqlDialect, $whereParams);
            return $sql;
        });

        $replacer->replaceTags('tables', function($tagName, $args) use ($sqlDialect) {
            return $this->sourcesToSql($sqlDialect);
        });

        $sql = $replacer->getResult();
        $sql = $this->replaceSimpleTags($sql, $sqlDialect, $sqlTemplateParams, $whereParams);
        return $sql;
    }
}
