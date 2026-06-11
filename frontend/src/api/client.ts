import type { Assignment, AuditLog, AuthUser, DashboardSummary, LevelRef, LoginResponse, MatchResult, MessageDraft, Paginated, StudentRequest, StudentRequestPayload, SubjectRef, Tutor } from '../types/api';

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000/api';
let authToken = localStorage.getItem('tutormatch_api_token');

export function setAuthToken(token: string | null) {
  authToken = token;
  if (token) {
    localStorage.setItem('tutormatch_api_token', token);
  } else {
    localStorage.removeItem('tutormatch_api_token');
  }
}

export function getAuthToken() {
  return authToken;
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const headers = new Headers(init?.headers);
  headers.set('Accept', 'application/json');
  headers.set('Content-Type', 'application/json');
  if (authToken) headers.set('Authorization', `Bearer ${authToken}`);

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(text || `API request failed with ${response.status}`);
  }

  return response.json() as Promise<T>;
}

export const api = {
  login: (payload: { email: string; password: string }) => request<LoginResponse>('/auth/login', { method: 'POST', body: JSON.stringify(payload) }),
  me: () => request<{ data: AuthUser }>('/auth/me'),
  logout: () => request<{ message: string }>('/auth/logout', { method: 'POST' }),
  summary: () => request<DashboardSummary>('/dashboard/summary'),
  auditLogs: (action?: string) => request<Paginated<AuditLog>>(`/audit-logs${action ? `?action=${encodeURIComponent(action)}` : ''}`),
  assignments: () => request<Paginated<Assignment>>('/assignments'),
  subjects: () => request<{ data: SubjectRef[] }>('/subjects'),
  levels: () => request<{ data: LevelRef[] }>('/levels'),
  requests: () => request<Paginated<StudentRequest>>('/requests'),
  createRequest: (payload: StudentRequestPayload) => request<{ data: StudentRequest }>('/requests', { method: 'POST', body: JSON.stringify(payload) }),
  request: (id: number) => request<{ data: StudentRequest }>(`/requests/${id}`),
  matches: (id: number) => request<Paginated<MatchResult>>(`/requests/${id}/matches`),
  generateMatches: (id: number) => request<{ data: MatchResult[] }>(`/requests/${id}/generate-matches`, { method: 'POST' }),
  tutors: () => request<Paginated<Tutor>>('/tutors'),
  updateMatchWorkflow: (id: number, payload: { status: MatchResult['status']; outreach_status?: MatchResult['outreach_status']; coordinator_notes?: string }) =>
    request<{ data: MatchResult }>(`/matches/${id}/workflow`, { method: 'PATCH', body: JSON.stringify(payload) }),
  updateApplicationStatus: (id: number, payload: { status: Assignment['applications'][number]['status'] }) =>
    request<{ data: Assignment['applications'][number] }>(`/applications/${id}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  explainMatch: (id: number) =>
    request<{ summary: string; strengths: string[]; risks: string[]; coordinator_note: string; generated_by: string }>(`/matches/${id}/explain`, { method: 'POST' }),
  createMessageDraft: (payload: { student_request_id: number; tutor_id?: number; match_result_id?: number; audience: 'client' | 'tutor'; channel: 'whatsapp' }) =>
    request<{ data: MessageDraft }>('/message-drafts', { method: 'POST', body: JSON.stringify(payload) }),
};
