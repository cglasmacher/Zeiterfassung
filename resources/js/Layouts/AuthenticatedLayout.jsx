import TimeTrackingLayout from "./TimeTrackingLayout";

export default function AuthenticatedLayout({ header, children }) {
    return (
        <TimeTrackingLayout>
            {header && <div className="mb-5">{header}</div>}
            {children}
        </TimeTrackingLayout>
    );
}
