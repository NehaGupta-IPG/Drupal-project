<?php

declare(strict_types=1);

namespace Drupal\update;

/**
 * Update server project information.
 *
 * The information from the server about available updates for an individual
 * project.
 *
 * @see \Drupal\update\UpdateCalculator
 */
class UpdateServerProjectInfo extends \ArrayObject {

  public function __construct(
    public ?string $status = NULL,
    public array $supported_branches = [],
    public array $releases = [],
    public string $title = '',
    public string $link = '',
    public ?int $fetch_status = NULL,
    public ?int $last_fetch = NULL,
    public ?string $short_name = NULL,
    public ?string $terms = NULL,
  ) {}

  /**
   * Creates a UpdateServerProjectInfo object.
   *
   * @param array $data
   *   The project data from the Update XML.
   *
   * @return static
   */
  public static function createFromArray(array $data): static {
    $data['supported_branches'] ??= [];
    return new UpdateServerProjectInfo(
      $data['project_status'] ?? NULL,
      $data['supported_branches'] ? explode(',', $data['supported_branches']) : [],
      $data['releases'] ?? [],
      $data['title'] ?? '',
      $data['link'] ?? '',
      $data['fetch_status'] ?? NULL,
      $data['last_fetch'] ?? NULL,
      $data['short_name'] ?? NULL,
      $data['terms'] ?? NULL,
    );
  }

}
