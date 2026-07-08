import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * K6 load test for Cwt Academy public API.
 * Run: k6 run --vus 100 --duration 60s tests/load/k6-public-api.js
 *
 * Targets:
 * - 500 concurrent users: p95 < 500ms
 * - 1500 concurrent users: p95 < 1500ms (with queue offloading)
 */

export const options = {
  stages: [
    { duration: '30s', target: 100 },
    { duration: '1m', target: 500 },
    { duration: '2m', target: 1500 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration: ['p(95)<1500'],
    http_req_failed: ['rate<0.01'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

export default function () {
  // 1. Homepage (cached)
  const home = http.get(`${BASE_URL}/`);
  check(home, {
    'homepage status is 200': (r) => r.status === 200,
    'homepage p95 < 500ms': (r) => r.timings.duration < 500,
  });

  sleep(1);

  // 2. Course listing (cached)
  const courses = http.get(`${BASE_URL}/api/v1/courses`);
  check(courses, {
    'courses status is 200': (r) => r.status === 200,
    'courses p95 < 800ms': (r) => r.timings.duration < 800,
  });

  sleep(2);

  // 3. Tracking endpoint (rate limited)
  const track = http.get(`${BASE_URL}/api/v1/course-requests/ABCD1234EFGH5678/tracking`);
  check(track, {
    'tracking returns 200 or 404': (r) => r.status === 200 || r.status === 404,
  });

  sleep(1);
}
