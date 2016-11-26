<?php
/**
 * pdquery v2.3.0
 * zmiany:
 * 2.3.0
 * - dodanie klasy InsertQueryBuilder
 * 2.2.0
 * - wsparcie dla SQLite
 * 2.1.0
 * - dodanie źródła CollectionDataSource
 * - drobne poprawki
 * 2.0.0
 * - usunięcie CountQuery (obiekty i interfejsy) - przeniesienie funkcjonalności do IQuery, zmiana nazwy metody w źródle na countRows [utrata kompatybilności wstecz]
 * - dodanie metody hasRows (IQuery, IDataSource) [utrata kompatybilności wstecz - konieczność zaimplementowania metody w źródle danych]
 * - poprawiony autoloader (podział na pliki autoloader - tu wprowadzenie klasy i autoload - rejestracja autoloadera)
 * - dodanie klasy OverrideQueryProperties
 * - drobne poprawki
 * 1.1.0
 * - dodanie wsparcia dla ms sql
 */
namespace pdquery;

interface IDataSource {
    public function getData(IQueryProperties $query, $selectOptions = null);
    public function getFirst(IQueryProperties $query, $selectOptions = null);
    public function hasRows(IQueryProperties $query, $options = null);
    public function countRows(IQueryProperties $query, $countOptions = null);

    /**
     * @return IQuery
     */
    public function query();
}

interface IDataSourceUpdater {
    public function updateData(IUpdateQueryProperties $query, $updateOptions = null);
    public function deleteData(IDeleteQueryProperties $query, $deleteOptions = null);

    /**
     * @param mixed $data Tablica lub iterator; elementami są tablice asocjacyjne lub obiekty.
     */
    public function insertData($data);

    /**
     * @return IUpdateQuery
     */
    public function updateQuery();

    /**
     * @return IDeleteQuery
     */
    public function deleteQuery();
}

interface IQuery extends \Countable, \IteratorAggregate {
    /**
     *
     * @param string ...$relationName
     * @return IQuery
     */
    public function withRelation($relationName);

    /**
     * Przykłady:
     * ->where('id', 5)
     * ->where('id', '<>', 5)
     * ->where('accepted')
     * ->where('date', '>', '2015-05-05')
     * ->where('id', [1,2,3])
     *
     * Warunki podrzędne - np. dla WHERE date > '2015-05-05' AND (date < '2018' OR status = 'planned')
     * $query = new Query(); // lub w inny sposób pobieramy zapytanie (np. z repozytorium jakiegoś modelu)
     * $query->where('date', '>', '2015-05-05')
     *  ->where(
     *      where('date', '<', '2018')
     *               ->orWhere('status', 'planned'
     *  )
     *
     * @param string $field
     * @param mixed  $valueOrOperator Wartość pola lub operator (jeśli operator to wartość pola podawana w 3-cim argumencie)
     * @return IQuery
     */
    public function where($field, $valueOrOperator = 1);

    /**
     *
     * @param string $field
     * @param mixed  $valueOrOperator Wartość pola lub operator (jeśli operator to wartość pola podawana w 3-cim argumencie)
     * @return IQuery
     */
    public function orWhere($field, $valueOrOperator = 1);

    /**
     *
     * @param string $field
     * @param bool $asc true = kolejność rosnąca, false - malejąca
     * @return IQuery
     */
    public function orderBy($field, $asc = true);

    /**
     * Parametrami są identyfikatory pól
     * @param string $field
     * @return IQuery
     */
    public function groupBy($field);

    /**
     * Jako argumenty podajemy nazwy pól, które mają znaleźć się w wyniku.
     *
     * @return IQuery
     */
    public function fields();

    /**
     * Jako argumenty podajemy pola, które mają znaleźć się w wyniku.
     *
     * @return IQuery
     */
    public function appendFields();

    /**
     *
     * @param int $limit
     * @param int $offset
     * @return IQuery
     */
    public function limit($limit, $offset = -1);

