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
  function buildEntityTypeQueries(EntityTypeSchemaInterface $entity_type) {
    $queries = '';

    $base_table = $entity_type->baseTable();
    $id_column = $entity_type->baseTableIdColumn();
    $columns = $entity_type->baseTableColumns('bt');

    // Create a temporary table with the ids to delete.
    $tmp_table = 'drush_shrinkdb_' . $entity_type->name();
    $days = -1 * $this->days;

    $table = $entity_type->dataTable();
    $queries .= "CREATE TEMPORARY TABLE $tmp_table AS (
SELECT $columns
FROM $table fdt INNER JOIN $base_table bt ON fdt.$id_column=bt.$id_column
WHERE changed < UNIX_TIMESTAMP(timestampadd(day, $days, now()))
);\n";

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
      $query = "DELETE t FROM $table t INNER JOIN $tmp_table ON t.$id_column=$tmp_table.$id_column;\n";
      $cosa = drush_command_invoke_all_ref('shrinkdb_entity_types_query_alter', $query, $table);
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
      $queries .= "DELETE t FROM $table t INNER JOIN $tmp_table ON t.entity_id=$tmp_table.$id_column;\n";
    }

    return $queries;
  }
}
