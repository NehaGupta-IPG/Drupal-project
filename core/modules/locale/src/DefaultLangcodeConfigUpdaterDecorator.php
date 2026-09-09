<?php

declare(strict_types=1);

namespace Drupal\locale;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\language\DefaultLangcodeConfigUpdater;

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
final class DefaultLangcodeConfigUpdaterDecorator extends DefaultLangcodeConfigUpdater {

  public function __construct(
    protected readonly DefaultLangcodeConfigUpdater $inner,
    protected readonly LocaleConfigManager $localeConfigManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildBatch(string $old_langcode, string $new_langcode): BatchBuilder {
    return $this->inner->buildBatch($old_langcode, $new_langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function reconcileConfigLangcodeOperation(string $old_langcode, string $new_langcode, &$context): void {
    $this->localeConfigManager->executeWithUpdatingTranslationsFromLocale(function () use ($old_langcode, $new_langcode, &$context) {
      $this->inner->reconcileConfigLangcodeOperation($old_langcode, $new_langcode, $context);
    });
  }

}