    /**
     *
     * @param int $offset
     * @return IQuery
     */
    public function offset($offset);

    public function selectFirst();

    public function selectById($id);

    /**
     * Select domyślnie powinien zwracać kolekcję.
     */
    public function select();
    
    /**
     * Wybiera pierwsze pole (z pierwszego znalezionego wiersza).
     * Jest to odpowiednik selectFirst()['pole']
     * Np. ...query()->selectFirst()['id'] można zapisać jako ...query()->fields('id')->selectScalar()
     */
    public function selectScalar();

    /**
     * Zwraca true, jeśli zapytanie ma wyniki (ilość wierszy > 0)
     * @return bool
     */
    public function hasRows();
}

interface IBaseQueryProperties {
    public function getIncludeRelations();
    public function getWhere();
}

interface IQueryProperties extends IBaseQueryProperties {
    public function getOrderBy();
    public function getFields();
    public function getGroupBy();
    public function getLimit();
    public function getOffset();
}

interface IUpdateQuery {
    /**
     *
     * @param string $relationName
     * @return IUpdateQuery
     */
    public function withRelation($relationName);

    /**
     * Przykłady;
     * ->where('id', 5)
     * ->where('id', '<>', 5)
     * ->where('accepted')
     * ->where('date', '>', '2015-05-05')
     * ->where('id', [1,2,3])
     *
     * Warunki podrzędne - np. dla WHERE date > '2015-05-05' AND (date < '2018' OR status = 'planned')
     * $query = new Query(); // lub w inny sposób pobieramy zapytanie (np. z repozytorium jakiegoś modelu)
     * $query->where('date', '>', '2015-05-05')
     *  ->where(
     *      where('date', '<', '2018')
     *               ->orWhere('status', 'planned'
     *  )
     *
     * @param mixed $field
     * @return IUpdateQuery
     */
    public function where($field, $valueOrOperator = 1);

    /**
     *
     * @param mixed $field
     * @return IUpdateQuery
     */
    public function orWhere($field, $valueOrOperator = 1);

    /**
     * @param mixed $fieldOrAssoc Nazwa pola lub tablica asocjacyjna nazwa pola => wartość (lub obiekt z polami publicznymi)
     * @param mixed $value
     * @return IUpdateQuery
     */
    public function set($fieldOrAssoc, $value = null);
    // set expr ? że np value = value * 2 itp

    public function update();
}

interface IUpdateQueryProperties extends IBaseQueryProperties {
    public function getUpdateFields();
}

interface IDeleteQuery {
    /**
     *
     * @param string $relationName
     * @return IUpdateQuery
     */
    public function withRelation($relationName);

    /**
     * Przykłady;
     * ->where('id', 5)
     * ->where('id', '<>', 5)
     * ->where('accepted')
     * ->where('date', '>', '2015-05-05')
     * ->where('id', [1,2,3])
     *
     * Warunki podrzędne - np. dla WHERE date > '2015-05-05' AND (date < '2018' OR status = 'planned')
     * $query = new SelectQuery(); // lub w inny sposób pobieramy zapytanie (np. z repozytorium jakiegoś modelu)
     * $query->where('date', '>', '2015-05-05')
     *  ->where(
     *      where('date', '<', '2018')
     *               ->orWhere('status', 'planned'
     *  )
     *
     * @param string $field
     * @param mixed  $valueOrOperator
     * @return IUpdateQuery
     */
    public function where($field, $valueOrOperator = 1);

    /**
     *
     * @param string $field
     * @param mixed  $valueOrOperator
     * @return IUpdateQuery
     */
    public function orWhere($field, $valueOrOperator = 1);

    public function delete();
}

interface IDeleteQueryProperties extends IBaseQueryProperties {
}

