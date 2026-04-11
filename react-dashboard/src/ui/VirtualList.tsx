import { useRef, type ReactNode, type CSSProperties } from 'react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { cn } from '../lib/cn';

export interface VirtualListProps<T> {
  items: T[];
  /** Approximate row height in pixels (used for scroll estimation before measurement) */
  estimateSize?: number;
  /** How many items to render outside the visible area */
  overscan?: number;
  /** Max height of the scroll container (CSS value) */
  maxHeight?: string;
  /** Render function for a single row */
  renderItem: (item: T, index: number) => ReactNode;
  /** Extract a stable unique key for each item */
  getKey?: (item: T, index: number) => string | number;
  /** Optional className on the outer scroll container */
  className?: string;
}

/**
 * VirtualList — headless windowing for long scrollable lists.
 *
 * Only renders items visible in the viewport (plus `overscan`), allowing
 * you to display 10k+ rows without DOM bloat. Built on @tanstack/react-virtual.
 *
 * Usage:
 *   <VirtualList
 *     items={bigArray}
 *     estimateSize={60}
 *     renderItem={(item) => <div>{item.name}</div>}
 *   />
 *
 * For full-viewport usage, pair with `maxHeight="80vh"`.
 */
export function VirtualList<T>({
  items,
  estimateSize = 56,
  overscan = 8,
  maxHeight = '70vh',
  renderItem,
  getKey,
  className,
}: VirtualListProps<T>) {
  const parentRef = useRef<HTMLDivElement>(null);

  const virtualizer = useVirtualizer({
    count: items.length,
    getScrollElement: () => parentRef.current,
    estimateSize: () => estimateSize,
    overscan,
  });

  const virtualItems = virtualizer.getVirtualItems();
  const totalSize = virtualizer.getTotalSize();

  const innerStyle: CSSProperties = {
    height: `${totalSize}px`,
    width: '100%',
    position: 'relative',
  };

  return (
    <div
      ref={parentRef}
      className={cn('overflow-auto', className)}
      style={{ maxHeight, contain: 'strict' }}
    >
      <div style={innerStyle}>
        {virtualItems.map((v) => {
          const item = items[v.index];
          const key = getKey ? getKey(item, v.index) : v.index;
          const itemStyle: CSSProperties = {
            position: 'absolute',
            top: 0,
            left: 0,
            width: '100%',
            transform: `translateY(${v.start}px)`,
          };
          return (
            <div key={key} data-index={v.index} style={itemStyle}>
              {renderItem(item, v.index)}
            </div>
          );
        })}
      </div>
    </div>
  );
}
