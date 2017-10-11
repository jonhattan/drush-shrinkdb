<?php

namespace Drush\ShrinkDB;

/**
 * Interface for EntityTypeSchema classes.
 */
interface EntityTypeSchemaInterface {

  /**
   * Returns the entity type name.
   */
  public function name();

  /**
   * Returns the entity type base table.
   */
  public function baseTable();

  /**
   * Returns the column name of the entity type id.
   */
  public function baseTableIdColumn($prefix = '');

  /**
   * Returns the column name of the entity type uuid.
   */
  public function baseTableUuidColumn($prefix = '');

  /**
   * Returns the entity type id and uuid columns separated by a comma.
   */
  public function baseTableColumns($prefix = '');

    /**
   * Returns the entity type data table.
   */
  public function dataTable();

  /**
   * Returns whether the entity type supports revisions.
   */
  public function isRevisionable();

  /**
   * Returns the entity type revisions table.
   */
  public function revisionsTable();

  /**
   * Returns the entity type data revisions table.
   */
  public function dataRevisionsTable();

  /**
   * Returns whether this entity type is dependant of others.
   */
  public function isDependant();

  /**
   * Returns the column name of the parent entity id.
   */
  public function parentIdColumn($prefix = '');

  /**
   * Returns the column name of the parent entity type.
   */
  public function parentTypeColumn($prefix = '');
}