interface IWhereConditions {
    /**
     * Przykłady;
     * ->where('id', 5)
     * ->where('id', '<>', 5)
     * ->where('accepted')
     * ->where('date', '>', '2015-05-05')
     * ->where('id', [1,2,3])
     *
     * Warunki podrzędne - np. dla WHERE date > '2015-05-05' AND (date < '2018' OR status = 'planned')
     * $query = new SelectQuery(); // lub w inny sposób pobieramy zapytanie (np. z repozytorium jakiegoś modelu)
     * $query->where('date', '>', '2015-05-05')
     *  ->where(
     *      where('date', '<', '2018')
     *               ->orWhere('status', 'planned'
     *  )
     *
     * @param string $field
     * @param mixed  $valueOrOperator
     * @return IWhereConditions
     */
    public function where($field, $valueOrOperator = 1);

    /**
     *
     * @param string $field
     * @param mixed  $valueOrOperator
     * @return IWhereConditions
     */
    public function orWhere($field, $valueOrOperator = 1);
}

interface IWhereConditionsProperties {
    public function getWhere();
}

/**
 * Funkcja służąca do dodawania zagnieżdżonych warunków where.
 * @param string $field
 * @param mixed  $valueOrOperator
 * @return IWhereConditions
 */
function where($field, $valueOrOperator = 1) {
    $whereConditions = new WhereConditions();
    call_user_func_array([$whereConditions, 'where'], func_get_args());
    return $whereConditions;
}

trait TWhereConditions {
    protected $where = null;

    public function where($field, $valueOrOperator = 1) {
        $args = func_get_args();
        if (2 == count($args)) {
            // format field, value
            $value = $valueOrOperator;
            $operator = '=';
        } elseif (3 == count($args)) {
            $operator = $valueOrOperator;
            $value = $args[2];
        } elseif (1 == count($args)) {
            $value = 1;
            $operator = '=';
        } else {
            throw new \Exception('Query where: invalid number of arguments');
        }
        if (null === $this->where) {
            $this->where = [];
        }
        if ($field instanceof IWhereConditionsProperties) {
            // warunek podrzędny zawarty w części where podanego zapytania
            $this->where[] = ['subQuery' => $field, 'join' => 'AND'];
        } else {
            if ('=' == $operator && is_array($value)) $operator = 'IN';
            $this->where[] = ['field' => $field, 'value' => $value, 'join' => 'AND', 'operator' => $operator];
        }
        return $this;
    }

    public function orWhere($field, $valueOrOperator = 1) {
        $args = func_get_args();
        if (2 == count($args)) {
            // format field, value
            $value = $valueOrOperator;
            $operator = '=';
        } elseif (3 == count($args)) {
            $operator = $valueOrOperator;
            $value = $args[2];
        } else {
            throw new \Exception('Query orWhere: invalid number of arguments');
        }
        if (null === $this->where) {
            $this->where = [];
        }
        if ($field instanceof IWhereConditionsProperties) {
            // warunek podrzędny zawarty w części where podanego zapytania
            $this->where[] = ['subQuery' => $field, 'join' => 'OR'];
        } else {
            if ('=' == $operator && is_array($value)) $operator = 'IN';
            $this->where[] = ['field' => $field, 'value' => $value, 'join' => 'OR', 'operator' => $operator];
        }
        return $this;
    }

    public function getWhere() {
        return $this->where;
    }
}

class WhereConditions implements IWhereConditions, IWhereConditionsProperties {
    use TWhereConditions;
}

class BaseQuery implements IBaseQueryProperties {
    protected $includeRelations = null;

    use TWhereConditions;

    public function withRelation($relationName) {
        if (null === $this->includeRelations) {
            $this->includeRelations = [];
        }
        foreach (func_get_args() as $relationName) {
            if (is_array($relationName)) {
                $this->includeRelations = array_merge($this->includeRelations, $relationName);
            } else {
                $this->includeRelations[] = $relationName;
            }
        }
        return $this;
    }

    public function getIncludeRelations() {
        return $this->includeRelations;
    }
}


class Query extends BaseQuery implements IQuery, IQueryProperties {
    protected $fields = '*';
    protected $orderBy = null;
    protected $groupBy = null;
    protected $limit = null;
    protected $offset = null;

