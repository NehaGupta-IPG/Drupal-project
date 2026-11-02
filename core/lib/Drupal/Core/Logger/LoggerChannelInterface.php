<?php

namespace Drupal\Core\Logger;

use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Logger channel interface.
 *
 * This interface defines the full behavior of the central Drupal logger
 * facility. However, when writing code that does logging, use the generic
 * \Psr\Log\LoggerInterface for type hinting instead (you shouldn't need the
 * methods here).
 *
 * To add a new logger to the system, implement \Psr\Log\LoggerInterface and
 * add a service for that class to a services.yml file tagged with the 'logger'
 * tag. The default logger channel implementation will call the log() method
 * of every logger service with some useful data set in the $context argument
 * of log(): request_uri, referer, ip, uid, link, channel, timestamp.
 *
 * SECURITY NOTE: the caller might also set a 'link' in the $context array
 * which will be printed as-is by the dblog module under an "operations"
 * header. Usually this is a "view", "edit" or similar relevant link. Make sure
 * to use proper, secure link generation facilities; some are listed below.
 *
 * @see \Drupal\Core\Logger\RfcLoggerTrait
 * @see \Psr\Log\LoggerInterface
 * @see \Drupal\Core\Logger\\LoggerChannelFactoryInterface
 * @see \Drupal\Core\Utility\LinkGeneratorInterface
 * @see \Drupal\Core\Link::fromTextAndUrl()
 * @see \Drupal\Core\Entity\EntityInterface::link()
 */
interface LoggerChannelInterface extends LoggerInterface {

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function emergency(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function alert(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function critical(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function error(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function warning(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function notice(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function info(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function debug(string|\Stringable $message, array $context = []): void;

  /**
   * {@inheritdoc}
   *
   * @param mixed $level
   *   The log level.
   * @param literal-string $message
   *   The message to log.
   * @param mixed[] $context
   *   The context data to log.
   */
  public function log($level, string|\Stringable $message, array $context = []): void;

  /**
   * Sets the request stack.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $requestStack
   *   The current request object.
   */
  public function setRequestStack(?RequestStack $requestStack = NULL);

  /**
   * Sets the current user.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $current_user
   *   The current user object.
   */
  public function setCurrentUser(?AccountInterface $current_user = NULL);

  /**
   * Sets the loggers for this channel.
   *
   * @param array $loggers
   *   An array of arrays of \Psr\Log\LoggerInterface keyed by priority.
   */
  public function setLoggers(array $loggers);

  /**
   * Adds a logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The PSR-3 logger to add.
   * @param int $priority
   *   The priority of the logger being added.
   */
  public function addLogger(LoggerInterface $logger, $priority = 0);

}
