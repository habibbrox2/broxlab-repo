#!/usr/bin/env node

/**
 * Same Port Multi-Server Setup
 * একই পোর্টে একাধিক Node.js সার্ভার সেটাপ করার স্বয়ংক্রিয় টুল
 */

import { execSync, spawn } from 'child_process';
import { existsSync, writeFileSync, readFileSync } from 'fs';
import { platform } from 'os';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(__dirname, '..');
const isWindows = platform() === 'win32';

class SamePortSetup {
  constructor() {
    this.config = {
      publicPort: 8080,
      internalPorts: {
        unified: 8081,
        assistant: 8082,
      },
      method: 'express-proxy', // 'nginx', 'express-proxy', 'load-balancer'
    };
  }

  async setup() {
    console.log('🚀 একই পোর্টে মাল্টি-সার্ভার সেটাপ শুরু...\n');

    // Detect best method
    await this.detectBestMethod();

    // Check dependencies
    await this.checkDependencies();

    // Configure servers
    await this.configureServers();

    // Start services
    await this.startServices();

    console.log('\n✅ সেটাপ সম্পন্ন!');
    console.log(`🌐 মেইন সার্ভার: http://localhost:${this.config.publicPort}`);
  }

  async detectBestMethod() {
    console.log('🔍 সেরা method নির্বাচন করা হচ্ছে...');

    // Check if Nginx is available
    try {
      execSync('nginx -v', { stdio: 'pipe' });
      console.log('✅ Nginx পাওয়া গেছে');
      this.config.method = 'nginx';
    } catch (e) {
      console.log('⚠️  Nginx পাওয়া যায়নি, Express proxy ব্যবহার করা হবে');
      this.config.method = 'express-proxy';
    }

    console.log(`📋 নির্বাচিত Method: ${this.config.method}`);
  }

  async checkDependencies() {
    console.log('\n🔧 Dependencies চেক করা হচ্ছে...');

    const deps = ['express', 'http-proxy-middleware', 'http-proxy', 'load-balancers'];

    for (const dep of deps) {
      try {
        await import(dep);
        console.log(`✅ ${dep} ইনস্টল আছে`);
      } catch (e) {
        console.log(`⚠️  ${dep} ইনস্টল হচ্ছে...`);
        execSync(`npm install ${dep}`, { cwd: rootDir, stdio: 'inherit' });
      }
    }
  }

  async configureServers() {
    console.log('\n⚙️  সার্ভার কনফিগার করা হচ্ছে...');

    // Configure unified server
    this.configureUnifiedServer();

    // Configure AI assistant
    this.configureAIAssistant();

    // Configure proxy/load balancer
    if (this.config.method === 'nginx') {
      this.configureNginx();
    }
  }

  configureUnifiedServer() {
    console.log('📝 Unified Server কনফিগার করা হচ্ছে...');

    // Create .env for unified server
    const envContent = `NODE_ENV=production
PORT=${this.config.internalPorts.unified}
HOST=127.0.0.1
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=broxlab
REDIS_HOST=localhost
REDIS_PORT=6379
OPENROUTER_API_KEY=your_key_here
ANTHROPIC_API_KEY=your_key_here
JWT_SECRET=same_port_setup_secret
CSRF_SECRET=same_port_setup_secret
`;

    writeFileSync(path.join(rootDir, '.env.unified'), envContent);
    console.log(`✅ .env.unified তৈরি করা হয়েছে (Port: ${this.config.internalPorts.unified})`);
  }

  configureAIAssistant() {
    console.log('🤖 AI Assistant কনফিগার করা হচ্ছে...');

    // Create .env for AI assistant
    const envContent = `NODE_ENV=production
AI_ASSISTANT_PORT=${this.config.internalPorts.assistant}
HOST=127.0.0.1
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=broxlab
REDIS_HOST=localhost
REDIS_PORT=6379
OPENROUTER_API_KEY=your_key_here
ANTHROPIC_API_KEY=your_key_here
JWT_SECRET=same_port_setup_secret
CSRF_SECRET=same_port_setup_secret
`;

    writeFileSync(path.join(rootDir, '.env.assistant'), envContent);
    console.log(`✅ .env.assistant তৈরি করা হয়েছে (Port: ${this.config.internalPorts.assistant})`);
  }

