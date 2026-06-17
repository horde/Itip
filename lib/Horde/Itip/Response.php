<?php

/**
 * Handles Itip response data.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Itip
 * @author   Mike Cochrane <mike@graftonhall.co.nz>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Steffen Hansen <steffen@klaralvdalens-datakonsult.se>
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 */

/**
 * Handles Itip response data.
 *
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 * Copyright 2004-2026 Klarälvdalens Datakonsult AB
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did not
 * receive this file, see
 * {@link http://www.horde.org/licenses/lgpl21 LGPL}.
 *
 * @category Horde
 * @package  Itip
 * @author   Mike Cochrane <mike@graftonhall.co.nz>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Steffen Hansen <steffen@klaralvdalens-datakonsult.se>
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 */
class Horde_Itip_Response
{
    /**
     * The request we are going to answer.
     *
     * @var Horde_Itip_Event
     */
    private $_request;

    /**
     * The requested resource.
     *
     * @var Horde_Itip_Resource
     */
    private $_resource;

    /**
     * Constructor.
     *
     * @param Horde_Itip_Event    $request  The request this instance will
     *                                      respond to.
     * @param Horde_Itip_Resource $resource The requested resource.
     */
    public function __construct(
        Horde_Itip_Event $request,
        Horde_Itip_Resource $resource
    ) {
        $this->_request  = $request;
        $this->_resource = $resource;
    }

    /**
     * Return the original request.
     *
     * @return Horde_Itip_Event The original request.
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Return the response as an iCalendar vEvent object.
     *
     * @param Horde_Itip_Response_Type $type The response type.
     * @param Horde_Icalendar|boolean  $vCal The parent container or false if not
     *                                       provided.
     *
     * @return Horde_Icalendar_Vevent The response object.
     */
    public function getVevent(
        Horde_Itip_Response_Type $type,
        $vCal = false
    ) {
        $itip_reply = new Horde_Itip_Event_Vevent(
            Horde_Icalendar::newComponent('VEVENT', $vCal)
        );
        $this->_request->copyEventInto($itip_reply);

        $type->setRequest($this->_request);

        $itip_reply->setAttendee(
            $this->_resource->getMailAddress(),
            $this->_resource->getCommonName(),
            $type->getStatus()
        );
        return $itip_reply->getVevent();
    }

    /**
     * Return the response as an iCalendar object.
     *
     * @param Horde_Itip_Response_Type $type       The response type.
     * @param Horde_Itip_Response_Options $options The options for the response.
     *
     * @return Horde_Icalendar The response object.
     */
    public function getIcalendar(
        Horde_Itip_Response_Type $type,
        Horde_Itip_Response_Options $options
    ) {
        $vCal = new Horde_Icalendar();
        $options->prepareIcalendar($vCal);
        $vCal->setAttribute('METHOD', 'REPLY');
        $vCal->addComponent($this->getVevent($type, $vCal));
        return $vCal;
    }

    protected function _setIcsFilename(Horde_Mime_Part &$message)
    {
        $message->setName('event-reply.ics');
    }

    /**
     * Return the response as a MIME message.
     *
     * @param Horde_Itip_Response_Type    $type    The response type.
     * @param Horde_Itip_Response_Options $options The options for the response.
     *
     * @return array A list of two object: The mime headers and the mime
     *               message.
     */
    public function getMessage(
        Horde_Itip_Response_Type $type,
        Horde_Itip_Response_Options $options
    ) {
        $message = new Horde_Mime_Part();
        $message->setType('text/calendar');
        $options->prepareIcsMimePart($message);
        $message->setContents(
            $this->getIcalendar($type, $options)->exportvCalendar()
        );
        $message->setEOL("\r\n");
        $this->_setIcsFilename($message);
        $message->setContentTypeParameter('METHOD', 'REPLY');

        // Build the reply headers.
        $from = $this->_resource->getFrom();
        $reply_to = $this->_resource->getReplyTo();
        $headers = new Horde_Mime_Headers();
        $headers->addHeaderOb(Horde_Mime_Headers_Date::create());
        $headers->addHeader('From', $from);
        $headers->addHeader('To', $this->_request->getOrganizer());
        if (!empty($reply_to) && $reply_to != $from) {
            $headers->addHeader('Reply-to', $reply_to);
        }
        $headers->addHeader(
            'Subject',
            $type->getSubject()
        );

        $options->prepareResponseMimeHeaders($headers);

        return [$headers, $message];
    }

