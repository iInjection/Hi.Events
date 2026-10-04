import {useEffect, useMemo, useState} from "react";
import {NavLink, useParams} from "react-router";
import {t} from "@lingui/macro";
import {i18n} from "@lingui/core";
import {
    Anchor,
    Badge,
    Button,
    Group,
    MultiSelect,
    SegmentedControl,
    Select,
    Stack,
    Text,
    Textarea,
    TextInput
} from "@mantine/core";
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

const EVENT_DETAIL_FIELDS: Array<{ type: TranslatableContentType, field: string }> = [
    {type: 'event', field: 'title'},
    {type: 'event', field: 'description'},
    {type: 'event_setting', field: 'pre_checkout_message'},
    {type: 'event_setting', field: 'post_checkout_message'},
    {type: 'event_setting', field: 'continue_button_text'},
    {type: 'event_setting', field: 'get_tickets_button_text'},
    {type: 'event_setting', field: 'offline_payment_instructions'},
    {type: 'event_setting', field: 'online_event_connection_details'},
];

const BUTTON_TEXT_FIELDS = ['continue_button_text', 'get_tickets_button_text'];

type SectionRow = { item: TranslatableContentItem } | { type: TranslatableContentType, field: string };

const withEmptyEventDetails = (sectionItems: TranslatableContentItem[]): SectionRow[] => [
    ...EVENT_DETAIL_FIELDS.map(({type, field}) => {
        const item = sectionItems.find(candidate => candidate.type === type && candidate.field === field);
        return item ? {item} : {type, field};
    }),
    ...sectionItems
        .filter(item => !EVENT_DETAIL_FIELDS.some(({type, field}) => item.type === type && item.field === field))
        .map(item => ({item})),
];

const fieldLabel = ({type, field}: { type: TranslatableContentType, field: string }): string => {
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

    return labels[`${type}.${field}`] ?? field;
};

const sections = (): Array<{ title: string, types: TranslatableContentType[] }> => [
    {title: t`Event Details`, types: ['event', 'event_setting']},
    {title: t`Tickets & Products`, types: ['product_category', 'product', 'product_price']},
    {title: t`Registration Questions`, types: ['question']},
];

const SettingsCard = ({eventId, settings}: { eventId: string, settings: EventTranslationSettings | null }) => {
    const mutation = useUpdateEventTranslationSettings();
    const defaultSourceLocale = getSupportedLocale(i18n.locale || 'en');
    const defaultFallbackLocale = defaultSourceLocale === 'en' ? null : 'en';
    const [sourceLocale, setSourceLocale] = useState(settings?.source_locale ?? defaultSourceLocale);
    const [locales, setLocales] = useState<string[]>(
        settings?.locales ?? (defaultFallbackLocale ? [defaultFallbackLocale] : [])
    );
    const [fallbackLocale, setFallbackLocale] = useState<string | null>(
        settings ? settings.fallback_locale : defaultFallbackLocale
    );

    const allLocales = localeOptions();
    const otherLocales = allLocales.filter(option => option.value !== sourceLocale);
    const fallbackOptions = allLocales.map(option => option.value === sourceLocale
        ? {...option, label: `${option.label} ${t`(original texts)`}`}
        : option);

    const save = () => {
        mutation.mutate({
            eventId,
            settings: {source_locale: sourceLocale, fallback_locale: fallbackLocale, locales},
        }, {
            onSuccess: () => showSuccess(t`Languages saved`),
            onError: () => showError(t`Something went wrong. Please try again.`),
        });
    };

    return (
        <Card>
            <Stack gap="sm" className={classes.compactInputs}>
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
                        if (fallbackLocale === value) {
                            setFallbackLocale(null);
                        }
                    }}
                />
                <MultiSelect
                    label={t`Translate into`}
                    data={otherLocales}
                    value={locales}
                    onChange={(value) => {
                        setLocales(value);
                        if (fallbackLocale && !value.includes(fallbackLocale)) {
                            setFallbackLocale(null);
                        }
                    }}
                    searchable
                />
                <Select
                    label={t`Fallback for other languages`}
                    description={t`Visitors whose language has no translation see this language.`}
                    data={fallbackOptions}
                    value={fallbackLocale ?? sourceLocale}
                    allowDeselect={false}
                    onChange={(value) => {
                        if (!value || value === sourceLocale) {
                            setFallbackLocale(null);
                            return;
                        }
                        setFallbackLocale(value);
                        if (!locales.includes(value)) {
                            setLocales(current => [...current, value]);
                        }
                    }}
                    searchable
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
            <Stack gap={4}>
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
        <Stack gap="md">
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
                const rows: SectionRow[] = section.types.includes('event')
                    ? withEmptyEventDetails(sectionItems)
                    : sectionItems.map(item => ({item}));

                if (rows.length === 0) {
                    return null;
                }

                return (
                    <Card key={section.title}>
                        <h3 className={classes.sectionTitle}>{section.title}</h3>
                        <Stack gap="md" className={classes.compactInputs}>
                            {rows.map(row => {
                                if (!('item' in row)) {
                                    return (
                                        <div key={`${row.type}:${row.field}`} className={classes.item}>
                                            <Text fw={600} size="sm">{fieldLabel(row)}</Text>
                                            <Text size="sm" c="dimmed">
                                                {BUTTON_TEXT_FIELDS.includes(row.field)
                                                    ? t`Not set. Visitors see the default button text in their own language.`
                                                    : t`No original text yet. Add it in the event settings, then translate it here.`}
                                                {' '}
                                                {!BUTTON_TEXT_FIELDS.includes(row.field) && (
                                                    <Anchor component={NavLink} to={`/manage/event/${eventId}/settings`} size="sm">
                                                        {t`Event Settings`}
                                                    </Anchor>
                                                )}
                                            </Text>
                                        </div>
                                    );
                                }

                                const item = row.item;
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
                <Stack gap="md" className={classes.page}>
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
