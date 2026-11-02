<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the 'image_url' field formatter.
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageUrlFormatterTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file', 'image', 'file_test'];

  /**
   * The entity type used in this test.
   */
  protected string $entityType;

  /**
   * The bundle used in this test.
   */
  protected string $bundle;

  /**
   * The image field name used in this test.
   */
  protected string $fieldName;

  /**
   * The entity view display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['field', 'image']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->entityType = 'entity_test';
    $this->bundle = $this->entityType;
    $this->fieldName = $this->randomMachineName();

    FieldStorageConfig::create([
      'entity_type' => $this->entityType,
      'field_name' => $this->fieldName,
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => $this->entityType,
      'field_name' => $this->fieldName,
      'bundle' => $this->bundle,
      'settings' => [
        'file_extensions' => 'png',
      ],
    ])->save();

    $this->display = \Drupal::service('entity_display.repository')
      ->getViewDisplay($this->entityType, $this->bundle)
      ->setComponent($this->fieldName, [
        'type' => 'image_url',
        'label' => 'hidden',
        'settings' => [
          'image_style' => 'medium',
        ],
      ]);
    $this->display->save();
  }

  /**
   * Renders the image field and returns the resulting markup.
   *
   * @return string
   *   The rendered URL.
   */
  protected function renderImageUrl(): string {
    $file = File::create([
      'uri' => 'public://test-image.png',
    ]);
    $file->save();
    // The formatter only reads the file URI, so the file does not need to
    // contain valid image data.
    touch($file->getFileUri());

    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      $this->fieldName => [$file],
    ]);
    $entity->save();

    $build = $this->display->build($entity);
    $this->container->get('renderer')->renderRoot($build[$this->fieldName][0]);

    return (string) $build[$this->fieldName][0]['#markup'];
  }

  /**
   * Tests that the separator between query parameters is not HTML-escaped.
   */
  public function testAmpersandBetweenQueryParametersIsNotEscaped(): void {
    // Append a second query parameter to derivative URLs. Without one the URL
    // carries only the itok parameter, so there is no ampersand to escape and
    // the bug is not observable.
    \Drupal::state()->set('file_test.hook_file_url_alter', 'query-parameter');

    $url = $this->renderImageUrl();

    $this->assertStringContainsString('h=abc12345', $url);
    $this->assertStringContainsString('itok=', $url);
    // The formatter returns the URL as a plain string in #markup, so
    // Renderer::ensureMarkupIsSafe() passes it through Xss::filter(), which
    // replaces every ampersand with an HTML entity. The resulting query
    // string contains a parameter named "amp;itok" and no "itok".
    $this->assertStringNotContainsString('&amp;', $url);
    $this->assertMatchesRegularExpression('/\?h=abc12345&itok=/', $url);
  }

  /**
   * Tests a derivative URL carrying only the itok parameter.
   */
  public function testSingleQueryParameterIsUnaffected(): void {
    \Drupal::state()->delete('file_test.hook_file_url_alter');

    $url = $this->renderImageUrl();

    $this->assertStringContainsString('itok=', $url);
    // With a single query parameter there is no separator to escape.
    $this->assertStringNotContainsString('&', $url);
  }

}
