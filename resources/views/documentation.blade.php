<!DOCTYPE html>
<html lang="en" class="dark" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation - Trading Bot</title>
    
    <!-- Include Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Enable dark mode support -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#0a0e27',
                            800: '#18181b',
                            700: '#27272a',
                            600: '#3f3f46',
                            500: '#52525b',
                            400: '#a1a1aa',
                        }
                    }
                }
            }
        }
    </script>
    
    <style type="text/tailwindcss">
        @layer base {
            body {
                @apply transition-colors duration-300;
            }
        }
    </style>
</head>
<body class="bg-dark-900 text-gray-200 min-h-screen transition-colors duration-300">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <header class="flex justify-between items-center py-4 border-b border-dark-700 mb-8">
            <h1 class="text-2xl font-bold text-white">Documentation</h1>
            <a href="/trade-dashboard" class="text-blue-400 hover:text-blue-300">← Back to Dashboard</a>
        </header>
        
        <div class="prose prose-invert max-w-none">
            <div class="bg-dark-800 rounded-lg p-6">
                {!! $readmeContent !!}
            </div>
        </div>
    </div>

    <script>
        // Loading documentation on the server-side using PHP
        // This should be handled server-side to avoid any fetch issues
    </script>
</body>
</html>