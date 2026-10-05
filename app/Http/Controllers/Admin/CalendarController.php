<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Request;
use App\Http\Response;
use DateTimeImmutable;
use DateTimeZone;

/** Simple month grid: one row per apartment, one column per day (the night that starts that day). */
final class CalendarController extends BasePage
{
    public function index(Request $request): Response
    {
        $utc = new DateTimeZone('UTC');
        $requested = $request->query('mese');
        $valid = $requested === '' || (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested) === 1 && $requested >= '2000-01' && $requested <= '2100-12');

        $first = new DateTimeImmutable(($valid && $requested !== '' ? $requested : substr($this->today(), 0, 7)) . '-01', $utc);
        $daysInMonth = (int) $first->format('t');
        $start = $first->format('Y-m-d');
        $end = $first->modify('+1 month')->format('Y-m-d');

        $queries = $this->queries();
        $apartments = $queries->apartments();
        $cells = [];

        foreach ($queries->bookingsInRange($start, $end) as $b) {
            foreach ($this->daysOf((string) $b['check_in'], (string) $b['check_out'], $start, $daysInMonth) as $day) {
                $cells[(int) $b['apartment_id']][$day] = ['type' => 'booking', 'id' => (int) $b['id'], 'label' => (string) $b['guest_name']];
            }
        }
        foreach ($queries->blocksInRange($start, $end) as $k) {
            foreach ($this->daysOf((string) $k['start_date'], (string) $k['end_date'], $start, $daysInMonth) as $day) {
                // A confirmed booking is never overlapped by a block, so there is no cell to overwrite.
                $cells[(int) $k['apartment_id']][$day] = ['type' => 'block', 'id' => (int) $k['id'], 'label' => (string) ($k['reason'] ?? '')];
            }
        }

        return $this->render('admin/calendar/index', [
            'title' => 'Calendario',
            'month' => $first->format('Y-m'),
            'monthStart' => $start,
            'days' => range(1, $daysInMonth),
            'apartments' => $apartments,
            'cells' => $cells,
            'prev' => $first->modify('-1 month')->format('Y-m'),
            'next' => $first->modify('+1 month')->format('Y-m'),
            'invalidMonth' => !$valid,
        ], $valid ? 200 : 400);
    }

    /**
     * Day numbers of the month whose night belongs to [$from, $to).
     *
     * @return list<int>
     */
    private function daysOf(string $from, string $to, string $monthStart, int $daysInMonth): array
    {
        $days = [];
        $prefix = substr($monthStart, 0, 8);
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $prefix . sprintf('%02d', $d);
            if ($date >= $from && $date < $to) {
                $days[] = $d;
            }
        }
        return $days;
    }
}
