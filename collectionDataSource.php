<?php
namespace pdquery;

class CollectionDataSource implements IDataSource {

    protected $data;
    protected $dataIndexedBy = null;
    protected $indexes = [];
    protected $keyFieldName;

    public function __construct($data, $keyFieldName, $dataIndexedBy = null) {
        $this->data = $data;
        $this->dataIndexedBy = $dataIndexedBy;
        $this->keyFieldName = $keyFieldName;
    }

    public function addIndex($fieldName) {
        if (!is_array($this->data)) {
            // aby działały indeksy, dane muszą być w tablicy
            $this->data = iterator_to_array($this->data);
        }
        $this->indexes[$fieldName] = [];
        foreach ($this->data as $key => $row) {
            if (!array_key_exists($fieldName, $row)) continue;
            $iv = is_object($row) ? $row->$fieldName : $row[$fieldName];
            if (!isset($this->indexes[$fieldName][$iv])) {
                $this->indexes[$fieldName][$iv] = [];
            }
            $this->indexes[$fieldName][$iv][] = $key;
        }
    }

    protected function getComparator($orderBy) {
        if (count($orderBy) == 1) {
            if (is_string($orderBy[0]['field'])) {
                $comparator = function($a, $b) use ($orderBy) {
                    $ob = $orderBy[0];
                    if (is_string($a[$ob['field']]) || is_string($b[$ob['field']])) {
                        $cmp = strcmp($a[$ob['field']], $b[$ob['field']]);
                    } else {
                        $cmp = $a[$ob['field']] > $b[$ob['field']] ? 1 : ($a[$ob['field']] < $b[$ob['field']] ? -1 : 0);
                    }
                    if ('ASC' != $ob['direction']) $cmp *= -1;
                    return $cmp;
                };
            } else {
                // używamy podanej z zewnątrz funkcji sortującej
                $comparator = $orderBy[0]['field'];
            }
        } else {
            $comparator = function($a, $b) use ($orderBy) {
                foreach ($orderBy as $ob) {
                    if (is_string($ob['field'])) {
                        if (is_string($a[$ob['field']]) || is_string($b[$ob['field']])) {
                            $cmp = strcmp($a[$ob['field']], $b[$ob['field']]);
                        } else {
                            $cmp = $a[$ob['field']] > $b[$ob['field']] ? 1 : ($a[$ob['field']] < $b[$ob['field']] ? -1 : 0);
                        }
                    } else {
                        // używamy podanej z zewnątrz funkcji sortującej
                        $cmp = call_user_func($ob['field'], $a, $b);
                    }
                    if ($cmp == 0) continue;
                    if ('ASC' != $ob['direction']) $cmp *= -1;
                    return $cmp;
                }
                return 0;
            };
        }
        return $comparator;
    }

    public function getData(IQueryProperties $query, $selectOptions = null) {
        $returnAsArray = false;
        $wrapWithCollection = false;
        if (!empty($selectOptions) && is_array($selectOptions) && !empty($selectOptions[0])) {
            $sl = strlen($selectOptions[0]);
            for ($p = 0; $p < $sl; $p++) {
                switch ($selectOptions[0][$p]) {
                    case 'a': $returnAsArray = true; break;
                    case 'i': $returnAsArray = false; break;
                    case 'x': $wrapWithCollection = true; break;
                    default:
                        throw new \Exception("Invalid flag " . $selectOptions[0][$p]);
                }
            }
        }
        $orderBy = $query->getOrderBy();
        $where = $query->getWhere();
        $offset = $query->getOffset();
        $limit = $query->getLimit();
        if (empty($orderBy)) {
            if (empty($where)) {
                $result = $this->data;
            } else {
                $result = $returnAsArray ? $this->getFilteredRowsArray($query, $offset, $limit) :
                    $this->getFilteredRowsGenerator($query, $offset, $limit);
            }
        } else {
            $data = empty($where) ? $this->data : $this->getFilteredRowsArray($query);
            if (!is_array($data)) {
                $data = iterator_to_array($data);
            }
            usort($data, $this->getComparator($orderBy));
            if ($returnAsArray) {
                if ($offset !== null || $limit !== null) {
                    $data = array_slice($data, $offset !== null ? $offset : 0, $limit, false);
                }
                $result = $data;
            } else {
                $result = $this->getRangeGenerator($data, $offset, $limit);
            }
        }
        if ($wrapWithCollection) {
            $result = new Collection($result);
        }
        return $result;
    }

