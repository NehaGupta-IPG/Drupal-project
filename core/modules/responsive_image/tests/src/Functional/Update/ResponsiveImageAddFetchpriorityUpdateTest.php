<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Functional\Update;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests responsive_image_post_update_add_fetchpriority_setting().
 */
#[Group('responsive_image')]
#[RunTestsInSeparateProcesses]
class ResponsiveImageAddFetchpriorityUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/responsive-image-post-update-fetchpriority.php',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The database dump only enables the responsive_image module. Install
    // its entity type, and switch the 'field_image' component of
    // 'node.article.default' to the 'responsive_image' formatter, without a
    // 'fetchpriority' key in its 'image_loading' settings, simulating a site
    // that configured this formatter before that setting was introduced.
    \Drupal::entityDefinitionUpdateManager()->installEntityType(
      \Drupal::entityTypeManager()->getDefinition('responsive_image_style')
    );

    $display = \Drupal::configFactory()->getEditable('core.entity_view_display.node.article.default');
    $display->set('dependencies.module', array_unique(array_merge($display->get('dependencies.module') ?? [], ['responsive_image'])));
    $display->set('content.field_image.type', 'responsive_image');
    $display->set('content.field_image.settings', [
      'responsive_image_style' => 'test_style',
      'image_link' => '',
      'image_loading' => [
        'attribute' => 'lazy',
      ],
    ]);
    $display->save();
  }

  /**
   * Tests that the 'fetchpriority' setting is added.
   *
   * @see responsive_image_post_update_add_fetchpriority_setting()
   */
  public function testAddFetchpriorityDefaultSetting(): void {
    $component = EntityViewDisplay::load('node.article.default')->getComponent('field_image');
    $this->assertSame('responsive_image', $component['type']);
    $this->assertArrayNotHasKey('fetchpriority', $component['settings']['image_loading']);

    $this->runUpdates();

    $component = EntityViewDisplay::load('node.article.default')->getComponent('field_image');
    $this->assertArrayHasKey('fetchpriority', $component['settings']['image_loading']);
    $this->assertNull($component['settings']['image_loading']['fetchpriority']);
  }

}
