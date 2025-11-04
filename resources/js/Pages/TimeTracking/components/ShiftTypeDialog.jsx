import React, { useState } from "react";
import { Plus, X } from 'lucide-react';

export default function ShiftTypeDialog({ types, onSelect }) {
  const [open, setOpen] = useState(false);

  const handleSelect = (typeId) => {
    onSelect(typeId);
    setOpen(false);
  };

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="w-full h-full min-h-[80px] rounded-lg border-2 border-dashed border-neutral-300 hover:border-primary-500 hover:bg-primary-50 transition-all duration-200 flex items-center justify-center group"
      >
        <Plus className="w-5 h-5 text-neutral-400 group-hover:text-primary-500" />
      </button>
    );
  }

  return (
    <div className="relative">
      <div className="absolute inset-0 bg-white rounded-lg shadow-xl border-2 border-primary-500 p-3 z-20 animate-scale-in min-w-[200px]">
        <div className="flex items-center justify-between mb-3">
          <p className="text-sm font-semibold text-neutral-900">Schichttyp wählen</p>
          <button
            onClick={() => setOpen(false)}
            className="p-1 hover:bg-neutral-100 rounded transition-colors"
          >
            <X className="w-4 h-4 text-neutral-600" />
          </button>
        </div>
        
        <div className="space-y-2">
          {types?.map((type) => (
            <button
              key={type.id}
              onClick={() => handleSelect(type.id)}
              className="w-full text-left px-3 py-2 rounded-lg hover:bg-primary-50 transition-colors border border-neutral-200 hover:border-primary-500"
            >
              <p className="text-sm font-medium text-neutral-900">{type.name}</p>
              <p className="text-xs text-neutral-500">
                {type.default_start?.slice(0, 5)} - {type.default_end?.slice(0, 5)}
              </p>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}