import api from './api';

export async function requestPasswordReset(email: string) {
  return api.post('/forgot-password', { email });
}

export async function resetPassword({ email, token, password, password_confirmation }: { email: string; token: string; password: string; password_confirmation: string; }) {
  return api.post('/reset-password', { email, token, password, password_confirmation });
}
