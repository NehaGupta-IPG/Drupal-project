<?php

namespace Drupal\Core\Routing;

/**
 * Enumeration of the possible conditions for comparing a route name.
 */
enum RouteName {

  case Equals;
  case StartsWith;
  case Contains;
  case EndsWith;
  case In;

}
