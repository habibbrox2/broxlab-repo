import { OllamaProvider } from './ollama.provider'
import { OpenRouterProvider } from './openrouter.provider'

export const providers = {
  ollama: new OllamaProvider(),
  openrouter: new OpenRouterProvider(process.env.OPENROUTER_API_KEY || '', 'openrouter/auto'),
}