<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
        * {
            font-family: "SF Mono", "Monaco", "Cascadia Code", "Fira Code",
                        "DejaVu Sans Mono", "Liberation Mono", monospace;
        }
        input { font-family: "SF Mono", "Monaco", "Cascadia Code", "Fira Code",
                        "DejaVu Sans Mono", "Liberation Mono", monospace; }
        .label-mono { font-family: "SF Mono", "Monaco", "Cascadia Code", "Fira Code",
                        "DejaVu Sans Mono", "Liberation Mono", monospace; font-weight: 500; }
        .thin-scrollbar::-webkit-scrollbar { width: 5px; }
        .thin-scrollbar::-webkit-scrollbar-track { background: #e1e4e7; border-radius: 10px; }
        .thin-scrollbar::-webkit-scrollbar-thumb { background: #ababab; border-radius: 10px; }
        .thin-scrollbar { scrollbar-width: thin; scrollbar-color: #ababab #f1f5f9; }
        input.changed {
            border-color: #f59e0b;
            background-color: #fffbeb;
        }

        /* Hide scrollbar but keep functionality */
        #notification-list {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }
        #notification-list::-webkit-scrollbar {
            display: none; /* Chrome/Safari/Opera */
        }

        /* Highlight row when coming from notification */
        tr.highlight-row {
            background: rgba(34, 197, 94, 0.15) !important;
            border-left: 3px solid #22c55e;
            animation: highlightFade 3s ease-in-out;
        }
        @keyframes highlightFade {
            0% { background: rgba(34, 197, 94, 0.4); }
            100% { background: rgba(34, 197, 94, 0.15); }
        }
        
    </style>
    
    <!-- Optimized MuntI Theme -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        /* ==========================
                        * Accent
                        * ========================== */
                        'munti-yellow': {
                            600: '#E5A500',
                            500: '#FFB702',
                            400: '#FCCC3D',
                            300: '#FFD966',
                        },

                        'munti-orange': {
                            600: '#D62B2B',
                            500: '#F97316',
                            400: '#FB923C',
                        },

                        /* ==========================
                        * Status
                        * ========================== */
                        'munti-red': {
                            700: '#B91C1C',
                            600: '#E2261B',
                            500: '#EF4444',
                            400: '#F87171',
                        },

                        'munti-green': {
                            700: '#3F6212',
                            600: '#65A30D',
                            500: '#84CC16',
                            400: '#A3E635',
                            300: '#D9F99D',
                        },

                        /* ==========================
                        * Radar / Military Green
                        * ========================== */
                        'radar': {
                            950: '#020B08',
                            900: '#04120E',
                            800: '#06221A',
                            700: '#083528',
                            600: '#0B4F3A',
                            500: '#0F766E',
                            400: '#14B8A6',
                            300: '#5EEAD4',
                        },

                        /* ==========================
                        * Background
                        * ========================== */
                        'background': {
                            950: '#080808',
                            900: '#101010',
                            800: '#18181B',
                            700: '#27272A',
                        },

                        /* ==========================
                        * Surface / Cards
                        * ========================== */
                        'surface': {
                            900: '#121212',
                            800: '#1A1A1A',
                            700: '#242424',
                            600: '#0a0a0a',
                        },

                        /* ==========================
                        * Borders
                        * ========================== */
                        'border': {
                            900: '#1F2937',
                            800: '#2B3442',
                            700: '#374151',
                            600: '#4B5563',
                        },

                        /* ==========================
                        * Text
                        * ========================== */
                        'text': {
                            100: '#FFFFFF',
                            200: '#F3F4F6',
                            300: '#E5E7EB',
                            400: '#9CA3AF',
                            500: '#6B7280',
                        },

                        /* ==========================
                        * Existing
                        * ========================== */
                        'munti-white': {
                            100: '#E0E0E0',
                        },

                        'munti-black': {
                            950: '#080808',
                        },
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-surface-600 min-h-screen flex items-center justify-center">