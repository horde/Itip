# Upgrading horde/itip

## From Horde_Itip (lib/) to Horde\Itip (src/)

The modern PSR-4 layer (`Horde\Itip\`) is a complete redesign. The legacy PSR-0 layer (`Horde_Itip` in `lib/`) handled only REPLY processing with tightly coupled email transport. The new engine covers REQUEST, REPLY, and CANCEL with a pure decision architecture.

### Conceptual Shift

| Legacy (`Horde_Itip`) | Modern (`Horde\Itip`) |
|------------------------|------------------------|
| REPLY-only facade | Full iTIP state machine (REQUEST, REPLY, CANCEL) |
| Directly sends email | Returns decisions; transport is a listener concern |
| Coupled to `horde/mime` | Transport-agnostic; iMIP moves to `horde/imip` |
| Procedural flow | Pure engine: input → decisions → output |
| String-based status | Typed open enums (`ParticipationStatus`, `CalendarMethod`) |
| No conflict detection | Structured conflicts (OutdatedSequence, UnknownAttendee) |

### Legacy Architecture

```php
// Old: directly coupled to email transport
$response = Horde_Itip_Response_Factory::create($message, $options);
$response->send($transport);
```

### Modern Architecture

```php
// New: pure decisions, app controls transport
$processor = new ItipProcessor($calendarState, $policy);
$result = $processor->process($itipMessage);

// App applies changes
foreach ($result->changes as $change) { /* persist */ }

// App dispatches events — listeners handle transport
$dispatcher->dispatch(new InvitationReceived($message, $result));
```

### Migration Path

#### Step 1: Implement CalendarState

The engine needs to query existing calendar data. Implement `CalendarState` in your application:

```php
use Horde\Itip\CalendarState;

class MyCalendarState implements CalendarState
{
    public function findEventByUid(string $uid): ?Vevent { ... }
    public function getAttendeeStatus(string $uid, string $email): ?ParticipationStatus { ... }
    public function getEventSequence(string $uid): ?int { ... }
}
```

#### Step 2: Implement SchedulingPolicy (optional)

For custom accept/reject logic, auto-response rules, or notification suppression:

```php
use Horde\Itip\SchedulingPolicy;

class MyPolicy implements SchedulingPolicy
{
    public function shouldAcceptUpdate(ItipMessage $message, ?Vevent $existing): bool { ... }
    public function shouldAutoRespond(ItipMessage $message): ?ParticipationStatus { ... }
    public function shouldSendNotification(RequiredAction $action): bool { ... }
}
```

Or use `DefaultSchedulingPolicy` (accepts all, never auto-responds, allows all notifications).

#### Step 3: Replace Direct Email Sending

Legacy code that sends replies directly:

```php
// OLD
$response = new Horde_Itip_Response_Type_Accept($event, $resource);
$response->send($mailer, $identity);
```

Modern code generates the reply calendar and delegates transport:

```php
// NEW
$replyCal = $processor->generateReply($event, $email, ParticipationStatus::from('ACCEPTED'));
// Pass to iMIP listener or send manually
$dispatcher->dispatch(new ReplySending($message, $result));
```

#### Step 4: Register PSR-14 Listeners

When `horde/imip` is available, register its listener with your event dispatcher:

```php
$provider->addListener(ReplySending::class, $imipTransport);
$provider->addListener(InvitationSending::class, $imipTransport);
$provider->addListener(CancellationSending::class, $imipTransport);
```

Until then, handle transport in your application code after processing results.

### Coexistence

Both layers coexist in the package. The legacy `lib/` code continues to function for applications not yet migrated. New integrations should use the modern `src/` API exclusively.

### Dependency Changes

| Legacy | Modern |
|--------|--------|
| `horde/mime` (required) | `horde/mime` no longer needed by engine |
| `horde/translation` (required) | Not required by engine |
| — | `horde/icalendar` modern API (required) |
| — | `horde/eventdispatcher` (required) |
| — | `psr/event-dispatcher` (required) |

Transport dependencies (`horde/mime`, `horde/mail`) will move to the upcoming `horde/imip` package.

### Upcoming: horde/imip

The iMIP transport layer will be extracted to a dedicated package:

- Constructs RFC 6047 compliant MIME messages
- Registers as PSR-14 listener for `*Sending` events
- Handles incoming iMIP parsing from mailbox
- Depends on `horde/mime` and `horde/mail`

This keeps `horde/itip` focused on scheduling logic and testable without email infrastructure.
