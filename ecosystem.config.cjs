module.exports = {
  apps: [
    {
      name: 'scraper-api',
      script: 'src/api/scraper-server.js',
      env: {
        NODE_ENV: 'production'
      },
      max_memory_restart: '512M',
      autorestart: true
    },
    {
      name: 'scraper-worker',
      script: 'src/workers/scrape-worker.js',
      instances: 'max',
      exec_mode: 'cluster',
      env: {
        NODE_ENV: 'production'
      },
      max_memory_restart: '512M',
      autorestart: true
    },
    {
      name: 'scraper-retry-worker',
      script: 'src/workers/retry-worker.js',
      instances: 1,
      env: {
        NODE_ENV: 'production'
      },
      max_memory_restart: '512M',
      autorestart: true
    }
  ]
};
