<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ConfigurableLanguage entity.
 *
 * @see \Drupal\language\Entity\ConfigurableLanguage.
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class ConfigurableLanguageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

  /**
   * Tests configurable language name methods.
   */
  public function testName(): void {
    $name = $this->randomMachineName();
    $language_code = $this->randomMachineName(2);
    $configurableLanguage = new ConfigurableLanguage(['label' => $name, 'id' => $language_code], 'configurable_language');
    $this->assertEquals($name, $configurableLanguage->getName());
    $this->assertEquals('Test language', $configurableLanguage->setName('Test language')->getName());
  }

  /**
   * Tests configurable language local name methods.
   */
  public function testLocalName(): void {
    $configurableLanguage = new ConfigurableLanguage(['label' => 'French', 'local' => 'Français', 'id' => 'fr'], 'configurable_language');
    $this->assertSame('Français', $configurableLanguage->getLocal());
    $this->assertSame('Deutsch', $configurableLanguage->setLocalName('Deutsch')->getLocal());

    $configurableLanguage = new ConfigurableLanguage(['label' => 'Spanish', 'id' => 'es'], 'configurable_language');
    $this->assertSame('Español', $configurableLanguage->getLocal());

    $configurableLanguage = new ConfigurableLanguage(['label' => 'Custom language', 'id' => 'xx'], 'configurable_language');
    $this->assertSame('Custom language', $configurableLanguage->getLocal());

    $configurableLanguage = new ConfigurableLanguage(['label' => 'French', 'local' => NULL, 'id' => 'fr'], 'configurable_language');
    $this->assertSame('Français', $configurableLanguage->getLocal());

    $configurableLanguage = ConfigurableLanguage::create([
      'id' => 'de',
      'label' => 'German',
      'local' => 'Deutsch',
    ]);
    $configurableLanguage->save();
    $configurableLanguage = ConfigurableLanguage::load('de');
    $this->assertSame('Deutsch', $configurableLanguage->getLocal());
  }

}
