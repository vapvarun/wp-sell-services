# Action Scheduler Integration

Since version 1.1.0 every recurring job in WP Sell Services runs on [Action Scheduler](https://actionscheduler.org/) instead of WP-Cron. This gives operators a real queue with durable retry, admin-visible run history (Tools > Scheduled Actions), and proper task isolation — no more dispute cron blocking page loads on a cron-starved site.

## What Runs On Action Scheduler

All free-plugin jobs use the `wpss` group; Pro uses `wpss-pro`. The group convention lets the deactivator sweep everything in a single call.

| Hook | Interval | Purpose |
|------|----------|---------|
| `wpss_check_late_orders` | 1 hour | Flags orders past their delivery deadline |
| `wpss_process_cancellation_timeouts` | 1 hour | Auto-cancels orders stuck in cancellation grace |
| `wpss_process_offline_auto_cancel` | 1 hour | Cancels offline-gateway orders that never paid |
| `wpss_auto_complete_orders` | 12 hours | Auto-completes delivered orders past the review window |
| `wpss_update_vendor_stats` | 12 hours | Refreshes cached vendor metrics |
| `wpss_send_deadline_reminders` | Daily | Emails vendors about upcoming delivery deadlines |
| `wpss_send_requirements_reminders` | Daily | Nudges buyers to submit order requirements |
| `wpss_check_requirements_timeout` | Daily | Handles orders where the buyer never submitted requirements |
| `wpss_cleanup_expired_requests` | Daily | Removes expired buyer requests |
| `wpss_cron_daily` | Daily | Dispute workflow deadline check |
| `wpss_audit_log_cleanup` | Daily | Prunes the audit log per retention setting |
| `wpss_cleanup_abandoned_tips` | Daily | Clears unpaid tip sub-orders past the abandon window |
| `wpss_cleanup_abandoned_extensions` | Daily | Same contract for extension sub-orders |
| `wpss_cleanup_abandoned_milestones` | Daily | Same contract for non-contract milestones |
| `wpss_recalculate_seller_levels` | Weekly | Seller level progression recalculation |
| `wpss_process_auto_withdrawals` | Admin-selected (weekly / biweekly / monthly) | Auto-payout run if enabled in Settings |

## The Scheduler Facade

Every scheduling call in the plugin routes through `WPSellServices\Services\Scheduler`. You should use it too — it's easier to stub in tests, enforces the `wpss` group by default, and handles the "data store not ready" race that Action Scheduler has on early `plugins_loaded`.

```php
use WPSellServices\Services\Scheduler;

// Schedule a recurring job. Idempotent — if an action with this hook
// is already pending, the call is a no-op.
Scheduler::schedule_recurring( 'my_hook', HOUR_IN_SECONDS );

// Schedule a one-off at a specific timestamp. Also idempotent on
// (hook + args + group).
Scheduler::schedule_single( 'my_hook', time() + 300, array( 'order_id' => 42 ) );

// Cancel everything matching a hook, any args.
Scheduler::unschedule_all( 'my_hook' );

// Cancel every action in the `wpss` group (used by the deactivator).
Scheduler::unschedule_all_for_group( Scheduler::GROUP_FREE );

// Check whether something is pending.
if ( Scheduler::has_pending( 'my_hook' ) ) {
    // ...
}
```

### Calling Before AS Is Ready

Action Scheduler's data store comes up on the `action_scheduler_init` action (fired during `init`). Calling `as_schedule_*()` before that logs a warning ("was called before the Action Scheduler data store was initialized").

Scheduler handles this with `is_ready()` and `on_ready()`:

```php
// Defer the call until AS is up. Safe to use from plugins_loaded
// or any earlier hook — if AS is already ready, it runs immediately.
Scheduler::on_ready( function () {
    Scheduler::schedule_recurring( 'my_hook', HOUR_IN_SECONDS );
} );
```

All of Scheduler's public methods already auto-defer via `on_ready` internally — `on_ready()` itself is only needed when you want to wrap a larger block of work.

## Upgrade Path From Pre-1.1.0

Sites upgrading from 1.1.0 will have WP-Cron entries for the legacy hook names. On first load after the upgrade, `Plugin::clear_legacy_wpcron_hooks()` runs once and scrubs the WP-Cron entries for:

```
wpss_check_late_orders
wpss_auto_complete_orders
wpss_send_deadline_reminders
wpss_send_requirements_reminders
wpss_check_requirements_timeout
wpss_recalculate_seller_levels
wpss_process_cancellation_timeouts
wpss_process_offline_auto_cancel
wpss_cleanup_expired_requests
wpss_update_vendor_stats
wpss_process_auto_withdrawals
wpss_cron_daily
wpss_audit_log_cleanup
```

Then `Activator::schedule_cron_events()` re-runs, registering everything against Action Scheduler. The sweep is keyed on version change so it fires exactly once per site.

## Adding A New Recurring Job

1. Author the handler in your service class:

   ```php
   add_action( 'my_addon_nightly_cleanup', array( $this, 'nightly_cleanup' ) );
   ```

2. Schedule it once — either on activation, or from a `Scheduler::on_ready()` call during bootstrap:

   ```php
   use WPSellServices\Services\Scheduler;

   Scheduler::on_ready( function () {
       Scheduler::schedule_recurring( 'my_addon_nightly_cleanup', DAY_IN_SECONDS );
   } );
   ```

3. If you're writing a Pro extension, pass `Scheduler::GROUP_PRO` as the group so it gets swept with the rest of Pro on deactivation.

## Monitoring

Scheduled Actions admin screen lives at **Tools > Scheduled Actions**. Filter by the `wpss` group to see every free-plugin job, its next run, last run, status, and log. Failed actions show their exception message inline — useful when debugging a flaky third-party API.

## Why File-Load Require

`wp-sell-services.php` requires `action-scheduler.php` at file-load time (not from a `plugins_loaded` hook). Action Scheduler's own internals bootstrap on `plugins_loaded:1` — registering the `every_minute` interval used by its queue runner. If the require runs later, AS's queue runner can't re-schedule itself and WordPress logs:

```
Cron reschedule event error for hook: action_scheduler_run_queue,
Error code: invalid_schedule
```

The file-load require is the recommended pattern documented in the Action Scheduler wiki and is safe — plugin headers run before any hook fires.

## Testing

The plugin ships PHPStan stubs for Action Scheduler's function family in `tests/stubs/action-scheduler-stubs.php`. That way static analysis passes without needing AS itself in the composer dev-requires tree; at runtime the real library is already loaded.

```neon
# phpstan.neon
parameters:
    scanFiles:
        - tests/stubs/action-scheduler-stubs.php
```

In PHPUnit tests, you can safely call `Scheduler::has_pending()` etc. — if AS isn't loaded in the test bootstrap, the facade falls through to `false`/`0` rather than a fatal.
