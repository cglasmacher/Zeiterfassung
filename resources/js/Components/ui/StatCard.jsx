import React from 'react';
import { Card, CardBody } from './Card';
import { TrendingUp, TrendingDown } from 'lucide-react';

export default function StatCard({ 
  title, 
  value, 
  subtitle, 
  icon: Icon, 
  color = 'primary',
  trend = null,
  loading = false 
}) {
  const colors = {
    primary: {
      bg: 'bg-primary-100',
      text: 'text-primary-600',
      trend: 'text-primary-600',
    },
    secondary: {
      bg: 'bg-secondary-100',
      text: 'text-secondary-600',
      trend: 'text-secondary-600',
    },
    success: {
      bg: 'bg-success-100',
      text: 'text-success-600',
      trend: 'text-success-600',
    },
    warning: {
      bg: 'bg-warning-100',
      text: 'text-warning-600',
      trend: 'text-warning-600',
    },
    error: {
      bg: 'bg-error-100',
      text: 'text-error-600',
      trend: 'text-error-600',
    },
    accent: {
      bg: 'bg-accent-100',
      text: 'text-accent-600',
      trend: 'text-accent-600',
    },
  };

  const colorClasses = colors[color] || colors.primary;

  if (loading) {
    return (
      <Card>
        <CardBody className="p-6">
          <div className="animate-pulse space-y-3">
            <div className="h-4 bg-neutral-200 rounded w-1/2"></div>
            <div className="h-8 bg-neutral-200 rounded w-3/4"></div>
            <div className="h-3 bg-neutral-200 rounded w-1/3"></div>
          </div>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card className="overflow-hidden hover:shadow-xl transition-shadow duration-300">
      <CardBody className="p-6">
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-neutral-600 mb-1">{title}</p>
            <h3 className="text-3xl font-bold text-neutral-900 mb-1">{value}</h3>
            {subtitle && <p className="text-sm text-neutral-500">{subtitle}</p>}
            
            {trend !== null && (
              <div className={`flex items-center gap-1 mt-2 text-sm ${
                trend > 0 ? 'text-success-600' : trend < 0 ? 'text-error-600' : 'text-neutral-600'
              }`}>
                {trend > 0 ? (
                  <TrendingUp className="w-4 h-4" />
                ) : trend < 0 ? (
                  <TrendingDown className="w-4 h-4" />
                ) : null}
                <span className="font-medium">{Math.abs(trend)}%</span>
                <span className="text-neutral-500">vs. letzte Woche</span>
              </div>
            )}
          </div>
          
          {Icon && (
            <div className={`p-3 rounded-xl ${colorClasses.bg} flex-shrink-0`}>
              <Icon className={`w-6 h-6 ${colorClasses.text}`} />
            </div>
          )}
        </div>
      </CardBody>
    </Card>
  );
}