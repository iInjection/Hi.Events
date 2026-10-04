import {useState} from "react";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {ActionIcon, Button, Group, Stack, Text, Tooltip} from "@mantine/core";
import {IconEdit} from "@tabler/icons-react";
import {Card} from "../../../common/Card";
import {QuestionInput} from "../../../common/CheckoutQuestion";
import {useGetBuyerQuestionAnswers} from "../../../../queries/useGetBuyerQuestionAnswers.ts";
import {useSaveBuyerQuestionAnswer} from "../../../../mutations/useSaveBuyerQuestionAnswer.ts";
import {BuyerAnswerValue, BuyerEditableAnswer} from "../../../../api/self-service.client.ts";
import {formatAnswer} from "../../../../utilites/questionHelper.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {IdParam} from "../../../../types.ts";

const answerKey = (item: BuyerEditableAnswer) => `${item.question_id}:${item.attendee_id ?? ''}:${item.product_id ?? ''}`;

const displayAnswer = (item: BuyerEditableAnswer): string => {
    const toLabel = (value: string) => {
        const index = item.options.indexOf(value);
        return index >= 0 && item.option_labels?.[index] ? item.option_labels[index] : value;
    };

    if (Array.isArray(item.answer)) {
        return formatAnswer(item.answer.map(toLabel));
    }
    if (typeof item.answer === 'string') {
        return toLabel(item.answer);
    }
    return formatAnswer(item.answer);
};

const AnswerEditor = ({item, eventId, orderShortId, onDone}: {
    item: BuyerEditableAnswer,
    eventId: IdParam,
    orderShortId: string,
    onDone: () => void,
}) => {
    const mutation = useSaveBuyerQuestionAnswer();
    const isAddress = item.type === 'ADDRESS';
    const emptyAnswer = item.type === 'CHECKBOX' ? [] : '';
    const form = useForm({
        initialValues: isAddress
            ? {answer: item.answer ?? {}}
            : {answer: {answer: item.answer ?? emptyAnswer}},
    });

    const submit = (values: { answer: any }) => {
        const answer: BuyerAnswerValue = isAddress ? values.answer : values.answer?.answer;
        mutation.mutate({
            eventId,
            orderShortId,
            data: {question_id: item.question_id, attendee_id: item.attendee_id, product_id: item.product_id, answer},
        }, {
            onSuccess: () => {
                showSuccess(t`Answer updated successfully.`);
                onDone();
            },
            onError: (error: any) => {
                if (error?.response?.status === 429) {
                    showError(t`Rate limit exceeded. Please try again later.`);
                    return;
                }
                showError(error?.response?.data?.errors?.answer?.[0] ?? t`Failed to update answer.`);
            },
        });
    };

    return (
        <form onSubmit={form.onSubmit(submit)}>
            <QuestionInput
                question={{
                    id: item.question_id,
                    title: item.title,
                    description: item.description ?? undefined,
                    type: item.type,
                    options: item.options,
                    option_labels: item.option_labels,
                    required: item.required,
                }}
                name="answer"
                form={form}
            />
            <Group gap="xs" mt="sm">
                <Button type="submit" size="xs" loading={mutation.isPending}>{t`Save`}</Button>
                <Button size="xs" variant="subtle" onClick={onDone}>{t`Cancel`}</Button>
            </Group>
        </form>
    );
};

export const BuyerQuestionAnswers = ({eventId, orderShortId, headingClassName}: {
    eventId: IdParam,
    orderShortId: string,
    headingClassName?: string,
}) => {
    const {data: items} = useGetBuyerQuestionAnswers(eventId, orderShortId);
    const [editingKey, setEditingKey] = useState<string | null>(null);

    if (!items || items.length === 0) {
        return null;
    }

    return (
        <>
            <h1 className={headingClassName}>{t`Questions`}</h1>
            <Card style={{marginBottom: '40px'}}>
                <Stack gap="md">
                    {items.map(item => {
                        const key = answerKey(item);
                        const context = [item.attendee_name, item.product_title].filter(Boolean).join(' · ');

                        if (editingKey === key) {
                            return (
                                <div key={key}>
                                    {context && <Text size="xs" c="dimmed">{context}</Text>}
                                    <AnswerEditor item={item} eventId={eventId} orderShortId={orderShortId}
                                                  onDone={() => setEditingKey(null)}/>
                                </div>
                            );
                        }

                        const answer = displayAnswer(item);
                        return (
                            <Group key={key} justify="space-between" wrap="nowrap" align="flex-start">
                                <div>
                                    <Text size="sm" fw={600}>{item.title}</Text>
                                    {context && <Text size="xs" c="dimmed">{context}</Text>}
                                    <Text size="sm" c={answer ? undefined : 'dimmed'}>{answer || '—'}</Text>
                                </div>
                                <Tooltip label={t`Edit Answer`} withArrow>
                                    <ActionIcon variant="subtle" aria-label={t`Edit Answer`}
                                                onClick={() => setEditingKey(key)}>
                                        <IconEdit size={16}/>
                                    </ActionIcon>
                                </Tooltip>
                            </Group>
                        );
                    })}
                </Stack>
            </Card>
        </>
    );
};
