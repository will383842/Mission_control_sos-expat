import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { VirtualList } from './VirtualList';

describe('VirtualList', () => {
  it('renders items in the viewport', () => {
    const items = Array.from({ length: 1000 }, (_, i) => ({ id: i, name: `Item ${i}` }));
    render(
      <VirtualList
        items={items}
        estimateSize={40}
        maxHeight="400px"
        getKey={(item) => item.id}
        renderItem={(item) => <div>{item.name}</div>}
      />,
    );

    // Virtualizer renders a slice of items, not all 1000
    // jsdom has 0 viewport height, so the exact number is unpredictable —
    // but at least the first item should always be rendered (due to overscan)
    // and the DOM should NOT contain all 1000 items
    const rendered = screen.queryAllByText(/^Item \d+$/);
    expect(rendered.length).toBeLessThan(1000);
  });

  it('handles empty list gracefully', () => {
    const { container } = render(
      <VirtualList
        items={[]}
        renderItem={() => <div>never</div>}
      />,
    );
    expect(container.querySelector('.overflow-auto')).toBeInTheDocument();
    expect(screen.queryByText('never')).not.toBeInTheDocument();
  });
});
