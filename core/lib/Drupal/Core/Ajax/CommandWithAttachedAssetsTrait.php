<?php

namespace Drupal\Core\Ajax;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Trait for Ajax commands that render content and attach assets.
 *
 * @ingroup ajax
 */
trait CommandWithAttachedAssetsTrait {

  /**
   * The attached assets for this Ajax command.
   *
   * @var \Drupal\Core\Asset\AttachedAssets
   */
  protected $attachedAssets;

  /**
   * The cacheability metadata for this Ajax command.
   */
  protected CacheableMetadata $cacheableMetadata;

  /**
   * Processes the content for output.
   *
   * If content is a render array, it may contain attached assets to be
   * processed.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   HTML rendered content.
   */
  protected function getRenderedContent() {
    $this->attachedAssets = new AttachedAssets();
    if (is_array($this->content)) {
      if (!$this->content) {
        return '';
      }
      $html = \Drupal::service('renderer')->renderRoot($this->content);
      $this->attachedAssets = AttachedAssets::createFromRenderArray($this->content);
      $this->cacheableMetadata = CacheableMetadata::createFromRenderArray($this->content);
      return $html;
    }
    else {
      return $this->content;
    }
  }

  /**
   * Gets the attached assets.
   *
   * @return \Drupal\Core\Asset\AttachedAssets|null
   *   The attached assets for this command.
   */
  public function getAttachedAssets() {
    return $this->attachedAssets;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return $this->cacheableMetadata;
  }

}
