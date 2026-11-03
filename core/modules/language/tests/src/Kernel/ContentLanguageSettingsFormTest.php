<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Form\ContentLanguageSettingsForm;
use Drupal\Tests\HttpKernelUiHelperTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the rendering of the content language settings form.
 */
#[Group('language')]
#[CoversClass(ContentLanguageSettingsForm::class)]
#[RunTestsInSeparateProcesses]
class ContentLanguageSettingsFormTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use HttpKernelUiHelperTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'language',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'node']);

    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    $this->setCurrentUser($this->createUser(['administer languages']));
  }

  /**
   * Tests that duplicate bundle labels are disambiguated by machine name.
   */
  public function testDuplicateBundleLabels(): void {
    // Two bundles sharing the same label but with distinct machine names, plus
    // a bundle with a unique label.
    $this->createContentType(['type' => 'text', 'name' => 'Text']);
    $this->createContentType(['type' => 'sidebar_text', 'name' => 'Text']);
    $this->createContentType(['type' => 'unique', 'name' => 'Unique']);

    $this->drupalGet('admin/config/regional/content-language');

    // Duplicate labels are suffixed with the machine name.
    $this->assertSession()->pageTextContains('Text (text)');
    $this->assertSession()->pageTextContains('Text (sidebar_text)');

    // A unique label is left untouched.
    $this->assertSession()->pageTextContains('Unique');
    $this->assertSession()->pageTextNotContains('Unique (unique)');
  }

}