    /**
     * Return the response as a MIME message.
     *
     * @param Horde_Itip_Response_Type $type       The response type.
     * @param Horde_Itip_Response_Options $options The options for the response.
     *
     * @return array A list of two object: The mime headers and the mime
     *               message.
     */
    public function getMultiPartMessage(
        Horde_Itip_Response_Type $type,
        Horde_Itip_Response_Options $options
    ) {
        $message = new Horde_Mime_Part();
        $message->setType('multipart/alternative');

        [$headers, $ics] = $this->getMessage($type, $options);

        $body = new Horde_Mime_Part();
        $body->setType('text/plain');
        $options->prepareMessageMimePart($body);
        $body->setContents(Horde_String::wrap($type->getMessage(), 76));

        $message->addPart($body);
        $message->addPart($ics);

        return [$headers, $message];
    }

    /**
     * Return a METHOD=COUNTER vEvent with proposed meeting times.
     *
     * @param Horde_Itip_Response_Type $type           Attendee response type.
     * @param Horde_Icalendar|boolean  $vCal           Parent container.
     * @param Horde_Date               $proposedStart  Proposed start time.
     * @param Horde_Date|null          $proposedEnd    Proposed end time.
     *
     * @return Horde_Icalendar_Vevent
     */
    public function getCounterVevent(
        Horde_Itip_Response_Type $type,
        $vCal,
        Horde_Date $proposedStart,
        $proposedEnd = null
    ) {
        $vevent = $this->getVevent($type, $vCal);
        $this->_applyProposedTimes($vevent, $proposedStart, $proposedEnd);

        return $vevent;
    }

    /**
     * Return a METHOD=COUNTER iCalendar object.
     *
     * @param Horde_Itip_Response_Type    $type          Attendee response type.
     * @param Horde_Itip_Response_Options $options       Response options.
     * @param Horde_Date                  $proposedStart Proposed start time.
     * @param Horde_Date|null             $proposedEnd   Proposed end time.
     *
     * @return Horde_Icalendar
     */
    public function getCounterIcalendar(
        Horde_Itip_Response_Type $type,
        Horde_Itip_Response_Options $options,
        Horde_Date $proposedStart,
        $proposedEnd = null
    ) {
        $vCal = new Horde_Icalendar();
        $options->prepareIcalendar($vCal);
        $vCal->setAttribute('METHOD', 'COUNTER');
        $vCal->addComponent(
            $this->getCounterVevent($type, $vCal, $proposedStart, $proposedEnd)
        );

        return $vCal;
    }

    /**
     * Return a METHOD=COUNTER MIME message.
     *
     * @param Horde_Itip_Response_Type    $type          Attendee response type.
     * @param Horde_Itip_Response_Options $options       Response options.
     * @param Horde_Date                  $proposedStart Proposed start time.
     * @param Horde_Date|null             $proposedEnd   Proposed end time.
     *
     * @return array  MIME headers and calendar part.
     */
    public function getCounterMessage(
        Horde_Itip_Response_Type $type,
        Horde_Itip_Response_Options $options,
        Horde_Date $proposedStart,
        $proposedEnd = null
    ) {
        $message = new Horde_Mime_Part();
        $message->setType('text/calendar');
        $options->prepareIcsMimePart($message);
        $message->setContents(
            $this->getCounterIcalendar(
                $type,
                $options,
                $proposedStart,
                $proposedEnd
            )->exportvCalendar()
        );
        $message->setEOL("\r\n");
        $message->setName('event-counter.ics');
        $message->setContentTypeParameter('METHOD', 'COUNTER');

        $from = $this->_resource->getFrom();
        $reply_to = $this->_resource->getReplyTo();
        $headers = new Horde_Mime_Headers();
        $headers->addHeaderOb(Horde_Mime_Headers_Date::create());
        $headers->addHeader('From', $from);
        $headers->addHeader('To', $this->_request->getOrganizer());
        if (!empty($reply_to) && $reply_to != $from) {
            $headers->addHeader('Reply-to', $reply_to);
        }
        $headers->addHeader(
            'Subject',
            $this->_getCounterSubject($proposedStart, $proposedEnd)
        );

        $options->prepareResponseMimeHeaders($headers);

        return [$headers, $message];
    }

