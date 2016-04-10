<?php
namespace pdquery\Sql;
use pdquery\EntityModel\IEntityModel;
use pdquery\Sql\Dialects\ISqlDialect;

interface ISqlDataSource extends \pdquery\IDataSource {
    public function getSelectSql(\pdquery\IQueryProperties $query);
    public function getCountSql(\pdquery\ICountQueryProperties $query);
}

interface ISqlDataSourceUpdater extends \pdquery\IDataSourceUpdater {
    public function getUpdateSql(\pdquery\IUpdateQueryProperties $query);
    public function getDeleteSql(\pdquery\IDeleteQueryProperties $query);
}

interface ISqlQuery extends \pdquery\IQuery {
    public function getSelectSql();
}

interface ISqlCountQuery extends \pdquery\ICountQuery {
    public function getCountSql();
}

interface ISqlUpdateQuery extends \pdquery\IUpdateQuery {
    public function getUpdateSql();
}

interface ISqlDeleteQuery extends \pdquery\IDeleteQuery {
    public function getDeleteSql();
}

class SqlQuery extends \pdquery\Query implements ISqlQuery {

    public function __construct(ISqlDataSource $dataSource = null) {
        parent::__construct($dataSource);
    }

    public function getSelectSql() {
        return $this->dataSource->getSelectSql($this);
    }
}

class SqlCountQuery extends \pdquery\CountQuery implements ISqlCountQuery {

    public function __construct(ISqlDataSource $dataSource = null) {
        parent::__construct($dataSource);
    }

    public function getCountSql() {
        return $this->dataSource->getCountSql($this);
    }
}

class SqlUpdateQuery extends \pdquery\UpdateQuery implements ISqlUpdateQuery {

    public function __construct(ISqlDataSourceUpdater $dataSource = null) {
        parent::__construct($dataSource);
    }

    public function getUpdateSql() {
        return $this->dataSource->getUpdateSql($this);
    }
}

class SqlDeleteQuery extends \pdquery\DeleteQuery implements ISqlDeleteQuery {

    public function __construct(ISqlDataSourceUpdater $dataSource = null) {
        parent::__construct($dataSource);
    }

    public function getDeleteSql() {
        return $this->dataSource->getDeleteSql($this);
    }
}

abstract class SqlDataSource implements ISqlDataSource, ISqlDataSourceUpdater {

    /**
     * @var ISqlDialect
     */
    protected $sqlDialect;

    /**
     * @var string
     */
    protected $entityClass;

    /**
     * @var IEntityModel
     */
    protected $entityModel;

    protected $complexFields = [];

    protected $selectQueryTemplate = null;
    protected $selectQueryTemplateParams = null;

    protected $countQueryTemplate = null;
    protected $countQueryTemplateParams = null;

    protected $updateQueryTemplate = null;
    protected $updateQueryTemplateParams = null;

    protected $deleteQueryTemplate = null;
    protected $deleteQueryTemplateParams = null;

    /**
     * SqlDataSource constructor.
     * @param ISqlDialect  $sqlDialect
     * @param string       $entityClass
     * @param IEntityModel $entityModel
     */
    public function __construct($entityClass, IEntityModel $entityModel, ISqlDialect $sqlDialect) {
        $this->sqlDialect = $sqlDialect;
        $this->entityClass = $entityClass;
        $this->entityModel = $entityModel;
    }

    public function getSelectSql(\pdquery\IQueryProperties $query) {
        $queryBuilder = new SelectQueryBuilder($query, $this->entityClass, $this->entityModel, $this->complexFields, $this->selectQueryTemplate, $this->selectQueryTemplateParams);
        $sql = $queryBuilder->getSql($this->sqlDialect);
        return $sql;
    }

    public function getCountSql(\pdquery\ICountQueryProperties $query) {
        $queryBuilder = new CountQueryBuilder($query, $this->entityClass, $this->entityModel, 'count', $this->complexFields, $this->countQueryTemplate, $this->countQueryTemplateParams);
        $sql = $queryBuilder->getSql($this->sqlDialect);
        return $sql;
    }

    public function getUpdateSql(\pdquery\IUpdateQueryProperties $query) {
        $queryBuilder = new UpdateQueryBuilder($query, $this->entityClass, $this->entityModel, $this->complexFields, $this->updateQueryTemplate, $this->updateQueryTemplateParams);
        $sql = $queryBuilder->getSql($this->sqlDialect);
        return $sql;
    }

    public function getDeleteSql(\pdquery\IDeleteQueryProperties $query) {
        $queryBuilder = new DeleteQueryBuilder($query, $this->entityClass, $this->entityModel, $this->complexFields, $this->deleteQueryTemplate, $this->deleteQueryTemplateParams);
        $sql = $queryBuilder->getSql($this->sqlDialect);
        return $sql;
    }

    /**
     * @return \pdquery\IQuery
     */
    public function query() {
        return new SqlQuery($this);
    }

    /**
     * @return \pdquery\ICountQuery
     */
    public function countQuery() {
        return new SqlCountQuery($this);
    }

    /**
     * @return \pdquery\IUpdateQuery
     */
    public function updateQuery() {
        return new SqlUpdateQuery($this);
    }

    /**
     * @return \pdquery\IDeleteQuery
     */
    public function deleteQuery() {
        return new SqlDeleteQuery($this);
    }
}
