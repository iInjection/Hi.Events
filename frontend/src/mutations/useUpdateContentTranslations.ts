import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {ContentTranslationInput, contentTranslationClient} from "../api/content-translation.client.ts";
import {GET_CONTENT_TRANSLATIONS_QUERY_KEY} from "../queries/useGetContentTranslations.ts";

export const useUpdateContentTranslations = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, locale, translations}: {
            eventId: IdParam,
            locale: string,
            translations: ContentTranslationInput[],
        }) => contentTranslationClient.upsert(eventId, locale, translations),

        onSuccess: (response, variables) => {
            queryClient.setQueryData([GET_CONTENT_TRANSLATIONS_QUERY_KEY, variables.eventId], response.data);
        }
    });
}
