<?php

namespace Drush\ShrinkDB;

/**
 * Home-made entity type schema definition.
 */
class EntityTypeSchema8 extends EntityTypeSchemaBase {

  /**
   * {@inheritdoc}
   */
  protected function keys() {
    return $this->info->entity_keys;
  }

  /**
   * {@inheritdoc}
   */
  public function baseTable() {
    return $this->info->base_table;
  }

  /**
   * {@inheritdoc}
   */
  public function dataTable() {
    return $this->info->data_table;
  }

  /**
   * {@inheritdoc}
   */
  public function revisionsTable() {
    if ($this->isRevisionable()) {
      return $this->info->revision_table;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dataRevisionsTable() {
    if ($this->isRevisionable()) {
      return $this->info->revision_data_table;
    }

    return NULL;
  }

}
