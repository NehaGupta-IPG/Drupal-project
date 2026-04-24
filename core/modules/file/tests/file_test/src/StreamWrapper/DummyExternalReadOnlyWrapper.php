<?php

declare(strict_types=1);

namespace Drupal\file_test\StreamWrapper;

use Drupal\Core\StreamWrapper\ReadOnlyStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;

/**
 * Helper class for testing the stream wrapper registry.
 *
 * Dummy external stream wrapper implementation (dummy-external-readonly://).
 */
class DummyExternalReadOnlyWrapper extends ReadOnlyStream {

  /**
   * {@inheritdoc}
   */
  public static function getType(): int {
    return StreamWrapperInterface::READ_VISIBLE;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'Dummy external stream wrapper (readonly)';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'Dummy external read-only stream wrapper for testing.';
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl(): string {
    [, $target] = explode('://', $this->uri, 2);
    return 'https://www.dummy-external-readonly.com/' . $target;
  }

  /**
   * {@inheritdoc}
   */
  public function realpath(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri = NULL): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_closedir(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_opendir($path, $options): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_readdir(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dir_rewinddir(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_cast($cast_as): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_close(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_eof(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_read($count): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_seek($offset, $whence = SEEK_SET): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_set_option($option, $arg1, $arg2): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_stat(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_tell(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function url_stat($path, $flags): bool {
    return FALSE;
  }

}
