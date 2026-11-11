export async function request(url, options = {}) {
  const headers = new Headers(options.headers);
  const method = (options.method || 'GET').toUpperCase();
  const target = new URL(url, window.location.href);
  if (target.origin !== window.location.origin) throw new Error('Only same-origin requests are supported.');
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) throw new Error('A CSRF token is required for this action.');
    headers.set('X-CSRF-Token', token);
  }
  headers.set('Accept', 'application/json');
  const response = await fetch(target, { ...options, method, headers, credentials: 'same-origin' });
  if (!response.ok) throw new Error(`Request failed (${response.status}). Please try again.`);
  return response.json();
}
