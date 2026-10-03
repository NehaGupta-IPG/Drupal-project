"""Generate the FreeUnit config that mirrors Drupal's .htaccess.

The rules follow the root .htaccess (core/assets/scaffold/files/htaccess),
so the tests exercising it pass on FreeUnit as they do on Apache.

Regenerate .gitlab-ci/unit.json after changing this file:
  python3 .gitlab-ci/scripts/unit-config.py /subdirectory > .gitlab-ci/unit.json
"""
import json, sys

def conf(base, docroot='/var/www/html', access_log=True):
    B = base.rstrip('/')
    R = B.replace('.', r'\.')  # base path as a regex fragment
    share = docroot + '$uri'
    files_match = (
        r'~(^|/)([^/]*\.(engine|inc|install|make|module|profile|po|sh|[^/]*sql|theme|twig|tpl(\.php)?|xtmpl|yml)'
        r'(~|\.sw[op]|\.bak|\.orig|\.save)?|\.(?!well-known)[^/]*|Entries[^/]*|Repository|Root|Tag|Template'
        r'|composer\.(json|lock)|web\.config|yarn\.lock|package(-lock)?\.json|#[^/]*#'
        r'|[^/]*\.php(~|\.sw[op]|\.bak|\.orig|\.save))$'
    )
    routes = {'main': [], 'front': [], 'static': []}
    m = routes['main']
    if B:
        m.append({'match': {'uri': ['!' + B, '!' + B + '/*']}, 'action': {'return': 404}})
    m += [
        # RewriteRule "/\.|^\.(?!well-known/)" - [F]
        {'match': {'uri': [r'~^%s/(\.(?!well-known/)|.*/\.)' % R]}, 'action': {'return': 403}},
        # <FilesMatch> applies to the file Apache serves: a path with no file
        # behind it is rewritten to index.php first, so Drupal answers. No
        # system/ directory exists in the docroot, so private file paths such
        # as /system/files/module/system/system.info.yml always reach Drupal.
        {'match': {'uri': B + '/system/files/*'}, 'action': {'pass': 'routes/front'}},
        # <FilesMatch>: protect files and directories from prying eyes.
        {'match': {'uri': files_match}, 'action': {'return': 403}},
        # Directories carrying their own "Require all denied" .htaccess.
        {'match': {'uri': [B + '/vendor', B + '/vendor/*', r'~^%s/sites/[^/]+/([^/]+/)?(private|temp)/' % R,
                           B + '/core/profiles/demo_umami/modules/demo_umami_content/default_content/*']},
         'action': {'return': 403}},
        # PHP entry points that .htaccess allows to execute.
        {'match': {'uri': [B + '/index.php', B + '/index.php/*', B + '/update.php', B + '/update.php/*',
                           r'~^%s/core/[^/]*\.php$' % R,
                           r'~^%s/core/modules/system/tests/https?\.php(/|$)' % R,
                           r'~^%s/core/modules/system/tests/modules/legacy_front_controller_test/test_index\.php(/|$)' % R]},
         'action': {'pass': 'applications/drupal'}},
        # Deny any other PHP file in a subdirectory, and the root autoload.php.
        {'match': {'uri': r'~^%s/(.+/.*|autoload)\.php($|/)' % R}, 'action': {'return': 403}},
        # Root-level *.php that does not exist (e.g. a path alias "test.php").
        {'match': {'uri': r'~^%s/[^/]*\.php($|/)' % R}, 'action': {'pass': 'routes/front'}},
    ]
    for ext, ctype in (('css', 'text/css'), ('js', 'text/javascript')):
        uri = r'~/%s_[a-zA-Z0-9_-]+\.%s$' % (ext, ext)
        gz = 'agg_%s_gz' % ext
        m.append({'match': {'uri': uri, 'headers': {'Accept-Encoding': '*br*'}},
                  'action': {'share': share + '.br', 'response_headers': {
                      'Content-Type': ctype, 'Content-Encoding': 'br', 'Vary': 'Accept-Encoding',
                      'X-Content-Type-Options': 'nosniff'},
                      'fallback': {'pass': 'routes/' + gz}}})
        m.append({'match': {'uri': uri}, 'action': {'pass': 'routes/' + gz}})
        routes[gz] = [
            {'match': {'headers': {'Accept-Encoding': '*gzip*'}},
             'action': {'share': share + '.gz', 'response_headers': {
                 'Content-Type': ctype, 'Content-Encoding': 'gzip', 'Vary': 'Accept-Encoding',
                 'X-Content-Type-Options': 'nosniff'},
                 'fallback': {'pass': 'routes/static'}}},
            {'action': {'pass': 'routes/static'}},
        ]
    # AddEncoding gzip svgz
    m.append({'match': {'uri': '*.svgz'},
              'action': {'share': share, 'response_headers': {
                  'Content-Type': 'image/svg+xml', 'Content-Encoding': 'gzip',
                  'X-Content-Type-Options': 'nosniff'},
                  'fallback': {'pass': 'routes/front'}}})
    m.append({'action': {'pass': 'routes/static'}})
    routes['static'] = [{'action': {
        'share': share,
        # Never serve PHP source, even if a route above is changed.
        'types': ['!application/x-httpd-php'],
        # mod_expires "access plus 1 year" and Header set X-Content-Type-Options.
        'response_headers': {'Cache-Control': 'max-age=31536000', 'X-Content-Type-Options': 'nosniff'},
        'fallback': {'pass': 'routes/front'}}}]
    # RewriteRule ^ index.php [L]: clean URLs. Rewriting only the path keeps
    # REQUEST_URI, and SCRIPT_NAME becomes <base>/index.php.
    routes['front'] = [{'action': {'rewrite': B + '/index.php', 'pass': 'applications/drupal'}}]
    c = {
        'settings': {'http': {
            'max_body_size': 1073741824,
            # mod_php accepts chunked request bodies; Unit answers 411 without this.
            'chunked_transform': True,
            'static': {'mime_types': {'text/javascript': ['.js', '.mjs'], 'image/svg+xml': ['.svg', '.svgz']}},
        }},
        'listeners': {'*:80': {'pass': 'routes/main'}},
        'routes': routes,
        'applications': {'drupal': {
            'type': 'php',
            # No "script": Unit realpath()s it and rejects a target outside
            # "root", which a symlinked docroot is. Dynamic mode also splits
            # PATH_INFO at ".php/".
            'root': docroot,
            'index': 'index.php',
            'user': 'www-data',
            'group': 'www-data',
            'processes': {'max': 64, 'spare': 5, 'idle_timeout': 60},
            'options': {
                'admin': {
                    # Unit implements fastcgi_finish_request(), so Symfony
                    # would complete the response before kernel.terminate
                    # runs. mod_php sends the last chunk only after it, and
                    # tests rely on that unless they opt in to
                    # drupal.test_wait_terminate.
                    'disable_functions': 'fastcgi_finish_request',
                },
                'user': {
                    # php_value assert.active 0 from .htaccess (mod_php only).
                    'assert.active': '0',
                },
            },
        }},
    }
    if access_log:
        c['access_log'] = {'path': '/var/log/unit-access.log',
                           'format': '$remote_addr - - [$time_local] "$request_line" $status $body_bytes_sent $request_time'}
    return c

if __name__ == '__main__':
    print(json.dumps(conf(sys.argv[1]), indent=2))
