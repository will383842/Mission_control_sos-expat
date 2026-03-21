export type Platform =
  | 'instagram' | 'tiktok' | 'youtube' | 'linkedin'
  | 'x' | 'facebook' | 'pinterest' | 'podcast' | 'blog' | 'newsletter';

export type Status =
  | 'prospect' | 'contacted' | 'negotiating'
  | 'active' | 'refused' | 'inactive';

export type ContactChannel =
  | 'email' | 'instagram' | 'linkedin' | 'whatsapp' | 'phone' | 'other';

export type ContactResult =
  | 'sent' | 'replied' | 'refused' | 'registered' | 'no_answer';

export type ReminderStatus = 'pending' | 'dismissed' | 'done';

export interface TeamMember {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'member' | 'researcher';
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
}

export interface Contact {
  id: number;
  influenceur_id: number;
  user_id: number;
  date: string;
  channel: ContactChannel;
  result: ContactResult;
  sender: string | null;
  message: string | null;
  reply: string | null;
  notes: string | null;
  rank: number;
  user?: Pick<TeamMember, 'id' | 'name'>;
  created_at: string;
}

export interface Reminder {
  id: number;
  influenceur_id: number;
  due_date: string;
  status: ReminderStatus;
  dismissed_by: number | null;
  dismissed_at: string | null;
  notes: string | null;
  days_elapsed?: number;
}

export interface Influenceur {
  id: number;
  name: string;
  handle: string | null;
  avatar_url: string | null;
  platforms: Platform[];
  primary_platform: Platform;
  followers: number | null;
  followers_secondary: Record<string, number> | null;
  niche: string | null;
  country: string | null;
  language: string | null;
  email: string | null;
  phone: string | null;
  profile_url: string | null;
  status: Status;
  assigned_to: number | null;
  assigned_to_user?: Pick<TeamMember, 'id' | 'name'> | null;
  reminder_days: number;
  reminder_active: boolean;
  last_contact_at: string | null;
  partnership_date: string | null;
  notes: string | null;
  tags: string[] | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  contacts?: Contact[];
  pending_reminder?: Reminder | null;
  days_elapsed?: number;
}

export interface PaginatedInfluenceurs {
  data: Influenceur[];
  next_cursor: string | null;
  has_more: boolean;
}

export interface InfluenceurFilters {
  status?: Status;
  platform?: Platform;
  assigned_to?: number;
  has_reminder?: boolean;
  search?: string;
}

export interface StatsData {
  total: number;
  byStatus: Record<Status, number>;
  responseRate: number;
  conversionRate: number;
  newThisMonth: number;
  active: number;
  contactsEvolution: { week: string; count: number }[];
  byPlatform: { primary_platform: string; count: number }[];
  responseByPlatform: { platform: string; rate: number; total: number }[];
  teamActivity: { user_id: number; count: number; user: Pick<TeamMember, 'id' | 'name'> }[];
  funnel: { stage: string; count: number }[];
  recentActivity: ActivityLogEntry[];
}

export interface ActivityLogEntry {
  id: number;
  user_id: number;
  influenceur_id: number | null;
  action: string;
  details: Record<string, unknown> | null;
  created_at: string;
  user?: Pick<TeamMember, 'id' | 'name'>;
  influenceur?: Pick<Influenceur, 'id' | 'name'> | null;
}

export interface ReminderWithInfluenceur extends Reminder {
  influenceur: Pick<Influenceur, 'id' | 'name' | 'status' | 'last_contact_at' | 'primary_platform'> & {
    assigned_to_user?: Pick<TeamMember, 'id' | 'name'> | null;
  };
}

export interface Objective {
  id: number;
  user_id: number;
  continent: string | null;
  countries: string[] | null;
  language: string | null;
  niche: string | null;
  target_count: number;
  deadline: string;
  is_active: boolean;
  created_by: number;
  created_at: string;
}

export interface ObjectiveWithProgress extends Objective {
  current_count: number;
  percentage: number;
  days_remaining: number;
}

export interface ObjectiveProgress {
  objectives: ObjectiveWithProgress[];
  global_progress: {
    total_target: number;
    total_current: number;
    percentage: number;
  };
}

export interface ResearcherStat {
  id: number;
  name: string;
  email: string;
  last_login_at: string | null;
  total_created: number;
  valid_count: number;
  objectives: ObjectiveWithProgress[];
}
