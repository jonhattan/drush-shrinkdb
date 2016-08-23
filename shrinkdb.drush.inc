<?php

require_once "vendor/autoload.php";

/**
 * Implements hook_drush_help_alter().
 */
function shrinkdb_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sanitize') {
    $command['options']['shrink-db'] = array(
      'description' => 'Shrink the database size by wiping content older than given days.',
    );
    $command['options']['shrink-db-days'] = array(
      'description' => 'Age (in days) of the contents to preserve. 30 by default.',
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

  $shrinkdb = new \Drush\ShrinkDB\Query\EntityType($source, $days);
  drush_sql_register_post_sync_op('shrinkdb', dt('Wipe content older than !num days', array('!num' => $days)), $shrinkdb->queries());
}
