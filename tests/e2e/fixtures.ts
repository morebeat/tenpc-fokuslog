import { test as base, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * FokusLog E2E Test Fixtures
 * 
 * Provides authenticated page contexts and test utilities.
 */

// Storage state file for authenticated sessions
export const STORAGE_STATE = path.join(__dirname, '../.auth/user.json');

// Test user credentials (created during setup)
export const TEST_USER = {
  username: `testuser_${Date.now()}`,
  password: 'TestPass123!',
  role: 'parent'
};

/**
 * Extended test fixture with authentication helpers.
 */
export const test = base.extend<{
  authenticatedPage: ReturnType<typeof base.page>;
}>({
  authenticatedPage: async ({ browser }, use) => {
    // Create context with stored authentication state
    const context = await browser.newContext({
      storageState: STORAGE_STATE,
    });
    const page = await context.newPage();
    await use(page);
    await context.close();
  },
});

export { expect };

/**
 * Helper: Wait for toast notification and verify message.
 */
export async function expectToast(page: ReturnType<typeof base.page>, messageContains: string, type?: 'success' | 'error' | 'info') {
  const toast = page.locator('.fl-toast');
  await expect(toast).toBeVisible({ timeout: 5000 });
  await expect(toast).toContainText(messageContains);
  if (type) {
    await expect(toast).toHaveClass(new RegExp(`fl-toast--${type}`));
  }
}

/**
 * Helper: Login with given credentials.
 */
export async function login(page: ReturnType<typeof base.page>, username: string, password: string) {
  await page.goto('/login.html');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  // Wait for redirect to dashboard
  await page.waitForURL(/dashboard\.html/);
}

/**
 * Helper: Register a new user.
 */
export async function register(page: ReturnType<typeof base.page>, username: string, password: string) {
  await page.goto('/register.html');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.fill('#password_confirm', password);
  await page.click('button[type="submit"]');
  // Wait for redirect to dashboard
  await page.waitForURL(/dashboard\.html/, { timeout: 10000 });
}

/**
 * Helper: Logout current user.
 */
export async function logout(page: ReturnType<typeof base.page>) {
  await page.click('#logout-btn');
  await page.waitForURL(/login\.html/);
}

/**
 * Helper: Create an entry via the form.
 */
export async function createEntry(
  page: ReturnType<typeof base.page>,
  options: {
    date?: string;
    time?: string;
    mood?: number;
    focus?: number;
    notes?: string;
  } = {}
) {
  await page.goto('/entry.html');
  
  // Set date if provided
  if (options.date) {
    await page.fill('#date', options.date);
  }
  
  // Set time if provided (select dropdown)
  if (options.time) {
    await page.selectOption('#time', options.time);
  }
  
  // Set mood rating (click on rating element)
  if (options.mood) {
    await page.click(`[data-field="mood"] [data-value="${options.mood}"]`);
  }
  
  // Set focus rating
  if (options.focus) {
    await page.click(`[data-field="focus"] [data-value="${options.focus}"]`);
  }
  
  // Add notes
  if (options.notes) {
    await page.fill('#notes', options.notes);
  }
  
  // Submit form
  await page.click('button[type="submit"]');
}

/**
 * Helper: Get today's date in YYYY-MM-DD format.
 */
export function getTodayDate(): string {
  return new Date().toISOString().split('T')[0];
}

/**
 * Helper: Generate unique username for tests.
 */
export function uniqueUsername(prefix = 'test'): string {
  return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
}
