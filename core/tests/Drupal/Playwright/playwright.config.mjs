import { defineConfig } from '@playwright/test';

const outputFolder = process.env.DRUPAL_PLAYWRIGHT_OUTPUT || 'reports';

export default defineConfig({
  testDir: './Tests',
  testMatch: '**/*.spec.mjs',
  timeout: 60000,
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
    launchOptions: {
      args: ['--disable-dev-shm-usage', '--disable-gpu', '--no-sandbox'],
    },
  },
});
