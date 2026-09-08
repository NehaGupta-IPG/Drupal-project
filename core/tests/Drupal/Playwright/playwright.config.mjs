import { defineConfig } from '@playwright/test';

const outputFolder = process.env.DRUPAL_PLAYWRIGHT_OUTPUT || 'reports';

export default defineConfig({
  testDir: './Tests',
  testMatch: '**/*.spec.mjs',
  timeout: 60000,
  workers: 6,
  maxFailures: 3,
  reporter: [
    ['list'],
    // Keep junit outside the HTML outputFolder — the HTML reporter clears
    // its folder and would delete results.xml if they shared a directory.
    ['junit', { outputFile: `${outputFolder}/results.xml` }],
    ['html', { open: 'never', outputFolder: `${outputFolder}/html` }],
  ],
  use: {
    // Playwright expects `/` at the end of the baseURL to make relative URLs
    // work with the provided subdirectory in the base URL.
    baseURL: `${process.env.DRUPAL_TEST_BASE_URL}/`,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    headless: process.env.DRUPAL_PLAYWRIGHT_HEADED !== '1',
  },
});
