<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension;

use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides methods to access modules' schema.
 */
class SchemaInspector {

  /**
   * Returns the module's schema specification.
   *
   * This function can be used to retrieve a schema specification provided by
   * hook_schema(), so it allows you to derive your tables from existing
   * specifications.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $handler
   *   The module handler to use for calling schema hook.
   * @param string $module
   *   The module to which the table belongs.
   *
   * @return \Drupal\Core\Database\SchemaDefinition\Schema|array
   *   An array of schema definition provided by hook_schema().
   *
   * @see \hook_schema()
   */
  public static function getTablesSpecification(ModuleHandlerInterface $handler, string $module): Schema|array {
    if ($handler->loadInclude($module, 'install')) {
      return $handler->invoke($module, 'schema') ?? [];
    }
    return [];
  }

  /**
   * Returns the module's schema table names.
   *
   * This function can be used to retrieve a schema specification provided by
   * hook_schema(), so it allows you to derive your tables from existing
   * specifications.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $handler
   *   The module handler to use for calling schema hook.
   * @param string $module
   *   The module to which the tables belong.
   *
   * @return list<string>
   *   A list of table names.
   *
   * @see \hook_schema()
   */
  public static function getTableNames(ModuleHandlerInterface $handler, string $module): array {
    $specification = self::getTablesSpecification($handler, $module);
    return $specification instanceof Schema ? $specification->tableNames() : array_keys($specification);
  }

}
