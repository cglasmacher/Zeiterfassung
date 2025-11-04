import React from 'react';

export default function LoadingSpinner({ size = 'md', text = 'Lädt...' }) {
  const sizes = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
    xl: 'h-16 w-16',
  };

  return (
    <div className="flex flex-col items-center justify-center py-12">
      <div className={`animate-spin rounded-full border-b-2 border-primary-500 ${sizes[size]} mb-4`}></div>
      {text && <p className="text-neutral-600">{text}</p>}
    </div>
  );
}