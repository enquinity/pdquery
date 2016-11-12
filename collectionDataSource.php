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

    public function getData(IQueryProperties $query, $selectOptions = null) {
        ///$flags =
        $returnAsArray = false;

        $orderBy = $query->getOrderBy();
        $where = $query->getWhere();
        $offset = $query->getOffset();
        $limit = $query->getLimit();
        if (empty($orderBy)) {
            if (empty($where)) {
                return $this->data;
            } else {
                if ($returnAsArray) {
                    return $this->getFilteredRows($query, $offset, $limit);
                } else {
                    return $this->getFilteredRowsGenerator($query, $offset, $limit);
                }
            }
        } else {
            $data = empty($where) ? $this->data : $this->getFilteredRows($query);
            if (!is_array($data)) {
                $data = iterator_to_array($data);
            }
            if (count($orderBy) == 1) {
                if (is_string($orderBy[0]['field'])) {
                    usort($data, function($a, $b) use ($orderBy) {
                        $ob = $orderBy[0];
                        if (is_string($a[$ob['field']]) || is_string($b[$ob['field']])) {
                            $cmp = strcmp($a[$ob['field']], $b[$ob['field']]);
                        } else {
                            $cmp = $a[$ob['field']] > $b[$ob['field']] ? 1 : ($a[$ob['field']] < $b[$ob['field']] ? -1 : 0);
                        }
                        if ('ASC' != $ob['direction']) $cmp *= -1;
                        return $cmp;
                    });
                } else {
                    // używamy podanej z zewnątrz funkcji sortującej
                    usort($data, $orderBy[0]['field']);
                }
            } else {
                usort($data, function($a, $b) use ($orderBy) {
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
                });
            }
            if ($returnAsArray) {
                if ($offset !== null || $limit !== null) {
                    $data = array_slice($data, $offset !== null ? $offset : 0, $limit, false);
                }
                return $data;
            } else {
                return $this->getRangeGenerator($data, $offset, $limit);
            }
        }
    }

    protected function getFilteredRows(IQueryProperties $query, $offset = null, $limit = null) {
        // TODO: obsługa indeksów
        $rows = [];
        $where = $query->getWhere();
        $decisionTreeBuilder = new FilterDecisionTreeBuilder();
        $decisionTree = $decisionTreeBuilder->build($where);
        $pos = -1;
        foreach ($this->data as $row) {
            if ($this->checkFilter($row, $decisionTree)) {
                $pos++;
                if (null === $offset || $pos >= $offset) {
                    if ($limit !== null && $pos >= $limit) break;
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    protected function getFilteredRowsGenerator(IQueryProperties $query, $offset = null, $limit = null) {
        // TODO: obsługa indeksów
        $where = $query->getWhere();
        $decisionTreeBuilder = new FilterDecisionTreeBuilder();
        $decisionTree = $decisionTreeBuilder->build($where);
        $pos = -1;
        foreach ($this->data as $row) {
            if ($this->checkFilter($row, $decisionTree)) {
                $pos++;
                if (null === $offset || $pos >= $offset) {
                    if ($limit !== null && $pos >= $limit) break;
                    yield $row;
                }
            }
        }
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
                // TODO: like and not like
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
        // TODO: Implement getFirst() method.
    }

    public function hasRows(IQueryProperties $query, $options = null) {
        // TODO: Implement hasRows() method.
    }

    public function countRows(IQueryProperties $query, $countOptions = null) {
        // TODO: Implement countRows() method.
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