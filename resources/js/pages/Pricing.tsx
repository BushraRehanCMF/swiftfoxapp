import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';

interface Plan {
  id: string;
  name: string;
  priceId: string;
  price: string;
  description: string;
  features: string[];
  conversationLimit: number;
  popular?: boolean;
}

const plans: Plan[] = [
  {
    id: 'starter',
    name: 'Starter',
    priceId: 'price_1T5vTQCvCAvaipQzhXgCLKR2',
    price: '$25',
    description: 'Perfect for small businesses',
    conversationLimit: 500,
    features: [
      '500 conversations/month',
      'WhatsApp Business integration',
      'Shared inbox',
      'Labels & organization',
      'Basic automations',
      'Business hours',
      'Email support',
    ],
  },
  {
    id: 'pro',
    name: 'Pro',
    priceId: 'price_1T5vTqCvCAvaipQzECijUTXY',
    price: '$49',
    description: 'For growing teams',
    conversationLimit: 2000,
    popular: true,
    features: [
      '2,000 conversations/month',
      'Everything in Starter',
      'Advanced automations',
      'Team collaboration',
      'Webhooks',
      'Priority support',
      'Custom labels',
    ],
  },
];

const Pricing: React.FC = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState<string | null>(null);
  const [error, setError] = useState('');

  const handleUpgrade = async (plan: Plan) => {
    setLoading(plan.id);
    setError('');

    try {
      const response = await api.post('/checkout/session', {
        price_id: plan.priceId,
        success_url: `${window.location.origin}/usage?success=true`,
        cancel_url: `${window.location.origin}/pricing`,
      });

      const checkoutUrl = response.data.data.checkout_url;
      // Redirect to Stripe Checkout
      window.location.href = checkoutUrl;
    } catch (err: any) {
      console.error('Checkout error:', err);
      setError(err.response?.data?.error?.message || 'Failed to create checkout session. Please try again.');
      setLoading(null);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-6 py-12">
      <div className="text-center mb-12">
        <h1 className="text-4xl font-bold text-gray-900 mb-4">Choose Your Plan</h1>
        <p className="text-lg text-gray-600">
          Upgrade to unlock unlimited WhatsApp messaging and advanced features
        </p>
      </div>

      {error && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
          {error}
        </div>
      )}

      <div className="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
        {plans.map((plan) => (
          <div
            key={plan.id}
            className={`relative rounded-2xl border-2 p-8 shadow-lg transition-all hover:shadow-xl ${
              plan.popular
                ? 'border-emerald-600 bg-emerald-50'
                : 'border-gray-200 bg-white'
            }`}
          >
            {plan.popular && (
              <div className="absolute -top-4 left-1/2 transform -translate-x-1/2">
                <span className="bg-emerald-600 text-white px-4 py-1 rounded-full text-sm font-semibold">
                  Most Popular
                </span>
              </div>
            )}

            <div className="text-center mb-6">
              <h2 className="text-2xl font-bold text-gray-900 mb-2">{plan.name}</h2>
              <p className="text-gray-600 text-sm mb-4">{plan.description}</p>
              <div className="mb-4">
                <span className="text-5xl font-bold text-gray-900">{plan.price}</span>
                <span className="text-gray-600 ml-2">/month</span>
              </div>
              <button
                onClick={() => handleUpgrade(plan)}
                disabled={loading === plan.id}
                className={`w-full py-3 px-6 rounded-lg font-semibold transition-colors ${
                  plan.popular
                    ? 'bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-emerald-400'
                    : 'bg-gray-800 text-white hover:bg-gray-900 disabled:bg-gray-400'
                }`}
              >
                {loading === plan.id ? 'Processing...' : 'Upgrade Now'}
              </button>
            </div>

            <div className="space-y-3">
              {plan.features.map((feature, idx) => (
                <div key={idx} className="flex items-start gap-3">
                  <svg
                    className="w-5 h-5 text-emerald-600 mt-0.5 flex-shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                  <span className="text-gray-700 text-sm">{feature}</span>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>

      <div className="mt-12 text-center">
        <button
          onClick={() => navigate('/usage')}
          className="text-gray-600 hover:text-gray-900 text-sm underline"
        >
          ← Back to Usage
        </button>
      </div>

      <div className="mt-12 text-center text-sm text-gray-500">
        <p>All plans include a secure payment via Stripe</p>
        <p className="mt-2">Need a custom plan? Contact us at admin@swiftfox.cloud</p>
      </div>
    </div>
  );
};

export default Pricing;
