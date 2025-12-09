<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SANROOM | Teacher Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/super_admin.css">
    <style>
        :root {
            --color-primary: #3b82f6;
            /* Blue 500 */
            --color-secondary: #1f2937;
            /* Gray 800 */
            --color-text-dark: #111827;
            /* Gray 900 */
            --color-text-subtle: #6b7280;
            /* Gray 500 */
            --color-app-bg: #f9fafb;
            /* Gray 50 */
            --color-card-bg: #ffffff;
            --color-border-subtle: #e5e7eb;
            /* Gray 200 */
            --color-status-online: #10b981;
            /* Green 500 */
            --color-status-warning: #f59e0b;
            /* Amber 500 */
            --color-status-danger: #ef4444;
            /* Red 500 */
        }

        .bg-app-bg {
            background-color: var(--color-app-bg);
        }

        .text-text-dark {
            color: var(--color-text-dark);
        }

        .text-text-subtle {
            color: var(--color-text-subtle);
        }

        .text-primary {
            color: var(--color-primary);
        }

        .bg-card-bg {
            background-color: var(--color-card-bg);
        }

        .border-border-subtle {
            border-color: var(--color-border-subtle);
        }

        .text-secondary {
            color: var(--color-secondary);
        }

        .bg-secondary {
            background-color: var(--color-secondary);
        }

        .bg-primary {
            background-color: var(--color-primary);
        }

        .hover\:bg-primary\/80:hover {
            background-color: rgba(59, 130, 246, 0.8);
        }

        .shadow-card {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        .bg-status-online {
            background-color: var(--color-status-online);
        }

        .text-status-online {
            color: var(--color-status-online);
        }

        .bg-status-warning {
            background-color: var(--color-status-warning);
        }

        .text-status-warning {
            color: var(--color-status-warning);
        }

        .border-status-danger {
            border-color: var(--color-status-danger);
        }

        .text-status-danger {
            color: var(--color-status-danger);
        }

        .hover\:bg-status-danger:hover {
            background-color: var(--color-status-danger);
        }

        .bg-status-online\/20 {
            background-color: rgba(16, 185, 129, 0.2);
        }

        .bg-status-warning\/20 {
            background-color: rgba(245, 158, 11, 0.2);
        }

        .bg-status-danger\/20 {
            background-color: rgba(239, 68, 68, 0.2);
        }

        .shadow-lg-primary {
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2), 0 4px 6px -4px rgba(59, 130, 246, 0.2);
        }
    </style>
</head>

