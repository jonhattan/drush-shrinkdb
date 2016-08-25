 * Make it hookeable so others can add custom queries based on the entity type
 being processed.
 * Manage interdependencies: f.e. restrict deletion of users referenced in
 the nodes that remain. Make it also hookeable.
 * Add an option to select which entity types to operate on.
 * Add an option to wipe tables.
 * Verify all entity types have a 'changed' column. See where it is defined.
 * Try and make it work for drupal 7.

