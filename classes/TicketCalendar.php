<?php
namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


/**
 * Simple PHP Calendar Class.
 *
 * @copyright  Copyright (c) Benjamin Hall
 * @license https://github.com/benhall14/php-calendar
 * @package protocols
 * @version 1.1
 * @author Benjamin Hall <https://linkedin.com/in/benhall14>
*/
class TicketCalendar
{
    /**
     * The internal date pointer.
     * @var DateTime
     */
    private $date;

    public function __construct() {
        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $this->today = new \DateTime();
        $this->today->setTimezone($this->railticket_timezone);
        $this->today->setTime(0,0,0);
    }

    /**
     * Draw the calendar and echo out.
     * @param string $date    The date of this calendar.
     * @param string $format  The format of the preceding date.
     * @return string         The calendar
     */
    public function draw($date = false, $guard = false)
    {
        $calendar = '';

        $yesterday = new \DateTime();
        $yesterday->setTimezone($this->railticket_timezone);
        $yesterday->modify('-1 day');

        if ($date) {
            $date = \DateTime::createFromFormat('Y-m-d', $date, $this->railticket_timezone);
            $date->modify('first day of this month');
        } else {
            $date = new \DateTime();
            $date->setTimezone($this->railticket_timezone);
            $date->modify('first day of this month');
        }

        $today = new \DateTime();
        $today->setTimezone($this->railticket_timezone);
        $total_days_in_month = (int) $date->format('t');
        $calendar .= '<table class="railticket-calendar ticket-calendar">';
        $calendar .= '<thead>';
        $calendar .= '<tr class="railticket-calendar-title">';
        $calendar .= '<th colspan="7">';
        $calendar .= railticket_timefunc('%B %Y', $date->getTimestamp());
        $calendar .= '</th>';
        $calendar .= '</tr>';
        $calendar .= '<tr class="railticket-calendar-header">';
        $calendar .= '<th>';
        $calendar .= implode('</th><th>', $this->daysofweek());
        $calendar .= '</th>';
        $calendar .= '</tr>';
        $calendar .= '</thead>';
        $calendar .= '<tbody>';
        $calendar .= '<tr>';

        # padding before the month start date IE. if the month starts on Wednesday
        for ($x = 0; $x < $date->format('w'); $x++) {
            $calendar .= '<td class="pad"> </td>';
        }

        $running_day = clone $date;
        $running_day_count = 1;
        $rowcount = 0;

        do {
            $timetable = \wc_railticket\Timetable::get_timetable_by_date($running_day->format('Y-m-d'));
            $class = '';
            $style = '';
            $event_summary = '';

            $datebookable = \wc_railticket\BookableDay::is_date_bookable($running_day, $guard);
            $datesoldout = \wc_railticket\BookableDay::is_date_sold_out($running_day);

            if ($timetable) {
                $timetabledate = \DateTime::createFromFormat('Y-m-d', $timetable->get_date(), $this->railticket_timezone);
                if ($timetabledate > $yesterday) {
                    if ($datebookable) {
                        if ($datesoldout) {
                            $style .= "background:#".$timetable->get_background().";color:#".$timetable->get_colour().";font-weight:bold;";
                        } else {
                            $style .= "background:#".$timetable->get_background().";color:#".$timetable->get_colour().";";
                        }
                    } else {
                        $style .= "opacity:0.4;background:#".$timetable->get_background().";color:#".$timetable->get_colour().";";
                    }
                }
            }

            $today_class = ($running_day->format('Y-m-d') == $today->format('Y-m-d')) ? ' today' : '';
            $calendar .= '<td style="'.$style.'" class="' . $class . $today_class . ' day" title="' . htmlentities($event_summary) . '">';

            if ($timetable) {
                if ($datebookable) {
                    $calendar .= "<a style='".$style."' title='Click to select this date' href=\"javascript:setBookingDate('".$running_day->format("Y-m-d")."');\">";
                    $calendar .= $running_day->format('j');
                    $calendar .= '</a>';
                } else {
                    if ($datesoldout) {
                        $calendar .= "<a style='".$style." text-decoration: line-through;' title='Sold Out' href=\"javascript:soldOut('".$running_day->format("Y-m-d")."');\">";
                        $calendar .= $running_day->format('j');
                        $calendar .= '</a>';
                    } else {
                        $calendar .= "<a style='".$style."' title='Not available to book on line' href=\"javascript:notBookable('".$running_day->format("Y-m-d")."');\">";
                        $calendar .= $running_day->format('j');
                        $calendar .= '</a>';
                    }
                }
            } else {
                $calendar .= $running_day->format('j');
            }
            $calendar .= "</td>";

            # check if this calendar-row is full and if so push to a new calendar row
            if ($running_day->format('w') == 6) {
                $calendar .= '</tr>';

                # start a new calendar row if there are still days left in the month
                if (($running_day_count + 1) <= $total_days_in_month) {
                    $calendar .= '<tr>';
                }

                # reset padding because its a new calendar row
                $day_padding_offset = 0;
            }

            $running_day->modify('+1 Day');

            $running_day_count++;
        } while ($running_day_count <= $total_days_in_month);

        $padding_at_end_of_month = 7 - $running_day->format('w');

        # padding at the end of the month
        if ($padding_at_end_of_month && $padding_at_end_of_month < 7) {
            for ($x = 1; $x <= $padding_at_end_of_month; $x++) {
                $calendar .= '<td class="pad"> </td>';
            }
        }

        $calendar .= '</tr>';
        $calendar .= '</tbody>';
        $calendar .= '</table>';

        return $calendar;
    }

    private function daysofweek($chars = 1) {
        $timestamp = strtotime('next Sunday');
        $days = array();
        for ($i = 0; $i < 7; $i++) {
            $days[] = substr(railticket_timefunc('%A', $timestamp), 0, 1);
            $timestamp = strtotime('+1 day', $timestamp);
        }
        return $days;
    }
}
 
