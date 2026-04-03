/**
 * Marscoin Mining Pool - Puppeteer Tests
 *
 * Run with: npm test
 *
 * Tests the main functionality of the mining pool frontend
 */

const puppeteer = require('puppeteer');

const POOL_URL = process.env.POOL_URL || 'http://mining-mars.com';
const TIMEOUT = 30000;

class PoolTester {
    constructor() {
        this.browser = null;
        this.page = null;
        this.results = [];
    }

    async init() {
        this.browser = await puppeteer.launch({
            headless: 'new',
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        this.page = await this.browser.newPage();
        await this.page.setViewport({ width: 1920, height: 1080 });
    }

    async close() {
        if (this.browser) {
            await this.browser.close();
        }
    }

    log(testName, passed, details = '') {
        const status = passed ? 'PASS' : 'FAIL';
        console.log(`[${status}] ${testName}${details ? ': ' + details : ''}`);
        this.results.push({ testName, passed, details });
    }

    // Test: Homepage loads correctly
    async testHomepageLoads() {
        try {
            const response = await this.page.goto(POOL_URL, {
                waitUntil: 'networkidle2',
                timeout: TIMEOUT
            });

            const status = response.status();
            this.log('Homepage loads', status === 200, `HTTP ${status}`);

            // Check for key elements
            const title = await this.page.title();
            this.log('Page has title', title.includes('Marscoin'), title);

        } catch (error) {
            this.log('Homepage loads', false, error.message);
        }
    }

    // Test: No BTC-centric language
    async testMarsLanguage() {
        try {
            const content = await this.page.content();

            // Should NOT contain BTC-centric language (except in technical contexts)
            const hasBtcWarning = content.includes('DO NOT USE a BTC address');
            this.log('No BTC warning text', !hasBtcWarning);

            // Should contain Mars-related content
            const hasMarsContent = content.includes('Marscoin') || content.includes('MARS');
            this.log('Contains Mars branding', hasMarsContent);

            // Check meta tags
            const metaDesc = await this.page.$eval(
                'meta[name="description"]',
                el => el.content
            ).catch(() => '');

            const hasMarsMeta = metaDesc.toLowerCase().includes('mars');
            this.log('Meta description mentions Mars', hasMarsMeta, metaDesc.substring(0, 50));

        } catch (error) {
            this.log('Mars language check', false, error.message);
        }
    }

    // Test: Stratum command generator works
    async testStratumGenerator() {
        try {
            // Check if the stratum generator elements exist
            const dropCoin = await this.page.$('#drop-coin');
            const textWallet = await this.page.$('#text-wallet');
            const output = await this.page.$('#output');

            this.log('Stratum generator elements exist',
                dropCoin && textWallet && output);

            // Enter a test wallet address
            if (textWallet) {
                await textWallet.type('MTestAddressForPuppeteer123');

                // Wait for JavaScript to update
                await this.page.waitForTimeout(500);

                const outputText = await this.page.$eval('#output', el => el.textContent);
                const hasWallet = outputText.includes('MTestAddressForPuppeteer123');
                this.log('Stratum generator updates with wallet', hasWallet);
            }

        } catch (error) {
            this.log('Stratum generator test', false, error.message);
        }
    }

    // Test: Pool statistics load via AJAX
    async testPoolStats() {
        try {
            // Wait for AJAX content to load
            await this.page.waitForTimeout(3000);

            // Check for pool current results
            const poolCurrent = await this.page.$('#pool_current_results');
            if (poolCurrent) {
                const content = await this.page.$eval('#pool_current_results', el => el.innerHTML);
                const hasContent = content.trim().length > 100; // Should have substantial content
                this.log('Pool stats AJAX loaded', hasContent, `${content.length} chars`);
            } else {
                this.log('Pool stats container exists', false);
            }

        } catch (error) {
            this.log('Pool stats test', false, error.message);
        }
    }

    // Test: Pool page loads
    async testPoolPage() {
        try {
            await this.page.goto(`${POOL_URL}/site/mining`, {
                waitUntil: 'networkidle2',
                timeout: TIMEOUT
            });

            const hasGraphs = await this.page.$('#graph_results_price') !== null;
            this.log('Pool page has graph containers', hasGraphs);

            // Wait for graphs to potentially load
            await this.page.waitForTimeout(2000);

        } catch (error) {
            this.log('Pool page test', false, error.message);
        }
    }

    // Test: CSS Mars theme applied
    async testMarsTheme() {
        try {
            await this.page.goto(POOL_URL, { waitUntil: 'networkidle2' });

            // Check if Mars theme colors are applied
            const tabMenuBg = await this.page.$eval('.tabmenu-out', el => {
                return window.getComputedStyle(el).backgroundColor;
            }).catch(() => null);

            this.log('Tab menu has dark background',
                tabMenuBg && tabMenuBg !== 'rgba(0, 0, 0, 0)',
                tabMenuBg);

        } catch (error) {
            this.log('Mars theme test', false, error.message);
        }
    }

    // Test: Mobile responsiveness
    async testMobileView() {
        try {
            await this.page.setViewport({ width: 375, height: 667 }); // iPhone SE
            await this.page.goto(POOL_URL, { waitUntil: 'networkidle2' });

            // Check if content is visible (not cut off)
            const mainLeftBox = await this.page.$('.main-left-box');
            if (mainLeftBox) {
                const box = await mainLeftBox.boundingBox();
                this.log('Mobile view - content visible',
                    box && box.width <= 375,
                    `Width: ${box?.width}px`);
            }

            // Reset viewport
            await this.page.setViewport({ width: 1920, height: 1080 });

        } catch (error) {
            this.log('Mobile view test', false, error.message);
        }
    }

    // Run all tests
    async runAll() {
        console.log('\n=== Marscoin Mining Pool Tests ===\n');
        console.log(`Testing: ${POOL_URL}\n`);

        await this.init();

        await this.testHomepageLoads();
        await this.testMarsLanguage();
        await this.testStratumGenerator();
        await this.testPoolStats();
        await this.testPoolPage();
        await this.testMarsTheme();
        await this.testMobileView();

        await this.close();

        // Summary
        const passed = this.results.filter(r => r.passed).length;
        const total = this.results.length;

        console.log(`\n=== Summary: ${passed}/${total} tests passed ===\n`);

        return passed === total ? 0 : 1;
    }
}

// Main
(async () => {
    const tester = new PoolTester();
    const exitCode = await tester.runAll();
    process.exit(exitCode);
})();
