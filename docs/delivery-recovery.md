# Delivery failure and rescheduling

Drivers can report a pickup failure from accepted, or delivery failure from in_transit with a recorded pickup. A bounded category and 3–500 character reason are required. Only the latest accepted driver may act. The existing status transaction records history and the integration webhook outbox.

Merchants owning the delivery or administrators can set a future appointment with a reason for pickup_failed/delivery_failed. Web inputs are interpreted in Asia/Dubai; API inputs must include seconds and an explicit offset (`YYYY-MM-DDTHH:mm:ss+04:00`). Database timestamps are UTC. Rescheduling preserves price, commission snapshot, pickup timestamp and accepted assignment. Failed/rescheduled jobs retain driver capacity. It does not charge again or rematch a parcel still in custody.

The current driver refreshes the page when due and explicitly resumes: a parcel already picked up returns to in_transit; a pre-pickup attempt returns to accepted. Resume before the appointment or after an assignment change is rejected. Repeated failed-state writes add no duplicate history. This is a same-driver recovery pilot, not automatic reassignment, returns or automatic refunds. Address data is not rewritten by the reason field.

## API and web

Authenticated, account-active API routes (20 requests/minute):
- GET `/api/v1/deliveries/{id}/recovery` returns authorized status, last recovery reason, appointment and can_resume.
- POST `/api/v1/deliveries/{id}/recovery/fail` with failure_code and reason, driver only.
- POST `/api/v1/deliveries/{id}/recovery/reschedule` with scheduled_at and reason, merchant/admin only.
- POST `/api/v1/deliveries/{id}/recovery/resume`, current driver only.

Equivalent CSRF-protected POST forms are embedded in the existing driver assignment and merchant/admin delivery detail pages. The mobile panel uses the same endpoints with abortable reads and server-confirmed resume eligibility.

Database notifications go to the merchant, assigned driver and administrators. Notification bodies and phone push omit reasons and recipient details; authorized users open the order to read them. Existing push opt-in/session restrictions remain. Accepted-assignment recovery notices are not treated as expired pending offers. Existing signed store callbacks/polling convey statuses and monotonic sequences without forwarding private reasons. WooCommerce already recognizes these statuses; no plugin update is needed for this phase. WooCommerce native order/payment state remains unchanged.

## Acceptance

Use a test order and the same driver. Report customer_unreachable during transit, confirm the failed state/reason in merchant/admin views, set a near-future UAE appointment, verify early resume is blocked, refresh after due time, resume and complete the normal proof-of-delivery workflow. Repeat a pre-pickup store_not_ready case. Check existing store status updates and one delivery per external order. Test another driver/merchant cannot read or act. No migration or new native dependencies are needed.

Automated tests use SQLite and fake provider boundaries. Live phone, WordPress callbacks, network interruptions and visual desktop/mobile checks remain deployment acceptance tasks.
