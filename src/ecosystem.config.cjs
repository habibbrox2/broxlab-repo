module.exports = {
    apps: [
        {
            name: 'unified-server',
            script: 'src/index.js',
            instances: 1,
            exec_mode: 'fork',
            env: {
                NODE_ENV: 'production',
                PORT: 3000
            },
            error_file: './logs/unified-server-error.log',
            out_file: './logs/unified-server-out.log',
            log_file: './logs/unified-server.log'
        },
        {
            name: 'ai-assistant',
            script: 'src/ai-assistant-server.js',
            instances: 1,
            exec_mode: 'fork',
            env: {
                NODE_ENV: 'production',
                PORT: 3001
            },
            error_file: './logs/ai-assistant-error.log',
            out_file: './logs/ai-assistant-out.log',
            log_file: './logs/ai-assistant.log'
        }
    ]
};