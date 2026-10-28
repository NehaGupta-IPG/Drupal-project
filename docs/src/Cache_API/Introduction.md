# Introduction

The Cache API is used to store data that takes a long time to compute. Caching can either be permanent or valid only for a certain time span, and the cache can contain any type of data.

To use the Cache API:

- Request a cache object through [\\Drupal::cache](https://api.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main) or by injecting a cache service.
- Define a Cache ID (cid) value for your data. A cid is a string, which must contain enough information to uniquely identify the data. For example, if your data contains translated strings, then your cid value must include the interface text language selected for page.
- Call the get() method to attempt a cache read, to see if the cache already contains your data.
- If your data is not already in the cache, compute it and add it to the cache using the set() method. The third argument of set() can be used to control the lifetime of your cache item.

Example:

```
$cid = 'my_module_example:' . \Drupal::languageManager()->getCurrentLanguage()
$data = NULL;
if ($cache = \Drupal::cache()->get($cid)) {
  $data = $cache->data;
}
else {
  $data = my_module_complicated_calculation();
  \Drupal::cache()->set($cid, $data);
}
```

Note the use of `$data` and `$cache->data` in the above example. Calls to [\\Drupal::cache](https://api.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main)()->get() return a record that contains the information stored by [\\Drupal::cache](https://api.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main)()-set() in the data property as well as additional meta information about the cached data. In order to make use of the cached data you can access it via $cache-&gt;data.

