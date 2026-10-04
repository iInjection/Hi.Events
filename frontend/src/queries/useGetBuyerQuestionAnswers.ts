import {useQuery} from "@tanstack/react-query";
import {selfServiceClient} from "../api/self-service.client.ts";
import {IdParam} from "../types.ts";

export const GET_BUYER_QUESTION_ANSWERS_QUERY_KEY = 'getBuyerQuestionAnswers';

export const useGetBuyerQuestionAnswers = (eventId: IdParam, orderShortId: string) => {
    return useQuery({
        queryKey: [GET_BUYER_QUESTION_ANSWERS_QUERY_KEY, eventId, orderShortId],
        queryFn: async () => (await selfServiceClient.getQuestionAnswers(eventId, orderShortId)).data,
        enabled: !!eventId && !!orderShortId,
    });
};
