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
    {
      name: 'notification-websocket',
      script: 'src/notification-websocket-server.js',
      instances: 1,
      exec_mode: 'fork',
      env: {
        NODE_ENV: 'production',
        NOTIFICATION_WS_PORT: 3003,
      },
      error_file: './storage/logs/notification-websocket-error.log',
      out_file: './storage/logs/notification-websocket-out.log',
      log_file: './storage/logs/notification-websocket.log',
    },
  ],
};
