<?php

namespace pdquery\EntityModel;

interface IEntityFieldType {
    const IMPLICIT = 'implicit';
    const INT = 'int';
    const INTEGER = 'int';
    const FLOAT = 'float';
    const STRING = 'string';
    const BOOL = 'bool';
}

interface IEntityRelation {
    const ONE_TO_ONE = 11;
    const ONE_TO_MANY = 18;
    const MANY_TO_ONE = 81;

    public function getTargetEntity();
    public function getSourceFieldName();
    public function getTargetFieldName();
    public function getType();
}

interface IEntityModel {
    public function getDbTableName($entityName);
    public function getDbColumnName($entityName, $fieldName);

    public function getFieldType($entityName, $fieldName);

    /**
     * @return IEntityRelation
     */
    public function getRelation($entityName, $relationName);

    /**
     *
     * @return string[] lub null gdy lista pól nie jest określona
     */
    public function getEntityFieldNames($entityName);

    public function getKeyFieldName($entityName);
}

class StdEntityRelation implements IEntityRelation {
    protected $type;
    protected $targetEntity;
    protected $sourceFieldName;
    protected $targetFieldName;

    public function __construct($type, $targetEntity, $sourceFieldName, $targetFieldName) {
        $this->type = $type;
        $this->targetEntity = $targetEntity;
        $this->sourceFieldName = $sourceFieldName;
        $this->targetFieldName = $targetFieldName;
    }

    public function getType() {
        return $this->type;
    }

    public function getTargetEntity() {
        return $this->targetEntity;
    }

    public function getSourceFieldName() {
        return $this->sourceFieldName;
    }

    public function getTargetFieldName() {
        return $this->targetFieldName;
    }
}

class SimpleEntityModel implements IEntityModel {
    public function getDbColumnName($entityName, $fieldName) {
        return $fieldName;
    }

    public function getDbTableName($entityName) {
        return $entityName;
    }

    public function getEntityFieldNames($entityName) {
        return null;
    }

    public function getFieldType($entityName, $fieldName) {
        return IEntityFieldType::IMPLICIT;
    }

    public function getRelation($entityName, $relationName) {
        // TODO: throw?
        return null;
    }

    public function getKeyFieldName($entityName) {
        // TODO: w zależności od opcji $entityName_id ?
        return 'id';
    }
}
