import axios from 'axios';
import { Preferences } from '@capacitor/preferences';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'https://dienstplan.hemingway-hilden.de/api/app/v1',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use(async (config) => {
  const { value } = await Preferences.get({ key: 'auth_token' });
  if (value) config.headers.Authorization = `Bearer ${value}`;
  return config;
});

export async function login(email, password) {
  const { data } = await api.post('/login', { email, password, device_name: 'Hemingway Team App' });
  await Preferences.set({ key: 'auth_token', value: data.token });
  return data;
}

export async function logout() {
  try { await api.post('/logout'); } finally { await Preferences.remove({ key: 'auth_token' }); }
}

export async function hasToken() {
  return Boolean((await Preferences.get({ key: 'auth_token' })).value);
}

export default api;
