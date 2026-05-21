<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Itip\Event;

/**
 * Dispatched when a METHOD=REPLY is about to be sent to the organizer.
 */
final class ReplySending extends ItipEvent {}
