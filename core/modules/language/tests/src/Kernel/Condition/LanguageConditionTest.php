<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel\Condition;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the language condition plugin.
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class LanguageConditionTest extends KernelTestBase {

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = $this->container->get('plugin.manager.condition');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpEnvironment(): void {
    parent::setUpEnvironment();
    $this->installConfig(['language']);
    // Setup Italian.
    ConfigurableLanguage::createFromLangcode('it')->save();
  }

  /**
   * Tests the language condition.
   */
  public function testConditions(): void {
    // Grab the language condition and configure it to check the content
    // language.
    $language = \Drupal::languageManager()->getLanguage('en');
    $condition = $this->manager->createInstance('language')
      ->setConfig('langcodes', ['en' => 'en', 'it' => 'it'])
      ->setContextValue('language', $language);
    $this->assertTrue($condition->execute(), 'Language condition passes as expected.');
    // Check for the proper summary.
    $this->assertEquals('The language is English, Italian.', $condition->summary());

    // Change to Italian only.
    $condition->setConfig('langcodes', ['it' => 'it']);
    $this->assertFalse($condition->execute(), 'Language condition fails as expected.');
    // Check for the proper summary.
    $this->assertEquals('The language is Italian.', $condition->summary());

    // Negate the condition.
    $condition->setConfig('negate', TRUE);
    $this->assertTrue($condition->execute(), 'Language condition passes as expected.');
    // Check for the proper summary.
    $this->assertEquals('The language is not Italian.', $condition->summary());

    // Change the default language to Italian.
    $language = \Drupal::languageManager()->getLanguage('it');

    $condition = $this->manager->createInstance('language')
      ->setConfig('langcodes', ['en' => 'en', 'it' => 'it'])
      ->setContextValue('language', $language);

    $this->assertTrue($condition->execute(), 'Language condition passes as expected.');
    // Check for the proper summary.
    $this->assertEquals('The language is English, Italian.', $condition->summary());

    // Change to Italian only.
    $condition->setConfig('langcodes', ['it' => 'it']);
    $this->assertTrue($condition->execute(), 'Language condition passes as expected.');
    // Check for the proper summary.
    $this->assertEquals('The language is Italian.', $condition->summary());

    // Negate the condition.
    $condition->setConfig('negate', TRUE);
    $this->assertFalse($condition->execute(), 'Language condition fails as expected.');
    // Check for the proper summary.
    $this->assertEquals('The language is not Italian.', $condition->summary());
  }

}
