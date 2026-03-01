import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import logo from '../assets/logo.png';
import { useAuth } from '../stores/auth';

const timezoneOptions = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Europe/London',
  'Europe/Berlin',
  'Asia/Dubai',
  'Asia/Kolkata',
  'Asia/Singapore',
  'Asia/Tokyo',
  'Australia/Sydney',
];

const Register: React.FC = () => {
  const [name, setName] = useState('');
  const [companyName, setCompanyName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [timezone, setTimezone] = useState('UTC');
  const [error, setError] = useState('');
  const { register, loading } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!name || !companyName || !email || !password || !passwordConfirmation) {
      setError('All fields are required.');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Passwords do not match.');
      return;
    }

    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        company_name: companyName,
        timezone,
      });

      navigate('/inbox');
    } catch (err: any) {
      setError(err.message || 'Registration failed.');
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
      <form onSubmit={handleSubmit} className="bg-white p-8 rounded shadow-md w-full max-w-md">
        <div className="mb-4 flex justify-center">
          <img src={logo} alt="SwiftFox logo" className="h-10 w-auto" />
        </div>
        <h2 className="text-2xl font-bold text-emerald-700 mb-2 text-center">Create your SwiftFox account</h2>
        <p className="text-sm text-gray-600 text-center mb-6">
          No payment or credit card information required. Try it 100% free.
        </p>

        {error && <div className="mb-4 text-red-600 text-sm">{error}</div>}

        <div className="mb-4">
          <label className="block text-gray-700 mb-1">Full name</label>
          <input
            type="text"
            className="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            value={name}
            onChange={(e) => setName(e.target.value)}
            autoFocus
            required
          />
        </div>

        <div className="mb-4">
          <label className="block text-gray-700 mb-1">Company name</label>
          <input
            type="text"
            className="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            value={companyName}
            onChange={(e) => setCompanyName(e.target.value)}
            required
          />
        </div>

        <div className="mb-4">
          <label className="block text-gray-700 mb-1">Email</label>
          <input
            type="email"
            className="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </div>

        <div className="mb-4">
          <label className="block text-gray-700 mb-1">Password</label>
          <input
            type="password"
            className="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>

        <div className="mb-4">
          <label className="block text-gray-700 mb-1">Confirm password</label>
          <input
            type="password"
            className="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
            required
          />
        </div>

        <div className="mb-6">
          <label className="block text-gray-700 mb-1">Timezone</label>
          <select
            className="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600"
            value={timezone}
            onChange={(e) => setTimezone(e.target.value)}
          >
            {timezoneOptions.map((timezoneOption) => (
              <option key={timezoneOption} value={timezoneOption}>
                {timezoneOption}
              </option>
            ))}
          </select>
        </div>

        <button
          type="submit"
          className="w-full bg-emerald-600 text-white py-2 rounded font-semibold hover:bg-emerald-700 transition"
          disabled={loading}
        >
          {loading ? 'Creating account...' : 'Start 14-day free trial'}
        </button>

        <div className="mt-4 text-center text-sm text-gray-600">
          Already have an account?{' '}
          <Link to="/login" className="text-emerald-700 hover:underline">
            Sign in
          </Link>
        </div>
      </form>
    </div>
  );
};

export default Register;