    protected function getFilteredRows(IQueryProperties $query, $returnAsGenerator, &$rows, $offset = null, $limit = null) {
        if (!$returnAsGenerator) $rows = [];
        $where = $query->getWhere();
        $decisionTreeBuilder = new FilterDecisionTreeBuilder();
        $decisionTree = $decisionTreeBuilder->build($where);
        $pos = -1;

        $cond = $decisionTree[0][0];
        if ($decisionTree[0][2] === false && ($cond['operator'] == '=' || $cond['operator'] == 'IN')) {
            $field = array_key_exists('id', $cond) ? $this->keyFieldName : $cond['field'];
            $condValue = array_key_exists('id', $cond) ? $cond['id'] : $cond['value'];

            $keys = [];
            if ($this->dataIndexedBy === $field) {
                if (is_array($condValue)) {
                    foreach ($condValue as $v) $keys[$v] = true;
                } else {
                    $keys[$condValue] = true;
                }
            } elseif (isset($this->indexes[$field])) {
                if (is_array($condValue)) {
                    foreach ($condValue as $cv) {
                        if (isset($this->indexes[$field][$cv])) {
                            foreach ($this->indexes[$field][$cv] as $k) $keys[$k] = true;
                        }
                    }
                } else {
                    if (isset($this->indexes[$field][$condValue])) {
                        foreach ($this->indexes[$field][$condValue] as $k) $keys[$k] = true;
                    }
                }
                if (count($keys) == 0) return; // dla tej wartości nie ma wierszy w indeksie
            }
            if (count($keys) > 0) {
                foreach ($keys as $key => $true) {
                    $row = $this->data[$key];
                    if (1 == count($decisionTree) || $this->checkFilter($row, $decisionTree)) {
                        $pos++;
                        if (null === $offset || $pos >= $offset) {
                            if ($limit !== null && $pos >= $limit) break;
                            if ($returnAsGenerator) {
                                yield $row;
                            } else {
                                $rows[] = $row;
                            }
                        }
                    }
                }
                return;
            }
        }

        foreach ($this->data as $row) {
            if ($this->checkFilter($row, $decisionTree)) {
                $pos++;
                if (null === $offset || $pos >= $offset) {
                    if ($limit !== null && $pos >= $limit) break;

                    if ($returnAsGenerator) {
                        yield $row;
                    } else {
                        $rows[] = $row;
                    }
                }
            }
        }
    }

    protected function getFilteredRowsArray(IQueryProperties $query, $offset = null, $limit = null) {
        $rows = null;
        $g = $this->getFilteredRows($query, false, $rows, $offset, $limit);

        // aby powyższa funkcja się wykonała, gdyż PHP widzi ją jako generator
        // pętla nie powinna mieć żadnej iteracji, jednak jest potrzebna do uruchomienia powyższej metody
        foreach ($g as $whatever) {
            throw new \Exception("Unexpected instruction");
        }
        return $rows;
    }

    protected function getFilteredRowsGenerator(IQueryProperties $query, $offset = null, $limit = null) {
        $null = null;
        return $this->getFilteredRows($query, true, $null);
    }

    protected function checkFilter($row, array $whereDecisionTree) {
        if (empty($whereDecisionTree)) return true;
        $pos = 0;
        // ustawiamy maksymalną ilość iteracji, jako zabezpieczenie przed nieskończoną pętlą
        for ($n = 0; $n < 100; $n++) {
            $cond = $whereDecisionTree[$pos][0];
            $accept = false;

            $condOperator = $cond['operator'];
            if (array_key_exists('id', $cond)) {
                $condValue = $cond['id'];
                $rowField = $this->keyFieldName;
            } else {
                $condValue = $cond['value'];
                $rowField = $cond['field'];
            }
            $rowValue = is_array($row) ? (array_key_exists($rowField, $row) ? $row[$rowField] : null) : $row->$rowField;
            switch ($condOperator) {
                case '=': $accept = $condValue == $rowValue; break;
                case '>': $accept = $rowValue > $condValue; break;
                case '<': $accept = $rowValue < $condValue; break;
                case '>=': $accept = $rowValue >= $condValue; break;
                case '<=': $accept = $rowValue <= $condValue; break;
                case '<>': // FALL THROUGH
                case '!=':
                    $accept = $rowValue != $condValue;
                    break;
                case 'IN': $accept = in_array($rowValue, $condValue); break;
                case 'NOT IN': $accept = !in_array($rowValue, $condValue); break;
                case 'LIKE': // FALL THROUGH
                case 'NOT LIKE':
                    $regex = '/^' . str_replace([chr(18), '%'], ['.', '.*'], preg_quote(str_replace('?', chr(18), $condValue))) . '$/';
                    $accept = (preg_match($regex, $rowValue) ? true : false) ^ ($condOperator != 'LIKE');
                    break;
                default:
                    throw new \Exception("Unsupported operator $condOperator");
            }

            $result = $whereDecisionTree[$pos][$accept ? 1 : 2];
            if (false === $result || true === $result) return $result;
            $pos = $result;
        }
        return false;
    }

