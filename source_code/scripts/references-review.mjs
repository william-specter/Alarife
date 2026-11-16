import { chromium } from '@playwright/test';
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
const base = 'http://localhost:8000';

await page.goto(`${base}/ancient-civilizations`, { waitUntil: 'load' });
await page.waitForTimeout(1200);
await page.locator('.cross-references').scrollIntoViewIfNeeded();
await page.waitForTimeout(300);
await page.screenshot({ path: '../Documentation/screenshots/reader-cross-references.png' });

await page.goto(`${base}/login`, { waitUntil: 'load' });
await page.fill('#email', 'admin@alarife.test');
await page.fill('#password', 'password');
await page.click('button[type=submit]');
await page.waitForURL('**/dashboard');

await page.goto(`${base}/author/ancient-civilizations/settings`, { waitUntil: 'load' });
await page.getByRole('heading', { name: 'Cross-references', exact: true }).scrollIntoViewIfNeeded();
await page.waitForTimeout(300);
await page.screenshot({ path: '../Documentation/screenshots/admin-cross-references-settings.png' });

await page.goto(`${base}/encyclopedias/create`, { waitUntil: 'load' });
await page.fill('#title', 'A Network of Reference');
await page.click('button:has-text("Continue")');
await page.waitForTimeout(500);
const input = page.getByPlaceholder('Search published articles and topics in other encyclopedias…');
await input.scrollIntoViewIfNeeded();
await input.fill('River');
await page.waitForTimeout(1000);
await page.locator('button:has-text("Reading a River Basin")').click();
await page.waitForTimeout(400);
await page.getByText('Selected references', { exact: true }).scrollIntoViewIfNeeded();
await page.waitForTimeout(300);
await page.screenshot({ path: '../Documentation/screenshots/admin-create-cross-references.png' });
console.log('errors', JSON.stringify(errors));
await browser.close();
