// cSpell:disable
const cases = [
  {
    label: 'no question mark at all',
    query: 'internal:/node',
    expected: {},
  },
  {
    label: 'a full URL without any query parameters',
    query: 'https://example.com/search',
    expected: {},
  },
  {
    label: 'a question mark with nothing after it',
    query: 'internal:/node?',
    expected: { '': '' },
  },
  {
    label: 'a single parameter',
    query: '?foo=bar',
    expected: { foo: 'bar' },
  },
  {
    label: 'multiple parameters',
    query: '?foo=bar&baz=qux',
    expected: { foo: 'bar', baz: 'qux' },
  },
  {
    label: 'a parameter without a value',
    query: '?foo',
    expected: { foo: '' },
  },
  {
    label: 'the q path argument is ignored',
    query: '?q=node/1&foo=bar',
    expected: { foo: 'bar' },
  },
  {
    label: 'a full URL with query parameters',
    query: 'https://example.com/search?foo=bar',
    expected: { foo: 'bar' },
  },
];
// cSpell:enable

module.exports = {
  '@tags': ['core'],
  before(browser) {
    browser.drupalInstall().drupalInstallModule('views_test_data', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Drupal.Views.parseQueryString': (browser) => {
    browser.drupalRelativeURL('/views_test_data_parse_query_string_page');
    cases.forEach(({ label, query, expected }) => {
      browser.execute(
        // eslint-disable-next-line func-names, prefer-arrow-callback
        function (input) {
          return Drupal.Views.parseQueryString(input);
        },
        [query],
        (result) => {
          browser.assert.deepEqual(result.value, expected, label);
        },
      );
    });
  },
};
