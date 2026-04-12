module.exports = {
  apps: [
    {
      name: 'broxlab-node',
      script: 'npx',
      args: ['tsx', 'src/index.ts'],
      instances: 1,
      exec_mode: 'fork',
      env: {
        NODE_ENV: 'production',
        PORT: 3002,
      },
      error_file: './storage/logs/broxlab-node-error.log',
      out_file: './storage/logs/broxlab-node-out.log',
      log_file: './storage/logs/broxlab-node.log',
    },
  ],
};
