import React, { useEffect, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import {
    Menu,
    X,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
    ChevronRight,
    UserRound,
    ChevronDown,
} from "lucide-react";
import {
    navigation,
    mobileNavigation,
    currentNavigation,
} from "@/lib/navigation";

export default function TimeTrackingLayout({ children }) {
    const { url, props } = usePage();
    const [mobileOpen, setMobileOpen] = useState(false);
    const [compact, setCompact] = useState(() => {
        try {
            return localStorage.getItem("hemingway.sidebar") === "compact";
        } catch {
            return false;
        }
    });
    const current = currentNavigation(url.split("?")[0]);
    const user = props.auth?.user;
    const allowed = new Set(props.allowedNavigation || []);
    const visibleNavigation = navigation
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => allowed.has(item.route)),
        }))
        .filter((group) => group.items.length);
    const visibleMobile = mobileNavigation
        .filter((item) => allowed.has(item.route))
        .slice(0, 4);
    const homeRoute = visibleNavigation[0]?.items[0]?.route || "profile.edit";
    const initials = (user?.name || "")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toUpperCase();
    useEffect(() => {
        setMobileOpen(false);
    }, [url]);
    useEffect(() => {
        if (!mobileOpen) return;
        const escape = (event) => {
            if (event.key === "Escape") setMobileOpen(false);
        };
        document.addEventListener("keydown", escape);
        const previous = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        return () => {
            document.removeEventListener("keydown", escape);
            document.body.style.overflow = previous;
        };
    }, [mobileOpen]);
    const toggleCompact = () => {
        setCompact(!compact);
        try {
            localStorage.setItem(
                "hemingway.sidebar",
                compact ? "full" : "compact",
            );
        } catch {}
    };
    return (
        <div className={`app-shell ${compact ? "nav-compact" : ""}`}>
            <Head title={current.label} />
            <a className="skip-link" href="#main-content">
                Zum Inhalt
            </a>
            {mobileOpen && (
                <button
                    className="nav-overlay"
                    aria-label="Navigation schließen"
                    onClick={() => setMobileOpen(false)}
                />
            )}
            <aside
                id="main-navigation"
                className={`app-sidebar ${mobileOpen ? "mobile-open" : ""}`}
                aria-label="Hauptnavigation"
            >
                <div className="brand-row">
                    <Link
                        href={route(homeRoute)}
                        className="brand-link"
                        aria-label="Hemingway Übersicht"
                    >
                        <img
                            className="brand-logo"
                            src="/brand/hemingway-logo.png?v=20261018"
                            alt="Hemingway · Café · Bar · Restaurant"
                            width="132"
                            height="132"
                        />
                        <span className="brand-copy">
                            <span>Team · Planung · Kasse</span>
                        </span>
                    </Link>
                    <button
                        className="mobile-only nav-close"
                        aria-label="Navigation schließen"
                        onClick={() => setMobileOpen(false)}
                    >
                        <X size={18} />
                    </button>
                </div>
                <nav className="sidebar-navigation custom-scrollbar">
                    {visibleNavigation.map((group) => (
                        <div className="nav-group" key={group.label}>
                            <p className="nav-group-label">{group.label}</p>
                            <div className="space-y-1">
                                {group.items.map((item) => {
                                    const active = current.route === item.route;
                                    const Icon = item.icon;
                                    return (
                                        <Link
                                            key={item.route}
                                            href={route(item.route)}
                                            className={`nav-link ${active ? "active" : ""}`}
                                            aria-current={
                                                active ? "page" : undefined
                                            }
                                            title={
                                                compact ? item.label : undefined
                                            }
                                        >
                                            <Icon
                                                size={18}
                                                strokeWidth={1.65}
                                            />
                                            <span className="nav-text">
                                                {item.label}
                                            </span>
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>
                <div className="sidebar-bottom">
                    <button
                        className="nav-link desktop-only"
                        onClick={toggleCompact}
                        aria-label={
                            compact
                                ? "Navigation erweitern"
                                : "Navigation verkleinern"
                        }
                    >
                        {compact ? (
                            <PanelLeftOpen size={18} />
                        ) : (
                            <PanelLeftClose size={18} />
                        )}
                        <span className="nav-text">Navigation verkleinern</span>
                    </button>
                    <Link
                        href={route("logout")}
                        method="post"
                        as="button"
                        className="nav-link w-full"
                        title="Abmelden"
                    >
                        <LogOut size={18} />
                        <span className="nav-text">Abmelden</span>
                    </Link>
                </div>
            </aside>
            <div className="app-workspace">
                <header className="app-topbar">
                    <div className="flex items-center gap-3 min-w-0">
                        <button
                            className="mobile-only mobile-menu-button"
                            aria-label="Navigation öffnen"
                            aria-expanded={mobileOpen}
                            aria-controls="main-navigation"
                            onClick={() => setMobileOpen(true)}
                        >
                            <Menu size={20} />
                            <span>Menü</span>
                        </button>
                        <div className="breadcrumb">
                            <span>{current.group}</span>
                            <ChevronRight size={13} />
                            <strong>{current.label}</strong>
                        </div>
                    </div>
                    <div className="flex items-center gap-5">
                        <time className="hidden md:block text-xs text-neutral-600">
                            {new Date().toLocaleDateString("de-DE", {
                                timeZone: "Europe/Berlin",
                                weekday: "short",
                                day: "2-digit",
                                month: "long",
                                year: "numeric",
                            })}
                        </time>
                        <details className="account-menu">
                            <summary aria-label="Kontomenü öffnen">
                                <span className="user-avatar">
                                    {initials || <UserRound size={16} />}
                                </span>
                                <span className="hidden sm:block max-w-40 truncate text-sm">
                                    {user?.name || "Mein Konto"}
                                </span>
                                <ChevronDown size={13} />
                            </summary>
                            <div className="account-dropdown">
                                <p>{user?.email}</p>
                                <Link href={route("profile.edit")}>
                                    <UserRound size={15} />
                                    Mein Profil
                                </Link>
                                <Link
                                    href={route("logout")}
                                    method="post"
                                    as="button"
                                >
                                    <LogOut size={15} />
                                    Abmelden
                                </Link>
                            </div>
                        </details>
                    </div>
                </header>
                <main id="main-content" className="app-content" tabIndex="-1">
                    <div className="content-container">{children}</div>
                </main>
            </div>
            <nav className="mobile-taskbar" aria-label="Schnellnavigation">
                {visibleMobile.map(
                    ({ route: routeName, label, icon: Icon }) => (
                        <Link
                            key={routeName}
                            href={route(routeName)}
                            className={
                                current.route === routeName ? "active" : ""
                            }
                            aria-current={
                                current.route === routeName ? "page" : undefined
                            }
                            onClick={() => setMobileOpen(false)}
                        >
                            <Icon size={21} strokeWidth={1.8} />
                            <span>{label}</span>
                        </Link>
                    ),
                )}
            </nav>
        </div>
    );
}
