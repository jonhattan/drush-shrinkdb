<?php

namespace Drush\ShrinkDB\Query;

use \Drush\ShrinkDB\EntityTypeSchema;

/**
 * Build queries to shrink entity type tables.
 */
class EntityType {

  private $db_key;
  private $days;
  private $entity_types = [];

  public function __construct($db_key, $days) {
    $this->db_key = $db_key;
    $this->days = $days;

    $this->initializeEntityTypes();
  }

  /**
   * Returns a database connection object.
   */
  private function database() {
    return \Drupal\Core\Database\Database::getConnection('default', $this->db_key);
  }

  /**
   * Initializes the list of entity types with core ones.
   *
   * @TODO@ Leverage drush_entity to obtain entity type definitions, or a custom command.
   */
  private function initializeEntityTypes() {
    $entity_types = drush_command_invoke_all('shrinkdb_entity_types');

    // Note: if there's a copy of drush_entity in any of drush commands folder,
    // it has precedence over this.
    $include_dir = dirname(dirname(__DIR__)) . '/vendor/drush_entity';
    $result = drush_invoke_process('@self', 'entity-type-read', $entity_types, ['include' => $include_dir, 'format' => 'json'], FALSE);
    if ($result['error_status'] > 0) {
      throw new \Exception('Failed to invoke «drush @self entity-type-read».');
    }
    $entity_types_info = json_decode($result['output']);

    foreach ($entity_types_info as $name => $info) {
      $this->entity_types[$name] = new EntityTypeSchema($name, $info);
    }
  }

  /**
   * Returns a string with all queries to perform.
   */
  public function queries() {
    $queries = '';

    foreach ($this->entity_types as $entity_type) {
      $queries .= $this->buildEntityTypeQueries($entity_type);
    }

    return $queries;
  }

  /**
   * Returns a string with all queries to perform for an entity type.
   */
  private function buildEntityTypeQueries(EntityTypeSchema $entity_type) {
    $queries = '';

    $base_table = $entity_type->baseTable();
    $id_column = $entity_type->baseTableIdColumn();
    $columns = $entity_type->baseTableColumns('bt');

    // Create a temporary table with the ids to delete.
    if ($fd_table = $entity_type->dataTable()) {
      $tmp_table = 'drush_shrinkdb_' . $entity_type->name();
      $days = -1 * $this->days;
      $queries .= "CREATE TEMPORARY TABLE $tmp_table AS (
        SELECT $columns
        FROM $fd_table fdt INNER JOIN $base_table bt ON fdt.$id_column=bt.$id_column
        WHERE changed < UNIX_TIMESTAMP(timestampadd(day, $days, now()))
      );\n";
    }

    // Shrink all entity tables (base, data, revisions).
    $tables = [];
    $tables[] = $base_table;
    if ($table = $entity_type->dataTable()) {
      $tables[] = $table;
    }
    if ($table = $entity_type->revisionsTable()) {
      $tables[] = $table;
    }
    if ($table = $entity_type->dataRevisionsTable()) {
      $tables[] = $table;
    }
    foreach ($tables as $table) {
      $queries .= "DELETE t FROM $table t INNER JOIN $tmp_table ON t.$id_column=$tmp_table.$id_column;\n";
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

