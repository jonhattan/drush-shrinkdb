<?php

require __DIR__ . '/vendor/autoload.php';

/**
 * Implements hook_drush_command().
 */
function shrinkdb_drush_command() {
  $items = [];
  $items['shrinkdb'] = [
    'description' => 'Shrink the database size by wiping content older than given days.',
    'options' => [
      'days' => [
        'description' => 'Age (in days) of the contents to preserve. 15 by default.',
        'example-value' => '15',
      ],
    ],
  ];

  return $items;
}

/**
 * Implements hook_drush_help_alter().
 *
 * Adds shrinkdb option to sql-sanitize.
 */
function shrinkdb_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sanitize') {
    $shrinkdb_command = shrinkdb_drush_command()['shrinkdb'];
    $command['options']['shrink-db'] = $shrinkdb_command['description'];
    foreach ($shrinkdb_command['options'] as $key => $value) {
      $command['options']['shrink-db-' . $key] = $value;
    }
  }
}

/**
 * Implements command callback for 'shrinkdb'.
 */
function drush_shrinkdb() {
  $db_key = 'default';
  $days = drush_get_option('days', 15);

  $sql = drush_sql_get_class();

  if (!drush_get_context('DRUSH_AFFIRMATIVE')) {
    $db_spec = $sql->db_spec();
    if (!drush_confirm(dt('Do you really want to wipe content older than !num days in the database !db?', array('!num' => $days, '!db' => $db_spec['database'])))) {
      return drush_user_abort();
    }
  }

  $queries = drush_shrinkdb_get_queries($db_key, $days);
  if (drush_get_context('DRUSH_SIMULATE')) {
    print $queries;
  }
  else {
    $result = $sql->query($queries);
    if (!$result) {
      throw new \Exception(dt('Wipe content query failed.'));
    }
  }
}

/**
 * Implements hook_drush_sql_sync_sanitize().
 */
function shrinkdb_drush_sql_sync_sanitize($db_key) {
  if (!drush_get_option('shrink-db', FALSE)) {
    return;
  }

  $days = drush_get_option('shrink-db-days', 15);

  $queries = drush_shrinkdb_get_queries($db_key, $days);
  drush_sql_register_post_sync_op('shrinkdb', dt('Wipe content older than !num days', array('!num' => $days)), $queries);
}

/**
 * Generate the queries.
 */
function drush_shrinkdb_get_queries($db_key, $days) {
  // We want to check if modules are enabled. This requires a full bootstrap.
  drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_FULL);

  try {
    $class = '\Drush\ShrinkDB\Query\EntityType';
    $args = [$db_key, $days];
    $shrinkdb = drush_get_class($class, $args);
  }
  catch (\Exception $e) {
    // @todo@ For some reason drush_set_error() triggers a fatal error:
    // Drupal\Core\DependencyInjection\ContainerNotInitializedException: \Drupal::$container is not initialized yet.
    return drush_set_error('SHRINKDB_ERROR', 'shrinkdb: ' . $e->getMessage());
  }

  return $shrinkdb->queries();
}

/**
 * Implements hook_shrinkdb_entity_types().
 */
function shrinkdb_shrinkdb_entity_types() {
  return ['node'];
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
