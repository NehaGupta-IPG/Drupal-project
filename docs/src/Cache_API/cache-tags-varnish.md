Cache tags + Varnish
====================

[Varnish Cache](https://www.varnish-cache.org/) is a web application accelerator also known as a caching HTTP reverse proxy. Varnish is used on thousands of Drupal sites to speed up page load performance by a factor of 10-1000x, and it can be used with cache tags to make cache invalidation easy.

This page is based on the [official Varnish tutorial for Drupal](https://www.varnish-software.com/developers/tutorials/configuring-varnish-drupal/).

[](#s-learn-more-about-drupal-cache-tags "Permalink to this headline")Learn more about Drupal Cache Tags
--------------------------------------------------------------------------------------------------------

Caching and reverse proxies are advanced and complex topics. Before you continue, make sure you understand [how cache tags work in Drupal core](https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags). See also the [Varnish Purging and banning documentation](https://varnish-cache.org/docs/trunk/users-guide/purging.html).

Cache-Tags headers could be very long and trigger errors (503) on Apache, Nginx or Varnish. If so, adjust this service parameters, see <https://www.drupal.org/project/drupal/issues/2844620>[](#s-conceptual-summary-for-tag-based-varnish-cache-invalidation "Permalink to this headline")Conceptual summary for tag-based Varnish cache invalidation
----------------------------------------------------------------------------------------------------------------------------------------------------------

Drupal cache tags are used to invalidate pages from Varnish cache. In a nutshell, the concept is as follows:

- We use the [Purge](https://www.drupal.org/project/purge) module together with a Purger module. This documentation uses the [Generic HTTP Purger](https://www.drupal.org/project/purge_purger_http) module but there is also a [Varnish Purger.](https://drupal.org/project/varnish_purge)
- The Purger module adds Drupal cache tags in a custom HTTP Response header when Drupal sends a response to Varnish. See screenshot below.
- When Varnish caches the page, it adds the cache tags as metadata to the cached object.
- When content changes in Drupal, the Purger module sends a [BAN ](https://varnish-cache.org/docs/trunk/users-guide/purging.html)to the Varnish server, indicating the cache tags that need to be invalidated. These BANs are usually sent with Cron but they can also be sent using Drush when configuring all of this.

Example of the response headers (inspected with Firefox developer tools) can be seen in the screenshot below:

![Purge-Cache-Tags in HTTP repsonse header](/files/purge-cahce-tags-in-response-headers.png)  
Purge-Cache-Tags in HTTP response header, added by the Generic HTTP Purger

[](#s-difference-between-the-generic-http-purger-and-varnish-purger "Permalink to this headline")Difference between the Generic HTTP Purger and Varnish Purger
--------------------------------------------------------------------------------------------------------------------------------------------------------------

- The key gotcha is that these two modules use different response headers as indicated in the table below. The rest of this documentation uses the Generic HTTP Purger module, so we use the Purge-Cache-Tags throughout this documentation.

<table border="0" cellpadding="1" cellspacing="1"><thead><tr><th scope="col">Project</th><th scope="col">Module</th><th scope="col">Header</th></tr><tr><td>[Varnish Purger](https://drupal.org/project/varnish_purge)</td><td>Varnish Purger Tags (`varnish_purge_tags`), provides a "zero config" setup with preconfigure queue, queuers, processors and vcl file. Beware, its "Purge Late runtime processor" is a bit aggressive, and its setup still needs a good understanding of your architecture (specially reverse\_proxy)</td><td>`Cache-Tags`</td></tr><tr><td>[Generic HTTP Purger](https://www.drupal.org/project/purge_purger_http)</td><td>Generic HTTP Tags Header (`purge_purger_http_tagsheader`)</td><td>`Purge-Cache-Tags`</td></tr></thead></table>

[](#s-varnish-vcl-configuration "Permalink to this headline")Varnish VCL configuration
--------------------------------------------------------------------------------------

Varnish configuration is located in the default.vcl file, [Varnish documentation has a complete example of a Drupal-specific VCL configuration](https://www.varnish-software.com/developers/tutorials/configuring-varnish-drupal/).

The VCL file has many important Drupal specific configurations. Essential part for cache invalidation is in vcl\_recv:


```php
sub vcl_recv {
    ...
    # Ban logic to remove multiple objects from the cache at once. Tailored to Drupal's cache invalidation mechanism
    if(req.method == "BAN") {
        ... 
        if (req.http.Purge-Cache-Tags) {
            ban("obj.http.Purge-Cache-Tags ~ " + req.http.Purge-Cache-Tags);
        }
    }
    ...
}

```

The operator ~ means regular expression. A plain-English translation of this regular expression is:

- Search cached objects and look at their Purge-Cache-Tags metadata (obj.http.Purge-Cache-Tags)
- If the cached objects *contain* the cache tag indicated in the BAN-request's Purge-Cache-Tags (req.http.Purge-Cache-Tags), ban the object from Varnish cache.

### [](#s-possible-issue "Permalink to this headline")Possible issue

If the length of cache tags exceed Varnish’s default of 8192 bytes, you can see HTTP 503 (Backend Fetch Failed) errors in the browser. The errors might display similar to the following: *“Error 503 Backend fetch failed. Backend fetch failed”.*

To resolve this issue, increase the default value of the `http_resp_hdr_len` parameter in your Varnish configuration file. The `http_resp_hdr_len` parameter specifies the max header length *within* the total default response size of 32768 bytes, so you may also increase this parameter (`http_resp_size`) of the same amount.

This parameters should be added in the service definitions, e.g.:


```php
# Live adjustments
varnishadm param.show http_resp_hdr_len
varnishadm param.set http_resp_hdr_len XXXXX 

# For permanent changes, edit the service conf file and add the following params
-p http_resp_hdr_len=XXXXX -p http_resp_size=YYYYY
```

[](#s-configuration-details-of-the-generic-http-purger "Permalink to this headline")Configuration details of the Generic HTTP Purger
------------------------------------------------------------------------------------------------------------------------------------

As mentioned above, the Generic HTTP Purger is responsible for sending the BAN requests to the Varnish server. It needs to be added as a Purger at the Purge configuration page (`admin/config/development/performance/purge`).

Once you have added the Generic HTTP Purger, it needs to be configured.

- The screenshots below show that the name of this Generic HTTP Purger is "Varnish - Tag".
- The Request section contains the parameters where the BAN requests are sent (Varnish server details)
- The Headers section contain the configuration that the cache tags will be included in the Purge-Cache-Tags header.

![Varnish server details](/files/generic-http-purger-configuration-request.png)  
Varnish server details

![BAN header configuration](/files/generic-http-purger-configuration-headers.png)  
BAN header configuration

[](#s-schedule-the-bans-with-cron "Permalink to this headline")Schedule the BANs with Cron
------------------------------------------------------------------------------------------

The bans are not sent on runtime when content changes on your site for performance reasons. You probably want to schedule the Purger cron jobs to run once per minute (or whatever frequency you want). If you're not familiar with the [Ultimate Cron](https://www.drupal.org/project/ultimate_cron) module already, it's one of the easiest ways to do this.

[](#s-other-ban-processors "Permalink to this headline")Other BAN processors
----------------------------------------------------------------------------

Purge uses a queue system to store cache invalidation that is executed by processors. The previous chapter explains how to setup a cron one. `drush p:queue-work` can be used to process BANs from the queue when configuring and testing cache invalidation.

Be aware that *Late runtime processor* **that run on every page response** could generate a heavy load on front webserver.

[](#s-other-purgers "Permalink to this headline")Other Purgers
--------------------------------------------------------------

This documentation page is about Varnish but the concepts apply to other cache mechanisms as well. The following Purger modules are available:

- **[`acquia_purge`](https://www.drupal.org/project/acquia_purge)**
- **[`akamai`](https://www.drupal.org/project/akamai)**
- **[`cloudflare`](https://www.drupal.org/project/cloudflare)**
- **[`cloudfront_purger`](https://www.drupal.org/project/cloudfront_purger)**
- **[`fastlypurger`](https://www.drupal.org/project/fastly)**
- **[`keycdn`](https://www.drupal.org/project/keycdn)**

[](#s-hints-for-varnish-configuration "Permalink to this headline")Hints for Varnish configuration
--------------------------------------------------------------------------------------------------

You might want to modify the excellent VCL template linked above slightly.

### [](#s-x-forwarded-for-header "Permalink to this headline")X-Forwarded-For header

Because your Drupal server is now behind the Varnish server, your Drupal logs will indicate the Varnish server's IP address. If you want Drupal logs to have the client's IP address, you can achieve this by configuring Varnish to add X-Forwarded-For header to the request it sends to the Drupal server.


```php
sub vcl_recv {
    # Add an X-Forwarded-For header with the client IP address.
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
        }
        else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }
    ...
}

```

### [](#s-removing-the-purge-cache-tags-header-from-the-response-sent-to-the-browser-and-adding-hit-miss-header "Permalink to this headline")Removing the Purge-Cache-Tags header from the response sent to the browser and adding HIT / MISS header

The Purge-Cache-Tags headers are very useful for debugging purposes so that you can see the cache tags using for example Firefox Developer Tools. Once you're happy with your working Varnish configurations, you might want to remove the Purge-Cache-Tags from the response Varnish sends to your browser because the browser does not need to know about it.

Another very useful hint is to add X-Varnish-Cache header which indicates whether the response was delivered from Varnish cache (HIT) or if it was generated by Drupal (MISS).


```php
sub vcl_deliver {
    # Cleanup of headers
    unset resp.http.X-url;
    unset resp.http.X-host;
    unset req.http.X-Static-File;
    unset resp.http.Purge-Cache-Tags;

    # Add HIT / MISS to X-Varnish-Cache header
    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT";
    }
    else {
        set resp.http.X-Varnish-Cache = "MISS";
    }
}

```

### [](#s-removing-esi-configurations "Permalink to this headline")Removing ESI configurations

The default.vcl linked above contains sections for [ESI (Edge Side Includes)](https://www.w3.org/TR/esi-lang/) in `sub vcl_recv` and `sub vcl_backend_response`. Drupal core does not have ESI support at the time this documentation page was updated (Drupal 10.1). If you don't use ESI, it is recommended to remove the ESI-specific configurations from your VCL configuration.

[](#s-views-custom-cache-tag-and-the-problematic-node-list-cache-tag "Permalink to this headline")Views Custom Cache Tag and the problematic node\_list cache tag
-----------------------------------------------------------------------------------------------------------------------------------------------------------------

At the moment this documentation was updated (Drupal 10.1), all Views that render nodes include a generic 'node\_list' cache tag. Whenever ANY node is created, updated or deleted, this cache tag is invalidated. This essentially means that EVERY node creation, update or deletion invalidates EVERY view that you have on your site. Depending on your site, this might mean that the caches are cold almost all the time.

- [Views Custom Cache Tag](https://www.drupal.org/project/views_custom_cache_tag) comes to rescue.
- [External tutorial on how to use the module](http://karimboudjema.com/en/drupal/20190909/node-list-cache-tag-pitfall-drupal-8-views).

[](#s-different-cache-lifetime-for-varnish-and-browser-cache-using-s-maxage-cache-control-header "Permalink to this headline")Different cache lifetime for Varnish and browser cache using s-maxage Cache-Control header
------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

One of the biggest advantages of the tag based cache invalidation concept is that you can use very long (e.g. 1 year) cache lifetimes and explicitly invalidate the cached objects from Varnish with cache tags when content changes.

Drupal core has only one configuration option for the minimum cache lifetime at admin/config/development/performance. This sets the Cache-Control header which tells all cache mechanisms (browsers, Varnish, CDN, ...) how long they should keep the object in their cache. The problem with this is that while you can invalidate the Varnish cache when you want, invalidating the browser caches of your site's users is not in your control.

In other words, if you use a long, 1 year cache lifetime:

- the page will be cached by Varnish for 1 year (or until you ban it with cache tags)
- the page will be cached in browser cache for 1 year (or until the user manually refreshes the page)

When reverse proxy is being used, it is common that you would like to:

- cache the pages in Varnish for a long time (e.g. 1 year, or until you ban it with cache tags)
- have a short lifetime for browser cache so that users will see fresh content if they navigate back to the page that has been updated so that they don't need to manually refresh the page to check if has updated.

This can be achieved by using the s-maxage Cache-Control header.

- Use max-age=60 to tell the browser it should not cache the page for more than 60 seconds
- Use s-maxage=31536000 to tell Varnish it should keep the page in the cache for 1 year (or whatever is the desired duration for your site).

You can use the [HTTP Cache Control](https://www.drupal.org/project/http_cache_control) module to configure the s-maxage lifetime. It also allows you to configure different lifetimes for 404 (page not found), 500 (internal server error) and 302 (redirect) responses.

 