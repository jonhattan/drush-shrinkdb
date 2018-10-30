<?php

namespace Drush\ShrinkDB\Query;

use \Drush\ShrinkDB\EntityTypeSchemaInterface;

/**
 * Build queries to shrink entity type tables.
 */
abstract class EntityTypeBase {

  private $db_key;
  protected $days;
  protected $dependant_entity_types = [];
  protected $entity_types = [];

  public function __construct($db_key, $days) {
    $this->db_key = $db_key;
    $this->days = $days;

    $this->initializeEntityTypes();
  }

  /**
   * Returns a database connection object.
   */
  protected function database() {
    return \Drupal\Core\Database\Database::getConnection('default', $this->db_key);
  }

  /**
   * Initializes the list of entity types with core ones.
   */
  protected function initializeEntityTypes() {
    $entity_types = drush_command_invoke_all('shrinkdb_entity_types');
    $dependant_entity_types = drush_command_invoke_all('shrinkdb_dependant_entity_types');

    // Note: if there's a copy of drush_entity in any of drush commands folders,
    // it has precedence over this.
    $include_dir = dirname(dirname(__DIR__)) . '/vendor/drush_entity';
    $all_entity_types = array_merge($entity_types, array_keys($dependant_entity_types));
    $debug = drush_get_context('DRUSH_DEBUG');
    drush_set_context('DRUSH_DEBUG', FALSE);
    $result = drush_invoke_process('@self', 'entity-type-read', $all_entity_types, ['include' => $include_dir, 'format' => 'json'], ['override-simulated' => TRUE, 'integrate' => FALSE]);
    drush_set_context('DRUSH_DEBUG', $debug);

    if ($result['error_status'] > 0) {
      throw new \Exception('Failed to invoke «drush @self entity-type-read».');
    }
    $entity_types_info = json_decode($result['output']);

    $class = '\Drush\ShrinkDB\EntityTypeSchema';
    foreach ($entity_types_info as $name => $info) {
      $dependant = array_key_exists($name, $dependant_entity_types);
      $parent_type = $dependant ? $dependant_entity_types[$name]['columns']['parent_type'] : NULL;
      $parent_id = $dependant ? $dependant_entity_types[$name]['columns']['parent_id'] : NULL;
      $args = [$name, $info, $dependant, $parent_type, $parent_id];
      /* @var \Drush\ShrinkDB\EntityTypeSchemaInterface $entity_type */
      $entity_type = drush_get_class($class, $args);
      if ($entity_type->isDependant()) {
        $this->dependant_entity_types[$name] = $entity_type;
      }
      else {
        $this->entity_types[$name] = $entity_type;
      }
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
    foreach ($this->dependant_entity_types as $entity_type) {
      $queries .= $this->buildEntityTypeQueries($entity_type);
    }

    $extra_queries = drush_command_invoke_all('shrinkdb_extra_queries');
    foreach ($extra_queries as $query) {
      $queries .= $query . ";\n";
    }

    return $queries;
  }

  /**
   * Generates the query to create a temporary table of contents to delete.
   */
  abstract protected function createTemporaryTableForEntityType($tmp_table, EntityTypeSchemaInterface $entity_type, $days);

  /**
   * Generates the query to create a temporary table of contents to delete.
   */
  abstract protected function createTemporaryTableForDependantEntityType($tmp_table, EntityTypeSchemaInterface $entity_type, EntityTypeSchemaInterface $parent_entity_type);

  /**
   * Returns a string with all queries to perform for an entity type.
   */
  protected function buildEntityTypeQueries(EntityTypeSchemaInterface $entity_type) {
    $queries = '';

    $days = -1 * $this->days;

    if (!$entity_type->isDependant()) {
      $tmp_table = 'drush_shrinkdb_' . $entity_type->name();
      $queries .= $this->createTemporaryTableForEntityType($tmp_table, $entity_type, $days);
      $queries .= $this->doBuildEntityTypeQueries($tmp_table, $entity_type);
      $extra_queries = drush_command_invoke_all('shrinkdb_entity_type_extra_queries', $tmp_table, $entity_type, $days);
      foreach ($extra_queries as $query) {
        $queries .= $query . ";\n";
      }
    }
    else {
      foreach ($this->entity_types as $parent_entity_type) {
        /* @var \Drush\ShrinkDB\EntityTypeSchemaInterface $parent_entity_type */
        $tmp_table = 'drush_shrinkdb_' . $entity_type->name() . '_' . $parent_entity_type->name();
        $queries .= $this->createTemporaryTableForDependantEntityType($tmp_table, $entity_type, $parent_entity_type);
        $queries .= $this->doBuildEntityTypeQueries($tmp_table, $entity_type);
        $extra_queries = drush_command_invoke_all('shrinkdb_dependant_entity_type_extra_queries', $tmp_table, $entity_type, $parent_entity_type, $days);
        foreach ($extra_queries as $query) {
          $queries .= $query . ";\n";
        }
      }
    }

    return $queries;
  }

  /**
   * Generates queries to delete entity fields.
   */
  abstract protected function doBuildEntityTypeQueries($tmp_table, EntityTypeSchemaInterface $entity_type);
}
