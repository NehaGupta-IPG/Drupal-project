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

    // An already-present langcode must never be overwritten by an ordinary
    // save, even though nothing here changes it deliberately.
    $config->set('untranslatable_value', 'bar')->save();
    $this->assertSame('en', $config->get('langcode'));

    // Removing the last translatable value must remove the langcode again.
    $config->clear('optional_translatable_value')->save();
    $this->assertNull($config->get('langcode'));
  }

  /**
   * Tests that an explicit langcode is never overwritten by an ordinary save.
   */
  public function testExplicitLangcodeIsNeverOverwritten(): void {
    $config = $this->config('config_test.langcode_test');

    // A translatable value is required for a langcode to be kept at all: set
    // one, then explicitly override the langcode the resolve event picked.
    $config->set('optional_translatable_value', 'Bonjour')->save();
    $this->assertSame('en', $config->get('langcode'));
    $config->set('langcode', 'fr')->save();
    $this->assertSame('fr', $config->get('langcode'));

    // An unrelated save must not touch the explicit langcode - this is the
    // bug this mechanism exists to avoid: 'fr' looks exactly like a merely
    // present default from Config::save()'s point of view, so it must never
    // be re-resolved.
    $config->set('untranslatable_value', 'foo')->save();
    $this->assertSame('fr', $config->get('langcode'));

    // Losing the last translatable value removes the langcode regardless of
    // how it got there - untranslatable configuration never keeps one.
    $config->clear('optional_translatable_value')->save();
    $this->assertNull($config->get('langcode'));
  }

  /**
   * Tests a newly added langcode lands in schema order, not trailing at the end.
   *
   * The castValue() method sorts $this->data into schema order before
   * reconcileLangcodeWithTranslatability() runs, so a langcode it then adds
   * is not part of that sort and lands wherever PHP puts a newly assigned
   * array key: at the end. Left uncorrected, the very next unrelated save
   * would silently re-sort it into its schema position instead, since by
   * then it is just another already-present key - changing the stored key
   * order between two saves of otherwise-identical data, a spurious diff
   * for anyone who exported in between.
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
