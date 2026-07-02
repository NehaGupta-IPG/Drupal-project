module.exports = {
  '@tags': ['treegrid_reorder'],
  before(browser) {
    browser.drupalInstall({
      setupFile:
        'core/modules/treegrid_reorder/tests/src/Nightwatch/Tests/TestSiteTreegridReorderInstallTestScript.php',
    });
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'computeIntent splits a row into before, child and after zones': (
    browser,
  ) => {
    browser
      .drupalRelativeURL('/treegrid-reorder-test')
      .waitForElementVisible(
        'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
      )
      .execute(
        // eslint-disable-next-line func-names, prefer-arrow-callback
        function () {
          const { computeIntent } = Drupal.treegridReorder;
          // A synthetic row pinned to the viewport top with a height of 100
          // makes clientY equal the percentage into the row, so the 30/70
          // zone boundaries can be probed exactly.
          const row = document.createElement('div');
          row.style.cssText =
            'position:fixed;top:0;left:0;width:100px;height:100px;';
          document.body.appendChild(row);
          const kinds = [0, 29, 30, 50, 70, 71, 99]
            .map((y) => computeIntent(row, y).kind)
            .join(',');
          const targets = [0, 50, 99].every(
            (y) => computeIntent(row, y).target === row,
          );
          row.remove();
          return { kinds, targets };
        },
        [],
        (result) => {
          browser.assert.equal(
            result.value.kinds,
            'before,before,child,child,child,after,after',
            'The top 30% aims before, the middle aims child, the bottom 30% aims after, with the boundaries in the child zone.',
          );
          browser.assert.ok(
            result.value.targets,
            'Every intent carries the probed row as its target.',
          );
        },
      );
  },
  'sameIntent compares kind and target, and treats null as only equal to null':
    (browser) => {
      browser
        .drupalRelativeURL('/treegrid-reorder-test')
        .waitForElementVisible(
          'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
        )
        .execute(
          // eslint-disable-next-line func-names, prefer-arrow-callback
          function () {
            const { sameIntent } = Drupal.treegridReorder;
            const rowA = document.createElement('div');
            const rowB = document.createElement('div');
            return [
              sameIntent(null, null) === true,
              sameIntent({ kind: 'before', target: rowA }, null) === false,
              sameIntent(null, { kind: 'before', target: rowA }) === false,
              sameIntent(
                { kind: 'child', target: rowA },
                { kind: 'child', target: rowA },
              ) === true,
              sameIntent(
                { kind: 'child', target: rowA },
                { kind: 'after', target: rowA },
              ) === false,
              sameIntent(
                { kind: 'child', target: rowA },
                { kind: 'child', target: rowB },
              ) === false,
            ].every(Boolean);
          },
          [],
          (result) => {
            browser.assert.ok(
              result.value,
              'sameIntent matches only intents with the same kind and target.',
            );
          },
        );
    },
};
