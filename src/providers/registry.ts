import { OllamaProvider } from './ollama.provider'
import { OpenRouterProvider } from './openrouter.provider'
import { config } from '../config/index'

export const providers = {
  ollama: new OllamaProvider(),
  openrouter: new OpenRouterProvider(config.ai.openrouter.apiKey || '', 'openrouter/auto'),
}