<body class="bg-app-bg text-text-dark min-h-screen flex flex-col">
    <nav class="sticky top-0 z-40 bg-card-bg flex justify-between items-center py-3 px-4 md:px-6 border-b border-border-subtle shadow-sm">
        <div class="flex items-center">
            <button id="menuToggle" class="md:hidden text-secondary p-2 mr-3 rounded-md hover:bg-gray-100 transition duration-150" aria-label="Toggle menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <div class="logo font-extrabold text-2xl text-primary">SANROOM</div>
        </div>

        <div class="hidden md:flex items-center gap-4">
            <div class="text-right">
                <p class="username text-text-dark font-semibold text-sm">USER NAME</p>
                <p class="role text-text-subtle text-xs">User Role</p>
            </div>
            <img class="w-10 h-10 object-cover rounded-full border-2 border-primary" src="" alt="User Avatar Placeholder">
        </div>
    </nav>

    <div class="dashboard-container flex flex-1">
        <aside id="sidebar" class="sidebar sidebar-mobile md:sidebar-desktop w-[250px] min-w-[250px] bg-secondary text-white flex flex-col py-6 flex-shrink-0 shadow-xl overflow-y-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out absolute md:relative z-30">
            <ul role="list" class="list-none p-0 m-0 space-y-1 flex-1">

                <li class="px-4 py-2 text-gray-400 text-xs font-bold uppercase tracking-wider mt-4">Administration</li>

                <li>
                    <a href="#" class="flex items-center p-3 mx-4 rounded-lg text-white font-medium transition duration-200 active bg-primary shadow-lg-primary">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 010 8h2m-7 8h14a2 2 000-2V5a2 2 000-2H6a2 2 000-2v14a2 2 000-2z"></path>
                        </svg>
                        Create Account
                    </a>
                </li>
                <li>
                    <a href="#currentTeachersSection" class="sidebar-scroll-link flex items-center p-3 mx-4 rounded-lg text-gray-200 font-medium transition duration-200 hover:bg-primary/80">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 000-3.25h-6.5l-.5-.5H12v-5l-4-4-4 4v5H4a3 3 000 3.25V20h5"></path>
                        </svg>
                        Manage Accounts
                    </a>
                </li>
                <li>
                    <a href="../index.php" class="btn-logout flex items-center p-3 mx-4 mt-6 rounded-lg font-medium transition duration-200 bg-transparent border border-status-danger text-status-danger hover:bg-status-danger hover:text-white">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 001 3h7a2 2 000 2V5a2 2 000-2H9a2 2 000-2v1"></path>
                        </svg>
                        Logout
                    </a>
                </li>
            </ul>

            <div class="px-4 pt-4 border-t border-secondary/70 mt-auto">
                <div class="md:hidden flex items-center gap-3 mb-4">
                    <img class="w-10 h-10 object-cover rounded-full border-2 border-primary" src="" alt="User Avatar Placeholder">
                    <div>
                        <p class="text-white font-semibold text-sm">USER NAME</p>
                        <p class="text-gray-400 text-xs">User Role</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="dashboard-content flex-1 p-4 sm:p-8 overflow-y-auto">
            <div class="top-bar flex flex-col md:flex-row items-start md:items-center justify-between pb-4 mb-6 border-b border-border-subtle">
                <div class="mb-4 md:mb-0">
                    <h1 class="text-3xl text-text-dark font-extrabold m-0">Teacher Administration</h1>
                    <p class="text-text-subtle text-sm mt-1 m-0">Manage account creation, view current teachers, and review activity logs.</p>
                </div>
            </div>

            <section class="account-creation mt-6 mb-10">
                <h2 class="text-2xl text-secondary mb-4 font-bold flex items-center gap-2">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 010 8h2m-7 8h14a2 2 000-2V5a2 2 000-2H6a2 2 000-2v14a2 2 000-2z"></path>
                    </svg>
                    Create New Teacher Account
                </h2>

                <div class="bg-card-bg rounded-xl p-4 sm:p-6 shadow-card max-w-2xl mx-auto">
                    <form id="createTeacherForm">
                        <div class="space-y-4">
                            <div class="form-group">
                                <label for="fullName" class="mb-1 block font-semibold text-secondary text-sm">Full Name</label>
                                <input type="text" id="fullName" name="full_name" placeholder="Teacher's Full Name" required class="w-full p-2.5 border border-border-subtle rounded-lg focus:border-primary focus:ring-1 focus:ring-primary/50 outline-none transition duration-150">
                            </div>

                            <div class="form-row flex flex-col sm:flex-row gap-4">
                                <div class="form-group flex flex-col flex-1">
                                    <label for="email" class="mb-1 font-semibold text-secondary text-sm">Email Address (Login)</label>
                                    <input type="email" id="email" name="email" placeholder="Email Address" required class="w-full p-2.5 border border-border-subtle rounded-lg focus:border-primary focus:ring-1 focus:ring-primary/50 outline-none transition duration-150">
                                </div>
                                <div class="form-group flex flex-col flex-1">
                                    <label for="department" class="mb-1 font-semibold text-secondary text-sm">Department / Subject</label>
                                    <select id="department" name="department" required class="w-full p-2.5 border border-border-subtle rounded-lg focus:border-primary focus:ring-1 focus:ring-primary/50 outline-none transition duration-150">
                                        <option value="">Select Department</option>
                                        <option value="Mathematics">Mathematics</option>
                                        <option value="Science">Science</option>
                                        <option value="English">English</option>
                                        <option value="IT">Information Technology</option>
                                        <option value="Arts">Arts</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="initialPassword" class="mb-1 block font-semibold text-secondary text-sm">Initial Password (Hidden)</label>
                                <div class="flex items-center gap-3">
                                    <input type="password" id="initialPassword" name="initial_password" value="DefaultPass123" readonly placeholder="Default Password" class="w-full p-2.5 border border-border-subtle rounded-lg bg-gray-50 text-text-subtle outline-none">
                                    <button type="button" id="generatePasswordBtn" class="btn-action bg-gray-200 text-text-dark py-2.5 px-4 rounded-lg font-semibold text-sm transition duration-200 hover:bg-gray-300 flex-shrink-0">
                                        Generate
                                    </button>
                                </div>
                                <p class="text-xs text-text-subtle mt-1">This is the fixed **default password** for the first login. It's recommended to auto-generate a strong one.</p>
                            </div>

                            <div class="form-group">
                                <label for="activationCode" class="mb-1 block font-semibold text-secondary text-sm">Activation Code / Token</label>
                                <div class="flex items-center gap-3">
                                    <input type="text" id="activationCode" name="activation_code" value="AC-XYZ123ABC" required placeholder="Activation Code" class="w-full p-2.5 border border-border-subtle rounded-lg bg-white outline-none transition duration-150 font-mono tracking-widest text-primary font-bold focus:border-primary focus:ring-1 focus:ring-primary/50">
                                    <button type="button" id="generateActivationCodeBtn" class="btn-action bg-gray-200 text-text-dark py-2.5 px-4 rounded-lg font-semibold text-sm transition duration-200 hover:bg-gray-300 flex-shrink-0">
                                        Generate
                                    </button>
                                </div>
                                <p class="text-xs text-text-subtle mt-1">Teachers must use this code to change their initial password. **Editable by Admin**.</p>
                            </div>
                        </div>

                        <div class="form-footer flex justify-end mt-6 pt-4 border-t border-border-subtle">
                            <button type="submit" class="btn-save bg-primary text-white py-2.5 px-6 rounded-lg font-semibold transition duration-200 hover:bg-primary/90 shadow-lg shadow-primary/20 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 01-18 0 9 9 0118 0z"></path>
                                </svg>
                                Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <hr class="my-10 border-border-subtle">

            <section id="currentTeachersSection" class="teacher-list mt-10">
                <div class="bg-card-bg rounded-xl shadow-card overflow-hidden">
                    <div class="flex items-center justify-between w-full p-4 sm:p-6 border-b border-border-subtle">
                        <div class="flex items-center gap-2">
                            <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 011.646-1.646C15.26 1.155 17 2.155 17 4v2.586l.707.707A2 2 001 21h-2a2 2 001-2v-4a2 2 001-2V8a2 2 000-2z"></path>
                            </svg>
                            <h2 class="text-xl text-secondary font-bold m-0">Current Teacher Accounts (Raw Data View)</h2>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border-subtle">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">teacher_id</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">full_name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">department</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">activation_code</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">is_active</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">created_at</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-subtle uppercase tracking-wider">qr_token</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-subtle uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle" id="teacherListTableBody">
                            </tbody>
                        </table>
                    </div>
                    <div class="p-6 text-center text-text-subtle italic" id="emptyTableMessage">
                        No teacher accounts found. Data will appear here once accounts are created.
                    </div>
                </div>
            </section>

            <hr class="my-10 border-border-subtle">

        </main>
    </div>

    <div id="toastContainer">
    </div>

    <div id="qrModal" class="fixed inset-0 bg-secondary/80 z-50 hidden items-center justify-center p-4" aria-modal="true" role="dialog">
        <div class="bg-card-bg rounded-xl shadow-2xl w-full max-w-sm p-6 transform transition-all duration-300 scale-100" id="qrModalContent">
            <h4 class="text-xl font-extrabold text-primary mb-2 flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Teacher QR Code Access
            </h4>
            <p class="text-sm text-text-subtle mb-4">
                Provide this QR code to **<span id="qrTeacherName" class="font-semibold text-text-dark"></span>**. They can scan it to complete their initial login setup.
            </p>

            <div class="flex flex-col items-center justify-center border border-border-subtle rounded-lg p-4 bg-gray-50 mb-4">
                <div id="qrCodeContainer" class="w-48 h-48 mb-3 border border-border-subtle bg-white p-1 flex items-center justify-center"></div>

                <p class="text-xs text-text-subtle font-mono select-all mt-3">QR Token: <span id="qrTokenDisplay" class="font-bold text-primary"></span></p>
            </div>

            <div class="flex flex-col gap-3">
                <a id="downloadQrBtn" href="#" download="sanroom_teacher_qr.png" class="btn-action w-full bg-status-online text-white py-3 px-4 rounded-lg font-semibold transition duration-200 hover:bg-status-online/80 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 000 3h16a3 3 000-3v-1M4 12l7-7 7 7M12 11V5"></path>
                    </svg>
                    Download QR Code
                </a>
                                <button id="closeQrModalBtn" type="button" class="btn-close w-full bg-gray-200 text-text-dark py-3 px-4 rounded-lg font-semibold transition duration-200 hover:bg-gray-300">
                    Close
                </button>
                           
            </div>
                   
        </div>
           
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="../assets/js/super_admin.js"></script>

</body>

</html>