<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EnvEditorController extends Controller
{
    private function getEnvPath()
    {
        /* return 'C:/Users/JUREEN/Desktop/Emergency-Warning-System-main/.env'; */
        return '/home/system/EMS/scripts/.env';
    }

    // ------------------------------------------------------------
    // DATABASE SETTINGS (now comments‑free)
    // ------------------------------------------------------------

    public function index()
    {
        return view('env.db-editor');
    }

    /**
     * Load only the variable lines – no comments.
     * The front‑end will split them by prefix.
     */
    public function load()
    {
        $path = $this->getEnvPath();

        if (!File::exists($path)) {
            return response()->json([
                'success' => false,
                'error'   => 'File not found',
                'path'    => $path,
            ]);
        }

        $fullContent = File::get($path);
        $lines = explode("\n", $fullContent);

        // We'll collect only lines that start with our defined prefixes
        $allPrefixes = ['SYSTEM_DB_', 'AQ_DB_', 'SEISMIC_DB_', 'SMS_DB_', 'API_DB_', 'LOG_DB_'];
        $collected = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            foreach ($allPrefixes as $prefix) {
                if (str_starts_with($trimmed, $prefix)) {
                    $collected[] = $line; // keep original line (with spaces etc.)
                    break;
                }
            }
        }

        // Return as a plain block (just lines, no comments)
        $finalBlock = implode("\n", $collected);

        return response()->json([
            'success' => true,
            'content' => $finalBlock,
        ]);
    }

    /**
     * Save the database settings – update only the variables we manage.
     * New variables are appended at the end.
     */
    public function save(Request $request)
    {
        $path = $this->getEnvPath();
        $newBlock = $request->input('content');

        if (!File::exists($path)) {
            return response()->json([
                'success' => false,
                'error'   => 'File not found',
            ]);
        }

        // Parse the new block to get key => value for all variables
        $newLines = explode("\n", $newBlock);
        $newVars = [];
        foreach ($newLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (str_contains($trimmed, '=')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $value = isset($parts[1]) ? trim($parts[1]) : '';
                $newVars[$key] = $value;
            }
        }

        // Read existing .env
        $fullContent = File::get($path);
        $lines = explode("\n", $fullContent);

        $allPrefixes = ['SYSTEM_DB_', 'AQ_DB_', 'SEISMIC_DB_', 'SMS_DB_', 'API_DB_', 'LOG_DB_'];

        $existingKeys = [];
        $updatedLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $matched = false;

            foreach ($allPrefixes as $prefix) {
                if (str_starts_with($trimmed, $prefix)) {
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $existingKeys[] = $key;

                    if (array_key_exists($key, $newVars)) {
                        $newValue = $newVars[$key];
                        $updatedLines[] = $key . '=' . $newValue;
                    } else {
                        $updatedLines[] = $line; // keep original
                    }
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $updatedLines[] = $line;
            }
        }

        // Append missing keys
        $missingKeys = array_diff(array_keys($newVars), $existingKeys);
        if (!empty($missingKeys)) {
            if (!empty($updatedLines) && trim(end($updatedLines)) !== '') {
                $updatedLines[] = '';
            }
            foreach ($missingKeys as $key) {
                $updatedLines[] = $key . '=' . $newVars[$key];
            }
        }

        $newFull = implode("\n", $updatedLines);

        if ($newFull === $fullContent) {
            return response()->json(['success' => true, 'message' => 'No changes needed']);
        }

        File::put($path, $newFull);
        return response()->json(['success' => true]);
    }

    /**
     * Helper to deduplicate variables inside a block.
     * Keeps all comment lines and the first occurrence of each variable key.
     */
    private function deduplicateBlock(array $lines)
    {
        $result = [];
        $seenKeys = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '#')) {
                // Comment line – always keep
                $result[] = $line;
            } else {
                // Variable line
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                if (!in_array($key, $seenKeys)) {
                    $seenKeys[] = $key;
                    $result[] = $line;
                }
            }
        }
        return $result;
    }

    // ------------------------------------------------------------
    // MQTT EDITOR (unchanged)
    // ------------------------------------------------------------

    public function mqttIndex()
    {
        return view('env.mqtt');
    }

    public function loadMqtt()
    {
        $path = $this->getEnvPath();

        if (!File::exists($path)) {
            return response()->json([
                'success' => false,
                'error'   => 'File not found',
                'path'    => $path,
            ]);
        }

        $fullContent = File::get($path);
        $lines = explode("\n", $fullContent);

        $mqttComment = '# ---- MQTT (Seismic) ----';

        $mqttLines = [];
        $inBlock = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === $mqttComment) {
                $inBlock = true;
                $mqttLines[] = $line;
                continue;
            }
            if ($inBlock) {
                if ($trimmed === '') {
                    $mqttLines[] = $line;
                    continue;
                }
                if (str_starts_with($trimmed, '#') && $trimmed !== $mqttComment) {
                    // Other comment – stop
                    break;
                }
                if (str_starts_with($trimmed, 'MQTT_')) {
                    $mqttLines[] = $line;
                    continue;
                }
                // Any other non‑empty line that doesn't start with MQTT_ or # -> stop
                if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                    break;
                }
                // fallback
                $mqttLines[] = $line;
            }
        }

        $deduped = $this->deduplicateBlock($mqttLines);
        $finalBlock = implode("\n", $deduped);

        return response()->json([
            'success' => true,
            'content' => $finalBlock,
        ]);
    }

    public function saveMqtt(Request $request)
    {
        $path = $this->getEnvPath();
        $newBlock = $request->input('content');

        if (!File::exists($path)) {
            return response()->json([
                'success' => false,
                'error'   => 'File not found',
            ]);
        }

        $fullContent = File::get($path);
        $lines = explode("\n", $fullContent);

        $mqttComment = '# ---- MQTT (Seismic) ----';

        $startIndex = null;
        foreach ($lines as $i => $line) {
            if (trim($line) === $mqttComment) {
                $startIndex = $i;
                break;
            }
        }

        if ($startIndex === null) {
            $newLines = $lines;
            if (!empty($newLines) && trim(end($newLines)) !== '') {
                $newLines[] = '';
            }
            $newLines[] = $newBlock;
            $newFull = implode("\n", $newLines);
            File::put($path, $newFull);
            return response()->json(['success' => true]);
        }

        $endIndex = $startIndex;
        for ($i = $startIndex + 1; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, 'MQTT_')) {
                $endIndex = $i;
            } else {
                break;
            }
        }

        $existingBlockLines = array_slice($lines, $startIndex, $endIndex - $startIndex + 1);
        $newBlockLines = explode("\n", $newBlock);

        $existingMap = [];
        foreach ($existingBlockLines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, '=') && !str_starts_with($trimmed, '#')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $existingMap[$key] = $line;
            }
        }

        $newMap = [];
        foreach ($newBlockLines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, '=') && !str_starts_with($trimmed, '#')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $newMap[$key] = $line;
            }
        }

        $updatedBlockLines = [];
        foreach ($existingBlockLines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '#')) {
                $updatedBlockLines[] = $line;
            } else {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                if (isset($newMap[$key])) {
                    $newLine = $newMap[$key];
                    $oldValue = isset($parts[1]) ? trim($parts[1]) : '';
                    $newValue = explode('=', $newLine, 2)[1] ?? '';
                    if (trim($oldValue) !== trim($newValue)) {
                        $updatedBlockLines[] = $newLine;
                    } else {
                        $updatedBlockLines[] = $line;
                    }
                } else {
                    $updatedBlockLines[] = $line;
                }
            }
        }

        $newFull = implode("\n", array_merge(
            array_slice($lines, 0, $startIndex),
            $updatedBlockLines,
            array_slice($lines, $endIndex + 1)
        ));

        if ($newFull === $fullContent) {
            return response()->json(['success' => true, 'message' => 'No changes needed']);
        }

        File::put($path, $newFull);
        return response()->json(['success' => true]);
    }
}