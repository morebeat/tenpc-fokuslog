import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright Configuration for FokusLog E2E Tests
 * 
 * Run all tests:     npm test
 * Run with UI:       npm run test:ui
 * Run headed:        npm run test:headed
 * Debug mode:        npm run test:debug
 * Generate tests:    npm run test:codegen
 */
export default defineConfig({
  testDir: './tests/e2e',
  
  /* Run tests in files in parallel */
  fullyParallel: true,
  
  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,
  
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  
  /* Opt out of parallel tests on CI */
  workers: process.env.CI ? 1 : undefined,
  
  /* Reporter to use */
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['list']
  ],
  
  /* Shared settings for all the projects below */
  use: {
    /* Base URL to use in actions like `await page.goto('/')` */
    baseURL: process.env.BASE_URL || 'http://localhost:8000/app/',

    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',
    
    /* Screenshot on failure */
    screenshot: 'only-on-failure',
    
    /* Video on failure */
    video: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
    /* Setup project - creates authenticated state */
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },
    
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
      },
      dependencies: ['setup'],
    },

    {
      name: 'firefox',
      use: { 
        ...devices['Desktop Firefox'],
      },
      dependencies: ['setup'],
    },

    /* Mobile viewport */
    {
      name: 'mobile-chrome',
      use: { 
        ...devices['Pixel 5'],
      },
      dependencies: ['setup'],
    },
  ],

  /* Run local dev server before starting the tests */
  webServer: {
    command: 'php -S localhost:8000',
    url: 'http://localhost:8000/app/',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },
});
