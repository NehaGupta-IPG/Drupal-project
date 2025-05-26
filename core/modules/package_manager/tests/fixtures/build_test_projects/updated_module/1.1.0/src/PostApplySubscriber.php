<?php

declare(strict_types=1);

namespace Drupal\updated_module;

use Drupal\package_manager\Event\PostApplyEvent;
use Drupal\package_manager\PathLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes a file after staged changes are applied to the active directory.
 *
 * This event subscriber doesn't exist in version 1.0.0 of this module, so we
 * use it to test that new event subscribers are picked up after staged changes
 * have been applied.
 */
class PostApplySubscriber implements EventSubscriberInterface {

  /**
   * Constructs a PostApplySubscriber.
   *
   * @param \Drupal\package_manager\PathLocator $pathLocator
   *   The path locator service.
   */
  public function __construct(
    private PathLocator $pathLocator,
  ) {}

  /**
   * Writes a file when staged changes are applied to the active directory.
   */
  public function postApply(): void {
    $dir = $this->pathLocator->getProjectRoot();
    file_put_contents("$dir/bravo.txt", 'Bravo!');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostApplyEvent::class => 'postApply',
    ];
  }

}
