<?php

namespace Drupal\Component\Diff;

/**
 * FIXME: bad name.
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class MappedDiff extends Diff {

  public function __construct($from_lines, $to_lines, $mapped_from_lines, $mapped_to_lines) {

    assert(sizeof($from_lines) == sizeof($mapped_from_lines));
    assert(sizeof($to_lines) == sizeof($mapped_to_lines));

    parent::__construct($mapped_from_lines, $mapped_to_lines);

    $xi = $yi = 0;
    for ($i = 0; $i < sizeof($this->edits); $i++) {
      $orig = &$this->edits[$i]->orig;
      if (is_array($orig)) {
        $orig = array_slice($from_lines, $xi, sizeof($orig));
        $xi += sizeof($orig);
      }

      $closing = &$this->edits[$i]->closing;
      if (is_array($closing)) {
        $closing = array_slice($to_lines, $yi, sizeof($closing));
        $yi += sizeof($closing);
      }
    }
  }

}