    /**
     *
     * @var IDataSource
     */
    protected $dataSource;

    public function __construct(IDataSource $dataSource = null) {
        $this->dataSource = $dataSource;
    }


    public function orderBy($field, $asc = true) {
        if (null === $this->orderBy) {
            $this->orderBy = [];
        }
        $this->orderBy[] = ['field' => $field, 'direction' => $asc ? 'ASC' : 'DESC'];
        return $this;
    }

    public function groupBy($field) {
        if (null === $this->groupBy) {
            $this->groupBy = [];
        }
        $this->groupBy = array_merge($this->groupBy, func_get_args());
        return $this;
    }

    public function fields() {
        $fields = func_get_args();
        if (is_array($fields[0])) {
            $this->fields = $fields[0];
        } else {
            $this->fields = $fields;
        }
        return $this;
    }

    public function appendFields() {
        $fields = func_get_args();
        if (is_array($fields[0])) {
            $this->fields = array_merge($this->fields, $fields[0]);
        } else {
            $this->fields = array_merge($this->fields, $fields);
        }
        return $this;
    }

    public function limit($limit, $offset = -1) {
        $this->limit = $limit;
        if ($offset !== -1) $this->offset = $offset;
        return $this;
    }

    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    public function selectFirst() {
        $oldLimit = $this->limit;
        $this->limit = 1;
        $options = func_get_args();
        $data = $this->dataSource->getFirst($this, $options);
        $this->limit = $oldLimit;
        return $data;
    }
    
    public function selectScalar() {
        $row = $this->selectFirst();
        if (is_object($row)) {
            foreach ($row as $k => $v) return $v;
        } else {
            return reset($row);
        }
        return null;
    }

    public function selectById($id) {
        $options = func_get_args();
        array_shift($options); // pomijamy pierwszy argument - id
        $oldWhere = $this->where;
        $this->where = [['id' => $id, 'join' => 'AND', 'operator' => '=']];

        if (is_array($id)) {
            $data = $this->dataSource->getData($this, $options);
        } else {
            $data = $this->dataSource->getFirst($this, $options);
        }
        $this->where = $oldWhere;
        return $data;
    }

    public function select() {
        $options = func_get_args();
        return $this->dataSource->getData($this, $options);
    }

    public function count() {
        return $this->dataSource->getRowsCount($this);
    }

    public function hasRows() {
        return $this->dataSource->hasRows($this);
    }

    public function getOrderBy() {
        return $this->orderBy;
    }

    public function getFields() {
        return $this->fields;
    }

    public function getGroupBy() {
        return $this->groupBy;
    }

    public function getLimit() {
        return $this->limit;
    }

    public function getOffset() {
        return $this->offset;
    }

    public function getIterator() {
        $data = $this->select();
        if (is_array($data)) return new \ArrayIterator($data);
        return $data;
    }
}

class UpdateQuery extends BaseQuery implements IUpdateQuery, IUpdateQueryProperties {
    /**
     * @var IDataSourceUpdater
     */
    protected $dataSource;

    protected $updateFields = null;

    /**
     * UpdateQuery constructor.
     * @param IDataSourceUpdater $dataSource
     */
    public function __construct(IDataSourceUpdater $dataSource = null) {
        $this->dataSource = $dataSource;
    }

    public function set($fieldOrAssoc, $value = null) {
        if (is_array($fieldOrAssoc)) {
            if (null === $this->updateFields) {
                $this->updateFields = $fieldOrAssoc;
            } else {
                foreach ($fieldOrAssoc as $k => $v) {
                    $this->updateFields[$k] = $v;
                }
            }
        } elseif (is_object($fieldOrAssoc)) {
            if (null === $this->updateFields) $this->updateFields = [];
            foreach ($fieldOrAssoc as $k => $v) {
                $this->updateFields[$k] = $v;
            }
        } else {
            if (null === $this->updateFields) $this->updateFields = [];
            $field = $fieldOrAssoc;
            $this->updateFields[$field] = $value;
        }
        return $this;
    }

