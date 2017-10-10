<?php

use \Drush\ShrinkDB\EntityTypeSchemaInterface;

/**
 * Enable supported entity types.
 */
function hook_shrinkdb_entity_types() {
  return ['myentitytype'];
}

/**
 * Provide extra queries for the shrink of an entity type.
 */
function hook_shrinkdb_extra_queries(EntityTypeSchemaInterface $entity_type, $tmp_table, $days) {
  if ($entity_type->name() == 'foo') {
    $queries = [];
    $id_column = $entity_type->baseTableIdColumn();
    $uuid_column = $entity_type->baseTableUuidColumn();
    $queries[] = "DELETE t FROM custom_indexed_by_id t INNER JOIN $tmp_table tmp ON t.id = tmp.$id_column";
    $queries[] = "DELETE t FROM custom_indexed_by_uuid t INNER JOIN $tmp_table tmp ON t.uuid = tmp.$uuid_column";
    return $queries;
  }
}