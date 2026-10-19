/**
 * Captures the screenshots in web/media/screenshots/ with Puppeteer, see
 * docs/screenshots.md. Usually started by docs/capture-screenshots.sh, which
 * sets up a test instance with sample content and passes the settings below. The site and the admin are shown in dark mode, at
 * 1280x800 CSS pixels with a device scale factor of 2.
 *
 *   BASE=http://localhost:21007 ADMIN_USER=admin ADMIN_PASSWORD=... \
 *     NODE_PATH=$(npm root -g) node docs/capture-screenshots.mjs
 *
 * Shots of admin sections that are not available, for example an AddOn that
 * is not registered, are skipped with a notice. Pages with little content are
 * cut below it, so the image shows no empty area.
 *
 * HOST_RULES passes a host mapping to Chrome, so links and file names in the
 * images show a neutral domain instead of localhost:
 *
 *   BASE=http://www.my-site.test HOST_RULES="MAP www.my-site.test 127.0.0.1:26007" ...
 */
import { createRequire } from 'module';
import { fileURLToPath } from 'url';
import path from 'path';

const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const imgDir = path.join(__dirname, '..', 'web', 'media', 'screenshots');

const BASE = (process.env.BASE || 'http://localhost:21007').replace(/\/$/, '');
const ADMIN = `${BASE}/admin`;
const USER = process.env.ADMIN_USER || 'admin';
const PASSWORD = process.env.ADMIN_PASSWORD;
if (!PASSWORD) {
    console.error('Set ADMIN_PASSWORD, or run docs/capture-screenshots.sh');
    process.exit(1);
}
const VIEWPORT = { width: 1280, height: 800, deviceScaleFactor: 2 };

const pause = (ms) => new Promise(r => setTimeout(r, ms));

async function main() {
    const args = process.env.HOST_RULES ? [`--host-resolver-rules=${process.env.HOST_RULES}`] : [];
    const browser = await puppeteer.launch({ headless: true, args });
    const page = await browser.newPage();
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'dark' }]);
    await page.setViewport(VIEWPORT);

    // fit: end the image a little below the lowest visible element, but keep
    // it at least 480 pixels high, so all images share a similar shape
    const shot = async (name, fit = false) => {
        const options = { path: path.join(imgDir, name) };
        if (fit) {
            const bottom = await page.evaluate(() => Math.max(...Array.from(document.body.querySelectorAll('*'))
                .filter(el => el.offsetParent !== null)
                .map(el => el.getBoundingClientRect().bottom)));
            const height = Math.min(VIEWPORT.height, Math.max(480, Math.ceil(bottom + 32)));
            options.clip = { x: 0, y: 0, width: VIEWPORT.width, height };
        }
        await page.screenshot(options);
        console.log(`  -> ${name}`);
    };
    const open = async (url, wait = 600) => {
        const response = await page.goto(url, { waitUntil: 'networkidle0' });
        await pause(wait);
        return response;
    };

    // --- The site ---

    console.log('Front page');
    await open(BASE);
    await shot('reboot-cms-index.png');

    console.log('Blog article with its box of links');
    await open(`${BASE}/blog`);
    const article = await page.$eval('.blog-teaser a', a => a.getAttribute('href'));
    await open(`${BASE}/${article}`);
    await shot('reboot-cms-blog-article.png');

    console.log('Gallery with an enlarged image');
    await open(BASE);
    await page.$eval('a[data-lightbox]', a => a.scrollIntoView({ block: 'center' }));
    await page.click('a[data-lightbox]');
    await pause(800);
    await shot('reboot-cms-lightbox.png');
    await page.keyboard.press('Escape');

    // --- The admin ---

    console.log('Login');
    await open(`${ADMIN}/login`);
    await shot('reboot-cms-admin-login.png');
    await page.type('#username', USER);
    await page.type('#password', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button[type="submit"]'),
    ]);
    if (page.url().includes('/login')) {
        throw new Error(`Login as "${USER}" failed, set ADMIN_USER and ADMIN_PASSWORD`);
    }

    console.log('Page editor with preview');
    await open(`${ADMIN}/pages?page=/index.md`, 1000);
    await page.click('#preview-toggle');
    await pause(2000);
    await shot('reboot-cms-admin-page-edit.png');

    console.log('Insert block menu');
    await page.click('.mde-btn-insert-block');
    await pause(400);
    await shot('reboot-cms-admin-insert-block.png');
    await page.keyboard.press('Escape');

    console.log('Page history');
    await open(`${ADMIN}/pages?page=/index.md`, 1000);
    await page.evaluate(() => window.toggleHistory());
    await page.waitForSelector('#page-history-modal.show', { timeout: 5000 });
    await pause(1200);
    await shot('reboot-cms-admin-page-history.png');

    const sections = [
        ['Media', `${ADMIN}/media?path=blog`, 'reboot-cms-admin-media.png', true],
        ['Site configuration', `${ADMIN}/config`, 'reboot-cms-admin-site-configuration.png', false],
        ['Users', `${ADMIN}/users`, 'reboot-cms-admin-users.png', true],
        ['Protected areas', `${ADMIN}/protectedareas`, 'reboot-cms-admin-protected-areas.png', true],
        ['Secure downloads', `${ADMIN}/securedownloads`, 'reboot-cms-admin-secure-downloads.png', true],
        ['Backup', `${ADMIN}/backup`, 'reboot-cms-admin-backup.png', true],
        ['System health', `${ADMIN}/health`, 'reboot-cms-admin-health.png', false],
        ['Update', `${ADMIN}/update`, 'reboot-cms-admin-update.png', true],
    ];
    for (const [label, url, file, fit] of sections) {
        console.log(label);
        const response = await open(url, 1500);
        if (!response || response.status() >= 400) {
            console.log(`  skipped, ${url} answered ${response ? response.status() : 'nothing'}`);
            continue;
        }
        await shot(file, fit);
    }

    await browser.close();
    console.log('\nDone, the screenshots are in web/media/screenshots/');
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
