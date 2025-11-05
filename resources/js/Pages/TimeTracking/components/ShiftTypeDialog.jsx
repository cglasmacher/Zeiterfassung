import React, { useState, useRef, useEffect } from "react";
import { Plus, X, Clock } from 'lucide-react';

export default function ShiftTypeDialog({ types, onSelect }) {
  const [open, setOpen] = useState(false);
  const dialogRef = useRef(null);

  // Schließe Dialog bei Klick außerhalb
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dialogRef.current && !dialogRef.current.contains(event.target)) {
        setOpen(false);
      }
    };

    if (open) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [open]);

  const handleSelect = (typeId) => {
    onSelect(typeId);
    setOpen(false);
  };

  const getShiftColor = (typeName) => {
    const colors = {
      'Frühschicht': 'border-l-4 border-primary-500 hover:bg-primary-50',
      'Spätschicht': 'border-l-4 border-accent-500 hover:bg-accent-50',
      'Nachtschicht': 'border-l-4 border-secondary-500 hover:bg-secondary-50',
      'Sonderschicht': 'border-l-4 border-success-500 hover:bg-success-50',
    };
    return colors[typeName] || 'border-l-4 border-neutral-500 hover:bg-neutral-50';
  };

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="w-full h-full min-h-[80px] rounded-lg border-2 border-dashed border-neutral-300 hover:border-primary-500 hover:bg-primary-50 transition-all duration-200 flex items-center justify-center group"
      >
        <Plus className="w-5 h-5 text-neutral-400 group-hover:text-primary-500 transition-colors" />
      </button>
    );
  }

  return (
    <div className="relative z-50" ref={dialogRef}>
      {/* Backdrop */}
      <div className="fixed inset-0 bg-neutral-900/20 backdrop-blur-sm" onClick={() => setOpen(false)} />
      
      {/* Dialog */}
      <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl border border-neutral-200 p-4 min-w-[280px] max-w-[320px] animate-scale-in">
        {/* Header */}
        <div className="flex items-center justify-between mb-4 pb-3 border-b border-neutral-200">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
              <Clock className="w-4 h-4 text-primary-600" />
            </div>
            <h3 className="font-semibold text-neutral-900">Schichttyp wählen</h3>
          </div>
          <button
            onClick={() => setOpen(false)}
            className="p-1.5 hover:bg-neutral-100 rounded-lg transition-colors"
            title="Schließen"
          >
            <X className="w-4 h-4 text-neutral-600" />
          </button>
        </div>
        
        {/* Options */}
        <div className="space-y-2 max-h-[400px] overflow-y-auto custom-scrollbar">
          {types && types.length > 0 ? (
            types.map((type) => (
              <button
                key={type.id}
                onClick={() => handleSelect(type.id)}
                className={`w-full text-left px-4 py-3 rounded-lg transition-all duration-200 border border-neutral-200 ${getShiftColor(type.name)} group`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <p className="font-semibold text-neutral-900 mb-1 group-hover:text-primary-600 transition-colors">
                      {type.name}
                    </p>
                    <div className="flex items-center gap-2 text-xs text-neutral-600">
                      <Clock className="w-3 h-3" />
                      <span>
                        {type.default_start?.slice(0, 5)} - {type.default_end?.slice(0, 5)}
                      </span>
                      {type.default_hours && (
                        <span className="text-neutral-400">
                          • {type.default_hours}h
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="opacity-0 group-hover:opacity-100 transition-opacity">
                    <Plus className="w-5 h-5 text-primary-500" />
                  </div>
                </div>
              </button>
            ))
          ) : (
            <div className="text-center py-8">
              <Clock className="w-12 h-12 text-neutral-300 mx-auto mb-3" />
              <p className="text-sm text-neutral-600">Keine Schichttypen verfügbar</p>
            </div>
          )}
        </div>

        {/* Footer Hint */}
        {types && types.length > 0 && (
          <div className="mt-4 pt-3 border-t border-neutral-200">
            <p className="text-xs text-neutral-500 text-center">
              Klicken Sie auf einen Schichttyp zum Hinzufügen
            </p>
          </div>
        )}
      </div>
    </div>
  );
}