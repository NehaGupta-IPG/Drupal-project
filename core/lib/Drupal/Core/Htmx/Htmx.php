<?php

namespace Drupal\Core\Htmx;

use Drupal\Core\Url;

/**
 *
 */
class Htmx {

  /**
   * The storage.
   *
   * @internal
   */
  protected array $storage = [];

  /**
   * Applies the values of this CacheableMetadata object to a render array.
   *
   * @param array &$build
   *   A render array.
   */
  public function applyTo(array &$build): void {
    foreach ($this->storage as $key => $value) {
      $build['#htmx'][$key] = $value;
    }
  }

  /**
   * Creates a CacheableMetadata object with values taken from a render array.
   *
   * @param array $build
   *   A render array.
   *
   * @return static
   */
  public static function createFromRenderArray(array $build) {
    $meta = new static();
    foreach ($build['#htmx'] ?? [] as $key => $value) {
      $meta->storage[$key] = $value;
    }
    return $meta;
  }

  protected function setValue(string $name, $value): self {
    $this->storage[$name] = $value;
    return $this;
  }

  /**
   * Creates a `data-hx-get` attribute.
   *
   * This attribute instructs HTMX to issue a GET request to the specified URL.
   *
   * This request method also accepts no parameters, which issues a get
   * request to the current url. If parameters are used, both are required.
   *
   * @param \Drupal\Core\Url|true $url
   *   The URL for the GET request. If NULL the current page is used.
   *
   * @return static
   *   returns self so that attribute methods may be chained.
   *
   * @see https://htmx.org/attributes/hx-get/
   */
  public function get(Url|true $url = TRUE): static {
    return $this->setValue('get', $url);
  }

  /**
   * Creates a `data-hx-post` attribute.
   *
   * This attribute instructs HTMX to issue a POST request to the specified URL.
   *
   *  This request method also accepts no parameters, which issues a post
   *  request to the current url. If parameters are used, both are required.
   *
   * @param \Drupal\Core\Url|true $url
   *   The URL for the POST request. If NULL the current page is used.
   *
   * @return static
   *   returns self so that attribute methods may be chained.
   *
   * @see https://htmx.org/attributes/hx-post/
   */
  public function post(Url|true $url = TRUE): static {
    return $this->setValue('post', $url);
  }

  /**
   * Creates a `data-hx-put` attribute.
   *
   * This attribute instructs HTMX to issue a PUT request to the specified URL.
   *
   *  This request method also accepts no parameters, which issues a put
   *  request to the current url. If parameters are used, both are required.
   *
   * @param \Drupal\Core\Url|true $url
   *   The URL for the PUT request. If NULL the current page is used.
   *
   * @return static
   *   returns self so that attribute methods may be chained.
   *
   * @see https://htmx.org/attributes/hx-put/
   */
  public function put(Url|true $url = TRUE): static {
    return $this->setValue('put', $url);
  }

  /**
   * Creates a `data-hx-patch` attribute.
   *
   * This attribute instructs HTMX to issue a PATCH request.
   *
   *  This request method also accepts no parameters, which issues a patch
   *  request to the current url. If parameters are used, both are required.
   *
   * @param \Drupal\Core\Url|true $url
   *   The URL for the PATCH request. If NULL the current page is used.
   *
   * @return static
   *   returns self so that attribute methods may be chained.
   *
   * @see https://htmx.org/attributes/hx-patch/
   */
  public function patch(Url|true $url = TRUE): static {
    return $this->setValue('patch', $url);
  }

  /**
   * Creates a `data-hx-delete` attribute.
   *
   * This attribute instructs HTMX to issue a DELETE request
   * to the specified URL.
   *
   *  This request method also accepts no parameters, which issues a delete
   *  request to the current url. If parameters are used, both are required.
   *
   * @param \Drupal\Core\Url|true $url
   *   The URL for the DELETE request. If NULL the current page is used.
   *
   * @return static
   *   returns self so that attribute methods may be chained.
   *
   * @see https://htmx.org/attributes/hx-delete/
   */
  public function delete(Url|true $url = TRUE): static {
    return $this->setValue('delete', $url);
  }

