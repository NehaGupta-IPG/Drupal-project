import { test, expect, drupalInstallModule } from '../drupal-fixtures';

// cSpell:disable
const machineNameTestArray = [
  {
    machineName: 'Bob',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'bob',
  },
  {
    machineName: 'Äwesome',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'awesome',
  },
  {
    machineName: 'B?!"@\\/-ob@e',
    replacePattern: '[^a-zA-Z0-9_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'b_ob_e',
  },
  {
    machineName: 'Bob@e\\0',
    replacePattern: '[^a-zA-Z0-9_.~@]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'bob@e_0',
  },
  {
    machineName: 'Bobby',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'bobby',
  },
  {
    machineName: 'ǍǎǏ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'aai',
  },
  {
    machineName: 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'aaaaaaaeceeeeiiii',
  },
  {
    machineName: 'ÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞß',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'dnoooooxouuuuuthss',
  },
  {
    machineName: 'àáâãäåæçèéêëìíîï',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'aaaaaaaeceeeeiiii',
  },
  {
    machineName: 'ðñòóôõö÷øùúûüýþÿ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'dnooooo_ouuuuythy',
  },
  {
    machineName: 'ĀāĂăĄąĆćĈĉĊċČčĎď',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'aaaaaaccccccccdd',
  },
  {
    machineName: 'ĐđĒēĔĕĖėĘęĚěĜĝĞğ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'ddeeeeeeeeeegggg',
  },
  {
    machineName: 'ĠġĢģĤĥĦħĨĩĪīĬĭĮį',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'gggghhhhiiiiiiii',
  },
  {
    machineName: 'İıĲĳĴĵĶķĸĹĺĻļĽľĿ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'iiijijjjkkklllllll',
  },
  {
    machineName: 'ŀŁłŃńŅņŇňŉŊŋŌōŎŏ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'lllnnnnnn_nngngoooo',
  },
  {
    machineName: 'ŐőŒœŔŕŖŗŘřŚśŜŝŞş',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'oooeoerrrrrrssssss',
  },
  {
    machineName: 'ŠšŢţŤťŦŧŨũŪūŬŭŮů',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'ssttttttuuuuuuuu',
  },
  {
    machineName: 'ŰűŲųŴŵŶŷŸŹźŻżŽž',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'uuuuwwyyyzzzzzz',
  },
  {
    machineName: 'ǐǑǒǓǔǕǖǗǘǙǚǛǜǝǞǟ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'ioouuuuuuuuuu_aa',
  },
  {
    machineName: 'ǠǡǢǣǤǥǦǧǨǩǪǫǬǭǮǯ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'aaaeaeggggkkoooozhzh',
  },
  {
    machineName: 'ǰǱǲǳǴǵǶǷǸǹǺǻǼǽǾǿ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'jdzddzgghvwnnaaaeaeoo',
  },
  {
    machineName: 'ȀȁȂȃȄȅȆȇȈȉȊȋȌȍȎȏ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'aaaaeeeeiiiioooo',
  },
  {
    machineName: 'ȐȑȒȓȔȕȖȗȘșȚțȜȝȞȟ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'rrrruuuussttyyhh',
  },
  {
    machineName: 'ȠȡȢȣȤȥȦȧȨȩȪȫȬȭȮȯ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'ndououzzaaeeoooooo',
  },
  {
    machineName: 'ȰȱȲȳȴȵȶȷȸȹȺȻȼȽȾȿ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'ooyylntjdbqpacclts',
  },
  {
    machineName: 'ɀɁɂɃɄɅɆɇɈɉɊɋɌɍɎɏ',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 64,
    expected: 'z_buveejjqqrryy',
  },
  {
    machineName: 'This is the test for max length',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 20,
    expected: 'this_is_the_test_for',
  },
  {
    machineName: 'Ma@Chi!~',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '_',
    maxlength: 5,
    expected: 'ma_ch',
  },
  {
    machineName: 'Test for custom replace character',
    replacePattern: '[^a-zA-Z0-9-_.~]+',
    replaceChar: '-',
    maxlength: 64,
    expected: 'test-for-custom-replace-character',
  },
  {
    machineName: 'Test for unusual replace pattern',
    replacePattern: '([^a-z0-9_]+)|(^custom$)',
    replaceChar: '_',
    maxlength: 64,
    expected: 'test_for_unusual_replace_pattern',
  },
  {
    machineName: 'custom',
    replacePattern: '([^a-z0-9_]+)|(^custom$)',
    replaceChar: '_',
    maxlength: 64,
    expected: '_',
  },
];
// cSpell:enable

test.describe('Machine name transliteration', { tag: ['@core'] }, () => {
  test('Machine name generation test', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'form_test', true);
    await page.goto('/form-test/machine-name');

    for (const iteration of machineNameTestArray) {
      const result = await page.evaluate((obj) => {
        return Drupal.behaviors.machineName.transliterate(obj.machineName, {
          replace_pattern: obj.replacePattern,
          replace: obj.replaceChar,
          maxlength: obj.maxlength,
        });
      }, iteration);
      expect(result).toBe(iteration.expected);
    }
  });
});
