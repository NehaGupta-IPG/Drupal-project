<?php

declare(strict_types=1);

namespace Drupal\media\Plugin\media\Source;

use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

interface FileSourceInterface {

  public function getFile(MediaInterface $media): ?FileInterface;

}
