import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';
const cwd = process.cwd();
const php = (code) => execSync(`docker compose exec -T app php artisan tinker --execute='${code}'`, { cwd }).toString();
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

await page.goto('http://localhost:8000/login', { waitUntil: 'load' });
await page.fill('#email', 'admin@alarife.test');
await page.fill('#password', 'password');
await page.click('button[type=submit]');
await page.waitForURL('**/dashboard');
await page.waitForTimeout(3500);
console.log('LIVE', await page.getByText('Live', { exact: true }).count());

php('$u=App\\Models\\User::where("email","admin@alarife.test")->first(); $e=App\\Models\\Encyclopedia::first(); app(App\\Domain\\Activity\\RecordActivity::class)($e,$u,"probe.docker",null,["title"=>"Docker realtime probe"]); echo "ok";');
await page.waitForTimeout(2500);
console.log('DASHBOARD PROBE', await page.getByText('Docker realtime probe', { exact: true }).count());

await page.goto('http://localhost:8000/author/ancient-civilizations/articles/1/edit', { waitUntil: 'load' });
await page.waitForTimeout(3000);
php('$e=App\\Models\\Encyclopedia::find(1); app(App\\Domain\\Realtime\\Realtime::class)->encyclopedia($e,"article.saved",["article_id"=>1,"editor_id"=>99999,"editor"=>"Remote Editor","revision"=>99,"title"=>"The Pyramids of Giza"]); echo "ok";');
await page.waitForTimeout(1800);
console.log('REMOTE BANNER', await page.getByText(/Remote Editor changed/).count());
await page.screenshot({ path: '../Documentation/screenshots/admin-realtime-collaboration.png' });

await page.goto('http://localhost:8000/author/ancient-civilizations/exports', { waitUntil: 'load' });
await page.waitForTimeout(1800);
php('$e=App\\Models\\Encyclopedia::find(1); app(App\\Domain\\Realtime\\Realtime::class)->encyclopedia($e,"export.updated",["export_id"=>1,"status"=>"processing","progress"=>55,"error_message"=>null,"archive_size"=>null,"checksum"=>null,"download_url"=>null]); echo "ok";');
await page.waitForTimeout(1200);
console.log('EXPORT PROGRESS', await page.getByText('55% — rendering pages and assets').count());
await page.screenshot({ path: '../Documentation/screenshots/admin-realtime-export.png' });

console.log('errors', JSON.stringify(errors));
await browser.close();
