<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Functional;

use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the default WebP convert effect on new image styles.
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageStyleDefaultConvertEffectTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['image'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer image styles']));
  }

  /**
   * Tests that a WebP convert effect is added to image styles created in the UI.
   */
  public function testConvertEffectAddedInUi(): void {
    $this->drupalGet('admin/config/media/image-styles/add');
    $this->submitForm(['label' => 'WebP default', 'name' => 'webp_default'], 'Create new style');
    $this->assertSession()->statusMessageContains('Style WebP default was created.', 'status');
    $this->assertSession()->pageTextContains('Convert WEBP');

    $style = ImageStyle::load('webp_default');
    $effects = $style->getEffects();
    $this->assertCount(1, $effects);
    $effect = $effects->getIterator()->current();
    $this->assertSame('image_convert', $effect->getPluginId());
    $this->assertSame(['extension' => 'webp'], $effect->getConfiguration()['data']);
    $this->assertSame('webp', $style->getDerivativeExtension('png'));

    // The convert effect can be removed, and is not added again when the
    // image style is edited.
    $this->drupalGet('admin/config/media/image-styles/manage/webp_default/effects/' . $effect->getUuid() . '/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/config/media/image-styles/manage/webp_default');
    $this->submitForm([], 'Save');
    $style = \Drupal::entityTypeManager()->getStorage('image_style')->loadUnchanged('webp_default');
    $this->assertCount(0, $style->getEffects());
  }

}
