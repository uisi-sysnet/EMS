<?php

namespace App\Http\Controllers;

use App\Models\CalibrationApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CalibrationController extends Controller
{
    /**
     * Display the calibration page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $calibrations = CalibrationApi::orderBy('created_at', 'desc')->get();
        return view('settings.calibration', compact('calibrations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source'        => 'required|string|max:255',
            'api_url'       => 'required|url|max:500',
            'api_token'     => 'required|string|min:1',
            'auth_type'     => 'required|string|in:bearer_token',
            'checklist'     => 'nullable|array',
            'total_data'    => 'nullable|integer|min:0',
            'requests_per_min' => 'nullable|integer|min:0',
            'file_path'     => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $calibration = CalibrationApi::create([
            'source'          => $request->source,
            'api_url'         => $request->api_url,
            'api_token'       => encrypt($request->api_token), // encrypt for safety
            'auth_type'       => $request->auth_type,
            'checklist'       => $request->checklist ?? [],
            'total_data'      => $request->total_data ?? 0,
            'requests_per_min' => $request->requests_per_min ?? 0,
            'file_path'       => $request->file_path ?? null,
        ]);

        return response()->json([
            'message' => 'Calibration API saved successfully.',
            'data'    => $calibration,
        ], 201);
    }

    /**
     * Display the specified resource (for JSON view).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $calibration = CalibrationApi::findOrFail($id);
        // Decrypt token if you want to show it (or just return a placeholder)
        $data = $calibration->toArray();
        $data['api_token'] = decrypt($calibration->api_token); // optional
        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $calibration = CalibrationApi::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'source'        => 'required|string|max:255',
            'api_url'       => 'required|url|max:500',
            'api_token'     => 'nullable|string|min:1', // allow empty to keep current
            'auth_type'     => 'required|string|in:bearer_token',
            'checklist'     => 'nullable|array',
            'total_data'    => 'nullable|integer|min:0',
            'requests_per_min' => 'nullable|integer|min:0',
            'file_path'     => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = [
            'source'          => $request->source,
            'api_url'         => $request->api_url,
            'auth_type'       => $request->auth_type,
            'checklist'       => $request->checklist ?? [],
            'total_data'      => $request->total_data ?? 0,
            'requests_per_min' => $request->requests_per_min ?? 0,
            'file_path'       => $request->file_path ?? null,
        ];

        // Only update token if provided
        if ($request->filled('api_token')) {
            $updateData['api_token'] = encrypt($request->api_token);
        }

        $calibration->update($updateData);

        return response()->json([
            'message' => 'Calibration API updated successfully.',
            'data'    => $calibration,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $calibration = CalibrationApi::findOrFail($id);
        $calibration->delete();

        return response()->json(['message' => 'Calibration API deleted successfully.']);
    }
}