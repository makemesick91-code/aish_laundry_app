# Counter and POS Journeys

Step 2 — Design System and UX Foundation. Cluster file for **JRN-004**, **JRN-005**, **JRN-006**,
**JRN-007**.

Index and full specification tables: [`../CRITICAL_JOURNEYS.md`](../CRITICAL_JOURNEYS.md).
Screen definitions: [`../SCREEN_INVENTORY.md`](../SCREEN_INVENTORY.md).

## Purpose

To describe what happens at the counter of Outlet Cempaka: intake of kiloan and mixed orders, recording
the condition of items before they are accepted into custody, and taking payment. The counter is busy and
the queue is physical, so the common path must be the fastest path — and every money figure must be
correct the first time.

All example data is fictional: cashier "Siti Rahmawati", customer "Budi Santoso", order
`AL-2026-000123`, outlet "Outlet Cempaka", tenant "Laundry Bersih Sejahtera".

## Status block

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| JRN-004, JRN-005, JRN-006, JRN-007 | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |
| Accessibility | **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED** |

Documentation is not implementation. `GO` is owner-conferred.

## JRN-004 — Cashier creates kiloan order

Siti Rahmawati receives a bag of laundry, weighs it at `1,5 kg`, and creates a weight-based order. The
total of `Rp79.000` is computed on the server in integer Rupiah; the figure shown on the device is a
display of the server's answer, never a substitute for it. At creation the order captures a price
snapshot, so a later change to the master price list can never retroactively alter this order, its
invoice, or a reprint. A `client_reference` is generated once at this point and travels with the
operation for its whole life. Where the connection is unavailable the order is queued and the receipt is
marked as pending confirmation rather than presented as final. The cashier sees the POS entry because it
is useful, not because visibility grants permission — the backend verifies membership and permission on
the create call from Step 3 onward.

```mermaid
flowchart TD
    A["Customer arrives at counter"] --> B["SCR-OPS-007 new order"]
    B --> C["Find or create customer profile"]
    C --> D["Select kiloan service"]
    D --> E["SCR-OPS-011 enter weight 1,5 kg"]
    E --> F{"Weight meets service minimum"}
    F -->|"No"| G["Block with recovery instruction"]
    G --> E
    F -->|"Yes"| H["Server computes total Rp79.000"]
    H --> I["Generate client_reference once"]
    I --> J{"Device online"}
    J -->|"No"| K["Queue order, UXS-005 in SCR-OPS-020"]
    K --> L["Receipt marked pending confirmation"]
    J -->|"Yes"| M["Order created at RECEIVED with price snapshot"]
    M --> N["SCR-OPS-018 print receipt"]
    L --> O["Sync on reconnect, same client_reference"]
    O --> M
```

## JRN-005 — Cashier creates mixed order

Budi Santoso brings both kiloan laundry and two satuan items, so one order carries lines of different
kinds. Each line captures its own price snapshot at creation; the order total is the server's sum of
those lines and is never assembled on the device. If the cashier applies a discount it requires a
permission and a recorded reason, and it appears as its own line rather than being folded invisibly into
a unit price — a discount hidden inside a price is a reconciliation problem three weeks later. An
inactive catalog item blocks only its own line and names which line needs attention, leaving the rest of
the intake intact. Offline, the whole multi-line order is queued as a single unit under one
`client_reference`; splitting lines across queue entries would let a partial order reach the server.
After confirmation, corrections follow the reversal and adjustment path rather than a silent edit.

```mermaid
flowchart TD
    A["Mixed intake at counter"] --> B["SCR-OPS-007 new order"]
    B --> C["Add kiloan line, weight 1,5 kg"]
    C --> D["Add two satuan lines with quantity"]
    D --> E{"All catalog items active"}
    E -->|"No"| F["Block only affected line, name it"]
    F --> D
    E -->|"Yes"| G["Each line captures its own price snapshot"]
    G --> H{"Discount applied"}
    H -->|"Yes"| I["Permission and reason required, discount as own line"]
    H -->|"No"| J["Server computes order total"]
    I --> J
    J --> K{"Device online"}
    K -->|"No"| L["Queue whole order as one unit, one client_reference"]
    K -->|"Yes"| M["Order created at RECEIVED"]
    L --> M
    M --> N["SCR-OPS-018 single receipt covering all lines"]
```

## JRN-006 — Cashier records condition and photo

Siti Rahmawati notices a stain and a loose button and records the condition before the item enters
custody. She selects a reason code, captures photographs, and adds a short note; the customer
acknowledges the record at the counter. Laundry photographs are RESTRICTED data — they can show the
inside of a customer's home or personal garments — so they are stored in private object storage, served
only through signed expiring URLs, tenant-scoped, and never shown on the public tracking portal. If the
customer declines photography, the cashier records the condition with a reason code and no photograph,
and the absence is recorded explicitly rather than left to inference. A denied camera permission renders
the permission-denied state and the intake still proceeds with a text-only condition note. Offline,
photographs are stored encrypted on device and queued under the order's `client_reference`; the queue
survives an app kill and a failed upload stays visible rather than being dropped.

```mermaid
flowchart TD
    A["Cashier notices stain and loose button"] --> B["SCR-OPS-013 condition evidence"]
    B --> C["Select condition reason code"]
    C --> D{"Camera permission granted"}
    D -->|"No"| E["UXS-010 permission denied panel"]
    E --> F["Record text-only condition note"]
    D -->|"Yes"| G{"Customer consents to photograph"}
    G -->|"No"| F
    G -->|"Yes"| H["Capture photographs, encrypt on device"]
    H --> I["Queue under order client_reference"]
    I --> J{"Upload succeeds"}
    J -->|"No"| K["UXS-008 failed sync, stays visible and actionable"]
    K --> L["Bounded backoff retry"]
    L --> J
    J -->|"Yes"| M["Stored privately, signed URL access only"]
    F --> N["Customer acknowledges recorded condition"]
    M --> N
```

## JRN-007 — Cashier takes partial payment

Budi Santoso pays `Rp40.000` of an `Rp79.000` order and will settle `Rp39.000` on collection. The payment
carries a `client_reference` generated once, and the server records it idempotently: a retry with that
same reference returns the original payment rather than creating a second one. An amount larger than the
outstanding balance is rejected before submission with a plain explanation. If the printer fails after
the payment is recorded, the printer-state screen appears — but the payment already exists and is never
re-submitted merely to obtain a printout, which is exactly how duplicate payments are born. Offline the
payment queues as pending sync, and the financial queue is never cleared by a cache clear, a logout, or a
version upgrade. An order is never marked paid on a client claim; corrections are made by reversal or
adjustment entries recording actor, timestamp, amount, and reason.

```mermaid
flowchart TD
    A["Customer pays part of Rp79.000"] --> B["SCR-OPS-016 payment"]
    B --> C["SCR-OPS-017 partial payment, enter Rp40.000"]
    C --> D{"Amount within outstanding balance"}
    D -->|"No"| E["Reject before submission with plain reason"]
    E --> C
    D -->|"Yes"| F["Generate client_reference once"]
    F --> G{"Device online"}
    G -->|"No"| H["Queue payment, UXS-005, financial queue protected"]
    H --> I["Reconnect and replay same client_reference"]
    G -->|"Yes"| J["Server records payment idempotently"]
    I --> J
    J --> K["Outstanding recomputed to Rp39.000"]
    K --> L{"Printer available"}
    L -->|"No"| M["SCR-OPS-019 printer state, payment already recorded"]
    L -->|"Yes"| N["SCR-OPS-018 receipt shows paid and outstanding"]
    M --> O["Reprint later, never re-submit payment"]
```