    /**
     * Return a multipart METHOD=COUNTER MIME message.
     *
     * @param Horde_Itip_Response_Type    $type          Attendee response type.
     * @param Horde_Itip_Response_Options $options       Response options.
     * @param Horde_Date                  $proposedStart Proposed start time.
     * @param Horde_Date|null             $proposedEnd   Proposed end time.
     *
     * @return array  MIME headers and message.
     */
    public function getCounterMultiPartMessage(
        Horde_Itip_Response_Type $type,
        Horde_Itip_Response_Options $options,
        Horde_Date $proposedStart,
        $proposedEnd = null
    ) {
        $message = new Horde_Mime_Part();
        $message->setType('multipart/alternative');

        [$headers, $ics] = $this->getCounterMessage(
            $type,
            $options,
            $proposedStart,
            $proposedEnd
        );

        $body = new Horde_Mime_Part();
        $body->setType('text/plain');
        $options->prepareMessageMimePart($body);
        $body->setContents(
            Horde_String::wrap(
                $this->_getCounterPlainMessage($proposedStart, $proposedEnd),
                76
            )
        );

        $message->addPart($body);
        $message->addPart($ics);

        return [$headers, $message];
    }

    /**
     * Apply proposed meeting times to a counter vEvent.
     */
    protected function _applyProposedTimes(
        Horde_Icalendar_Vevent $vevent,
        Horde_Date $proposedStart,
        $proposedEnd = null
    ) {
        try {
            $dtstartParams = $vevent->getAttribute('DTSTART', true);
            $params = is_array($dtstartParams) ? ($dtstartParams[0] ?? []) : [];
        } catch (Horde_Icalendar_Exception $e) {
            $params = [];
        }

        $start = clone $proposedStart;
        if (!empty($params['TZID'])) {
            $start->setTimezone($params['TZID']);
        }
        $vevent->removeAttribute('DTSTART');
        $vevent->setAttribute('DTSTART', $start, $params);

        if (!empty($proposedEnd)) {
            $endParams = $params;
            try {
                $dtendParams = $vevent->getAttribute('DTEND', true);
                if (is_array($dtendParams) && isset($dtendParams[0])) {
                    $endParams = $dtendParams[0];
                }
            } catch (Horde_Icalendar_Exception $e) {
            }

            $end = clone $proposedEnd;
            if (!empty($endParams['TZID'])) {
                $end->setTimezone($endParams['TZID']);
            }
            $vevent->removeAttribute('DTEND');
            $vevent->setAttribute('DTEND', $end, $endParams);
            $vevent->removeAttribute('DURATION');
        }
    }

    /**
     * Return the subject for a counter proposal.
     */
    protected function _getCounterSubject(
        Horde_Date $proposedStart,
        $proposedEnd = null
    ) {
        return sprintf(
            '%s: %s',
            Horde_Itip_Translation::t('Proposed new time'),
            $this->_request->getSummary()
        );
    }

    /**
     * Return the plain text body for a counter proposal.
     */
    protected function _getCounterPlainMessage(
        Horde_Date $proposedStart,
        $proposedEnd = null
    ) {
        $range = $proposedStart->setTimezone('UTC')->format('Y-m-d H:i:s') . ' UTC';
        if (!empty($proposedEnd)) {
            $range .= ' - ' . $proposedEnd->setTimezone('UTC')->format('Y-m-d H:i:s') . ' UTC';
        }

        return sprintf(
            "%s %s:\n\n%s\n\n%s",
            $this->_resource->getCommonName(),
            Horde_Itip_Translation::t('has proposed a new time for the following event'),
            $this->_request->getSummary(),
            $range
        );
    }
}
