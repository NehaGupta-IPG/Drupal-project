<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\LanguageArgument as CoreLanguageArgument;

/**
 * Overriding the views argument plugin "language".
 */
class LanguageArgument extends CoreLanguageArgument {

  use ArgumentPluginTrait;

}
