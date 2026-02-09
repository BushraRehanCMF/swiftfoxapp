import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';

type TeamMember = {
  id: string;
  name: string;
  email: string;
  role: 'owner' | 'member';
  email_verified_at?: string | null;
};

type CurrentUser = {
  id: string;
  role: 'owner' | 'member' | 'super_admin';
};

const Team: React.FC = () => {
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [currentUser, setCurrentUser] = useState<CurrentUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<'member' | 'owner'>('member');

  const canManage = useMemo(() => currentUser?.role === 'owner', [currentUser]);

  const fetchTeam = async () => {
    setLoading(true);
    setError('');
    try {
      const [userRes, teamRes] = await Promise.all([
        api.get('/auth/user'),
        api.get('/team'),
      ]);

      setCurrentUser(userRes.data.data);
      setMembers(teamRes.data.data || []);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load team members.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTeam();
  }, []);

  const invite = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!canManage) return;
    setSaving(true);
    setError('');
    setNotice('');

    try {
      await api.post('/team/invite', {
        name: name.trim(),
        email: email.trim(),
        role,
      });
      setNotice('Invitation sent successfully.');
      setName('');
      setEmail('');
      setRole('member');
      await fetchTeam();
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to invite team member.');
    } finally {
      setSaving(false);
    }
  };

  const updateRole = async (member: TeamMember, nextRole: 'owner' | 'member') => {
    if (!canManage || member.id === currentUser?.id) return;
    setError('');
    setNotice('');

    try {
      const { data } = await api.put(`/team/${member.id}/role`, { role: nextRole });
      setMembers(prev => prev.map(item => (item.id === member.id ? data.data : item)));
      setNotice('Role updated successfully.');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to update role.');
    }
  };

  const removeMember = async (member: TeamMember) => {
    if (!canManage || member.id === currentUser?.id) return;
    setError('');
    setNotice('');
    if (!confirm(`Remove ${member.name} from the team?`)) return;

    try {
      await api.delete(`/team/${member.id}`);
      setMembers(prev => prev.filter(item => item.id !== member.id));
      setNotice('Team member removed.');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to remove team member.');
    }
  };

  const resendInvite = async (member: TeamMember) => {
    if (!canManage) return;
    setError('');
    setNotice('');

    try {
      await api.post(`/team/${member.id}/resend-invite`);
      setNotice('Invitation email resent.');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to resend invite.');
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Team</h1>
        <p className="text-sm text-gray-600 mt-1">Invite and manage team members.</p>
      </div>

      {error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {notice && <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>}

      <form onSubmit={invite} className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div className="flex items-center justify-between">
          <div className="text-sm font-semibold text-gray-700">Invite member</div>
          {!canManage && (
            <span className="text-xs text-gray-500">Only owners can invite team members.</span>
          )}
        </div>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input
              type="text"
              value={name}
              onChange={event => setName(event.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="Jane Doe"
              required
              disabled={!canManage}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              type="email"
              value={email}
              onChange={event => setEmail(event.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="jane@company.com"
              required
              disabled={!canManage}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select
              value={role}
              onChange={event => setRole(event.target.value as 'member' | 'owner')}
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              disabled={!canManage}
            >
              <option value="member">Member</option>
              <option value="owner">Owner</option>
            </select>
          </div>
        </div>
        <button
          type="submit"
          disabled={!canManage || saving}
          className="mt-4 inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
        >
          {saving ? 'Inviting...' : 'Send invite'}
        </button>
      </form>

      <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div className="border-b border-gray-200 px-6 py-4 text-sm font-semibold text-gray-700">Team members</div>
        {loading ? (
          <div className="px-6 py-4 text-sm text-gray-500">Loading team members...</div>
        ) : members.length === 0 ? (
          <div className="px-6 py-6 text-sm text-gray-500">No team members yet.</div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {members.map(member => {
              const isSelf = member.id === currentUser?.id;
              const isVerified = Boolean(member.email_verified_at);
              return (
                <li key={member.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                  <div>
                    <div className="text-sm font-medium text-gray-900">{member.name}</div>
                    <div className="text-xs text-gray-500">{member.email}</div>
                    <div className="mt-2 text-xs text-gray-500">
                      {isVerified ? 'Verified' : 'Invite pending'}
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <select
                      value={member.role}
                      onChange={event => updateRole(member, event.target.value as 'owner' | 'member')}
                      className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700"
                      disabled={!canManage || isSelf}
                    >
                      <option value="member">Member</option>
                      <option value="owner">Owner</option>
                    </select>
                    {!isVerified && (
                      <button
                        type="button"
                        onClick={() => resendInvite(member)}
                        className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        disabled={!canManage}
                      >
                        Resend invite
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => removeMember(member)}
                      className="rounded border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                      disabled={!canManage || isSelf}
                    >
                      Remove
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
};

export default Team;
