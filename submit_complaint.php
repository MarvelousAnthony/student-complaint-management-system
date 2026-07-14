<?php
require_once 'db_connect.php';

// Secure access: student role check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_name = $_SESSION['full_name'];
$csrf_token = get_csrf_token();

$error_msg = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File a Complaint | Student Complaint Management System</title>
    <link rel="icon" type="image/jpeg" href="favicon.jpg">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Theme overrides and dynamic theme switch -->
    <link rel="stylesheet" href="theme.css?v=1.3">
    <script src="theme_logout.js" defer></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex overflow-hidden">

    <!-- Sidebar -->
    <aside class="hidden">
        <div>
            <!-- Header Brand -->
            <div class="h-16 flex items-center px-6 border-b border-slate-800">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <span class="font-bold text-white tracking-wide">CMS Portal</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-6 px-4 space-y-1">
                <a href="student_dashboard.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    Dashboard
                </a>
                <a href="submit_complaint.php" class="bg-indigo-600 text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all shadow-lg shadow-indigo-600/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    File a Complaint
                </a>
                <a href="change_password.php" class="text-slate-400 hover:bg-slate-800 hover:text-white group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Security Settings
                </a>
            </nav>
        </div>

        <!-- Student Profile Footer Info -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/50">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 font-bold border border-slate-700">
                    <?php echo strtoupper(substr($student_name, 0, 2)); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($student_name); ?></p>
                    <p class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($_SESSION['matric_no_staff_id']); ?></p>
                </div>
            </div>
            <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden relative">
        
        <!-- Header -->
        <header class="h-16 border-b border-slate-800 bg-slate-950 flex items-center justify-between px-6 z-10">
            <!-- Mobile Menu Toggle -->
            <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            
            <div>
                <h1 class="text-lg font-bold text-white">File a New Complaint</h1>
            </div>

            <!-- Profile Info & Sign Out Actions -->
            <div class="flex items-center space-x-4">
                <span class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 text-indigo-400 rounded-full font-semibold uppercase tracking-wider hidden sm:inline-block">
                    Student Account
                </span>
                <a href="logout.php" class="text-xs font-semibold text-rose-450 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 px-3.5 py-1.5 rounded-xl transition-all flex items-center space-x-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Sign Out</span>
                </a>
            </div>
        </header>

        <!-- Mobile Drawer Navigation -->
        <div id="mobile-menu" class="fixed inset-0 z-30 bg-slate-950/80 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 flex">
            <!-- Dismiss overlay -->
            <div class="absolute inset-0" onclick="toggleMobileMenu()"></div>
            
            <div id="mobile-drawer-panel" class="relative w-64 bg-slate-900 h-full p-6 border-r border-slate-800 flex flex-col justify-between transform -translate-x-full transition-transform duration-300 ease-in-out z-10">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <span class="font-bold text-white">CMS Portal</span>
                        </div>
                        <button class="text-slate-400 hover:text-white" onclick="toggleMobileMenu()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <nav class="space-y-2">
                        <a href="student_dashboard.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Dashboard
                        </a>
                        <a href="submit_complaint.php" class="bg-indigo-600 text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl shadow-lg shadow-indigo-600/10">
                            File a Complaint
                        </a>
                        <a href="change_password.php" class="text-slate-400 hover:bg-slate-800 hover:text-white flex items-center px-4 py-3 text-sm font-semibold rounded-xl">
                            Security Settings
                        </a>
                    </nav>
                </div>
                <div class="border-t border-slate-800 pt-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 font-bold border border-slate-700">
                            <?php echo strtoupper(substr($student_name, 0, 2)); ?>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($student_name); ?></p>
                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($_SESSION['matric_no_staff_id']); ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="w-full flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 rounded-xl transition-all">
                        Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Scrollable Form Content -->
        <main class="flex-1 overflow-y-auto p-6 relative">
            <div class="max-w-3xl mx-auto w-full">
                
                <!-- Alert Display -->
                <?php if ($error_msg): ?>
                    <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Client-side Validation Error Placeholder -->
                <div id="js-error-alert" class="hidden mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span id="js-error-message"></span>
                </div>

                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl shadow-2xl backdrop-blur-xl p-6 sm:p-8">
                    <form id="complaint-form" action="process_complaint.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-semibold text-slate-300">Complaint Title</label>
                            <p class="text-xs text-slate-500 mb-2">Provide a brief, descriptive summary of the issue.</p>
                            <input id="title" name="title" type="text" required
                                class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                                placeholder="e.g., Portal login error on payment confirmation page">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Category -->
                            <div>
                                <label for="category" class="block text-sm font-semibold text-slate-300">Category</label>
                                <p class="text-xs text-slate-500 mb-2">Select the department/functional area concerned.</p>
                                <div class="relative">
                                    <select id="category" name="category" required
                                        class="appearance-none block w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                        <option value="" disabled selected>Select Category</option>
                                        <option value="Academics">Academics</option>
                                        <option value="Bursary / Fees">Bursary / Fees</option>
                                        <option value="Accommodation / Hostels">Accommodation / Hostels</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Information Technology (ICT)">Information Technology (ICT)</option>
                                        <option value="Medical / Health Center">Medical / Health Center</option>
                                        <option value="Cafeteria">Cafeteria</option>
                                        <option value="Chapel / Spiritual Life">Chapel / Spiritual Life</option>
                                        <option value="Security & Welfare">Security & Welfare</option>
                                        <option value="Library Services">Library Services</option>
                                        <option value="Other / General">Other / General</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                                <!-- AI Suggestion Badge -->
                                <div id="ai-suggestion-container" class="hidden mt-2 flex items-center space-x-2">
                                    <span class="text-xs text-slate-500 flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        AI Suggested:
                                    </span>
                                    <button type="button" id="ai-suggestion-badge" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-500/10 text-violet-400 border border-violet-500/25 hover:bg-violet-500/20 active:scale-[0.98] transition-all cursor-pointer">
                                        <span>Portal Issues</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Priority -->
                            <div>
                                <label for="priority" class="block text-sm font-semibold text-slate-300">Priority Level</label>
                                <p class="text-xs text-slate-500 mb-2">How urgent is this issue to your academics?</p>
                                <div class="relative">
                                    <select id="priority" name="priority" required
                                        class="appearance-none block w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                        <option value="low">Low (General inquiry/suggestion)</option>
                                        <option value="medium" selected>Medium (Standard issue, requires attention)</option>
                                        <option value="high">High (Critical/Urgent blockage)</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-semibold text-slate-300">Detailed Description</label>
                            <p class="text-xs text-slate-500 mb-2">Explain the details clearly. Include steps to reproduce if it is a technical bug.</p>
                            <textarea id="description" name="description" rows="6" required
                                class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                                placeholder="Describe what happened, what you expected, and any error message you received..."></textarea>
                        </div>

                        <!-- Attachment -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-300">Attachment / Screenshot</label>
                            <p class="text-xs text-slate-500 mb-2">Optional. PNG, JPG, or PDF. Max size: 5MB.</p>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-800 border-dashed rounded-xl bg-slate-950/40 hover:bg-slate-950/60 transition-all relative">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-slate-500" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20a4 4 0 004 4h16a4 4 0 004-4V12a4 4 0 00-4-4z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M14 29l7-7 7 7M26 23l3-3 7 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-slate-400 justify-center">
                                        <label for="attachment" class="relative cursor-pointer rounded-md font-semibold text-indigo-400 hover:text-indigo-300 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500 transition-colors">
                                            <span>Upload a file</span>
                                            <input id="attachment" name="attachment" type="file" class="sr-only">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-slate-500" id="file-name-display">No file selected</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end space-x-4 border-t border-slate-800 pt-6">
                            <a href="student_dashboard.php" class="px-5 py-3 rounded-xl border border-slate-800 hover:bg-slate-800 text-sm font-semibold text-slate-300 transition-colors active:scale-[0.98]">
                                Cancel
                            </a>
                            <button type="submit" 
                                class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-semibold transition-all active:scale-[0.98]">
                                Submit Complaint
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Interactive JS scripts -->
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const panel = document.getElementById('mobile-drawer-panel');
            if (menu.classList.contains('pointer-events-none')) {
                menu.classList.remove('pointer-events-none', 'opacity-0');
                menu.classList.add('pointer-events-auto', 'opacity-100');
                panel.classList.remove('-translate-x-full');
                panel.classList.add('translate-x-0');
            } else {
                menu.classList.remove('pointer-events-auto', 'opacity-100');
                menu.classList.add('pointer-events-none', 'opacity-0');
                panel.classList.remove('translate-x-0');
                panel.classList.add('-translate-x-full');
            }
        }

        // Show selected file name in drag area
        const fileInput = document.getElementById('attachment');
        const fileNameDisplay = document.getElementById('file-name-display');
        
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                fileNameDisplay.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                fileNameDisplay.classList.remove('text-slate-500');
                fileNameDisplay.classList.add('text-indigo-400', 'font-semibold');
            } else {
                fileNameDisplay.textContent = 'No file selected';
                fileNameDisplay.classList.add('text-slate-500');
                fileNameDisplay.classList.remove('text-indigo-400', 'font-semibold');
            }
        });

        // Client Side Form Validation
        document.getElementById('complaint-form').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const category = document.getElementById('category').value;
            const priority = document.getElementById('priority').value;
            const description = document.getElementById('description').value.trim();
            
            const errorAlert = document.getElementById('js-error-alert');
            const errorMessage = document.getElementById('js-error-message');
            
            let errors = [];

            if (!title || title.length < 5) {
                errors.push('Title is required and must be at least 5 characters.');
            }

            if (!category) {
                errors.push('Please select a valid category.');
            }

            if (!priority) {
                errors.push('Please select a priority level.');
            }

            if (!description || description.length < 15) {
                errors.push('Description is required and must detail the issue (at least 15 characters).');
            }

            // File validation
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                const maxSize = 5 * 1024 * 1024; // 5MB

                if (!allowedExtensions.includes(fileExtension)) {
                    errors.push('Attachment file type invalid. Only JPG, PNG, or PDF files are allowed.');
                }

                if (file.size > maxSize) {
                    errors.push('Attachment file is too large. Maximum size is 5MB.');
                }
            }

            if (errors.length > 0) {
                e.preventDefault();
                errorMessage.textContent = errors.join(' ');
                errorAlert.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                errorAlert.classList.add('hidden');
            }
        });

        // AI Rule-Based Client-Side NLP Categorization
        const descriptionInput = document.getElementById('description');
        const categorySelect = document.getElementById('category');
        const aiContainer = document.getElementById('ai-suggestion-container');
        const aiBadge = document.getElementById('ai-suggestion-badge');

        const rules = [
            { category: 'Academics', keywords: ['grade', 'lecturer', 'exam', 'result', 'transcript', 'gpa', 'cgpa', 'semester', 'test', 'score', 'carryover', 'carry-over', 'missing', 'course', 'registration', 'add', 'drop'] },
            { category: 'Bursary / Fees', keywords: ['payment', 'fees', 'remita', 'receipt', 'tuition', 'bursary', 'charge', 'invoice', 'paid', 'transaction', 'bank', 'teller', 'refunding', 'refund', 'clearance'] },
            { category: 'Accommodation / Hostels', keywords: ['hostel', 'room', 'accommodation', 'bedspace', 'squat', 'hall', 'residence', 'bunk', 'porter', 'block', 'warden'] },
            { category: 'Maintenance', keywords: ['repairs', 'facilities', 'maintenance', 'light', 'water', 'fan', 'ac', 'electricity', 'classroom', 'broken', 'leak', 'toilet'] },
            { category: 'Information Technology (ICT)', keywords: ['login', 'portal', 'password', 'reset', 'profile', 'credentials', 'account', 'signin', 'sign-in', 'log-in', 'ict', 'wifi', 'internet', 'network', 'email', 'computer', 'server', 'connection', 'offline', 'ethernet'] },
            { category: 'Medical / Health Center', keywords: ['sick', 'ill', 'clinic', 'hospital', 'doctor', 'nurse', 'medical', 'drugs', 'health', 'center', 'treatment', 'medication', 'first-aid'] },
            { category: 'Cafeteria', keywords: ['food', 'eat', 'canteen', 'cafeteria', 'catering', 'meal', 'rice', 'beans', 'chicken', 'cook', 'hygiene', 'pricing', 'spoil'] },
            { category: 'Chapel / Spiritual Life', keywords: ['chapel', 'church', 'spiritual', 'pastor', 'chaplain', 'worship', 'service', 'attendance', 'singing', 'devotional'] },
            { category: 'Security & Welfare', keywords: ['security', 'theft', 'stolen', 'fight', 'harassment', 'threat', 'gate', 'guard', 'officer', 'police', 'abuse', 'bully'] },
            { category: 'Library Services', keywords: ['book', 'library', 'borrow', 'card', 'return', 'fine', 'textbook', 'journal', 'shelf', 'librarian'] }
        ];

        let lastSuggestedCategory = null;

        descriptionInput.addEventListener('input', function() {
            const text = this.value.toLowerCase();
            if (text.length < 5) {
                aiContainer.classList.add('hidden');
                return;
            }

            let categoryScores = {};
            rules.forEach(rule => {
                categoryScores[rule.category] = 0;
                rule.keywords.forEach(keyword => {
                    // count matches
                    const regex = new RegExp(keyword, 'gi');
                    const count = (text.match(regex) || []).length;
                    categoryScores[rule.category] += count;
                });
            });

            // Find category with highest score > 0
            let bestCategory = null;
            let highestScore = 0;
            for (const cat in categoryScores) {
                if (categoryScores[cat] > highestScore) {
                    highestScore = categoryScores[cat];
                    bestCategory = cat;
                }
            }

            // Only suggest if dropdown is not already set to this category
            if (bestCategory && categorySelect.value !== bestCategory) {
                lastSuggestedCategory = bestCategory;
                aiBadge.querySelector('span').textContent = bestCategory;
                aiContainer.classList.remove('hidden');
            } else {
                aiContainer.classList.add('hidden');
                lastSuggestedCategory = null;
            }
        });

        // Handle suggestion click
        aiBadge.addEventListener('click', function() {
            if (lastSuggestedCategory) {
                categorySelect.value = lastSuggestedCategory;
                aiContainer.classList.add('hidden');
                // Trigger flash highlight style on category select
                categorySelect.classList.add('ring-2', 'ring-indigo-500');
                setTimeout(() => {
                    categorySelect.classList.remove('ring-2', 'ring-indigo-500');
                }, 1000);
            }
        });

        // Hide badge if admin manually selects a category matching suggestion
        categorySelect.addEventListener('change', function() {
            if (this.value === lastSuggestedCategory) {
                aiContainer.classList.add('hidden');
            }
        });

    </script>
</body>
</html>
