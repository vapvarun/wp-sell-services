# Journey 09 — A buyer's files follow a request into the order

**Roles:** buyer (`subscriber`), second buyer, seller (`wpss_vendor`), admin
**Rail:** standalone payments
**Status:** passes (2026-10-07, after the 1.8.0 release was rejected)

Guards cards 10380173025, 10380395139, 10380601755.

The 1.8.0 build fataled on the order page whenever the buyer had attached a
file. Every earlier walk passed because no seeded or walked order had a file,
so only the empty branch of `templates/order/requirements.php` ever rendered.
**Attach a real file at every step that offers one.**

## Preconditions

- At least one image or PDF the buyer can upload (an allowed type under
  Settings > Advanced).
- A seller who has not yet proposed on the buyer's request.

## Steps

### 1. Buyer posts a request with a file
Dashboard > Buyer Requests > new request, attach one file.

**Expect** the request page lists the file, and it opens.

### 2. Seller proposes, buyer accepts and pays
Proposal, accept from the request, pay at checkout.

**Expect** an order with `platform = request`.

### 3. Seller opens the order both ways
From Messages (the order's conversation card) and from Sales Orders > Deliver.

**Expect**
- The page renders fully: sidebar, footer, nothing truncated. A fatal halfway
  through the requirements partial looks like a narrow, broken layout.
- "Files the buyer attached" lists the request's file, and its link downloads
  (200).
- The same at 390px.

### 4. Buyer submits requirements with a second file
Buyer's order page, then the requirements form, with a description and a new
file.

**Expect** on the seller, buyer and admin order views:
- BOTH files are listed, and both download.
- The request's description and the proposal cover are still shown. Before
  1.8.0, submitting replaced the brief and lost them.

### 5. REST
As the buyer: `GET /wpss/v1/orders/{id}/requirements`, then
`GET /wpss/v1/orders/{id}/files/{file_id}` for each file.

**Expect**
- `status: "submitted"`, with both files in `attachments`.
- Each file returns 200.

### 6. Someone else
As the second buyer: the same REST routes, and the download link from step 3.

**Expect**
- 403 on the order routes, and no file is served.
- `POST /orders/{id}/requirements` with another user's media id in
  `attachments` does not store that id on the order.

## Automated check

`wp eval-file tests/test-requirement-attachment-records.php` covers the data
shapes (stored media ids, records, ownership, merge on submit) without a
browser. It does not replace this walk: the walk is what proves the rendered
page.
