#!/usr/bin/env node

/**
 * Production deployment script for BroxLab AI Backend
 * This script handles building, testing, and deploying the Node.js backend
 */

import { execSync } from 'child_process';
import { readFileSync, writeFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

console.log('🚀 Starting BroxLab AI Backend Production Deployment...\n');

// Check Node.js version
const nodeVersion = process.version;
console.log(`📋 Node.js version: ${nodeVersion}`);
if (!nodeVersion.startsWith('v20.')) {
    console.warn('⚠️  Warning: Recommended Node.js version is 20.x');
}

// Check environment
const isProduction = process.env.NODE_ENV === 'production';
console.log(`📋 Environment: ${isProduction ? 'production' : 'development'}\n`);

try {
    // Install dependencies
    console.log('📦 Installing dependencies...');
    execSync('npm ci --production=false', { stdio: 'inherit' });

    // Run linting
    console.log('🔍 Running linter...');
    execSync('npm run lint', { stdio: 'inherit' });

    // Run type checking
    console.log('🔍 Running TypeScript compiler...');
    execSync('npx tsc --noEmit', { stdio: 'inherit' });

    // Run tests
    console.log('🧪 Running tests...');
    execSync('npm test', { stdio: 'inherit' });

    // Build the application
    console.log('🔨 Building application...');
    execSync('npm run build', { stdio: 'inherit' });

    // Create production package.json
    console.log('📦 Creating production package.json...');
    const pkg = JSON.parse(readFileSync('package.json', 'utf8'));
    const prodPkg = {
        ...pkg,
        scripts: {
            start: 'node dist/server.js'
        },
        devDependencies: {}
    };
    writeFileSync('dist/package.json', JSON.stringify(prodPkg, null, 2));

    // Copy environment template
    console.log('📋 Creating environment template...');
    const envTemplate = `# BroxLab AI Backend Environment Variables
NODE_ENV=production
PORT=3001

# Database
DB_HOST=localhost
DB_PORT=3306
DB_USER=broxlab
DB_PASSWORD=your_password
DB_DATABASE=broxlab

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

# AI Providers
OPENROUTER_API_KEY=your_openrouter_key
ANTHROPIC_API_KEY=your_anthropic_key

# Security
JWT_SECRET=your_jwt_secret
CSRF_SECRET=your_csrf_secret

# Monitoring
PROMETHEUS_PORT=9090
`;
    writeFileSync('dist/.env.example', envTemplate);

    console.log('\n✅ Deployment preparation completed!');
    console.log('\n📋 Next steps:');
    console.log('1. Copy dist/ to your production server');
    console.log('2. Set up environment variables (.env file)');
    console.log('3. Configure reverse proxy (nginx/apache)');
    console.log('4. Set up monitoring (Prometheus + Grafana)');
    console.log('5. Configure SSL certificates');
    console.log('6. Start the application: npm start');

} catch (error) {
    console.error('\n❌ Deployment failed:', error.message);
    process.exit(1);
}
