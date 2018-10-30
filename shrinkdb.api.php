<?php

use \Drush\ShrinkDB\EntityTypeSchemaInterface;

/**
 * Enable shrink of extra entity types.
 */
function hook_shrinkdb_entity_types() {
  return ['file', 'media'];
}

/**
 * Enable shrink of extra dependant entity types.
 */
function hook_shrinkdb_dependant_entity_types() {
  $types = [];

  drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_FULL);
  if (drush_module_exists('paragraph')) {
    $types['paragraph'] = [
      'columns' => [
        'parent_id' => 'parent_id',
        'parent_type' => 'parent_type',
      ],
    ];
  }

  return $types;
}

/**
 * Provide extra queries to shrink the database.
 */
function hook_shrinkdb_extra_queries() {
  $queries = [];

  // Remove files not referenced.
  if (drush_module_exists('file')) {
    $queries[] = "DELETE fm FROM file_managed fm LEFT JOIN file_usage fu ON fm.fid = fu.fid WHERE fu.fid IS NULL";
  }

  return $queries;
}

/**
 * Provide extra queries for the shrink of an entity type.
 */
function hook_shrinkdb_entity_type_extra_queries($tmp_table, EntityTypeSchemaInterface $entity_type, $days) {
  $queries = [];

  $id_column = $entity_type->baseTableIdColumn();

  // Clean up references in file_usage.
  if (drush_module_exists('file')) {
    $queries[] = "DELETE t FROM file_usage t INNER JOIN $tmp_table tmp ON t.id = tmp.$id_column WHERE t.type = '" . $entity_type->name() . "'";
  }

  // Clean up references in taxonomy_index.
  if ($entity_type->name() == 'node') {
    if (drush_module_exists('taxonomy')) {
      $queries[] = "DELETE t FROM taxonomy_index t INNER JOIN $tmp_table tmp ON t.nid = tmp.nid";
    }
  }

  return $queries;
}

/**
 * Provide extra queries for the shrink of a dependant entity type.
 */
function hook_shrinkdb_dependant_entity_type_extra_queries($tmp_table, EntityTypeSchemaInterface $entity_type, EntityTypeSchemaInterface $parent_entity_type, $days) {
  $queries = [];

  if ($entity_type->name() == 'foo') {
    $id_column = $entity_type->baseTableIdColumn();
    $uuid_column = $entity_type->baseTableUuidColumn();
    $queries[] = "DELETE t FROM custom_indexed_by_id t INNER JOIN $tmp_table tmp ON t.id = tmp.$id_column";
    $queries[] = "DELETE t FROM custom_indexed_by_uuid t INNER JOIN $tmp_table tmp ON t.uuid = tmp.$uuid_column";
  }

  return $queries;
}