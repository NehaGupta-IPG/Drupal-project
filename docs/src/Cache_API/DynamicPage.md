
## Dynamic Page Cache

Comprehensive use of cache contexts across Drupal allows Drupal to ship with its [Dynamic Page Cache](https://www.drupal.org/documentation/modules/smart_cache) enabled by default. (Previously known as 'Smart Cache')

## Internal Page Cache

Note the [Internal Page Cache](https://www.drupal.org/docs/8/administering-drupal-8-site/internal-page-cache) assumes that all pages served to anonymous users will be identical, regardless of the implementation of cache contexts. If you want to use cache contexts to vary the content served to anonymous users, this module must be disabled, and the performance impact that entails incurred.

