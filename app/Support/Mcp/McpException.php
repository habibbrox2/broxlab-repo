<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use RuntimeException;

/**
 * Safe MCP-layer exception: carries only a JSON-RPC code and a client-safe
 * message. Never wraps SQL errors, paths or stack traces.
 */
class McpException extends RuntimeException
{
}
