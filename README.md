# Horde iTIP

`horde/itip` is a pure decision engine for iTIP (RFC 5546) scheduling. It processes incoming iCalendar messages with METHOD (REQUEST, REPLY, CANCEL) and returns structured results describing what should change, without performing any I/O.

## Installation

```bash
composer require horde/itip
```

Requires PHP 8.1+.

## Architecture

### Design Principles

- **Pure logic, no side effects** — the engine returns decisions, not mutations
- **App applies results** — the calling application decides what to persist and when to dispatch events
- **Transport-agnostic** — email (iMIP), CalDAV, ActiveSync are listener concerns, not engine concerns
- **PSR-14 ready** — domain events are dispatched by the app layer via `horde/eventdispatcher`

### Layers

| Layer | Namespace | Purpose |
|-------|-----------|---------|
| Engine | `Horde\Itip` | ItipProcessor, ItipMessage, ItipResult |
| Interfaces | `Horde\Itip` | CalendarState, SchedulingPolicy |
| Changes | `Horde\Itip\Change` | Proposed calendar mutations (CreateEvent, UpdateEvent, CancelEvent, etc.) |
| Actions | `Horde\Itip\Action` | Required outbound actions (SendReply, SendRequest, SendCancel) |
| Conflicts | `Horde\Itip\Conflict` | Problems detected (OutdatedSequence, UnknownAttendee, MissingRequiredProperty) |
| Events | `Horde\Itip\Event` | PSR-14 domain events for listener dispatch |
| Generators | `Horde\Itip\Generator` | Build outgoing METHOD=REQUEST/REPLY/CANCEL VCalendar messages |

### Processing Flow

```
Incoming VCalendar with METHOD
        │
        ▼
  ItipMessage::fromCalendar()
        │
        ▼
  ItipProcessor::process()
        │  - validates required properties
        │  - checks SEQUENCE ordering
        │  - consults CalendarState for existing data
        │  - consults SchedulingPolicy for decisions
        │
        ▼
  ItipResult
        │  - changes[]    (what should be persisted)
        │  - actions[]    (what should be sent)
        │  - conflicts[]  (why processing failed)
        │
        ▼
  Application applies changes, then dispatches PSR-14 events
        │
        ▼
  Listeners handle transport (iMIP, CalDAV, logging)
```

### Boundaries

This library is responsible for:

- Deciding what calendar mutations an iTIP message implies
- Validating SEQUENCE ordering and required properties
- Consulting application-provided policy for accept/reject decisions
- Generating outbound iTIP VCalendar messages (REQUEST, REPLY, CANCEL)
- Defining PSR-14 event types for scheduling notifications

This library is **not** responsible for:

- Parsing iCalendar byte streams. See `horde/icalendar`
- Sending email (iMIP/MIME construction), relegated to the upcoming `horde/imip` package
- Persisting data. This is handled by application layers (Kronolith, Nag)
- CalDAV schedule-outbox/inbox. See `horde/dav`
- Event dispatch itself. The app layer calls `$dispatcher->dispatch()`

### Collaboration Partners

| Package | Relationship |
|---------|-------------|
| `horde/icalendar` | Provides VCalendar, Vevent, Attendee, Organizer, and open enums consumed by the engine |
| `horde/eventdispatcher` | PSR-14 dispatcher used by apps to notify listeners after applying results |
| `horde/imip` (planned) | Will listen for PSR-14 events and handle MIME email construction/delivery |
| `horde/dav` | CalDAV layer that may feed iTIP messages into the engine and act on results |
| Kronolith / Nag | Application layers that implement CalendarState and SchedulingPolicy |

### Upcoming: horde/imip

A separate `horde/imip` package will handle the iMIP (RFC 6047) transport layer:

- Listens for `InvitationSending`, `ReplySending`, `CancellationSending` PSR-14 events
- Constructs MIME messages with `text/calendar` parts
- Delivers via configured mail transport
- Parses incoming iMIP messages from mailbox into `ItipMessage` for processing

This separation keeps `horde/itip` transport-free and testable without email infrastructure.

## Upgrading

See [doc/UPGRADING.md](doc/UPGRADING.md) for migration guidance from `Horde_Itip` (PSR-0) to the modern `Horde\Itip` (PSR-4) engine.

## Usage

### Processing an Incoming Request

```php
use Horde\Itip\ItipMessage;
use Horde\Itip\ItipProcessor;
use Horde\Itip\NullCalendarState;
use Horde\Itip\DefaultSchedulingPolicy;
use Horde\Itip\Change\CreateEvent;
use Horde\Itip\Change\UpdateEvent;

$processor = new ItipProcessor(
    new MyCalendarState($calendarBackend),  // implements CalendarState
    new DefaultSchedulingPolicy(),
);

$message = ItipMessage::fromCalendar($vcalendar, 'me@example.com');
$result = $processor->process($message);

if ($result->hasConflicts()) {
    // Handle conflicts (outdated sequence, missing properties, etc.)
    foreach ($result->conflicts as $conflict) { ... }
    return;
}

// Apply proposed changes
foreach ($result->changes as $change) {
    match (true) {
        $change instanceof CreateEvent => $backend->create($change->event),
        $change instanceof UpdateEvent => $backend->update($change->uid, $change->updatedEvent),
        // ...
    };
}

// Dispatch PSR-14 event for listeners (iMIP, logging, etc.)
$dispatcher->dispatch(new InvitationReceived($message, $result));
```

### Generating an Outbound Reply

```php
use Horde\Icalendar\Enum\ParticipationStatus;

$replyCal = $processor->generateReply(
    $existingEvent,
    'me@example.com',
    ParticipationStatus::from('ACCEPTED'),
);

// $replyCal is a VCalendar with METHOD=REPLY, ready for transport
```

### Implementing CalendarState

```php
use Horde\Itip\CalendarState;
use Horde\Icalendar\Calendar\Vevent;
use Horde\Icalendar\Enum\ParticipationStatus;

class KronolithCalendarState implements CalendarState
{
    public function findEventByUid(string $uid): ?Vevent { ... }
    public function getAttendeeStatus(string $uid, string $email): ?ParticipationStatus { ... }
    public function getEventSequence(string $uid): ?int { ... }
}
```

## Legacy horde/itip lib/ content

The package ships with its legacy, largely horde 5 compatible API to tie into the horde/icalendar legacy lib/ API.
This older API mixes iTip and iMip concerns.

## License

LGPL-2.1 — see [LICENSE](LICENSE).
