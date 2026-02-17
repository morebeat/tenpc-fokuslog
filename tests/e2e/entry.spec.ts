import { test, expect } from '@playwright/test';
import { uniqueUsername, register, getTodayDate } from './fixtures';

/**
 * Entry CRUD E2E Tests
 * 
 * Tests for creating, viewing, and managing diary entries.
 */

test.describe('Entry Creation', () => {
  test.beforeEach(async ({ page }) => {
    // Register and login for each test
    const username = uniqueUsername('entry');
    await register(page, username, 'EntryTest123!');
  });

  test('can create a basic entry', async ({ page }) => {
    await page.goto('/entry.html');
    
    // Fill basic fields
    await page.fill('#date', getTodayDate());
    await page.selectOption('#time', 'morning');
    
    // Set mood (click radio button)
    await page.click('label[for="mood-4"]');
    
    // Set focus
    await page.click('label[for="focus-4"]');
    
    // Submit form
    await page.click('button[type="submit"]');
    
    // Should show success feedback (toast or redirect)
    await expect(page.locator('.fl-toast--success, .success-message')).toBeVisible({ timeout: 5000 });
  });

  test('can create entry with all ratings', async ({ page }) => {
    await page.goto('/entry.html');
    
    // Fill all fields
    await page.fill('#date', getTodayDate());
    await page.selectOption('#time', 'noon');
    
    // Set all ratings
    await page.click('label[for="sleep-3"]');
    await page.click('label[for="mood-4"]');
    await page.click('label[for="focus-5"]');
    await page.click('label[for="appetite-3"]');
    await page.click('label[for="impulsivity-2"]');
    
    // Add notes if field exists
    const notesField = page.locator('#notes, textarea[name="notes"]');
    if (await notesField.isVisible()) {
      await notesField.fill('Test entry with all ratings');
    }
    
    // Submit
    await page.click('button[type="submit"]');
    
    // Should succeed
    await expect(page.locator('.fl-toast--success, .success-message')).toBeVisible({ timeout: 5000 });
  });

  test('shows validation error without required fields', async ({ page }) => {
    await page.goto('/entry.html');
    
    // Clear date (if pre-filled)
    await page.fill('#date', '');
    
    // Try to submit empty form
    await page.click('button[type="submit"]');
    
    // Should show validation error or prevent submission
    // Browser validation or custom error
    const hasError = await page.locator('.error-message, .fl-toast--error, :invalid').first().isVisible();
    expect(hasError).toBeTruthy();
  });

  test('can select medication from dropdown', async ({ page }) => {
    await page.goto('/entry.html');
    
    // Wait for medications to load
    await page.waitForTimeout(500);
    
    // Check if medication dropdown has options
    const medicationSelect = page.locator('#medication_id');
    const optionCount = await medicationSelect.locator('option').count();
    
    // Should have at least "Bitte wählen" and "Kein Medikament"
    expect(optionCount).toBeGreaterThanOrEqual(2);
  });

  test('date defaults to today', async ({ page }) => {
    await page.goto('/entry.html');
    
    // Check if date is prefilled with today
    const dateValue = await page.inputValue('#date');
    expect(dateValue).toBe(getTodayDate());
  });
});

test.describe('Entry in Report', () => {
  test('created entry appears in report', async ({ page }) => {
    const username = uniqueUsername('report');
    await register(page, username, 'ReportTest123!');
    
    // Create an entry
    await page.goto('/entry.html');
    await page.fill('#date', getTodayDate());
    await page.selectOption('#time', 'morning');
    await page.click('label[for="mood-5"]');
    await page.click('label[for="focus-5"]');
    await page.click('button[type="submit"]');
    
    // Wait for save
    await page.waitForTimeout(1000);
    
    // Go to report
    await page.goto('/report.html');
    
    // Set date filter to include today
    await page.fill('#date_from', getTodayDate());
    await page.fill('#date_to', getTodayDate());
    
    // Apply filter
    await page.click('#filter-btn');
    
    // Wait for entries to load
    await page.waitForTimeout(1000);
    
    // Should show entries
    const entriesTable = page.locator('#entries-table');
    await expect(entriesTable).not.toContainText('Keine Einträge');
  });
});

test.describe('Entry Times', () => {
  test('can create entries for all time slots', async ({ page }) => {
    const username = uniqueUsername('times');
    await register(page, username, 'TimesTest123!');
    
    const times = ['morning', 'noon', 'evening'];
    
    for (const time of times) {
      await page.goto('/entry.html');
      await page.fill('#date', getTodayDate());
      await page.selectOption('#time', time);
      await page.click('label[for="mood-3"]');
      await page.click('label[for="focus-3"]');
      await page.click('button[type="submit"]');
      
      // Wait for save and toast to clear
      await page.waitForTimeout(1500);
    }
    
    // Verify in report
    await page.goto('/report.html');
    await page.fill('#date_from', getTodayDate());
    await page.fill('#date_to', getTodayDate());
    await page.click('#filter-btn');
    
    await page.waitForTimeout(1000);
    
    // Should show multiple entries
    const entriesText = await page.locator('#entries-table').textContent();
    // Should contain time slot labels
    expect(entriesText).toContain('Morgen');
  });
});
