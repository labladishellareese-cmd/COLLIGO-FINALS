<?php
// 1. INCLUDE THE DATABASE CONNECTION
require_once 'db_connect.php';

// 2. DEFINE VARIABLES FOR MESSAGES
$error_message = '';
$success_message = '';

// 3. CHECK IF THE FORM WAS SUBMITTED
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 4. GET ALL FORM DATA
    $role = $_POST['role'];
    $first_name = $_POST['firstName'];
    $last_name = $_POST['lastName'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $contact_no = $_POST['contact'];

    // Handle optional fields
    $teacher_level_arr = $_POST['teacherLevel'] ?? null; // <-- FIXED
    $student_level = $_POST['studentLevel'] ?? null;
    $grade_level = $_POST['gradeLevel'] ?? null;
    $section = $_POST['section'] ?? null;
    
    // --- VALIDATION ---
    
    // 5. Check if passwords match
    if ($password !== $confirmPassword) {
        $error_message = "Passwords do not match.";
    } else {
        // 6. Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            // --- ALL CHECKS PASSED, PROCEED WITH REGISTRATION ---
            
            // 7. Securely hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // 8. Convert teacher levels array to a comma-separated string (or null)
            $teacher_level_str = $teacher_level_arr ? implode(',', $teacher_level_arr) : null; // <-- FIXED

            // 9. Prepare the SQL statement to insert the new user
            // ** THIS IS THE LINE (51) THAT CAUSED THE ERROR. IT IS NOW FIXED. **
            $stmt_insert = $conn->prepare(
                "INSERT INTO users (role, first_name, last_name, email, password, contact_no, teacher_level, student_level, grade_level, section, is_approved) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" // <-- FIXED
            );
            
            // 10. For teachers, set is_approved to 0. For students, set to 1 (auto-approved).
            $is_approved = ($role == 'teacher') ? 0 : 1;

            // 11. Bind all parameters and execute
            $stmt_insert->bind_param(
                "ssssssssssi",
                $role,
                $first_name,
                $last_name,
                $email,
                $hashed_password,
                $contact_no,
                $teacher_level_str, // <-- FIXED
                $student_level,
                $grade_level,
                $section,
                $is_approved
            );

            if ($stmt_insert->execute()) {
                if ($role == 'teacher') {
                    $success_message = "Registration successful! Your account is pending admin approval.";
                } else {
                    $success_message = "Registration successful! You can now log in.";
                }
            } else {
                $error_message = "Error: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Apply Inter font as the default sans-serif font */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom styling for select to remove default arrow */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        /* Simple transition for showing hidden fields */
        .field-group {
            transition: all 0.3s ease-in-out;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="min-h-screen flex">
        
        <div class="w-full lg:w-3/5 bg-sky-50 flex items-center justify-center p-8 sm:p-12">
            <div class="w-full max-w-2xl">
                
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Register</h1>
                <p class="text-sm text-gray-600 mb-8">Welcome! Please fill in the required information to create your account.</p>

                <form id="registerForm" action="register_page.php" method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        
                        <div class="md:col-span-2 relative">
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Register as</label>
                            <select id="role" name="role" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                                <option value="" disabled selected>Select your role...</option>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 top-7 flex items-center px-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>

                        <div id="mainFormFields" class="field-group md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 hidden">
                            <div>
                                <label for="firstName" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" id="firstName" name="firstName" placeholder="Enter First Name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>

                            <div>
                                <label for="lastName" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" id="lastName" name="lastName" placeholder="Enter Last Name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="Enter Email Address"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input type="password" id="password" name="password" placeholder="Password"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>

                            <div>
                                <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>

                            <div class="md:col-span-2">
                                <label for="contact" class="block text-sm font-medium text-gray-700 mb-1">Contact No.</label>
                                <input type="tel" id="contact" name="contact" placeholder="Contact No."
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                            </div>
                        </div>

                        <div id="teacherOptions" class="field-group md:col-span-2 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Teacher Level (Select all that apply)</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="teacherLevel[]" value="shs" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm font-medium">Senior High School</span>
                                </label>
                                <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="teacherLevel[]" value="tertiary" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="text-sm font-medium">Tertiary (College)</span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="studentOptions" class="field-group md:col-span-2 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Student Level (Select one)</label>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer flex-1">
                                    <input type="radio" name="studentLevel" value="shs" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <span class="text-sm font-medium">Senior High School</span>
                                </label>
                                <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer flex-1">
                                    <input type="radio" name="studentLevel" value="tertiary" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <span class="text-sm font-medium">Tertiary (College)</span>
                                </label>
                            </div>
                        </div>

                        <div id="studentClassInfo" class="field-group md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 hidden">
                            <div class="relative">
                                <label for="gradeLevel" class="block text-sm font-medium text-gray-700 mb-1">Grade Level</label>
                                <select id="gradeLevel" name="gradeLevel"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="" disabled selected>Select a grade...</option>
                                    </select>
                                <div class="absolute inset-y-0 right-0 top-7 flex items-center px-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>

                            <div class="relative">
                                <label for="section" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <select id="section" name="section"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="" disabled selected>Select a section...</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 top-7 flex items-center px-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                    </div> <button type="submit" id="registerButton"
                            class="field-group w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold text-center shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 transition duration-300 mt-6 hidden">
                        REGISTER
                    </button>

                    <div id="message" class="text-center mt-4 text-sm font-medium">
                        <?php 
                        if (!empty($error_message)) {
                            echo "<p class='text-red-600'>$error_message</p>";
                        }
                        if (!empty($success_message)) {
                            echo "<p class='text-green-600'>$success_message</p>";
                        }
                        ?>
                    </div>

                </form>

                <div class="text-center mt-8">
                    <p class="text-sm text-gray-600">
                        Already have account? 
                        <a href="login.php" class="font-medium text-blue-600 hover:underline">Login here</a>
                    </p>
                </div>
            </div>
        </div>

        <div class="hidden lg:flex w-2/5 bg-gradient-to-br from-blue-400 to-sky-600 items-center justify-center p-12 relative overflow-hidden">
            
            <div class="absolute inset-0 w-full h-full opacity-10" style="background-image: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.1) 75%, transparent 75%, transparent); background-size: 50px 50px;"></div>

            <div class="z-10 bg-blue-500 bg-opacity-80 backdrop-blur-sm p-12 xl:p-16 rounded-3xl shadow-2xl text-white text-center flex flex-col items-center max-w-lg">
                <div class="text-7xl font-bold mb-6 tracking-wider" aria-label="Colligo">
                    COLLIGO
                </div>
                
                <p class="text-xl italic font-light">
                    "Attendance is the purest form of dedication."
                </p>
            </div>
        </div>
    </div>

    <script>
        const roleSelect = document.getElementById('role');
        const mainFormFields = document.getElementById('mainFormFields');
        const teacherOptions = document.getElementById('teacherOptions');
        const studentOptions = document.getElementById('studentOptions');
        const studentClassInfo = document.getElementById('studentClassInfo');
        const registerButton = document.getElementById('registerButton');

        // Form fields to require
        const firstName = document.getElementById('firstName');
        const lastName = document.getElementById('lastName');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const contact = document.getElementById('contact');
        const gradeLevel = document.getElementById('gradeLevel');
        const section = document.getElementById('section');

        // --- START OF NEW LOGIC ---

        // 1. Define the grade options
        const gradeOptions = {
            shs: [
                { value: '11', text: '11' },
                { value: '12', text: '12' }
            ],
            tertiary: [
                { value: '1st Year', text: '1st Year' },
                { value: '2nd Year', text: '2nd Year' },
                { value: '3rd Year', text: '3rd Year' },
                { value: '4th Year', text: '4th Year' }
            ]
        };

        // 2. Find the radio buttons
        const studentLevelRadios = document.querySelectorAll('input[name="studentLevel"]');

        // 3. Function to update the grade level dropdown
        function updateGradeLevels(level) {
            gradeLevel.innerHTML = ''; // Clear all existing options

            // Add the default "Select a grade..." option
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.text = 'Select a grade...';
            defaultOption.disabled = true;
            defaultOption.selected = true;
            gradeLevel.appendChild(defaultOption);

            if (level && gradeOptions[level]) {
                // Add the new options based on the level selected
                gradeOptions[level].forEach(option => {
                    const newOption = document.createElement('option');
                    newOption.value = option.value;
                    newOption.text = option.text;
                    gradeLevel.appendChild(newOption);
                });
            }
        }

        // 4. Add event listeners to the radio buttons to trigger the update
        studentLevelRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    updateGradeLevels(this.value);
                }
            });
        });

        // --- END OF NEW LOGIC ---


        // This is your original code, with one line added
        roleSelect.addEventListener('change', function() {
            const selectedRole = this.value;

            // Hide all conditional fields first
            teacherOptions.classList.add('hidden');
            studentOptions.classList.add('hidden');
            studentClassInfo.classList.add('hidden');

            // Reset required status for client-side validation (good practice)
            firstName.required = false;
            lastName.required = false;
            email.required = false;
            password.required = false;
            confirmPassword.required = false;
            contact.required = false;
            gradeLevel.required = false;
            section.required = false;
            
            // Clear selections
            document.querySelectorAll('input[name="studentLevel"]').forEach(radio => radio.checked = false);
            document.querySelectorAll('input[name="teacherLevel[]"]').forEach(check => check.checked = false);
            
            // --- ADDED THIS LINE ---
            updateGradeLevels(null); // This clears the grade dropdown when you change roles
            // ---

            if (selectedRole === 'teacher') {
                teacherOptions.classList.remove('hidden');
                mainFormFields.classList.remove('hidden');
                registerButton.classList.remove('hidden');

                // Set required fields for teacher
                firstName.required = true;
                lastName.required = true;
                email.required = true;
                password.required = true;
                confirmPassword.required = true;
                contact.required = true;

            } else if (selectedRole === 'student') {
                studentOptions.classList.remove('hidden');
                studentClassInfo.classList.remove('hidden');
                mainFormFields.classList.remove('hidden');
                registerButton.classList.remove('hidden');

                // Set required fields for student
                firstName.required = true;
                lastName.required = true;
                email.required = true;
                password.required = true;
                confirmPassword.required = true;
                contact.required = true;
                gradeLevel.required = true;
                section.required = true;

            } else {
                // If no role is selected, hide everything
                mainFormFields.classList.add('hidden');
                registerButton.classList.add('hidden');
            }
        });

        // All simulation and submit-handling logic has been removed.
        // The form will now submit directly to "register_process.php".

    </script>

</body>
</html>