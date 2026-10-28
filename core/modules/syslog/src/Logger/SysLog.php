<?php

namespace Drupal\syslog\Logger;

use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

// cspell:ignore ndelay

/**
 * Redirects logging messages to syslog.
 */
class SysLog implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * Stores whether there is a system logger connection opened or not.
   *
   * @var bool
   */
  protected $connectionOpened = FALSE;

  public function __construct(
    #[AutowireServiceClosure('config.factory')]
    protected \Closure $configFactory,
    protected LogMessageParserInterface $parser,
  ) {
  }

  /**
   * Gets syslog configuration object for backwards-compatibility purposes.
   */
  public function __get(string $name): mixed {
    trigger_error('The config property is deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use the configFactory property instead. See https://www.drupal.org/node/3343843', E_USER_DEPRECATED);
    return match ($name) {
      'config' => ($this->configFactory)()->get('syslog.settings'),
    };
  }

  /**
   * Opens a connection to the system logger.
   */
  protected function openConnection() {
    if (!$this->connectionOpened) {
      // Do not connect if identity or facility are not configured.
      $config = ($this->configFactory)()->get('syslog.settings');
      $identity = $config->get('identity');
      $facility = $config->get('facility');
      if ($identity === NULL || $facility === NULL) {
        return;
      }
      $this->connectionOpened = openlog($identity, LOG_NDELAY, $facility);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    global $base_url;

    $format = ($this->configFactory)()->get('syslog.settings')->get('format');
    // If no format is configured then a message will not be written to syslog
    // so return early. This occurs during installation of the syslog module
    // before configuration has been written.
    if (empty($format)) {
      return;
    }

    // Ensure we have a connection available.
    $this->openConnection();
    if (!$this->connectionOpened) {
      return;
    }

    // Populate the message placeholders and then replace them in the message.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

    $entry = strtr($format, [
      '!base_url' => $base_url,
      '!timestamp' => $context['timestamp'],
      '!type' => $context['channel'],
      '!ip' => $context['ip'],
      '!request_uri' => $context['request_uri'],
      '!referer' => $context['referer'],
      '!severity' => $level,
      '!uid' => $context['uid'],
      '!link' => strip_tags($context['link']),
      '!message' => strip_tags($message),
    ]);

    $this->syslogWrapper($level, $entry);
  }

  /**
   * A syslog wrapper to make syslog functionality testable.
   *
   * @param int $level
   *   The syslog priority.
   * @param string $entry
   *   The message to send to syslog function.
   */
  protected function syslogWrapper($level, $entry) {
    syslog($level, $entry);
  }

}
