import {api} from "./client";
import {GenericDataResponse, IdParam} from "../types";

export type TranslatableContentType =
    'event'
    | 'event_setting'
    | 'product_category'
    | 'product'
    | 'product_price'
    | 'question';

export type TranslatableFieldFormat = 'TEXT' | 'MULTILINE' | 'HTML' | 'LIST';

export type TranslationValue = string | string[];

export interface ContentTranslation {
    value: TranslationValue;
    is_outdated: boolean;
}

export interface TranslatableContentItem {
    type: TranslatableContentType;
    id: number;
    field: string;
    format: TranslatableFieldFormat;
    source: TranslationValue;
    context: string | null;
    translations: Record<string, ContentTranslation>;
}

export interface EventTranslationSettings {
    source_locale: string;
    fallback_locale: string | null;
    locales: string[];
}

export interface EventContentTranslations {
    settings: EventTranslationSettings | null;
    items: TranslatableContentItem[];
}

export interface ContentTranslationInput {
    type: TranslatableContentType;
    id: number;
    field: string;
    value: TranslationValue | null;
}

export const contentTranslationClient = {
    get: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<EventContentTranslations>>(
            `events/${eventId}/content-translations`
        );
        return response.data;
    },
    upsert: async (eventId: IdParam, locale: string, translations: ContentTranslationInput[]) => {
        const response = await api.put<GenericDataResponse<EventContentTranslations>>(
            `events/${eventId}/content-translations`, {locale, translations}
        );
        return response.data;
    },
    updateSettings: async (eventId: IdParam, settings: EventTranslationSettings) => {
        const response = await api.put<GenericDataResponse<EventContentTranslations>>(
            `events/${eventId}/content-translations/settings`, settings
        );
        return response.data;
    },
};
