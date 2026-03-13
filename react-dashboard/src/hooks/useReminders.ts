import { useState, useEffect, useCallback } from 'react';
import api from '../api/client';
import type { ReminderWithInfluenceur } from '../types/influenceur';

const POLL_INTERVAL = 5 * 60 * 1000; // 5 minutes

export function useReminders() {
  const [reminders, setReminders] = useState<ReminderWithInfluenceur[]>([]);
  const [loading, setLoading] = useState(true);

  const fetch = useCallback(async () => {
    try {
      const { data } = await api.get<ReminderWithInfluenceur[]>('/reminders');
      setReminders(data);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetch();
    const interval = setInterval(fetch, POLL_INTERVAL);
    return () => clearInterval(interval);
  }, [fetch]);

  const dismiss = useCallback(async (id: number, notes?: string) => {
    await api.post(`/reminders/${id}/dismiss`, { notes });
    setReminders(prev => prev.filter(r => r.id !== id));
  }, []);

  const markDone = useCallback(async (id: number) => {
    await api.post(`/reminders/${id}/done`);
    setReminders(prev => prev.filter(r => r.id !== id));
  }, []);

  return { reminders, loading, fetch, dismiss, markDone };
}
