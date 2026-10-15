import { clientIp, isProxied } from './api-proxy';

describe('api-proxy', () => {
  it('relays the API and the listing photos, nothing else', () => {
    expect(isProxied('/api')).toBe(true);
    expect(isProxied('/api/accommodations?page=1')).toBe(true);
    expect(isProxied('/api?x=1')).toBe(true);
    expect(isProxied('/media/photos/abc.webp')).toBe(true);

    expect(isProxied('/apis')).toBe(false);
    expect(isProxied('/media/figtree.woff2')).toBe(false);
    expect(isProxied('/recherche')).toBe(false);
    expect(isProxied('/en/api')).toBe(false);
  });

  it('keeps only one well-formed address from X-Real-IP', () => {
    expect(clientIp('203.0.113.7')).toBe('203.0.113.7');
    expect(clientIp(' 2001:db8::1 ')).toBe('2001:db8::1');
    expect(clientIp(['198.51.100.2', '10.0.0.1'])).toBe('198.51.100.2');

    expect(clientIp('10.0.0.1, 203.0.113.7')).toBeNull();
    expect(clientIp('pas une ip')).toBeNull();
    expect(clientIp(undefined)).toBeNull();
  });
});
