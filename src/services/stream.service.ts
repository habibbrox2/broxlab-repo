import { FastifyReply } from 'fastify';

export interface StreamChunk {
    content?: string;
    done?: boolean;
    meta?: {
        model: string;
        provider: string;
        finishReason?: string;
        tokensUsed?: number;
    };
    error?: string;
}

export class StreamService {
    /**
     * Send SSE chunk
     */
    static sendChunk(reply: FastifyReply, chunk: StreamChunk): void {
        const data = JSON.stringify(chunk);
        reply.raw.write(`data: ${data}\n\n`);
    }

    /**
     * Send SSE done signal
     */
    static sendDone(
        reply: FastifyReply,
        meta: StreamChunk['meta']
    ): void {
        const chunk: StreamChunk = {
            done: true,
            meta,
        };
        this.sendChunk(reply, chunk);
    }

    /**
     * Send SSE error
     */
    static sendError(reply: FastifyReply, error: string): void {
        const chunk: StreamChunk = {
            error,
        };
        this.sendChunk(reply, chunk);
    }

    /**
     * Initialize SSE response
     */
    static initSSE(reply: FastifyReply): void {
        reply.raw.writeHead(200, {
            'Content-Type': 'text/event-stream',
            'Cache-Control': 'no-cache',
            'Connection': 'keep-alive',
            'X-Accel-Buffering': 'no',
            'Access-Control-Allow-Origin': '*',
        });
    }

    /**
     * End SSE response
     */
    static endSSE(reply: FastifyReply): void {
        reply.raw.end();
    }

    /**
     * Send keepalive comment
     */
    static sendKeepalive(reply: FastifyReply): void {
        reply.raw.write(': keepalive\n\n');
    }

    /**
     * Format SSE data
     */
    static formatData(data: any): string {
        return `data: ${JSON.stringify(data)}\n\n`;
    }
}
