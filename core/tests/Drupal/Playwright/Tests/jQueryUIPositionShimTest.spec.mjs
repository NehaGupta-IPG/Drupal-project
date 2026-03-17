import { test, expect, drupalInstallModule } from '../drupal-fixtures';

/* cSpell:disable */
const testScenarios = {
  window: {
    centerbottomcenterbottom: {
      at: 'center bottom',
      my: 'center bottom',
      x: 38.5,
      y: 77,
    },
    centerbottomcentercenter: {
      at: 'center center',
      my: 'center bottom',
      x: 38.5,
      y: 77,
    },
    centerbottomcentertop: {
      at: 'center top',
      my: 'center bottom',
      x: 38.5,
      y: -76.984375,
    },
    centerbottomleftbottom: {
      at: 'left bottom',
      my: 'center bottom',
      x: -38.5,
      y: 77,
    },
    centerbottomleftcenter: {
      at: 'left center',
      my: 'center bottom',
      x: -38.5,
      y: 77,
    },
    centerbottomlefttop: {
      at: 'left top',
      my: 'center bottom',
      x: -38.5,
      y: -76.984375,
    },
    centerbottomrightbottom: {
      at: 'right bottom',
      my: 'center bottom',
      x: 38.5,
      y: 77,
    },
    centerbottomrightcenter: {
      at: 'right center',
      my: 'center bottom',
      x: 38.5,
      y: 77,
    },
    centerbottomrightminus80bottomminus40: {
      at: 'right-80 bottom-40',
      my: 'center bottom',
      x: 118.5,
      y: 117,
    },
    centerbottomrighttop: {
      at: 'right top',
      my: 'center bottom',
      x: 38.5,
      y: -76.984375,
    },
    centerminus40topplus40leftplus20ptop: {
      at: 'left+20 top',
      my: 'center-40 top+40',
      x: -58.5,
      y: 40,
    },
    centerplus10perpbottomcenterminus10pertop: {
      at: 'center+110 top',
      my: 'center+150 bottom',
      x: -221.5,
      y: -76.984375,
    },
    centerplus20ptopplus20pcenterbottom: {
      at: 'center bottom',
      my: 'center+100 top-200',
      x: -61.5,
      y: 200,
    },
    centerplus40topminus15pcentercenterplus40: {
      at: 'center center+40',
      my: 'center+40 top+15',
      x: -1.5,
      y: -55,
    },
    centerplus80bottomminus90leftbottom: {
      at: 'left bottom',
      my: 'center+80 bottom-90',
      x: 41.5,
      y: 167,
    },
    centertopcenterbottom: {
      at: 'center bottom',
      my: 'center top',
      x: 38.5,
      y: 0,
    },
    centertopcentercenter: {
      at: 'center center',
      my: 'center top',
      x: 38.5,
      y: 0,
    },
    centertopcenterplus20ptopplus20p: {
      at: 'center+70 top+60',
      my: 'center top',
      x: -31.5,
      y: 60,
    },
    centertopcentertop: { at: 'center top', my: 'center top', x: 38.5, y: 0 },
    centertopleftbottom: {
      at: 'left bottom',
      my: 'center top',
      x: -38.5,
      y: 0,
    },
    centertopleftcenter: {
      at: 'left center',
      my: 'center top',
      x: -38.5,
      y: 0,
    },
    centertoplefttop: { at: 'left top', my: 'center top', x: -38.5, y: 0 },
    centertoprightbottom: {
      at: 'right bottom',
      my: 'center top',
      x: 38.5,
      y: 0,
    },
    centertoprightcenter: {
      at: 'right center',
      my: 'center top',
      x: 38.5,
      y: 0,
    },
    centertoprighttop: { at: 'right top', my: 'center top', x: 38.5, y: 0 },
    leftbottomcenterbottom: {
      at: 'center bottom',
      my: 'left bottom',
      x: 0,
      y: 77,
    },
    leftbottomcentercenter: {
      at: 'center center',
      my: 'left bottom',
      x: 0,
      y: 77,
    },
    leftbottomcentertop: {
      at: 'center top',
      my: 'left bottom',
      x: 0,
      y: -76.984375,
    },
    leftbottomleftbottom: { at: 'left bottom', my: 'left bottom', x: 0, y: 77 },
    leftbottomleftcenter: { at: 'left center', my: 'left bottom', x: 0, y: 77 },
    leftbottomlefttop: {
      at: 'left top',
      my: 'left bottom',
      x: 0,
      y: -76.984375,
    },
    leftbottomrightbottom: {
      at: 'right bottom',
      my: 'left bottom',
      x: 0,
      y: 77,
    },
    leftbottomrightcenter: {
      at: 'right center',
      my: 'left bottom',
      x: 0,
      y: 77,
    },
    leftbottomrighttop: {
      at: 'right top',
      my: 'left bottom',
      x: 0,
      y: -76.984375,
    },
    leftcentercenterbottom: {
      at: 'center bottom',
      my: 'left center',
      x: 0,
      y: 38.5,
    },
    leftcentercentercenter: {
      at: 'center center',
      my: 'left center',
      x: 0,
      y: 38.5,
    },
    leftcentercentertop: {
      at: 'center top',
      my: 'left center',
      x: 0,
      y: -38.484375,
    },
    leftcenterleftbottom: {
      at: 'left bottom',
      my: 'left center',
      x: 0,
      y: 38.5,
    },
    leftcenterleftcenter: {
      at: 'left center',
      my: 'left center',
      x: 0,
      y: 38.5,
    },
    leftcenterlefttop: {
      at: 'left top',
      my: 'left center',
      x: 0,
      y: -38.484375,
    },
    leftcenterrightbottom: {
      at: 'right bottom',
      my: 'left center',
      x: 0,
      y: 38.5,
    },
    leftcenterrightcenter: {
      at: 'right center',
      my: 'left center',
      x: 0,
      y: 38.5,
    },
    leftcenterrighttop: {
      at: 'right top',
      my: 'left center',
      x: 0,
      y: -38.484375,
    },
    lefttopcenterbottom: { at: 'center bottom', my: 'left top', x: 0, y: 0 },
    lefttopcentercenter: { at: 'center center', my: 'left top', x: 0, y: 0 },
    lefttopcentertop: { at: 'center top', my: 'left top', x: 0, y: 0 },
    lefttopleftbottom: { at: 'left bottom', my: 'left top', x: 0, y: 0 },
    lefttopleftcenter: { at: 'left center', my: 'left top', x: 0, y: 0 },
    lefttoplefttop: { at: 'left top', my: 'left top', x: 0, y: 0 },
    lefttoprightbottom: { at: 'right bottom', my: 'left top', x: 0, y: 0 },
    lefttoprightcenter: { at: 'right center', my: 'left top', x: 0, y: 0 },
    lefttoprighttop: { at: 'right top', my: 'left top', x: 0, y: 0 },
    rightbottomcenterbottom: {
      at: 'center bottom',
      my: 'right bottom',
      x: 77,
      y: 77,
    },
    rightbottomcentercenter: {
      at: 'center center',
      my: 'right bottom',
      x: 77,
      y: 77,
    },
    rightbottomcentertop: {
      at: 'center top',
      my: 'right bottom',
      x: 77,
      y: -76.984375,
    },
    rightbottomleftbottom: {
      at: 'left bottom',
      my: 'right bottom',
      x: -77,
      y: 77,
    },
    rightbottomleftcenter: {
      at: 'left center',
      my: 'right bottom',
      x: -77,
      y: 77,
    },
    rightbottomlefttop: {
      at: 'left top',
      my: 'right bottom',
      x: -77,
      y: -76.984375,
    },
    rightbottomrightbottom: {
      at: 'right bottom',
      my: 'right bottom',
      x: 77,
      y: 77,
    },
    rightbottomrightcenter: {
      at: 'right center',
      my: 'right bottom',
      x: 77,
      y: 77,
    },
    rightbottomrighttop: {
      at: 'right top',
      my: 'right bottom',
      x: 77,
      y: -76.984375,
    },
    rightcentercenterbottom: {
      at: 'center bottom',
      my: 'right center',
      x: 77,
      y: 38.5,
    },
    rightcentercentercenter: {
      at: 'center center',
      my: 'right center',
      x: 77,
      y: 38.5,
    },
    rightcentercentertop: {
      at: 'center top',
      my: 'right center',
      x: 77,
      y: -38.484375,
    },
    rightcenterleftbottom: {
      at: 'left bottom',
      my: 'right center',
      x: -77,
      y: 38.5,
    },
    rightcenterleftcenter: {
      at: 'left center',
      my: 'right center',
      x: -77,
      y: 38.5,
    },
    rightcenterlefttop: {
      at: 'left top',
      my: 'right center',
      x: -77,
      y: -38.484375,
    },
    rightcenterrightbottom: {
      at: 'right bottom',
      my: 'right center',
      x: 77,
      y: 38.5,
    },
    rightcenterrightcenter: {
      at: 'right center',
      my: 'right center',
      x: 77,
      y: 38.5,
    },
    rightcenterrighttop: {
      at: 'right top',
      my: 'right center',
      x: 77,
      y: -38.484375,
    },
    righttopcenterbottom: { at: 'center bottom', my: 'right top', x: 77, y: 0 },
    righttopcentercenter: { at: 'center center', my: 'right top', x: 77, y: 0 },
    righttopcentertop: { at: 'center top', my: 'right top', x: 77, y: 0 },
    righttopleftbottom: { at: 'left bottom', my: 'right top', x: -77, y: 0 },
    righttopleftcenter: { at: 'left center', my: 'right top', x: -77, y: 0 },
    righttoplefttop: { at: 'left top', my: 'right top', x: -77, y: 0 },
    righttoprightbottom: { at: 'right bottom', my: 'right top', x: 77, y: 0 },
    righttoprightcenter: { at: 'right center', my: 'right top', x: 77, y: 0 },
    righttoprighttop: { at: 'right top', my: 'right top', x: 77, y: 0 },
  },
  selector: {
    centerbottomcenterbottom: {
      at: 'center bottom',
      my: 'center bottom',
      x: 62.5,
      y: 125,
    },
    centerbottomcentercenter: {
      at: 'center center',
      my: 'center bottom',
      x: 62.5,
      y: 24,
    },
    centerbottomcentertop: {
      at: 'center top',
      my: 'center bottom',
      x: 62.5,
      y: -77,
    },
    centerbottomleftbottom: {
      at: 'left bottom',
      my: 'center bottom',
      x: -38.5,
      y: 125,
    },
    centerbottomleftcenter: {
      at: 'left center',
      my: 'center bottom',
      x: -38.5,
      y: 24,
    },
    centerbottomlefttop: {
      at: 'left top',
      my: 'center bottom',
      x: -38.5,
      y: -77,
    },
    centerbottomrightbottom: {
      at: 'right bottom',
      my: 'center bottom',
      x: 163.5,
      y: 125,
    },
    centerbottomrightcenter: {
      at: 'right center',
      my: 'center bottom',
      x: 163.5,
      y: 24,
    },
    centerbottomrightplus40bottomminus40: {
      at: 'right+40 bottom-40',
      my: 'center bottom',
      x: 203.5,
      y: 85,
    },
    centerbottomrighttop: {
      at: 'right top',
      my: 'center bottom',
      x: 163.5,
      y: -77,
    },
    centerminus40topplus40leftminus20ptop: {
      at: 'left-20% top',
      my: 'center-40 top+40',
      x: -118.890625,
      y: 40,
    },
    centerplus10perpbottomcenterminus10pertop: {
      at: 'center-20% top',
      my: 'center+20% bottom',
      x: 37.5,
      y: -77,
    },
    centerplus40bottomminus40leftbottom: {
      at: 'left bottom',
      my: 'center+40 bottom-40',
      x: 1.5,
      y: 85,
    },
    centerplus40topminus15pcentercenterplus40: {
      at: 'center center+40',
      my: 'center+40 top-15%',
      x: 102.5,
      y: 129.4375,
    },
    centertopcenterbottom: {
      at: 'center bottom',
      my: 'center top',
      x: 62.5,
      y: 202,
    },
    centertopcentercenter: {
      at: 'center center',
      my: 'center top',
      x: 62.5,
      y: 101,
    },
    centertopcenterplus20ptopplus20p: {
      at: 'center+20% top+20%',
      my: 'center top',
      x: 102.890625,
      y: 40.390625,
    },
    centertopcentertop: { at: 'center top', my: 'center top', x: 62.5, y: 0 },
    centertopleftbottom: {
      at: 'left bottom',
      my: 'center top',
      x: -38.5,
      y: 202,
    },
    centertopleftcenter: {
      at: 'left center',
      my: 'center top',
      x: -38.5,
      y: 101,
    },
    centertoplefttop: { at: 'left top', my: 'center top', x: -38.5, y: 0 },
    centertoprightbottom: {
      at: 'right bottom',
      my: 'center top',
      x: 163.5,
      y: 202,
    },
    centertoprightcenter: {
      at: 'right center',
      my: 'center top',
      x: 163.5,
      y: 101,
    },
    centertoprighttop: { at: 'right top', my: 'center top', x: 163.5, y: 0 },
    leftbottomcenterbottom: {
      at: 'center bottom',
      my: 'left bottom',
      x: 101,
      y: 125,
    },
    leftbottomcentercenter: {
      at: 'center center',
      my: 'left bottom',
      x: 101,
      y: 24,
    },
    leftbottomcentertop: {
      at: 'center top',
      my: 'left bottom',
      x: 101,
      y: -77,
    },
    leftbottomleftbottom: {
      at: 'left bottom',
      my: 'left bottom',
      x: 0,
      y: 125,
    },
    leftbottomleftcenter: { at: 'left center', my: 'left bottom', x: 0, y: 24 },
    leftbottomlefttop: { at: 'left top', my: 'left bottom', x: 0, y: -77 },
    leftbottomrightbottom: {
      at: 'right bottom',
      my: 'left bottom',
      x: 202,
      y: 125,
    },
    leftbottomrightcenter: {
      at: 'right center',
      my: 'left bottom',
      x: 202,
      y: 24,
    },
    leftbottomrighttop: { at: 'right top', my: 'left bottom', x: 202, y: -77 },
    leftcentercenterbottom: {
      at: 'center bottom',
      my: 'left center',
      x: 101,
      y: 163.5,
    },
    leftcentercentercenter: {
      at: 'center center',
      my: 'left center',
      x: 101,
      y: 62.5,
    },
    leftcentercentertop: {
      at: 'center top',
      my: 'left center',
      x: 101,
      y: -38.5,
    },
    leftcenterleftbottom: {
      at: 'left bottom',
      my: 'left center',
      x: 0,
      y: 163.5,
    },
    leftcenterleftcenter: {
      at: 'left center',
      my: 'left center',
      x: 0,
      y: 62.5,
    },
    leftcenterlefttop: { at: 'left top', my: 'left center', x: 0, y: -38.5 },
    leftcenterrightbottom: {
      at: 'right bottom',
      my: 'left center',
      x: 202,
      y: 163.5,
    },
    leftcenterrightcenter: {
      at: 'right center',
      my: 'left center',
      x: 202,
      y: 62.5,
    },
    leftcenterrighttop: {
      at: 'right top',
      my: 'left center',
      x: 202,
      y: -38.5,
    },
    lefttopcenterbottom: {
      at: 'center bottom',
      my: 'left top',
      x: 101,
      y: 202,
    },
    lefttopcentercenter: {
      at: 'center center',
      my: 'left top',
      x: 101,
      y: 101,
    },
    lefttopcentertop: { at: 'center top', my: 'left top', x: 101, y: 0 },
    lefttopleftbottom: { at: 'left bottom', my: 'left top', x: 0, y: 202 },
    lefttopleftcenter: { at: 'left center', my: 'left top', x: 0, y: 101 },
    lefttoplefttop: { at: 'left top', my: 'left top', x: 0, y: 0 },
    lefttoprightbottom: { at: 'right bottom', my: 'left top', x: 202, y: 202 },
    lefttoprightcenter: { at: 'right center', my: 'left top', x: 202, y: 101 },
    lefttoprighttop: { at: 'right top', my: 'left top', x: 202, y: 0 },
    rightbottomcenterbottom: {
      at: 'center bottom',
      my: 'right bottom',
      x: 24,
      y: 125,
    },
    rightbottomcentercenter: {
      at: 'center center',
      my: 'right bottom',
      x: 24,
      y: 24,
    },
    rightbottomcentertop: {
      at: 'center top',
      my: 'right bottom',
      x: 24,
      y: -77,
    },
    rightbottomleftbottom: {
      at: 'left bottom',
      my: 'right bottom',
      x: -77,
      y: 125,
    },
    rightbottomleftcenter: {
      at: 'left center',
      my: 'right bottom',
      x: -77,
      y: 24,
    },
    rightbottomlefttop: { at: 'left top', my: 'right bottom', x: -77, y: -77 },
    rightbottomrightbottom: {
      at: 'right bottom',
      my: 'right bottom',
      x: 125,
      y: 125,
    },
    rightbottomrightcenter: {
      at: 'right center',
      my: 'right bottom',
      x: 125,
      y: 24,
    },
    rightbottomrighttop: {
      at: 'right top',
      my: 'right bottom',
      x: 125,
      y: -77,
    },
    rightcentercenterbottom: {
      at: 'center bottom',
      my: 'right center',
      x: 24,
      y: 163.5,
    },
    rightcentercentercenter: {
      at: 'center center',
      my: 'right center',
      x: 24,
      y: 62.5,
    },
    rightcentercentertop: {
      at: 'center top',
      my: 'right center',
      x: 24,
      y: -38.5,
    },
    rightcenterleftbottom: {
      at: 'left bottom',
      my: 'right center',
      x: -77,
      y: 163.5,
    },
    rightcenterleftcenter: {
      at: 'left center',
      my: 'right center',
      x: -77,
      y: 62.5,
    },
    rightcenterlefttop: {
      at: 'left top',
      my: 'right center',
      x: -77,
      y: -38.5,
    },
    rightcenterrightbottom: {
      at: 'right bottom',
      my: 'right center',
      x: 125,
      y: 163.5,
    },
    rightcenterrightcenter: {
      at: 'right center',
      my: 'right center',
      x: 125,
      y: 62.5,
    },
    rightcenterrighttop: {
      at: 'right top',
      my: 'right center',
      x: 125,
      y: -38.5,
    },
    righttopcenterbottom: {
      at: 'center bottom',
      my: 'right top',
      x: 24,
      y: 202,
    },
    righttopcentercenter: {
      at: 'center center',
      my: 'right top',
      x: 24,
      y: 101,
    },
    righttopcentertop: { at: 'center top', my: 'right top', x: 24, y: 0 },
    righttopleftbottom: { at: 'left bottom', my: 'right top', x: -77, y: 202 },
    righttopleftcenter: { at: 'left center', my: 'right top', x: -77, y: 101 },
    righttoplefttop: { at: 'left top', my: 'right top', x: -77, y: 0 },
    righttoprightbottom: {
      at: 'right bottom',
      my: 'right top',
      x: 125,
      y: 202,
    },
    righttoprightcenter: {
      at: 'right center',
      my: 'right top',
      x: 125,
      y: 101,
    },
    righttoprighttop: { at: 'right top', my: 'right top', x: 125, y: 0 },
  },
};
// jQuery and element use same values as selector.
testScenarios.jQuery = testScenarios.selector;
testScenarios.element = testScenarios.selector;
/* cSpell:enable */

