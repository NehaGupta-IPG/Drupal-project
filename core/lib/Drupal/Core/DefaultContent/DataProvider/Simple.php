<?php

declare(strict_types=1);

namespace Drupal\Core\DefaultContent\DataProvider;

/**
 * A data provider for importing a single content entity.
 *
 * @internal
 *   This API is experimental.
 */
final class Simple implements DataProviderInterface {

  /**
   * Constructs a Simple data provider.
   *
   * @param array<mixed> $data
   *   The decoded entity data array, with at minimum a `_meta.uuid` key.
   */
  public function __construct(private readonly array $data) {}

  /**
   * {@inheritdoc}
   */
  public function getData(): array {
    return [$this->data['_meta']['uuid'] => $this->data];
  }

}
