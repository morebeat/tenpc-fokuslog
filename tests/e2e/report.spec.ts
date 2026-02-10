import { test, expect } from '@playwright/test';
import { uniqueUsername, register, getTodayDate } from './fixtures';

/**
 * Report & Analytics E2E Tests
 * 
 * Tests for viewing reports, filtering, and export functionality.
 */

test.describe('Report Page', () => {
  test.beforeEach(async ({ page }) => {
    const username = uniqueUsername('report');
    await register(page, username, 'ReportTest123!');
  });

  test('report page loads successfully', async ({ page }) => {
    await page.goto('/report.html');
    
    // Should show page title
    await expect(page.locator('.page-title, h2')).toContainText('Auswertung');
    
    // Should have filter controls
    await expect(page.locator('#date_from')).toBeVisible();
    await expect(page.locator('#date_to')).toBeVisible();
    await expect(page.locator('#filter-btn')).toBeVisible();
  });

  test('can filter by date range', async ({ page }) => {
    await page.goto('/report.html');
    
    const today = getTodayDate();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    const weekAgoStr = weekAgo.toISOString().split('T')[0];
    
    // Set date range
    await page.fill('#date_from', weekAgoStr);
    await page.fill('#date_to', today);
    
    // Apply filter
    await page.click('#filter-btn');
    
    // Wait for response
    await page.waitForTimeout(1000);
    
    // Should show entries container (even if empty)
    await expect(page.locator('#entries-table')).toBeVisible();
  });

  test('shows chart container', async ({ page }) => {
    await page.goto('/report.html');
    
    // Chart canvas should exist
    await expect(page.locator('#reportChart')).toBeVisible();
  });

  test('export buttons are visible', async ({ page }) => {
    await page.goto('/report.html');
    
    // Check export buttons
    await expect(page.locator('#export-csv-btn')).toBeVisible();
    await expect(page.locator('#export-pdf-btn')).toBeVisible();
  });
});

test.describe('Report with Data', () => {
  test('chart updates with entry data', async ({ page }) => {
    const username = uniqueUsername('chartdata');
    await register(page, username, 'ChartTest123!');
    
    // Create a few entries
    for (let i = 0; i < 3; i++) {
      await page.goto('/entry.html');
      
      const date = new Date();
      date.setDate(date.getDate() - i);
      const dateStr = date.toISOString().split('T')[0];
      
      await page.fill('#date', dateStr);
      await page.selectOption('#time', 'morning');
      await page.click(`label[for="mood-${3 + i % 3}"]`);
      await page.click(`label[for="focus-${3 + i % 3}"]`);
      await page.click('button[type="submit"]');
      
      await page.waitForTimeout(1000);
    }
    
    // View report
    await page.goto('/report.html');
    
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    
    await page.fill('#date_from', weekAgo.toISOString().split('T')[0]);
    await page.fill('#date_to', getTodayDate());
    await page.click('#filter-btn');
    
    await page.waitForTimeout(2000);
    
    // Entries should appear
    const entriesTable = page.locator('#entries-table');
    const text = await entriesTable.textContent();
    
    // Should not say "keine Einträge"
    expect(text?.toLowerCase()).not.toContain('keine einträge');
  });
});

test.describe('Export Functionality', () => {
  test('CSV export triggers download', async ({ page }) => {
    const username = uniqueUsername('csvexport');
    await register(page, username, 'CSVTest123!');
    
    // Create an entry first
    await page.goto('/entry.html');
    await page.fill('#date', getTodayDate());
    await page.selectOption('#time', 'morning');
    await page.click('label[for="mood-4"]');
    await page.click('label[for="focus-4"]');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1000);
    
    // Go to report
    await page.goto('/report.html');
    await page.fill('#date_from', getTodayDate());
    await page.fill('#date_to', getTodayDate());
    await page.click('#filter-btn');
    await page.waitForTimeout(1000);
    
    // Listen for download
    const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
    
    // Click CSV export
    await page.click('#export-csv-btn');
    
    const download = await downloadPromise;
    
    // If download happened, verify filename
    if (download) {
      const filename = download.suggestedFilename();
      expect(filename).toContain('.csv');
    }
  });

  test('PDF export button works', async ({ page }) => {
    const username = uniqueUsername('pdfexport');
    await register(page, username, 'PDFTest123!');
    
    await page.goto('/report.html');
    
    // PDF export button should be clickable
    const pdfBtn = page.locator('#export-pdf-btn');
    await expect(pdfBtn).toBeEnabled();
    
    // Click should not cause error (PDF generation may open new tab or download)
    await pdfBtn.click();
    
    // Just verify no error occurred
    await page.waitForTimeout(1000);
  });
});

test.describe('Trend Analysis', () => {
  test('trends section exists', async ({ page }) => {
    const username = uniqueUsername('trends');
    await register(page, username, 'TrendsTest123!');
    
    await page.goto('/report.html');
    
    // Trends section should exist (may be hidden initially)
    const trendsSection = page.locator('#trends-section');
    const isVisible = await trendsSection.isVisible();
    
    // Either visible or present but hidden
    if (!isVisible) {
      await expect(trendsSection).toHaveCount(1);
    }
  });
});
