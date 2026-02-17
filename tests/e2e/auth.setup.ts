import { test as setup, expect } from '@playwright/test';
import { STORAGE_STATE, uniqueUsername } from './fixtures';
import fs from 'fs';
import path from 'path';

/**
 * Global Setup: Create authenticated user state.
 * 
 * This runs before all tests and creates storage state
 * that can be reused by authenticated tests.
 */
setup('authenticate', async ({ page }) => {
  // Ensure .auth directory exists
  const authDir = path.dirname(STORAGE_STATE);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  const username = `e2e_parent_${Date.now()}`;
  const password = 'E2ETestPass123!';

  // Register new user
  await page.goto('/register.html');
  
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.fill('#password_confirm', password);
  
  // Submit registration
  await page.click('button[type="submit"]');
  
  // Wait for successful registration and redirect
  await page.waitForURL(/dashboard\.html/, { timeout: 15000 });
  
  // Verify we're logged in
  await expect(page.locator('#welcome')).toContainText(`Hallo, ${username}`);
  
  // Save storage state for reuse
  await page.context().storageState({ path: STORAGE_STATE });
  
  console.log(`✅ Auth setup complete. User: ${username}`);
});
