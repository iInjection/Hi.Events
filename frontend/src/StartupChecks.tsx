import {useGetMe} from "./queries/useGetMe.ts";
import {useEffect} from "react";
import {getClientLocale, getSupportedLocale, setLocaleCookie} from "./locales.ts";

const APPLIED_PROFILE_LOCALE_KEY = 'applied_profile_locale';

const getAppliedProfileLocale = (): string | null => {
    try {
        return window.localStorage.getItem(APPLIED_PROFILE_LOCALE_KEY);
    } catch {
        return null;
    }
};

const setAppliedProfileLocale = (value: string) => {
    try {
        window.localStorage.setItem(APPLIED_PROFILE_LOCALE_KEY, value);
    } catch {
        return;
    }
};

export const StartupChecks = () => {
    const meQuery = useGetMe();
    const userId = meQuery.data?.id;
    const userLocale = meQuery.data?.locale;

    useEffect(() => {
        if (!meQuery.isSuccess || !userLocale) {
            return;
        }

        const locale = getSupportedLocale(userLocale);
        const appliedProfileLocale = `${userId}:${locale}`;

        if (getAppliedProfileLocale() === appliedProfileLocale) {
            return;
        }

        setAppliedProfileLocale(appliedProfileLocale);
        const localeChanged = getClientLocale() !== locale;
        setLocaleCookie(locale);

        if (localeChanged) {
            window.location.reload();
        }
    }, [meQuery.isSuccess, userId, userLocale]);

    return <></>;
}
