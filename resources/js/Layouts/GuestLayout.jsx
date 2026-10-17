import { Link } from "@inertiajs/react";

export default function GuestLayout({ children }) {
    return (
        <div className="min-h-screen flex flex-col items-center justify-center px-5 py-12 bg-accent-50">
            <Link href="/" className="text-center mb-8">
                <img src="/brand/hemingway-logo.png?v=20261018" className="guest-brand-logo" alt="Hemingway · Café · Bar · Restaurant" width="180" height="180" />
                <span className="block mt-3 text-[10px] uppercase tracking-[0.2em] text-primary-700">
                    Team · Planung · Kasse
                </span>
            </Link>
            <div className="card w-full max-w-md p-7 sm:p-9">{children}</div>
        </div>
    );
}
