<?php

namespace Drupal\Core\Htmx;

use DOMElement;

class HtmxSwapModifiers {

  protected array $modifiers = [];

  /**
   * @param array $modifiers
   */
  public function setModifier(string $name, $value): static {
    $this->modifiers[$name] = $value;
    return $this;
  }

  public function transition(bool $value = TRUE): static {
    if ($value === FALSE) {
      unset($this->modifiers['transition']);
      return $this;
    }
    return $this->setModifier('transition', $value);
  }

  public function swap(int $value, string $unit = 'ms'): static {
    return $this->setModifier('swap', $value . $unit);
  }

  public function settle(int $value, string $unit = 'ms'): static {
    return $this->setModifier('settle', $value . $unit);
  }

  public function ignoreTitle(bool $value = TRUE): static {
    return $this->setModifier('ignoreTitle', $value);
  }

  public function __toString(): string {
    $out = '';
    foreach ($this->modifiers as $name => $value) {
      if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
      }
      $out .= $name . ':' . $value . ';';
    }
    return $out;
  }

}
