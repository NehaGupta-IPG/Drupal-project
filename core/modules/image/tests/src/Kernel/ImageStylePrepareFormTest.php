<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Hook\ImageHooks;
use Drupal\image\ImageStyleInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the default WebP convert effect added when preparing image style forms.
 */
#[CoversMethod(ImageHooks::class, 'imageStylePrepareForm')]
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageStylePrepareFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'image', 'image_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
  }

  /**
   * Tests that the add form adds a WebP convert effect to new image styles.
   */
  public function testAddForm(): void {
    $style = $this->prepareForm(ImageStyle::create(['name' => 'test', 'label' => 'Test']), 'add');
    $effects = $style->getEffects();
    $this->assertCount(1, $effects);
    $effect = $effects->getIterator()->current();
    $this->assertSame('image_convert', $effect->getPluginId());
    $this->assertSame(['extension' => 'webp'], $effect->getConfiguration()['data']);
  }

  /**
   * Tests that image styles created programmatically have no default effects.
   */
  public function testProgrammaticCreation(): void {
    ImageStyle::create(['name' => 'test', 'label' => 'Test'])->save();
    $this->assertCount(0, ImageStyle::load('test')->getEffects());
  }

  /**
   * Tests that the edit form does not add a convert effect.
   */
  public function testEditForm(): void {
    $style = ImageStyle::create(['name' => 'test', 'label' => 'Test']);
    $style->save();
    $style = $this->prepareForm($style, 'edit');
    $this->assertCount(0, $style->getEffects());
  }

  /**
   * Tests that existing effects on a new image style are left untouched.
   */
  public function testAddFormWithExistingEffects(): void {
    $style = ImageStyle::create(['name' => 'test', 'label' => 'Test']);
    $style->addImageEffect([
      'id' => 'image_desaturate',
      'weight' => 0,
      'data' => [],
    ]);
    $style = $this->prepareForm($style, 'add');
    $effects = $style->getEffects();
    $this->assertCount(1, $effects);
    $this->assertSame('image_desaturate', $effects->getIterator()->current()->getPluginId());
  }

  /**
   * Tests that no convert effect is added if the toolkit does not support WebP.
   */
  public function testAddFormWithoutWebpSupport(): void {
    // The test toolkit does not support WebP.
    $this->config('system.image')->set('toolkit', 'test')->save();
    $style = $this->prepareForm(ImageStyle::create(['name' => 'test', 'label' => 'Test']), 'add');
    $this->assertCount(0, $style->getEffects());
  }

  /**
   * Invokes hook_image_style_prepare_form() as the image style form does.
   *
   * @param \Drupal\image\ImageStyleInterface $style
   *   The image style to prepare the form for.
   * @param string $operation
   *   The entity form operation.
   *
   * @return \Drupal\image\ImageStyleInterface
   *   The prepared image style.
   */
  protected function prepareForm(ImageStyleInterface $style, string $operation): ImageStyleInterface {
    $this->container->get('module_handler')->invokeAll('image_style_prepare_form', [$style, $operation, new FormState()]);
    return $style;
  }

}
