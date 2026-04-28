<?php

declare(strict_types=1);

namespace Drupal\core_mailer_services_test;

use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\Experimental\MailerServicesTrait;

/**
 * Service provider for testing the mailer services.
 */
class CoreMailerServicesTestServiceProvider implements ServiceProviderInterface {
  use MailerServicesTrait;

}
