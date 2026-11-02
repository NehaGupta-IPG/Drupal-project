<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Component\FileSystem\VersionedFile;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rebuild subscriber.
 */
class RebuildSubscriber implements EventSubscriberInterface {

  /**
   * The versioned file handler.
   *
   * @var \Drupal\Component\FileSystem\VersionedFile
   */
  protected VersionedFile $versionedFile;

  public function __construct(
    protected StateInterface $state,
    DrupalKernelInterface $kernel,
  ) {
    $this->versionedFile = $kernel->getVersionedFile();
  }

  /**
   * Reset on terminate if necessary.
   */
  public function onTerminate(): void {
    $this->versionedFile->resetIfNecessary($this->state->get('container_version', 0));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::TERMINATE] = ['onTerminate'];
    return $events;
  }

}
