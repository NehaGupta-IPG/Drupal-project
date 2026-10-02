<?php

declare(strict_types=1);

namespace Drupal\locale;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Site\Settings;
use Drupal\language\DefaultLangcodeConfigUpdater;
use Drupal\language\DefaultLangcodeConfigUpdaterInterface;

/**
 * Keeps DefaultLangcodeConfigUpdater's own saves out of locale storage.
 *
 * \Drupal\language\DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation()
 * saves configuration and language overrides while updating them to the
 * new default. It moves existing content between the active store and an
 * override, not authoring anything new. Left undecorated, those saves fire
 * the same events a deliberate edit would, and
 * \Drupal\locale\LocaleConfigSubscriber would record them as customized
 * translations a second time.
 *
 * @see \Drupal\locale\LocaleConfigSubscriber
 */
final class DefaultLangcodeConfigUpdaterDecorator implements DefaultLangcodeConfigUpdaterInterface {

  public function __construct(
    protected readonly DefaultLangcodeConfigUpdaterInterface $inner,
    protected readonly LocaleConfigManager $localeConfigManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildBatch(string $old_langcode, string $new_langcode): BatchBuilder {
    return $this->inner->buildBatch($old_langcode, $new_langcode)->addOperation(
      DefaultLangcodeConfigUpdater::class . ':translateUpdatedConfigOperation',
      [$new_langcode]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function reconcileConfigLangcodeOperation(string $old_langcode, string $new_langcode, &$context): void {
    $this->localeConfigManager->executeWithUpdatingTranslationsFromLocale(function () use ($old_langcode, $new_langcode, &$context) {
      $this->inner->reconcileConfigLangcodeOperation($old_langcode, $new_langcode, $context);
    });
  }

  /**
   * Translates configuration just updated by DefaultLangcodeConfigUpdater.
   *
   * Runs after \Drupal\language\DefaultLangcodeConfigUpdater's own operation
   * completes, so $context['results']['updated_names'] holds every name
   * it updated to the new default, and its pre-change content already
   * saved as an override for the previous default. Applying translations
   * here reuses LocaleConfigManager::updateConfigTranslations(), the same
   * mechanism that already merges real translated strings into a config
   * object's active record when the target langcode matches the object's
   * own langcode - which, having just been updated, it now does.
   *
   * @param string $new_langcode
   *   The new default langcode.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see \Drupal\language\DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation()
   */
  public function translateUpdatedConfigOperation(string $new_langcode, &$context): void {
    if (!isset($context['sandbox']['names'])) {
      $context['sandbox']['names'] = $context['results']['updated_names'] ?? [];
      $context['sandbox']['max'] = count($context['sandbox']['names']);
    }

    $names = array_splice($context['sandbox']['names'], 0, Settings::get('entity_update_batch_size', 50));
    if (!empty($names)) {
      // Key paths DefaultLangcodeConfigUpdater found no override for. These
      // keys still have value from the previous default language and should
      // fall back to the English value, if it exists.
      $untranslated_keys = array_intersect_key($context['results']['untranslated_keys'] ?? [], array_flip($names));
      $this->localeConfigManager->updateConfigTranslations($names, [$new_langcode], $untranslated_keys);
    }

    $context['sandbox']['progress'] = ($context['sandbox']['progress'] ?? 0) + count($names);
    $context['finished'] = empty($context['sandbox']['max']) ? 1 : $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

}
