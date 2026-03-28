import React, { useEffect, useState } from 'react';
import api from '../services/api';

type TemplateComponent = {
  type: string;
  text?: string;
  format?: string;
  buttons?: Array<{ type: string; text: string; url?: string; phone_number?: string }>;
  example?: { body_text?: string[][] };
};

type Template = {
  id: string;
  name: string;
  status: string;
  language: string;
  category: string;
  components: TemplateComponent[];
};

const CATEGORIES = [
  { value: 'MARKETING', label: 'Marketing', description: 'Promotions, offers, updates' },
  { value: 'UTILITY', label: 'Utility', description: 'Order updates, alerts, confirmations' },
  { value: 'AUTHENTICATION', label: 'Authentication', description: 'OTP codes, verification' },
];

const LANGUAGES = [
  { value: 'en_US', label: 'English (US)' },
  { value: 'en_GB', label: 'English (UK)' },
  { value: 'de', label: 'German' },
  { value: 'fr', label: 'French' },
  { value: 'es', label: 'Spanish' },
  { value: 'pt_BR', label: 'Portuguese (BR)' },
  { value: 'ar', label: 'Arabic' },
  { value: 'hi', label: 'Hindi' },
  { value: 'tr', label: 'Turkish' },
  { value: 'ur', label: 'Urdu' },
];

const statusColors: Record<string, string> = {
  APPROVED: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  PENDING: 'bg-yellow-100 text-yellow-700 border-yellow-200',
  REJECTED: 'bg-red-100 text-red-700 border-red-200',
  DISABLED: 'bg-gray-100 text-gray-500 border-gray-200',
};

