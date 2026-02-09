import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';

type Label = {
  id: string;
  name: string;
  color: string;
};

type Contact = {
  id: string;
  name: string | null;
  phone_number: string;
  labels?: Label[];
};

const Contacts: React.FC = () => {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [labels, setLabels] = useState<Label[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [labelFilter, setLabelFilter] = useState('');
  const [selected, setSelected] = useState<Contact | null>(null);
  const [name, setName] = useState('');
  const [selectedLabelIds, setSelectedLabelIds] = useState<string[]>([]);

  const filteredContacts = useMemo(() => contacts, [contacts]);

  const fetchLabels = async () => {
    try {
      const { data } = await api.get('/labels');
      setLabels(data.data || []);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load labels.');
    }
  };

  const fetchContacts = async (params?: { search?: string; label_id?: string }) => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/contacts', { params });
      setContacts(data.data || []);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load contacts.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLabels();
    fetchContacts();
  }, []);

  const applyFilters = () => {
    fetchContacts({
      search: search.trim() || undefined,
      label_id: labelFilter || undefined,
    });
  };

  const startEdit = (contact: Contact) => {
    setSelected(contact);
    setName(contact.name || '');
    setSelectedLabelIds((contact.labels || []).map(label => label.id));
    setNotice('');
    setError('');
  };

  const toggleLabel = (labelId: string) => {
    setSelectedLabelIds(prev =>
      prev.includes(labelId) ? prev.filter(id => id !== labelId) : [...prev, labelId]
    );
  };

  const saveContact = async () => {
    if (!selected) return;
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await api.put(`/contacts/${selected.id}`, { name: name.trim() || null });
      await api.post(`/contacts/${selected.id}/labels`, { label_ids: selectedLabelIds });
      setNotice('Contact updated successfully.');
      await fetchContacts({
        search: search.trim() || undefined,
        label_id: labelFilter || undefined,
      });

      const refreshed = contacts.find(contact => contact.id === selected.id) || null;
      setSelected(refreshed);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to update contact.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Contacts</h1>
        <p className="text-sm text-gray-600 mt-1">Manage contacts and label assignments.</p>
      </div>

      {error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {notice && <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>}

      <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div className="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              type="text"
              value={search}
              onChange={event => setSearch(event.target.value)}
              placeholder="Search by name or phone"
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Filter by label</label>
            <select
              value={labelFilter}
              onChange={event => setLabelFilter(event.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
            >
              <option value="">All labels</option>
              {labels.map(label => (
                <option key={label.id} value={label.id}>
                  {label.name}
                </option>
              ))}
            </select>
          </div>
          <button
            type="button"
            onClick={applyFilters}
            className="h-10 rounded bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"
          >
            Apply
          </button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-200 px-6 py-4 text-sm font-semibold text-gray-700">Contacts</div>
          {loading ? (
            <div className="px-6 py-4 text-sm text-gray-500">Loading contacts...</div>
          ) : filteredContacts.length === 0 ? (
            <div className="px-6 py-6 text-sm text-gray-500">No contacts found.</div>
          ) : (
            <ul className="divide-y divide-gray-100">
              {filteredContacts.map(contact => (
                <li key={contact.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                  <div>
                    <div className="text-sm font-medium text-gray-900">{contact.name || 'Unnamed contact'}</div>
                    <div className="text-xs text-gray-500">{contact.phone_number}</div>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {(contact.labels || []).length === 0 ? (
                        <span className="text-xs text-gray-400">No labels</span>
                      ) : (
                        (contact.labels || []).map(label => (
                          <span
                            key={label.id}
                            className="inline-flex items-center gap-1 rounded-full border border-gray-200 px-2 py-0.5 text-xs"
                          >
                            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: label.color }} />
                            {label.name}
                          </span>
                        ))
                      )}
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => startEdit(contact)}
                    className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Edit
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <div className="text-sm font-semibold text-gray-700">Contact details</div>
          {!selected ? (
            <div className="mt-4 text-sm text-gray-500">Select a contact to edit.</div>
          ) : (
            <div className="mt-4 space-y-4">
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">Phone</div>
                <div className="text-sm text-gray-900">{selected.phone_number}</div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input
                  type="text"
                  value={name}
                  onChange={event => setName(event.target.value)}
                  className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                />
              </div>
              <div>
                <div className="text-sm font-medium text-gray-700 mb-2">Labels</div>
                <div className="space-y-2">
                  {labels.length === 0 ? (
                    <div className="text-sm text-gray-500">No labels available.</div>
                  ) : (
                    labels.map(label => (
                      <label key={label.id} className="flex items-center gap-2 text-sm text-gray-700">
                        <input
                          type="checkbox"
                          checked={selectedLabelIds.includes(label.id)}
                          onChange={() => toggleLabel(label.id)}
                          className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600"
                        />
                        <span className="flex items-center gap-2">
                          <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: label.color }} />
                          {label.name}
                        </span>
                      </label>
                    ))
                  )}
                </div>
              </div>
              <button
                type="button"
                onClick={saveContact}
                disabled={saving}
                className="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
              >
                {saving ? 'Saving...' : 'Save changes'}
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default Contacts;
