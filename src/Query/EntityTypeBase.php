<?php

namespace Drush\ShrinkDB\Query;

use \Drush\ShrinkDB\EntityTypeSchemaInterface;

/**
 * Build queries to shrink entity type tables.
 */
abstract class EntityTypeBase {

  private $db_key;
  protected $days;
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

    // Note: if there's a copy of drush_entity in any of drush commands folders,
    // it has precedence over this.
    $include_dir = dirname(dirname(__DIR__)) . '/vendor/drush_entity';
    $result = drush_invoke_process('@self', 'entity-type-read', $entity_types, ['include' => $include_dir, 'format' => 'json'], FALSE);

    if ($result['error_status'] > 0) {
      throw new \Exception('Failed to invoke «drush @self entity-type-read».');
    }
    $entity_types_info = json_decode($result['output']);

    $class = '\Drush\ShrinkDB\EntityTypeSchema';
    foreach ($entity_types_info as $name => $info) {
      $args = [$name, $info];
      $this->entity_types[$name] = drush_get_class($class, $args);
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
  protected abstract function buildEntityTypeQueries(EntityTypeSchemaInterface $entity_type);
}
