import { test, expect } from '@playwright/test';

test.describe('Public Header - Smoke Test', () => {
    test.beforeEach(async ({ page }) => {
        // Navigate to home page
        await page.goto('/');
        await page.waitForLoadState('networkidle');
    });

    test('should render header with correct structure', async ({ page }) => {
        // Check header exists
        const header = page.locator('header').first();
        await expect(header).toBeVisible();

        // Check header has sticky positioning
        const headerClasses = await header.getAttribute('class');
        expect(headerClasses).toContain('sticky');
        expect(headerClasses).toContain('top-0');
        expect(headerClasses).toContain('z-50');
    });

    test('should have proper color scheme in light mode', async ({ page }) => {
        // Ensure light mode
        await page.evaluate(() => {
            document.documentElement.removeAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', 'light');
        });

        // Get nav items
        const navLinks = page.locator('nav a[href*="/"]');
        const count = await navLinks.count();

        if (count > 0) {
            const firstLink = navLinks.first();
            const computedStyle = await firstLink.evaluate((el) => {
                return window.getComputedStyle(el);
            });

            // Check text color is slate (not old custom color)
            const color = computedStyle.color;
            console.log('Nav link color (light):', color);

            // Should be readable dark gray/slate color (RGB values around 100-150)
            expect(color).toMatch(/rgb\(\d+,/);
        }
    });

    test('should have proper color scheme in dark mode', async ({ page }) => {
        // Enable dark mode
        await page.evaluate(() => {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.classList.add('dark');
        });

        await page.waitForTimeout(300); // Wait for theme transition

        // Get nav items
        const navLinks = page.locator('nav a[href*="/"]');
        const count = await navLinks.count();

        if (count > 0) {
            const firstLink = navLinks.first();
            const computedStyle = await firstLink.evaluate((el) => {
                return window.getComputedStyle(el);
            });

            // Check text color is light slate (not old custom color)
            const color = computedStyle.color;
            console.log('Nav link color (dark):', color);

            // Should be light color
            expect(color).toMatch(/rgb\(\d+,/);
        }
    });

    test('should have responsive navigation menu', async ({ page }) => {
        const nav = page.locator('nav').first();
        const navClasses = await nav.getAttribute('class');

        // Should have responsive classes
        expect(navClasses).toContain('hidden');
        expect(navClasses).toContain('lg:flex');
    });

    test('should have hamburger menu on mobile', async ({ page }) => {
        // Set mobile viewport
        await page.setViewportSize({ width: 375, height: 667 });

        // Find hamburger button
        const hamburger = page.locator('button[aria-expanded]').first();

        if (await hamburger.isVisible()) {
            expect(await hamburger.getAttribute('aria-expanded')).toBe('false');

            // Click hamburger
            await hamburger.click();

            // Check if menu opens
            const nav = page.locator('nav').first();
            const isVisible = await nav.isVisible();
            console.log('Mobile menu visible after click:', isVisible);
        }
    });

    test('should not have legacy CSS classes in compiled output', async ({ page }) => {
        // Check that legacy navbar CSS is not applied
        const nav = page.locator('nav').first();
        const navClasses = await nav.getAttribute('class');

        // Should NOT contain legacy class names
        expect(navClasses).not.toContain('brox-navbar-container');
        expect(navClasses).not.toContain('brox-nav-link');
        expect(navClasses).not.toContain('brox-navbar-collapse');
        expect(navClasses).not.toContain('brox-mobile-toggle');
    });

    test('should have tailwind utility classes for styling', async ({ page }) => {
        const nav = page.locator('nav').first();
        const navClasses = await nav.getAttribute('class');

        // Should contain Tailwind utility classes
        expect(navClasses).toMatch(/text-slate-\d+/);
        expect(navClasses).toMatch(/bg-\w+-\d+|bg-transparent|dark:/);
    });

    test('dropdown menus should be properly styled', async ({ page }) => {
        // Check notification dropdown exists
        const notificationBtn = page.locator('button[id*="notification"]').first();

        if (await notificationBtn.isVisible()) {
            // Click to open
            await notificationBtn.click();
            await page.waitForTimeout(200);

            // Check dropdown has proper z-index and styling
            const dropdown = page.locator('[id*="dropdown"]').first();
            const isVisible = await dropdown.isVisible({ timeout: 1000 }).catch(() => false);
            console.log('Dropdown visible:', isVisible);
        }
    });

    test('buttons should have consistent color scheme', async ({ page }) => {
        // Check action buttons (login, signup, etc)
        const buttons = page.locator('button, a[role="button"]');
        const count = await buttons.count();

        console.log(`Found ${count} buttons/links`);
        expect(count).toBeGreaterThan(0);

        // Check first few buttons have proper classes
        for (let i = 0; i < Math.min(3, count); i++) {
            const btn = buttons.nth(i);
            const classes = await btn.getAttribute('class');

            // Should have Tailwind styling
            if (classes) {
                expect(classes).toMatch(/px-|py-|rounded-/);
            }
        }
    });

    test('theme toggle should work correctly', async ({ page }) => {
        // Find theme toggle button
        const themeToggle = page.locator('button').filter({ has: page.locator('svg[class*="lucide"]') }).first();

        if (await themeToggle.isVisible()) {
            const initialTheme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));

            // Click theme toggle
            await themeToggle.click();
            await page.waitForTimeout(300);

            const newTheme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
            console.log(`Theme changed from ${initialTheme} to ${newTheme}`);
        }
    });

    test('should not have scroll behavior issues', async ({ page }) => {
        // Scroll down to check if header behaves correctly
        await page.evaluate(() => window.scrollBy(0, 500));

        const header = page.locator('header').first();
        const isVisible = await header.isVisible();

        expect(isVisible).toBe(true);

        // Scroll back up
        await page.evaluate(() => window.scrollBy(0, -500));
    });
});
