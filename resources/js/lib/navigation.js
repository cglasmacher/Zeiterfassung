import {
    LayoutDashboard,
    Monitor,
    Clock3,
    CalendarDays,
    CalendarRange,
    Timer,
    FilePenLine,
    Wallet,
    ReceiptText,
    Download,
    Settings2,
    Ticket,
    BookOpenCheck,
    ClipboardCheck,
    Armchair,
    CalendarCheck,
} from "lucide-react";

export const navigation = [
    { label: "Betrieb", items: [
        { route: "dashboard", label: "Übersicht", icon: LayoutDashboard },
        { route: "info.admin", label: "Infosystem", icon: Monitor },
        { route: "haccp.dashboard", label: "HACCP", icon: ClipboardCheck },
        { route: "reservations.book", label: "Reservierungen", icon: CalendarCheck },
        { route: "reservations.floor-plan", label: "Tischplan-Builder", icon: Armchair },
        { route: "reservations.management", label: "Reservierungsregeln", icon: Settings2 },
    ]},
    { label: "Kasse & Abrechnung", items: [
        { route: "cash.handovers", label: "Schichtübergabe", icon: Wallet },
        { route: "cash.days", label: "Abrechnung", icon: ReceiptText },
        { route: "cash.vouchers", label: "Gutscheinverkauf", icon: Ticket },
    ]},
    { label: "Buchhaltung", items: [
        { route: "accounting.documents", label: "Eingangsbelege", icon: BookOpenCheck },
    ]},
    { label: "Team & Planung", items: [
        { route: "timetracking.planner", label: "Dienstplan", icon: CalendarRange },
        { route: "timetracking.daily", label: "Tagesübersicht", icon: Clock3 },
        { route: "timetracking.monthly", label: "Monatsübersicht", icon: CalendarDays },
        { route: "timetracking.manual", label: "Zeiten erfassen", icon: Timer },
        { route: "timetracking.manipulation", label: "Zeitkorrekturen", icon: FilePenLine },
    ]},
    { label: "Verwaltung", items: [
        { route: "timetracking.exports", label: "Lohnexport", icon: Download },
        { route: "timetracking.settings", label: "Einstellungen", icon: Settings2 },
    ]},
];

const haccpNavigation = [
    { route: "haccp.dashboard", label: "Heute" },
    { route: "haccp.operations", label: "Kontrollen & Reinigung" },
    { route: "haccp.checklists", label: "Checklisten" },
    { route: "haccp.compliance", label: "Abweichungen & Wartungen" },
    { route: "haccp.documentation", label: "Dokumentation" },
    { route: "haccp.corrections", label: "Korrekturen & Nachträge" },
    { route: "haccp.management", label: "Verwaltung" },
    { route: "haccp.qr-labels", label: "QR-Etiketten" },
];

export const currentNavigation = (pathname) => {
    const haccpItem = haccpNavigation.find(
        (item) => new URL(route(item.route), window.location.origin).pathname === pathname,
    );
    if (haccpItem) return {
        ...haccpItem,
        group: "HACCP",
        sectionRoute: "haccp.dashboard",
        backRoute: haccpItem.route === "haccp.dashboard" ? null : "haccp.dashboard",
    };

    for (const group of navigation) {
        const item = group.items.find((item) => new URL(route(item.route), window.location.origin).pathname === pathname);
        if (item) return { ...item, group: group.label };
    }
    return { label: "Mein Profil", group: "Konto" };
};
export const formatHours = (value) => Number(value || 0).toLocaleString("de-DE", { maximumFractionDigits: 2 });
export const formatMoney = (cents) => (Number(cents || 0) / 100).toLocaleString("de-DE", { style: "currency", currency: "EUR" });
export const formatTime = (value) => new Date(value).toLocaleTimeString("de-DE", { timeZone: "Europe/Berlin", hour: "2-digit", minute: "2-digit" });
export const formatDate = (value) => new Date(value).toLocaleDateString("de-DE", { timeZone: "Europe/Berlin", day: "2-digit", month: "2-digit", year: "numeric" });

export const mobileNavigation = [
    { route: "dashboard", label: "Übersicht", icon: LayoutDashboard },
    { route: "haccp.dashboard", label: "HACCP", icon: ClipboardCheck },
    { route: "timetracking.planner", label: "Dienstplan", icon: CalendarRange },
    { route: "cash.handovers", label: "Schichtübergabe", icon: Wallet },
    { route: "cash.days", label: "Abrechnung", icon: ReceiptText },
    { route: "cash.vouchers", label: "Gutscheine", icon: Ticket },
];

