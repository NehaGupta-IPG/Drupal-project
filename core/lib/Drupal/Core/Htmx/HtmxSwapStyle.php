<?php

namespace Drupal\Core\Htmx;

enum HtmxSwapStyle {
  case innerHTML;
  case outerHTML;
  case textContent;
  case beforebegin;
  case afterbegin;
  case beforeend;
  case afterend;
  case delete;
  case none;
}
