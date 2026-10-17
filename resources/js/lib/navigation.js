import {
    LayoutDashboard,
    Clock3,
    CalendarDays,
    CalendarRange,
    Timer,
    FilePenLine,
    Wallet,
    ReceiptText,
    Download,
    Settings2,
} from "lucide-react";

export const navigation = [
    {
        label: "Betrieb",
        items: [
            { route: "dashboard", label: "Übersicht", icon: LayoutDashboard },
        ],
    },
    {
        label: "Team & Planung",
        items: [
            {
                route: "timetracking.planner",
                label: "Dienstplan",
                icon: CalendarRange,
            },
            {
                route: "timetracking.daily",
                label: "Tagesübersicht",
                icon: Clock3,
            },
            {
                route: "timetracking.monthly",
                label: "Monatsübersicht",
                icon: CalendarDays,
            },
            {
                route: "timetracking.manual",
                label: "Zeiten erfassen",
                icon: Timer,
            },
            {
                route: "timetracking.manipulation",
                label: "Zeitkorrekturen",
                icon: FilePenLine,
            },
        ],
    },
    {
        label: "Kasse",
        items: [
            { route: "cash.handovers", label: "Schichtübergabe", icon: Wallet },
            { route: "cash.days", label: "Tagesabschluss", icon: ReceiptText },
        ],
    },
    {
        label: "Verwaltung",
        items: [
            {
                route: "timetracking.exports",
                label: "Lohnexport",
                icon: Download,
            },
            {
                route: "timetracking.settings",
                label: "Einstellungen",
                icon: Settings2,
            },
        ],
    },
];

export const currentNavigation = (pathname) => {
    for (const group of navigation) {
        const item = group.items.find(
            (item) =>
                new URL(route(item.route), window.location.origin).pathname ===
                pathname,
        );
        if (item) return { ...item, group: group.label };
    }
    return { label: "Mein Profil", group: "Konto" };
};
export const formatHours = (value) =>
    Number(value || 0).toLocaleString("de-DE", { maximumFractionDigits: 2 });
export const formatMoney = (cents) =>
    (Number(cents || 0) / 100).toLocaleString("de-DE", {
        style: "currency",
        currency: "EUR",
    });
export const formatTime = (value) =>
    new Date(value).toLocaleTimeString("de-DE", {
        timeZone: "Europe/Berlin",
        hour: "2-digit",
        minute: "2-digit",
    });
export const formatDate = (value) =>
    new Date(value).toLocaleDateString("de-DE", {
        timeZone: "Europe/Berlin",
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    });
