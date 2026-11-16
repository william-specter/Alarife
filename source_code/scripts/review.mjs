import { chromium } from '@playwright/test';

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
const page = await context.newPage();
page.on('pageerror', (e) => console.log('PAGEERROR', e.message));
page.on('console', (m) => { if (m.type() === 'error') console.log('CONSOLE', m.text()); });

await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
await page.fill('#email', 'admin@alarife.test');
await page.fill('#password', 'password');
await page.click('button[type=submit]');
await page.waitForURL('**/dashboard', { timeout: 20000 });
await page.waitForTimeout(800);
await page.screenshot({ path: '/tmp/opencode/admin-dashboard.png' });

await page.goto('http://127.0.0.1:8000/author/ancient-civilizations/overview', { waitUntil: 'networkidle' });
await page.waitForTimeout(500);
await page.screenshot({ path: '/tmp/opencode/admin-overview.png' });

await page.goto('http://127.0.0.1:8000/author/ancient-civilizations/articles', { waitUntil: 'networkidle' });
await page.screenshot({ path: '/tmp/opencode/admin-articles.png' });

await page.goto('http://127.0.0.1:8000/author/ancient-civilizations/articles/1/edit', { waitUntil: 'networkidle' });
await page.waitForTimeout(1500);
await page.screenshot({ path: '/tmp/opencode/admin-editor.png' });

await page.goto('http://127.0.0.1:8000/author/ancient-civilizations/appearance', { waitUntil: 'networkidle' });
await page.waitForTimeout(2000);
await page.screenshot({ path: '/tmp/opencode/admin-appearance.png' });

await page.goto('http://127.0.0.1:8000/author/ancient-civilizations/exports', { waitUntil: 'networkidle' });
await page.waitForTimeout(500);
await page.screenshot({ path: '/tmp/opencode/admin-exports.png' });

await browser.close();
