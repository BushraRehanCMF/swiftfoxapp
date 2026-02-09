import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';

type Label = {
  id: string;
  name: string;
  color: string;
  created_at?: string;
};

const Labels: React.FC = () => {
  const [labels, setLabels] = useState<Label[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [name, setName] = useState('');
  const [color, setColor] = useState('#10b981');
  const [editingId, setEditingId] = useState<string | null>(null);

  const isEditing = useMemo(() => Boolean(editingId), [editingId]);

  const fetchLabels = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/labels');
      setLabels(data.data || []);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load labels.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLabels();
  }, []);

  const resetForm = () => {
    setName('');
    setColor('#10b981');
    setEditingId(null);
  };

  const startEdit = (label: Label) => {
    setName(label.name);
    setColor(label.color);
    setEditingId(label.id);
    setNotice('');
    setError('');
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');
    setNotice('');

    if (!name.trim()) {
      setError('Label name is required.');
      return;
    }

    setSaving(true);
    try {
      if (isEditing && editingId) {
        await api.put(`/labels/${editingId}`, {
          name: name.trim(),
          color,
        });
        setNotice('Label updated successfully.');
      } else {
        await api.post('/labels', {
          name: name.trim(),
          color,
        });
        setNotice('Label created successfully.');
      }

      resetForm();
      await fetchLabels();
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to save label.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (label: Label) => {
    setError('');
    setNotice('');
    if (!confirm(`Delete the label "${label.name}"?`)) return;

    try {
      await api.delete(`/labels/${label.id}`);
      setLabels(prev => prev.filter(item => item.id !== label.id));
      if (editingId === label.id) {
        resetForm();
      }
      setNotice('Label deleted successfully.');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to delete label.');
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Labels</h1>
        <p className="text-sm text-gray-600 mt-1">Create labels to organize conversations and contacts.</p>
      </div>

      {error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {notice && <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>}

      <form onSubmit={handleSubmit} className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div className="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Label name</label>
            <input
              type="text"
              value={name}
              onChange={event => setName(event.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="e.g. Priority"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Color</label>
            <input
              type="color"
              value={color}
              onChange={event => setColor(event.target.value)}
              className="h-10 w-16 cursor-pointer rounded border border-gray-300 bg-white"
              aria-label="Pick a label color"
            />
          </div>
        </div>
        <div className="mt-4 flex flex-wrap gap-3">
          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
          >
            {saving ? 'Saving...' : isEditing ? 'Update Label' : 'Create Label'}
          </button>
          {isEditing && (
            <button
              type="button"
              onClick={resetForm}
              className="inline-flex items-center rounded border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </button>
          )}
        </div>
      </form>

      <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div className="border-b border-gray-200 px-6 py-4 text-sm font-semibold text-gray-700">Existing labels</div>
        {loading ? (
          <div className="px-6 py-4 text-sm text-gray-500">Loading labels...</div>
        ) : labels.length === 0 ? (
          <div className="px-6 py-6 text-sm text-gray-500">No labels yet. Create your first label above.</div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {labels.map(label => (
              <li key={label.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                <div className="flex items-center gap-3">
                  <span className="h-3 w-3 rounded-full" style={{ backgroundColor: label.color }} />
                  <div>
                    <div className="text-sm font-medium text-gray-900">{label.name}</div>
                    <div className="text-xs text-gray-500">{label.color}</div>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => startEdit(label)}
                    className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    onClick={() => handleDelete(label)}
                    className="rounded border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                  >
                    Delete
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

export default Labels;
