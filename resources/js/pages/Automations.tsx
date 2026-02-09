import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';

type Option = {
  value: string;
  label: string;
};

type AutomationRule = {
  id: string;
  name: string;
  trigger_type: string;
  trigger_label?: string;
  conditions?: Condition[] | null;
  actions?: Action[];
  is_enabled: boolean;
  created_at?: string;
};

type Condition = {
  field: string;
  operator: string;
  value: string;
};

type Action = {
  type: string;
  value: string;
};

type Label = {
  id: string;
  name: string;
  color: string;
};

type TeamMember = {
  id: string;
  name: string;
  email: string;
};

const conditionFields: Option[] = [
  { value: 'message_content', label: 'Message content' },
  { value: 'contact_name', label: 'Contact name' },
  { value: 'contact_phone', label: 'Contact phone' },
  { value: 'conversation_status', label: 'Conversation status' },
];

const conditionOperators: Option[] = [
  { value: 'equals', label: 'Equals' },
  { value: 'not_equals', label: 'Not equals' },
  { value: 'contains', label: 'Contains' },
  { value: 'not_contains', label: 'Not contains' },
  { value: 'starts_with', label: 'Starts with' },
  { value: 'ends_with', label: 'Ends with' },
];

const Automations: React.FC = () => {
  const [rules, setRules] = useState<AutomationRule[]>([]);
  const [triggers, setTriggers] = useState<Option[]>([]);
  const [actions, setActions] = useState<Option[]>([]);
  const [labels, setLabels] = useState<Label[]>([]);
  const [team, setTeam] = useState<TeamMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [editingId, setEditingId] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [triggerType, setTriggerType] = useState('');
  const [isEnabled, setIsEnabled] = useState(true);
  const [conditions, setConditions] = useState<Condition[]>([]);
  const [ruleActions, setRuleActions] = useState<Action[]>([]);

  const isEditing = useMemo(() => Boolean(editingId), [editingId]);

  const resetForm = () => {
    setEditingId(null);
    setName('');
    setTriggerType('');
    setIsEnabled(true);
    setConditions([]);
    setRuleActions([]);
  };

  const fetchInitial = async () => {
    setLoading(true);
    setError('');
    try {
      const [rulesRes, triggersRes, actionsRes, labelsRes, teamRes] = await Promise.all([
        api.get('/automations'),
        api.get('/automations/triggers'),
        api.get('/automations/actions'),
        api.get('/labels'),
        api.get('/team'),
      ]);

      setRules(rulesRes.data.data || []);
      setTriggers(triggersRes.data.data || []);
      setActions(actionsRes.data.data || []);
      setLabels(labelsRes.data.data || []);
      setTeam(teamRes.data.data || []);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load automations.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInitial();
  }, []);

  const startEdit = (rule: AutomationRule) => {
    setEditingId(rule.id);
    setName(rule.name);
    setTriggerType(rule.trigger_type);
    setIsEnabled(rule.is_enabled);
    setConditions((rule.conditions || []) as Condition[]);
    setRuleActions((rule.actions || []) as Action[]);
    setNotice('');
    setError('');
  };

  const updateCondition = (index: number, updates: Partial<Condition>) => {
    setConditions(prev =>
      prev.map((condition, idx) => (idx === index ? { ...condition, ...updates } : condition))
    );
  };

  const updateAction = (index: number, updates: Partial<Action>) => {
    setRuleActions(prev => prev.map((action, idx) => (idx === index ? { ...action, ...updates } : action)));
  };

  const addCondition = () => {
    setConditions(prev => [
      ...prev,
      { field: conditionFields[0].value, operator: conditionOperators[0].value, value: '' },
    ]);
  };

  const removeCondition = (index: number) => {
    setConditions(prev => prev.filter((_, idx) => idx !== index));
  };

  const addAction = () => {
    const firstAction = actions[0]?.value || 'assign_user';
    setRuleActions(prev => [...prev, { type: firstAction, value: '' }]);
  };

  const removeAction = (index: number) => {
    setRuleActions(prev => prev.filter((_, idx) => idx !== index));
  };

  const submitRule = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');
    setNotice('');

    if (!name.trim()) {
      setError('Rule name is required.');
      return;
    }

    if (!triggerType) {
      setError('Select a trigger type.');
      return;
    }

    if (ruleActions.length === 0) {
      setError('Add at least one action.');
      return;
    }

    const payload = {
      name: name.trim(),
      trigger_type: triggerType,
      is_enabled: isEnabled,
      conditions: conditions.length ? conditions : null,
      actions: ruleActions,
    };

    setSaving(true);
    try {
      if (isEditing && editingId) {
        await api.put(`/automations/${editingId}`, payload);
        setNotice('Automation rule updated successfully.');
      } else {
        await api.post('/automations', payload);
        setNotice('Automation rule created successfully.');
      }

      resetForm();
      await fetchInitial();
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to save automation rule.');
    } finally {
      setSaving(false);
    }
  };

  const toggleRule = async (rule: AutomationRule) => {
    setError('');
    setNotice('');
    try {
      const { data } = await api.post(`/automations/${rule.id}/toggle`);
      setRules(prev => prev.map(item => (item.id === rule.id ? data.data : item)));
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to toggle rule.');
    }
  };

  const deleteRule = async (rule: AutomationRule) => {
    setError('');
    setNotice('');
    if (!confirm(`Delete automation rule "${rule.name}"?`)) return;

    try {
      await api.delete(`/automations/${rule.id}`);
      setRules(prev => prev.filter(item => item.id !== rule.id));
      if (editingId === rule.id) {
        resetForm();
      }
      setNotice('Automation rule deleted successfully.');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to delete rule.');
    }
  };

  const renderActionValue = (action: Action, index: number) => {
    if (action.type === 'assign_user') {
      return (
        <select
          value={action.value}
          onChange={event => updateAction(index, { value: event.target.value })}
          className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
        >
          <option value="">Select a user</option>
          {team.map(user => (
            <option key={user.id} value={user.id}>
              {user.name} ({user.email})
            </option>
          ))}
        </select>
      );
    }

    if (action.type === 'add_label') {
      return (
        <select
          value={action.value}
          onChange={event => updateAction(index, { value: event.target.value })}
          className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
        >
          <option value="">Select a label</option>
          {labels.map(label => (
            <option key={label.id} value={label.id}>
              {label.name}
            </option>
          ))}
        </select>
      );
    }

    return (
      <input
        type="text"
        value={action.value}
        onChange={event => updateAction(index, { value: event.target.value })}
        placeholder="Auto-reply message"
        className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
      />
    );
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Automations</h1>
        <p className="text-sm text-gray-600 mt-1">Create rules to automate assignments, labels, and replies.</p>
      </div>

      {error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {notice && <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>}

      <form onSubmit={submitRule} className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm space-y-6">
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Rule name</label>
            <input
              type="text"
              value={name}
              onChange={event => setName(event.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="e.g. Tag urgent messages"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Trigger</label>
            <select
              value={triggerType}
              onChange={event => setTriggerType(event.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              required
            >
              <option value="">Select trigger</option>
              {triggers.map(trigger => (
                <option key={trigger.value} value={trigger.value}>
                  {trigger.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <label className="inline-flex items-center gap-2 text-sm text-gray-700">
          <input
            type="checkbox"
            checked={isEnabled}
            onChange={event => setIsEnabled(event.target.checked)}
            className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600"
          />
          Enable rule
        </label>

        <div>
          <div className="flex items-center justify-between">
            <div className="text-sm font-semibold text-gray-700">Conditions (optional)</div>
            <button
              type="button"
              onClick={addCondition}
              className="text-xs font-medium text-emerald-700 hover:underline"
            >
              Add condition
            </button>
          </div>
          {conditions.length === 0 ? (
            <div className="mt-2 text-sm text-gray-500">No conditions. This rule will always run when triggered.</div>
          ) : (
            <div className="mt-3 space-y-3">
              {conditions.map((condition, index) => (
                <div key={`condition-${index}`} className="grid gap-2 sm:grid-cols-[160px_160px_1fr_auto]">
                  <select
                    value={condition.field}
                    onChange={event => updateCondition(index, { field: event.target.value })}
                    className="rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                  >
                    {conditionFields.map(field => (
                      <option key={field.value} value={field.value}>
                        {field.label}
                      </option>
                    ))}
                  </select>
                  <select
                    value={condition.operator}
                    onChange={event => updateCondition(index, { operator: event.target.value })}
                    className="rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                  >
                    {conditionOperators.map(op => (
                      <option key={op.value} value={op.value}>
                        {op.label}
                      </option>
                    ))}
                  </select>
                  <input
                    type="text"
                    value={condition.value}
                    onChange={event => updateCondition(index, { value: event.target.value })}
                    className="rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                  />
                  <button
                    type="button"
                    onClick={() => removeCondition(index)}
                    className="rounded border border-gray-200 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50"
                  >
                    Remove
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        <div>
          <div className="flex items-center justify-between">
            <div className="text-sm font-semibold text-gray-700">Actions</div>
            <button
              type="button"
              onClick={addAction}
              className="text-xs font-medium text-emerald-700 hover:underline"
            >
              Add action
            </button>
          </div>
          {ruleActions.length === 0 ? (
            <div className="mt-2 text-sm text-gray-500">Add at least one action.</div>
          ) : (
            <div className="mt-3 space-y-3">
              {ruleActions.map((action, index) => (
                <div key={`action-${index}`} className="grid gap-2 sm:grid-cols-[200px_1fr_auto]">
                  <select
                    value={action.type}
                    onChange={event => updateAction(index, { type: event.target.value, value: '' })}
                    className="rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                  >
                    {actions.map(actionType => (
                      <option key={actionType.value} value={actionType.value}>
                        {actionType.label}
                      </option>
                    ))}
                  </select>
                  {renderActionValue(action, index)}
                  <button
                    type="button"
                    onClick={() => removeAction(index)}
                    className="rounded border border-gray-200 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50"
                  >
                    Remove
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="flex flex-wrap gap-3">
          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
          >
            {saving ? 'Saving...' : isEditing ? 'Update rule' : 'Create rule'}
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
        <div className="border-b border-gray-200 px-6 py-4 text-sm font-semibold text-gray-700">Existing rules</div>
        {loading ? (
          <div className="px-6 py-4 text-sm text-gray-500">Loading rules...</div>
        ) : rules.length === 0 ? (
          <div className="px-6 py-6 text-sm text-gray-500">No automation rules yet.</div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {rules.map(rule => (
              <li key={rule.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                <div>
                  <div className="text-sm font-medium text-gray-900">{rule.name}</div>
                  <div className="text-xs text-gray-500">Trigger: {rule.trigger_label || rule.trigger_type}</div>
                  <div className="text-xs text-gray-500">Status: {rule.is_enabled ? 'Enabled' : 'Disabled'}</div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    onClick={() => toggleRule(rule)}
                    className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                  >
                    {rule.is_enabled ? 'Disable' : 'Enable'}
                  </button>
                  <button
                    type="button"
                    onClick={() => startEdit(rule)}
                    className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    onClick={() => deleteRule(rule)}
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

export default Automations;
