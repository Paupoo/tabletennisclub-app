<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - Ace Table Tennis Club</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'club-blue': '#1e40af',
                        'club-yellow': '#fbbf24',
                        'club-blue-light': '#3b82f6',
                        'club-yellow-light': '#fcd34d'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900" x-data="{ mobileMenuOpen: false, selectedSeason: '2024' }">
    <!-- Navigation -->
    <nav class="bg-white shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="index.html" class="shrink-0">
                        <h1 class="text-2xl font-bold text-club-blue">🏓 Ace TTC</h1>
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="index.html" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors">Home</a>
                        <a href="results.html" class="text-club-blue px-3 py-2 rounded-md text-sm font-medium">Results</a>
                        <a href="events.html" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors">Events</a>
                        <a href="index.html#contact" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors">Contact</a>
                    </div>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-900 hover:text-club-blue">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden bg-white border-t">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="index.html" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Home</a>
                <a href="results.html" class="block text-club-blue px-3 py-2 rounded-md text-base font-medium">Results</a>
                <a href="events.html" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Events</a>
                <a href="index.html#contact" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Contact</a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="bg-linear-to-r from-club-blue to-club-blue-light text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Competition Results</h1>
            <p class="text-xl opacity-90">Track our teams' performance across all competitions</p>
        </div>
    </div>

    <!-- Season Filter -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-2xl font-bold">Team Results</h2>
            <div class="flex items-center gap-2">
                <label for="season" class="text-sm font-medium">Season:</label>
                <select x-model="selectedSeason" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Results Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <!-- Team A Results -->
        <div class="mb-12">
            <div class="bg-white rounded-lg shadow-xs border p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-club-blue">Team A - Premier Division</h3>
                    <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                        2nd Place
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-semibold">Date</th>
                                <th class="text-left py-3 px-4 font-semibold">Opponent</th>
                                <th class="text-left py-3 px-4 font-semibold">Home/Away</th>
                                <th class="text-left py-3 px-4 font-semibold">Score</th>
                                <th class="text-left py-3 px-4 font-semibold">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Dec 15, 2024</td>
                                <td class="py-3 px-4">Thunder TTC</td>
                                <td class="py-3 px-4">Home</td>
                                <td class="py-3 px-4 font-mono">8-2</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Dec 8, 2024</td>
                                <td class="py-3 px-4">Elite Paddles</td>
                                <td class="py-3 px-4">Away</td>
                                <td class="py-3 px-4 font-mono">6-4</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Dec 1, 2024</td>
                                <td class="py-3 px-4">Spin Masters</td>
                                <td class="py-3 px-4">Home</td>
                                <td class="py-3 px-4 font-mono">3-7</td>
                                <td class="py-3 px-4">
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded-sm text-sm font-medium">Loss</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Nov 24, 2024</td>
                                <td class="py-3 px-4">Rapid Rackets</td>
                                <td class="py-3 px-4">Away</td>
                                <td class="py-3 px-4 font-mono">7-3</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-club-blue">12</div>
                        <div class="text-sm text-gray-600">Matches Played</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">9</div>
                        <div class="text-sm text-gray-600">Wins</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">3</div>
                        <div class="text-sm text-gray-600">Losses</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-club-yellow">75%</div>
                        <div class="text-sm text-gray-600">Win Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team B Results -->
        <div class="mb-12">
            <div class="bg-white rounded-lg shadow-xs border p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-club-blue">Team B - Division 1</h3>
                    <div class="bg-club-yellow text-club-blue px-3 py-1 rounded-full text-sm font-medium">
                        1st Place
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-semibold">Date</th>
                                <th class="text-left py-3 px-4 font-semibold">Opponent</th>
                                <th class="text-left py-3 px-4 font-semibold">Home/Away</th>
                                <th class="text-left py-3 px-4 font-semibold">Score</th>
                                <th class="text-left py-3 px-4 font-semibold">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Dec 14, 2024</td>
                                <td class="py-3 px-4">City Spinners</td>
                                <td class="py-3 px-4">Home</td>
                                <td class="py-3 px-4 font-mono">9-1</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Dec 7, 2024</td>
                                <td class="py-3 px-4">Paddle Power</td>
                                <td class="py-3 px-4">Away</td>
                                <td class="py-3 px-4 font-mono">8-2</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Nov 30, 2024</td>
                                <td class="py-3 px-4">Net Ninjas</td>
                                <td class="py-3 px-4">Home</td>
                                <td class="py-3 px-4 font-mono">7-3</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Nov 23, 2024</td>
                                <td class="py-3 px-4">Smash Squad</td>
                                <td class="py-3 px-4">Away</td>
                                <td class="py-3 px-4 font-mono">6-4</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-club-blue">10</div>
                        <div class="text-sm text-gray-600">Matches Played</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">10</div>
                        <div class="text-sm text-gray-600">Wins</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">0</div>
                        <div class="text-sm text-gray-600">Losses</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-club-yellow">100%</div>
                        <div class="text-sm text-gray-600">Win Rate</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team C Results -->
        <div class="mb-12">
            <div class="bg-white rounded-lg shadow-xs border p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-club-blue">Team C - Division 2</h3>
                    <div class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-medium">
                        4th Place
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-semibold">Date</th>
                                <th class="text-left py-3 px-4 font-semibold">Opponent</th>
                                <th class="text-left py-3 px-4 font-semibold">Home/Away</th>
                                <th class="text-left py-3 px-4 font-semibold">Score</th>
                                <th class="text-left py-3 px-4 font-semibold">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Dec 13, 2024</td>
                                <td class="py-3 px-4">Rookie Rackets</td>
                                <td class="py-3 px-4">Home</td>
                                <td class="py-3 px-4 font-mono">5-5</td>
                                <td class="py-3 px-4">
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-sm text-sm font-medium">Draw</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Dec 6, 2024</td>
                                <td class="py-3 px-4">Junior Jaguars</td>
                                <td class="py-3 px-4">Away</td>
                                <td class="py-3 px-4 font-mono">7-3</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Nov 29, 2024</td>
                                <td class="py-3 px-4">Rising Stars</td>
                                <td class="py-3 px-4">Home</td>
                                <td class="py-3 px-4 font-mono">4-6</td>
                                <td class="py-3 px-4">
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded-sm text-sm font-medium">Loss</span>
                                </td>
                            </tr>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4">Nov 22, 2024</td>
                                <td class="py-3 px-4">Future Champs</td>
                                <td class="py-3 px-4">Away</td>
                                <td class="py-3 px-4 font-mono">6-4</td>
                                <td class="py-3 px-4">
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-sm text-sm font-medium">Win</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-club-blue">11</div>
                        <div class="text-sm text-gray-600">Matches Played</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">6</div>
                        <div class="text-sm text-gray-600">Wins</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">4</div>
                        <div class="text-sm text-gray-600">Losses</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-club-yellow">55%</div>
                        <div class="text-sm text-gray-600">Win Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-2xl font-bold mb-4">🏓 Ace TTC</h3>
                    <p class="text-gray-400 mb-4">
                        Your premier destination for table tennis excellence. Join our community today!
                    </p>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="index.html" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="results.html" class="text-gray-400 hover:text-white transition-colors">Results</a></li>
                        <li><a href="events.html" class="text-gray-400 hover:text-white transition-colors">Events</a></li>
                        <li><a href="index.html#contact" class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Contact Info</h4>
                    <div class="space-y-2 text-gray-400">
                        <p>📍 123 Sports Center Ave</p>
                        <p>📞 (555) 123-4567</p>
                        <p>✉️ info@acettc.com</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 Ace Table Tennis Club. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
