/**
 * Marscoin Mining Pool - Screenshot Tests
 *
 * Captures screenshots of key pages for visual regression testing
 * Run with: npm run test:screenshot
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const POOL_URL = process.env.POOL_URL || 'http://mining-mars.com';
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

const PAGES = [
    { name: 'homepage', path: '/' },
    { name: 'pool', path: '/site/mining' },
    { name: 'miners', path: '/site/miners' },
    { name: 'api', path: '/site/api' },
    { name: 'difficulty', path: '/site/diff' }
];

const VIEWPORTS = [
    { name: 'desktop', width: 1920, height: 1080 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 375, height: 667 }
];

async function captureScreenshots() {
    // Ensure screenshot directory exists
    if (!fs.existsSync(SCREENSHOT_DIR)) {
        fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    }

    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

    console.log('\n=== Capturing Screenshots ===\n');
    console.log(`URL: ${POOL_URL}`);
    console.log(`Output: ${SCREENSHOT_DIR}\n`);

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');

    for (const viewport of VIEWPORTS) {
        console.log(`\nViewport: ${viewport.name} (${viewport.width}x${viewport.height})`);

        await page.setViewport({
            width: viewport.width,
            height: viewport.height
        });

        for (const pageConfig of PAGES) {
            const url = `${POOL_URL}${pageConfig.path}`;
            const filename = `${pageConfig.name}-${viewport.name}-${timestamp}.png`;
            const filepath = path.join(SCREENSHOT_DIR, filename);

            try {
                await page.goto(url, {
                    waitUntil: 'networkidle2',
                    timeout: 30000
                });

                // Wait for AJAX content
                await page.waitForTimeout(2000);

                await page.screenshot({
                    path: filepath,
                    fullPage: true
                });

                console.log(`  [OK] ${pageConfig.name}: ${filename}`);

            } catch (error) {
                console.log(`  [FAIL] ${pageConfig.name}: ${error.message}`);
            }
        }
    }

    await browser.close();

    console.log('\n=== Screenshots Complete ===\n');
}

captureScreenshots().catch(console.error);
