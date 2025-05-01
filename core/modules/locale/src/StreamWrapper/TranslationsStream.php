<?php

namespace Drupal\locale\StreamWrapper;

use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a Drupal translations (translations://) stream wrapper class.
 *
 * Provides support for storing translation files.
 */
class TranslationsStream extends PrivateStream {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    return StreamWrapperInterface::LOCAL_HIDDEN;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t('Translation files');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Translation files');
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    return \Drupal::config('locale.settings')->get('translation.path');
  }

  /**
   * Implements Drupal\Core\StreamWrapper\StreamWrapperInterface::getExternalUrl().
   *
   * @throws \LogicException
   *   PO files URL should not be public.
   */
  public function getExternalUrl() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3378735', E_USER_DEPRECATED);
    throw new \LogicException('PO files URL should not be public.');
  }

}
