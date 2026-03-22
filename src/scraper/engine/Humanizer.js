import logger from '../../utils/scraperLogger.js';

const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

export async function randomDelay(min = 200, max = 900) {
    const delay = rand(min, max);
    return new Promise(resolve => setTimeout(resolve, delay));
}

export async function randomScroll(page) {
    try {
        const height = await page.evaluate(() => document.body.scrollHeight);
        const steps = rand(2, 5);
        for (let i = 0; i < steps; i++) {
            const y = Math.floor((height / steps) * (i + 1));
            await page.mouse.wheel({ deltaY: rand(200, 600) });
            await page.evaluate(scrollY => window.scrollTo(0, scrollY), y);
            await randomDelay(250, 700);
        }
    } catch (e) {
        logger.debug('randomScroll failed', { error: e?.message });
    }
}

export async function randomMouseMove(page) {
    try {
        const viewport = page.viewport() || { width: 1280, height: 720 };
        const moves = rand(4, 8);
        for (let i = 0; i < moves; i++) {
            const x = rand(10, Math.max(10, viewport.width - 10));
            const y = rand(10, Math.max(10, viewport.height - 10));
            await page.mouse.move(x, y, { steps: rand(5, 15) });
            await randomDelay(80, 220);
        }
    } catch (e) {
        logger.debug('randomMouseMove failed', { error: e?.message });
    }
}
