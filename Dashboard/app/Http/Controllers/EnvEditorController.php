<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EnvEditorController extends Controller
{
    private function getEnvPath()
    {
        return 'C:/Users/JUREEN/Desktop/Emergency-Warning-System-main/.env';
    }

    public function index()
    {
        return view('env.db-editor');
    }

    /**
     * Load the block, deduplicate variables, and return a clean block.
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

        $airComment = '# ---- Air Quality Database ----';
        $seismicComment = '# ---- Seismic Database ----';

        // Collect only the lines that belong to the two blocks
        $airLines = [];
        $seismicLines = [];
        $currentBlock = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === $airComment) {
                $currentBlock = 'air';
                $airLines[] = $line;
                continue;
            }
            if ($trimmed === $seismicComment) {
                $currentBlock = 'seismic';
                $seismicLines[] = $line;
                continue;
            }
            if ($currentBlock === 'air' && str_starts_with($trimmed, 'AQ_DB_')) {
                $airLines[] = $line;
            } elseif ($currentBlock === 'seismic' && str_starts_with($trimmed, 'SEISMIC_DB_')) {
                $seismicLines[] = $line;
            }
        }

        // Deduplicate variables in each block (keep first occurrence)
        $airDeduped = $this->deduplicateBlock($airLines);
        $seismicDeduped = $this->deduplicateBlock($seismicLines);

        $finalBlock = implode("\n", array_merge($airDeduped, $seismicDeduped));

        return response()->json([
            'success' => true,
            'content' => $finalBlock,
        ]);
    }

    /**
     * Helper to deduplicate variables inside a block (comment + variable lines).
     * Keeps the comment and the first occurrence of each variable key.
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

    /**
     * Save the block by **replacing** the entire block – never duplicates.
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

        $fullContent = File::get($path);
        $lines = explode("\n", $fullContent);

        $airComment = '# ---- Air Quality Database ----';
        $seismicComment = '# ---- Seismic Database ----';

        // Find start and end indices of the block
        $startIndex = null;
        $endIndex = null;
        foreach ($lines as $i => $line) {
            if (trim($line) === $airComment) {
                $startIndex = $i;
            }
            if (trim($line) === $seismicComment) {
                $endIndex = $i;
            }
        }

        // If start comment missing, append new block at the end
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

        // Determine the end of the block
        if ($endIndex === null) {
            $endIndex = count($lines) - 1;
        } elseif ($endIndex < $startIndex) {
            $endIndex = $startIndex;
        }

        // Extract the existing block (from startIndex to endIndex inclusive)
        $existingBlockLines = array_slice($lines, $startIndex, $endIndex - $startIndex + 1);
        $newBlockLines = explode("\n", $newBlock);

        // Build a mapping of existing variable lines (key => full line)
        $existingMap = [];
        foreach ($existingBlockLines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, '=') && !str_starts_with($trimmed, '#')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $existingMap[$key] = $line;
            }
        }

        // Build a mapping of new variable lines (key => full line)
        $newMap = [];
        foreach ($newBlockLines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, '=') && !str_starts_with($trimmed, '#')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $newMap[$key] = $line;
            }
        }

        // Create the updated block by preserving the existing order and only changing values
        $updatedBlockLines = [];
        foreach ($existingBlockLines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '#')) {
                // Comment lines – keep as is
                $updatedBlockLines[] = $line;
            } else {
                // Variable line – check if it exists in newMap and if value differs
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                if (isset($newMap[$key])) {
                    // Replace only if the value is different
                    $newLine = $newMap[$key];
                    $oldValue = isset($parts[1]) ? trim($parts[1]) : '';
                    $newValue = explode('=', $newLine, 2)[1] ?? '';
                    if (trim($oldValue) !== trim($newValue)) {
                        $updatedBlockLines[] = $newLine;
                    } else {
                        $updatedBlockLines[] = $line; // keep original
                    }
                } else {
                    // If key not in new block, keep original (shouldn't happen)
                    $updatedBlockLines[] = $line;
                }
            }
        }

        // Reconstruct the full file with the updated block
        $newFull = implode("\n", array_merge(
            array_slice($lines, 0, $startIndex),
            $updatedBlockLines,
            array_slice($lines, $endIndex + 1)
        ));

        // Only write if there is an actual change (to avoid unnecessary writes)
        if ($newFull === $fullContent) {
            return response()->json(['success' => true, 'message' => 'No changes needed']);
        }

        File::put($path, $newFull);
        return response()->json(['success' => true]);
    }


    
    /**
     * Show the MQTT editor view.
     */
    public function mqttIndex()
    {
        return view('env.mqtt');
    }

    /**
     * Load the MQTT block from .env
     */
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

        // Collect only the lines belonging to the MQTT block
        $mqttLines = [];
        $inBlock = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === $mqttComment) {
                $inBlock = true;
                $mqttLines[] = $line;
                continue;
            }
            if ($inBlock && str_starts_with($trimmed, 'MQTT_')) {
                $mqttLines[] = $line;
            } elseif ($inBlock && $trimmed === '') {
                // Stop if we hit a blank line after the block? We'll keep until a non-MQTT line.
                // But we want to stop when we hit a line that doesn't start with MQTT and is not blank?
                // Simpler: we collect until we hit a line that is not a comment and not starting with MQTT.
                // We'll break if we encounter a non-empty line that doesn't start with MQTT and isn't a comment.
                if (!str_starts_with($trimmed, '#') && !str_starts_with($trimmed, 'MQTT_')) {
                    break;
                }
                // If blank line, we might include it or not? We'll include it only if it's within the block.
                // But we want to preserve the block exactly. We'll keep all lines until we detect a non-MQTT non-comment line.
                // To be safe, we'll just collect all lines between the comment and the next non-MQTT non-empty line.
                // However, we can use a simpler approach: search for the comment and then collect until we hit a line that doesn't start with MQTT_ and is not a comment.
                // But the comment is one line, then variables. There might be blank lines. We'll keep them.
                // Let's adjust: we'll include all lines that are either the comment, blank, or start with MQTT_.
                // Since we don't know where the block ends, we'll assume it ends at the next line that is not a comment and not MQTT_ or a blank line.
                // For safety, we'll stop at the first line that is not empty, not a comment, and doesn't start with MQTT_.
                if (!str_starts_with($trimmed, '#') && !str_starts_with($trimmed, 'MQTT_') && $trimmed !== '') {
                    break;
                }
                $mqttLines[] = $line;
            } elseif ($inBlock) {
                // If we're in block and the line is not a comment and not MQTT_, stop.
                if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                    break;
                }
                // if blank or comment, include it
                $mqttLines[] = $line;
            }
        }

        // Deduplicate variables (keep first occurrence)
        $deduped = $this->deduplicateBlock($mqttLines);

        $finalBlock = implode("\n", $deduped);

        return response()->json([
            'success' => true,
            'content' => $finalBlock,
        ]);
    }

    /**
     * Save the MQTT block (replace existing block).
     */
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

        // Find start index of the MQTT block
        $startIndex = null;
        foreach ($lines as $i => $line) {
            if (trim($line) === $mqttComment) {
                $startIndex = $i;
                break;
            }
        }

        // If comment not found, append new block at the end
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

        // Find the end of the block: scan from startIndex+1 until we hit a line that is not a comment, not blank, and not starting with MQTT_
        $endIndex = $startIndex;
        for ($i = $startIndex + 1; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, 'MQTT_')) {
                $endIndex = $i;
            } else {
                break;
            }
        }

        // Extract existing block
        $existingBlockLines = array_slice($lines, $startIndex, $endIndex - $startIndex + 1);
        $newBlockLines = explode("\n", $newBlock);

        // Build mapping of existing variables (key => full line)
        $existingMap = [];
        foreach ($existingBlockLines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, '=') && !str_starts_with($trimmed, '#')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $existingMap[$key] = $line;
            }
        }

        // Build mapping of new variables (key => full line)
        $newMap = [];
        foreach ($newBlockLines as $line) {
            $trimmed = trim($line);
            if (str_contains($trimmed, '=') && !str_starts_with($trimmed, '#')) {
                $parts = explode('=', $line, 2);
                $key = trim($parts[0]);
                $newMap[$key] = $line;
            }
        }

        // Update block: preserve order and only change values
        $updatedBlockLines = [];
        foreach ($existingBlockLines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '#')) {
                // Keep comment lines as is
                $updatedBlockLines[] = $line;
            } else {
                // Variable line: check if in newMap and value differs
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
                    // Key not in new block (shouldn't happen) – keep original
                    $updatedBlockLines[] = $line;
                }
            }
        }

        // Reconstruct full file
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