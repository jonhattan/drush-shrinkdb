<?php

require __DIR__ . '/vendor/autoload.php';

/**
 * Implements hook_drush_help_alter().
 */
function shrinkdb_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sanitize') {
    $command['options']['shrink-db'] = array(
      'description' => 'Shrink the database size by wiping content older than given days.',
    );
    $command['options']['shrink-db-days'] = array(
      'description' => 'Age (in days) of the contents to preserve. 15 by default.',
      'example-value' => '15',
    );
  }
}

/**
 * Implements hook_drush_sql_sync_sanitize().
 */
function shrinkdb_drush_sql_sync_sanitize($source) {
  if (!drush_get_option('shrink-db', FALSE)) {
    return;
  }

  $days = drush_get_option('shrink-db-days', 15);

  // We want to check if modules are enabled. This requires a full bootstrap.
  drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_FULL);

  try {
    $class = '\Drush\ShrinkDB\Query\EntityType';
    $args = [$source, $days];
    $shrinkdb = drush_get_class($class, $args);
  }
  catch (\Exception $e) {
    // @todo@ For some reason drush_set_error() triggers a fatal error:
    // Drupal\Core\DependencyInjection\ContainerNotInitializedException: \Drupal::$container is not initialized yet.
    return drush_set_error('SHRINKDB_ERROR', 'shrinkdb: ' . $e->getMessage());
  }

  drush_sql_register_post_sync_op('shrinkdb', dt('Wipe content older than !num days', array('!num' => $days)), $shrinkdb->queries());
}

/**
 * Implements hook_shrinkdb_entity_types().
 */
function shrinkdb_shrinkdb_entity_types() {
  return ['node', 'media'];
}

/**
 * Implements hook_shrinkdb_dependant_entity_types().
 */
function shrinkdb_shrinkdb_dependant_entity_types() {
  $types = [];

  if (drush_module_exists('comment')) {
    $types['comment'] = [
      'columns' => [
        'parent_id' => 'entity_id',
        'parent_type' => 'entity_type',
      ],
    ];
  }

  return $types;
}