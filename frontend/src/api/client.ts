import type { DashboardSummary, MatchResult, MessageDraft, Paginated, StudentRequest, Tutor } from '../types/api';

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000/api';

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...init?.headers,
    },
    ...init,
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(text || `API request failed with ${response.status}`);
  }

  return response.json() as Promise<T>;
}

export const api = {
  summary: () => request<DashboardSummary>('/dashboard/summary'),
  requests: () => request<Paginated<StudentRequest>>('/requests'),
  request: (id: number) => request<{ data: StudentRequest }>(`/requests/${id}`),
  matches: (id: number) => request<Paginated<MatchResult>>(`/requests/${id}/matches`),
  generateMatches: (id: number) => request<{ data: MatchResult[] }>(`/requests/${id}/generate-matches`, { method: 'POST' }),
  tutors: () => request<Paginated<Tutor>>('/tutors'),
  explainMatch: (id: number) =>
    request<{ summary: string; strengths: string[]; risks: string[]; coordinator_note: string; generated_by: string }>(`/matches/${id}/explain`, { method: 'POST' }),
  createMessageDraft: (payload: { student_request_id: number; tutor_id?: number; match_result_id?: number; audience: 'client' | 'tutor'; channel: 'whatsapp' }) =>
    request<{ data: MessageDraft }>('/message-drafts', { method: 'POST', body: JSON.stringify(payload) }),
};
