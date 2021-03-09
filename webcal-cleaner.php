#!/usr/bin/php
<?php
# Webcal cleaner (aka clean that huge iCloud calendars)
# Copyright (C) 2021 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.

// webcal separator of each event
define( 'EVENT_SEPARATOR', "BEGIN:VEVENT" );

// do not display events older than this number of days
define( 'MAX_DAYS', 30 * 6 );

// url
$url = $argv[ 1 ] ?? null;
if( !$url ) {
	echo "RTFM missing URL\n";
	exit( 1 );
}

// output filename
$OUT_FILE = $argv[ 2 ] ?? null;
if( !$OUT_FILE ) {
	echo "RTFM missing output filename\n";
	exit( 2 );
}

$start_time = new DateTime();

$cal = file_get_contents( $url );

// how much time to download this shit asd
$download_time = ( new DateTime() )->getTimestamp() - $start_time->getTimestamp();

//
//CREATED:20141203T080231Z
//UID:002455D8-719B-435B-84F9-AC5D56C4B6E9
//DTEND;TZID=Europe/Rome:20141211T233000
//SUMMARY:asd
//DTSTART;TZID=Europe/Rome:20141211T190000
//DTSTAMP:20141203T080231Z
//SEQUENCE:0
//END:VEVENT
//
$events_raw = explode( EVENT_SEPARATOR, $cal );

// header of the calendar
$calendar_start = array_shift( $events_raw );

// counter of filtered events
$filtered = 0;

// interesting events
$events_interesting = $calendar_start;

// total number of events
$last_i = count( $events_raw ) - 1;

// for each raw event
foreach( $events_raw as $i => $event_raw ) {

	// the event can be empty (or the first line)
	if( $event_raw ) {

		// restore separator
		$event_raw = EVENT_SEPARATOR . $event_raw;

		if( $i > 1 ) {
			$event_lines = explode( "\r\n", $event_raw );
			$event_sections = event_sections( $event_lines );

			// creation date
			$created_raw = $event_sections['CREATED'] ?? null;

			// no creation date? try last modified
			if(!$created_raw) {
				$created_raw = $event_sections['LAST-MODIFIED'] ?? null;
			}

			// check if the creation date has sense
			if( !$created_raw ) {
				echo "Missing date\n";
				var_dump( $event_raw );
				continue;
			}

			// parse the date
			$created = DateTime::createFromFormat( 'Ymd\THisO', $created_raw );
			if( !$created ) {
				echo "Bad date $created_raw\n";
				var_dump( $event_raw );
				continue;
			}

			// date diff
			$diff = $start_time->diff( $created );

			// days ago
			$days = $diff->days;

			// take only interesting events
			// note that the last event is always taken,
			// to preserve END:VCALENDAR shit asd
			if( $days < MAX_DAYS || $i === $last_i ) {
				$events_interesting .= $event_raw;
			} else {
				$filtered++;
			}
		}

	}

}

// print the shit
file_put_contents( $OUT_FILE, $events_interesting );

// remanining events
$remainings = $last_i - $filtered;

// print stats
echo "Download in {$download_time}s. Filtered $filtered. Kept $remainings.\n";

/**
 * Parse event sections in a key=>value form
 */
function event_sections( $event_lines ) {

	$lines = [];
	foreach( $event_lines as $event_line ) {
		if( $event_line ) {
			$parts = explode( ':', $event_line, 2 );
			if( count( $parts ) === 2 ) {
				$lines[ $parts[0] ] = $parts[1];
			}
		}
	}

	return $lines;
}
