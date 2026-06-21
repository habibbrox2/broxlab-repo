import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E Testing Configuration
 * End-to-end tests for BroxLab platform
 */

export default defineConfig({
    testDir: './tests/e2e',
    testMatch: '**/*.e2e.ts',
    timeout: 30 * 1000,
    expect: {
        timeout: 5000,
    },

    /* Run tests in files in parallel */
    fullyParallel: true,
    workers: 4,

    /* Fail after */
    maxFailures: 5,

    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,

    /* Reporter settings */
    reporter: [
        ['html', { outputFolder: 'test-results/e2e' }],
        ['json', { outputFile: 'test-results/e2e/results.json' }],
        ['junit', { outputFile: 'test-results/e2e/junit.xml' }],
        ['list'],
    ],

    /* Shared settings for all the projects below */
    use: {
        actionTimeout: 15000,
        navigationTimeout: 30000,
        baseURL: process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://localhost:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },

        /* Mobile viewports */
        {
            name: 'Mobile Chrome',
            use: { ...devices['Pixel 5'] },
        },
        {
            name: 'Mobile Safari',
            use: { ...devices['iPhone 12'] },
        },

        /* Tablet */
        {
            name: 'iPad',
            use: { ...devices['iPad Pro 11'] },
        },
    ],

    /* Run your local dev server before starting the tests */
    webServer: process.env.SKIP_WEB_SERVER || process.env.CI ? undefined : {
        command: 'npm run serve',
        port: 8000,
        reuseExistingServer: true,
        timeout: 120 * 1000,
    },
});
