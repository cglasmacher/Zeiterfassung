import React from 'react';
import Button from './Button';
import { Card, CardBody } from './Card';

export default function EmptyState({ 
  icon: Icon, 
  title, 
  description, 
  action,
  actionLabel,
  onAction 
}) {
  return (
    <Card>
      <CardBody className="p-12 text-center">
        {Icon && (
          <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-neutral-100 flex items-center justify-center">
            <Icon className="w-8 h-8 text-neutral-400" />
          </div>
        )}
        
        <h3 className="heading-4 mb-2">{title}</h3>
        
        {description && (
          <p className="text-neutral-600 mb-6 max-w-md mx-auto">
            {description}
          </p>
        )}
        
        {action && onAction && (
          <Button onClick={onAction} variant="primary">
            {actionLabel || action}
          </Button>
        )}
      </CardBody>
    </Card>
  );
}