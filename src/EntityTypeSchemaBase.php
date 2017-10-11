<?php

namespace Drush\ShrinkDB;

/**
 * Home-made entity type schema definition.
 */
abstract class EntityTypeSchemaBase implements EntityTypeSchemaInterface {

  protected $name;
  protected $info;
  protected $dependant;
  protected $parent_type_column;
  protected $parent_id_column;

  public function __construct($entity_type_name, $entity_type_info, $dependant = FALSE, $parent_type_column = NULL, $parent_id_column = NULL) {
    $this->name = $entity_type_name;
    $this->info = $entity_type_info;
    $this->dependant = $dependant;
    $this->parent_type_column = $parent_type_column;
    $this->parent_id_column = $parent_id_column;
  }

  /**
   * Returns an object with entity type keys.
   */
  protected abstract function keys();

  /**
   * {@inheritdoc}
   */
  public function name() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function baseTableIdColumn($prefix = '') {
    $column = $this->keys()->id;
    if ($prefix) {
      $column = $prefix . '.' . $column;
    }

    return $column;
  }

  /**
   * {@inheritdoc}
   */
  public function baseTableUuidColumn($prefix = '') {
    if (!empty($this->keys()->uuid)) {
      $column = $this->keys()->uuid;
      if ($prefix) {
        $column = $prefix . '.' . $column;
      }

      return $column;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function baseTableColumns($prefix = '') {
    $columns = $this->baseTableIdColumn($prefix);

    if ($uuid_column = $this->baseTableUuidColumn($prefix)) {
      $columns = $columns . ', ' . $uuid_column;
    }

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function isRevisionable() {
    return !empty($this->keys()->revision);
  }

  /**
   * {@inheritdoc}
   */
  public function isDependant() {
    return $this->dependant;
  }

  /**
   * {@inheritdoc}
   */
  public function parentIdColumn($prefix = '') {
    if ($this->isDependant()) {
      $column = $this->parent_id_column;
      if ($prefix) {
        $column = $prefix . '.' . $column;
      }

      return $column;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function parentTypeColumn($prefix = '') {
    if ($this->isDependant()) {
      $column = $this->parent_type_column;
      if ($prefix) {
        $column = $prefix . '.' . $column;
      }

      return $column;
    }

    return NULL;
  }
}
