import React from 'react';

/**
 * Unified tab bar component for ALL content pages in Mission Control.
 * Replaces the 15+ duplicate tab bar implementations.
 *
 * Supports two variants:
 * - 'pills' (default): Pill-style buttons in a bar (ContentGenerator, ArtMotsCles)
 * - 'underline': Border-bottom tab style (NewsHub, ArticleDetail)
 */

interface TabDef {
  id: string;
  label: string;
  icon?: string;
  badge?: number | null;
  disabled?: boolean;
}

interface UnifiedContentTabProps {
  tabs: TabDef[];
  activeTab: string;
  onTabChange: (tabId: string) => void;
  variant?: 'pills' | 'underline';
  className?: string;
}

export default function UnifiedContentTab({
  tabs,
  activeTab,
  onTabChange,
  variant = 'pills',
  className = '',
}: UnifiedContentTabProps) {
  if (variant === 'underline') {
    return (
      <div className={`flex border-b border-border/30 gap-0 ${className}`} role="tablist">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            role="tab"
            aria-selected={activeTab === tab.id}
            disabled={tab.disabled}
            onClick={() => onTabChange(tab.id)}
            className={`relative px-5 py-3 text-sm font-medium transition-all whitespace-nowrap ${
              activeTab === tab.id
                ? 'text-violet-light border-b-2 border-violet'
                : 'text-muted hover:text-white'
            } ${tab.disabled ? 'opacity-40 cursor-not-allowed' : ''}`}
          >
            {tab.icon && <span className="mr-1.5">{tab.icon}</span>}
            {tab.label}
            {tab.badge != null && tab.badge > 0 && (
              <span className="ml-2 px-1.5 py-0.5 text-[10px] rounded-full bg-violet/20 text-violet-light">
                {tab.badge}
              </span>
            )}
          </button>
        ))}
      </div>
    );
  }

  // Default: pills variant
  return (
    <div
      className={`flex gap-1 bg-surface/40 backdrop-blur rounded-xl p-1 border border-border/20 ${className}`}
      role="tablist"
    >
      {tabs.map((tab) => (
        <button
          key={tab.id}
          role="tab"
          aria-selected={activeTab === tab.id}
          disabled={tab.disabled}
          onClick={() => onTabChange(tab.id)}
          className={`flex-1 px-4 py-2.5 rounded-lg text-sm font-medium transition-all whitespace-nowrap ${
            activeTab === tab.id
              ? 'bg-violet/20 text-violet-light border border-violet/30 shadow-lg shadow-violet/5'
              : 'text-muted hover:text-white hover:bg-surface/60'
          } ${tab.disabled ? 'opacity-40 cursor-not-allowed' : ''}`}
        >
          {tab.icon && <span className="mr-1.5">{tab.icon}</span>}
          {tab.label}
          {tab.badge != null && tab.badge > 0 && (
            <span className="ml-2 px-1.5 py-0.5 text-[10px] rounded-full bg-violet/20 text-violet-light">
              {tab.badge}
            </span>
          )}
        </button>
      ))}
    </div>
  );
}
