<?php

declare(strict_types=1);

namespace Drupal\file_test\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;

/**
 * Dummy read-only remote stream wrapper (dummy-remote-readonly://).
 */
class DummyRemoteReadOnlyStreamWrapper extends DummyRemoteStreamWrapper {

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
    return 'Dummy remote read-only files';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'Dummy remote read-only stream wrapper for testing.';
  }

}
