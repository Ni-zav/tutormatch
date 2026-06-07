import { StatusBar } from 'expo-status-bar';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';

type Assignment = {
  id: number;
  title: string;
  level: string;
  subject: string;
  location: string;
  mode: 'home' | 'online' | 'hybrid';
  budget: string;
  schedule: string;
  fit: number;
  notes: string;
};

const assignments: Assignment[] = [
  {
    id: 1,
    title: 'Sec 4 Chemistry exam prep',
    level: 'Sec 4 O-Level',
    subject: 'Chemistry',
    location: 'Bishan',
    mode: 'home',
    budget: 'SGD 45-65/hr',
    schedule: 'Saturday morning',
    fit: 96,
    notes: 'Parent prefers an experienced science tutor for structured revision.',
  },
  {
    id: 2,
    title: 'Primary 6 Chinese support',
    level: 'Primary 6',
    subject: 'Chinese',
    location: 'Serangoon',
    mode: 'hybrid',
    budget: 'SGD 55-75/hr',
    schedule: 'Weekday evening',
    fit: 88,
    notes: 'Focus on oral confidence and composition practice.',
  },
  {
    id: 3,
    title: 'Sec 2 Mathematics foundation',
    level: 'Sec 2',
    subject: 'Mathematics',
    location: 'Jurong East',
    mode: 'home',
    budget: 'SGD 30-45/hr',
    schedule: 'Sunday morning',
    fit: 81,
    notes: 'Student needs help closing algebra and graphing gaps.',
  },
];

export default function App() {
  const [mode, setMode] = useState<'all' | Assignment['mode']>('all');
  const [selected, setSelected] = useState(assignments[0]);
  const [appliedIds, setAppliedIds] = useState<number[]>([]);

  const filtered = useMemo(() => assignments.filter((item) => mode === 'all' || item.mode === mode), [mode]);

  function bulkApply() {
    setAppliedIds(Array.from(new Set([...appliedIds, ...filtered.map((item) => item.id)])));
  }

  return (
    <ScrollView contentInsetAdjustmentBehavior="automatic" style={{ flex: 1, backgroundColor: '#f4f7fb' }} contentContainerStyle={{ padding: 20, gap: 16 }}>
      <StatusBar style="dark" />
      <View style={{ gap: 6 }}>
        <Text selectable style={{ color: '#1f8a7a', fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>TutorMatch Mobile</Text>
        <Text selectable style={{ color: '#172033', fontSize: 30, fontWeight: '800' }}>Assignment Feed</Text>
        <Text selectable style={{ color: '#64748b', lineHeight: 22 }}>Mock tutor-side flow for browsing filtered assignments and bulk applying.</Text>
      </View>

      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
        {(['all', 'home', 'online', 'hybrid'] as const).map((item) => (
          <Pressable key={item} onPress={() => setMode(item)} style={{ paddingVertical: 9, paddingHorizontal: 12, borderRadius: 999, backgroundColor: mode === item ? '#172033' : '#fff', borderWidth: 1, borderColor: '#dbe3ef' }}>
            <Text selectable style={{ color: mode === item ? '#fff' : '#172033', fontWeight: '700' }}>{item}</Text>
          </Pressable>
        ))}
      </View>

      <Pressable onPress={bulkApply} style={{ padding: 14, borderRadius: 8, backgroundColor: '#1f8a7a' }}>
        <Text selectable style={{ color: '#fff', fontWeight: '800', textAlign: 'center' }}>Bulk Apply to Filtered Assignments</Text>
      </Pressable>

      <View style={{ gap: 10 }}>
        {filtered.map((assignment) => (
          <Pressable key={assignment.id} onPress={() => setSelected(assignment)} style={{ padding: 16, borderRadius: 8, backgroundColor: selected.id === assignment.id ? '#eaf7f5' : '#fff', borderWidth: 1, borderColor: selected.id === assignment.id ? '#5eb6ad' : '#dbe3ef' }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 12 }}>
              <Text selectable style={{ color: '#172033', fontSize: 17, fontWeight: '800', flex: 1 }}>{assignment.title}</Text>
              <Text selectable style={{ color: '#1f8a7a', fontWeight: '800', fontVariant: ['tabular-nums'] }}>{assignment.fit}%</Text>
            </View>
            <Text selectable style={{ color: '#64748b', marginTop: 6 }}>{assignment.level} · {assignment.location} · {assignment.budget}</Text>
            {appliedIds.includes(assignment.id) && <Text selectable style={{ color: '#1f8a7a', marginTop: 8, fontWeight: '700' }}>Applied mock</Text>}
          </Pressable>
        ))}
      </View>

      <View style={{ padding: 18, borderRadius: 8, backgroundColor: '#fff', borderWidth: 1, borderColor: '#dbe3ef', gap: 10 }}>
        <Text selectable style={{ color: '#64748b', fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>Assignment Detail</Text>
        <Text selectable style={{ color: '#172033', fontSize: 22, fontWeight: '800' }}>{selected.title}</Text>
        <Text selectable style={{ color: '#334155', lineHeight: 22 }}>{selected.notes}</Text>
        <Text selectable style={{ color: '#64748b' }}>{selected.subject} · {selected.level}</Text>
        <Text selectable style={{ color: '#64748b' }}>{selected.location} · {selected.mode} · {selected.schedule}</Text>
      </View>
    </ScrollView>
  );
}
