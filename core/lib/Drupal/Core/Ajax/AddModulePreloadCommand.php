<?php

namespace Drupal\Core\Ajax;

/**
 * An AJAX command for adding module preloads to the page.
 *
 * Module preloads are <link rel="modulepreload"> elements, instructing the
 * browser to fetch the given module, so that module scripts that import it
 * can start executing as soon as possible. A page only supports a single
 * import map, which is sent with the main HTML document; module preloads may
 * be added to a page multiple times, e.g., in Ajax responses.
 *
 * This command is implemented by
 * Drupal.AjaxCommands.prototype.add_module_preload() defined in misc/ajax.js.
 *
 * @see misc/ajax.js
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/rel/modulepreload
 *
 * @ingroup ajax
 */
class AddModulePreloadCommand implements CommandInterface {

  /**
   * Arrays containing attributes of the module preload link elements.
   *
   * @var string[][]
   */
  protected $modulePreloads;

  /**
   * Constructs an AddModulePreloadCommand.
   *
   * @param string[][] $modulePreloads
   *   Arrays containing attributes of the <link rel="modulepreload"> elements
   *   to be added to the page. i.e. `['rel' => 'modulepreload', 'href' =>
   *   'someURL']` becomes `<link rel="modulepreload" href="someURL">`.
   */
  public function __construct(array $modulePreloads) {
    $this->modulePreloads = $modulePreloads;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'add_module_preload',
      'data' => $this->modulePreloads,
    ];
  }

}
