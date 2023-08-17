<?php

namespace Drupal\Core\Shutdown;

/**
 * Callback stack instance used by the ShutdownHandler class.
 *
 * @see \Drupal\Core\Shutdown\ShutdownHandler
 */
class CallbackStack extends \ArrayIterator {}
