import React from 'react';

export default function Field({label,children}) {
    return <label className="rb-field"><span>{label}</span>{children}</label>;
}
