<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CronRunHistory;

class CronRunHistoryController extends Controller
{
    /**
     * Store a new cron run history.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cron_name' => 'required|string',
            'start_time' => 'required|date',
            'status' => 'required|string',
        ]);

        $cronRun = CronRunHistory::create([
            'cron_name' => $request->cron_name,
            'start_time' => $request->start_time,
            'status' => $request->status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Cron run history stored', 'id' => $cronRun->_id], 201);
    }

    /**
     * Update an existing cron run history.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'end_time' => 'required|date',
            'status' => 'required|string',
            'error_message' => 'nullable|string',
        ]);

        $cronRun = CronRunHistory::where('_id', $id)->update([
            'end_time' => $request->end_time,
            'status' => $request->status,
            'error_message' => $request->error_message,
            'updated_at' => now(),
        ]);

        if ($cronRun) {
            return response()->json(['message' => 'Cron run history updated']);
        } else {
            return response()->json(['message' => 'Cron run history not found'], 404);
        }
    }
}