  configureNginx() {
    console.log('🌐 Nginx কনফিগার করা হচ্ছে...');

    const nginxConfig = `server {
    listen ${this.config.publicPort};
    server_name localhost;

    # Unified Server (API routes)
    location /api/ {
        proxy_pass http://127.0.0.1:${this.config.internalPorts.unified};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # AI Assistant routes
    location /ai/ {
        proxy_pass http://127.0.0.1:${this.config.internalPorts.assistant};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # Health check
    location /health {
        access_log off;
        proxy_pass http://127.0.0.1:${this.config.internalPorts.unified}/health;
    }

    # Default route
    location / {
        proxy_pass http://127.0.0.1:${this.config.internalPorts.unified};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}`;

    writeFileSync(path.join(rootDir, 'nginx.conf'), nginxConfig);
    console.log(`✅ nginx.conf তৈরি করা হয়েছে`);

    // Instructions for Nginx setup
    console.log('\n📋 Nginx Setup Instructions:');
    console.log(`   sudo cp nginx.conf /etc/nginx/sites-available/broxlab`);
    console.log(`   sudo ln -s /etc/nginx/sites-available/broxlab /etc/nginx/sites-enabled/`);
    console.log(`   sudo nginx -t && sudo systemctl reload nginx`);
  }

  async startServices() {
    console.log('\n▶️  সার্ভারগুলো চালু করা হচ্ছে...');

    if (this.config.method === 'nginx') {
      console.log('⚠️  Nginx proxy ব্যবহার করা হচ্ছে - manual setup required');
      console.log('   1. Nginx config apply করুন');
      console.log('   2. তারপর নিচের commands run করুন');
    }

    // Start unified server
    console.log(`🔄 Unified Server চালু হচ্ছে (Port: ${this.config.internalPorts.unified})...`);
    const unifiedEnv = { ...process.env, PORT: this.config.internalPorts.unified.toString() };
    const unifiedProcess = spawn('node', ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'], {
      cwd: rootDir,
      env: unifiedEnv,
      detached: true,
      stdio: 'ignore',
    });
    unifiedProcess.unref();

    // Start AI assistant
    console.log(`🔄 AI Assistant চালু হচ্ছে (Port: ${this.config.internalPorts.assistant})...`);
    const assistantEnv = {
      ...process.env,
      PORT: this.config.internalPorts.assistant.toString(),
      AI_ASSISTANT_PORT: this.config.internalPorts.assistant.toString(),
    };
    const assistantProcess = spawn('node', ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'], {
      cwd: rootDir,
      env: assistantEnv,
      detached: true,
      stdio: 'ignore',
    });
    assistantProcess.unref();

    // Start proxy if using express
    if (this.config.method === 'express-proxy') {
      console.log(`🔄 Express Proxy চালু হচ্ছে (Port: ${this.config.publicPort})...`);
      const proxyEnv = { ...process.env, PORT: this.config.publicPort.toString() };
      const proxyProcess = spawn('node', ['src/reverse-proxy.js'], {
        cwd: rootDir,
        env: proxyEnv,
        detached: true,
        stdio: 'ignore',
      });
      proxyProcess.unref();
    }

    // Wait a bit for servers to start
    await new Promise((resolve) => setTimeout(resolve, 3000));

    console.log('\n📋 সার্ভার URLs:');
    console.log(`   🌐 Main: http://localhost:${this.config.publicPort}`);
    console.log(`   🔧 API: http://localhost:${this.config.publicPort}/api/*`);
    console.log(`   🤖 AI: http://localhost:${this.config.publicPort}/ai/*`);
    console.log(`   ❤️  Health: http://localhost:${this.config.publicPort}/health`);
  }

  showStatus() {
    console.log('📊 সার্ভার স্ট্যাটাস:');
    console.log(`   Method: ${this.config.method}`);
    console.log(`   Public Port: ${this.config.publicPort}`);
    console.log(`   Unified Server: Port ${this.config.internalPorts.unified}`);
    console.log(`   AI Assistant: Port ${this.config.internalPorts.assistant}`);
  }
}

// CLI Interface
async function main() {
  const args = process.argv.slice(2);
  const command = args[0] || 'setup';

  const setup = new SamePortSetup();

  switch (command) {
    case 'setup':
      await setup.setup();
      break;
    case 'status':
      setup.showStatus();
      break;
    case 'nginx':
      setup.config.method = 'nginx';
      await setup.setup();
      break;
    case 'express':
      setup.config.method = 'express-proxy';
      await setup.setup();
      break;
    default:
      console.log('Usage:');
      console.log('  node scripts/same-port-setup.js setup     # Auto setup');
      console.log('  node scripts/same-port-setup.js nginx     # Force Nginx');
      console.log('  node scripts/same-port-setup.js express   # Force Express proxy');
      console.log('  node scripts/same-port-setup.js status    # Show config');
  }
}

main().catch(console.error);
