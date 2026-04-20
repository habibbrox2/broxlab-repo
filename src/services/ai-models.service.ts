import { query } from '../config/database';
import logger from '../utils/logger';

export interface AIProviderRow {
    provider_name?: string;
    display_name?: string;
    supported_models?: string | Record<string, string>;
    extra_settings?: string | Record<string, any>;
}

export interface ProviderModel {
    id: string;
    name: string;
    default?: boolean;
    supports_multimodal?: boolean;
}

export interface ProviderModelResponse {
    providers: Record<string, ProviderModel[]>;
    providerMeta: Record<string, { supports_multimodal: boolean }>;
}

function parseJsonValue<T>(value: unknown, fallback: T): T {
    if (!value) {
        return fallback;
    }

    if (typeof value === 'string') {
        try {
            return JSON.parse(value) as T;
        } catch (error) {
            logger.warn('Failed to parse JSON value', error);
            return fallback;
        }
    }

    if (typeof value === 'object') {
        return value as T;
    }

    return fallback;
}

function normalizeModels(models: Record<string, string> | unknown): ProviderModel[] {
    const normalized = parseJsonValue<Record<string, string>>(models, {});

    const list: ProviderModel[] = Object.entries(normalized).map(([id, name]) => ({
        id: String(id),
        name: String(name),
    }));

    if (list.length > 0) {
        list[0].default = true;
    }

    return list;
}

function parseMultimodalFlag(extraSettings: unknown): boolean {
    const extra = parseJsonValue<Record<string, any>>(extraSettings, {});
    return Boolean(extra.supports_multimodal || extra.supports_rich_content);
}

export class AIModelService {
    async getActiveProviderModels(): Promise<ProviderModelResponse> {
        const rows = await query<AIProviderRow>(
            'SELECT provider_name, display_name, supported_models, extra_settings FROM ai_providers WHERE is_active = 1 ORDER BY sort_order'
        );

        const response: ProviderModelResponse = {
            providers: {},
            providerMeta: {},
        };

        for (const row of rows) {
            const providerName = row.provider_name?.trim();
            if (!providerName) {
                continue;
            }

            const models = normalizeModels(row.supported_models);
            response.providers[providerName] = models;
            response.providerMeta[providerName] = {
                supports_multimodal: parseMultimodalFlag(row.extra_settings),
            };
        }

        return response;
    }

    async getProviderModels(providerName: string): Promise<{ models: ProviderModel[]; supportsMultimodal: boolean } | null> {
        const rows = await query<AIProviderRow>(
            'SELECT provider_name, display_name, supported_models, extra_settings FROM ai_providers WHERE provider_name = ? AND is_active = 1 LIMIT 1',
            [providerName]
        );

        const provider = rows[0];
        if (!provider) {
            return null;
        }

        const models = normalizeModels(provider.supported_models);
        if (models.length === 0) {
            return {
                models,
                supportsMultimodal: parseMultimodalFlag(provider.extra_settings),
            };
        }

        return {
            models: models.map((model) => ({
                ...model,
                supports_multimodal: parseMultimodalFlag(provider.extra_settings),
            })),
            supportsMultimodal: parseMultimodalFlag(provider.extra_settings),
        };
    }
}

export const aiModelService = new AIModelService();
