$minutes = 25;

if (!empty($hits)) {
    $lastCron = $hits[0]['_source'];
    if (!empty($lastCron['end_time'])) {
        $endTime = Carbon::parse($lastCron['end_time']);
        $timeDifference = max(0, $endTime->diffInMinutes(now()));
        Log::info('Time Difference Active '. $timeDifference);
        if ($timeDifference > 25) {
            $minutes = $timeDifference + 10;
        } elseif ($timeDifference === 25) {
            $minutes = $timeDifference + 5;
        }
    }
}

// Get the current hour
$currentHour = now()->hour;

// Check if the current hour is one of the specific hours (0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22)
if (in_array($currentHour, range(0, 24, 2))) {
    Log::info('Current time is an even hour: ' . $currentHour);
    // Print something for even hours like 0, 2, 4, 6, etc.
} else {
    $currentMinute = now()->minute;
    // Check if it's 0:30, 1:00, 1:30, etc.
    if (($currentMinute == 30) || ($currentHour == 0 && $currentMinute == 0)) {
        Log::info('Current time is 0:30, 1:00, 1:30, etc.');
        // Print something else for these times
    }
}


->whereRaw(
                "DATE_FORMAT(DATE_ADD(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), INTERVAL 28 HOUR), '%Y-%m-%d %H:%i') <= ?",
                [now()->format('Y-m-d H:i')]
            )
