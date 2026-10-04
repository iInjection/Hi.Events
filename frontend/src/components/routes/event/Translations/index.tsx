import {useEffect, useMemo, useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {i18n} from "@lingui/core";
import {Badge, Button, Group, MultiSelect, SegmentedControl, Select, Stack, Text, Textarea, TextInput} from "@mantine/core";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {Card} from "../../../common/Card";
import {Editor} from "../../../common/Editor";
import {UserGeneratedContent} from "../../../common/UserGeneratedContent";
import {LoadingMask} from "../../../common/LoadingMask";
import {useGetContentTranslations} from "../../../../queries/useGetContentTranslations.ts";
import {useUpdateContentTranslations} from "../../../../mutations/useUpdateContentTranslations.ts";
import {useUpdateEventTranslationSettings} from "../../../../mutations/useUpdateEventTranslationSettings.ts";
import {
    ContentTranslationInput,
    EventTranslationSettings,
    TranslatableContentItem,
    TranslatableContentType,
    TranslationValue,
} from "../../../../api/content-translation.client.ts";
import {getSupportedLocale, localeToFlagEmojiMap, localeToNameMap, SupportedLocales} from "../../../../locales.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import classes from "./Translations.module.scss";

const itemKey = (item: TranslatableContentItem) => `${item.type}:${item.id}:${item.field}`;

const isEmptyValue = (value: TranslationValue | undefined): boolean => Array.isArray(value)
    ? value.every(entry => entry.trim() === '')
    : !value || value.replace(/<[^>]*>/g, '').trim() === '';

const isSameValue = (left: TranslationValue | undefined, right: TranslationValue | undefined): boolean => {
    if (isEmptyValue(left) && isEmptyValue(right)) {
        return true;
    }
    return JSON.stringify(left ?? '') === JSON.stringify(right ?? '');
};

const localeOptions = () => Object.keys(localeToNameMap).map(locale => ({
    value: locale,
    label: `${localeToFlagEmojiMap[locale as SupportedLocales]} ${localeToNameMap[locale as SupportedLocales]}`,
}));

const fieldLabel = (item: TranslatableContentItem): string => {
    const labels: Record<string, string> = {
        'event.title': t`Event Title`,
        'event.description': t`Event Description`,
        'event_setting.pre_checkout_message': t`Pre Checkout message`,
        'event_setting.post_checkout_message': t`Post Checkout message`,
        'event_setting.product_page_message': t`Product page message`,
        'event_setting.continue_button_text': t`Continue Button Text`,
        'event_setting.get_tickets_button_text': t`Get Tickets Button Text`,
        'event_setting.offline_payment_instructions': t`Offline Payment Instructions`,
        'event_setting.online_event_connection_details': t`Connection Details`,
        'product_category.name': t`Name`,
        'product_category.description': t`Description`,
        'product_category.no_products_message': t`No products message`,
        'product.title': t`Title`,
        'product.description': t`Description`,
        'product.highlight_message': t`Highlight Message`,
        'product_price.label': t`Label`,
        'question.title': t`Question Title`,
        'question.description': t`Description`,
        'question.options': t`Options`,
    };

    return labels[`${item.type}.${item.field}`] ?? item.field;
};

const sections = (): Array<{ title: string, types: TranslatableContentType[] }> => [
    {title: t`Event Details`, types: ['event', 'event_setting']},
    {title: t`Tickets & Products`, types: ['product_category', 'product', 'product_price']},
    {title: t`Registration Questions`, types: ['question']},
];

const SettingsCard = ({eventId, settings}: { eventId: string, settings: EventTranslationSettings | null }) => {
    const mutation = useUpdateEventTranslationSettings();
    const defaultSourceLocale = getSupportedLocale(i18n.locale || 'en');
    const [sourceLocale, setSourceLocale] = useState(settings?.source_locale ?? defaultSourceLocale);
    const [locales, setLocales] = useState<string[]>(settings?.locales ?? []);
    const [fallbackLocale, setFallbackLocale] = useState<string | null>(
        settings ? settings.fallback_locale : (defaultSourceLocale === 'en' ? null : 'en')
    );

    const allLocales = localeOptions();
    const targetLocales = allLocales.filter(option => option.value !== sourceLocale);
    const fallbackOptions = allLocales.filter(option => locales.includes(option.value));

    const save = () => {
        const fallback = fallbackLocale && locales.includes(fallbackLocale) ? fallbackLocale : null;
        mutation.mutate({
            eventId,
            settings: {source_locale: sourceLocale, fallback_locale: fallback, locales},
        }, {
            onSuccess: () => showSuccess(t`Languages saved`),
            onError: () => showError(t`Something went wrong. Please try again.`),
        });
    };

    return (
        <Card>
            <Stack gap="md">
                <Select
                    label={t`Language of your texts`}
                    data={allLocales}
                    value={sourceLocale}
                    allowDeselect={false}
                    onChange={(value) => {
                        if (!value) {
                            return;
                        }
                        setSourceLocale(value);
                        setLocales(current => current.filter(locale => locale !== value));
                    }}
                />
                <MultiSelect
                    label={t`Translate into`}
                    data={targetLocales}
                    value={locales}
                    onChange={setLocales}
                    searchable
                />
                <Select
                    label={t`Fallback for other languages`}
                    description={t`Visitors whose language has no translation see this language. Without a fallback they see your original texts.`}
                    data={fallbackOptions}
                    value={fallbackLocale && locales.includes(fallbackLocale) ? fallbackLocale : null}
                    onChange={setFallbackLocale}
                    placeholder={t`None`}
                    clearable
                />
                <Group justify="flex-end">
                    <Button onClick={save} loading={mutation.isPending}>{t`Save`}</Button>
                </Group>
            </Stack>
        </Card>
    );
};

const SourcePreview = ({item}: { item: TranslatableContentItem }) => {
    if (Array.isArray(item.source)) {
        return <Text size="sm" c="dimmed">{item.source.join(' · ')}</Text>;
    }
    if (item.format === 'HTML') {
        return <UserGeneratedContent className={classes.htmlSource} html={item.source}/>;
    }
    return <Text size="sm" c="dimmed" className={classes.textSource}>{item.source}</Text>;
};

const TranslationInput = ({item, value, onChange}: {
    item: TranslatableContentItem,
    value: TranslationValue | undefined,
    onChange: (value: TranslationValue) => void,
}) => {
    if (item.format === 'LIST' && Array.isArray(item.source)) {
        const values = Array.isArray(value) ? value : item.source.map(() => '');
        return (
            <Stack gap={6}>
                {item.source.map((option, index) => (
                    <TextInput
                        key={index}
                        aria-label={option}
                        placeholder={option}
                        value={values[index] ?? ''}
                        onChange={(event) => {
                            const next = [...values];
                            next[index] = event.currentTarget.value;
                            onChange(next);
                        }}
                    />
                ))}
            </Stack>
        );
    }

    const stringValue = typeof value === 'string' ? value : '';

    if (item.format === 'HTML') {
        return <Editor value={stringValue} onChange={onChange} editorType="simple"/>;
    }
    if (item.format === 'MULTILINE') {
        return <Textarea autosize minRows={2} value={stringValue}
                         onChange={(event) => onChange(event.currentTarget.value)}/>;
    }
    return <TextInput value={stringValue} onChange={(event) => onChange(event.currentTarget.value)}/>;
};

const TranslationsEditor = ({eventId, items, locales}: {
    eventId: string,
    items: TranslatableContentItem[],
    locales: string[],
}) => {
    const mutation = useUpdateContentTranslations();
    const [activeLocale, setActiveLocale] = useState(locales[0]);
    const [drafts, setDrafts] = useState<Record<string, TranslationValue>>({});

    const locale = locales.includes(activeLocale) ? activeLocale : locales[0];

    useEffect(() => {
        setDrafts(Object.fromEntries(items.map(item => [itemKey(item), item.translations[locale]?.value ?? ''])));
    }, [items, locale]);

    const changedItems = items.filter(item => !isSameValue(drafts[itemKey(item)], item.translations[locale]?.value));

    const save = () => {
        const translations: ContentTranslationInput[] = changedItems.map(item => {
            const value = drafts[itemKey(item)];
            return {type: item.type, id: item.id, field: item.field, value: isEmptyValue(value) ? null : value};
        });

        mutation.mutate({eventId, locale, translations}, {
            onSuccess: () => showSuccess(t`Translations saved`),
            onError: () => showError(t`Something went wrong. Please try again.`),
        });
    };

    const progress = (target: string) => items.filter(item => item.translations[target]).length;

    return (
        <Stack gap="lg">
            <SegmentedControl
                className={classes.localeSwitcher}
                value={locale}
                onChange={(value) => {
                    if (changedItems.length === 0) {
                        setActiveLocale(value);
                    }
                }}
                disabled={changedItems.length > 0}
                data={locales.map(target => ({
                    value: target,
                    label: `${localeToFlagEmojiMap[target as SupportedLocales] ?? ''} ${localeToNameMap[target as SupportedLocales] ?? target} · ${progress(target)}/${items.length}`,
                }))}
            />

            {sections().map(section => {
                const sectionItems = items.filter(item => section.types.includes(item.type));
                if (sectionItems.length === 0) {
                    return null;
                }

                return (
                    <Card key={section.title}>
                        <h3 className={classes.sectionTitle}>{section.title}</h3>
                        <Stack gap="xl">
                            {sectionItems.map(item => {
                                const key = itemKey(item);
                                return (
                                    <div key={`${key}:${locale}`} className={classes.item}>
                                        <Group gap="xs" className={classes.itemHeader}>
                                            <Text fw={600} size="sm">{fieldLabel(item)}</Text>
                                            {item.context && item.context !== item.source && (
                                                <Text size="sm" c="dimmed">{item.context}</Text>
                                            )}
                                            {item.translations[locale]?.is_outdated && (
                                                <Badge color="orange" variant="light" size="sm">{t`Original changed`}</Badge>
                                            )}
                                        </Group>
                                        <div className={classes.source}>
                                            <SourcePreview item={item}/>
                                        </div>
                                        <TranslationInput
                                            item={item}
                                            value={drafts[key]}
                                            onChange={(value) => setDrafts(current => ({...current, [key]: value}))}
                                        />
                                    </div>
                                );
                            })}
                        </Stack>
                    </Card>
                );
            })}

            <div className={classes.saveBar}>
                <Button onClick={save} loading={mutation.isPending} disabled={changedItems.length === 0}>
                    {t`Save Changes`}
                </Button>
            </div>
        </Stack>
    );
};

export const Translations = () => {
    const {eventId} = useParams();
    const {data, isFetched} = useGetContentTranslations(eventId);

    const locales = useMemo(() => data?.settings?.locales ?? [], [data?.settings?.locales]);

    return (
        <PageBody>
            <PageTitle
                subheading={t`Translate your event texts. Visitors see them in their language. Texts without a translation use the fallback language or your original text.`}>
                {t`Translations`}
            </PageTitle>

            {!isFetched && <LoadingMask/>}

            {isFetched && eventId && (
                <Stack gap="lg">
                    <SettingsCard key={JSON.stringify(data?.settings)} eventId={eventId} settings={data?.settings ?? null}/>
                    {locales.length > 0 && data && (
                        <TranslationsEditor eventId={eventId} items={data.items} locales={locales}/>
                    )}
                </Stack>
            )}
        </PageBody>
    );
};

export default Translations;
