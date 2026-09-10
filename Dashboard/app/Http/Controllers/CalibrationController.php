<?php

namespace App\Http\Controllers;

use App\Models\CalibrationApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CalibrationController extends Controller
{
    /**
     * Display the calibration page.
     */
    public function index()
    {
        $calibrations = CalibrationApi::orderBy('created_at', 'desc')->get();
        return view('settings.calibration', compact('calibrations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source'           => 'required|string|max:255',
            'api_url'          => 'required|url|max:500',
            'api_token'        => 'required|string|min:1',
            'auth_type'        => 'required|string|in:bearer_token',
            'checklist'        => 'nullable|array',
            'total_data'       => 'nullable|integer|min:0',
            'requests_per_min' => 'nullable|integer|min:0',
            'file_path'        => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $calibration = CalibrationApi::create([
            'source'           => $request->source,
            'api_url'          => $request->api_url,
            'api_token'        => encrypt($request->api_token),
            'auth_type'        => $request->auth_type,
            'checklist'        => $request->checklist ?? [],
            'total_data'       => $request->total_data ?? 0,
            'requests_per_min' => $request->requests_per_min ?? 0,
            'file_path'        => $request->file_path ?? null,
        ]);

        return response()->json([
            'message' => 'Calibration API saved successfully.',
            'data'    => $calibration,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $calibration = CalibrationApi::findOrFail($id);
        $data = $calibration->toArray();
        try {
            $data['api_token'] = decrypt($calibration->api_token);
        } catch (\Exception $e) {
            $data['api_token'] = null;
        }
        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $calibration = CalibrationApi::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'source'           => 'required|string|max:255',
            'api_url'          => 'required|url|max:500',
            'api_token'        => 'nullable|string|min:1',
            'auth_type'        => 'required|string|in:bearer_token',
            'checklist'        => 'nullable|array',
            'total_data'       => 'nullable|integer|min:0',
            'requests_per_min' => 'nullable|integer|min:0',
            'file_path'        => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = [
            'source'           => $request->source,
            'api_url'          => $request->api_url,
            'auth_type'        => $request->auth_type,
            'checklist'        => $request->checklist ?? [],
            'total_data'       => $request->total_data ?? 0,
            'requests_per_min' => $request->requests_per_min ?? 0,
            'file_path'        => $request->file_path ?? null,
        ];

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
     */
    public function destroy($id)
    {
        $calibration = CalibrationApi::findOrFail($id);
        $calibration->delete();

        return response()->json(['message' => 'Calibration API deleted successfully.']);
    }

    /**
     * Test the API connection before saving.
     */
    public function test(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_url'   => 'required|url',
            'api_token' => 'required|string',
            'auth_type' => 'required|string|in:bearer_token',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $url      = $request->api_url;
        $token    = $request->api_token;
        $authType = $request->auth_type;

        try {
            $headers = [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'User-Agent'    => 'Munti-Calibration/1.0',
            ];

            $startTime = microtime(true);

            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->get($url);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $body = null;
            try {
                $body = $response->json();
            } catch (\Exception $e) {
                $body = $response->body();
            }

            // Extract flattened field paths from the response
            $fields = is_array($body) ? $this->extractFields($body) : [];

            if ($response->successful()) {
                return response()->json([
                    'success'     => true,
                    'status'      => $response->status(),
                    'duration_ms' => $duration,
                    'message'     => 'API is working correctly.',
                    'preview'     => $this->truncatePreview($body),
                    'fields'      => $fields,
                ]);
            }

            return response()->json([
                'success'     => false,
                'status'      => $response->status(),
                'duration_ms' => $duration,
                'message'     => 'API responded with an error status.',
                'preview'     => $this->truncatePreview($body),
                'fields'      => $fields,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not connect to API. Please check the URL.',
                'error'   => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('API test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'API test failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch the live API response for a saved calibration record.
     */
    public function fetchResponse($id)
    {
        $calibration = CalibrationApi::findOrFail($id);

        try {
            $token = decrypt($calibration->api_token);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stored token could not be decrypted.',
                'error'   => $e->getMessage(),
            ]);
        }

        try {
            $headers = [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'User-Agent'    => 'Munti-Calibration/1.0',
            ];

            $startTime = microtime(true);

            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->get($calibration->api_url);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $body = null;
            try {
                $body = $response->json();
            } catch (\Exception $e) {
                $body = $response->body();
            }

            return response()->json([
                'success'     => $response->successful(),
                'status'      => $response->status(),
                'duration_ms' => $duration,
                'url'         => $calibration->api_url,
                'source'      => $calibration->source,
                'fetched_at'  => now()->toDateTimeString(),
                'data'        => $body,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not connect to API. Please check the URL.',
                'url'     => $calibration->api_url,
                'error'   => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetch API response failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch API response: ' . $e->getMessage(),
                'url'     => $calibration->api_url,
            ]);
        }
    }

    /**
     * Flatten a JSON structure to dot-notation field paths.
     *
     * Example:
     *   [{ "a": 1, "b": { "c": 2 } }]  →  ["a", "b.c"]
     */
    private function extractFields($data, $prefix = '')
    {
        $fields = [];
        if (!is_array($data)) return $fields;

        $keys = array_keys($data);
        $isList = !empty($keys) && $keys === range(0, count($keys) - 1);

        // If root is a list, sample the first element
        if ($isList && $prefix === '' && !empty($data)) {
            return $this->extractFields($data[0], '');
        }

        foreach ($data as $key => $value) {
            if ($isList) {
                $path = $prefix === '' ? "[$key]" : "{$prefix}[$key]";
            } else {
                $path = $prefix === '' ? (string)$key : "{$prefix}.{$key}";
            }

            if (is_array($value) && !empty($value)) {
                $fields = array_merge($fields, $this->extractFields($value, $path));
            } else {
                $fields[] = $path;
            }
        }

        return $fields;
    }

    /**
     * Truncate the response preview to avoid huge payloads.
     */
    private function truncatePreview($data, $maxLength = 2000)
    {
        if (is_string($data)) {
            return substr($data, 0, $maxLength);
        } elseif (is_array($data)) {
            $json = json_encode($data, JSON_PRETTY_PRINT);
            if (strlen($json) > $maxLength) {
                return substr($json, 0, $maxLength) . "\n... (truncated)";
            }
            return $data;
        }
        return $data;
    }
}