test.describe('jQuery UI Position Shim', { tag: ['@core'] }, () => {
  test('test position', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.setViewportSize({ width: 1200, height: 600 });
    await page.goto('./position-shim-test');
    await page.locator('#position-reference-1').waitFor({ state: 'attached' });

    const result = await page.evaluate(async (testIterations) => {
      const $ = jQuery;
      const toReturn = {};
      const withinRange = (actual, expected) =>
        actual <= expected + 3 && actual >= expected - 3;

      const parseOffset = (offset, element) => {
        const regexHorizontal = /left|center|right/;
        const regexVertical = /top|center|bottom/;
        const regexOffset = /[+-]\d+(\.[\d]+)?%?/;
        const regexPosition = /^\w+/;
        let positions = offset.split(' ');
        if (positions.length === 1) {
          if (regexHorizontal.test(positions[0])) {
            positions.push('center');
          } else if (regexVertical.test(positions[0])) {
            positions = ['center'].concat(positions);
          }
        }
        const horizontalOffset = regexOffset.exec(positions[0]);
        const verticalOffset = regexOffset.exec(positions[1]);
        positions = positions.map((pos) => regexPosition.exec(pos)[0]);
        return {
          horizontalOffset: horizontalOffset
            ? parseFloat(horizontalOffset[0]) *
              (horizontalOffset[0].endsWith('%')
                ? element.offsetWidth / 100
                : 1)
            : 0,
          verticalOffset: verticalOffset
            ? parseFloat(verticalOffset[0]) *
              (verticalOffset[0].endsWith('%') ? element.offsetWidth / 100 : 1)
            : 0,
          horizontal: positions[0],
          vertical: positions[1],
        };
      };

      const checkPosition = (tip, options, attachToType, idKey) =>
        new Promise((resolve) => {
          setTimeout(() => {
            const box = tip[0].getBoundingClientRect();
            let { x, y } = box;
            if (attachToType === 'window') {
              const atOffsets = parseOffset(options.at, tip[0]);
              if (atOffsets.horizontal === 'center') {
                x = document.documentElement.clientWidth / 2 - x;
              } else if (atOffsets.horizontal === 'right') {
                x = document.documentElement.clientWidth - x;
              }
              if (atOffsets.vertical === 'center') {
                y = document.documentElement.clientHeight / 2 - y;
              } else if (atOffsets.vertical === 'bottom') {
                y = document.documentElement.clientHeight - y;
              } else {
                y += window.scrollY;
              }
            } else {
              const refRect = document
                .querySelector('#position-reference-1')
                .getBoundingClientRect();
              x -= refRect.x;
              y -= refRect.y;
            }
            if (!withinRange(x, options.x) || !withinRange(y, options.y)) {
              toReturn[idKey] =
                `${idKey} EXPECTED x:${options.x} y:${options.y} ACTUAL x:${x} y:${y}`;
            } else {
              toReturn[idKey] = true;
            }
            resolve();
          }, 25);
        });

      const attachScenarios = {
        selector: '#position-reference-1',
        window,
        jQuery: $('#position-reference-1'),
        element: document.querySelector('#position-reference-1'),
      };

      const attachToTypes = Object.keys(attachScenarios);
      for (let i = 0; i < attachToTypes.length; i++) {
        const attachToType = attachToTypes[i];
        const scenarios = Object.keys(testIterations[attachToType]);
        for (let j = 0; j < scenarios.length; j++) {
          const key = scenarios[j];
          const options = testIterations[attachToType][key];
          options.of = attachScenarios[attachToType];
          options.collision = 'none';
          const idKey = `${attachToType}${key}`;
          const tip = await new Promise((resolve) => {
            const addedTip = $(
              `<div class="test-tip" style="position:${attachToType === 'window' ? 'fixed' : 'absolute'}" id="${idKey}">${idKey}</div>`,
            ).appendTo('main');
            addedTip.position(options);
            setTimeout(() => resolve(addedTip));
          });
          await checkPosition(tip, options, attachToType, idKey);
          tip.remove();
        }
      }
      return toReturn;
    }, testScenarios);

    let totalScenarios = 0;
    Object.keys(testScenarios).forEach((scenario) => {
      totalScenarios += Object.keys(testScenarios[scenario]).length;
    });
    const keys = Object.keys(result);
    expect(keys.length).toBe(totalScenarios);
    keys.forEach((item) => {
      expect(result[item]).toBe(true);
    });
  });

  test('my, at, of', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const toReturn = {};
      const $elx = $('#elx');
      $elx.position({
        my: 'left top',
        at: 'left top',
        of: '#parentX',
        collision: 'none',
      });
      toReturn['left top, left top'] = {
        actual: $elx.offset(),
        expected: { top: 40, left: 40 },
      };
      $elx.position({
        my: 'left top',
        at: 'left bottom',
        of: '#parentX',
        collision: 'none',
      });
      toReturn['left top, left bottom'] = {
        actual: $elx.offset(),
        expected: { top: 60, left: 40 },
      };
      $elx.position({
        my: 'left',
        at: 'bottom',
        of: '#parentX',
        collision: 'none',
      });
      toReturn['left, bottom'] = {
        actual: $elx.offset(),
        expected: { top: 55, left: 50 },
      };
      $elx.position({
        my: 'left foo',
        at: 'bar baz',
        of: '#parentX',
        collision: 'none',
      });
      toReturn['left foo, bar baz'] = {
        actual: $elx.offset(),
        expected: { top: 45, left: 50 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('multiple elements', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const toReturn = {};
      const elements = $('#el1, #el2');
      elements.position({
        my: 'left top',
        at: 'left bottom',
        of: '#parent',
        collision: 'none',
      });
      elements.each(function eachFn(index) {
        toReturn[`element${index}`] = {
          actual: $(this).offset(),
          expected: { top: 10, left: 4 },
        };
      });
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('positions', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const toReturn = {};
      const offsets = { left: 0, center: 3, right: 6, top: 0, bottom: 6 };
      const start = { left: 4, top: 4 };
      const el = $('#el1');
      $.each([0, 1], (my) => {
        $.each(['top', 'center', 'bottom'], (vIndex, vertical) => {
          $.each(['left', 'center', 'right'], (hIndex, horizontal) => {
            const myVal = my ? `${horizontal} ${vertical}` : 'left top';
            const atVal = !my ? `${horizontal} ${vertical}` : 'left top';
            el.position({
              my: myVal,
              at: atVal,
              of: '#parent',
              collision: 'none',
            });
            toReturn[`my: ${myVal} at: ${atVal}`] = {
              actual: el.offset(),
              expected: {
                top: start.top + offsets[vertical] * (my ? -1 : 1),
                left: start.left + offsets[horizontal] * (my ? -1 : 1),
              },
            };
          });
        });
      });
      return toReturn;
    });
    expect(Object.keys(result).length).toBe(17);
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('of', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const toReturn = {};
      const $elx = $('#elx');
      const $parentX = $('#parentX');
      const win = $(window);

      $elx.position({
        my: 'left top',
        at: 'left top',
        of: '#parentX',
        collision: 'none',
      });
      toReturn.selector = {
        actual: $elx.offset(),
        expected: { top: 40, left: 40 },
      };

      $elx.position({
        my: 'left top',
        at: 'left bottom',
        of: $parentX,
        collision: 'none',
      });
      toReturn['jQuery object'] = {
        actual: $elx.offset(),
        expected: { top: 60, left: 40 },
      };

      $elx.position({
        my: 'left top',
        at: 'left top',
        of: $parentX[0],
        collision: 'none',
      });
      toReturn['DOM element'] = {
        actual: $elx.offset(),
        expected: { top: 40, left: 40 },
      };

      $elx.position({
        my: 'right bottom',
        at: 'right bottom',
        of: document,
        collision: 'none',
      });
      toReturn.document = {
        actual: $elx.offset(),
        expected: {
          top: $(document).height() - 10,
          left: $(document).width() - 10,
        },
      };

      $elx.position({
        my: 'right bottom',
        at: 'right bottom',
        of: $(document),
        collision: 'none',
      });
      toReturn['document as jQuery object'] = {
        actual: $elx.offset(),
        expected: {
          top: $(document).height() - 10,
          left: $(document).width() - 10,
        },
      };

      win.scrollTop(0);
      $elx.position({
        my: 'right bottom',
        at: 'right bottom',
        of: window,
        collision: 'none',
      });
      toReturn.window = {
        actual: $elx.offset(),
        expected: { top: win.height() - 10, left: win.width() - 10 },
      };

      $elx.position({
        my: 'right bottom',
        at: 'right bottom',
        of: win,
        collision: 'none',
      });
      toReturn['window as jQuery object'] = {
        actual: $elx.offset(),
        expected: { top: win.height() - 10, left: win.width() - 10 },
      };

      let scrollTopSupport = function scrollTopSupportFn() {
        const support = win.scrollTop(1).scrollTop() === 1;
        win.scrollTop(0);
        scrollTopSupport = () => support;
        return support;
      };
      if (scrollTopSupport()) {
        win.scrollTop(500).scrollLeft(200);
        $elx.position({
          my: 'right bottom',
          at: 'right bottom',
          of: window,
          collision: 'none',
        });
        toReturn['window, scrolled'] = {
          actual: $elx.offset(),
          expected: {
            top: win.height() + 500 - 10,
            left: win.width() + 200 - 10,
          },
        };
        win.scrollTop(0).scrollLeft(0);
      }

      const event1 = $.extend($.Event('someEvent'), { pageX: 200, pageY: 300 });
      $elx.position({
        my: 'left top',
        at: 'left top',
        of: event1,
        collision: 'none',
      });
      toReturn['event - left top, left top'] = {
        actual: $elx.offset(),
        expected: { top: 300, left: 200 },
      };

      const event2 = $.extend($.Event('someEvent'), { pageX: 400, pageY: 600 });
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: event2,
        collision: 'none',
      });
      toReturn['event - left top, right bottom'] = {
        actual: $elx.offset(),
        expected: { top: 600, left: 400 },
      };

      return toReturn;
    });
    expect(Object.keys(result).length).toBe(10);
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('offsets', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const toReturn = { deepEquals: {}, trues: {} };
      const $elx = $('#elx');
      let offset;

      $elx.position({
        my: 'left top',
        at: 'left+10 bottom+10',
        of: '#parentX',
        collision: 'none',
      });
      toReturn.deepEquals['offsets in at'] = {
        actual: $elx.offset(),
        expected: { top: 70, left: 50 },
      };

      $elx.position({
        my: 'left+10 top-10',
        at: 'left bottom',
        of: '#parentX',
        collision: 'none',
      });
      toReturn.deepEquals['offsets in my'] = {
        actual: $elx.offset(),
        expected: { top: 50, left: 50 },
      };

      $elx.position({
        my: 'left top',
        at: 'left+50% bottom-10%',
        of: '#parentX',
        collision: 'none',
      });
      toReturn.deepEquals['percentage offsets in at'] = {
        actual: $elx.offset(),
        expected: { top: 58, left: 50 },
      };

      $elx.position({
        my: 'left-30% top+50%',
        at: 'left bottom',
        of: '#parentX',
        collision: 'none',
      });
      toReturn.deepEquals['percentage offsets in my'] = {
        actual: $elx.offset(),
        expected: { top: 65, left: 37 },
      };

      $elx.position({
        my: 'left-30.001% top+50.0%',
        at: 'left bottom',
        of: '#parentX',
        collision: 'none',
      });
      offset = $elx.offset();
      toReturn.trues['decimal percentage top offsets in my'] =
        Math.round(offset.top) === 65;
      toReturn.trues['decimal percentage left offsets in my'] =
        Math.round(offset.left) === 37;

      $elx.position({
        my: 'left+10.4 top-10.6',
        at: 'left bottom',
        of: '#parentX',
        collision: 'none',
      });
      offset = $elx.offset();
      toReturn.trues['decimal top offsets in my'] =
        Math.round(offset.top) === 49;
      toReturn.trues['decimal left offsets in my'] =
        Math.round(offset.left) === 50;

      $elx.position({
        my: 'left+right top-left',
        at: 'left-top bottom-bottom',
        of: '#parentX',
        collision: 'none',
      });
      toReturn.deepEquals['invalid offsets'] = {
        actual: $elx.offset(),
        expected: { top: 60, left: 40 },
      };

      return toReturn;
    });
    expect(Object.keys(result.trues).length).toBe(4);
    expect(Object.keys(result.deepEquals).length).toBe(5);
    Object.entries(result.deepEquals).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
    Object.entries(result.trues).forEach(([key, value]) => {
      expect(value).toBe(true);
    });
  });

  test('using', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const toReturn = {};
      let count = 0;
      const elements = $('#el1, #el2');
      const expectedPosition = { top: 60, left: 60 };
      const expectedFeedback = {
        target: { width: 20, height: 20, left: 40, top: 40 },
        element: { width: 6, height: 6, left: 60, top: 60 },
        horizontal: 'left',
        vertical: 'top',
        important: 'vertical',
      };
      const originalPosition = elements
        .position({
          my: 'right bottom',
          at: 'right bottom',
          of: '#parentX',
          collision: 'none',
        })
        .offset();

      elements.position({
        my: 'left top',
        at: 'center+10 bottom',
        of: '#parentX',
        using(position, feedback) {
          toReturn[`correct position for call #${count}`] = {
            actual: position,
            expected: expectedPosition,
          };
          delete feedback.target.element;
          delete feedback.element.element;
          toReturn[`expected feedback for call #${count}`] = {
            actual: feedback,
            expected: expectedFeedback,
          };
          count += 1;
        },
      });

      elements.each(function eachFn(index) {
        toReturn[`elements not moved: ${index}`] = {
          actual: $(this).offset(),
          expected: originalPosition,
        };
      });
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: fit, no collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        collision: 'fit',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'left top',
        at: 'right+2 bottom+3',
        of: '#parent',
        collision: 'fit',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: 13, left: 12 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: fit, collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const win = $(window);
      const toReturn = {};
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        collision: 'fit',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: 0, left: 0 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left+2 top+3',
        of: '#parent',
        collision: 'fit',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: 0, left: 0 },
      };
      let scrollTopSupport = function scrollTopSupportFn() {
        const support = win.scrollTop(1).scrollTop() === 1;
        win.scrollTop(0);
        scrollTopSupport = () => support;
        return support;
      };
      if (scrollTopSupport()) {
        win.scrollTop(300).scrollLeft(200);
        $elx.position({
          my: 'right bottom',
          at: 'left top',
          of: '#parent',
          collision: 'fit',
        });
        toReturn['window scrolled'] = {
          actual: $elx.offset(),
          expected: { top: 300, left: 200 },
        };
        win.scrollTop(0).scrollLeft(0);
      }
      return toReturn;
    });
    expect(Object.keys(result).length).toBe(3);
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: flip, no collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        collision: 'flip',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'left top',
        at: 'right+2 bottom+3',
        of: '#parent',
        collision: 'flip',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: 13, left: 12 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: flip, collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        collision: 'flip',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left+2 top+3',
        of: '#parent',
        collision: 'flip',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: 7, left: 8 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: flipfit, no collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        collision: 'flipfit',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'left top',
        at: 'right+2 bottom+3',
        of: '#parent',
        collision: 'flipfit',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: 13, left: 12 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: flipfit, collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        collision: 'flipfit',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left+2 top+3',
        of: '#parent',
        collision: 'flipfit',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: 7, left: 8 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: none, no collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        collision: 'none',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'left top',
        at: 'right+2 bottom+3',
        of: '#parent',
        collision: 'none',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: 13, left: 12 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: none, collision', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        collision: 'none',
      });
      toReturn['no offset'] = {
        actual: $elx.offset(),
        expected: { top: -6, left: -6 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left+2 top+3',
        of: '#parent',
        collision: 'none',
      });
      toReturn['with offset'] = {
        actual: $elx.offset(),
        expected: { top: -3, left: -4 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: fit, with margin', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      Object.assign($elx[0].style, { marginTop: '6px', marginLeft: '4px' });
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        collision: 'fit',
      });
      toReturn['right bottom'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        collision: 'fit',
      });
      toReturn['left top'] = {
        actual: $elx.offset(),
        expected: { top: 6, left: 4 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('collision: flip, with margin', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      Object.assign($elx[0].style, { marginTop: '6px', marginLeft: '4px' });
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        collision: 'flip',
      });
      toReturn['left top'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        collision: 'flip',
      });
      toReturn['right bottom'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'left top',
        at: 'left top',
        of: '#parent',
        collision: 'flip',
      });
      toReturn['left top left top'] = {
        actual: $elx.offset(),
        expected: { top: 0, left: 4 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('within', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        within: document,
      });
      toReturn['within document'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: 10 },
      };
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        collision: 'fit',
        within: '#within',
      });
      toReturn['fit - right bottom'] = {
        actual: $elx.offset(),
        expected: { top: 4, left: 2 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        within: '#within',
        collision: 'fit',
      });
      toReturn['fit - left top'] = {
        actual: $elx.offset(),
        expected: { top: 2, left: 0 },
      };
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        within: '#within',
        collision: 'flip',
      });
      toReturn['flip - right bottom'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: -6 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        within: '#within',
        collision: 'flip',
      });
      toReturn['flip - left top'] = {
        actual: $elx.offset(),
        expected: { top: 10, left: -6 },
      };
      $elx.position({
        my: 'left top',
        at: 'right bottom',
        of: '#parent',
        within: '#within',
        collision: 'flipfit',
      });
      toReturn['flipfit - right bottom'] = {
        actual: $elx.offset(),
        expected: { top: 4, left: 0 },
      };
      $elx.position({
        my: 'right bottom',
        at: 'left top',
        of: '#parent',
        within: '#within',
        collision: 'flipfit',
      });
      toReturn['flipfit - left top'] = {
        actual: $elx.offset(),
        expected: { top: 4, left: 0 },
      };
      return toReturn;
    });
    expect(Object.keys(result).length).toBe(7);
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('with scrollbars', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $scrollX = $('#scrollX');
      Object.assign($scrollX[0].style, {
        width: '100px',
        height: '100px',
        left: 0,
        top: 0,
      });
      const $elx = $('#elx').position({
        my: 'left top',
        at: 'right bottom',
        of: '#scrollX',
        within: '#scrollX',
        collision: 'fit',
      });
      const toReturn = {};
      toReturn.visible = {
        actual: $elx.offset(),
        expected: { top: 90, left: 90 },
      };
      const scrollbarInfo = $.position.getScrollInfo(
        $.position.getWithinInfo($('#scrollX')),
      );
      $elx.position({
        of: '#scrollX',
        collision: 'fit',
        within: '#scrollX',
        my: 'left top',
        at: 'right bottom',
      });
      toReturn.scroll = {
        actual: $elx.offset(),
        expected: {
          top: 90 - scrollbarInfo.height,
          left: 90 - scrollbarInfo.width,
        },
      };
      $scrollX[0].style.overflow = 'auto';
      toReturn['auto, no scroll'] = {
        actual: $elx.offset(),
        expected: { top: 90, left: 90 },
      };
      $scrollX[0].style.overflow = 'auto';
      $scrollX.append($('<div>').height(300).width(300));
      $elx.position({
        of: '#scrollX',
        collision: 'fit',
        within: '#scrollX',
        my: 'left top',
        at: 'right bottom',
      });
      toReturn['auto, with scroll'] = {
        actual: $elx.offset(),
        expected: {
          top: 90 - scrollbarInfo.height,
          left: 90 - scrollbarInfo.width,
        },
      };
      return toReturn;
    });
    expect(Object.keys(result).length).toBe(4);
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });

  test('fractions', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $fractionElement = $('#fractions-element').position({
        my: 'left top',
        at: 'left top',
        of: '#fractions-parent',
        collision: 'none',
      });
      return {
        actual: $fractionElement.offset(),
        expected: $('#fractions-parent').offset(),
      };
    });
    expect(result.actual).toEqual(result.expected);
  });

  test('bug #5280: consistent results', async ({ drupalPage: page }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const wrapper = $('#bug-5280');
      const elem = wrapper.children();
      const offset1 = elem
        .position({
          my: 'center',
          at: 'center',
          of: wrapper,
          collision: 'none',
        })
        .offset();
      const offset2 = elem
        .position({
          my: 'center',
          at: 'center',
          of: wrapper,
          collision: 'none',
        })
        .offset();
      return { offset1, offset2 };
    });
    expect(result.offset1).toEqual(result.offset2);
  });

  test('bug #8710: flip if flipped position fits more', async ({
    drupalPage: page,
  }) => {
    await drupalInstallModule(page, 'position_shim_test');
    await page.goto('./position-shim-test-ported-from-jqueryui');
    await page.locator('#el1').waitFor({ state: 'visible' });

    const result = await page.evaluate(() => {
      const $ = jQuery;
      const $elx = $('#elx');
      const toReturn = {};
      $elx.position({
        my: 'left top',
        within: '#bug-8710-within-smaller',
        of: '#parentX',
        collision: 'flip',
        at: 'right bottom+30',
      });
      toReturn['flip - top fits all'] = {
        actual: $elx.offset(),
        expected: { top: 0, left: 60 },
      };
      $elx.position({
        my: 'left top',
        within: '#bug-8710-within-smaller',
        of: '#parentX',
        collision: 'flip',
        at: 'right bottom+32',
      });
      toReturn['flip - top fits more'] = {
        actual: $elx.offset(),
        expected: { top: -2, left: 60 },
      };
      $elx.position({
        my: 'left top',
        within: '#bug-8710-within-bigger',
        of: '#parentX',
        collision: 'flip',
        at: 'right bottom+32',
      });
      toReturn['no flip - top fits less'] = {
        actual: $elx.offset(),
        expected: { top: 92, left: 60 },
      };
      return toReturn;
    });
    Object.entries(result).forEach(([key, value]) => {
      expect(value.actual).toEqual(value.expected);
    });
  });
});
