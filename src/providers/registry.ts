import { OllamaProvider } from './ollama.provider.js'
import { OpenRouterProvider } from './openrouter.provider.js'

export const providers = {
  ollama: new OllamaProvider(),
  openrouter: new OpenRouterProvider(process.env.OPENROUTER_API_KEY || '', 'openrouter/auto'),
}