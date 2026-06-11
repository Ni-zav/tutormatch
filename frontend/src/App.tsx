import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { api, setAuthToken } from './api/client';
import type { AuthUser, DashboardSummary, LevelRef, MatchResult, MessageDraft, StudentRequest, StudentRequestPayload, SubjectRef, Tutor } from './types/api';
import './App.css';

type View = 'dashboard' | 'requests' | 'new-request' | 'detail' | 'tutors' | 'drafts';

function App() {
  const [view, setView] = useState<View>('dashboard');
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [requests, setRequests] = useState<StudentRequest[]>([]);
  const [subjects, setSubjects] = useState<SubjectRef[]>([]);
  const [levels, setLevels] = useState<LevelRef[]>([]);
  const [selectedRequestId, setSelectedRequestId] = useState<number | null>(null);
  const [selectedRequest, setSelectedRequest] = useState<StudentRequest | null>(null);
  const [matches, setMatches] = useState<MatchResult[]>([]);
  const [tutors, setTutors] = useState<Tutor[]>([]);
  const [draft, setDraft] = useState<MessageDraft | null>(null);
  const [aiNote, setAiNote] = useState<string | null>(null);
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState('Loading workspace');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void restoreSession();
  }, []);

  useEffect(() => {
    if (selectedRequestId) void loadRequestDetail(selectedRequestId);
  }, [selectedRequestId]);

  async function restoreSession() {
    setLoading('Checking session');
    setError(null);
    try {
      const response = await api.me();
      setUser(response.data);
      await loadInitialData();
    } catch {
      setAuthToken(null);
      setUser(null);
      setLoading('');
    }
  }

  async function login(email: string, password: string) {
    setLoading('Signing in');
    setError(null);
    try {
      const response = await api.login({ email, password });
      setAuthToken(response.data.token);
      setUser(response.data.user);
      await loadInitialData();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to sign in.');
      setLoading('');
    }
  }

  async function logout() {
    try {
      await api.logout();
    } catch {
      // Token cleanup is still correct if the server session already expired.
    }
    setAuthToken(null);
    setUser(null);
    setSummary(null);
    setRequests([]);
    setSelectedRequest(null);
    setSelectedRequestId(null);
    setMatches([]);
    setTutors([]);
  }

  async function loadInitialData() {
    setLoading('Loading TutorMatch Ops data');
    setError(null);
    try {
      const [summaryResponse, requestResponse, tutorResponse] = await Promise.all([api.summary(), api.requests(), api.tutors()]);
      setSummary(summaryResponse);
      setRequests(requestResponse.data);
      setTutors(tutorResponse.data);
      void loadReferenceData();
      if (requestResponse.data[0]) setSelectedRequestId(requestResponse.data[0].id);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load API data.');
    } finally {
      setLoading('');
    }
  }

  async function loadRequestDetail(id: number) {
    setError(null);
    try {
      const [requestResponse, matchResponse] = await Promise.all([api.request(id), api.matches(id)]);
      setSelectedRequest(requestResponse.data);
      setMatches(matchResponse.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load request detail.');
    }
  }

  async function generateMatches() {
    if (!selectedRequestId) return;
    setLoading('Generating deterministic matches');
    setError(null);
    try {
      const response = await api.generateMatches(selectedRequestId);
      setMatches(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to generate matches.');
    } finally {
      setLoading('');
    }
  }

  async function explainTopMatch() {
    const match = matches[0];
    if (!match) return;
    setLoading('Generating match explanation');
    try {
      const response = await api.explainMatch(match.id);
      setAiNote(`${response.summary} ${response.coordinator_note}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to explain match.');
    } finally {
      setLoading('');
    }
  }

  async function draftMessage(audience: 'client' | 'tutor') {
    if (!selectedRequestId) return;
    const topMatch = matches[0];
    setLoading('Drafting message');
    try {
      const response = await api.createMessageDraft({
        student_request_id: selectedRequestId,
        tutor_id: audience === 'tutor' ? topMatch?.tutor.id : undefined,
        match_result_id: topMatch?.id,
        audience,
        channel: 'whatsapp',
      });
      setDraft(response.data);
      setView('drafts');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to create message draft.');
    } finally {
      setLoading('');
    }
  }

  async function loadReferenceData() {
    try {
      const [subjectResponse, levelResponse] = await Promise.all([api.subjects(), api.levels()]);
      setSubjects(subjectResponse.data);
      setLevels(levelResponse.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load form options.');
    }
  }

  async function updateMatchWorkflow(id: number, status: MatchResult['status'], outreach_status?: MatchResult['outreach_status']) {
    setLoading('Updating match workflow');
    setError(null);
    try {
      const response = await api.updateMatchWorkflow(id, { status, outreach_status });
      setMatches((current) => current.map((match) => (match.id === id ? response.data : match)));
      if (selectedRequestId) {
        const requestResponse = await api.request(selectedRequestId);
        setSelectedRequest(requestResponse.data);
        setRequests((current) => current.map((item) => (item.id === selectedRequestId ? requestResponse.data : item)));
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to update match workflow.');
    } finally {
      setLoading('');
    }
  }

  async function createStudentRequest(payload: StudentRequestPayload) {
    setLoading('Creating student request');
    setError(null);
    try {
      const response = await api.createRequest(payload);
      const [summaryResponse, requestResponse] = await Promise.all([api.summary(), api.requests()]);
      setSummary(summaryResponse);
      setRequests(requestResponse.data);
      setSelectedRequestId(response.data.id);
      setSelectedRequest(response.data);
      setMatches([]);
      setView('detail');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to create request.');
    } finally {
      setLoading('');
    }
  }

  const topMatch = matches[0];
  const requestOptions = useMemo(() => requests.map((item) => ({ id: item.id, label: `${item.student_name} - ${item.subject?.name ?? 'Subject'}` })), [requests]);

  if (!user) {
    return <LoginScreen loading={loading} error={error} onLogin={login} />;
  }

  return (
    <main className="app-shell">
      <aside className="sidebar">
        <div>
          <p className="eyebrow">TutorMatch Ops</p>
          <h1>Coordinator Console</h1>
        </div>
        <nav>
          {(['dashboard', 'requests', 'new-request', 'detail', 'tutors', 'drafts'] as View[]).map((item) => (
            <button key={item} className={view === item ? 'active' : ''} onClick={() => setView(item)}>
              {labelView(item)}
            </button>
          ))}
        </nav>
      </aside>

      <section className="workspace">
        <header className="topbar">
          <div>
            <p className="eyebrow">MindFlex-style assignment operations</p>
            <h2>{labelView(view)}</h2>
          </div>
          <div className="session-tools">
            <span>{user.name} - {user.role}</span>
            <select value={selectedRequestId ?? ''} onChange={(event) => setSelectedRequestId(Number(event.target.value))}>
              {requestOptions.map((option) => (
                <option key={option.id} value={option.id}>{option.label}</option>
              ))}
            </select>
            <button onClick={logout}>Log out</button>
          </div>
        </header>

        {loading && <div className="notice">{loading}</div>}
        {error && <div className="notice error">{error}</div>}

        {view === 'dashboard' && <Dashboard summary={summary} topMatch={topMatch} />}
        {view === 'requests' && <Requests requests={requests} onOpen={(id) => { setSelectedRequestId(id); setView('detail'); }} />}
        {view === 'new-request' && <NewRequest subjects={subjects} levels={levels} onCreate={createStudentRequest} />}
        {view === 'detail' && selectedRequest && (
          <RequestDetail
            request={selectedRequest}
            matches={matches}
            aiNote={aiNote}
            onGenerate={generateMatches}
            onExplain={explainTopMatch}
            onDraftClient={() => draftMessage('client')}
            onDraftTutor={() => draftMessage('tutor')}
            onUpdateMatch={updateMatchWorkflow}
          />
        )}
        {view === 'tutors' && <Tutors tutors={tutors} />}
        {view === 'drafts' && <Drafts draft={draft} />}
      </section>
    </main>
  );
}

function LoginScreen({ loading, error, onLogin }: { loading: string; error: string | null; onLogin: (email: string, password: string) => void }) {
  const [email, setEmail] = useState('coordinator@tutormatch.test');
  const [password, setPassword] = useState('password');

  return (
    <main className="login-shell">
      <section className="login-panel">
        <p className="eyebrow">TutorMatch Ops</p>
        <h1>Coordinator Console</h1>
        <form onSubmit={(event) => { event.preventDefault(); onLogin(email, password); }}>
          <label>
            Email
            <input value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="email" />
          </label>
          <label>
            Password
            <input value={password} onChange={(event) => setPassword(event.target.value)} type="password" autoComplete="current-password" />
          </label>
          <button type="submit" disabled={Boolean(loading)}>{loading || 'Log in'}</button>
        </form>
        {error && <div className="notice error">{error}</div>}
      </section>
    </main>
  );
}

function Dashboard({ summary, topMatch }: { summary: DashboardSummary | null; topMatch?: MatchResult }) {
  if (!summary) return <EmptyState text="Start the backend API to load the dashboard." />;
  return (
    <div className="grid four">
      <Metric label="Open requests" value={summary.requests.total} detail={`${summary.requests.urgent} urgent`} />
      <Metric label="Tutor pool" value={summary.tutors.total} detail="Seeded demo tutors" />
      <Metric label="Applications" value={summary.applications.total} detail="Tutor-side activity" />
      <Metric label="Avg match" value={summary.matches.average_score || 0} detail={topMatch ? `Top: ${topMatch.tutor.name}` : 'Generate matches'} />
    </div>
  );
}

function Requests({ requests, onOpen }: { requests: StudentRequest[]; onOpen: (id: number) => void }) {
  if (!requests.length) return <EmptyState text="No student requests yet." />;
  return (
    <div className="table-list">
      {requests.map((request) => (
        <button className="row" key={request.id} onClick={() => onOpen(request.id)}>
          <span><strong>{request.student_name}</strong><small>{request.level?.name} {request.subject?.name} - {request.location}</small></span>
          <span className={`pill ${request.urgency}`}>{request.urgency}</span>
          <span>{request.status}</span>
          <span>SGD {request.budget_max}/hr</span>
        </button>
      ))}
    </div>
  );
}

function NewRequest({ subjects, levels, onCreate }: { subjects: SubjectRef[]; levels: LevelRef[]; onCreate: (payload: StudentRequestPayload) => void }) {
  const [form, setForm] = useState({
    student_name: '',
    parent_name: '',
    subject_id: subjects[0]?.id ?? 0,
    level_id: levels[0]?.id ?? 0,
    location: '',
    teaching_mode: 'home' as StudentRequestPayload['teaching_mode'],
    budget_min: '',
    budget_max: '',
    preferred_tutor_type: '',
    requested_day_of_week: '',
    requested_time_block: '',
    urgency: 'normal' as StudentRequestPayload['urgency'],
    schedule_notes: '',
    notes: '',
  });

  useEffect(() => {
    setForm((current) => ({
      ...current,
      subject_id: current.subject_id || subjects[0]?.id || 0,
      level_id: current.level_id || levels[0]?.id || 0,
    }));
  }, [subjects, levels]);

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    onCreate({
      student_name: form.student_name,
      parent_name: form.parent_name || undefined,
      subject_id: Number(form.subject_id),
      level_id: Number(form.level_id),
      location: form.location,
      teaching_mode: form.teaching_mode,
      budget_min: form.budget_min ? Number(form.budget_min) : null,
      budget_max: Number(form.budget_max),
      preferred_tutor_type: form.preferred_tutor_type ? form.preferred_tutor_type as StudentRequestPayload['preferred_tutor_type'] : undefined,
      requested_day_of_week: form.requested_day_of_week || undefined,
      requested_time_block: form.requested_time_block || undefined,
      urgency: form.urgency,
      schedule_notes: form.schedule_notes,
      notes: form.notes || undefined,
    });
  }

  return (
    <section className="panel">
      <h3>Create Student Request</h3>
      <form className="request-form" onSubmit={submit}>
        <label>Student<input required value={form.student_name} onChange={(event) => update('student_name', event.target.value)} /></label>
        <label>Parent<input value={form.parent_name} onChange={(event) => update('parent_name', event.target.value)} /></label>
        <label>Subject<select required value={form.subject_id} onChange={(event) => update('subject_id', Number(event.target.value))}>{subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}</select></label>
        <label>Level<select required value={form.level_id} onChange={(event) => update('level_id', Number(event.target.value))}>{levels.map((level) => <option key={level.id} value={level.id}>{level.name}</option>)}</select></label>
        <label>Location<input required value={form.location} onChange={(event) => update('location', event.target.value)} /></label>
        <label>Mode<select value={form.teaching_mode} onChange={(event) => update('teaching_mode', event.target.value as StudentRequestPayload['teaching_mode'])}><option value="home">home</option><option value="online">online</option><option value="hybrid">hybrid</option></select></label>
        <label>Budget Min<input type="number" min="0" value={form.budget_min} onChange={(event) => update('budget_min', event.target.value)} /></label>
        <label>Budget Max<input required type="number" min="1" value={form.budget_max} onChange={(event) => update('budget_max', event.target.value)} /></label>
        <label>Tutor Preference<select value={form.preferred_tutor_type} onChange={(event) => update('preferred_tutor_type', event.target.value)}><option value="">none</option><option value="part_time">part_time</option><option value="full_time">full_time</option><option value="ex_moe">ex_moe</option><option value="current_moe">current_moe</option></select></label>
        <label>Day<input value={form.requested_day_of_week} onChange={(event) => update('requested_day_of_week', event.target.value)} placeholder="saturday" /></label>
        <label>Time Block<input value={form.requested_time_block} onChange={(event) => update('requested_time_block', event.target.value)} placeholder="morning" /></label>
        <label>Urgency<select value={form.urgency} onChange={(event) => update('urgency', event.target.value as StudentRequestPayload['urgency'])}><option value="low">low</option><option value="normal">normal</option><option value="urgent">urgent</option></select></label>
        <label className="wide">Schedule Notes<input required value={form.schedule_notes} onChange={(event) => update('schedule_notes', event.target.value)} /></label>
        <label className="wide">Coordinator Notes<textarea value={form.notes} onChange={(event) => update('notes', event.target.value)} /></label>
        <button type="submit" disabled={!subjects.length || !levels.length}>Create Request</button>
      </form>
    </section>
  );
}

function RequestDetail({ request, matches, aiNote, onGenerate, onExplain, onDraftClient, onDraftTutor, onUpdateMatch }: {
  request: StudentRequest;
  matches: MatchResult[];
  aiNote: string | null;
  onGenerate: () => void;
  onExplain: () => void;
  onDraftClient: () => void;
  onDraftTutor: () => void;
  onUpdateMatch: (id: number, status: MatchResult['status'], outreach_status?: MatchResult['outreach_status']) => void;
}) {
  return (
    <div className="detail-layout">
      <section className="panel">
        <h3>{request.level?.name} {request.subject?.name}</h3>
        <p>{request.notes ?? request.schedule_notes}</p>
        <dl>
          <div><dt>Location</dt><dd>{request.location}</dd></div>
          <div><dt>Mode</dt><dd>{request.teaching_mode}</dd></div>
          <div><dt>Budget</dt><dd>SGD {request.budget_min ?? 0}-{request.budget_max}/hr</dd></div>
          <div><dt>Schedule</dt><dd>{request.requested_day_of_week} {request.requested_time_block}</dd></div>
        </dl>
        <div className="actions">
          <button onClick={onGenerate}>Generate Matches</button>
          <button onClick={onExplain} disabled={!matches.length}>Explain Top Match</button>
          <button onClick={onDraftClient}>Draft Client</button>
          <button onClick={onDraftTutor} disabled={!matches.length}>Draft Tutor</button>
        </div>
        {aiNote && <div className="ai-note">{aiNote}</div>}
      </section>
      <section className="panel">
        <h3>Top Matches</h3>
        {!matches.length && <EmptyState text="No matches generated yet." />}
        {matches.map((match) => (
          <article className="match" key={match.id}>
            <div className="match-head"><strong>{match.tutor.name}</strong><span>{match.total_score}/100</span></div>
            <div className="workflow-line">
              <span>{match.status}</span>
              <span>{match.outreach_status}</span>
            </div>
            <p>{match.deterministic_explanation}</p>
            <div className="factors">
              {Object.entries(match.score_breakdown).map(([factor, score]) => <span key={factor}>{factor}: {score}</span>)}
            </div>
            <div className="match-actions">
              <button onClick={() => onUpdateMatch(match.id, 'shortlisted')}>Shortlist</button>
              <button onClick={() => onUpdateMatch(match.id, match.status, 'contacted')}>Mark Contacted</button>
              <button onClick={() => onUpdateMatch(match.id, 'needs_follow_up')}>Follow Up</button>
              <button onClick={() => onUpdateMatch(match.id, 'confirmed')}>Confirm</button>
              <button onClick={() => onUpdateMatch(match.id, 'rejected')}>Reject</button>
            </div>
          </article>
        ))}
      </section>
    </div>
  );
}

function Tutors({ tutors }: { tutors: Tutor[] }) {
  if (!tutors.length) return <EmptyState text="No tutors loaded." />;
  return (
    <div className="cards">
      {tutors.map((tutor) => (
        <article className="panel" key={tutor.id}>
          <h3>{tutor.name}</h3>
          <p>{tutor.bio}</p>
          <div className="meta-line">
            <span>{tutor.tutor_type}</span>
            <span>{tutor.location}</span>
            <span>SGD {tutor.hourly_rate_min}-{tutor.hourly_rate_max}/hr</span>
          </div>
        </article>
      ))}
    </div>
  );
}

function Drafts({ draft }: { draft: MessageDraft | null }) {
  if (!draft) return <EmptyState text="Generate a WhatsApp draft from a request detail page." />;
  return (
    <section className="panel">
      <div className="match-head"><h3>{draft.audience} message</h3><span>{draft.generated_by}</span></div>
      <p className="draft-body">{draft.body}</p>
    </section>
  );
}

function Metric({ label, value, detail }: { label: string; value: number; detail: string }) {
  return <article className="metric"><span>{label}</span><strong>{value}</strong><small>{detail}</small></article>;
}

function EmptyState({ text }: { text: string }) {
  return <div className="empty">{text}</div>;
}

function labelView(view: View) {
  return {
    dashboard: 'Dashboard',
    requests: 'Student Requests',
    'new-request': 'New Request',
    detail: 'Request Detail + Matches',
    tutors: 'Tutors',
    drafts: 'Message Drafts',
  }[view];
}

export default App;
