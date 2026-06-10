# REST API Controllers Reference

WP Sell Services includes 21 dedicated REST API controllers. All endpoints use the base URL `/wp-json/wpss/v1/`.

For authentication, pagination, error handling, and generic endpoints, see [REST API Overview](rest-api-overview.md).

## 1. Services (/services)

Manage service listings, packages, and metadata.

**GET /services** - List services
**GET /services/{id}** - Get service details
**POST /services** - Create service (vendor only)
**PUT /services/{id}** - Update service (owner/admin)
**DELETE /services/{id}** - Delete service (owner/admin)

## 2. Orders (/orders)

Handle order lifecycle, status changes, and order management.

**GET /orders** - List orders (filtered by user role)
**GET /orders/{id}** - Get order details
**POST /orders/{id}/accept** - Accept order (vendor)
**POST /orders/{id}/start** - Start work (vendor)
**POST /orders/{id}/complete** - Complete order (buyer)
**POST /orders/{id}/cancel** - Cancel order

## 3. Reviews (/reviews)

Manage service and vendor reviews.

**GET /reviews** - List reviews
**GET /reviews/{id}** - Get review details
**POST /reviews** - Submit review (buyer, within review window)
**PUT /reviews/{id}** - Update review (owner)
**DELETE /reviews/{id}** - Delete review (owner/admin)

## 4. Vendors (/vendors)

Vendor profiles, statistics, and public information.

**GET /vendors** - List vendors
**GET /vendors/{id}** - Get vendor profile
**PUT /vendors/{id}** - Update profile (own profile)
**GET /vendors/{id}/services** - Get vendor's services
**GET /vendors/{id}/stats** - Get vendor statistics

## 5. Conversations (/conversations)

Order messaging and communication.

**GET /conversations/{order_id}** - Get messages for order
**POST /conversations/{order_id}/message** - Send message
**PUT /conversations/{message_id}/read** - Mark message as read

## 6. Disputes (/disputes)

Dispute management and resolution.

**GET /disputes** - List disputes
**GET /disputes/{id}** - Get dispute details
**POST /disputes** - Open dispute
**POST /disputes/{id}/message** - Add evidence
**POST /disputes/{id}/resolve** - Resolve dispute (admin)

## 7. Buyer Requests (/buyer-requests)

Job posting and proposal system.

**GET /buyer-requests** - List requests
**GET /buyer-requests/{id}** - Get request details
**POST /buyer-requests** - Post request (buyer)
**PUT /buyer-requests/{id}** - Update request
**DELETE /buyer-requests/{id}** - Delete request

## 8. Proposals (/proposals)

Vendor proposals for buyer requests.

**GET /proposals** - List proposals (filtered by user)
**GET /proposals/{id}** - Get proposal details
**POST /proposals** - Submit proposal (vendor)
**PUT /proposals/{id}** - Update proposal
**POST /proposals/{id}/accept** - Accept proposal (buyer)
**DELETE /proposals/{id}** - Withdraw proposal (vendor)

## 9. Notifications (/notifications)

In-app notifications.

**GET /notifications** - List notifications
**GET /notifications/unread** - Get unread count
**PUT /notifications/{id}/read** - Mark as read
**POST /notifications/read-all** - Mark all as read
**DELETE /notifications/{id}** - Delete notification

## 10. Portfolio (/portfolio)

Vendor portfolio items.

**GET /portfolio** - List portfolio items
**GET /portfolio/{id}** - Get portfolio item
**POST /portfolio** - Add portfolio item (vendor)
**PUT /portfolio/{id}** - Update portfolio item
**DELETE /portfolio/{id}** - Delete portfolio item

## 11. Earnings (/earnings)

Vendor earnings and withdrawals.

**GET /earnings** - Get earnings summary (vendor)
**GET /earnings/history** - Earnings history
**GET /earnings/withdrawals** - Withdrawal history
**POST /earnings/withdraw** - Request withdrawal

## 12. Extension Requests (/extension-requests)

Order deadline extensions.

**GET /extension-requests** - List extension requests
**POST /extension-requests** - Request extension (vendor)
**POST /extension-requests/{id}/approve** - Approve (buyer)
**POST /extension-requests/{id}/reject** - Reject (buyer)

## 13. Milestones (/milestones)

