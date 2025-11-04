import React from 'react';

const variants = {
  success: 'badge-success',
  warning: 'badge-warning',
  error: 'badge-error',
  primary: 'badge-primary',
  secondary: 'badge bg-secondary-100 text-secondary-700',
  neutral: 'badge bg-neutral-100 text-neutral-700',
};

export default function Badge({ children, variant = 'neutral', className = '', icon = null }) {
  const variantClass = variants[variant] || variants.neutral;
  
  return (
    <span className={`${variantClass} ${className} inline-flex items-center gap-1`}>
      {icon && icon}
      {children}
    </span>
  );
}