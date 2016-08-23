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
    // Check which core entity type modules are enabled.
    // Use ReflectionClass to avoid WET.
    $core_entity_types = [
//      ['comment', 'comment', 'cid', 'uuid', TRUE, FALSE],
      ['node', 'node', 'nid'],
//      ['taxonomy_term', 'taxonomy_term', 'tid', 'uuid', TRUE, FALSE], // <- the annotation overrides the base table name!!
//      ['user', 'users', 'uid', 'uuid', TRUE, FALSE],
    ];

    // @todo@ add a hook for contrib to add its own entity types..
    $core_entity_types[] = ['media', 'media', 'mid'];
    //$core_entity_types[] = ['paragraph', 'paragraphs_item', 'id'], // <- the annotation provides revision_data_table and others !!!

    $class = new \ReflectionClass('\Drush\ShrinkDB\EntityTypeSchema');
    foreach ($core_entity_types as $args) {
      $instance = $class->newInstanceArgs($args);
      if ($this->validateEntityType($instance)) {
        $this->entity_types[$instance->name()] = $instance;
      }
    }
  }

  /**
   * Returns whether an entity type exists in the database.
   *
   * It simply tests the existence of some entity type tables.
   */
  private function validateEntityType(EntityTypeSchema $entity_type) {
    $schema = $this->database()->schema();

    if (!$schema->tableExists($entity_type->baseTable())) {
      return FALSE;
    }
    if (($table = $entity_type->fieldDataTable()) && (!$schema->tableExists($table))) {
      return FALSE;
    }
    if (($table = $entity_type->revisionsTable()) && (!$schema->tableExists($table))) {
      return FALSE;
    }
    if (($table = $entity_type->fieldDataRevisionsTable()) && (!$schema->tableExists($table))) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks a given entity type is valid and stores it.
   */
  public function addEntityType(EntityTypeSchema $entity_type) {
    if ($this->validateEntityType($entity_type)) {
      $this->entity_types[] = $entity_type;
    }
    else {
      return drush_set_error('SHRINKDB_INVALID_ENTITY_TYPE', dt('«!this» doesn\'t seem a valid entity type.', ['!this' => $entity_type]));
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
    if ($fd_table = $entity_type->fieldDataTable()) {
      $tmp_table = 'drush_shrinkdb_' . $entity_type->name();
      $days = -1 * $this->days;
      $queries .= "CREATE TEMPORARY TABLE $tmp_table AS (
        SELECT $columns
        FROM $fd_table fdt INNER JOIN $base_table bt ON fdt.$id_column=bt.$id_column
        WHERE changed < UNIX_TIMESTAMP(timestampadd(day, $days, now()))
      );\n";
    }

    // Shrink all entity tables (base, field data, revisions data).
    $tables = [];
    $tables[] = $base_table;
    if ($table = $entity_type->fieldDataTable()) {
      $tables[] = $table;
    }
    if ($table = $entity_type->revisionsTable()) {
      $tables[] = $table;
    }
    if ($table = $entity_type->fieldDataRevisionsTable()) {
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
    $table = $table . '\_\_';
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

