import { useEffect, useMemo, useState } from 'react';
import { api } from './api/client';
import type { DashboardSummary, MatchResult, MessageDraft, StudentRequest, Tutor } from './types/api';
import './App.css';

type View = 'dashboard' | 'requests' | 'detail' | 'tutors' | 'drafts';

function App() {
  const [view, setView] = useState<View>('dashboard');
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [requests, setRequests] = useState<StudentRequest[]>([]);
  const [selectedRequestId, setSelectedRequestId] = useState<number | null>(null);
  const [selectedRequest, setSelectedRequest] = useState<StudentRequest | null>(null);
  const [matches, setMatches] = useState<MatchResult[]>([]);
  const [tutors, setTutors] = useState<Tutor[]>([]);
  const [draft, setDraft] = useState<MessageDraft | null>(null);
  const [aiNote, setAiNote] = useState<string | null>(null);
  const [loading, setLoading] = useState('Loading workspace');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void loadInitialData();
  }, []);

  useEffect(() => {
    if (selectedRequestId) void loadRequestDetail(selectedRequestId);
  }, [selectedRequestId]);

  async function loadInitialData() {
    setLoading('Loading TutorMatch Ops data');
    setError(null);
    try {
      const [summaryResponse, requestResponse, tutorResponse] = await Promise.all([api.summary(), api.requests(), api.tutors()]);
      setSummary(summaryResponse);
      setRequests(requestResponse.data);
      setTutors(tutorResponse.data);
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

  const topMatch = matches[0];
  const requestOptions = useMemo(() => requests.map((item) => ({ id: item.id, label: `${item.student_name} - ${item.subject?.name ?? 'Subject'}` })), [requests]);

  return (
    <main className="app-shell">
      <aside className="sidebar">
        <div>
          <p className="eyebrow">TutorMatch Ops</p>
          <h1>Coordinator Console</h1>
        </div>
        <nav>
          {(['dashboard', 'requests', 'detail', 'tutors', 'drafts'] as View[]).map((item) => (
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
          <select value={selectedRequestId ?? ''} onChange={(event) => setSelectedRequestId(Number(event.target.value))}>
            {requestOptions.map((option) => (
              <option key={option.id} value={option.id}>{option.label}</option>
            ))}
          </select>
        </header>

        {loading && <div className="notice">{loading}</div>}
        {error && <div className="notice error">{error}</div>}

        {view === 'dashboard' && <Dashboard summary={summary} topMatch={topMatch} />}
        {view === 'requests' && <Requests requests={requests} onOpen={(id) => { setSelectedRequestId(id); setView('detail'); }} />}
        {view === 'detail' && selectedRequest && (
          <RequestDetail
            request={selectedRequest}
            matches={matches}
            aiNote={aiNote}
            onGenerate={generateMatches}
            onExplain={explainTopMatch}
            onDraftClient={() => draftMessage('client')}
            onDraftTutor={() => draftMessage('tutor')}
          />
        )}
        {view === 'tutors' && <Tutors tutors={tutors} />}
        {view === 'drafts' && <Drafts draft={draft} />}
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

function RequestDetail({ request, matches, aiNote, onGenerate, onExplain, onDraftClient, onDraftTutor }: {
  request: StudentRequest;
  matches: MatchResult[];
  aiNote: string | null;
  onGenerate: () => void;
  onExplain: () => void;
  onDraftClient: () => void;
  onDraftTutor: () => void;
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
            <p>{match.deterministic_explanation}</p>
            <div className="factors">
              {Object.entries(match.score_breakdown).map(([factor, score]) => <span key={factor}>{factor}: {score}</span>)}
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
    detail: 'Request Detail + Matches',
    tutors: 'Tutors',
    drafts: 'Message Drafts',
  }[view];
}

export default App;
