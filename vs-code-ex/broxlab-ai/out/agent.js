"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.Agent = void 0;
const vscode = __importStar(require("vscode"));
const fs = __importStar(require("fs"));
const path = __importStar(require("path"));
const os = __importStar(require("os"));
const child_process_1 = require("child_process");
const util_1 = require("util");
const execAsync = (0, util_1.promisify)(child_process_1.exec);
const SYSTEM_PROMPT = `You are BroxLab AI, a powerful autonomous coding assistant for VS Code.

You can analyze projects, write code, refactor existing files, run terminal commands, and perform web searches to help the developer.

### Your Capabilities:
1. **Workspace Analysis**: Use \`list_files\` and \`read_file\` to understand the project structure and contents.
2. **Code Search**: Use \`search_workspace\` to find specific code or patterns.
3. **Modifying Code**: 
   - Use \`edit_file\` to replace specific blocks of code in existing files.
   - Use \`write_file\` to create new files or overwrite existing ones.
   - Use \`delete_file\` to remove files.
4. **Terminal**: Use \`run_terminal\` to execute commands (e.g., npm install, tests, builds).
5. **Web Search**: You have access to a web search plugin (via OpenRouter). Use it to find latest documentation or solutions.

### Working Rules:
1. **Think First**: Always analyze the request and plan your steps.
2. **Read Before Writing**: Never assume file content. Always use \`read_file\` or \`search_workspace\` before editing.
3. **Safety First**: Make minimal, precise changes.
4. **Error Handling**: If a tool fails, explain why and try an alternative approach.
5. **Clarity**: Explain what you did and summarize changes at the end.

When calling tools, use the standard tool calling mechanism.`;
const TOOLS = [
    {
        "type": "function",
        "function": {
            "name": "read_file",
            "description": "Read a file from the workspace",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {
                        "type": "string",
                        "description": "Relative path of the file"
                    }
                },
                "required": ["path"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "edit_file",
            "description": "Edit part of a file by replacing a specific search string with a new replace string.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": { "type": "string" },
                    "search": { "type": "string", "description": "Exact code block to replace" },
                    "replace": { "type": "string", "description": "New code block" }
                },
                "required": ["path", "search", "replace"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "search_workspace",
            "description": "Search files in the workspace (using grep)",
            "parameters": {
                "type": "object",
                "properties": {
                    "query": { "type": "string", "description": "The text or regex to search for in the workspace" }
                },
                "required": ["query"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "list_files",
            "description": "List files in a directory",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": { "type": "string", "description": "Relative path to list (e.g. '.' or 'src')" }
                },
                "required": ["path"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "run_terminal",
            "description": "Execute a terminal command",
            "parameters": {
                "type": "object",
                "properties": {
                    "command": { "type": "string" }
                },
                "required": ["command"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "write_file",
            "description": "Create a new file with the given content",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": { "type": "string" },
                    "content": { "type": "string" }
                },
                "required": ["path", "content"]
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "read_diagnostics",
            "description": "Read the active VS Code errors, warnings, and problems in the current workspace.",
            "parameters": {
                "type": "object",
                "properties": {}
            }
        }
    },
    {
        "type": "function",
        "function": {
            "name": "delete_file",
            "description": "Delete a file from the workspace",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": { "type": "string" }
                },
                "required": ["path"]
            }
        }
    }
];
class Agent {
    static sessionApproved = false;
    apiKey;
    workspaceRoot;
    constructor(apiKey) {
        this.apiKey = apiKey;
        this.workspaceRoot = vscode.workspace.workspaceFolders?.[0].uri.fsPath || '';
    }
    resolvePath(relativePath) {
        if (path.isAbsolute(relativePath)) {
            return relativePath;
        }
        return path.resolve(this.workspaceRoot, relativePath);
    }
    async executeTool(name, args) {
        if (!this.workspaceRoot) {
            return "Error: No workspace open.";
        }
        const config = vscode.workspace.getConfiguration('broxlab');
        const approvalMode = config.get('approvalMode', 'Ask Every Time');
        const needsApproval = ['edit_file', 'write_file', 'delete_file', 'run_terminal'].includes(name);
        let userApproved = false;
        if (needsApproval && approvalMode !== 'Auto Approve') {
            if (approvalMode === 'Ask Once Per Session' && Agent.sessionApproved) {
                userApproved = true;
            }
            else {
                while (!userApproved) {
                    let message = `BroxLab AI wants to execute: ${name}`;
                    if (name === 'run_terminal')
                        message += `\nCommand: ${args.command}`;
                    if (name === 'edit_file' || name === 'write_file' || name === 'delete_file') {
                        message += ` on ${path.basename(args.path)}`;
                    }
                    const actions = ['Allow', 'Reject'];
                    if (name === 'edit_file' || name === 'write_file') {
                        actions.splice(1, 0, 'View Changes');
                    }
                    const selection = await vscode.window.showWarningMessage(message, { modal: true }, ...actions);
                    if (selection === 'Allow') {
                        userApproved = true;
                        if (approvalMode === 'Ask Once Per Session')
                            Agent.sessionApproved = true;
                    }
                    else if (selection === 'View Changes') {
                        // Generate diff
                        const filePath = this.resolvePath(args.path);
                        const originalUri = vscode.Uri.file(filePath);
                        const tempPath = path.join(os.tmpdir(), `broxlab-diff-${Date.now()}-${path.basename(filePath)}`);
                        let newContent = args.content || '';
                        if (name === 'edit_file') {
                            if (fs.existsSync(filePath)) {
                                const fileContent = fs.readFileSync(filePath, 'utf8');
                                newContent = fileContent.replace(args.search, args.replace);
                            }
                        }
                        fs.writeFileSync(tempPath, newContent);
                        const tempUri = vscode.Uri.file(tempPath);
                        await vscode.commands.executeCommand('vscode.diff', originalUri, tempUri, `Proposed Changes for ${path.basename(filePath)}`);
                        // continue loop to ask again
                    }
                    else {
                        return "Error: Operation cancelled by the user.";
                    }
                }
            }
        }
        try {
            switch (name) {
                case 'read_diagnostics': {
                    const diagnostics = vscode.languages.getDiagnostics();
                    const result = [];
                    for (const [uri, diags] of diagnostics) {
                        // Only include diagnostics for files in the current workspace
                        if (uri.fsPath.startsWith(this.workspaceRoot) && diags.length > 0) {
                            const fileErrors = diags.map(d => {
                                const severity = d.severity === vscode.DiagnosticSeverity.Error ? 'Error' :
                                    d.severity === vscode.DiagnosticSeverity.Warning ? 'Warning' : 'Info';
                                return `[Line ${d.range.start.line + 1}] ${severity}: ${d.message}`;
                            });
                            result.push(`File: ${uri.fsPath}\n` + fileErrors.join('\n'));
                        }
                    }
                    return result.length > 0 ? result.join('\n\n') : 'No active errors or warnings found.';
                }
                case 'read_file': {
                    const filePath = this.resolvePath(args.path);
                    return fs.readFileSync(filePath, 'utf8');
                }
                case 'edit_file': {
                    const filePath = this.resolvePath(args.path);
                    if (!fs.existsSync(filePath)) {
                        return `Error: File not found at ${args.path}`;
                    }
                    const fileContent = fs.readFileSync(filePath, 'utf8');
                    if (!fileContent.includes(args.search)) {
                        return "Error: Could not find the exact text specified in 'search'. Please ensure the search block matches exactly, including whitespace and line endings.";
                    }
                    // Count occurrences
                    const occurrences = fileContent.split(args.search).length - 1;
                    if (occurrences > 1) {
                        return `Error: Found ${occurrences} occurrences of the search text. Please provide a more specific search block to avoid ambiguous replacements.`;
                    }
                    const updated = fileContent.replace(args.search, args.replace);
                    fs.writeFileSync(filePath, updated);
                    return "File updated successfully.";
                }
                case 'write_file': {
                    const filePath = this.resolvePath(args.path);
                    fs.mkdirSync(path.dirname(filePath), { recursive: true });
                    fs.writeFileSync(filePath, args.content);
                    return "File written successfully.";
                }
                case 'delete_file': {
                    const filePath = this.resolvePath(args.path);
                    if (fs.existsSync(filePath)) {
                        fs.unlinkSync(filePath);
                        return "File deleted successfully.";
                    }
                    else {
                        return "Error: File does not exist.";
                    }
                }
                case 'run_terminal': {
                    const { stdout, stderr } = await execAsync(args.command, { cwd: this.workspaceRoot });
                    return stdout || stderr || "Command executed, no output.";
                }
                case 'list_files': {
                    const dirPath = this.resolvePath(args.path || '.');
                    const files = fs.readdirSync(dirPath);
                    return files.join('\n');
                }
                case 'search_workspace': {
                    const isWin = process.platform === "win32";
                    // Avoid node_modules and other junk directories
                    // On Windows, findstr doesn't have a simple exclude-dir, so we use a more complex approach or just filter files
                    // But for simplicity and cross-platform robustness, we can try to use a more specific glob or filter in JS
                    let command;
                    if (isWin) {
                        // Using findstr /s /i with a filter to exclude node_modules paths
                        // This is tricky with raw exec, so let's use a better approach:
                        // Search only in src or exclude node_modules using a pipe if needed
                        command = `findstr /s /i /n "${args.query}" * | findstr /v /i "node_modules"`;
                    }
                    else {
                        command = `grep -rn --exclude-dir=node_modules "${args.query}" .`;
                    }
                    try {
                        const { stdout, stderr } = await execAsync(command, { cwd: this.workspaceRoot });
                        return stdout || stderr || "No results found.";
                    }
                    catch (err) {
                        // If grep/findstr returns non-zero, it often means no results or small errors
                        return err.stdout || err.stderr || "No results found or search failed.";
                    }
                }
                default:
                    return `Unknown tool: ${name}`;
            }
        }
        catch (e) {
            return `Error executing tool: ${e.message}`;
        }
    }
    async enhancePrompt(template, userInput) {
        const fullPrompt = template.replace('${userInput}', userInput);
        const config = vscode.workspace.getConfiguration('broxlab');
        const model = config.get('defaultModel', 'anthropic/claude-3.7-sonnet');
        const body = {
            model: model,
            messages: [{ role: 'user', content: fullPrompt }]
        };
        const result = await fetch("https://openrouter.ai/api/v1/chat/completions", {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${this.apiKey}`,
                "Content-Type": "application/json",
                "HTTP-Referer": "https://broxlab.com",
                "X-OpenRouter-Title": "BroxLab AI VSCode"
            },
            body: JSON.stringify(body)
        });
        if (!result.ok) {
            const text = await result.text();
            throw new Error(`Enhancement API error: ${text}`);
        }
        const data = await result.json();
        return data.choices[0].message.content.trim();
    }
    async handleRequest(prompt, history, onUpdate) {
        if (!this.workspaceRoot) {
            onUpdate({ type: 'error', text: 'Please open a workspace before interacting.' });
            return;
        }
        let messages = [
            { role: 'system', content: SYSTEM_PROMPT },
            ...history,
            { role: 'user', content: prompt }
        ];
        const config = vscode.workspace.getConfiguration('broxlab');
        const model = config.get('defaultModel', 'anthropic/claude-3.7-sonnet');
        let isCompleted = false;
        onUpdate({ type: 'progress', text: 'BroxLab AI thinking...' });
        while (!isCompleted) {
            const body = {
                model: model,
                messages: messages,
                tools: TOOLS,
                plugins: [{ id: "web" }] // Enable OpenRouter web plug-in
            };
            const result = await fetch("https://openrouter.ai/api/v1/chat/completions", {
                method: "POST",
                headers: {
                    "Authorization": `Bearer ${this.apiKey}`,
                    "Content-Type": "application/json",
                    "HTTP-Referer": "https://broxlab.com", // Update this
                    "X-OpenRouter-Title": "BroxLab AI VSCode" // Update this
                },
                body: JSON.stringify(body)
            });
            if (!result.ok) {
                const text = await result.text();
                throw new Error(`OpenRouter API error (${result.status}): ${text}`);
            }
            const data = await result.json();
            const message = data.choices[0].message;
            if (message.tool_calls && message.tool_calls.length > 0) {
                // Keep the tool call in context
                messages.push(message);
                for (const toolCall of message.tool_calls) {
                    const funcName = toolCall.function.name;
                    const args = JSON.parse(toolCall.function.arguments);
                    let statusMsg = `Running tool: ${funcName}`;
                    if (funcName === 'edit_file')
                        statusMsg = `Editing ${path.basename(args.path)}...`;
                    if (funcName === 'write_file')
                        statusMsg = `Generating ${path.basename(args.path)}...`;
                    if (funcName === 'run_terminal')
                        statusMsg = `Executing command...`;
                    if (funcName === 'search_workspace')
                        statusMsg = `Searching workspace...`;
                    onUpdate({ type: 'progress', text: statusMsg });
                    const toolResult = await this.executeTool(funcName, args);
                    messages.push({
                        role: "tool",
                        content: toolResult,
                        tool_call_id: toolCall.id,
                        name: funcName
                    });
                    onUpdate({ type: 'tool', name: funcName, result: toolResult });
                }
            }
            else {
                // The assistant provided a normal response
                onUpdate({ type: 'text', text: message.content });
                isCompleted = true;
            }
        }
    }
}
exports.Agent = Agent;
//# sourceMappingURL=agent.js.map