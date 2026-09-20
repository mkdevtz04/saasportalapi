// Load test for the customer portal, written for k6 (https://k6.io).
//
//   k6 run -e BASE=https://acme.staging.example -e TENANT=acme deploy/loadtest/portal.k6.js
//
// Point it at STAGING, never at production. It imitates customers opening the portal and polling
// for a payment, and it never pays for anything. It has not been run yet: run it before launch and
// watch /health, the database and the queue while it does.
//
// What "good" looks like: portal page under 500 ms and status polls under 300 ms at the load you
// expect on a busy evening, with no errors. A hotspot with a few hundred customers is a few
// hundred requests a minute, far below what this generates.

import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE || 'http://localhost:8000';
const TENANT = __ENV.TENANT || 'acme';

export const options = {
  stages: [
    { duration: '1m', target: 50 },    // ramp up
    { duration: '3m', target: 200 },   // busy evening
    { duration: '1m', target: 0 },     // ramp down
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{kind:portal}': ['p(95)<500'],
    'http_req_duration{kind:status}': ['p(95)<300'],
  },
};

export default function () {
  // A customer opens the portal from the router login page.
  const portal = http.get(`${BASE}/portal/${TENANT}?lang=sw`, { tags: { kind: 'portal' } });
  check(portal, { 'portal loads': (r) => r.status === 200 });

  sleep(2);

  // A customer who has just paid polls every three seconds. An unknown id is answered cheaply
  // ("not_found"), which is the realistic worst case for the database lookup.
  for (let i = 0; i < 10; i++) {
    const status = http.get(`${BASE}/api/payment/status?tenant=${TENANT}&transaction_id=01LOADTEST0000000000000000`, {
      tags: { kind: 'status' },
    });
    check(status, { 'status answers': (r) => r.status === 200 });
    sleep(3);
  }
}
