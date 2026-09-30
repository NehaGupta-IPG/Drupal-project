<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\Development\LenientConfigSchemaChecker;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the warnings of the LenientConfigSchemaChecker.
 */
#[CoversClass(LenientConfigSchemaChecker::class)]
#[Group('config')]
#[RunTestsInSeparateProcesses]
class LenientConfigSchemaCheckerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test'];

  /**
   * {@inheritdoc}
   */
  protected bool $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->register('test.logger', TestLogger::class);
    // Mirrors the service definition in development.services.yml.
    $container->register('config.schema_checker', LenientConfigSchemaChecker::class)
      ->addArgument(new Reference('config.typed'))
      ->addArgument(new Reference('messenger'))
      ->addArgument(new Reference('test.logger'))
      ->addTag('event_subscriber');
  }

  /**
   * Tests that the warning is markup with a working link to the documentation.
   */
  public function testWarningMarkup(): void {
    $suffix = '. These errors mean there is configuration that does not comply with its schema. This is not a fatal error, but it is recommended to fix these issues. For more information on configuration schemas, check out <a href="https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata">the documentation</a>.';

    $this->config('config_schema_test.schemaless')->set('foo', 'bar')->save();
    // The apostrophe in the key ensures the schema error is escaped only once.
    $this->config('config_test.types')->set("it's", 'bar')->save();

    $warnings = $this->container->get('messenger')->messagesByType('warning');
    $this->assertCount(2, $warnings);
    $this->assertInstanceOf(MarkupInterface::class, $warnings[0]);
    $this->assertSame('No schema for config_schema_test.schemaless' . $suffix, (string) $warnings[0]);
    $this->assertInstanceOf(MarkupInterface::class, $warnings[1]);
    $this->assertSame('Schema errors for config_test.types with the following errors: config_test.types:it&#039;s missing schema' . $suffix, (string) $warnings[1]);

    // The log entries use placeholders and render to the same markup.
    $records = $this->container->get('test.logger')->recordsByLevel['warning'];
    $this->assertCount(2, $records);
    foreach ($records as $delta => $record) {
      $this->assertSame((string) $warnings[$delta], (string) new FormattableMarkup($record['message'], $record['context']));
    }
  }

}
