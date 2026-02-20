import { test, expect } from '@playwright/test';
import { uniqueUsername, login, logout, register } from './fixtures';

/**
 * Authentication Flow E2E Tests
 * 
 * Tests for registration, login, logout, and session handling.
 */

test.describe('Registration', () => {
  test('can register new parent account', async ({ page }) => {
    const username = uniqueUsername('reg');
    const password = 'SecurePass123!';

    await page.goto('/register.html');
    
    // Fill registration form
    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.fill('#password_confirm', password);
    
    // Submit
    await page.click('button[type="submit"]');
    
    // Should redirect to dashboard
    await page.waitForURL(/dashboard\.html/);
    
    // Should show welcome message
    await expect(page.locator('#welcome')).toContainText(`Hallo, ${username}`);
  });

  test('shows error for mismatched passwords', async ({ page }) => {
    await page.goto('/register.html');
    
    await page.fill('#username', uniqueUsername('mismatch'));
    await page.fill('#password', 'Password123!');
    await page.fill('#password_confirm', 'DifferentPass123!');
    
    await page.click('button[type="submit"]');
    
    // Should show error message
    await expect(page.locator('.error-message, .fl-toast--error')).toBeVisible();
  });

  test('shows error for short password', async ({ page }) => {
    await page.goto('/register.html');
    
    await page.fill('#username', uniqueUsername('short'));
    await page.fill('#password', 'short');
    await page.fill('#password_confirm', 'short');
    
    await page.click('button[type="submit"]');
    
    // Should show validation error
    await expect(page.locator('.error-message, .fl-toast--error')).toBeVisible();
  });

  test('shows error for duplicate username', async ({ page }) => {
    const username = uniqueUsername('dup');
    const password = 'SecurePass123!';

    // Register first user
    await register(page, username, password);
    await logout(page);

    // Try to register with same username
    await page.goto('/register.html');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.fill('#password_confirm', password);
    await page.click('button[type="submit"]');
    
    // Should show error
    await expect(page.locator('.error-message, .fl-toast--error')).toBeVisible({ timeout: 5000 });
  });
});

test.describe('Login', () => {
  test('can login with valid credentials', async ({ page }) => {
    const username = uniqueUsername('login');
    const password = 'LoginTest123!';

    // Register first
    await register(page, username, password);
    await logout(page);

    // Login
    await login(page, username, password);
    
    // Should be on dashboard
    await expect(page).toHaveURL(/dashboard\.html/);
    await expect(page.locator('#welcome')).toContainText(`Hallo, ${username}`);
  });

  test('shows error for invalid credentials', async ({ page }) => {
    await page.goto('/login.html');
    
    await page.fill('#username', 'nonexistent_user_12345');
    await page.fill('#password', 'wrongpassword');
    
    await page.click('button[type="submit"]');
    
    // Should show error
    await expect(page.locator('#login-error, .fl-toast--error')).toBeVisible();
  });

  test('redirects unauthenticated user to login', async ({ page }) => {
    // Clear any existing session
    await page.context().clearCookies();
    
    // Try to access protected page
    await page.goto('/dashboard.html');
    
    // Should redirect to login
    await page.waitForURL(/login\.html/);
  });
});

test.describe('Logout', () => {
  test('can logout successfully', async ({ page }) => {
    const username = uniqueUsername('logout');
    const password = 'LogoutTest123!';

    await register(page, username, password);
    
    // Verify logged in
    await expect(page.locator('#welcome')).toContainText(username);
    
    // Logout
    await logout(page);
    
    // Should be on login page
    await expect(page).toHaveURL(/login\.html/);
    
    // Try to access dashboard - should redirect to login
    await page.goto('/dashboard.html');
    await page.waitForURL(/login\.html/);
  });
});

test.describe('Session Persistence', () => {
  test('session persists across page navigations', async ({ page }) => {
    const username = uniqueUsername('session');
    const password = 'SessionTest123!';

    await register(page, username, password);
    
    // Navigate to different pages
    await page.goto('/entry.html');
    await expect(page.locator('#welcome')).toContainText(username);
    
    await page.goto('/report.html');
    await expect(page.locator('#welcome, .page-title')).toBeVisible();
    
    await page.goto('/dashboard.html');
    await expect(page.locator('#welcome')).toContainText(username);
  });
});
