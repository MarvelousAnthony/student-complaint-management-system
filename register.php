<?php
require_once 'db_connect.php';

// If user is already logged in, redirect them to their respective dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit();
}

$csrf_token = get_csrf_token();

// Fetch session messages
$error_msg = $_SESSION['error'] ?? null;
$success_msg = $_SESSION['success'] ?? null;

// Clear session messages so they don't persist on refresh
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | Student Complaint Management System</title>
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
<body class="h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 text-slate-100 relative overflow-x-hidden">
    
    <!-- Background Decorative Elements -->
    <div class="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-indigo-500/10 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[600px] h-[600px] rounded-full bg-violet-600/10 blur-[130px] pointer-events-none"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md z-10">
        <div class="flex justify-center">
            <!-- Icon / Logo representation -->
            <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 shadow-lg shadow-indigo-500/25">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold tracking-tight text-white">Create your student account</h2>
        <p class="mt-2 text-center text-sm text-slate-400">
            Or
            <a href="login.php" class="font-medium text-indigo-400 hover:text-indigo-300 transition-colors">
                sign in to your existing account
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-xl z-10">
        <div class="bg-slate-900/60 backdrop-blur-xl py-8 px-4 border border-slate-800 rounded-2xl shadow-2xl sm:px-10">
            
            <!-- Alert Display -->
            <?php if ($error_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Client-side Validation Error Placeholder -->
            <div id="js-error-alert" class="hidden mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-start space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span id="js-error-message"></span>
            </div>

            <form id="registration-form" action="auth_process.php" method="POST" class="space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <!-- Action Parameter -->
                <input type="hidden" name="action" value="register">

                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                    <!-- Full Name -->
                    <div class="sm:col-span-2">
                        <label for="full_name" class="block text-sm font-medium text-slate-300">Full Name</label>
                        <div class="mt-1">
                            <input id="full_name" name="full_name" type="text" autocomplete="name" required 
                                class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                                placeholder="John Doe">
                        </div>
                    </div>

                    <!-- Matric Number -->
                    <div>
                        <label for="matric_no" class="block text-sm font-medium text-slate-300">Matric Number</label>
                        <div class="mt-1">
                            <input id="matric_no" name="matric_no" type="text" required 
                                class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                                placeholder="e.g., 22/1314">
                        </div>
                    </div>

                    <!-- Faculty / College -->
                    <div>
                        <label for="faculty" class="block text-sm font-medium text-slate-300">Faculty / College</label>
                        <div class="mt-1 relative">
                            <select id="faculty" name="faculty" required 
                                class="appearance-none block w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                <option value="" disabled selected class="text-slate-500">Select Faculty / College</option>
                                <option value="College of Medicine">College of Medicine</option>
                                <option value="Basic Medical Sciences">Basic Medical Sciences</option>
                                <option value="Pharmacy">Pharmacy</option>
                                <option value="Arts">Arts</option>
                                <option value="Sciences">Sciences</option>
                                <option value="Business & Social Sciences">Business & Social Sciences</option>
                                <option value="Engineering">Engineering</option>
                                <option value="Law">Law</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Department -->
                    <div>
                        <label for="department" class="block text-sm font-medium text-slate-300">Department</label>
                        <div class="mt-1 relative">
                            <select id="department" name="department" required disabled
                                class="appearance-none block w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all">
                                <option value="" disabled selected class="text-slate-500">Please select Faculty first</option>
                                <!-- College of Medicine -->
                                <option value="Medicine and Surgery (MBBS)" data-faculty="College of Medicine">Medicine and Surgery (MBBS)</option>
                                <!-- Basic Medical Sciences -->
                                <option value="Anatomy" data-faculty="Basic Medical Sciences">Anatomy</option>
                                <option value="Physiology" data-faculty="Basic Medical Sciences">Physiology</option>
                                <option value="Nursing Science" data-faculty="Basic Medical Sciences">Nursing Science</option>
                                <option value="Medical Laboratory Science" data-faculty="Basic Medical Sciences">Medical Laboratory Science</option>
                                <option value="Public Health" data-faculty="Basic Medical Sciences">Public Health</option>
                                <option value="Health Information Management" data-faculty="Basic Medical Sciences">Health Information Management</option>
                                <!-- Pharmacy -->
                                <option value="Doctor of Pharmacy" data-faculty="Pharmacy">Doctor of Pharmacy</option>
                                <!-- Arts -->
                                <option value="Theatre Arts" data-faculty="Arts">Theatre Arts</option>
                                <option value="English Studies" data-faculty="Arts">English Studies</option>
                                <option value="History and International Studies" data-faculty="Arts">History and International Studies</option>
                                <option value="Religious Studies" data-faculty="Arts">Religious Studies</option>
                                <!-- Sciences -->
                                <option value="Biological Sciences" data-faculty="Sciences">Biological Sciences</option>
                                <option value="Cyber Security" data-faculty="Sciences">Cyber Security</option>
                                <option value="Computer Science" data-faculty="Sciences">Computer Science</option>
                                <option value="Biochemistry" data-faculty="Sciences">Biochemistry</option>
                                <option value="Information Technology" data-faculty="Sciences">Information Technology</option>
                                <option value="Information System" data-faculty="Sciences">Information System</option>
                                <option value="Microbiology" data-faculty="Sciences">Microbiology</option>
                                <option value="Biotechnology" data-faculty="Sciences">Biotechnology</option>
                                <option value="Chemistry" data-faculty="Sciences">Chemistry</option>
                                <option value="Physics" data-faculty="Sciences">Physics</option>
                                <option value="Mathematical Sciences" data-faculty="Sciences">Mathematical Sciences</option>
                                <option value="Statistics" data-faculty="Sciences">Statistics</option>
                                <option value="Software Engineering" data-faculty="Sciences">Software Engineering</option>
                                <option value="Food Science and Technology" data-faculty="Sciences">Food Science and Technology</option>
                                <!-- Business & Social Sciences -->
                                <option value="Accounting" data-faculty="Business & Social Sciences">Accounting</option>
                                <option value="Business Administration" data-faculty="Business & Social Sciences">Business Administration</option>
                                <option value="Economics" data-faculty="Business & Social Sciences">Economics</option>
                                <option value="Mass Communication" data-faculty="Business & Social Sciences">Mass Communication</option>
                                <option value="Political Science" data-faculty="Business & Social Sciences">Political Science</option>
                                <option value="Library and Information System" data-faculty="Business & Social Sciences">Library and Information System</option>
                                <option value="Public Administration" data-faculty="Business & Social Sciences">Public Administration</option>
                                <option value="Office and Information Management" data-faculty="Business & Social Sciences">Office and Information Management</option>
                                <option value="Finance" data-faculty="Business & Social Sciences">Finance</option>
                                <!-- Engineering -->
                                <option value="Civil Engineering" data-faculty="Engineering">Civil Engineering</option>
                                <option value="Mechanical Engineering" data-faculty="Engineering">Mechanical Engineering</option>
                                <option value="Electrical and Electronics Engineering" data-faculty="Engineering">Electrical and Electronics Engineering</option>
                                <option value="Computer Engineering" data-faculty="Engineering">Computer Engineering</option>
                                <option value="Agricultural and Biosystems Engineering" data-faculty="Engineering">Agricultural and Biosystems Engineering</option>
                                <option value="Chemical Engineering" data-faculty="Engineering">Chemical Engineering</option>
                                <!-- Law -->
                                <option value="Law" data-faculty="Law">Law</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="sm:col-span-2">
                        <label for="email" class="block text-sm font-medium text-slate-300">Email Address</label>
                        <div class="mt-1">
                            <input id="email" name="email" type="email" autocomplete="email" required 
                                class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                                placeholder="john.doe@university.edu">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                        <div class="mt-1">
                            <input id="password" name="password" type="password" required 
                                class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-slate-300">Confirm Password</label>
                        <div class="mt-1">
                            <input id="confirm_password" name="confirm_password" type="password" required 
                                class="appearance-none block w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all"
                                placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                        class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-150 active:scale-[0.99] shadow-indigo-600/20">
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-side Validation Logic -->
    <script>
        // Dynamic Faculty-Department filtering
        const facultySelect = document.getElementById('faculty');
        const deptSelect = document.getElementById('department');
        
        // Cache all department options (except the placeholder prompt option)
        const deptOptions = Array.from(deptSelect.options).slice(1).map(opt => ({
            value: opt.value,
            text: opt.text,
            faculty: opt.getAttribute('data-faculty')
        }));

        facultySelect.addEventListener('change', function() {
            const selectedFaculty = this.value;
            
            // Clear current options
            deptSelect.innerHTML = '';
            
            if (selectedFaculty) {
                // Enable select
                deptSelect.disabled = false;
                
                // Add default select department prompt
                const defaultOpt = document.createElement('option');
                defaultOpt.value = "";
                defaultOpt.disabled = true;
                defaultOpt.selected = true;
                defaultOpt.className = "text-slate-500";
                defaultOpt.textContent = "Select Department";
                deptSelect.appendChild(defaultOpt);
                
                // Filter and add matching departments
                const filtered = deptOptions.filter(opt => opt.faculty === selectedFaculty);
                filtered.forEach(opt => {
                    const el = document.createElement('option');
                    el.value = opt.value;
                    el.textContent = opt.text;
                    deptSelect.appendChild(el);
                });
            } else {
                // Disable and prompt
                deptSelect.disabled = true;
                const defaultOpt = document.createElement('option');
                defaultOpt.value = "";
                defaultOpt.disabled = true;
                defaultOpt.selected = true;
                defaultOpt.className = "text-slate-500";
                defaultOpt.textContent = "Please select Faculty first";
                deptSelect.appendChild(defaultOpt);
            }
        });

        // Form submission validation
        document.getElementById('registration-form').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const matricNo = document.getElementById('matric_no').value.trim();
            const department = document.getElementById('department').value;
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            const errorAlert = document.getElementById('js-error-alert');
            const errorMessage = document.getElementById('js-error-message');
            
            let errors = [];

            // 1. Check required fields
            if (!fullName || !matricNo || !department || !email || !password || !confirmPassword) {
                errors.push('All fields are required.');
            }

            // 2. Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailRegex.test(email)) {
                errors.push('Please enter a valid email address.');
            }

            // 3. Validate Matric Number format (Adeleke University YY/XXXX)
            const matricRegex = /^\d{2}\/\d{4}$/;
            if (matricNo && !matricRegex.test(matricNo)) {
                errors.push('Matric Number must be in the format YY/XXXX (e.g., 22/1314 or 24/0452).');
            }

            // 3. Password length check
            if (password && password.length < 8) {
                errors.push('Password must be at least 8 characters long.');
            }

            // 4. Passwords match check
            if (password !== confirmPassword) {
                errors.push('Passwords do not match.');
            }

            // 5. Full name character limits / formatting
            if (fullName && fullName.length < 3) {
                errors.push('Full Name must be at least 3 characters.');
            }

            if (errors.length > 0) {
                e.preventDefault(); // Prevent form submission
                errorMessage.textContent = errors.join(' ');
                errorAlert.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                errorAlert.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
