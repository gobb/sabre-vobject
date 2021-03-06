<?php

namespace Sabre\VObject\Component;

use Sabre\VObject\Component;
use DateTime;
use Sabre\VObject\Reader;

class VAlarmTest extends \PHPUnit_Framework_TestCase {

    public function setUp() {

        $this->markTestSkipped('This test relies on custom properties, which isn\'t ready yet');

    }

    /**
     * @dataProvider timeRangeTestData
     */
    public function testInTimeRange(VAlarm $valarm,$start,$end,$outcome) {

        $this->assertEquals($outcome, $valarm->isInTimeRange($start, $end));

    }

    public function timeRangeTestData() {

        $tests = array();

        // Hard date and time        
        $valarm1 = Component::create('VALARM');
        $valarm1->TRIGGER = '20120312T130000Z';
        $valarm1->TRIGGER['VALUE'] = 'DATE-TIME';

        $tests[] = array($valarm1, new DateTime('2012-03-01 01:00:00'), new DateTime('2012-04-01 01:00:00'), true);
        $tests[] = array($valarm1, new DateTime('2012-03-01 01:00:00'), new DateTime('2012-03-10 01:00:00'), false);

        // Relation to start time of event
        $valarm2 = Component::create('VALARM');
        $valarm2->TRIGGER = '-P1D';
        $valarm2->TRIGGER['VALUE'] = 'DURATION';

        $vevent2 = Component::create('VEVENT');
        $vevent2->DTSTART = '20120313T130000Z';
        $vevent2->add($valarm2);

        $tests[] = array($valarm2, new DateTime('2012-03-01 01:00:00'), new DateTime('2012-04-01 01:00:00'), true);
        $tests[] = array($valarm2, new DateTime('2012-03-01 01:00:00'), new DateTime('2012-03-10 01:00:00'), false);

        // Relation to end time of event
        $valarm3 = Component::create('VALARM');
        $valarm3->TRIGGER = '-P1D';
        $valarm3->TRIGGER['VALUE'] = 'DURATION';
        $valarm3->TRIGGER['RELATED']= 'END';

        $vevent3 = Component::create('VEVENT');
        $vevent3->DTSTART = '20120301T130000Z';
        $vevent3->DTEND = '20120401T130000Z';
        $vevent3->add($valarm3);

        $tests[] = array($valarm3, new DateTime('2012-02-25 01:00:00'), new DateTime('2012-03-05 01:00:00'), false);
        $tests[] = array($valarm3, new DateTime('2012-03-25 01:00:00'), new DateTime('2012-04-05 01:00:00'), true);

        // Relation to end time of todo 
        $valarm4 = Component::create('VALARM');
        $valarm4->TRIGGER = '-P1D';
        $valarm4->TRIGGER['VALUE'] = 'DURATION';
        $valarm4->TRIGGER['RELATED']= 'END';

        $vtodo4 = Component::create('VTODO');
        $vtodo4->DTSTART = '20120301T130000Z';
        $vtodo4->DUE = '20120401T130000Z';
        $vtodo4->add($valarm4);

        $tests[] = array($valarm4, new DateTime('2012-02-25 01:00:00'), new DateTime('2012-03-05 01:00:00'), false);
        $tests[] = array($valarm4, new DateTime('2012-03-25 01:00:00'), new DateTime('2012-04-05 01:00:00'), true);

        // Relation to start time of event + repeat
        $valarm5 = Component::create('VALARM');
        $valarm5->TRIGGER = '-P1D';
        $valarm5->TRIGGER['VALUE'] = 'DURATION';
        $valarm5->REPEAT = 10;
        $valarm5->DURATION = 'P1D';

        $vevent5 = Component::create('VEVENT');
        $vevent5->DTSTART = '20120301T130000Z';
        $vevent5->add($valarm5);

        $tests[] = array($valarm5, new DateTime('2012-03-09 01:00:00'), new DateTime('2012-03-10 01:00:00'), true);

        // Relation to start time of event + duration, but no repeat
        $valarm6 = Component::create('VALARM');
        $valarm6->TRIGGER = '-P1D';
        $valarm6->TRIGGER['VALUE'] = 'DURATION';
        $valarm6->DURATION = 'P1D';

        $vevent6 = Component::create('VEVENT');
        $vevent6->DTSTART = '20120313T130000Z';
        $vevent6->add($valarm6);

        $tests[] = array($valarm6, new DateTime('2012-03-01 01:00:00'), new DateTime('2012-04-01 01:00:00'), true);
        $tests[] = array($valarm6, new DateTime('2012-03-01 01:00:00'), new DateTime('2012-03-10 01:00:00'), false);


        // Relation to end time of event (DURATION instead of DTEND)
        $valarm7 = Component::create('VALARM');
        $valarm7->TRIGGER = '-P1D';
        $valarm7->TRIGGER['VALUE'] = 'DURATION';
        $valarm7->TRIGGER['RELATED']= 'END';

        $vevent7 = Component::create('VEVENT');
        $vevent7->DTSTART = '20120301T130000Z';
        $vevent7->DURATION = 'P30D';
        $vevent7->add($valarm7);

        $tests[] = array($valarm7, new DateTime('2012-02-25 01:00:00'), new DateTime('2012-03-05 01:00:00'), false);
        $tests[] = array($valarm7, new DateTime('2012-03-25 01:00:00'), new DateTime('2012-04-05 01:00:00'), true);

        // Relation to end time of event (No DTEND or DURATION)
        $valarm7 = Component::create('VALARM');
        $valarm7->TRIGGER = '-P1D';
        $valarm7->TRIGGER['VALUE'] = 'DURATION';
        $valarm7->TRIGGER['RELATED']= 'END';

        $vevent7 = Component::create('VEVENT');
        $vevent7->DTSTART = '20120301T130000Z';
        $vevent7->add($valarm7);

        $tests[] = array($valarm7, new DateTime('2012-02-25 01:00:00'), new DateTime('2012-03-05 01:00:00'), true);
        $tests[] = array($valarm7, new DateTime('2012-03-25 01:00:00'), new DateTime('2012-04-05 01:00:00'), false);


        return $tests;
    }

    /**
     * @expectedException LogicException
     */
    public function testInTimeRangeInvalidComponent() {

        $valarm = Component::create('VALARM');
        $valarm->TRIGGER = '-P1D';
        $valarm->TRIGGER['RELATED'] = 'END';

        $vjournal = Component::create('VJOURNAL');
        $vjournal->add($valarm);

        $valarm->isInTimeRange(new DateTime('2012-02-25 01:00:00'), new DateTime('2012-03-05 01:00:00'));

    }

    /**
     * This bug was found and reported on the mailing list.
     */
    public function testInTimeRangeBuggy() {

$input = <<<BLA
BEGIN:VCALENDAR
BEGIN:VTODO
DTSTAMP:20121003T064931Z
UID:b848cb9a7bb16e464a06c222ca1f8102@examle.com
STATUS:NEEDS-ACTION
DUE:20121005T000000Z
SUMMARY:Task 1
CATEGORIES:AlarmCategory
BEGIN:VALARM
TRIGGER:-PT10M
ACTION:DISPLAY
DESCRIPTION:Task 1
END:VALARM
END:VTODO
END:VCALENDAR
BLA;

        $vobj = Reader::read($input);

        $this->assertTrue($vobj->VTODO->VALARM->isInTimeRange(new \DateTime('2012-10-01 00:00:00'), new \DateTime('2012-11-01 00:00:00')));

    }

}