const Templates: React.FC = () => {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [showForm, setShowForm] = useState(false);

  // Form state
  const [name, setName] = useState('');
  const [language, setLanguage] = useState('en_US');
  const [category, setCategory] = useState('MARKETING');
  const [headerText, setHeaderText] = useState('');
  const [bodyText, setBodyText] = useState('');
  const [footerText, setFooterText] = useState('');

  // Send template state
  const [sendingTemplate, setSendingTemplate] = useState<string | null>(null);
  const [sendPhone, setSendPhone] = useState('');
  const [sending, setSending] = useState(false);

  const fetchTemplates = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/whatsapp/templates');
      setTemplates(data.data || []);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load templates.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTemplates();
  }, []);

  const resetForm = () => {
    setName('');
    setLanguage('en_US');
    setCategory('MARKETING');
    setHeaderText('');
    setBodyText('');
    setFooterText('');
    setShowForm(false);
  };

  const handleCreate = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');
    setNotice('');

    if (!name.trim()) {
      setError('Template name is required.');
      return;
    }
    if (!bodyText.trim()) {
      setError('Message body is required.');
      return;
    }

    const components: Array<{ type: string; text?: string; format?: string }> = [];

    if (headerText.trim()) {
      components.push({ type: 'HEADER', format: 'TEXT', text: headerText.trim() });
    }

    components.push({ type: 'BODY', text: bodyText.trim() });

    if (footerText.trim()) {
      components.push({ type: 'FOOTER', text: footerText.trim() });
    }

    setSaving(true);
    try {
      await api.post('/whatsapp/templates', {
        name: name.trim().toLowerCase().replace(/\s+/g, '_'),
        language,
        category,
        components,
      });
      setNotice('Template created and submitted for review. Meta usually approves within a few minutes.');
      resetForm();
      await fetchTemplates();
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to create template.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (templateName: string) => {
    if (!confirm(`Delete template "${templateName}"? This cannot be undone.`)) return;
    setError('');
    setNotice('');
    try {
      await api.delete('/whatsapp/templates', { data: { name: templateName } });
      setTemplates(prev => prev.filter(t => t.name !== templateName));
      setNotice('Template deleted.');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to delete template.');
    }
  };

  const handleSendTemplate = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!sendingTemplate || !sendPhone.trim()) return;

    const template = templates.find(t => t.name === sendingTemplate);
    if (!template) return;

    setError('');
    setNotice('');
    setSending(true);
    try {
      await api.post('/whatsapp/send-template', {
        phone_number: sendPhone.trim(),
        template_name: sendingTemplate,
        language_code: template.language,
      });
      setNotice(`Template message "${sendingTemplate}" sent to ${sendPhone}.`);
      setSendingTemplate(null);
      setSendPhone('');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to send template message.');
    } finally {
      setSending(false);
    }
  };

  const getComponentText = (template: Template, type: string): string => {
    const comp = template.components?.find(c => c.type === type);
    return comp?.text || '';
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Message Templates</h1>
          <p className="text-sm text-gray-600 mt-1">
            Create and manage WhatsApp message templates for business-initiated conversations.
          </p>
        </div>
        {!showForm && (
          <button
            onClick={() => setShowForm(true)}
            className="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
          >
            + New Template
          </button>
        )}
      </div>

      {error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {notice && <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>}

      {/* Create Template Form */}
      {showForm && (
        <form onSubmit={handleCreate} className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Create New Template</h2>

          <div className="grid gap-4 sm:grid-cols-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Template name</label>
              <input
                type="text"
                value={name}
                onChange={e => setName(e.target.value)}
                className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                placeholder="e.g. welcome_message"
                pattern="[a-z0-9_]+"
                title="Lowercase letters, numbers, and underscores only"
                required
              />
              <p className="text-xs text-gray-500 mt-1">Lowercase, numbers, underscores only</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Language</label>
              <select
                value={language}
                onChange={e => setLanguage(e.target.value)}
                className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              >
                {LANGUAGES.map(l => (
                  <option key={l.value} value={l.value}>{l.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
              <select
                value={category}
                onChange={e => setCategory(e.target.value)}
                className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              >
                {CATEGORIES.map(c => (
                  <option key={c.value} value={c.value}>{c.label} - {c.description}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="mt-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Header (optional)</label>
            <input
              type="text"
              value={headerText}
              onChange={e => setHeaderText(e.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="e.g. Welcome to our service!"
              maxLength={60}
            />
            <p className="text-xs text-gray-500 mt-1">{headerText.length}/60 characters</p>
          </div>

          <div className="mt-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Body</label>
            <textarea
              value={bodyText}
              onChange={e => setBodyText(e.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="e.g. Hello! Thank you for reaching out. How can we help you today?"
              rows={4}
              maxLength={1024}
              required
            />
            <p className="text-xs text-gray-500 mt-1">
              {bodyText.length}/1024 characters. Use {'{{1}}'}, {'{{2}}'} etc. for dynamic variables.
            </p>
          </div>

          <div className="mt-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Footer (optional)</label>
            <input
              type="text"
              value={footerText}
              onChange={e => setFooterText(e.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="e.g. Reply STOP to unsubscribe"
              maxLength={60}
            />
            <p className="text-xs text-gray-500 mt-1">{footerText.length}/60 characters</p>
          </div>

          {/* Preview */}
          {bodyText.trim() && (
            <div className="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
              <p className="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Preview</p>
              <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-4 max-w-sm">
                {headerText.trim() && (
                  <p className="text-sm font-semibold text-gray-900 mb-1">{headerText}</p>
                )}
                <p className="text-sm text-gray-700 whitespace-pre-wrap">{bodyText}</p>
                {footerText.trim() && (
                  <p className="text-xs text-gray-400 mt-2">{footerText}</p>
                )}
              </div>
            </div>
          )}

          <div className="mt-4 flex flex-wrap gap-3">
            <button
              type="submit"
              disabled={saving}
              className="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
            >
              {saving ? 'Creating...' : 'Create Template'}
            </button>
            <button
              type="button"
              onClick={resetForm}
              className="inline-flex items-center rounded border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
        </form>
      )}

      {/* Templates List */}
      <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div className="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
          <span className="text-sm font-semibold text-gray-700">Your Templates</span>
          <button
            onClick={fetchTemplates}
            disabled={loading}
            className="text-xs text-emerald-600 hover:text-emerald-700 font-medium"
          >
            {loading ? 'Loading...' : 'Refresh'}
          </button>
        </div>

        {loading ? (
          <div className="px-6 py-8 text-sm text-gray-500 text-center">Loading templates...</div>
        ) : templates.length === 0 ? (
          <div className="px-6 py-8 text-sm text-gray-500 text-center">
            No templates yet. Click "New Template" to create your first one.
          </div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {templates.map(template => (
              <li key={template.id || template.name} className="px-6 py-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-sm font-medium text-gray-900">{template.name}</span>
                      <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${statusColors[template.status] || statusColors.DISABLED}`}>
                        {template.status}
                      </span>
                      <span className="text-xs text-gray-400">{template.language}</span>
                      <span className="text-xs text-gray-400">{template.category}</span>
                    </div>
                    {getComponentText(template, 'HEADER') && (
                      <p className="text-xs font-semibold text-gray-600 mt-1">{getComponentText(template, 'HEADER')}</p>
                    )}
                    <p className="text-sm text-gray-600 mt-1 line-clamp-2">{getComponentText(template, 'BODY')}</p>
                    {getComponentText(template, 'FOOTER') && (
                      <p className="text-xs text-gray-400 mt-1">{getComponentText(template, 'FOOTER')}</p>
                    )}
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {template.status === 'APPROVED' && (
                      <button
                        onClick={() => {
                          setSendingTemplate(sendingTemplate === template.name ? null : template.name);
                          setSendPhone('');
                        }}
                        className="rounded border border-emerald-200 px-3 py-1.5 text-xs font-medium text-emerald-600 hover:bg-emerald-50"
                      >
                        {sendingTemplate === template.name ? 'Cancel' : 'Send'}
                      </button>
                    )}
                    <button
                      onClick={() => handleDelete(template.name)}
                      className="rounded border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                      Delete
                    </button>
                  </div>
                </div>

                {/* Send Template Form (inline) */}
                {sendingTemplate === template.name && (
                  <form onSubmit={handleSendTemplate} className="mt-3 flex items-center gap-2">
                    <input
                      type="text"
                      value={sendPhone}
                      onChange={e => setSendPhone(e.target.value)}
                      className="flex-1 rounded border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                      placeholder="Phone number (e.g. +1234567890)"
                      required
                    />
                    <button
                      type="submit"
                      disabled={sending}
                      className="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:bg-emerald-300"
                    >
                      {sending ? 'Sending...' : 'Send Template'}
                    </button>
                  </form>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

export default Templates;
