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
exports.BroxLabViewProvider = void 0;
const vscode = __importStar(require("vscode"));
const agent_1 = require("./agent");
class BroxLabViewProvider {
    _extensionUri;
    _context;
    static viewType = 'broxlab.chatView';
    _view;
    constructor(_extensionUri, _context) {
        this._extensionUri = _extensionUri;
        this._context = _context;
    }
    resolveWebviewView(webviewView, context, _token) {
        this._view = webviewView;
        webviewView.webview.options = {
            enableScripts: true,
            localResourceRoots: [
                this._extensionUri
            ]
        };
        webviewView.webview.html = this._getHtmlForWebview(webviewView.webview);
        webviewView.webview.onDidReceiveMessage(async (data) => {
            switch (data.type) {
                case 'webviewLoaded': {
                    const apiKey = await this._context.secrets.get('openrouter_api_key');
                    const config = vscode.workspace.getConfiguration('broxlab');
                    const model = config.get('defaultModel', 'anthropic/claude-3.7-sonnet');
                    const savedHistory = this._context.workspaceState.get('broxlab.chatHistory', []);
                    if (this._view) {
                        this._view.webview.postMessage({
                            type: 'settingsLoaded',
                            apiKey: apiKey || '',
                            model: model,
                            customPrompt: config.get('customPrompt', ''),
                            approvalMode: config.get('approvalMode', 'Ask Every Time'),
                            history: savedHistory
                        });
                    }
                    break;
                }
                case 'saveSettings': {
                    if (data.apiKey) {
                        await this._context.secrets.store('openrouter_api_key', data.apiKey);
                    }
                    const config = vscode.workspace.getConfiguration('broxlab');
                    await config.update('defaultModel', data.model, vscode.ConfigurationTarget.Global);
                    await config.update('customPrompt', data.customPrompt, vscode.ConfigurationTarget.Global);
                    await config.update('approvalMode', data.approvalMode, vscode.ConfigurationTarget.Global);
                    vscode.window.showInformationMessage('BroxLab AI Settings Saved');
                    break;
                }
                case 'fetchModels': {
                    try {
                        const result = await fetch("https://openrouter.ai/api/v1/models");
                        if (result.ok) {
                            const data = await result.json();
                            this._view?.webview.postMessage({
                                type: 'modelsLoaded',
                                models: data.data
                            });
                        }
                    }
                    catch (err) {
                        console.error('Failed to fetch models', err);
                    }
                    break;
                }
                case 'clearHistory': {
                    await this._context.workspaceState.update('broxlab.chatHistory', undefined);
                    break;
                }
                case 'insertCode': {
                    const editor = vscode.window.activeTextEditor;
                    if (editor) {
                        editor.edit(editBuilder => {
                            editBuilder.insert(editor.selection.active, data.text);
                        });
                        vscode.window.showInformationMessage('Code inserted at cursor.');
                    }
                    else {
                        vscode.window.showWarningMessage('No active editor to insert code into.');
                    }
                    break;
                }
                case 'replaceCode': {
                    const editor = vscode.window.activeTextEditor;
                    if (editor) {
                        const selection = editor.selection;
                        if (!selection.isEmpty) {
                            editor.edit(editBuilder => {
                                editBuilder.replace(selection, data.text);
                            });
                            vscode.window.showInformationMessage('Selection replaced with code.');
                        }
                        else {
                            vscode.window.showWarningMessage('Please select some text to replace first.');
                        }
                    }
                    else {
                        vscode.window.showWarningMessage('No active editor.');
                    }
                    break;
                }
                case 'sendMessage': {
                    const apiKey = await this._context.secrets.get('openrouter_api_key');
                    if (!apiKey) {
                        this._view?.webview.postMessage({
                            type: 'addMessage',
                            message: { role: 'assistant', content: 'Please configure your OpenRouter API Key in the Settings tab first.' }
                        });
                        return;
                    }
                    const agent = new agent_1.Agent(apiKey);
                    // Add user message to UI immediately (original text)
                    this._view?.webview.postMessage({
                        type: 'addMessage',
                        message: { role: 'user', content: data.text }
                    });
                    // Update history in workspace state
                    const newHistory = [...data.history, { role: 'user', content: data.text }];
                    await this._context.workspaceState.update('broxlab.chatHistory', newHistory);
                    let promptText = data.text;
                    const config = vscode.workspace.getConfiguration('broxlab');
                    const customPromptTemplate = config.get('customPrompt', '');
                    // Context Awareness
                    const editor = vscode.window.activeTextEditor;
                    let contextPrefix = '';
                    if (editor) {
                        const fileName = editor.document.fileName;
                        const language = editor.document.languageId;
                        const selection = editor.document.getText(editor.selection);
                        contextPrefix = `[CONTEXT: The user is currently viewing file '${fileName}' (${language}).`;
                        if (selection) {
                            contextPrefix += ` They have selected the following code:\n\`\`\`\n${selection}\n\`\`\`\n`;
                        }
                        contextPrefix += `]\n\n`;
                    }
                    promptText = contextPrefix + promptText;
                    // Enhancement Logic
                    if (customPromptTemplate && customPromptTemplate.includes('${userInput}')) {
                        this._view?.webview.postMessage({
                            type: 'agentUpdate',
                            update: { type: 'progress', text: 'Enhancing prompt...' }
                        });
                        try {
                            const enhanced = await agent.enhancePrompt(customPromptTemplate, promptText);
                            promptText = enhanced;
                            this._view?.webview.postMessage({
                                type: 'agentUpdate',
                                update: { type: 'progress', text: `Enhanced: "${promptText.substring(0, 50)}..."` }
                            });
                        }
                        catch (err) {
                            console.error('Enhancement failed:', err);
                            // Fallback to original prompt or show error? Let's just continue with original.
                        }
                    }
                    // Add user message to UI immediately (original text)
                    this._view?.webview.postMessage({
                        type: 'addMessage',
                        message: { role: 'user', content: data.text }
                    });
                    try {
                        await agent.handleRequest(promptText, data.history, async (update) => {
                            // Provide streaming updates to the webview
                            if (this._view) {
                                this._view.webview.postMessage({
                                    type: 'agentUpdate',
                                    update
                                });
                            }
                            // If it's a final text or tool response, save history
                            if (update.type === 'text' || update.type === 'tool') {
                                // Real robust tracking would maintain messages on backend, but for now we trust webview
                                // The webview will send us the full history on the next message
                            }
                        });
                    }
                    catch (err) {
                        this._view?.webview.postMessage({
                            type: 'agentUpdate',
                            update: { type: 'error', text: err.message }
                        });
                    }
                    break;
                }
            }
        });
    }
    _getHtmlForWebview(webview) {
        return `<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>BroxLab AI</title>
                <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
                <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
                <style>
                    :root {
                        --padding: 12px;
                        --border-radius: 8px;
                        --glass-bg: rgba(255, 255, 255, 0.05);
                        --transition: all 0.2s ease;
                    }

                    body {
                        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                        color: var(--vscode-foreground);
                        background-color: var(--vscode-sideBar-background);
                        padding: 0;
                        margin: 0;
                        height: 100vh;
                        display: flex;
                        flex-direction: column;
                        overflow: hidden;
                    }

                    /* Tabs */
                    .tabs {
                        display: flex;
                        background: var(--vscode-editor-background);
                        padding: 4px;
                        border-bottom: 1px solid var(--vscode-panel-border);
                        gap: 4px;
                    }
                    .tab {
                        padding: 8px 12px;
                        cursor: pointer;
                        opacity: 0.6;
                        flex: 1;
                        text-align: center;
                        font-weight: 500;
                        font-size: 13px;
                        border-radius: 4px;
                        transition: var(--transition);
                    }
                    .tab:hover {
                        opacity: 1;
                        background: var(--glass-bg);
                    }
                    .tab.active {
                        opacity: 1;
                        background: var(--vscode-button-background);
                        color: var(--vscode-button-foreground);
                    }

                    /* Content Areas */
                    .tab-content {
                        display: none;
                        flex: 1;
                        flex-direction: column;
                        overflow: hidden;
                        background: var(--vscode-sideBar-background);
                    }
                    .tab-content.active {
                        display: flex;
                        animation: fadeIn 0.3s ease;
                    }

                    @keyframes fadeIn {
                        from { opacity: 0; transform: translateY(5px); }
                        to { opacity: 1; transform: translateY(0); }
                    }

                    /* Settings Tab Specifics */
                    #settings-content {
                        padding: 16px;
                        overflow-y: auto;
                    }
                    .setting-group {
                        margin-bottom: 24px;
                        background: var(--glass-bg);
                        padding: 12px;
                        border-radius: var(--border-radius);
                        border: 1px solid var(--vscode-panel-border);
                    }
                    label {
                        display: block;
                        margin-bottom: 8px;
                        font-weight: 600;
                        font-size: 12px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        color: var(--vscode-descriptionForeground);
                    }
                    input[type="text"], input[type="password"], select {
                        width: 100%;
                        padding: 10px;
                        background: var(--vscode-input-background);
                        color: var(--vscode-input-foreground);
                        border: 1px solid var(--vscode-input-border);
                        box-sizing: border-box;
                        border-radius: 4px;
                        outline: none;
                        transition: var(--transition);
                    }
                    input:focus, select:focus {
                        border-color: var(--vscode-focusBorder);
                    }
                    .help-text {
                        font-size: 11px;
                        color: var(--vscode-descriptionForeground);
                        margin-top: 6px;
                        line-height: 1.4;
                    }
                    button.primary {
                        background: var(--vscode-button-background);
                        color: var(--vscode-button-foreground);
                        border: none;
                        padding: 12px;
                        cursor: pointer;
                        width: 100%;
                        font-weight: 600;
                        border-radius: 4px;
                        transition: var(--transition);
                    }
                    button.primary:hover {
                        background: var(--vscode-button-hoverBackground);
                        filter: brightness(1.1);
                    }

                    /* Chat Tab Specifics */
                    #chat-header {
                        padding: 12px 16px;
                        border-bottom: 1px solid var(--vscode-panel-border);
                        background: var(--vscode-editor-background);
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    #chat-history {
                        flex: 1;
                        overflow-y: auto;
                        padding: 16px;
                        display: flex;
                        flex-direction: column;
                        gap: 16px;
                    }
                    .message {
                        padding: 12px;
                        border-radius: var(--border-radius);
                        max-width: 85%;
                        font-size: 13.5px;
                        line-height: 1.5;
                        position: relative;
                        animation: slideIn 0.2s ease-out;
                    }
                    @keyframes slideIn {
                        from { opacity: 0; transform: scale(0.95); }
                        to { opacity: 1; transform: scale(1); }
                    }
                    .message.user {
                        background-color: var(--vscode-button-background);
                        color: var(--vscode-button-foreground);
                        align-self: flex-end;
                        border-bottom-right-radius: 2px;
                    }
                    .message.assistant {
                        background-color: var(--vscode-editor-background);
                        border: 1px solid var(--vscode-panel-border);
                        align-self: flex-start;
                        border-bottom-left-radius: 2px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        width: 100%; /* Allows pre/code blocks to expand */
                    }
                    .message.assistant p {
                        margin-top: 0;
                    }
                    .message.assistant p:last-child {
                        margin-bottom: 0;
                    }
                    .message.system {
                        font-style: italic;
                        opacity: 0.7;
                        align-self: center;
                        font-size: 11px;
                        background: var(--glass-bg);
                        padding: 4px 12px;
                        border-radius: 12px;
                        border: none;
                        max-width: 100%;
                    }
                    .message.tool {
                        background-color: var(--vscode-textCodeBlock-background);
                        border: 1px solid var(--vscode-panel-border);
                        align-self: flex-start;
                        font-family: var(--vscode-editor-font-family);
                        font-size: 12px;
                        white-space: pre-wrap;
                        color: var(--vscode-debugTokenEditor-string);
                        max-width: 95%;
                    }
                    #chat-input-container {
                        padding: 16px;
                        border-top: 1px solid var(--vscode-panel-border);
                        background-color: var(--vscode-editor-background);
                        display: flex;
                        flex-direction: column;
                        gap: 10px;
                    }
                    #chat-input {
                        width: 100%;
                        background: var(--vscode-input-background);
                        color: var(--vscode-input-foreground);
                        border: 1px solid var(--vscode-input-border);
                        padding: 12px;
                        resize: none;
                        border-radius: 6px;
                        font-family: inherit;
                        font-size: 13px;
                        outline: none;
                        box-sizing: border-box;
                    }
                    #chat-input:focus {
                        border-color: var(--vscode-focusBorder);
                    }
                    #send-button {
                        background: var(--vscode-button-background);
                        color: var(--vscode-button-foreground);
                        border: none;
                        padding: 8px 16px;
                        cursor: pointer;
                        border-radius: 4px;
                        font-weight: 600;
                        align-self: flex-end;
                        transition: var(--transition);
                    }
                    #send-button:hover {
                        background: var(--vscode-button-hoverBackground);
                    }
            </head>
            <body>
                <div class="tabs">
                    <div class="tab active" data-target="chat-content">Chat</div>
                    <div class="tab" data-target="settings-content">Settings</div>
                </div>

                <!-- Chat Content -->
                <div id="chat-content" class="tab-content active">
                    <div id="chat-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border-bottom: 1px solid var(--vscode-panel-border);">
                        <strong>Chat Session</strong>
                        <button id="clear-chat-btn" style="background: transparent; color: var(--vscode-errorForeground); border: 1px solid var(--vscode-errorForeground); padding: 4px 8px; border-radius: 4px; cursor: pointer;">Clear Chat</button>
                    </div>
                    <div id="chat-history"></div>
                    <div id="chat-input-container">
                        <textarea id="chat-input" rows="3" placeholder="Ask BroxLab AI something..."></textarea>
                        <button id="send-button">Send</button>
                    </div>
                </div>

                <!-- Settings Content -->
                <div id="settings-content" class="tab-content">
                    <h2>Providers</h2>
                    
                    <div class="setting-group">
                        <label>Configuration Profile</label>
                        <select disabled>
                            <option>default (Active)</option>
                        </select>
                        <div class="help-text">Save different API configurations to quickly switch.</div>
                    </div>

                    <div class="setting-group">
                        <label>API Provider</label>
                        <select disabled>
                            <option>OpenRouter</option>
                        </select>
                    </div>

                    <div class="setting-group">
                        <label>OpenRouter API Key</label>
                        <input type="password" id="api-key-input" placeholder="sk-or-v1-..." />
                    </div>

                    <div class="setting-group">
                        <label>Approval Mode</label>
                        <select id="approval-mode-input">
                            <option value="Auto Approve">Auto Approve (Fastest, Danger)</option>
                            <option value="Ask Every Time">Ask Every Time (Safest)</option>
                            <option value="Ask Once Per Session">Ask Once Per Session</option>
                        </select>
                        <div class="help-text">Configure when the agent should ask for confirmation before executing file edits or terminal commands.</div>
                    </div>

                    <div class="setting-group">
                        <label>Model</label>
                        <div style="display: flex; gap: 8px;">
                            <select id="model-select" style="flex: 1; padding: 10px; background: var(--vscode-input-background); color: var(--vscode-input-foreground); border: 1px solid var(--vscode-input-border); border-radius: 4px; outline: none;">
                                <option value="anthropic/claude-3.7-sonnet">Loading models...</option>
                            </select>
                            <button id="refresh-models-btn" title="Refresh Models" style="padding: 0 12px; border-radius: 4px; border: 1px solid var(--vscode-input-border); background: var(--glass-bg); color: var(--vscode-foreground); cursor: pointer;">↻</button>
                        </div>
                        <div class="help-text">Choose the model to power BroxLab AI. Recommended: openai/gpt-4o, anthropic/claude-3.7-sonnet.</div>
                    </div>

                    <div class="setting-group">
                        <label>Model Reasoning Effort</label>
                        <select>
                            <option>None (Default)</option>
                            <option>Low</option>
                            <option>Medium</option>
                            <option>High</option>
                        </select>
                        <div class="help-text">Note: Reasoning effort is only supported by specific reasoning models (e.g. o1, o3-mini).</div>
                    </div>

                    <div class="setting-group">
                        <label>Custom Prompt (Enhancement Template)</label>
                        <textarea id="custom-prompt-input" rows="4" style="width: 100%; background: var(--vscode-input-background); color: var(--vscode-input-foreground); border: 1px solid var(--vscode-input-border); border-radius: 4px; padding: 10px; font-family: inherit; font-size: 12px; resize: vertical;" placeholder="Generate an enhanced version..."></textarea>
                        <div class="help-text">Instruction used to transform your prompt before processing. Use \${userInput} as the placeholder. Leave empty to disable.</div>
                    </div>

                    <button class="primary" id="save-settings-btn">Save Settings</button>
                </div>

                <script>
                    const vscode = acquireVsCodeApi();
                    
                    // Configure Marked & Highlight.js
                    const renderer = new marked.Renderer();
                    renderer.code = function(code, language) {
                        const validLanguage = hljs.getLanguage(language) ? language : 'plaintext';
                        const highlighted = hljs.highlight(code, { language: validLanguage }).value;
                        const encoded = encodeURIComponent(code);
                        return \`<div class="code-block" style="position:relative; margin: 10px 0; border-radius: 6px; overflow: hidden; border: 1px solid var(--vscode-panel-border);">
                            <div class="code-header" style="display: flex; justify-content: space-between; padding: 6px 10px; background: var(--vscode-editor-background); border-bottom: 1px solid var(--vscode-panel-border); font-size: 11px; opacity: 0.9;">
                                <span style="text-transform: uppercase; font-weight: 600;">\${language || 'text'}</span>
                                <div style="display:flex; gap: 12px;">
                                    <a href="#" onclick="copyChatCode(this, decodeURIComponent('\${encoded}')); return false;" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 4px;">Copy</a>
                                    <a href="#" onclick="vscode.postMessage({ type: 'insertCode', text: decodeURIComponent('\${encoded}') }); return false;" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 4px;">Insert at Cursor</a>
                                    <a href="#" onclick="vscode.postMessage({ type: 'replaceCode', text: decodeURIComponent('\${encoded}') }); return false;" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 4px;">Replace Selection</a>
                                </div>
                            </div>
                            <pre style="margin: 0; padding: 12px; overflow-x: auto; background: var(--vscode-textCodeBlock-background);"><code class="hljs \${language}">\${highlighted}</code></pre>
                        </div>\`;
                    };
                    marked.setOptions({ renderer });

                    function copyChatCode(element, text) {
                        navigator.clipboard.writeText(text);
                        const originalText = element.innerText;
                        element.innerText = 'Copied!';
                        setTimeout(() => { element.innerText = originalText; }, 2000);
                    }

                    // Tab Switching
                    document.querySelectorAll('.tab').forEach(tab => {
                        tab.addEventListener('click', () => {
                            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                            
                            tab.classList.add('active');
                            document.getElementById(tab.dataset.target).classList.add('active');
                        });
                    });

                    // Elements
                    const chatHistory = document.getElementById('chat-history');
                    const chatInput = document.getElementById('chat-input');
                    const sendBtn = document.getElementById('send-button');
                    
                    const apiKeyInput = document.getElementById('api-key-input');
                    const modelSelect = document.getElementById('model-select');
                    const approvalModeInput = document.getElementById('approval-mode-input');
                    const customPromptInput = document.getElementById('custom-prompt-input');
                    const saveBtn = document.getElementById('save-settings-btn');
                    const refreshModelsBtn = document.getElementById('refresh-models-btn');

                    let messageHistory = [];
                    let currentModel = 'anthropic/claude-3.7-sonnet';

                    // Load initial config
                    window.addEventListener('message', event => {
                        const message = event.data;
                        switch (message.type) {
                            case 'settingsLoaded':
                                apiKeyInput.value = message.apiKey;
                                currentModel = message.model;
                                modelSelect.value = currentModel;
                                approvalModeInput.value = message.approvalMode || 'Ask Every Time';
                                customPromptInput.value = message.customPrompt || '';
                                
                                // Restore History
                                if (message.history && message.history.length > 0) {
                                    messageHistory = message.history;
                                    chatHistory.innerHTML = ''; // Clear existing
                                    messageHistory.forEach(msg => {
                                        if (msg.role === 'user') appendMessage('user', msg.content);
                                        else if (msg.role === 'assistant' && msg.content) appendMessage('assistant', msg.content);
                                        else if (msg.role === 'tool') appendMessage('tool', msg.name + '\\n' + msg.content);
                                    });
                                }

                                // Trigger model fetch
                                vscode.postMessage({ type: 'fetchModels' });
                                break;
                            case 'modelsLoaded':
                                modelSelect.innerHTML = '';
                                let found = false;
                                message.models.forEach(m => {
                                    const opt = document.createElement('option');
                                    opt.value = m.id;
                                    opt.textContent = m.name || m.id;
                                    if (m.id === currentModel) found = true;
                                    modelSelect.appendChild(opt);
                                });
                                if (!found && currentModel) {
                                    const opt = document.createElement('option');
                                    opt.value = currentModel;
                                    opt.textContent = currentModel + ' (Custom)';
                                    modelSelect.appendChild(opt);
                                }
                                modelSelect.value = currentModel;
                                break;
                            case 'addMessage':
                                appendMessage(message.message.role, message.message.content);
                                break;
                            case 'agentUpdate':
                                handleAgentUpdate(message.update);
                                break;
                        }
                    });

                    // Tell backend we're ready
                    vscode.postMessage({ type: 'webviewLoaded' });

                    // Actions
                    refreshModelsBtn.addEventListener('click', () => {
                        modelSelect.innerHTML = '<option>Loading models...</option>';
                        vscode.postMessage({ type: 'fetchModels' });
                    });

                    saveBtn.addEventListener('click', () => {
                        vscode.postMessage({
                            type: 'saveSettings',
                            apiKey: apiKeyInput.value,
                            model: modelSelect.value,
                            approvalMode: approvalModeInput.value,
                            customPrompt: customPromptInput.value
                        });
                    });

                    document.getElementById('clear-chat-btn').addEventListener('click', () => {
                        messageHistory = [];
                        chatHistory.innerHTML = '';
                        vscode.postMessage({ type: 'clearHistory' });
                    });

                    sendBtn.addEventListener('click', sendMessage);
                    chatInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            sendMessage();
                        }
                    });

                    function sendMessage() {
                        const text = chatInput.value.trim();
                        if (!text) return;

                        vscode.postMessage({
                            type: 'sendMessage',
                            text: text,
                            history: messageHistory
                        });

                        messageHistory.push({ role: 'user', content: text });
                        chatInput.value = '';
                    }

                    function appendMessage(role, content) {
                        const div = document.createElement('div');
                        div.className = 'message ' + role;
                        if (role === 'assistant') {
                            div.innerHTML = marked.parse(content);
                        } else {
                            div.innerText = content;
                        }
                        chatHistory.appendChild(div);
                        chatHistory.scrollTop = chatHistory.scrollHeight;
                    }

                    let currentResponseDiv = null;

                    function handleAgentUpdate(update) {
                        if (update.type === 'progress') {
                            appendMessage('system', update.text);
                        } else if (update.type === 'tool') {
                            appendMessage('tool', update.name + '\n' + update.result);
                            messageHistory.push({ role: 'assistant', tool_calls: [ { function: { name: update.name } } ] });
                            messageHistory.push({ role: 'tool', content: update.result, name: update.name });
                        } else if (update.type === 'text') {
                            appendMessage('assistant', update.text);
                            messageHistory.push({ role: 'assistant', content: update.text });
                        } else if (update.type === 'error') {
                            appendMessage('system', 'Error: ' + update.text);
                        }
                    }
                </script>
            </body>
            </html>`;
    }
}
exports.BroxLabViewProvider = BroxLabViewProvider;
//# sourceMappingURL=BroxLabViewProvider.js.map