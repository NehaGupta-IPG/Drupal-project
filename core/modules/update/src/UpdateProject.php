<?php

declare(strict_types=1);

namespace Drupal\update;

/**
 * Update project information.
 *
 * Update information for one project. This is used to assist with calculating
 * the relevant updates and version compatibility.
 *
 * @see \Drupal\update\UpdateCalculator
 */
class UpdateProject extends \ArrayObject {

  public function __construct(
    public ?string $name = NULL,
    public ?array $info = NULL,
    public ?array $includes = NULL,
    public ?string $project_type = NULL,
    public ?array $disabled = NULL,
    public string|int|null $existing_major = NULL,
    public int|string|null $status = NULL,
    public array $extra = [],
    public ?string $title = NULL,
    public ?string $link = NULL,
    public ?string $reason = NULL,
    public ?bool $project_status = NULL,
    public string|\Stringable $existing_version = '',
    public ?string $fetch_status = NULL,
    public ?string $recommended = NULL,
    public array $also = [],
    public array $releases = [],
    public ?string $latest_version = NULL,
    public ?string $dev_version = NULL,
    public ?string $install_type = NULL,
    public ?string $latest_dev = NULL,
    public int|string|null $datestamp = NULL,
    public ?array $security_updates = NULL,
  ) {}

  /**
   * Creates a UpdateProject object.
   *
   * @param array $data
   *   The project data from the Update XML.
   *
   * @return static
   */
  public static function createFromArray(array $data): static {
    return new UpdateProject(...$data);
  }

  /**
   * Creates an array from UpdateProject object.
   *
   * @return array
   *   The array representation on the object.
   */
  public function toArray(): array {
    $project_data = [];
    $properties = [
      'extra',
      'also',
      'releases',
      'name',
      'title',
      'link',
      'reason',
      'fetch_status',
      'install_type',
      'latest_dev',
      'latest_version',
      'info',
      'includes',
      'project_type',
      'disabled',
      'existing_major',
      'status',
      'project_status',
      'recommended',
      'dev_version',
      'datestamp',
    ];

    foreach ($properties as $prop) {
      $this->$prop ? $project_data[$prop] = $this->$prop : '';
    }

    $project_data['existing_version'] = $this->existing_version;
    $this->security_updates ? $project_data['security updates'] = $this->security_updates : '';

    return $project_data;
  }

}
