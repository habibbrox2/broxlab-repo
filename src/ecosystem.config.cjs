module.exports = {
    apps: [
        {
            name: 'unified-server',
            script: 'node',
            args: ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'],
            instances: 1,
            exec_mode: 'fork',
            env: {
                NODE_ENV: 'production',
                PORT: 3002
            },
            error_file: './logs/unified-server-error.log',
            out_file: './logs/unified-server-out.log',
            log_file: './logs/unified-server.log'
        },
        {
            name: 'ai-assistant',
            script: 'node',
            args: ['./node_modules/tsx/dist/cli.mjs', 'src/index.ts'],
            instances: 1,
            exec_mode: 'fork',
            env: {
                NODE_ENV: 'production',
                PORT: 3001,
                AI_ASSISTANT_PORT: 3001
            },
            error_file: './logs/ai-assistant-error.log',
            out_file: './logs/ai-assistant-out.log',
            log_file: './logs/ai-assistant.log'
        }
    ]
};
