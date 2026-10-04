import {useQuery} from "@tanstack/react-query";
import {contentTranslationClient} from "../api/content-translation.client.ts";
import {IdParam} from "../types.ts";

export const GET_CONTENT_TRANSLATIONS_QUERY_KEY = 'getContentTranslations';

export const useGetContentTranslations = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_CONTENT_TRANSLATIONS_QUERY_KEY, eventId],
        queryFn: async () => (await contentTranslationClient.get(eventId)).data,
    });
};
