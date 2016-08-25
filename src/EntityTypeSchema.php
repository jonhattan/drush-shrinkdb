<?php

namespace Drush\ShrinkDB;

/**
 * Home-made entity type schema definition.
 */
class EntityTypeSchema {

  private $name;
  private $info;

  public function __construct($entity_type_name, $entity_type_info) {
    $this->name = $entity_type_name;
    $this->info = $entity_type_info;
  }

  public function name() {
    return $this->name;
  }

  public function baseTable() {
    return $this->info->base_table;
  }

  public function baseTableIdColumn($prefix = '') {
    $column = $this->info->entity_keys->id;
    if ($prefix) {
      $column = $prefix . '.' . $column;
    }

    return $column;
  }

  public function baseTableUuidColumn($prefix = '') {
    if (!empty($this->info->entity_keys->uuid)) {
      $column = $this->info->entity_keys->uuid;
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

  public function dataTable() {
    return $this->info->data_table;
  }

  public function isRevisionable() {
    return !empty($this->info->entity_keys->revision);
  }

  public function revisionsTable() {
    if ($this->isRevisionable()) {
      return $this->info->revision_table;
    }
    return NULL;
  }

  public function dataRevisionsTable() {
    if ($this->isRevisionable()) {
      return $this->info->revision_data_table;
    }

    return NULL;
  }

}
