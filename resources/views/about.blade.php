<!DOCTYPE html>
<html lang="en" class="dark" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Me - Eren Ilhan</title>
    
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
    <div class="max-w-3xl mx-auto px-4 py-8">
        <header class="flex justify-between items-center py-4 border-b border-dark-700 mb-8">
            <h1 class="text-2xl font-bold text-white">About Me</h1>
            <a href="/trade-dashboard" class="text-blue-400 hover:text-blue-300">← Back to Dashboard</a>
        </header>
        
        <div class="bg-dark-800 rounded-lg p-8">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold text-white mb-6">Eren Ilhan</h2>
                <p class="text-xl text-gray-300 mb-8">Cryptocurrency Trading Bot Developer</p>
            </div>
            
            <div class="space-y-6">
                <div class="flex items-center justify-between p-4 bg-dark-700/50 rounded-lg">
                    <span class="text-gray-400">Website:</span>
                    <a href="https://erenilhan.com" target="_blank" class="text-blue-400 hover:text-blue-300 transition-colors">
                        erenilhan.com
                    </a>
                </div>
                
                <div class="flex items-center justify-between p-4 bg-dark-700/50 rounded-lg">
                    <span class="text-gray-400">Phone:</span>
                    <a href="tel:+905069115526" class="text-blue-400 hover:text-blue-300 transition-colors">
                        +90 506 911 55 26
                    </a>
                </div>
                
                <div class="flex items-center justify-between p-4 bg-dark-700/50 rounded-lg">
                    <span class="text-gray-400">Email:</span>
                    <a href="mailto:erenilhan1@gmail.com" class="text-blue-400 hover:text-blue-300 transition-colors">
                        erenilhan1@gmail.com
                    </a>
                </div>
            </div>
            
            <div class="mt-12 text-center">
                <h3 class="text-xl font-semibold text-white mb-4">About This Project</h3>
                <p class="text-gray-300 max-w-2xl mx-auto">
                    This cryptocurrency trading bot is designed to automate trading decisions on Binance Futures
                    using artificial intelligence. The system includes multi-coin trading, risk management,
                    and comprehensive monitoring capabilities.
                </p>
            </div>
        </div>
    </div>
</body>
</html>