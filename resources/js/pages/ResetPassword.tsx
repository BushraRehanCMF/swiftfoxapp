import React, { useState } from 'react';
import { requestPasswordReset } from '../services/password';

const ResetPassword: React.FC = () => {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    if (!email) {
      setError('Email is required.');
      setLoading(false);
      return;
    }
    try {
      await requestPasswordReset(email);
      setSent(true);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Failed to send reset link.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <form onSubmit={handleSubmit} className="bg-white p-8 rounded shadow-md w-full max-w-sm">
        <h2 className="text-2xl font-bold text-emerald-700 mb-6 text-center">Reset Password</h2>
        {sent ? (
          <div className="text-green-700 text-sm mb-4">If your email exists, a reset link has been sent.</div>
        ) : (
          <>
            {error && <div className="mb-4 text-red-600 text-sm">{error}</div>}
            <div className="mb-6">
              <label className="block text-gray-700 mb-1">Email</label>
              <input
                type="email"
                className="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600"
                value={email}
                onChange={e => setEmail(e.target.value)}
                autoFocus
                required
              />
            </div>
            <button
              type="submit"
              className="w-full bg-emerald-600 text-white py-2 rounded font-semibold hover:bg-emerald-700 transition"
              disabled={loading}
            >
              {loading ? 'Sending...' : 'Send Reset Link'}
            </button>
          </>
        )}
        <div className="mt-4 text-center">
          <a href="/login" className="text-emerald-700 hover:underline text-sm">Back to login</a>
        </div>
      </form>
    </div>
  );
};

export default ResetPassword;
