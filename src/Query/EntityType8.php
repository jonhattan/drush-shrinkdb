<?php

namespace Drush\ShrinkDB\Query;

use \Drush\ShrinkDB\EntityTypeSchemaInterface;

/**
 * Build queries to shrink Drupal 8 entity type tables.
 */
class EntityType8 extends EntityTypeBase {

  /**
   * {@inheritdoc}
   */
  function createTemporaryTableForEntityType($tmp_table, EntityTypeSchemaInterface $entity_type, $days) {
    $base_table = $entity_type->baseTable();
    $id_column = $entity_type->baseTableIdColumn();
    $uuid_column = $entity_type->baseTableUuidColumn();
    $table = $entity_type->dataTable();
    $columns = $entity_type->baseTableColumns('bt');

    $query = <<<QUERY
CREATE TEMPORARY TABLE $tmp_table (UNIQUE ($id_column), UNIQUE ($uuid_column))
AS (
  SELECT DISTINCT $columns
  FROM $table fdt
  INNER JOIN $base_table bt ON fdt.$id_column=bt.$id_column
  WHERE changed < UNIX_TIMESTAMP(timestampadd(day, $days, now()))
);\n
QUERY;

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  function createTemporaryTableForDependantEntityType($tmp_table, EntityTypeSchemaInterface $entity_type, EntityTypeSchemaInterface $parent_entity_type) {
    $base_table = $entity_type->baseTable();
    $id_column = $entity_type->baseTableIdColumn();
    $uuid_column = $entity_type->baseTableUuidColumn();
    $table = $entity_type->dataTable();
    $columns = $entity_type->baseTableColumns('bt');

    $parent_type_column = $entity_type->parentTypeColumn();
    $parent_id_column = $entity_type->parentIdColumn();
    $parent_base_table_id_column = $parent_entity_type->baseTableIdColumn();
    $parent_entity_type_name = $parent_entity_type->name();

    $query = <<<QUERY
CREATE TEMPORARY TABLE $tmp_table (UNIQUE ($id_column), UNIQUE ($uuid_column))
AS (
  SELECT DISTINCT $columns
  FROM $table fdt
  INNER JOIN $base_table bt ON fdt.$id_column=bt.$id_column
  INNER JOIN drush_shrinkdb_$parent_entity_type_name p ON p.$parent_base_table_id_column=fdt.$parent_id_column
  WHERE fdt.$parent_type_column='$parent_entity_type_name'
);\n
QUERY;

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  function doBuildEntityTypeQueries($tmp_table, EntityTypeSchemaInterface $entity_type) {
    $queries = '';

    $base_table = $entity_type->baseTable();
    $id_column = $entity_type->baseTableIdColumn();

    // Shrink all entity tables (base, data, revisions).
    $tables = [];
    $tables[] = $base_table;
    $tables[] = $entity_type->dataTable();
    if ($table = $entity_type->isRevisionable()) {
      $tables[] = $entity_type->revisionsTable();
    }
    if ($table = $entity_type->dataRevisionsTable()) {
      $tables[] = $table;
    }
    foreach ($tables as $table) {
      $query = "DELETE t FROM $table t INNER JOIN $tmp_table tmp ON t.$id_column=tmp.$id_column;\n";
      $queries .= $query;
    }

    // Shrink field tables.
    $tables = [];
    $table = $entity_type->name() . '\_\_';

    $field_tables = $this->database()
      ->query("show tables like '$table%'")
      ->fetchAll();
    foreach ($field_tables as $t) {
      $tables[] = current((array) $t);
    }

    $table = $entity_type->name() . '\_revision\_\_';
    $field_tables = $this->database()
      ->query("show tables like '$table%'")
      ->fetchAll();
    foreach ($field_tables as $t) {
      $tables[] = current((array) $t);
    }

    foreach ($tables as $table) {
      $queries .= "DELETE t FROM $table t INNER JOIN $tmp_table tmp ON t.entity_id=tmp.$id_column;\n";
    }

    return $queries;
  }
}
