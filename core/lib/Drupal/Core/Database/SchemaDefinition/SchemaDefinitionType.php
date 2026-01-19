<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Enumeration of cases for schema source.
 *
 * It represents the Drupal feature that is providing a collection of schema
 * elements (only tables at the moment) that need to be available on the
 * database.
 */
enum SchemaDefinitionType: string {

  case Module = 'module';
  case Storage = 'storage';
  case Entity = 'entity';
  case Migrate = 'migrate';
  case Test = 'test';
  case Dump = 'dump';

}
