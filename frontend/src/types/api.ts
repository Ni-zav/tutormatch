export type SubjectRef = { id: number; name: string };
export type LevelRef = { id: number; name: string };

export type StudentRequest = {
  id: number;
  student_name: string;
  parent_name: string | null;
  subject?: SubjectRef;
  level?: LevelRef;
  location: string;
  teaching_mode: 'home' | 'online' | 'hybrid';
  budget_min: number | null;
  budget_max: number;
  preferred_tutor_type: string | null;
  requested_day_of_week: string | null;
  requested_time_block: string | null;
  urgency: 'low' | 'normal' | 'urgent';
  status: string;
  schedule_notes: string;
  notes: string | null;
  assignment?: { id: number; title: string; status: string };
  created_at: string;
};

export type Tutor = {
  id: number;
  name: string;
  tutor_type: string;
  teaching_mode: string;
  location: string;
  hourly_rate_min: number;
  hourly_rate_max: number;
  years_experience: number;
  rating: number | null;
  acceptance_rate: number;
  success_score: number;
  is_active: boolean;
  bio: string | null;
  subjects?: { subject: string; level: string | null; proficiency: number }[];
  availabilities?: { day_of_week: string; time_block: string }[];
};

export type MatchResult = {
  id: number;
  student_request_id: number;
  tutor: Tutor;
  total_score: number;
  score_breakdown: Record<string, number>;
  deterministic_explanation: string;
  generated_at: string;
};

export type DashboardSummary = {
  requests: { total: number; new: number; urgent: number };
  tutors: { total: number };
  applications: { total: number };
  matches: { generated: number; average_score: number };
};

export type Paginated<T> = {
  data: T[];
  links?: unknown;
  meta?: unknown;
};

export type MessageDraft = {
  id: number;
  body: string;
  audience: string;
  channel: string;
  generated_by: string;
};

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'coordinator' | 'tutor';
};

export type LoginResponse = {
  data: {
    token: string;
    user: AuthUser;
  };
};
