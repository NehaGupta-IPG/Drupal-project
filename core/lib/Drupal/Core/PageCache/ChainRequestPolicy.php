<?php

namespace Drupal\Core\PageCache;

use Symfony\Component\HttpFoundation\Request;

/**
 * Implements a compound request policy.
 *
 * When evaluating the compound policy, all of the contained rules are applied
 * to the request. The overall result is computed according to the following
 * rules:
 *
 * <ol>
 *   <li>Returns static::DENY if any of the rules evaluated to static::DENY</li>
 *   <li>Returns static::ALLOW if at least one of the rules evaluated to
 *       static::ALLOW and none to static::DENY</li>
 *   <li>Otherwise returns NULL</li>
 * </ol>
 *
 * @phpstan-ignore class.implementsDeprecatedInterface
 */
class ChainRequestPolicy implements ChainRequestPolicyInterface {

  /**
   * Constructs a ChainRequestPolicy object.
   *
   * @param \Traversable<\Drupal\Core\PageCache\RequestPolicyInterface> $rules
   *   The request policies to evaluate.
   */
  public function __construct(
    protected \Traversable $rules,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    $final_result = NULL;

    foreach ($this->rules as $rule) {
      $result = $rule->check($request);
      if ($result === static::DENY) {
        return $result;
      }
      elseif ($result === static::ALLOW) {
        $final_result = $result;
      }
      elseif (isset($result)) {
        throw new \UnexpectedValueException('Return value of RequestPolicyInterface::check() must be one of RequestPolicyInterface::ALLOW, RequestPolicyInterface::DENY or NULL');
      }
    }

    return $final_result;
  }

}
