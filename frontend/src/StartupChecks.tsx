import {useGetMe} from "./queries/useGetMe.ts";
import {useEffect} from "react";
import {getClientLocale, getSupportedLocale, setLocaleCookie} from "./locales.ts";

export const StartupChecks = () => {
    const meQuery = useGetMe();
    const userLocale = meQuery.data?.locale;

    useEffect(() => {
        if (!meQuery.isSuccess || !userLocale) {
            return;
        }

        const locale = getSupportedLocale(userLocale);
        const localeChanged = getClientLocale() !== locale;
        setLocaleCookie(locale);

        if (localeChanged) {
            window.location.reload();
        }
    }, [meQuery.isSuccess, userLocale]);

    return <></>;
}
