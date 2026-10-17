# Journey 18 - Forms, state and feedback

**Roles:** admin, seller, buyer
**Rail:** any
**Themes:** Reign, BuddyX, BuddyX Pro, Twenty Twenty-Five
**Status:** written 2026-10-18; walk pending

## Steps

### 1. Inactive commission rule stays inactive when edited (card:10407616413)
Pro > Settings > Commission, save a rule with Active unticked, open Edit.

**Expect** the list says Inactive, the Active box is unticked in Edit, and saving without touching it keeps the rule inactive.

### 2. Order status change says what happened (card:10399369371)
Admin > Orders > view an order, change the status and Update; then submit the same status; then a status the order cannot move to.

**Expect** a green "updated" notice, an info "nothing changed" notice, and a red notice explaining the refusal.

### 3. Wizard names the missing delivery time (card:10399020562)
Create a service with a Basic price and no delivery time, Continue.

**Expect** "Please set a delivery time for the Basic package", not a price message.

### 4. Decision note stays on the dispute (card:10398958589)
Admin > Disputes > open one, type a decision note, move it to Pending Review, Update.

**Expect** the note appears in the dispute thread as an Admin message, and the buyer and seller see it in theirs.

### 5. Empty requirement question is caught (card:10398584633)
Wizard > Requirements, add a row and leave the question blank, Continue.

**Expect** "Requirement 1 needs a question, or remove it."

### 6. Add-on rows keep unique ids (card:10398686846)
Services > Add New, add two add-ons, remove the first, add another.

**Expect** no two elements share an id; clicking a label focuses its own field.

### 7. Edit Request has one heading (card:10403821351)
Buyer dashboard > Requests > Edit.

**Expect** one "Edit Request" title and a Back button.

### 8. A portfolio image can be removed (card:10403187415)
Seller dashboard > Portfolio > Add item, pick two images, remove one with its X, Save; then edit the item and remove the last one.

**Expect** the X has an accessible name, the saved item keeps only the remaining image, and an item with no images saves.

### 9. Earnings commission button works (card:10384898745)
Admin > Vendors > a vendor > Earnings, set a rate, Save; then Reset.

**Expect** the status line shows the saved message (not "undefined") and the rate persists; Reset returns to the global rate.

### 10. Push settings save (card:10408271874)
Pro > Settings > Integrations > Push, Save.

**Expect** a success notice, the button re-enables, no console error.

### 11. Header avatar matches the photo the member set (card:10411862427)
Member sets a profile photo; open the site on Reign and BuddyX.

**Expect** the header avatar shows that photo, not the Gravatar.
