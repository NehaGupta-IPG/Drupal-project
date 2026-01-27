<?php

declare(strict_types=1);

namespace Drupal\node_access_test_auto_bubbling\EventSubscriber;

use Drupal\Core\EventSubscriber\EarlyRenderingControllerWrapperSubscriber;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Class that implements an early rendering subscriber that can be disabled.
 */
class TestEarlyRenderingControllerWrapperSubscriber extends EarlyRenderingControllerWrapperSubscriber {

  /**
   * The key value store factory.
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  #[Required]
  public function setKeyValueFactory(KeyValueFactoryInterface $keyValueFactory): void {
    $this->keyValueFactory = $keyValueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function onController(ControllerEvent $event): void {
    // Disable early rendering based on value from key value store.
    if ($this->keyValueFactory->get('node_access_test')->get('disable_early_rendering', FALSE)) {
      return;
    }
    parent::onController($event);
  }

}
