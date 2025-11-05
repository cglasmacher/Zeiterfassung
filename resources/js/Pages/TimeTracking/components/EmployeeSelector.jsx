import React, { useState, useRef, useEffect } from "react";
import { X, Search, User } from 'lucide-react';

export default function EmployeeSelector({ employees, position, onSelect, onClose }) {
  const [searchTerm, setSearchTerm] = useState('');
  const dialogRef = useRef(null);

  const filteredEmployees = employees?.filter(emp => {
    const matchesSearch = 
      emp.first_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      emp.last_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      emp.position?.toLowerCase().includes(searchTerm.toLowerCase());
    
    if (position) {
      const empPosition = (emp.position || '').toLowerCase();
      const targetPosition = position.toLowerCase();
      return matchesSearch && empPosition.includes(targetPosition);
    }
    
    return matchesSearch;
  }) || [];

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dialogRef.current && !dialogRef.current.contains(event.target)) {
        onClose();
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-900/50 backdrop-blur-sm animate-fade-in">
      <div 
        ref={dialogRef}
        className="bg-white rounded-xl shadow-2xl border border-neutral-200 w-full max-w-md animate-scale-in"
      >
        <div className="flex items-center justify-between p-4 border-b border-neutral-200">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
              <User className="w-4 h-4 text-primary-600" />
            </div>
            <div>
              <h3 className="font-semibold text-neutral-900">Mitarbeiter zuweisen</h3>
              {position && (
                <p className="text-xs text-neutral-500">{position}</p>
              )}
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 hover:bg-neutral-100 rounded-lg transition-colors"
          >
            <X className="w-4 h-4 text-neutral-600" />
          </button>
        </div>

        <div className="p-4 border-b border-neutral-200">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
            <input
              type="text"
              placeholder="Mitarbeiter suchen..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-neutral-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
              autoFocus
            />
          </div>
        </div>

        <div className="max-h-[400px] overflow-y-auto custom-scrollbar">
          {filteredEmployees.length > 0 ? (
            <div className="p-2 space-y-1">
              {filteredEmployees.map((emp) => (
                <button
                  key={emp.id}
                  onClick={() => onSelect(emp)}
                  className="w-full text-left p-3 rounded-lg hover:bg-primary-50 transition-all border border-transparent hover:border-primary-200 group"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-semibold flex-shrink-0">
                      {emp.first_name?.charAt(0)}{emp.last_name?.charAt(0)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-semibold text-neutral-900 group-hover:text-primary-600 transition-colors">
                        {emp.first_name} {emp.last_name}
                      </p>
                      <p className="text-xs text-neutral-500 truncate">
                        {emp.position || 'Mitarbeiter'}
                      </p>
                    </div>
                  </div>
                </button>
              ))}
            </div>
          ) : (
            <div className="p-12 text-center">
              <User className="w-12 h-12 text-neutral-300 mx-auto mb-3" />
              <p className="text-sm text-neutral-600">
                {searchTerm ? 'Keine Mitarbeiter gefunden' : 'Keine Mitarbeiter verfügbar'}
              </p>
            </div>
          )}
        </div>

        <div className="p-4 border-t border-neutral-200 bg-neutral-50">
          <p className="text-xs text-neutral-500 text-center">
            {filteredEmployees.length} {filteredEmployees.length === 1 ? 'Mitarbeiter' : 'Mitarbeiter'} verfügbar
          </p>
        </div>
      </div>
    </div>
  );
}