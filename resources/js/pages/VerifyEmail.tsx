import React, { useEffect, useState } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import logo from '../assets/logo.png';
import api from '../services/api';

const VerifyEmail: React.FC = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [status, setStatus] = useState<'verifying' | 'success' | 'error'>('verifying');
  const [message, setMessage] = useState('');

  useEffect(() => {
    const verifyEmail = async () => {
      try {
        // Get all query parameters
        const userId = searchParams.get('user');
        const expires = searchParams.get('expires');
        const signature = searchParams.get('signature');

        if (!userId || !expires || !signature) {
          setStatus('error');
          setMessage('Invalid verification link.');
          return;
        }

        // Call backend verification endpoint with all parameters
        const response = await api.get(
          `/auth/verify-email/${userId}?expires=${expires}&signature=${signature}`
        );

        setStatus('success');
        setMessage(response.data.message || 'Email verified successfully!');

        // Redirect to login after 3 seconds
        setTimeout(() => {
          navigate('/login');
        }, 3000);
      } catch (err: any) {
        setStatus('error');
        setMessage(
          err.response?.data?.message ||
            err.response?.data?.error?.message ||
            'Verification failed. The link may have expired or is invalid.'
        );
      }
    };

    verifyEmail();
  }, [searchParams, navigate]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
      <div className="bg-white p-8 rounded shadow-md w-full max-w-md text-center">
        <div className="mb-6 flex justify-center">
          <img src={logo} alt="SwiftFox logo" className="h-10 w-auto" />
        </div>

        {status === 'verifying' && (
          <>
            <div className="mb-6">
              <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600"></div>
            </div>
            <h2 className="text-xl font-bold text-gray-800 mb-2">Verifying your email...</h2>
            <p className="text-sm text-gray-600">Please wait while we confirm your email address.</p>
          </>
        )}

        {status === 'success' && (
          <>
            <div className="mb-6">
              <svg
                className="mx-auto h-16 w-16 text-emerald-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <h2 className="text-xl font-bold text-emerald-700 mb-2">Email Verified!</h2>
            <p className="text-sm text-gray-600 mb-6">{message}</p>
            <p className="text-xs text-gray-500 mb-4">Redirecting to login in 3 seconds...</p>
            <Link
              to="/login"
              className="inline-block bg-emerald-600 text-white px-6 py-2 rounded font-semibold hover:bg-emerald-700 transition"
            >
              Go to Login
            </Link>
          </>
        )}

        {status === 'error' && (
          <>
            <div className="mb-6">
              <svg
                className="mx-auto h-16 w-16 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <h2 className="text-xl font-bold text-red-700 mb-2">Verification Failed</h2>
            <p className="text-sm text-gray-600 mb-6">{message}</p>
            <div className="space-y-2">
              <Link
                to="/register"
                className="block bg-emerald-600 text-white px-6 py-2 rounded font-semibold hover:bg-emerald-700 transition"
              >
                Sign Up Again
              </Link>
              <Link
                to="/login"
                className="block text-emerald-700 hover:underline text-sm"
              >
                Already verified? Sign in
              </Link>
            </div>
          </>
        )}
      </div>
    </div>
  );
};

export default VerifyEmail;
