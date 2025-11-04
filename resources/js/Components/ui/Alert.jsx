import React from 'react';
import { CheckCircle, XCircle, AlertCircle, Info, X } from 'lucide-react';

export default function Alert({ 
  variant = 'info', 
  title, 
  children, 
  onClose,
  className = '' 
}) {
  const variants = {
    success: {
      bg: 'bg-success-50',
      border: 'border-success-200',
      text: 'text-success-900',
      icon: CheckCircle,
      iconColor: 'text-success-600',
    },
    error: {
      bg: 'bg-error-50',
      border: 'border-error-200',
      text: 'text-error-900',
      icon: XCircle,
      iconColor: 'text-error-600',
    },
    warning: {
      bg: 'bg-warning-50',
      border: 'border-warning-200',
      text: 'text-warning-900',
      icon: AlertCircle,
      iconColor: 'text-warning-600',
    },
    info: {
      bg: 'bg-secondary-50',
      border: 'border-secondary-200',
      text: 'text-secondary-900',
      icon: Info,
      iconColor: 'text-secondary-600',
    },
  };

  const config = variants[variant] || variants.info;
  const Icon = config.icon;

  return (
    <div className={`${config.bg} border ${config.border} rounded-lg p-4 ${className}`}>
      <div className="flex items-start gap-3">
        <Icon className={`w-5 h-5 ${config.iconColor} flex-shrink-0 mt-0.5`} />
        
        <div className="flex-1 min-w-0">
          {title && (
            <h4 className={`font-semibold ${config.text} mb-1`}>
              {title}
            </h4>
          )}
          <div className={`text-sm ${config.text}`}>
            {children}
          </div>
        </div>
        
        {onClose && (
          <button
            onClick={onClose}
            className={`${config.iconColor} opacity-50 hover:opacity-100 transition-opacity flex-shrink-0`}
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>
    </div>
  );
}