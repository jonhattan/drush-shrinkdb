<?php

namespace Drush\ShrinkDB;

/**
 * Home-made entity type schema definition.
 */
class EntityTypeSchema {

  private $entity_type;
  private $base_table;
  private $id_key;
  private $uuid_key;
  private $fieldable;
  private $revisionable;

  public function __construct($entity_type, $base_table, $id_key, $uuid_key = 'uuid', $fieldable = TRUE, $revisionable = TRUE) {
    $this->entity_type = $entity_type;
    $this->base_table = $base_table;
    $this->id_key = $id_key;
    $this->uuid_key = $uuid_key;
    $this->fieldable = $fieldable;
    $this->revisionable = $revisionable;
  }

  public function name() {
    return $this->entity_type;
  }

  public function baseTable() {
    return $this->base_table;
  }

  public function baseTableIdColumn($prefix = '') {
    $column = $this->id_key;
    if ($prefix) {
      $column = $prefix . '.' . $column;
    }

    return $column;
  }

  public function baseTableUuidColumn($prefix = '') {
    if ($this->uuid_key) {
      $column = $this->uuid_key;
      if ($prefix) {
        $column = $prefix . '.' . $column;
      }

      return $column;
    }

    return FALSE;
  }

  public function baseTableColumns($prefix = '') {
    $columns = $this->baseTableIdColumn($prefix);

    if ($uuid_column = $this->baseTableUuidColumn($prefix)) {
      $columns = $columns . ', ' . $uuid_column;
    }

    return $columns;
  }

  public function hasUuid() {
    return (bool)$this->uuid_key;
  }

  public function fieldDataTable() {
    if ($this->fieldable) {
      return "{$this->base_table}_field_data";
    }
    return NULL;
  }

  public function revisionsTable() {
    if ($this->revisionable) {
      return "{$this->base_table}_revision";
    }
    return NULL;
  }

  public function fieldDataRevisionsTable() {
    if (($this->fieldable) && ($this->revisionable)) {
      return "{$this->base_table}_field_revision";
    }
    return NULL;
  }

}

