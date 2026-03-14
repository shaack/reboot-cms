import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer');
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const imgDir = path.join(__dirname, '..', 'web', 'media', 'screenshots');

const BASE = 'http://localhost';
const ADMIN = `${BASE}/admin`;
const WIDTH = 1024;
const HEIGHT = 768;

async function main() {
    const browser = await puppeteer.launch({ headless: true });
    const page = await browser.newPage();
    await page.setViewport({ width: WIDTH, height: HEIGHT });

    // 1. Front page (no auth needed)
    console.log('Capturing front page...');
    await page.goto(BASE, { waitUntil: 'networkidle0' });
    await page.screenshot({ path: path.join(imgDir, 'reboot-cms-index.png') });
    console.log('  -> reboot-cms-index.png');

    // 2. Login page
    console.log('Capturing login page...');
    await page.goto(`${ADMIN}/login`, { waitUntil: 'networkidle0' });
    await page.screenshot({ path: path.join(imgDir, 'reboot-cms-admin-login.png') });
    console.log('  -> reboot-cms-admin-login.png');

    // 3. Log in
    console.log('Logging in...');
    await page.type('#username', 'admin');
    await page.type('#password', 'd3v_p455');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle0' });
    console.log('  Logged in, now at:', page.url());

    // 4. Page editor (editing index.md)
    console.log('Capturing page editor...');
    await page.goto(`${ADMIN}/pages?page=/index.md`, { waitUntil: 'networkidle0' });
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(imgDir, 'reboot-cms-admin-page-edit.png') });
    console.log('  -> reboot-cms-admin-page-edit.png');

    // 6. Site configuration
    console.log('Capturing site configuration...');
    await page.goto(`${ADMIN}/config`, { waitUntil: 'networkidle0' });
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(imgDir, 'reboot-cms-admin-site-configuration.png') });
    console.log('  -> reboot-cms-admin-site-configuration.png');

    // 7. Media manager
    console.log('Capturing media manager...');
    await page.goto(`${ADMIN}/media`, { waitUntil: 'networkidle0' });
    await page.screenshot({ path: path.join(imgDir, 'reboot-cms-admin-media.png') });
    console.log('  -> reboot-cms-admin-media.png');

    // 8. Users
    console.log('Capturing users...');
    await page.goto(`${ADMIN}/users`, { waitUntil: 'networkidle0' });
    await page.screenshot({ path: path.join(imgDir, 'reboot-cms-admin-users.png') });
    console.log('  -> reboot-cms-admin-users.png');

    // 9. Update
    console.log('Capturing update page...');
    await page.goto(`${ADMIN}/update`, { waitUntil: 'networkidle0' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(imgDir, 'reboot-cms-admin-update.png') });
    console.log('  -> reboot-cms-admin-update.png');

    await browser.close();
    console.log('\nDone! All screenshots saved to web/media/screenshots/');
}

main().catch(err => {
    console.error('Error:', err);
    process.exit(1);
});
