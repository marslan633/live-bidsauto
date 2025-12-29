<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LandingPageRule;

class LandingPageRuleController extends Controller
{
     /**
     * -----------------------------------------
     * GET: All Landing Page Rules
     * -----------------------------------------
     */
    public function index()
    {
        try {
            $rules = LandingPageRule::orderBy('id')->get();

            return sendResponse(true, 200, 'Landing page rules fetched successfully', $rules, 200);

        } catch (\Exception $e) {
            return sendResponse(false, 500, 'Internal Server Error', $e->getMessage(), 500);
        }
    }

    /**
     * -----------------------------------------
     * GET: Single Rule (Edit Page)
     * -----------------------------------------
     */
    public function show($id)
    {
        try {
            $rule = LandingPageRule::find($id);

            if (!$rule) {
                return sendResponse(false, 404, 'Rule not found', null, 404);
            }

            return sendResponse(true, 200, 'Landing page rule fetched successfully', $rule, 200);

        } catch (\Exception $e) {
            return sendResponse(false, 500, 'Internal Server Error', $e->getMessage(), 500);
        }
    }

    /**
     * -----------------------------------------
     * UPDATE: Landing Page Rule
     * -----------------------------------------
     */
    public function update(Request $request, $id)
    {
        try {
            $rule = LandingPageRule::find($id);

            if (!$rule) {
                return sendResponse(false, 404, 'Rule not found', null, 404);
            }

            $request->validate([
                'section_title' => 'required|string',
                'limit' => 'required|integer|min:1|max:20',
                'request_body' => 'required|array',
            ]);

            /**
             * IMPORTANT:
             * - request_body is saved AS-IS
             * - IDs only
             * - No validation of filters here
             * - Your existing search logic will handle it
             */
            $rule->update([
                'section_title' => $request->section_title,
                'limit' => $request->limit,
                'request_body' => $request->request_body,
            ]);

            return sendResponse(true, 200, 'Landing page rule updated successfully', [
                'data' => $rule
            ], 200);

        } catch (\Exception $e) {
            return sendResponse(false, 500, 'Internal Server Error', $e->getMessage(), 500);
        }
    }
}