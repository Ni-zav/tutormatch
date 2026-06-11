import { StatusBar } from 'expo-status-bar';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, Text, TextInput, View } from 'react-native';

declare const process: { env?: { EXPO_PUBLIC_API_BASE_URL?: string } };

type Assignment = {
  id: number;
  title: string;
  status: string;
  application_status: string | null;
  request: {
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

type Mode = 'all' | 'home' | 'online' | 'hybrid';

const API_BASE = process.env?.EXPO_PUBLIC_API_BASE_URL ?? 'http://127.0.0.1:8000/api';

export default function App() {
  const [email, setEmail] = useState('tutor@tutormatch.test');
  const [password, setPassword] = useState('password');
  const [token, setToken] = useState<string | null>(null);
  const [mode, setMode] = useState<Mode>('all');
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [loading, setLoading] = useState('');
  const [error, setError] = useState<string | null>(null);

  const filtered = useMemo(
    () => assignments.filter((item) => mode === 'all' || item.request?.teaching_mode === mode),
    [assignments, mode],
  );
  const selected = filtered.find((item) => item.id === selectedId) ?? filtered[0] ?? null;

  useEffect(() => {
    if (token) void loadAssignments(token);
  }, [token]);

  async function apiRequest<T>(path: string, init?: RequestInit, authToken = token): Promise<T> {
    const response = await fetch(`${API_BASE}${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
        ...init?.headers,
      },
    });

    if (!response.ok) {
      const text = await response.text();
      throw new Error(text || `Request failed with ${response.status}`);
    }

    return response.json() as Promise<T>;
  }

  async function login() {
    setLoading('Signing in');
    setError(null);
    try {
      const response = await apiRequest<{ data: { token: string } }>('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      }, null);
      setToken(response.data.token);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to sign in.');
    } finally {
      setLoading('');
    }
  }

  async function loadAssignments(authToken = token) {
    if (!authToken) return;
    setLoading('Loading assignments');
    setError(null);
    try {
      const response = await apiRequest<{ data: Assignment[] }>('/assignments', undefined, authToken);
      setAssignments(response.data);
      setSelectedId(response.data[0]?.id ?? null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load assignments.');
    } finally {
      setLoading('');
    }
  }

  async function applyToAssignment(id: number) {
    setLoading('Applying');
    setError(null);
    try {
      await apiRequest(`/assignments/${id}/applications`, {
        method: 'POST',
        body: JSON.stringify({ message: 'Available and interested.' }),
      });
      await loadAssignments();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to apply.');
      setLoading('');
    }
  }

  async function withdrawFromAssignment(id: number) {
    setLoading('Withdrawing');
    setError(null);
    try {
      await apiRequest(`/assignments/${id}/applications`, { method: 'DELETE' });
      await loadAssignments();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to withdraw.');
      setLoading('');
    }
  }

  return (
    <ScrollView contentInsetAdjustmentBehavior="automatic" style={{ flex: 1, backgroundColor: '#f4f7fb' }} contentContainerStyle={{ padding: 20, gap: 16 }}>
      <StatusBar style="dark" />
      <View style={{ gap: 6 }}>
        <Text selectable style={{ color: '#1f8a7a', fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>TutorMatch Mobile</Text>
        <Text selectable style={{ color: '#172033', fontSize: 30, fontWeight: '800' }}>Assignment Feed</Text>
        <Text selectable style={{ color: '#64748b', lineHeight: 22 }}>Authenticated tutor view backed by the Laravel assignment API.</Text>
      </View>

      {!token && (
        <View style={{ padding: 16, borderRadius: 8, backgroundColor: '#fff', borderWidth: 1, borderColor: '#dbe3ef', gap: 10 }}>
          <Text selectable style={{ color: '#172033', fontSize: 18, fontWeight: '800' }}>Tutor Login</Text>
          <TextInput value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" style={inputStyle} />
          <TextInput value={password} onChangeText={setPassword} secureTextEntry style={inputStyle} />
          <Pressable onPress={login} disabled={Boolean(loading)} style={primaryButtonStyle}>
            <Text selectable style={buttonTextStyle}>{loading || 'Log in'}</Text>
          </Pressable>
        </View>
      )}

      {token && (
        <>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
            {(['all', 'home', 'online', 'hybrid'] as const).map((item) => (
              <Pressable key={item} onPress={() => setMode(item)} style={{ paddingVertical: 9, paddingHorizontal: 12, borderRadius: 999, backgroundColor: mode === item ? '#172033' : '#fff', borderWidth: 1, borderColor: '#dbe3ef' }}>
                <Text selectable style={{ color: mode === item ? '#fff' : '#172033', fontWeight: '700' }}>{item}</Text>
              </Pressable>
            ))}
          </View>

          <Pressable onPress={() => loadAssignments()} disabled={Boolean(loading)} style={primaryButtonStyle}>
            <Text selectable style={buttonTextStyle}>{loading || 'Refresh Assignments'}</Text>
          </Pressable>

          <View style={{ gap: 10 }}>
            {filtered.map((assignment) => (
              <Pressable key={assignment.id} onPress={() => setSelectedId(assignment.id)} style={{ padding: 16, borderRadius: 8, backgroundColor: selected?.id === assignment.id ? '#eaf7f5' : '#fff', borderWidth: 1, borderColor: selected?.id === assignment.id ? '#5eb6ad' : '#dbe3ef' }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 12 }}>
                  <Text selectable style={{ color: '#172033', fontSize: 17, fontWeight: '800', flex: 1 }}>{assignment.title}</Text>
                  <Text selectable style={{ color: '#1f8a7a', fontWeight: '800' }}>{assignment.application_status ?? assignment.status}</Text>
                </View>
                <Text selectable style={{ color: '#64748b', marginTop: 6 }}>{assignment.request?.level} - {assignment.request?.location} - {formatBudget(assignment)}</Text>
              </Pressable>
            ))}
            {!filtered.length && <Text selectable style={{ color: '#64748b' }}>No open assignments match this filter.</Text>}
          </View>

          {selected && (
            <View style={{ padding: 18, borderRadius: 8, backgroundColor: '#fff', borderWidth: 1, borderColor: '#dbe3ef', gap: 10 }}>
              <Text selectable style={{ color: '#64748b', fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>Assignment Detail</Text>
              <Text selectable style={{ color: '#172033', fontSize: 22, fontWeight: '800' }}>{selected.title}</Text>
              <Text selectable style={{ color: '#334155', lineHeight: 22 }}>{selected.request?.notes ?? 'Coordinator has not added extra notes.'}</Text>
              <Text selectable style={{ color: '#64748b' }}>{selected.request?.subject} - {selected.request?.level}</Text>
              <Text selectable style={{ color: '#64748b' }}>{selected.request?.location} - {selected.request?.teaching_mode} - {selected.request?.schedule || 'Schedule flexible'}</Text>
              <Pressable
                onPress={() => selected.application_status === 'applied' ? withdrawFromAssignment(selected.id) : applyToAssignment(selected.id)}
                disabled={Boolean(loading)}
                style={primaryButtonStyle}
              >
                <Text selectable style={buttonTextStyle}>{selected.application_status === 'applied' ? 'Withdraw Interest' : 'Apply Interest'}</Text>
              </Pressable>
            </View>
          )}
        </>
      )}

      {error && <Text selectable style={{ color: '#9f1239', backgroundColor: '#fff1f2', padding: 12, borderRadius: 8 }}>{error}</Text>}
    </ScrollView>
  );
}

function formatBudget(assignment: Assignment): string {
  const request = assignment.request;
  if (!request) return 'Budget unavailable';

  return `SGD ${request.budget_min ?? 0}-${request.budget_max}/hr`;
}

const inputStyle = {
  padding: 12,
  borderRadius: 8,
  borderWidth: 1,
  borderColor: '#cbd5e1',
  color: '#172033',
  backgroundColor: '#fff',
};

const primaryButtonStyle = {
  padding: 14,
  borderRadius: 8,
  backgroundColor: '#1f8a7a',
};

const buttonTextStyle = {
  color: '#fff',
  fontWeight: '800' as const,
  textAlign: 'center' as const,
};
