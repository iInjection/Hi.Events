import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {contentTranslationClient, EventTranslationSettings} from "../api/content-translation.client.ts";
import {GET_CONTENT_TRANSLATIONS_QUERY_KEY} from "../queries/useGetContentTranslations.ts";

export const useUpdateEventTranslationSettings = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, settings}: {
            eventId: IdParam,
            settings: EventTranslationSettings,
        }) => contentTranslationClient.updateSettings(eventId, settings),

        onSuccess: (response, variables) => {
            queryClient.setQueryData([GET_CONTENT_TRANSLATIONS_QUERY_KEY, variables.eventId], response.data);
        }
    });
}
