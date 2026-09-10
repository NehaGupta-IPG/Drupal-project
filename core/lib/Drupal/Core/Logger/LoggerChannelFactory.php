<?php

namespace Drupal\Core\Logger;

use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a factory for logging channels.
 */
class LoggerChannelFactory implements LoggerChannelFactoryInterface {

  /**
   * Array of all instantiated logger channels keyed by channel name.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface[]
   */
  protected $channels = [];

  /**
   * An array of arrays of \Psr\Log\LoggerInterface keyed by priority.
   *
   * @var array
   */
  protected $loggers = [];

  /**
   * Constructs a LoggerChannelFactory.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   (optional) The request stack.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   (optional) The current user.
   * @param iterable<\Psr\Log\LoggerInterface> $containerLoggers
   *   The logger services, sorted by descending priority.
   */
  public function __construct(
    protected RequestStack $requestStack,
    protected AccountInterface $currentUser,
    protected iterable $containerLoggers = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function get($channel) {
    if ($this->containerLoggers !== []) {
      // Clear the iterator before resolving logger services, since their
      // dependencies may request a channel themselves.
      $loggers = $this->containerLoggers;
      $this->containerLoggers = [];
      foreach ($loggers as $logger) {
        // The tagged iterator has already sorted the services by priority.
        $this->loggers[0][] = $logger;
      }
      // Update channels requested while logger services were being resolved.
      foreach ($this->channels as $instance) {
        $instance->setLoggers($this->loggers);
      }
    }

    if (!isset($this->channels[$channel])) {
      $instance = new LoggerChannel($channel);

      // Set the request_stack and current_user services on the channel.
      // It is up to the channel to determine if there is a current request.
      $instance->setRequestStack($this->requestStack);
      $instance->setCurrentUser($this->currentUser);

      // Pass the loggers to the channel.
      $instance->setLoggers($this->loggers);
      $this->channels[$channel] = $instance;
    }

    return $this->channels[$channel];
  }

  /**
   * {@inheritdoc}
   */
  public function addLogger(LoggerInterface $logger, $priority = 0) {
    // Store it so we can pass it to potential new logger instances.
    $this->loggers[$priority][] = $logger;
    // Add the logger to already instantiated channels.
    foreach ($this->channels as $channel) {
      $channel->addLogger($logger, $priority);
    }
  }

}
