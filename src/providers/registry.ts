import { OllamaProvider } from './ollama.provider'
import { OpenRouterProvider } from './openrouter.provider'
import { OpenAIProvider } from './openai.provider'
import { CodexProvider } from './codex.provider'
import { AnthropicProvider } from './anthropic.provider'
import { GeminiProvider } from './gemini.provider'
import { KiloProvider } from './kilo.provider'
import { config } from '../config/index'

const googleProvider = new GeminiProvider(config.ai.google.apiKey || '', 'gemini-pro');

export const providers = {
  ollama: new OllamaProvider(),
  openrouter: new OpenRouterProvider(config.ai.openrouter.apiKey || '', 'meta-llama/llama-3-8b-instruct:free'),
  openai: new OpenAIProvider(config.ai.openai.apiKey || '', config.ai.defaultModel || 'gpt-4o'),
  codex: new CodexProvider(config.ai.openai.apiKey || '', 'code-davinci-002'),
  anthropic: new AnthropicProvider(config.ai.anthropic.apiKey || '', 'claude-3.0'),
  google: googleProvider,
  gemini: googleProvider,
  kilo: new KiloProvider(config.ai.kilo.apiKey || '', config.ai.defaultModel || 'gpt-4o'),
}