  /**
   * Creates a `data-hx-on` attribute.
   *
   * This attribute instructs HTMX to react to events with inline scripts
   * on elements.
   * - $event is the name of the JavaScript event.
   * - $action is the JavaScript statement to execute when the event occurs.
   *   This can be a short instruction or call a function in a script that
   *   has been loaded by the page.
   *
   * @param string $event
   *   An event in either camelCase or kebab-case.
   * @param string $action
   *   The JavaScript statement.
   *
   * @return static
   *   Returns this object to allow chaining methods.
   *
   * @see https://htmx.org/attributes/hx-on/
   */
  public function on(string $event, string $action): static {
    $this->storage['on'][$event] = $action;
    return $this;
  }

  /**
   * Creates a `data-hx-select-oob` attribute.
   *
   * This attribute instructs HTMX to select content for an out-of-band swap
   * from a response. Each value can specify any valid hx-swap strategy by
   * separating the selector and the swap strategy with a colon,
   * such as #alert:afterbegin.
   *
   * @param string|string[] $selectors
   *   A value or array of values.
   *
   * @return static
   *   Returns this object to allow chaining methods.
   *
   * @see https://htmx.org/attributes/hx-select-oob/
   */
  public function selectOob(string|array $selectors): static {
    if (!is_array($selectors)) {
      $selectors = [$selectors];
    }
    return $this->setValue('select-oob', $selectors);
  }

  /**
   * Creates a `data-hx-swap` attribute.
   *
   * This attribute allows you to specify how the response will be
   * swapped into the DOM relative to the target of an AJAX request.
   *
   * @param \Drupal\Core\Htmx\HtmxSwapStyle $style
   *   The swap strategy.
   * @param \Drupal\Core\Htmx\HtmxSwapModifiers $modifiers
   *   Optional modifiers for changing the behavior of the swap.
   *
   * @return static
   *   Returns this object to allow chaining methods.
   *
   * @see https://htmx.org/attributes/hx-swap/
   */
  public function swap(HtmxSwapStyle $style, ?HtmxSwapModifiers $modifiers = new HtmxSwapModifiers()): static {
    $this->storage['swap'] = [
      'style' => $style,
      'modifiers' => $modifiers,
    ];
    return $this;
  }

  /**
   * Creates a `data-hx-swap-oob` attribute.
   *
   * This attribute is used in the markup of the returned response. It
   * specifies that some content in a response should be swapped into the DOM
   * somewhere other than the target, that is “Out of Band”. This allows you to
   * piggyback updates to other elements on a response.
   *
   * @param true|string $value
   *   Either true, a swap strategy, or strategy:CSS-selector.
   *
   * @return static
   *   Returns this object to allow chaining methods.
   *
   * @see https://htmx.org/attributes/hx-swap-oob/
   */
  public function swapOob(true|HtmxSwapStyle|string $value = TRUE): static {
    return $this->setValue('swap-oob', $value);
  }

  /**
   * Creates a `data-hx-target` attribute.
   *
   * This attribute allows you to target a different element for
   * swapping than the one issuing the AJAX request. There are a variety
   * of target string syntaxes.  See the URL below for details.
   *
   * @param string $target
   *   The target descriptor.
   *
   * @return static
   *   Returns this object to allow chaining methods.
   *
   * @see https://htmx.org/attributes/hx-target/
   */
  public function target(string $target): static {
    return $this->setValue('target', $target);
  }

  /**
   * Creates a `data-hx-vals` attribute.
   *
   * This attribute instructs HTMX to add values to the parameters that will be
   * submitted with an HTMX request.
   *
   * The value of this attribute is a list of name-expression values
   * which will be converted to JSON (JavaScript Object Notation) format.
   *
   * @param array<string, string> $values
   *   The values in an array of 'name' => 'value' pairs.
   *
   * @return static
   *   Returns this object to allow chaining methods.
   *
   * @see https://htmx.org/attributes/hx-vals/
   */
  public function vals(array $values): static {
    return $this->setValue('vals', $values);
  }

}
