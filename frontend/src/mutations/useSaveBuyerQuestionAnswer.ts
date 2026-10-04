import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {SaveBuyerAnswerData, selfServiceClient} from "../api/self-service.client.ts";
import {GET_BUYER_QUESTION_ANSWERS_QUERY_KEY} from "../queries/useGetBuyerQuestionAnswers.ts";

export const useSaveBuyerQuestionAnswer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, orderShortId, data}: {
            eventId: IdParam;
            orderShortId: string;
            data: SaveBuyerAnswerData;
        }) => selfServiceClient.saveQuestionAnswer(eventId, orderShortId, data),

        onSuccess: (response, variables) => {
            queryClient.setQueryData(
                [GET_BUYER_QUESTION_ANSWERS_QUERY_KEY, variables.eventId, variables.orderShortId],
                response.data,
            );
        }
    });
}