Milestone-based payments for complex projects.

**GET /milestones/{order_id}** - Get order milestones
**POST /milestones** - Create milestone
**POST /milestones/{id}/submit** - Submit milestone (vendor)
**POST /milestones/{id}/approve** - Approve milestone (buyer)
**POST /milestones/{id}/reject** - Reject milestone (buyer)

## 14. Tipping (/tips)

Optional tipping system.

**POST /tips** - Send tip to vendor
**GET /tips/sent** - Tips sent (buyer)
**GET /tips/received** - Tips received (vendor)

## 15. Seller Levels (/seller-levels)

Vendor tier system.

**GET /seller-levels** - List level definitions
**GET /seller-levels/{id}** - Get level details
**GET /seller-levels/progress** - Get vendor progress (own profile)

## 16. Moderation (/moderation)

Content moderation tools (admin).

**GET /moderation/services** - Services pending approval
**POST /moderation/services/{id}/approve** - Approve service
**POST /moderation/services/{id}/reject** - Reject service
**GET /moderation/reviews** - Reviews pending moderation
**POST /moderation/reviews/{id}/approve** - Approve review

## 17. Favorites (/favorites)

Buyer favorites/wishlist.

**GET /favorites** - List favorites (buyer)
**POST /favorites** - Add to favorites
**DELETE /favorites/{service_id}** - Remove from favorites

## 18. Media (/media)

File upload and management.

**POST /media/upload** - Upload file
**GET /media/{id}** - Get file info
**DELETE /media/{id}** - Delete file

## 19. Cart (/cart)

Shopping cart management.

**GET /cart** - Get cart contents
**POST /cart/add** - Add service to cart
**PUT /cart/update** - Update cart item
**DELETE /cart/remove** - Remove from cart
**POST /cart/clear** - Clear cart

## 20. Auth (/auth)

Authentication and session management **[PRO]**.

**POST /auth/login** - Login user
**POST /auth/register** - Register new user
**POST /auth/logout** - Logout
**GET /auth/validate** - Validate token
**POST /auth/refresh** - Refresh token

## 21. Realtime (/realtime)

Private-channel authorization for the realtime (WebSocket) layer. The plugin speaks the Pusher protocol, so this works with Pusher.com or any self-hosted Pusher-compatible server (e.g. Soketi). Client connection settings (key, host, cluster, port, TLS) are exposed under the `realtime` key of `GET /settings`; the app secret never leaves the server.

### POST /realtime/auth

Authorize a private-channel subscription per the Pusher auth contract. Called automatically by the bundled `wpss-realtime.js` client (with the `X-WP-Nonce` header); external clients can call it with any supported authentication method.

**Authentication Required**: Yes (logged-in user)

**Parameters**:
- `socket_id` (string, required) - Pusher socket ID of the connecting client (format `123.456`)
- `channel_name` (string, required) - Private channel to subscribe to

**Allowed channels**:
- `private-wpss-user-{ID}` - Only the user themself
- `private-wpss-order-{ID}` - The order's customer, its vendor, or administrators

**Response** (200):
```json
{
  "auth": "app_key:hmac_sha256_signature"
}
```

**Errors**:
- `401 rest_not_logged_in` - Not authenticated
- `403 wpss_realtime_forbidden` - Channel not owned by the current user (any channel outside the two shapes above is also refused)
- `404 wpss_realtime_disabled` - Realtime is not enabled/configured on this site

**Example**:
```bash
curl -X POST \
  https://yoursite.com/wp-json/wpss/v1/realtime/auth \
  -u "username:xxxx xxxx xxxx xxxx" \
  -d "socket_id=1234.5678" \
  -d "channel_name=private-wpss-user-45"
```

**Events published by the plugin**:
- `notification.created` on `private-wpss-user-{ID}` - payload `{ id, type }`
- `message.created` on `private-wpss-order-{ID}` and the recipient's `private-wpss-user-{ID}` - payload `{ order_id, sender_id, message_id, excerpt }`

## Related Documentation

- [REST API Overview](rest-api-overview.md) - Authentication, error handling, pagination, CORS
- [Hooks and Filters](hooks-filters.md) - Available action and filter hooks
- [Custom Integrations](custom-integrations.md) - Building custom controllers
- [Theme Integration](theme-integration.md) - Frontend integration