    protected function getRangeGenerator($data, $offset, $limit) {
        $pos = 0;
        foreach ($data as $row) {
            if ($limit !== null && $pos >= $limit) break;
            if ($offset === null || $pos >= $offset) {
                yield $row;
            }
            $pos++;
        }
    }

    public function getFirst(IQueryProperties $query, $selectOptions = null) {
        $orderBy = $query->getOrderBy();
        $where = $query->getWhere();
        if (empty($orderBy)) {
            if (empty($where)) {
                foreach ($this->data as $row) {
                    return $row;
                }
                return null;
            } else {
                $rows = $this->getFilteredRowsArray($query, null, 1);
                return !empty($rows) ? $rows[0] : null;
            }
        } else {
            $comparator = $this->getComparator($orderBy);
            $data = empty($where) ? $this->data : $this->getFilteredRowsGenerator($query);
            $result = null;
            foreach ($data as $row) {
                if (null === $result) {
                    $result = $row;
                } else {
                    $cmp = call_user_func($comparator, $row, $result);
                    if (-1 == $cmp) $result = $row;
                }
            }
            return $result;
        }
    }

    public function hasRows(IQueryProperties $query, $options = null) {
        $where = $query->getWhere();
        if (empty($where)) {
            if ($this->data instanceof \Countable) {
                return $this->data->count() > 0;
            }
            if (!is_array($this->data)) {
                $this->data = iterator_to_array($this->data);
            }
            return count($this->data) > 0;
        } else {
            $rows = $this->getFilteredRowsGenerator($query);
            foreach ($rows as $row) {
                return true;
            }
            return false;
        }
    }

    public function countRows(IQueryProperties $query, $countOptions = null) {
        $where = $query->getWhere();
        if (empty($where)) {
            if ($this->data instanceof \Countable) {
                return $this->data->count();
            }
            if (!is_array($this->data)) {
                $this->data = iterator_to_array($this->data);
            }
            return count($this->data);
        } else {
            $rows = $this->getFilteredRowsGenerator($query);
            $cnt = 0;
            foreach ($rows as $row) {
                $cnt++;
            }
            return $cnt;
        }
    }

    /**
     * @return IQuery
     */
    public function query() {
        return new Query($this);
    }
}

class FilterDecisionTreeBuilder {
    // array (array(condition, action_if_true, action_if_false))
    // action_if_true, action_if_false = true|false (return value) or int - index of next node to check
    protected $tree;

    public function build(array $whereConditions) {
        $this->tree = [];
        $this->processWhere($whereConditions);
        return $this->tree;
    }

    protected function processWhere(array $whereConditions) {
        $segmentStartPos = count($this->tree);
        $currentAndFromId = $segmentStartPos;
        foreach ($whereConditions as $wc) {
            $newElId = count($this->tree);
            switch ($wc['join']) {
                case 'AND':
                    for ($i = $currentAndFromId; $i < count($this->tree); $i++) {
                        if ($this->tree[$i][1] === true) {
                            $this->tree[$i][1] = $newElId;
                        }
                    }
                    break;
                case 'OR':
                    for ($i = $segmentStartPos; $i < count($this->tree); $i++) {
                        if ($this->tree[$i][2] === false) {
                            $this->tree[$i][2] = $newElId;
                        }
                    }
                    $currentAndFromId = $newElId;
                    break;
            }
            if (isset($wc['subQuery'])) {
                $this->processWhere($wc['subQuery']->getWhere());
            } else {
                $this->tree[] = [$wc, true, false];
            }
        }
    }
}