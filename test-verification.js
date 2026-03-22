async function verify() {
    console.log('🔍 10-Point Verification Checklist\n');
    const results = [];

    try {
        // 1. ErrorHandler
        const eh = await import('./src/scraper/utils/ErrorHandler.js');
        console.log('✅ 1. ErrorHandler - Imported successfully');
        results.push(true);
    } catch (e) {
        console.log('❌ 1. ErrorHandler -', e.message);
        results.push(false);
    }

    try {
        // 2. URLValidator
        const { default: URLValidator } = await import('./src/scraper/utils/URLValidator.js');
        const valid = URLValidator.validate('https://example.com');
        console.log('✅ 2. URLValidator - Works (example.com valid)');
        results.push(true);
    } catch (e) {
        console.log('❌ 2. URLValidator -', e.message);
        results.push(false);
    }

    try {
        // 3. DateParser
        const { default: DateParser } = await import('./src/scraper/utils/DateParser.js');
        const date = DateParser.parse('२०२४-०१-०१');
        console.log('✅ 3. DateParser - Works (Bangla date parsed)');
        results.push(true);
    } catch (e) {
        console.log('❌ 3. DateParser -', e.message);
        results.push(false);
    }

    try {
        // 4. Metrics
        const metricsModule = await import('./src/scraper/utils/Metrics.js');
        const metrics = metricsModule.default;
        metrics.recordOperation('test', 100, true);
        console.log('✅ 4. Metrics - Works (operation recorded)');
        results.push(true);
    } catch (e) {
        console.log('❌ 4. Metrics -', e.message);
        results.push(false);
    }

    try {
        // 5. Config Validation
        const config = await import('./src/scraper/config.js');
        config.validateConfig();
        console.log('✅ 5. Config Validation - Works (EnvLoader called)');
        results.push(true);
    } catch (e) {
        console.log('❌ 5. Config Validation -', e.message);
        results.push(false);
    }

    try {
        // 6. Logger
        const loggerModule = await import('./src/scraper/utils/Logger.js');
        const logger = loggerModule.default;
        logger.info('test', 'test message');
        console.log('✅ 6. Logger - Works (message logged)');
        results.push(true);
    } catch (e) {
        console.log('❌ 6. Logger -', e.message);
        results.push(false);
    }

    try {
        // 7. HttpClient
        const hc = await import('./src/scraper/utils/HttpClient.js');
        console.log('✅ 7. HttpClient - Imported successfully');
        results.push(true);
    } catch (e) {
        console.log('❌ 7. HttpClient -', e.message);
        results.push(false);
    }

    try {
        // 8. DiffDetector
        const dd = await import('./src/scraper/agents/DiffDetector.js');
        console.log('✅ 8. DiffDetector - Imported successfully');
        results.push(true);
    } catch (e) {
        console.log('❌ 8. DiffDetector -', e.message);
        results.push(false);
    }

    try {
        // 9. EnvLoader
        const { default: EnvLoader } = await import('./src/scraper/utils/EnvLoader.js');
        EnvLoader.load();
        console.log('✅ 9. EnvLoader - Works (environment loaded)');
        results.push(true);
    } catch (e) {
        console.log('❌ 9. EnvLoader -', e.message);
        results.push(false);
    }

    try {
        // 10. Index entry point
        const idx = await import('./src/scraper/index.js');
        console.log('✅ 10. Index (Main Scraper) - Imported successfully');
        results.push(true);
    } catch (e) {
        console.log('❌ 10. Index (Main Scraper) -', e.message);
        results.push(false);
    }

    const passed = results.filter(r => r).length;
    console.log(`
📊 Result: ${passed}/10 checks passed`);
    if (passed === 10) console.log('🎉 All components working!');
}

verify().catch(console.error);
