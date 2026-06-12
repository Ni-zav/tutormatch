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
  status: 'new' | 'matching' | 'no_matches' | 'shortlisted' | 'confirmed' | 'rejected' | 'closed' | 'needs_follow_up';
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
  status: 'recommended' | 'shortlisted' | 'accepted' | 'rejected' | 'confirmed' | 'needs_follow_up' | 'closed';
  outreach_status: 'not_contacted' | 'drafted' | 'contacted' | 'responded' | 'no_response';
  coordinator_notes: string | null;
  status_updated_at: string | null;
  generated_at: string;
};

export type DashboardSummary = {
  requests: { total: number; new: number; urgent: number; no_matches: number; needs_follow_up: number };
  tutors: { total: number };
  applications: { total: number; pending: number };
  matches: { generated: number; average_score: number; shortlisted: number; contacted: number };
};

export type AuditLog = {
  id: number;
  action: string;
  actor: {
    id: number;
    name: string;
    email: string;
    role: string;
  } | null;
  auditable_type: string | null;
  auditable_id: number | null;
  metadata: Record<string, unknown> | null;
  ip_address: string | null;
  created_at: string;
};

export type AssignmentApplication = {
  id: number;
  tutor_id: number;
  tutor_name: string | null;
  status: 'applied' | 'accepted' | 'rejected' | 'withdrawn';
  message: string | null;
  applied_at: string;
};

export type Assignment = {
  id: number;
  title: string;
  status: string;
  published_at: string | null;
  application_status: string | null;
  application_id: number | null;
  applications: AssignmentApplication[];
  request: {
    id: number;
    subject: string | null;
    level: string | null;
    location: string;
    teaching_mode: 'home' | 'online' | 'hybrid';
    budget_min: number | null;
    budget_max: number;
    schedule: string;
    notes: string | null;
  } | null;
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
  prompt_version: string;
  fallback_used: boolean;
  generation_metadata: Record<string, unknown> | null;
};

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'coordinator' | 'tutor';
  token_issued_at: string | null;
  token_last_used_at: string | null;
  token_expires_at: string | null;
};

export type LoginResponse = {
  data: {
    token: string;
    user: AuthUser;
  };
};

export type StudentRequestPayload = {
  student_name: string;
  parent_name?: string;
  subject_id: number;
  level_id: number;
  location: string;
  teaching_mode: 'home' | 'online' | 'hybrid';
  budget_min?: number | null;
  budget_max: number;
  preferred_tutor_type?: 'part_time' | 'full_time' | 'ex_moe' | 'current_moe';
  requested_day_of_week?: string;
  requested_time_block?: string;
  urgency: 'low' | 'normal' | 'urgent';
  schedule_notes: string;
  notes?: string;
};