    public function update() {
        return $this->dataSource->updateData($this, func_get_args());
    }

    public function getUpdateFields() {
        return $this->updateFields;
    }
}

class DeleteQuery extends BaseQuery implements IDeleteQuery, IDeleteQueryProperties {
    /**
     * @var IDataSourceUpdater
     */
    protected $dataSource;

    /**
     * DeleteQuery constructor.
     * @param IDataSourceUpdater $dataSource
     */
    public function __construct(IDataSourceUpdater $dataSource = null) {
        $this->dataSource = $dataSource;
    }

    public function delete() {
        return $this->dataSource->deleteData($this, func_get_args());
    }
}

/**
 * Klasa służąca do nadpisywania wybranych parametrów zapytania.
 */
class OverrideQueryProperties implements IQueryProperties {

    /**
     * @var IQueryProperties
     */
    protected $originalQueryProperties;
    
    protected $includeRelations;
    protected $where;
    protected $orderBy;
    protected $fields;
    protected $groupBy;
    protected $limit;
    protected $offset;
    
    protected $overrideIncludeRelations = false;
    protected $overrideWhere = false;
    protected $overrideOrderBy = false;
    protected $overrideFields = false;
    protected $overrideGroupBy = false;
    protected $overrideLimit = false;
    protected $overrideOffset = false;

    public function __construct(IQueryProperties $originalQueryProperties) {
        $this->originalQueryProperties = $originalQueryProperties;
    }

    public function getIncludeRelations() {
        if ($this->overrideIncludeRelations !== null) return $this->includeRelations;
        return $this->originalQueryProperties->getIncludeRelations();
    }

    public function getWhere() {
        if ($this->overrideWhere) return $this->where;
        return $this->originalQueryProperties->getWhere();
    }

    public function getOrderBy() {
        if ($this->overrideOrderBy) return $this->orderBy;
        return $this->originalQueryProperties->getOrderBy();
    }

    public function getFields() {
        if ($this->overrideFields) return $this->fields;
        return $this->originalQueryProperties->getFields();
    }

    public function getGroupBy() {
        if ($this->overrideGroupBy) return $this->groupBy;
        return $this->originalQueryProperties->getGroupBy();
    }

    public function getLimit() {
        if ($this->overrideLimit) return $this->limit;
        return $this->originalQueryProperties->getIncludeRelations();
    }

    public function getOffset() {
        if ($this->overrideOffset) return $this->offset;
        return $this->originalQueryProperties->getIncludeRelations();
    }

    /**
     * @param mixed $includeRelations
     * @return OverrideQueryProperties
     */
    public function overrideIncludeRelations($includeRelations) {
        $this->overrideIncludeRelations = true;
        $this->includeRelations = $includeRelations;
        return $this;
    }

    /**
     * @param mixed $where
     * @return OverrideQueryProperties
     */
    public function overrideWhere($where) {
        $this->overrideWhere = true;
        $this->where = $where;
        return $this;
    }

    /**
     * @param mixed $orderBy
     * @return OverrideQueryProperties
     */
    public function overrideOrderBy($orderBy) {
        $this->overrideOrderBy = true;
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * @param mixed $fields
     * @return OverrideQueryProperties
     */
    public function overrideFields($fields) {
        $this->overrideFields = true;
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param mixed $groupBy
     * @return OverrideQueryProperties
     */
    public function overrideGroupBy($groupBy) {
        $this->overrideGroupBy = true;
        $this->groupBy = $groupBy;
        return $this;
    }

    /**
     * @param mixed $limit
     * @return OverrideQueryProperties
     */
    public function overrideLimit($limit) {
        $this->overrideLimit = true;
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param mixed $offset
     * @return OverrideQueryProperties
     */
    public function overrideOffset($offset) {
        $this->overrideOffset = true;
        $this->offset = $offset;
        return $this;
    }
}