<?php

namespace Drupal\Core\Extension\Install;

use Drupal\Core\Database\Schema;

/**
 * Method for identifying the data.base schema for a module or profile.
 *
 * Classes implementing this must be in the Install\Schema namespace.
 * For example src/Install/Schema/ModuleNameSchema.php.
 */
interface SchemaInterface {

  /**
   * Define the current version of the database schema.
   *
   * Only procedural implementations are supported for this hook.
   *
   * A Drupal schema definition is a structure of value objects representing one
   * or more tables and their related keys and indexes. A schema is defined by
   * hook_schema() which must live in your module's .install file.
   *
   * The tables declared by this hook will be automatically created when the
   * module is installed, and removed when the module is uninstalled. This
   * happens before hook_install() is invoked, and after hook_uninstall() is
   * invoked, respectively.
   *
   * By declaring the tables used by your module via an implementation of
   * hook_schema(), these tables will be available on all supported database
   * engines. You don't have to deal with the different SQL dialects for table
   * creation and alteration of the supported database engines.
   *
   * See the Schema API documentation for details on schema definition
   * structures. Note that foreign key definitions are for documentation
   * purposes only; foreign keys are not created in the database, nor are they
   * enforced by Drupal.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema
   *   A schema definition.
   *
   * @ingroup schemaapi
   */
  public function getSchema(): Schema;

}
