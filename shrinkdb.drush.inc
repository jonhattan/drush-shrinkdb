<?php

function shrinkdb_drush_init() {
  drush_autoload(__FILE__);
}

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
