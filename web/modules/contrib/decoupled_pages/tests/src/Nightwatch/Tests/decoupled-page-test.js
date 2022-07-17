const RED = 'rgb(255, 0, 0)';
const BLUE = 'rgb(0, 0, 255)';

module.exports = {
  '@tags': ['decoupled_pages'],
  before(browser) {
    browser.drupalInstall({
      setupFile: 'modules/contrib/decoupled_pages/tests/src/NightwatchTestSetupFile.php',
    });
  },
  'Test that the RED decoupled page text loads.': function (browser) {
    browser.drupalRelativeURL('/decoupled_pages/examples/red');
    browser.waitForElementVisible('.decoupled-pages-example-text').execute(function () {
      const p = document.getElementsByClassName('decoupled-pages-example-text').item(0);
      return window.getComputedStyle(p).getPropertyValue('color');
    }, function (color) {
      browser.assert.equal(color.value, RED);
    });
  },
  'Test that the BLUE decoupled page text loads.': function (browser) {
    browser.drupalRelativeURL('/decoupled_pages/examples/blue');
    browser.waitForElementVisible('.decoupled-pages-example-text').execute(function () {
      const p = document.getElementsByClassName('decoupled-pages-example-text').item(0);
      return window.getComputedStyle(p).getPropertyValue('color')
    }, function (color) {
      browser.assert.equal(color.value, BLUE);
    });
  },
  'Test that the alternate decoupled page exists.': function (browser) {
    browser.drupalRelativeURL('/decoupled_pages/examples/blue/alternate');
    browser.waitForElementVisible('.decoupled-pages-example-text').execute(function () {
      const p = document.getElementsByClassName('decoupled-pages-example-text').item(0);
      return window.getComputedStyle(p).getPropertyValue('color')
    }, function (color) {
      browser.assert.equal(color.value, BLUE);
    });
  },
  'Test that the data attributes from a route definition are added the root element.': function (browser) {
    browser.drupalRelativeURL('/decoupled_pages/examples/red');
    browser.waitForElementVisible('#decoupled-page-root').execute(function () {
      const r = document.getElementById('decoupled-page-root');
      return r.dataset.foo
    }, function (fooData) {
      browser.assert.equal(fooData.value, 'bar');
    });
  },
  'Test that the dynamic data attributes from a decoupled pages data provider are added the root element.': function (browser) {
    browser.drupalRelativeURL('/decoupled_pages/examples/red?dynamic_value=green');
    browser.waitForElementVisible('#decoupled-page-root').execute(function () {
      const r = document.getElementById('decoupled-page-root');
      return r.dataset.dynamic
    }, function (dynamicData) {
      browser.assert.equal(dynamicData.value, 'green');
    });
  }
};
