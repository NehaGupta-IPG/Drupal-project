const { defineConfig } = require('@playwright/test');

const outputFolder = process.env.DRUPAL_PLAYWRIGHT_OUTPUT || 'reports';

module.exports = defineConfig({
  testDir: './Tests',
  timeout: 60000,
  workers: 1,
  reporter: [
    ['list'],
    ['junit', { outputFile: `${outputFolder}/results.xml` }],
    ['html', { open: 'never', outputFolder }],
  ],
  use: {
    baseURL: process.env.DRUPAL_TEST_BASE_URL,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    headless: true,
  },
});
