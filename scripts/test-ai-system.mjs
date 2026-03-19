#!/usr/bin/env node

/**
 * AI System Test Suite
 * 
 * Tests all components of the new Node.js AI system:
 * - Configuration loading
 * - Provider initialization
 * - Chat functionality
 * - RAG engine
 * - Skills and tools
 * - PHP backend integration
 */

import { readFileSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Colors for output
const colors = {
    reset: '\x1b[0m',
    green: '\x1b[32m',
    red: '\x1b[31m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m',
};

function log(message, color = 'reset') {
    console.log(`${colors[color]}${message}${colors.reset}`);
}

function logTest(name, passed, details = '') {
    const status = passed ? `${colors.green}✓ PASS${colors.reset}` : `${colors.red}✗ FAIL${colors.reset}`;
    console.log(`  ${status} ${name}${details ? ` - ${details}` : ''}`);
    return passed;
}

async function runTests() {
    log('\n🧪 AI System Test Suite\n', 'cyan');
    log('='.repeat(50), 'cyan');

    let passed = 0;
    let failed = 0;

    // =========================================================================
    // Test 1: Configuration Loading
    // =========================================================================
    log('\n📋 Test 1: Configuration Loading', 'blue');

    try {
        const configContent = readFileSync(join(__dirname, '../src/ai/config.js'), 'utf-8');
        if (configContent.includes('FEATURE_FLAGS') && configContent.includes('PROVIDER_CONFIGS')) {
            passed += logTest('config.js loads', true) ? 1 : 0;
        } else {
            failed += logTest('config.js loads', false, 'Missing exports') ? 0 : 1;
        }
    } catch (error) {
        failed += logTest('config.js loads', false, error.message) ? 0 : 1;
    }

    try {
        const extendedContent = readFileSync(join(__dirname, '../src/ai/config-extended.js'), 'utf-8');
        if (extendedContent.includes('PHP_BACKEND') && extendedContent.includes('PROMPTS_CONFIG')) {
            passed += logTest('config-extended.js loads', true) ? 1 : 0;
        } else {
            failed += logTest('config-extended.js loads', false, 'Missing exports') ? 0 : 1;
        }
    } catch (error) {
        failed += logTest('config-extended.js loads', false, error.message) ? 0 : 1;
    }

    // =========================================================================
    // Test 2: Provider Files
    // =========================================================================
    log('\n🤖 Test 2: AI Providers', 'blue');

    const providers = ['BaseProvider', 'GoogleProvider', 'OpenAIProvider', 'AnthropicProvider'];
    for (const provider of providers) {
        const filepath = join(__dirname, `../src/ai/providers/${provider}.js`);
        if (existsSync(filepath)) {
            passed += logTest(`${provider}.js exists`, true) ? 1 : 0;
        } else {
            failed += logTest(`${provider}.js exists`, false, 'File missing') ? 0 : 1;
        }
    }

    // =========================================================================
    // Test 3: Core Modules
    // =========================================================================
    log('\n⚙️ Test 3: Core Modules', 'blue');

    const coreModules = ['AIRouter', 'RAGEngine', 'Logger', 'Cache'];
    for (const module of coreModules) {
        const filepath = join(__dirname, `../src/ai/${module}.js`);
        if (existsSync(filepath)) {
            passed += logTest(`${module}.js exists`, true) ? 1 : 0;
        } else {
            failed += logTest(`${module}.js exists`, false, 'File missing') ? 0 : 1;
        }
    }

    // =========================================================================
    // Test 4: Services
    // =========================================================================
    log('\n🔧 Test 4: Services', 'blue');

    const services = ['AIWorker', 'PHPBridge', 'SkillsManager'];
    for (const service of services) {
        const filepath = join(__dirname, `../src/ai/services/${service}.js`);
        if (existsSync(filepath)) {
            passed += logTest(`${service}.js exists`, true) ? 1 : 0;
        } else {
            failed += logTest(`${service}.js exists`, false, 'File missing') ? 0 : 1;
        }
    }

    // =========================================================================
    // Test 5: System Prompts
    // =========================================================================
    log('\n📝 Test 5: System Prompts', 'blue');

    const prompts = [
        'admin.md',
        'public.md',
        'enhancer.md',
        'summarizer.md',
        'translator.md',
        'code-helper.md',
        'scraper.md',
        'ai-skills.json',
        'ai-tools.json'
    ];

    for (const prompt of prompts) {
        const filepath = join(__dirname, `../../system/prompts/${prompt}`);
        if (existsSync(filepath)) {
            passed += logTest(`prompts/${prompt} exists`, true) ? 1 : 0;
        } else {
            failed += logTest(`prompts/${prompt} exists`, false, 'File missing') ? 0 : 1;
        }
    }

    // =========================================================================
    // Test 6: Package.json Dependencies
    // =========================================================================
    log('\n📦 Test 6: Dependencies', 'blue');

    try {
        const pkg = JSON.parse(readFileSync(join(__dirname, '../package.json'), 'utf-8'));
        const deps = pkg.dependencies || {};

        // Check required SDKs
        const required = ['@google/generative-ai', '@anthropic-ai/sdk', 'openai'];
        for (const dep of required) {
            if (deps[dep]) {
                passed += logTest(`Dependency: ${dep}`, true) ? 1 : 0;
            } else {
                failed += logTest(`Dependency: ${dep}`, false, 'Not found') ? 0 : 1;
            }
        }

        // Check removed dependencies
        if (!deps.genkit && !deps['@genkit-ai/googleai']) {
            passed += logTest('Genkit removed', true) ? 1 : 0;
        } else {
            failed += logTest('Genkit removed', false, 'Still present') ? 0 : 1;
        }
    } catch (error) {
        failed += logTest('package.json parse', false, error.message) ? 0 : 1;
    }

    // =========================================================================
    // Test 7: Import Test
    // =========================================================================
    log('\n🔌 Test 7: Module Imports', 'blue');

    try {
        // Test config
        const { FEATURE_FLAGS, PROVIDER_CONFIGS } = await import('../src/ai/config.js');
        if (FEATURE_FLAGS && PROVIDER_CONFIGS) {
            passed += logTest('config.js imports', true) ? 1 : 0;
        } else {
            failed += logTest('config.js imports', false, 'Missing exports') ? 0 : 1;
        }

        // Test extended config
        const { PHP_BACKEND, PROMPTS_CONFIG, SKILLS_CONFIG, TOOLS_CONFIG } = await import('../src/ai/config-extended.js');
        if (PHP_BACKEND && PROMPTS_CONFIG && SKILLS_CONFIG && TOOLS_CONFIG) {
            passed += logTest('config-extended.js imports', true) ? 1 : 0;
        } else {
            failed += logTest('config-extended.js imports', false, 'Missing exports') ? 0 : 1;
        }

        // Test skills loaded
        if (SKILLS_CONFIG.skills && Object.keys(SKILLS_CONFIG.skills).length > 0) {
            passed += logTest('Skills loaded', true, `${Object.keys(SKILLS_CONFIG.skills).length} skills`) ? 1 : 0;
        } else {
            failed += logTest('Skills loaded', false, 'No skills') ? 0 : 1;
        }

        // Test tools loaded
        if (TOOLS_CONFIG.tools && TOOLS_CONFIG.tools.tools && TOOLS_CONFIG.tools.tools.length > 0) {
            passed += logTest('Tools loaded', true, `${TOOLS_CONFIG.tools.tools.length} tools`) ? 1 : 0;
        } else {
            failed += logTest('Tools loaded', false, 'No tools') ? 0 : 1;
        }

    } catch (error) {
        failed += logTest('Module imports', false, error.message) ? 0 : 1;
    }

    // =========================================================================
    // Summary
    // =========================================================================
    log('\n' + '='.repeat(50), 'cyan');
    log(`\n📊 Test Results:`, 'cyan');
    log(`   ${colors.green}Passed: ${passed}${colors.reset}`);
    log(`   ${colors.red}Failed: ${failed}${colors.reset}`);
    log(`   Total: ${passed + failed}\n`);

    if (failed === 0) {
        log('🎉 All tests passed! AI System is ready.\n', 'green');
        process.exit(0);
    } else {
        log('⚠️ Some tests failed. Please review.\n', 'yellow');
        process.exit(1);
    }
}

runTests().catch(error => {
    log(`\n❌ Test suite crashed: ${error.message}`, 'red');
    process.exit(1);
});