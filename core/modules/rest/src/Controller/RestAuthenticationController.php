<?php

namespace Drupal\rest\Controller;

use Drupal\user\Controller\UserAuthenticationController;

/**
 * Provides controllers for login, login status and logout via HTTP requests.
 *
 * @todo Move the logic from UserAuthenticationController here
 * and remove UserAuthenticationController in Drupal 12.
 */
class RestAuthenticationController extends UserAuthenticationController {
}
