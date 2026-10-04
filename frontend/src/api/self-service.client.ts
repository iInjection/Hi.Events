import {publicApi} from "./public-client.ts";
import {GenericDataResponse, IdParam} from "../types.ts";

export type BuyerAnswerValue = string | string[] | Record<string, string> | null;

export interface BuyerEditableAnswer {
    question_id: number;
    title: string;
    description: string | null;
    type: string;
    required: boolean;
    options: string[];
    option_labels: string[] | null;
    attendee_id: number | null;
    attendee_name: string | null;
    product_id: number | null;
    product_title: string | null;
    answer: BuyerAnswerValue;
}

export interface SaveBuyerAnswerData {
    question_id: number;
    attendee_id: number | null;
    product_id: number | null;
    answer: BuyerAnswerValue;
}

export interface SelfServiceUpdateResult {
    success: boolean;
    short_id_changed: boolean;
    new_short_id?: string;
    message: string;
    warning?: string;
    email_sent?: boolean;
}

export interface EditAttendeeData {
    first_name?: string;
    last_name?: string;
    email?: string;
}

export interface EditOrderData {
    first_name?: string;
    last_name?: string;
    email?: string;
}

export const selfServiceClient = {
    editAttendee: async (
        eventId: IdParam,
        orderShortId: string,
        attendeeShortId: string,
        data: EditAttendeeData
    ): Promise<SelfServiceUpdateResult> => {
        const response = await publicApi.patch(
            `/events/${eventId}/order/${orderShortId}/attendees/${attendeeShortId}`,
            data
        );
        return response.data;
    },

    editOrder: async (
        eventId: IdParam,
        orderShortId: string,
        data: EditOrderData
    ): Promise<SelfServiceUpdateResult> => {
        const response = await publicApi.patch(
            `/events/${eventId}/order/${orderShortId}`,
            data
        );
        return response.data;
    },

    resendAttendeeTicket: async (
        eventId: IdParam,
        orderShortId: string,
        attendeeShortId: string
    ): Promise<{ success: boolean; message: string }> => {
        const response = await publicApi.post(
            `/events/${eventId}/order/${orderShortId}/attendees/${attendeeShortId}/resend-ticket`
        );
        return response.data;
    },

    resendOrderConfirmation: async (
        eventId: IdParam,
        orderShortId: string
    ): Promise<{ success: boolean; message: string }> => {
        const response = await publicApi.post(
            `/events/${eventId}/order/${orderShortId}/resend-confirmation`
        );
        return response.data;
    },

    getQuestionAnswers: async (eventId: IdParam, orderShortId: string) => {
        const response = await publicApi.get<GenericDataResponse<BuyerEditableAnswer[]>>(
            `/events/${eventId}/order/${orderShortId}/question-answers`
        );
        return response.data;
    },

    saveQuestionAnswer: async (eventId: IdParam, orderShortId: string, data: SaveBuyerAnswerData) => {
        const response = await publicApi.put<GenericDataResponse<BuyerEditableAnswer[]>>(
            `/events/${eventId}/order/${orderShortId}/question-answers`,
            data
        );
        return response.data;
    },
};
