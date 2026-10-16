<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that simple configuration gains and loses a langcode as its data changes.
 *
 * A configuration object's translatability is determined by the values it
 * currently holds, not by a fixed property of its schema - for example, a
 * plugin being swapped for one with a translatable setting. Config::save()
 * must add a langcode the first time that happens, and remove it again if
 * the last translatable value is removed.
 *
 * @see \Drupal\Core\Config\Config::reconcileLangcodeWithTranslatability()
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigLangcodeTranslatabilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test'];

  /**
   * Tests langcode is added and removed as translatability changes.
   */
  public function testLangcodeFollowsTranslatability(): void {
    $config = $this->config('config_test.langcode_test');

    // No translatable value present: no langcode.
    $config->set('untranslatable_value', 'foo')->save();
    $this->assertNull($config->get('langcode'));

    // Setting a translatable value must add a langcode.
    $config->set('optional_translatable_value', 'Hello')->save();
    $this->assertSame('en', $config->get('langcode'));

    // Change the langcode.
    $config->set('langcode', 'fr')->save();
    $this->assertSame('fr', $config->get('langcode'));

    // An already-present langcode must never be overwritten by an ordinary
    // save.
    $config->set('untranslatable_value', 'bar')->save();
    $this->assertSame('fr', $config->get('langcode'));

    // Removing the last translatable value must remove the langcode again.
    $config->clear('optional_translatable_value')->save();
    $this->assertNull($config->get('langcode'));
  }

  /**
   * Tests a newly added langcode lands in schema order.
   */
  public function testLangcodeAddedInSchemaOrder(): void {
    $config = $this->config('config_test.langcode_test');
    $config->set('untranslatable_value', 'foo')->save();

    // This save adds langcode for the first time.
    $config->set('optional_translatable_value', 'Hello')->save();
    $keys_after_add = array_keys($config->getRawData());

    // This save only changes an already-present value: no key is added or
    // removed, so any difference in order can only come from re-sorting.
    $config->set('untranslatable_value', 'bar')->save();
    $keys_after_unrelated_save = array_keys($config->getRawData());

    $this->assertSame($keys_after_unrelated_save, $keys_after_add);
  }

}
