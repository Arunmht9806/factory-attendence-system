<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factory Attendance System</title>
    <link rel="icon" type="image/png" href="assets/images/wellhope-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media (max-width: 767px) {
            .mobile-stack-sm {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">
    <header class="bg-gradient-to-r from-blue-700 to-indigo-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-3 w-full min-w-0">
                <div class="bg-white p-1.5 rounded-lg flex-shrink-0 shadow-sm">
                    <img src="assets/images/wellhope-logo.png" alt="Wellhope Logo" class="w-10 h-10 object-contain rounded">
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight truncate">NEPAL WELLHOPE AGRI TECH PVT.LTD. </h1>
                    <p class="text-xs text-blue-200 truncate">Biometric Punch & Attendance Registry</p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 w-full sm:w-auto gap-2">
                <span id="current-time-display" class="bg-black/20 px-3 py-1.5 rounded text-sm font-mono tracking-wider text-center sm:text-left"></span>
                <span class="bg-white/10 text-blue-100 px-2.5 py-1 rounded text-xs font-mono">
                    <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="ml-1 opacity-60">(<?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?>)</span>
                </span>
                <a href="logout.php"
                   class="bg-rose-600 hover:bg-rose-500 text-white font-bold px-3 py-2 rounded-lg text-xs flex items-center justify-center gap-1.5 transition shadow">
                    <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                </a>
                <a href="punch.php" target="_blank"
                   class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-4 py-2 rounded-lg text-sm flex items-center justify-center gap-1.5 transition shadow">
                    <i data-lucide="fingerprint" class="w-4 h-4"></i> Launch Punch Terminal
                </a>
                <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2.5 py-1 rounded-full text-xs font-semibold flex items-center justify-center gap-1.5">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                    Terminal Sync: Live
                </span>
                </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex items-center space-x-4">
                <div class="p-3 bg-blue-100 text-blue-700 rounded-lg">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium">Active Headcount</p>
                    <p id="stat-headcount" class="text-2xl font-bold text-slate-900">0</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex items-center space-x-4">
                <div class="p-3 bg-purple-100 text-purple-700 rounded-lg">
                    <i data-lucide="factory" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium">Production Staff</p>
                    <p id="stat-production" class="text-2xl font-bold text-slate-900">0</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex items-center space-x-4">
                <div class="p-3 bg-cyan-100 text-cyan-700 rounded-lg">
                    <i data-lucide="building" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium">Office Staff</p>
                    <p id="stat-office" class="text-2xl font-bold text-slate-900">0</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex items-center space-x-4">
                <div class="p-3 bg-amber-100 text-amber-700 rounded-lg">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium">Overtime Registered</p>
                    <p id="stat-ot" class="text-2xl font-bold text-slate-900">0.00h</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <section class="lg:col-span-12 space-y-6">
                <div id="admin-screen" class="hidden rounded-xl border px-4 py-3 text-sm font-semibold"></div>
                <div class="bg-white p-2 rounded-xl shadow-sm border border-slate-200 flex flex-wrap gap-2 overflow-x-auto">
                    <button onclick="switchTab('tab-attendance')" id="btn-tab-attendance" class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg transition-all flex items-center gap-1.5 bg-blue-600 text-white">
                        <i data-lucide="calendar" class="w-4 h-4"></i> Attendance Sheet
                    </button>
                    <button onclick="switchTab('tab-employees')" id="btn-tab-employees" class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg transition-all text-slate-600 hover:text-slate-900 hover:bg-slate-100 flex items-center gap-1.5">
                        <i data-lucide="user-cog" class="w-4 h-4"></i> Manage Staff
                    </button>
                    <button onclick="switchTab('tab-archived-employees')" id="btn-tab-archived-employees" class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg transition-all text-slate-600 hover:text-slate-900 hover:bg-slate-100 flex items-center gap-1.5">
                        <i data-lucide="archive" class="w-4 h-4"></i> Archived Staff
                    </button>
                    <button onclick="switchTab('tab-holidays')" id="btn-tab-holidays" class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg transition-all text-slate-600 hover:text-slate-900 hover:bg-slate-100 flex items-center gap-1.5">
                        <i data-lucide="plane-takeoff" class="w-4 h-4"></i> Public Holidays
                    </button>
                    <button onclick="switchTab('tab-vehicle-usage')" id="btn-tab-vehicle-usage" class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg transition-all text-slate-600 hover:text-slate-900 hover:bg-slate-100 flex items-center gap-1.5">
                        <i data-lucide="truck" class="w-4 h-4"></i> Vehicle Usage
                    </button>
                    <button onclick="switchTab('tab-leave-management')" id="btn-tab-leave-management" class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg transition-all text-slate-600 hover:text-slate-900 hover:bg-slate-100 flex items-center gap-1.5">
                        <i data-lucide="calendar-check-2" class="w-4 h-4"></i> Leave Management
                    </button>
                    <button onclick="switchTab('tab-travel-form')" id="btn-tab-travel-form" class="flex-shrink-0 px-4 py-2 text-sm font-semibold rounded-lg transition-all text-slate-600 hover:text-slate-900 hover:bg-slate-100 flex items-center gap-1.5">
                        <i data-lucide="file-text" class="w-4 h-4"></i> Travel Form
                    </button>
                </div>

                <div id="tab-attendance" class="tab-content space-y-6">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4 items-end mobile-stack-sm">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">From Date</label>
                                <input type="date" id="filter-start" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">To Date</label>
                                <input type="date" id="filter-end" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Department</label>
                                <select id="filter-dept" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="All">All Departments</option>
                                    <option value="Production">Production</option>
                                    <option value="Office">Office</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Employee</label>
                                <select id="filter-emp" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="All">All Employees</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">HR Editor</label>
                                <select id="hr-editor" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="">Select HR staff</option>
                                </select>
                                <span id="hr-editor-warning" class="text-[10px] text-slate-500 mt-1 block">Select HR editor to unlock Edit / Delete buttons.</span>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:space-x-2 gap-2">
                                <button id="btn-filter" class="w-full sm:flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-all flex items-center justify-center gap-1.5">
                                    <i data-lucide="filter" class="w-4 h-4"></i> Filter
                                </button>
                                <button id="btn-export" class="w-full sm:flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-all flex items-center justify-center gap-1.5">
                                    <i data-lucide="download" class="w-4 h-4"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="attendance-summary" class="hidden bg-white p-4 rounded-xl shadow-sm border border-slate-200"></div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <h3 class="font-bold text-slate-900">Attendance Register Matrix</h3>
                            <span class="text-xs text-slate-500">Live dynamic calculation database</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                                        <th class="p-4">Staff ID</th>
                                        <th class="p-4">Full Name</th>
                                        <th class="p-4">Dept</th>
                                        <th class="p-4">Date</th>
                                        <th class="p-4">Day Type</th>
                                        <th class="p-4">Session Time</th>
                                        <th class="p-4">Office Vehicle Used</th>
                                        <th class="p-4">GPS Tracking</th>
                                        <th class="p-4">Leave Type</th>
                                        <th class="p-4">Leave Days</th>
                                        <th class="p-4">Regular Hours</th>
                                        <th class="p-4">Overtime (OT)</th>
                                        <th class="p-4 text-right">Total Hours</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="attendance-table-body" class="divide-y divide-slate-200 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab-employees" class="tab-content hidden space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                        <h3 id="employee-form-title" class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i> Add New Employee Profile
                        </h3>
                        <form id="employee-form" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <input type="hidden" id="emp-form-mode" value="add">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Employee ID</label>
                                <input type="text" id="emp-form-id" placeholder="e.g. EMP102" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Full Name</label>
                                <input type="text" id="emp-form-name" placeholder="e.g. Rita Sen" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Post / Designation</label>
                                <input type="text" id="emp-form-designation" placeholder="e.g. HR Officer, Line Supervisor" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Department</label>
                                <select id="emp-form-dept" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="Production">Production</option>
                                    <option value="Office">Office</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">HR Attendance Access</label>
                                <label class="flex items-center gap-2 h-[42px] px-3 border border-slate-300 rounded-lg text-sm text-slate-700">
                                    <input type="checkbox" id="emp-form-can-edit-attendance" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500/20">
                                    Can edit attendance
                                </label>
                            </div>
                            <div class="md:col-span-2 flex flex-col sm:flex-row justify-end sm:space-x-2 gap-2 pt-2">
                                <button type="button" id="btn-reset-employee" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-sm transition">Cancel</button>
                                <button type="submit" id="emp-submit-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition">Register Profile</button>
                            </div>
                        </form>
                    </div>

                    <!-- Bulk Import Panel -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                        <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="file-spreadsheet" class="w-5 h-5 text-emerald-600"></i> Bulk Import Staff via Excel (.xlsx)
                        </h3>
                        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
                            <div class="flex-1">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Select .xlsx File</label>
                                <input type="file" id="bulk-import-file" accept=".xlsx" class="w-full p-2 border border-slate-300 rounded-lg text-sm outline-none bg-white">
                                <p class="text-[10px] text-slate-400 mt-1">Columns: <span class="font-mono">Employee ID, Full Name, Post / Designation, Department, HR Attendance Access (1/0)</span></p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button id="btn-download-staff-template" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-sm transition flex items-center gap-1.5">
                                    <i data-lucide="download" class="w-4 h-4"></i> Template
                                </button>
                                <button id="btn-bulk-import" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-sm transition flex items-center gap-1.5">
                                    <i data-lucide="upload" class="w-4 h-4"></i> Import
                                </button>
                            </div>
                        </div>
                        <div id="bulk-import-result" class="mt-3 text-sm hidden"></div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col gap-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="font-bold text-slate-900">Current Staff Register</h3>
                                <div class="flex items-center gap-3">
                                    <span id="employee-selection-count" class="text-xs text-slate-500">0 selected</span>
                                    <button id="btn-delete-selected-employees" class="px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-lg text-xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                                        Delete Selected Staff
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <input type="search" id="employee-search" placeholder="Search by Staff ID, name, designation, or department" class="w-full sm:max-w-md p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                <button id="btn-select-filtered-employees" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    Select Filtered
                                </button>
                                <span id="employee-match-count" class="text-xs text-slate-500">0 matches</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                                        <th class="p-4 w-14 text-center"><input type="checkbox" id="employee-select-all" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500/20"></th>
                                        <th class="p-4">Staff ID</th>
                                        <th class="p-4">Full Name</th>
                                        <th class="p-4">Post / Designation</th>
                                        <th class="p-4">Department</th>
                                        <th class="p-4">HR Access</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="employee-table-body" class="divide-y divide-slate-200 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="user-crud-panel" class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                        <h3 id="user-form-title" class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-5 h-5 text-blue-600"></i> Create Login User (User ID & Password)
                        </h3>
                        <p class="text-xs text-slate-500 mb-4">Users created here must use a Staff ID from Manage Staff. Role is auto-assigned (Viewer / HR / IT). Viewer accounts get default password <span class="font-semibold text-slate-700">123</span>; HR/Admin/IT use secure passwords.</p>
                        <div id="user-form-feedback" class="hidden mb-4 rounded-lg border px-4 py-3 text-sm font-semibold"></div>
                        <form id="user-form" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <input type="hidden" id="user-form-id" value="">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">User ID</label>
                                <select id="user-form-username" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="">Select Staff ID</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Password</label>
                                <input type="password" id="user-form-password" placeholder="Auto-set to 123 for Viewer" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Role</label>
                                <select id="user-form-role" disabled class="w-full p-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="viewer" selected>Viewer</option>
                                    <option value="hr">HR</option>
                                    <option value="it">IT</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="flex flex-col sm:flex-row justify-end sm:space-x-2 gap-2 pt-2">
                                <button type="button" id="btn-reset-user" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-sm transition">Cancel</button>
                                <button type="submit" id="user-submit-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition">Create User</button>
                            </div>
                        </form>

                        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200">
                            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex flex-col gap-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span id="user-selection-count" class="text-xs text-slate-500">0 selected</span>
                                    <button id="btn-delete-selected-users" class="px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-lg text-xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                                        Delete Selected Users
                                    </button>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <input type="search" id="user-search" placeholder="Search by User ID or role" class="w-full sm:max-w-md p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <button id="btn-select-filtered-users" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                                        Select Filtered
                                    </button>
                                    <span id="user-match-count" class="text-xs text-slate-500">0 matches</span>
                                </div>
                            </div>
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                                        <th class="p-4 w-14 text-center"><input type="checkbox" id="user-select-all" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500/20"></th>
                                        <th class="p-4">User ID</th>
                                        <th class="p-4">Role</th>
                                        <th class="p-4">Created At</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="user-table-body" class="divide-y divide-slate-200 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab-archived-employees" class="tab-content hidden space-y-6">
                    <div class="relative overflow-hidden rounded-2xl border border-amber-200 bg-[radial-gradient(circle_at_top_left,_rgba(251,191,36,0.24),_transparent_38%),linear-gradient(135deg,_#fff7ed,_#fffbeb_55%,_#ffffff)] p-6 shadow-sm">
                        <div class="absolute right-0 top-0 h-28 w-28 rounded-full bg-amber-200/30 blur-2xl"></div>
                        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-white/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-800">Archive Vault</div>
                                <h3 class="mt-3 text-2xl font-black tracking-tight text-slate-900">Archived Staff Records</h3>
                                <p class="mt-2 max-w-2xl text-sm text-slate-600">Deleted staff are stored separately here so login access stays removed while attendance history remains safe and restorable.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span id="archived-selection-count" class="rounded-full border border-amber-300 bg-white/80 px-3 py-1 text-xs font-semibold text-amber-800">0 selected</span>
                                <span id="archived-employee-count" class="rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">0 archived</span>
                                <button id="btn-delete-selected-archived-employees" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-lg text-sm transition disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Selected
                                </button>
                                <button id="btn-restore-selected-employees" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg text-sm transition disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                                    <i data-lucide="archive-restore" class="w-4 h-4"></i> Restore Selected
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-amber-200 bg-amber-50 flex flex-col gap-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <input type="search" id="archived-employee-search" placeholder="Search archived staff by ID, name, designation, or department" class="w-full sm:max-w-md p-2 border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                                <button id="btn-select-filtered-archived-employees" class="px-3 py-2 bg-white hover:bg-amber-100 text-amber-800 border border-amber-200 font-semibold rounded-lg text-xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    Select Filtered
                                </button>
                                <span id="archived-match-count" class="text-xs text-amber-700">0 matches</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-amber-100/70 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-amber-200">
                                        <th class="p-4 w-14 text-center"><input type="checkbox" id="archived-employee-select-all" class="rounded border-amber-300 text-amber-600 focus:ring-amber-500/20"></th>
                                        <th class="p-4">Staff ID</th>
                                        <th class="p-4">Full Name</th>
                                        <th class="p-4">Post / Designation</th>
                                        <th class="p-4">Department</th>
                                        <th class="p-4">HR Access</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="archived-employee-table-body" class="divide-y divide-amber-100 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab-holidays" class="tab-content hidden space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                        <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="plane" class="w-5 h-5 text-blue-600"></i> Register Factory Public Holiday
                        </h3>
                        <form id="holiday-form" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Calendar Date</label>
                                <input type="date" id="holiday-form-date" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Holiday Name / Occasion</label>
                                <input type="text" id="holiday-form-desc" placeholder="e.g. New Year Day" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition">Register Calendar Event</button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                            <h3 class="font-bold text-slate-900">Registered Public Holidays</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                                        <th class="p-4">Date</th>
                                        <th class="p-4">Occasion</th>
                                        <th class="p-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="holiday-table-body" class="divide-y divide-slate-200 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab-vehicle-usage" class="tab-content hidden space-y-6">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-end mobile-stack-sm">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">From Date</label>
                                <input type="date" id="vehicle-filter-start" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">To Date</label>
                                <input type="date" id="vehicle-filter-end" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Department</label>
                                <select id="vehicle-filter-dept" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="All">All Departments</option>
                                    <option value="Production">Production</option>
                                    <option value="Office">Office</option>
                                </select>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:space-x-2 gap-2">
                                <button id="btn-vehicle-filter" class="w-full sm:flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-all flex items-center justify-center gap-1.5">
                                    <i data-lucide="filter" class="w-4 h-4"></i> Filter
                                </button>
                                <button id="btn-vehicle-export" class="w-full sm:flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-all flex items-center justify-center gap-1.5">
                                    <i data-lucide="download" class="w-4 h-4"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <h3 class="font-bold text-slate-900">Vehicle Usage Register</h3>
                            <span class="text-xs text-slate-500">Dedicated vehicle punch logs</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                                        <th class="p-4">Staff ID</th>
                                        <th class="p-4">Full Name</th>
                                        <th class="p-4">Dept</th>
                                        <th class="p-4">Date</th>
                                        <th class="p-4">From Time</th>
                                        <th class="p-4">To Time</th>
                                        <th class="p-4">Duration</th>
                                        <th class="p-4">Vehicle Number</th>
                                        <th class="p-4">Purpose</th>
                                        <th class="p-4">Status</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="vehicle-usage-table-body" class="divide-y divide-slate-200 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab-leave-management" class="tab-content hidden space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                        <h3 id="leave-form-title" class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="calendar-check-2" class="w-5 h-5 text-blue-600"></i> Register Leave Request
                        </h3>
                        <form id="leave-form" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <input type="hidden" id="leave-form-id" value="">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Employee</label>
                                <select id="leave-form-emp-id" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="">Select employee</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Leave Type</label>
                                <input type="text" id="leave-form-type" placeholder="e.g. Sick Leave" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Start Date</label>
                                <input type="date" id="leave-form-start" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">End Date</label>
                                <input type="date" id="leave-form-end" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Leave Days</label>
                                <input type="number" id="leave-form-days" step="0.5" min="0.5" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Status</label>
                                <select id="leave-form-status" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Reason</label>
                                <input type="text" id="leave-form-reason" placeholder="Reason for leave" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Remarks</label>
                                <input type="text" id="leave-form-remarks" placeholder="Optional manager remarks" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div class="md:col-span-2 flex flex-col sm:flex-row justify-end sm:space-x-2 gap-2 pt-2">
                                <button type="button" id="btn-reset-leave" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-sm transition">Cancel</button>
                                <button type="submit" id="leave-submit-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition">Save Leave Request</button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-end mobile-stack-sm">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">From Date</label>
                                <input type="date" id="leave-filter-start" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">To Date</label>
                                <input type="date" id="leave-filter-end" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Department</label>
                                <select id="leave-filter-dept" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="All">All Departments</option>
                                    <option value="Production">Production</option>
                                    <option value="Office">Office</option>
                                </select>
                            </div>
                            <div>
                                <button id="btn-leave-filter" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-all flex items-center justify-center gap-1.5">
                                    <i data-lucide="filter" class="w-4 h-4"></i> Filter
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                            <h3 class="font-bold text-slate-900">Leave Request Register</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                                        <th class="p-4">Staff ID</th>
                                        <th class="p-4">Name</th>
                                        <th class="p-4">Dept</th>
                                        <th class="p-4">Type</th>
                                        <th class="p-4">Start</th>
                                        <th class="p-4">End</th>
                                        <th class="p-4">Days</th>
                                        <th class="p-4">Status</th>
                                        <th class="p-4">Reason</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="leave-table-body" class="divide-y divide-slate-200 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab-travel-form" class="tab-content hidden space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                        <h3 id="travel-form-title" class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                            <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i> Travel Order Form
                        </h3>
                        <form id="travel-form" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <input type="hidden" id="travel-form-id" value="">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Date</label>
                                <input type="date" id="travel-form-date" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Employee</label>
                                <select id="travel-form-emp-id" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="">Select employee</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Branch</label>
                                <input type="text" id="travel-form-branch" placeholder="Branch" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Destination</label>
                                <input type="text" id="travel-form-destination" placeholder="Traveling place / destination" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Purpose</label>
                                <input type="text" id="travel-form-purpose" placeholder="Purpose" required class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Date of Departure</label>
                                <input type="date" id="travel-form-departure" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Date of Arrival</label>
                                <input type="date" id="travel-form-arrival" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Mode of Travel</label>
                                <select id="travel-form-mode" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="Office Vehicle">Office Vehicle</option>
                                    <option value="Air">Air</option>
                                    <option value="Bus">Bus</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Other Mode</label>
                                <input type="text" id="travel-form-mode-other" placeholder="If mode is Other" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Advance Rs.</label>
                                <input type="number" id="travel-form-advance" min="0" step="0.01" value="0" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Requested By</label>
                                <input type="text" id="travel-form-requested-by" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Checked By</label>
                                <input type="text" id="travel-form-checked-by" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Approved By</label>
                                <input type="text" id="travel-form-approved-by" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Total Days</label>
                                <input type="number" id="travel-form-total-days" min="0" step="0.01" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">TADA Per Day</label>
                                <input type="number" id="travel-form-tada" min="0" step="0.01" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Other Expenses</label>
                                <input type="number" id="travel-form-other-expenses" min="0" step="0.01" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Total Expenses</label>
                                <input type="number" id="travel-form-total-expenses" min="0" step="0.01" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Settlement Requested By</label>
                                <input type="text" id="travel-form-settlement-requested-by" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Settlement Checked By</label>
                                <input type="text" id="travel-form-settlement-checked-by" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Settlement Approved By</label>
                                <input type="text" id="travel-form-settlement-approved-by" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Notes</label>
                                <textarea id="travel-form-notes" rows="2" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none" placeholder="Optional notes"></textarea>
                            </div>
                            <div class="md:col-span-4 flex flex-col sm:flex-row justify-end sm:space-x-2 gap-2 pt-2">
                                <button type="button" id="btn-reset-travel" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg text-sm transition">Cancel</button>
                                <button type="submit" id="travel-submit-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition">Save Travel Form</button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 items-end mobile-stack-sm">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">From Date</label>
                                <input type="date" id="travel-filter-start" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">To Date</label>
                                <input type="date" id="travel-filter-end" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Department</label>
                                <select id="travel-filter-dept" class="w-full p-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none">
                                    <option value="All">All Departments</option>
                                    <option value="Production">Production</option>
                                    <option value="Office">Office</option>
                                </select>
                            </div>
                            <div>
                                <button id="btn-travel-filter" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-all flex items-center justify-center gap-1.5">
                                    <i data-lucide="filter" class="w-4 h-4"></i> Filter
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                            <h3 class="font-bold text-slate-900">Travel Order Register</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wider font-semibold border-b border-slate-200">
                                        <th class="p-4">Date</th>
                                        <th class="p-4">Staff ID</th>
                                        <th class="p-4">Name</th>
                                        <th class="p-4">Destination</th>
                                        <th class="p-4">Purpose</th>
                                        <th class="p-4">Mode</th>
                                        <th class="p-4">Advance</th>
                                        <th class="p-4">Departure</th>
                                        <th class="p-4">Arrival</th>
                                        <th class="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="travel-table-body" class="divide-y divide-slate-200 text-sm text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div id="gps-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 px-4 py-6">
        <div class="w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Employee GPS Tracking</h3>
                    <p id="gps-modal-subtitle" class="text-sm text-slate-500">Daily punch map and coordinate history.</p>
                </div>
                <button type="button" onclick="closeGpsModal()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Close
                </button>
            </div>
            <div class="grid grid-cols-1 gap-0 lg:grid-cols-[1.6fr_1fr]">
                <div class="min-h-[360px] border-b border-slate-200 bg-slate-100 lg:border-b-0 lg:border-r">
                    <iframe id="gps-map-frame" title="Employee GPS map" class="h-[420px] w-full" src="about:blank" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div class="space-y-4 p-5">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs font-bold uppercase tracking-widest text-emerald-700">Map Mode</div>
                        <div class="mt-2 text-sm text-emerald-900">Google Maps hybrid view focused on the latest punch location. Use the external link below for full navigation controls.</div>
                        <a id="gps-external-link" href="#" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            <i data-lucide="map" class="h-4 w-4"></i> Open in Google Maps
                        </a>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-900">Captured Points</h4>
                        <div id="gps-points-list" class="mt-3 max-h-[280px] space-y-3 overflow-y-auto"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-slate-900 text-slate-400 py-6 mt-12 border-t border-slate-800 text-xs text-center">
        <div class="max-w-7xl mx-auto px-4 space-y-2">
            <p>© 2026 NEPAL WELLHOPE AGRI TECH PVT.LTD. factory attendance management system.</p>
            <p class="text-slate-500"></p>
        </div>
    </footer>

    <script>
        const apiUrl = 'api.php';
        const currentUserRole = '<?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?>';
        const currentUserId = Number('<?= (int)($_SESSION['user_id'] ?? 0) ?>');
        const hasFullAccessRole = ['admin', 'hr', 'it'].includes(currentUserRole);
        const isViewerRole = currentUserRole === 'viewer';
        let hasLeaveFormFullAccess = hasFullAccessRole;
        let employees = [];
        let holidays = [];
        let attendanceRecords = [];
        let vehicleUsageRecords = [];
        let leaveRequests = [];
        let travelOrders = [];
        let users = [];
        let archivedEmployees = [];
        let selectedEmployeeIds = new Set();
        let selectedArchivedEmployeeIds = new Set();
        let selectedUserIds = new Set();
        let activeMutationRequests = 0;

        function formatDateTimeLocal(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function syncLeaveFormAccessUi() {
            const leaveStatus = document.getElementById('leave-form-status');
            if (!leaveStatus) return;
            if (hasLeaveFormFullAccess) {
                leaveStatus.disabled = false;
                return;
            }
            leaveStatus.value = 'Pending';
            leaveStatus.disabled = true;
        }

        function buildGoogleHybridMapUrl(latitude, longitude, embed = false) {
            const coords = `${latitude},${longitude}`;
            if (embed) {
                return `https://maps.google.com/maps?q=${encodeURIComponent(coords)}&t=h&z=19&output=embed`;
            }
            return `https://www.google.com/maps?q=${encodeURIComponent(coords)}&t=h&z=19`;
        }

        function closeGpsModal() {
            const modal = document.getElementById('gps-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('gps-map-frame').src = 'about:blank';
        }

        function openGpsModal(empId, date) {
            if (!hasFullAccessRole) {
                return;
            }
            const record = attendanceRecords.find(item => item.empId === empId && item.date === date);
            if (!record || !Array.isArray(record.gpsPoints) || record.gpsPoints.length === 0) {
                alert('No GPS points were saved for this attendance day.');
                return;
            }

            const latestPoint = record.latestGps || record.gpsPoints[record.gpsPoints.length - 1];
            const modal = document.getElementById('gps-modal');
            document.getElementById('gps-modal-subtitle').textContent = `${record.name} (${record.empId}) on ${record.date}`;
            document.getElementById('gps-map-frame').src = buildGoogleHybridMapUrl(latestPoint.latitude, latestPoint.longitude, true);
            document.getElementById('gps-external-link').href = buildGoogleHybridMapUrl(latestPoint.latitude, latestPoint.longitude, false);
            document.getElementById('gps-points-list').innerHTML = record.gpsPoints.map(point => {
                const accuracyText = Number.isFinite(Number(point.accuracy)) ? `${Number(point.accuracy).toFixed(1)}m accuracy` : 'Accuracy unavailable';
                const mapUrl = buildGoogleHybridMapUrl(point.latitude, point.longitude, false);
                return `
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">${escapeHtml(point.label || 'Punch location')}</div>
                                <div class="mt-1 font-mono text-xs text-slate-600">${escapeHtml(point.timestamp || '')}</div>
                            </div>
                            <a href="${mapUrl}" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Open</a>
                        </div>
                        <div class="mt-2 font-mono text-xs leading-5 text-slate-700">Lat ${Number(point.latitude).toFixed(6)}, Lng ${Number(point.longitude).toFixed(6)}</div>
                        <div class="mt-1 text-xs text-slate-500">${escapeHtml(accuracyText)}</div>
                    </div>
                `;
            }).join('');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            lucide.createIcons();
        }

        document.addEventListener('DOMContentLoaded', async () => {
            lucide.createIcons();
            const today = formatDateInputValue();
            const rollingStart = formatDateInputValue(addDays(new Date(), -30));
            const leaveWindowEnd = formatDateInputValue(addDays(new Date(), 90));
            document.getElementById('filter-start').value = today;
            document.getElementById('filter-end').value = today;
            document.getElementById('vehicle-filter-start').value = rollingStart;
            document.getElementById('vehicle-filter-end').value = today;
            document.getElementById('leave-filter-start').value = rollingStart;
            document.getElementById('leave-filter-end').value = leaveWindowEnd;
            document.getElementById('travel-filter-start').value = rollingStart;
            document.getElementById('travel-filter-end').value = today;
            document.getElementById('travel-form-date').value = today;
            document.getElementById('gps-modal').addEventListener('click', event => {
                if (event.target.id === 'gps-modal') {
                    closeGpsModal();
                }
            });
            applyRoleAccess();
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
            setInterval(() => {
                if (!document.getElementById('tab-vehicle-usage').classList.contains('hidden')) {
                    renderVehicleUsageGrid();
                }
            }, 15000);
            bindEvents();
            applyUserPanelAccess();
            await fetchAllData();
            switchTab('tab-attendance');
        });

        function updateCurrentTime() {
            document.getElementById('current-time-display').innerText = new Date().toLocaleString();
        }

        function formatDateInputValue(date = new Date()) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function addDays(date, days) {
            const copy = new Date(date);
            copy.setDate(copy.getDate() + days);
            return copy;
        }

        function isValidDateRange(start, end) {
            return Boolean(start) && Boolean(end) && start <= end;
        }

        function wait(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        function isTransientRequestFailure(message) {
            const value = String(message || '').toLowerCase();
            return value.includes('timeout') || value.includes('network') || value.includes('failed to fetch') || value.includes('temporarily unavailable');
        }

        function setMutationBusyState(isBusy, message = '') {
            document.body.classList.toggle('cursor-wait', isBusy);
            document.querySelectorAll('button, input, select, textarea').forEach(element => {
                if (isBusy) {
                    if (!('busyDisabled' in element.dataset)) {
                        element.dataset.busyDisabled = element.disabled ? '1' : '0';
                    }
                    element.disabled = true;
                } else if ('busyDisabled' in element.dataset) {
                    element.disabled = element.dataset.busyDisabled === '1';
                    delete element.dataset.busyDisabled;
                }
            });

            if (message) {
                showHardwareScreen(message, 'text-emerald-400');
            }
        }

        async function requestJson(url, options = {}, settings = {}) {
            const method = String(options.method || 'GET').toUpperCase();
            const retries = Number.isInteger(settings.retries) ? settings.retries : (method === 'GET' ? 1 : 0);
            const timeoutMs = Number.isInteger(settings.timeoutMs) ? settings.timeoutMs : 15000;
            const retryDelayMs = Number.isInteger(settings.retryDelayMs) ? settings.retryDelayMs : 350;
            const isMutation = settings.isMutation === true || method !== 'GET';

            if (isMutation) {
                activeMutationRequests += 1;
                if (activeMutationRequests === 1) {
                    setMutationBusyState(true, settings.busyMessage || 'Saving changes...');
                }
            }

            try {
                for (let attempt = 0; attempt <= retries; attempt += 1) {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

                    try {
                        const response = await fetch(url, {
                            ...options,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(options.headers || {}),
                            },
                            credentials: 'same-origin',
                            signal: controller.signal,
                        });
                        clearTimeout(timeoutId);

                        const text = await response.text();
                        let payload = null;
                        try {
                            payload = text ? JSON.parse(text) : {};
                        } catch (error) {
                            payload = null;
                        }

                        if (!response.ok) {
                            return {
                                success: false,
                                message: payload?.message || `Request failed (${response.status}).`,
                            };
                        }

                        if (!payload || typeof payload !== 'object') {
                            return {
                                success: false,
                                message: 'Server returned an unreadable response.',
                            };
                        }

                        return payload;
                    } catch (error) {
                        clearTimeout(timeoutId);
                        const isTimeout = error?.name === 'AbortError';
                        const message = isTimeout ? 'Request timeout. Please try again.' : (error?.message || 'Network request failed.');

                        if (attempt < retries && isTransientRequestFailure(message)) {
                            await wait(retryDelayMs * (attempt + 1));
                            continue;
                        }

                        return {
                            success: false,
                            message,
                        };
                    }
                }

                return {
                    success: false,
                    message: 'Request failed after retries.',
                };
            } finally {
                if (isMutation) {
                    activeMutationRequests = Math.max(0, activeMutationRequests - 1);
                    if (activeMutationRequests === 0) {
                        setMutationBusyState(false);
                    }
                }
            }
        }

        function bindEvents() {
            document.getElementById('btn-filter').addEventListener('click', renderAttendanceGrid);
            document.getElementById('btn-export').addEventListener('click', exportAttendance);
            document.getElementById('btn-vehicle-filter').addEventListener('click', renderVehicleUsageGrid);
            document.getElementById('btn-vehicle-export').addEventListener('click', exportVehicleUsage);
            document.getElementById('btn-leave-filter').addEventListener('click', renderLeaveGrid);
            document.getElementById('btn-travel-filter').addEventListener('click', renderTravelGrid);
            document.getElementById('employee-form').addEventListener('submit', handleEmployeeSubmit);
            document.getElementById('btn-reset-employee').addEventListener('click', resetEmployeeForm);
            document.getElementById('holiday-form').addEventListener('submit', handleHolidaySubmit);
            document.getElementById('leave-form').addEventListener('submit', handleLeaveSubmit);
            document.getElementById('btn-reset-leave').addEventListener('click', resetLeaveForm);
            document.getElementById('travel-form').addEventListener('submit', handleTravelSubmit);
            document.getElementById('btn-reset-travel').addEventListener('click', resetTravelForm);
            document.getElementById('user-form').addEventListener('submit', handleUserSubmit);
            document.getElementById('btn-reset-user').addEventListener('click', resetUserForm);
            document.getElementById('user-form-username').addEventListener('change', syncAutoRoleFromUsername);
            document.getElementById('btn-download-staff-template').addEventListener('click', () => {
                window.location.href = `${apiUrl}?action=downloadEmployeeTemplate`;
            });
            document.getElementById('btn-bulk-import').addEventListener('click', handleBulkImport);
            document.getElementById('employee-select-all').addEventListener('change', event => toggleAllEmployees(event.target.checked));
            document.getElementById('user-select-all').addEventListener('change', event => toggleAllUsers(event.target.checked));
            document.getElementById('btn-delete-selected-employees').addEventListener('click', deleteSelectedEmployees);
            document.getElementById('btn-delete-selected-users').addEventListener('click', deleteSelectedUsers);
            document.getElementById('employee-search').addEventListener('input', renderEmployeeTable);
            document.getElementById('archived-employee-search').addEventListener('input', renderArchivedEmployeeTable);
            document.getElementById('user-search').addEventListener('input', renderUserTable);
            document.getElementById('btn-select-filtered-employees').addEventListener('click', toggleFilteredEmployees);
            document.getElementById('archived-employee-select-all').addEventListener('change', event => toggleAllArchivedEmployees(event.target.checked));
            document.getElementById('btn-select-filtered-archived-employees').addEventListener('click', toggleFilteredArchivedEmployees);
            document.getElementById('btn-restore-selected-employees').addEventListener('click', restoreSelectedEmployees);
            document.getElementById('btn-delete-selected-archived-employees').addEventListener('click', deleteSelectedArchivedEmployees);
            document.getElementById('btn-select-filtered-users').addEventListener('click', toggleFilteredUsers);
        }

        function applyRoleAccess() {
            if (!isViewerRole) {
                return;
            }

            document.getElementById('btn-tab-employees')?.classList.add('hidden');
            document.getElementById('btn-tab-archived-employees')?.classList.add('hidden');
            document.getElementById('btn-tab-holidays')?.classList.add('hidden');
            document.getElementById('btn-tab-vehicle-usage')?.classList.add('hidden');
            document.getElementById('btn-tab-travel-form')?.classList.add('hidden');
            document.getElementById('tab-employees')?.classList.add('hidden');
            document.getElementById('tab-archived-employees')?.classList.add('hidden');
            document.getElementById('tab-holidays')?.classList.add('hidden');
            document.getElementById('tab-vehicle-usage')?.classList.add('hidden');
            document.getElementById('tab-travel-form')?.classList.add('hidden');

            const hrEditorWrap = document.getElementById('hr-editor')?.closest('div');
            if (hrEditorWrap) {
                hrEditorWrap.classList.add('hidden');
            }

            const adminScreen = document.getElementById('admin-screen');
            if (adminScreen) {
                adminScreen.classList.add('hidden');
            }

            syncLeaveFormAccessUi();
        }

        function applyUserPanelAccess() {
            const panel = document.getElementById('user-crud-panel');
            if (!panel) return;
            if (!hasFullAccessRole) {
                panel.innerHTML = '<div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">Only admin, HR, and IT users can manage login User ID and Password records.</div>';
            }
        }

        function setUserFormFeedback(message, success) {
            const box = document.getElementById('user-form-feedback');
            if (!box) return;
            if (!message) {
                box.className = 'hidden mb-4 rounded-lg border px-4 py-3 text-sm font-semibold';
                box.textContent = '';
                return;
            }
            box.className = `mb-4 rounded-lg border px-4 py-3 text-sm font-semibold ${success ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'}`;
            box.textContent = message;
        }

        function getAutoRoleForUsername(username) {
            const employee = employees.find(emp => String(emp.id) === String(username));
            if (!employee) {
                return null;
            }
            const designation = String(employee.designation || '').toUpperCase();
            if (/\bIT\b/.test(designation)) {
                return 'it';
            }
            return Number(employee.can_edit_attendance || 0) === 1 ? 'hr' : 'viewer';
        }

        function applyUserPasswordPolicy(role, isEditMode = false) {
            const passwordInput = document.getElementById('user-form-password');
            if (!passwordInput) return;

            if (!isEditMode && role === 'viewer') {
                passwordInput.value = '123';
                passwordInput.required = false;
                passwordInput.minLength = 0;
                passwordInput.readOnly = true;
                passwordInput.placeholder = 'Auto-set to 123 for Viewer';
            } else {
                passwordInput.readOnly = false;
                passwordInput.required = isEditMode ? false : true;
                passwordInput.minLength = 6;
                if (!isEditMode) {
                    passwordInput.value = '';
                    passwordInput.placeholder = 'At least 6 characters';
                } else {
                    passwordInput.placeholder = 'Leave blank to keep current password';
                }
            }
        }

        function syncAutoRoleFromUsername() {
            const username = document.getElementById('user-form-username').value;
            const roleSelect = document.getElementById('user-form-role');
            const autoRole = getAutoRoleForUsername(username);
            if (autoRole) {
                roleSelect.value = autoRole;
                applyUserPasswordPolicy(autoRole, Number(document.getElementById('user-form-id').value || 0) > 0);
            }
        }

        function populateUserUsernameOptions() {
            const select = document.getElementById('user-form-username');
            if (!select) return;

            const selected = select.value;
            select.innerHTML = '<option value="">Select Staff ID</option>';

            [...employees]
                .sort((a, b) => String(a.id).localeCompare(String(b.id)))
                .forEach(emp => {
                    const option = document.createElement('option');
                    option.value = emp.id;
                    option.textContent = `${emp.id} - ${emp.name}`;
                    select.appendChild(option);
                });

            if (selected && [...select.options].some(o => o.value === selected)) {
                select.value = selected;
            }
            syncAutoRoleFromUsername();
        }

        async function fetchAllData() {
            await Promise.all([fetchEmployees(), fetchArchivedEmployees(), fetchHolidays(), fetchUsers()]);
            updateLiveStats();
        }

        async function fetchArchivedEmployees() {
            if (!hasFullAccessRole) {
                archivedEmployees = [];
                selectedArchivedEmployeeIds = new Set();
                renderArchivedEmployeeTable();
                return;
            }
            const payload = await requestJson(`${apiUrl}?action=listArchivedEmployees`);
            if (!payload.success) {
                showHardwareScreen(payload.message || 'Unable to load archived employees.', 'text-red-400');
                return;
            }
            archivedEmployees = payload.employees || [];
            selectedArchivedEmployeeIds = new Set([...selectedArchivedEmployeeIds].filter(id => archivedEmployees.some(emp => emp.id === id)));
            renderArchivedEmployeeTable();
        }

        async function fetchUsers() {
            if (!hasFullAccessRole) {
                users = [];
                selectedUserIds = new Set();
                return;
            }
            const payload = await requestJson(`${apiUrl}?action=listUsers`);
            if (!payload.success) {
                showHardwareScreen(payload.message || 'Unable to load user accounts.', 'text-red-400');
                return;
            }
            users = payload.users;
            selectedUserIds = new Set([...selectedUserIds].filter(id => users.some(user => Number(user.id) === Number(id) && Number(user.id) !== currentUserId)));
            renderUserTable();
        }

        async function fetchEmployees() {
            const payload = await requestJson(`${apiUrl}?action=listEmployees`);
            if (!payload.success) return;
            employees = payload.employees;
            hasLeaveFormFullAccess = hasFullAccessRole;
            syncLeaveFormAccessUi();
            selectedEmployeeIds = new Set([...selectedEmployeeIds].filter(id => employees.some(emp => emp.id === id)));
            populateHrEditorDropdown();
            populateAttendanceEmployeeFilter();
            populateLeaveEmployeeOptions();
            populateTravelEmployeeOptions();
            populateUserUsernameOptions();
            renderEmployeeTable();
        }

        function updateEmployeeSelectionControls() {
            const filteredEmployees = getFilteredEmployees();
            const total = filteredEmployees.length;
            const selectedCount = selectedEmployeeIds.size;
            const selectedFilteredCount = filteredEmployees.filter(emp => selectedEmployeeIds.has(emp.id)).length;
            const label = document.getElementById('employee-selection-count');
            const matchLabel = document.getElementById('employee-match-count');
            const deleteBtn = document.getElementById('btn-delete-selected-employees');
            const selectAll = document.getElementById('employee-select-all');
            const selectFilteredBtn = document.getElementById('btn-select-filtered-employees');
            if (label) {
                label.textContent = `${selectedCount} selected${total !== employees.length ? ` (${selectedFilteredCount} in filter)` : ''}`;
            }
            if (matchLabel) {
                matchLabel.textContent = `${total} match${total === 1 ? '' : 'es'}`;
            }
            if (deleteBtn) {
                deleteBtn.disabled = isViewerRole || selectedCount === 0;
            }
            if (selectFilteredBtn) {
                selectFilteredBtn.disabled = isViewerRole || total === 0;
                selectFilteredBtn.textContent = selectedFilteredCount === total && total > 0 ? 'Unselect Filtered' : 'Select Filtered';
            }
            if (selectAll) {
                selectAll.checked = total > 0 && selectedFilteredCount === total;
                selectAll.indeterminate = selectedFilteredCount > 0 && selectedFilteredCount < total;
                selectAll.disabled = isViewerRole || total === 0;
            }
        }

        function updateUserSelectionControls() {
            const selectableUsers = getFilteredUsers();
            const total = selectableUsers.length;
            const selectedCount = selectedUserIds.size;
            const selectedFilteredCount = selectableUsers.filter(user => selectedUserIds.has(Number(user.id))).length;
            const label = document.getElementById('user-selection-count');
            const matchLabel = document.getElementById('user-match-count');
            const deleteBtn = document.getElementById('btn-delete-selected-users');
            const selectAll = document.getElementById('user-select-all');
            const selectFilteredBtn = document.getElementById('btn-select-filtered-users');
            if (label) {
                label.textContent = `${selectedCount} selected${total !== users.filter(user => Number(user.id) !== currentUserId).length ? ` (${selectedFilteredCount} in filter)` : ''}`;
            }
            if (matchLabel) {
                matchLabel.textContent = `${total} match${total === 1 ? '' : 'es'}`;
            }
            if (deleteBtn) {
                deleteBtn.disabled = isViewerRole || selectedCount === 0;
            }
            if (selectFilteredBtn) {
                selectFilteredBtn.disabled = isViewerRole || total === 0;
                selectFilteredBtn.textContent = selectedFilteredCount === total && total > 0 ? 'Unselect Filtered' : 'Select Filtered';
            }
            if (selectAll) {
                selectAll.checked = total > 0 && selectedFilteredCount === total;
                selectAll.indeterminate = selectedFilteredCount > 0 && selectedFilteredCount < total;
                selectAll.disabled = isViewerRole || total === 0;
            }
        }

        function getEmployeeSearchTerm() {
            return String(document.getElementById('employee-search')?.value || '').trim().toLowerCase();
        }

        function getUserSearchTerm() {
            return String(document.getElementById('user-search')?.value || '').trim().toLowerCase();
        }

        function getArchivedEmployeeSearchTerm() {
            return String(document.getElementById('archived-employee-search')?.value || '').trim().toLowerCase();
        }

        function getFilteredEmployees() {
            const term = getEmployeeSearchTerm();
            if (!term) {
                return employees;
            }
            return employees.filter(emp => [emp.id, emp.name, emp.designation, emp.department].some(value => String(value || '').toLowerCase().includes(term)));
        }

        function getFilteredUsers() {
            const term = getUserSearchTerm();
            const baseUsers = users.filter(user => Number(user.id) !== currentUserId);
            if (!term) {
                return baseUsers;
            }
            return baseUsers.filter(user => [user.username, user.role, user.created_at].some(value => String(value || '').toLowerCase().includes(term)));
        }

        function getFilteredArchivedEmployees() {
            const term = getArchivedEmployeeSearchTerm();
            if (!term) {
                return archivedEmployees;
            }
            return archivedEmployees.filter(emp => [emp.id, emp.name, emp.designation, emp.department].some(value => String(value || '').toLowerCase().includes(term)));
        }

        function toggleEmployeeSelection(id, checked) {
            if (checked) {
                selectedEmployeeIds.add(id);
            } else {
                selectedEmployeeIds.delete(id);
            }
            updateEmployeeSelectionControls();
        }

        function toggleUserSelection(id, checked) {
            const numericId = Number(id);
            if (numericId === currentUserId) {
                return;
            }
            if (checked) {
                selectedUserIds.add(numericId);
            } else {
                selectedUserIds.delete(numericId);
            }
            updateUserSelectionControls();
        }

        function toggleArchivedEmployeeSelection(id, checked) {
            if (checked) {
                selectedArchivedEmployeeIds.add(id);
            } else {
                selectedArchivedEmployeeIds.delete(id);
            }
            updateArchivedEmployeeSelectionControls();
        }

        function toggleAllEmployees(checked) {
            selectFilteredEmployees(checked);
            renderEmployeeTable();
        }

        function toggleAllUsers(checked) {
            selectFilteredUsers(checked);
            renderUserTable();
        }

        function toggleAllArchivedEmployees(checked) {
            selectFilteredArchivedEmployees(checked);
            renderArchivedEmployeeTable();
        }

        function selectFilteredEmployees(checked) {
            const filteredEmployees = getFilteredEmployees();
            if (checked) {
                filteredEmployees.forEach(emp => selectedEmployeeIds.add(emp.id));
            } else {
                filteredEmployees.forEach(emp => selectedEmployeeIds.delete(emp.id));
            }
        }

        function toggleFilteredEmployees() {
            const filteredEmployees = getFilteredEmployees();
            const allSelected = filteredEmployees.length > 0 && filteredEmployees.every(emp => selectedEmployeeIds.has(emp.id));
            selectFilteredEmployees(!allSelected);
            renderEmployeeTable();
        }

        function selectFilteredUsers(checked) {
            const filteredUsers = getFilteredUsers();
            if (checked) {
                filteredUsers.forEach(user => selectedUserIds.add(Number(user.id)));
            } else {
                filteredUsers.forEach(user => selectedUserIds.delete(Number(user.id)));
            }
        }

        function toggleFilteredUsers() {
            const filteredUsers = getFilteredUsers();
            const allSelected = filteredUsers.length > 0 && filteredUsers.every(user => selectedUserIds.has(Number(user.id)));
            selectFilteredUsers(!allSelected);
            renderUserTable();
        }

        function selectFilteredArchivedEmployees(checked) {
            const filteredEmployees = getFilteredArchivedEmployees();
            if (checked) {
                filteredEmployees.forEach(emp => selectedArchivedEmployeeIds.add(emp.id));
            } else {
                filteredEmployees.forEach(emp => selectedArchivedEmployeeIds.delete(emp.id));
            }
        }

        function toggleFilteredArchivedEmployees() {
            const filteredEmployees = getFilteredArchivedEmployees();
            const allSelected = filteredEmployees.length > 0 && filteredEmployees.every(emp => selectedArchivedEmployeeIds.has(emp.id));
            selectFilteredArchivedEmployees(!allSelected);
            renderArchivedEmployeeTable();
        }

        function buildSelectionPreview(items, label, maxItems = 12) {
            const visibleItems = items.slice(0, maxItems);
            const remainder = items.length - visibleItems.length;
            const suffix = remainder > 0 ? `\n...and ${remainder} more ${label}.` : '';
            return visibleItems.join(', ') + suffix;
        }

        function populateLeaveEmployeeOptions() {
            const select = document.getElementById('leave-form-emp-id');
            if (!select) return;
            const selected = select.value;
            select.innerHTML = '<option value="">Select employee</option>';
            [...employees]
                .sort((a, b) => String(a.id).localeCompare(String(b.id)))
                .forEach(emp => {
                    const option = document.createElement('option');
                    option.value = emp.id;
                    option.textContent = `[${emp.id}] ${emp.name}`;
                    select.appendChild(option);
                });
            if (isViewerRole) {
                const own = employees[0];
                if (own) {
                    select.value = own.id;
                }
                select.disabled = true;
            } else {
                select.disabled = false;
            }
            if (selected && [...select.options].some(o => o.value === selected)) {
                select.value = selected;
            }
        }

        function populateTravelEmployeeOptions() {
            const select = document.getElementById('travel-form-emp-id');
            if (!select) return;
            const selected = select.value;
            select.innerHTML = '<option value="">Select employee</option>';
            [...employees]
                .sort((a, b) => String(a.id).localeCompare(String(b.id)))
                .forEach(emp => {
                    const option = document.createElement('option');
                    option.value = emp.id;
                    option.textContent = `[${emp.id}] ${emp.name}`;
                    select.appendChild(option);
                });
            if (selected && [...select.options].some(o => o.value === selected)) {
                select.value = selected;
            }
        }

        function populateAttendanceEmployeeFilter() {
            const select = document.getElementById('filter-emp');
            if (!select) return;
            const selected = select.value;
            select.innerHTML = '<option value="All">All Employees</option>';
            [...employees]
                .sort((a, b) => String(a.id).localeCompare(String(b.id)))
                .forEach(emp => {
                    const option = document.createElement('option');
                    option.value = emp.id;
                    option.textContent = `[${emp.id}] ${emp.name}`;
                    select.appendChild(option);
                });
            if (selected && [...select.options].some(o => o.value === selected)) {
                select.value = selected;
            }
        }

        async function fetchHolidays() {
            const payload = await requestJson(`${apiUrl}?action=listHolidays`);
            if (!payload.success) return;
            holidays = payload.holidays;
            renderHolidayTable();
        }

        function populateHrEditorDropdown() {
            const select = document.getElementById('hr-editor');
            const warning = document.getElementById('hr-editor-warning');
            if (!select) return;
            const selected = select.value;
            select.innerHTML = '<option value="">-- Select HR editor --</option>';
            const hrEditors = employees.filter(emp => Number(emp.can_edit_attendance || 0) === 1);
            hrEditors.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = `[${emp.id}] ${emp.name}`;
                select.appendChild(option);
            });
            if (warning) {
                if (hrEditors.length === 0) {
                    warning.textContent = 'No HR editors configured. Go to Manage Staff, edit a staff member and enable "Can edit attendance".';
                    warning.className = 'text-[10px] text-red-600 mt-1 block font-semibold';
                } else {
                    warning.textContent = 'Select HR editor to unlock Edit / Delete buttons.';
                    warning.className = 'text-[10px] text-slate-500 mt-1 block';
                }
            }
            if (selected && [...select.options].some(o => o.value === selected)) {
                select.value = selected;
            } else if (hrEditors.length === 1) {
                select.value = hrEditors[0].id;
            }
        }

        function updateLiveStats() {
            document.getElementById('stat-headcount').innerText = employees.length;
            document.getElementById('stat-production').innerText = employees.filter(e => e.department === 'Production').length;
            document.getElementById('stat-office').innerText = employees.filter(e => e.department === 'Office').length;
        }

        function updateArchivedEmployeeSelectionControls() {
            const filteredEmployees = getFilteredArchivedEmployees();
            const total = filteredEmployees.length;
            const selectedCount = selectedArchivedEmployeeIds.size;
            const selectedFilteredCount = filteredEmployees.filter(emp => selectedArchivedEmployeeIds.has(emp.id)).length;
            const countLabel = document.getElementById('archived-employee-count');
            const selectionLabel = document.getElementById('archived-selection-count');
            const matchLabel = document.getElementById('archived-match-count');
            const selectAll = document.getElementById('archived-employee-select-all');
            const selectFilteredBtn = document.getElementById('btn-select-filtered-archived-employees');
            const deleteBtn = document.getElementById('btn-delete-selected-archived-employees');
            const restoreBtn = document.getElementById('btn-restore-selected-employees');

            if (countLabel) {
                countLabel.textContent = `${archivedEmployees.length} archived`;
            }
            if (selectionLabel) {
                selectionLabel.textContent = `${selectedCount} selected${total !== archivedEmployees.length ? ` (${selectedFilteredCount} in filter)` : ''}`;
            }
            if (matchLabel) {
                matchLabel.textContent = `${total} match${total === 1 ? '' : 'es'}`;
            }
            if (selectAll) {
                selectAll.checked = total > 0 && selectedFilteredCount === total;
                selectAll.indeterminate = selectedFilteredCount > 0 && selectedFilteredCount < total;
                selectAll.disabled = isViewerRole || total === 0;
            }
            if (selectFilteredBtn) {
                selectFilteredBtn.disabled = isViewerRole || total === 0;
                selectFilteredBtn.textContent = selectedFilteredCount === total && total > 0 ? 'Unselect Filtered' : 'Select Filtered';
            }
            if (deleteBtn) {
                deleteBtn.disabled = isViewerRole || selectedCount === 0;
            }
            if (restoreBtn) {
                restoreBtn.disabled = isViewerRole || selectedCount === 0;
            }
        }

        function renderArchivedEmployeeTable() {
            const tbody = document.getElementById('archived-employee-table-body');
            if (!tbody) return;
            tbody.innerHTML = '';
            const filteredEmployees = getFilteredArchivedEmployees();
            if (filteredEmployees.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-400 italic">No archived staff matched the current search.</td></tr>`;
                updateArchivedEmployeeSelectionControls();
                return;
            }
            filteredEmployees.forEach(emp => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-amber-50 border-b border-amber-100';
                tr.innerHTML = `
                    <td class="p-4 text-center"><input type="checkbox" class="archived-employee-row-checkbox rounded border-amber-300 text-amber-600 focus:ring-amber-500/20" data-employee-id="${escapeHtml(emp.id)}" ${selectedArchivedEmployeeIds.has(emp.id) ? 'checked' : ''} ${isViewerRole ? 'disabled' : ''}></td>
                    <td class="p-4 font-mono font-bold text-slate-900">${emp.id}</td>
                    <td class="p-4">${emp.name}</td>
                    <td class="p-4 text-slate-600">${emp.designation || '<span class="text-slate-400 italic text-xs">—</span>'}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold ${emp.department === 'Production' ? 'bg-purple-100 text-purple-700' : 'bg-cyan-100 text-cyan-700'}">${emp.department}</span></td>
                    <td class="p-4">${Number(emp.can_edit_attendance || 0) === 1 ? '<span class="px-2 py-1 rounded text-xs font-bold bg-emerald-100 text-emerald-700">HR</span>' : '<span class="text-slate-400 text-xs">No</span>'}</td>
                    <td class="p-4 text-right">${isViewerRole ? '<span class="text-slate-300 text-xs">View only</span>' : `<div class="inline-flex items-center gap-3"><button onclick="restoreEmployee('${emp.id}')" class="text-amber-700 hover:text-amber-900 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="archive-restore" class="w-3.5 h-3.5"></i> Restore</button><button onclick="deleteArchivedEmployee('${emp.id}')" class="text-rose-600 hover:text-rose-900 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button></div>`}</td>
                `;
                tbody.appendChild(tr);
            });
            document.querySelectorAll('.archived-employee-row-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', event => toggleArchivedEmployeeSelection(event.target.dataset.employeeId || '', event.target.checked));
            });
            updateArchivedEmployeeSelectionControls();
            lucide.createIcons();
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            document.querySelectorAll('[id^="btn-tab-"]').forEach(btn => btn.classList.remove('bg-blue-600', 'text-white'));
            document.getElementById(`btn-${tabId}`).classList.add('bg-blue-600', 'text-white');
            if (tabId === 'tab-vehicle-usage') {
                renderVehicleUsageGrid();
            }
            if (tabId === 'tab-attendance') {
                renderAttendanceGrid();
            }
            if (tabId === 'tab-leave-management') {
                renderLeaveGrid();
            }
            if (tabId === 'tab-travel-form') {
                renderTravelGrid();
            }
            if (tabId === 'tab-archived-employees') {
                renderArchivedEmployeeTable();
            }
        }

        function getSelectedHrEditorId() {
            return document.getElementById('hr-editor').value;
        }

        async function editAttendanceDay(empId, date) {
            const hrId = getSelectedHrEditorId();
            if (!hrId) {
                alert('Please select an HR editor from the dropdown above before editing attendance.\n\nIf the list is empty, go to Manage Staff, edit a staff member and tick "Can edit attendance".');
                return;
            }

            const record = attendanceRecords.find(item => item.empId === empId && item.date === date);
            if (!record) {
                alert('Attendance record not found. Please refresh the page.');
                return;
            }

            const times = prompt(
                `Edit attendance for ${record.name} on ${date}.\n\nEnter session times as comma-separated HH:MM or HH:MM:SS pairs (check-in,check-out per session).\nExample for 2 sessions: 08:00,12:00,13:00,17:00`,
                record.rawTimes || ''
            );
            if (times === null) return;
            if (!times.trim()) {
                alert('Session times cannot be empty.');
                return;
            }

            const vehicleName = prompt('Office vehicle used (leave blank if none)', record.vehicleName || '');
            if (vehicleName === null) return;
            const vehiclePurpose = prompt('Vehicle purpose (leave blank if none)', record.vehiclePurpose || '');
            if (vehiclePurpose === null) return;

            const body = new URLSearchParams();
            body.append('action', 'updateAttendanceDay');
            body.append('hrId', hrId);
            body.append('empId', empId);
            body.append('date', date);
            body.append('times', times.trim());
            body.append('vehicleName', vehicleName.trim());
            body.append('vehiclePurpose', vehiclePurpose.trim());

            let payload;
            payload = await requestJson(apiUrl, { method: 'POST', body });
            if (!payload.success && !payload.message) {
                alert('Request failed. Please check your connection and try again.');
                return;
            }

            if (payload.success) {
                showHardwareScreen(payload.message, 'text-emerald-400');
                await renderAttendanceGrid();
            } else {
                alert('Update failed: ' + payload.message);
            }
        }

        async function deleteAttendanceDay(empId, date) {
            const hrId = getSelectedHrEditorId();
            if (!hrId) {
                alert('Please select an HR editor from the dropdown above before deleting attendance.\n\nIf the list is empty, go to Manage Staff, edit a staff member and tick "Can edit attendance".');
                return;
            }
            if (!confirm(`Delete ALL attendance records for ${empId} on ${date}?\nThis cannot be undone.`)) return;

            const body = new URLSearchParams();
            body.append('action', 'deleteAttendanceDay');
            body.append('hrId', hrId);
            body.append('empId', empId);
            body.append('date', date);

            let payload;
            payload = await requestJson(apiUrl, { method: 'POST', body });
            if (!payload.success && !payload.message) {
                alert('Request failed. Please check your connection and try again.');
                return;
            }

            if (payload.success) {
                showHardwareScreen(payload.message, 'text-emerald-400');
                await renderAttendanceGrid();
            } else {
                alert('Delete failed: ' + payload.message);
            }
        }

        async function renderAttendanceGrid() {
            const start = document.getElementById('filter-start').value;
            const end = document.getElementById('filter-end').value;
            const department = document.getElementById('filter-dept').value;
            const empId = document.getElementById('filter-emp').value;
            if (!isValidDateRange(start, end)) {
                alert('Please choose a valid attendance date range.');
                return;
            }
            const payload = await requestJson(`${apiUrl}?action=attendanceRecords&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&department=${encodeURIComponent(department)}&empId=${encodeURIComponent(empId)}`);
            const tbody = document.getElementById('attendance-table-body');
            const summaryBox = document.getElementById('attendance-summary');
            tbody.innerHTML = '';
            if (!payload.success || payload.records.length === 0) {
                attendanceRecords = [];
                tbody.innerHTML = `<tr><td colspan="14" class="p-8 text-center text-slate-400 italic">No attendance records found for the selected filters.</td></tr>`;
                if (summaryBox) {
                    summaryBox.classList.add('hidden');
                    summaryBox.innerHTML = '';
                }
                return;
            }
            attendanceRecords = payload.records;
            let totalOt = 0;
            payload.records.forEach(record => {
                totalOt += record.otHours;
                const sessionText = record.sessionText || 'No complete session';
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 border-b border-slate-150 transition';
                tr.innerHTML = `
                    <td class="p-4 font-mono font-bold text-slate-900">${record.empId}</td>
                    <td class="p-4 font-medium text-slate-800">${record.name}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold ${record.department === 'Production' ? 'bg-purple-100 text-purple-700' : 'bg-cyan-100 text-cyan-700'}">${record.department}</span></td>
                    <td class="p-4 font-mono">${record.date}</td>
                    <td class="p-4 text-xs font-semibold"><span class="px-2 py-1 rounded ${record.isSpecial ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700'}">${record.dayType}</span></td>
                    <td class="p-4 text-xs font-mono leading-5 text-slate-700">${sessionText}</td>
                    <td class="p-4 text-xs font-mono leading-5 text-slate-700">${record.vehicleText || 'No vehicle used'}</td>
                    <td class="p-4 text-xs text-slate-700">${hasFullAccessRole ? (record.gpsPointCount > 0 ? `<button onclick="openGpsModal('${record.empId}','${record.date}')" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 font-semibold text-emerald-700 transition hover:bg-emerald-100"><i data-lucide="map-pinned" class="w-3.5 h-3.5"></i> ${record.gpsPointCount} point${record.gpsPointCount === 1 ? '' : 's'}</button>` : '<span class="text-slate-400">No GPS</span>') : '<span class="text-slate-300">Restricted</span>'}</td>
                    <td class="p-4 text-xs font-semibold">${record.leaveType === 'Full Leave' ? '<span class="px-2 py-1 rounded bg-red-100 text-red-700">Full Leave</span>' : record.leaveType === 'Half Leave' ? '<span class="px-2 py-1 rounded bg-amber-100 text-amber-700">Half Leave</span>' : record.leaveType === 'Present' ? '<span class="px-2 py-1 rounded bg-emerald-100 text-emerald-700">Present</span>' : '<span class="text-slate-400">-</span>'}</td>
                    <td class="p-4 font-mono">${Number(record.leaveDays || 0).toFixed(2)}</td>
                    <td class="p-4 font-mono">${record.regularHours.toFixed(2)}h</td>
                    <td class="p-4 font-mono text-amber-600 font-bold">${record.otHours > 0 ? '+' + record.otHours.toFixed(2) + 'h' : '0.00h'}</td>
                    <td class="p-4 font-mono font-extrabold text-right text-slate-900">${record.totalHours.toFixed(2)}h</td>
                    <td class="p-4 text-right space-x-2">
                        ${isViewerRole ? '<span class="text-slate-300 text-xs">View only</span>' : `<button onclick="editAttendanceDay('${record.empId}','${record.date}')" class="text-blue-600 hover:text-blue-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit</button>
                        <button onclick="deleteAttendanceDay('${record.empId}','${record.date}')" class="text-red-600 hover:text-red-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>`}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            document.getElementById('stat-ot').innerText = `${totalOt.toFixed(2)}h`;

            if (summaryBox) {
                const summaryRows = payload.summary || [];
                const totalLeave = summaryRows.reduce((sum, row) => sum + Number(row.leaveDays || 0), 0);
                const totalHours = summaryRows.reduce((sum, row) => sum + Number(row.totalHours || 0), 0);
                const totalOtHours = summaryRows.reduce((sum, row) => sum + Number(row.otHours || 0), 0);
                const totalHalf = summaryRows.reduce((sum, row) => sum + Number(row.halfLeaveDays || 0), 0);
                const totalFull = summaryRows.reduce((sum, row) => sum + Number(row.fullLeaveDays || 0), 0);
                summaryBox.classList.remove('hidden');
                summaryBox.innerHTML = `
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 text-sm">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs text-slate-500 uppercase">Employees</div><div class="font-bold text-slate-900 mt-1">${summaryRows.length}</div></div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs text-slate-500 uppercase">Total Work Hours</div><div class="font-bold text-slate-900 mt-1">${totalHours.toFixed(2)}h</div></div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs text-slate-500 uppercase">Total OT Hours</div><div class="font-bold text-amber-700 mt-1">${totalOtHours.toFixed(2)}h</div></div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs text-slate-500 uppercase">Leave Days</div><div class="font-bold text-red-700 mt-1">${totalLeave.toFixed(2)}</div></div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs text-slate-500 uppercase">Half / Full Leave</div><div class="font-bold text-slate-900 mt-1">${totalHalf.toFixed(2)} / ${totalFull.toFixed(2)}</div></div>
                    </div>
                `;
            }

            lucide.createIcons();
        }

        async function handleEmployeeSubmit(event) {
            event.preventDefault();
            const id = document.getElementById('emp-form-id').value.trim();
            const name = document.getElementById('emp-form-name').value.trim();
            const designation = document.getElementById('emp-form-designation').value.trim();
            const department = document.getElementById('emp-form-dept').value;
            const canEditAttendance = document.getElementById('emp-form-can-edit-attendance').checked;

            if (!/^[A-Za-z0-9_.-]{1,20}$/.test(id)) {
                alert('Employee ID must be 1-20 chars using letters, numbers, dot, dash, or underscore.');
                return;
            }
            if (name.length < 2 || name.length > 100) {
                alert('Employee name must be between 2 and 100 characters.');
                return;
            }
            if (designation.length > 100) {
                alert('Designation cannot exceed 100 characters.');
                return;
            }
            if (!['Production', 'Office'].includes(department)) {
                alert('Please select a valid department.');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'saveEmployee');
            formData.append('id', id);
            formData.append('name', name);
            formData.append('designation', designation);
            formData.append('department', department);
            formData.append('canEditAttendance', canEditAttendance ? '1' : '0');
            const payload = await requestJson(apiUrl, { method: 'POST', body: formData });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                resetEmployeeForm();
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                await renderAttendanceGrid();
                await renderVehicleUsageGrid();
            }
        }

        function editEmployee(id) {
            const employee = employees.find(emp => emp.id === id);
            if (!employee) return;
            document.getElementById('emp-form-id').value = employee.id;
            document.getElementById('emp-form-id').disabled = true;
            document.getElementById('emp-form-name').value = employee.name;
            document.getElementById('emp-form-designation').value = employee.designation || '';
            document.getElementById('emp-form-dept').value = employee.department;
            document.getElementById('emp-form-can-edit-attendance').checked = Number(employee.can_edit_attendance || 0) === 1;
            document.getElementById('emp-form-mode').value = 'edit';
            document.getElementById('employee-form-title').innerHTML = `<i data-lucide="edit-3" class="w-5 h-5 text-blue-600"></i> Modify Staff Profile`;
            document.getElementById('emp-submit-btn').textContent = 'Update Details';
            lucide.createIcons();
            switchTab('tab-employees');
        }

        async function deleteEmployee(id) {
            if (!confirm('Archive this employee and remove login access? Attendance logs will be preserved.')) return;
            const body = new URLSearchParams();
            body.append('action', 'deleteEmployee');
            body.append('id', String(id));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Deleting employee...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedEmployeeIds.delete(String(id));
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                await renderAttendanceGrid();
                await renderVehicleUsageGrid();
                updateLiveStats();
            }
        }

        async function deleteSelectedEmployees() {
            if (isViewerRole) {
                alert('Only admin, HR, and IT users can delete staff records.');
                return;
            }
            const ids = [...selectedEmployeeIds];
            if (ids.length === 0) {
                alert('Select at least one employee first.');
                return;
            }
            const preview = buildSelectionPreview(ids, 'employee record(s)');
            if (!confirm(`Delete ${ids.length} selected employee record(s) and their linked login users?\n\nSelected Staff IDs:\n${preview}`)) return;

            const body = new URLSearchParams();
            body.append('action', 'deleteEmployees');
            body.append('ids', JSON.stringify(ids));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Deleting selected employees...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedEmployeeIds = new Set();
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                await renderAttendanceGrid();
                await renderVehicleUsageGrid();
                updateLiveStats();
            }
        }

        async function restoreEmployee(id) {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can restore archived staff.');
                return;
            }
            if (!confirm(`Restore archived employee "${id}" and recreate the linked login user?`)) return;

            const body = new URLSearchParams();
            body.append('action', 'restoreEmployee');
            body.append('id', String(id));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Restoring archived employee...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedArchivedEmployeeIds.delete(String(id));
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                await renderAttendanceGrid();
                await renderVehicleUsageGrid();
                updateLiveStats();
            }
        }

        async function restoreSelectedEmployees() {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can restore archived staff.');
                return;
            }
            const ids = [...selectedArchivedEmployeeIds];
            if (ids.length === 0) {
                alert('Select at least one archived employee first.');
                return;
            }
            const preview = buildSelectionPreview(ids, 'archived employee(s)');
            if (!confirm(`Restore ${ids.length} archived employee(s) and recreate their linked login users?\n\nSelected Staff IDs:\n${preview}`)) return;

            const body = new URLSearchParams();
            body.append('action', 'restoreEmployees');
            body.append('ids', JSON.stringify(ids));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Restoring selected employees...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedArchivedEmployeeIds = new Set();
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                await renderAttendanceGrid();
                await renderVehicleUsageGrid();
                updateLiveStats();
            }
        }

        async function deleteArchivedEmployee(id) {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can delete archived staff.');
                return;
            }
            if (!confirm(`Delete archived employee "${id}" from staff directories permanently?\n\nAttendance history will stay safe, but this staff profile will no longer appear in Archived Staff and cannot be restored.`)) return;

            const body = new URLSearchParams();
            body.append('action', 'purgeArchivedEmployee');
            body.append('id', String(id));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Deleting archived employee...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedArchivedEmployeeIds.delete(String(id));
                await fetchArchivedEmployees();
                await fetchUsers();
                updateLiveStats();
            }
        }

        async function deleteSelectedArchivedEmployees() {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can delete archived staff.');
                return;
            }
            const ids = [...selectedArchivedEmployeeIds];
            if (ids.length === 0) {
                alert('Select at least one archived employee first.');
                return;
            }
            const preview = buildSelectionPreview(ids, 'archived employee(s)');
            if (!confirm(`Delete ${ids.length} archived employee record(s) from staff directories permanently?\n\nSelected Staff IDs:\n${preview}\n\nAttendance history will be preserved, but these profiles will no longer appear in Archived Staff and cannot be restored.`)) return;

            const body = new URLSearchParams();
            body.append('action', 'purgeArchivedEmployees');
            body.append('ids', JSON.stringify(ids));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Deleting archived employees...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedArchivedEmployeeIds = new Set();
                await fetchArchivedEmployees();
                await fetchUsers();
                updateLiveStats();
            }
        }

        function resetEmployeeForm() {
            document.getElementById('employee-form').reset();
            document.getElementById('emp-form-id').disabled = false;
            document.getElementById('emp-form-can-edit-attendance').checked = false;
            document.getElementById('emp-form-mode').value = 'add';
            document.getElementById('employee-form-title').innerHTML = `<i data-lucide="user-plus" class="w-5 h-5 text-blue-600"></i> Add New Employee Profile`;
            document.getElementById('emp-submit-btn').textContent = 'Register Profile';
            lucide.createIcons();
        }

        async function renderEmployeeTable() {
            const tbody = document.getElementById('employee-table-body');
            tbody.innerHTML = '';
            const filteredEmployees = getFilteredEmployees();
            if (filteredEmployees.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-400 italic">No staff matched the current search.</td></tr>`;
                updateEmployeeSelectionControls();
                return;
            }
            filteredEmployees.forEach(emp => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 border-b border-slate-150';
                tr.innerHTML = `
                    <td class="p-4 text-center"><input type="checkbox" class="employee-row-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500/20" data-employee-id="${escapeHtml(emp.id)}" ${selectedEmployeeIds.has(emp.id) ? 'checked' : ''} ${isViewerRole ? 'disabled' : ''}></td>
                    <td class="p-4 font-mono font-bold text-slate-900">${emp.id}</td>
                    <td class="p-4">${emp.name}</td>
                    <td class="p-4 text-slate-600">${emp.designation || '<span class="text-slate-400 italic text-xs">—</span>'}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold ${emp.department === 'Production' ? 'bg-purple-100 text-purple-700' : 'bg-cyan-100 text-cyan-700'}">${emp.department}</span></td>
                    <td class="p-4">${Number(emp.can_edit_attendance || 0) === 1 ? '<span class="px-2 py-1 rounded text-xs font-bold bg-emerald-100 text-emerald-700">HR</span>' : '<span class="text-slate-400 text-xs">No</span>'}</td>
                    <td class="p-4 text-right space-x-2">
                        ${isViewerRole ? '<span class="text-slate-300 text-xs">View only</span>' : `<button onclick="editEmployee('${emp.id}')" class="text-blue-600 hover:text-blue-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit</button>
                        <button onclick="deleteEmployee('${emp.id}')" class="text-red-600 hover:text-red-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>`}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            document.querySelectorAll('.employee-row-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', event => toggleEmployeeSelection(event.target.dataset.employeeId || '', event.target.checked));
            });
            updateEmployeeSelectionControls();
            lucide.createIcons();
        }

        async function handleBulkImport() {
            const fileInput = document.getElementById('bulk-import-file');
            const resultBox = document.getElementById('bulk-import-result');
            const btn = document.getElementById('btn-bulk-import');

            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select an .xlsx file first.');
                return;
            }
            const file = fileInput.files[0];
            if (!file.name.toLowerCase().endsWith('.xlsx')) {
                alert('Only .xlsx files are accepted.');
                return;
            }

            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            resultBox.className = 'mt-3 text-sm text-slate-500';
            resultBox.textContent = 'Importing…';
            resultBox.classList.remove('hidden');

            const formData = new FormData();
            formData.append('action', 'bulkImportEmployees');
            formData.append('xlsxFile', file);

            let payload;
            payload = await requestJson(apiUrl, { method: 'POST', body: formData });
            if (!payload.success && !payload.message) {
                resultBox.className = 'mt-3 text-sm text-red-600';
                resultBox.textContent = 'Request failed. Check your connection and try again.';
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-not-allowed');
                return;
            }

            if (!payload.success) {
                resultBox.className = 'mt-3 text-sm text-red-600';
                resultBox.textContent = payload.message;
            } else {
                const errNote = payload.errors && payload.errors.length ? ' Errors: ' + payload.errors.slice(0, 3).join(' | ') : '';
                resultBox.className = 'mt-3 text-sm text-emerald-700 font-semibold';
                resultBox.textContent = payload.message + errNote;
                fileInput.value = '';
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                await renderAttendanceGrid();
                await renderVehicleUsageGrid();
                updateLiveStats();
            }

            btn.disabled = false;
            btn.classList.remove('opacity-70', 'cursor-not-allowed');
        }

        async function handleHolidaySubmit(event) {
            event.preventDefault();
            const date = document.getElementById('holiday-form-date').value;
            const description = document.getElementById('holiday-form-desc').value.trim();

            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                alert('Holiday date must be in YYYY-MM-DD format.');
                return;
            }
            if (description.length < 2 || description.length > 255) {
                alert('Holiday description must be between 2 and 255 characters.');
                return;
            }

            const body = new URLSearchParams();
            body.append('action', 'saveHoliday');
            body.append('date', date);
            body.append('description', description);
            const payload = await requestJson(apiUrl, { method: 'POST', body });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                document.getElementById('holiday-form').reset();
                await fetchHolidays();
                await renderAttendanceGrid();
            }
        }

        async function renderVehicleUsageGrid() {
            const start = document.getElementById('vehicle-filter-start').value;
            const end = document.getElementById('vehicle-filter-end').value;
            const department = document.getElementById('vehicle-filter-dept').value;
            const payload = await requestJson(`${apiUrl}?action=vehicleUsageRecords&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&department=${encodeURIComponent(department)}`);
            const tbody = document.getElementById('vehicle-usage-table-body');
            tbody.innerHTML = '';

            if (!payload.success || payload.records.length === 0) {
                vehicleUsageRecords = [];
                tbody.innerHTML = `<tr><td colspan="11" class="p-8 text-center text-slate-400 italic">No vehicle usage records found for the selected filters.</td></tr>`;
                return;
            }

            vehicleUsageRecords = payload.records;
            payload.records.forEach(record => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 border-b border-slate-150 transition';
                tr.innerHTML = `
                    <td class="p-4 font-mono font-bold text-slate-900">${record.empId}</td>
                    <td class="p-4 font-medium text-slate-800">${record.name}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold ${record.department === 'Production' ? 'bg-purple-100 text-purple-700' : 'bg-cyan-100 text-cyan-700'}">${record.department}</span></td>
                    <td class="p-4 font-mono">${record.date}</td>
                    <td class="p-4 font-mono text-xs">${record.startTime || ''}</td>
                    <td class="p-4 font-mono text-xs">${record.endTime || '<span class="text-amber-600 font-semibold">Open</span>'}</td>
                    <td class="p-4 font-mono text-xs text-slate-700">${record.durationText || (record.endTime ? '0m' : 'Open')}</td>
                    <td class="p-4 font-mono text-xs text-slate-700">${record.vehicleName}</td>
                    <td class="p-4 text-xs text-slate-700">${record.vehiclePurpose}</td>
                    <td class="p-4 text-xs font-semibold">${record.status === 'Completed' ? '<span class="px-2 py-1 rounded bg-emerald-100 text-emerald-700">Completed</span>' : '<span class="px-2 py-1 rounded bg-amber-100 text-amber-700">In progress</span>'}</td>
                    <td class="p-4 text-right space-x-2">
                        ${hasFullAccessRole ? `<button onclick='editVehicleUsage(${JSON.stringify(record.sessionToken)})' class="text-blue-600 hover:text-blue-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit</button>
                        <button onclick='deleteVehicleUsage(${JSON.stringify(record.sessionToken)})' class="text-red-600 hover:text-red-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>` : '<span class="text-slate-300 text-xs">View only</span>'}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
        }

        async function editVehicleUsage(sessionToken) {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can edit vehicle sessions.');
                return;
            }
            const record = vehicleUsageRecords.find(item => item.sessionToken === sessionToken);
            if (!record) {
                alert('Vehicle session not found. Please refresh and try again.');
                return;
            }

            const empId = prompt('Employee ID', record.empId || '');
            if (empId === null) return;
            const startTimestamp = prompt('From time (YYYY-MM-DD HH:MM:SS)', record.startTimestamp || '');
            if (startTimestamp === null) return;
            const endTimestamp = prompt('To time (leave blank if open)', record.endTimestamp || '');
            if (endTimestamp === null) return;
            const vehicleName = prompt('Vehicle number', record.vehicleName || '');
            if (vehicleName === null) return;
            const vehiclePurpose = prompt('Vehicle purpose', record.vehiclePurpose || '');
            if (vehiclePurpose === null) return;

            const body = new URLSearchParams();
            body.append('action', 'updateVehicleUsageSession');
            body.append('sessionToken', sessionToken);
            body.append('empId', empId.trim());
            body.append('startTimestamp', startTimestamp.trim());
            body.append('endTimestamp', endTimestamp.trim());
            body.append('vehicleName', vehicleName.trim());
            body.append('vehiclePurpose', vehiclePurpose.trim());

            const payload = await requestJson(apiUrl, { method: 'POST', body });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                await renderVehicleUsageGrid();
            }
        }

        async function deleteVehicleUsage(sessionToken) {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can delete vehicle sessions.');
                return;
            }
            const record = vehicleUsageRecords.find(item => item.sessionToken === sessionToken);
            if (!record) {
                alert('Vehicle session not found. Please refresh and try again.');
                return;
            }
            if (!confirm('Delete this vehicle session and all its entries?')) return;
            const params = new URLSearchParams();
            params.append('action', 'deleteVehicleUsageSession');
            params.append('sessionToken', sessionToken);
            params.append('empId', record.empId || '');
            params.append('startTimestamp', record.startTimestamp || '');
            params.append('endTimestamp', record.endTimestamp || '');
            params.append('vehicleName', record.vehicleName || '');
            params.append('vehiclePurpose', record.vehiclePurpose || '');
            const payload = await requestJson(apiUrl, { method: 'POST', body: params }, { isMutation: true, busyMessage: 'Deleting vehicle session...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                await renderVehicleUsageGrid();
            }
        }

        async function handleUserSubmit(event) {
            event.preventDefault();
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can manage login accounts.');
                return;
            }

            const id = Number(document.getElementById('user-form-id').value || 0);
            const username = document.getElementById('user-form-username').value.trim();
            const password = document.getElementById('user-form-password').value;
            let role = getAutoRoleForUsername(username);
            const currentUser = users.find(item => Number(item.id) === id);

            if (id <= 0 && !role) {
                setUserFormFeedback('Please select a valid Staff ID from Manage Staff.', false);
                return;
            }
            if (!role && currentUser && currentUser.role === 'admin') {
                role = 'admin';
            }
            if (!role) {
                role = 'viewer';
            }

            document.getElementById('user-form-role').value = role;

            const body = new URLSearchParams();
            body.append('action', 'saveUser');
            if (id > 0) {
                body.append('id', String(id));
            }
            body.append('username', username);
            body.append('password', password);
            body.append('role', role);

            let payload;
            payload = await requestJson(apiUrl, { method: 'POST', body });
            if (!payload.success && !payload.message) {
                setUserFormFeedback('Could not contact the server while creating the login user.', false);
                return;
            }

            setUserFormFeedback(payload.message || '', !!payload.success);
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');

            if (payload.success) {
                resetUserForm();
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
            }
        }

        async function renderUserTable() {
            const tbody = document.getElementById('user-table-body');
            if (!tbody) return;
            tbody.innerHTML = '';

            const searchTerm = getUserSearchTerm();
            const filteredUsers = searchTerm
                ? users.filter(user => [user.username, user.role, user.created_at].some(value => String(value || '').toLowerCase().includes(searchTerm)))
                : users;

            if (filteredUsers.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-slate-400 italic">No users matched the current search.</td></tr>`;
                updateUserSelectionControls();
                return;
            }

            filteredUsers.forEach(user => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 border-b border-slate-150';
                const isCurrent = Number(user.id) === currentUserId;
                tr.innerHTML = `
                    <td class="p-4 text-center"><input type="checkbox" class="user-row-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500/20" data-user-id="${user.id}" ${selectedUserIds.has(Number(user.id)) ? 'checked' : ''} ${isViewerRole || isCurrent ? 'disabled' : ''}></td>
                    <td class="p-4 font-mono font-bold text-slate-900">${user.username}${isCurrent ? ' <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 align-middle">You</span>' : ''}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold ${user.role === 'admin' ? 'bg-blue-100 text-blue-700' : user.role === 'hr' ? 'bg-emerald-100 text-emerald-700' : user.role === 'it' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'}">${user.role}</span></td>
                    <td class="p-4 font-mono text-xs">${user.created_at || ''}</td>
                    <td class="p-4 text-right space-x-2">
                        ${isViewerRole ? '<span class="text-slate-300 text-xs">View only</span>' : `<button onclick="editUser(${user.id})" class="text-blue-600 hover:text-blue-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit</button>
                        <button onclick='deleteUser(${user.id}, ${JSON.stringify(String(user.username))})' ${isCurrent ? 'disabled' : ''} class="${isCurrent ? 'text-slate-300 cursor-not-allowed' : 'text-red-600 hover:text-red-950'} font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>`}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            document.querySelectorAll('.user-row-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', event => toggleUserSelection(event.target.dataset.userId || '0', event.target.checked));
            });
            updateUserSelectionControls();
            lucide.createIcons();
        }

        function editUser(id) {
            const user = users.find(item => Number(item.id) === Number(id));
            if (!user) return;

            document.getElementById('user-form-id').value = String(user.id);
            const usernameSelect = document.getElementById('user-form-username');
            if (![...usernameSelect.options].some(option => option.value === user.username)) {
                const legacyOption = document.createElement('option');
                legacyOption.value = user.username;
                legacyOption.textContent = `${user.username} - Legacy User`;
                usernameSelect.appendChild(legacyOption);
            }
            usernameSelect.value = user.username;
            document.getElementById('user-form-password').value = '';
            document.getElementById('user-form-role').value = getAutoRoleForUsername(user.username) || user.role;
            applyUserPasswordPolicy(document.getElementById('user-form-role').value, true);
            document.getElementById('user-form-title').innerHTML = '<i data-lucide="edit-3" class="w-5 h-5 text-blue-600"></i> Update Login User';
            document.getElementById('user-submit-btn').textContent = 'Update User';
            setUserFormFeedback('', true);
            lucide.createIcons();
            switchTab('tab-employees');
        }

        async function deleteUser(id, username) {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can delete login accounts.');
                return;
            }
            if (!confirm(`Delete login user "${username}"?`)) return;

            const body = new URLSearchParams();
            body.append('action', 'deleteUser');
            body.append('id', String(id));

            let payload;
            payload = await requestJson(apiUrl, { method: 'POST', body });
            if (!payload.success && !payload.message) {
                setUserFormFeedback('Could not contact the server while deleting the login user.', false);
                showHardwareScreen('Could not contact the server while deleting the login user.', 'text-red-400');
                return;
            }

            setUserFormFeedback(payload.message || '', !!payload.success);
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedUserIds.delete(Number(id));
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                updateLiveStats();
            }
        }

        async function deleteSelectedUsers() {
            if (!hasFullAccessRole) {
                alert('Only admin, HR, and IT users can delete login accounts.');
                return;
            }
            const ids = [...selectedUserIds];
            if (ids.length === 0) {
                alert('Select at least one user first.');
                return;
            }
            const previewNames = users
                .filter(user => ids.includes(Number(user.id)))
                .map(user => String(user.username));
            const preview = buildSelectionPreview(previewNames, 'user account(s)');
            if (!confirm(`Delete ${ids.length} selected login account(s)? Linked staff profiles will also be deleted when the User ID matches a registered employee.\n\nSelected User IDs:\n${preview}`)) return;

            const body = new URLSearchParams();
            body.append('action', 'deleteUsers');
            body.append('ids', JSON.stringify(ids));

            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Deleting selected users...' });
            setUserFormFeedback(payload.message || '', !!payload.success);
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                selectedUserIds = new Set();
                await fetchEmployees();
                await fetchArchivedEmployees();
                await fetchUsers();
                await renderAttendanceGrid();
                await renderVehicleUsageGrid();
                updateLiveStats();
            }
        }

        function resetUserForm() {
            const form = document.getElementById('user-form');
            form.reset();
            document.getElementById('user-form-id').value = '';
            populateUserUsernameOptions();
            document.getElementById('user-form-title').innerHTML = '<i data-lucide="shield-check" class="w-5 h-5 text-blue-600"></i> Create Login User (User ID & Password)';
            document.getElementById('user-submit-btn').textContent = 'Create User';
            document.getElementById('user-form-role').value = 'viewer';
            applyUserPasswordPolicy('viewer', false);
            setUserFormFeedback('', true);
            lucide.createIcons();
        }

        async function renderHolidayTable() {
            const tbody = document.getElementById('holiday-table-body');
            tbody.innerHTML = '';
            holidays.forEach(holiday => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 border-b border-slate-150';
                tr.innerHTML = `
                    <td class="p-4 font-mono font-bold text-slate-900">${holiday.date}</td>
                    <td class="p-4 font-medium text-slate-700">${holiday.description}</td>
                    <td class="p-4 text-right">${isViewerRole ? '<span class="text-slate-300 text-xs">View only</span>' : `<button onclick="deleteHoliday('${holiday.date}')" class="text-red-600 hover:text-red-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>`}</td>
                `;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
        }

        async function deleteHoliday(date) {
            if (!confirm('Remove this holiday entry?')) return;
            const body = new URLSearchParams();
            body.append('action', 'deleteHoliday');
            body.append('date', date);
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Removing holiday...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            await fetchHolidays();
            await renderAttendanceGrid();
        }

        async function renderLeaveGrid() {
            const start = document.getElementById('leave-filter-start').value;
            const end = document.getElementById('leave-filter-end').value;
            const department = document.getElementById('leave-filter-dept').value;
            const payload = await requestJson(`${apiUrl}?action=listLeaveRequests&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&department=${encodeURIComponent(department)}`);
            const tbody = document.getElementById('leave-table-body');
            tbody.innerHTML = '';
            if (!payload.success || payload.leaveRequests.length === 0) {
                leaveRequests = [];
                tbody.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-slate-400 italic">No leave requests found for the selected filters.</td></tr>`;
                return;
            }

            leaveRequests = payload.leaveRequests;
            payload.leaveRequests.forEach(record => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 border-b border-slate-150';
                const statusClass = record.status === 'Approved'
                    ? 'bg-emerald-100 text-emerald-700'
                    : (record.status === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                tr.innerHTML = `
                    <td class="p-4 font-mono font-bold text-slate-900">${record.emp_id}</td>
                    <td class="p-4">${record.name}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold ${record.department === 'Production' ? 'bg-purple-100 text-purple-700' : 'bg-cyan-100 text-cyan-700'}">${record.department}</span></td>
                    <td class="p-4">${record.leave_type}</td>
                    <td class="p-4 font-mono text-xs">${record.start_date}</td>
                    <td class="p-4 font-mono text-xs">${record.end_date}</td>
                    <td class="p-4 font-mono">${Number(record.leave_days || 0).toFixed(2)}</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold ${statusClass}">${record.status}</span></td>
                    <td class="p-4 text-xs text-slate-700">${record.reason}</td>
                    <td class="p-4 text-right space-x-2">
                        ${hasLeaveFormFullAccess ? `<button onclick="editLeaveRequest(${record.id})" class="text-blue-600 hover:text-blue-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit</button>
                        <button onclick="deleteLeaveRequest(${record.id})" class="text-red-600 hover:text-red-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>` : '<span class="text-slate-300 text-xs">View only</span>'}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
        }

        async function handleLeaveSubmit(event) {
            event.preventDefault();
            const id = Number(document.getElementById('leave-form-id').value || 0);
            const empId = document.getElementById('leave-form-emp-id').value;
            const leaveType = document.getElementById('leave-form-type').value.trim();
            const startDate = document.getElementById('leave-form-start').value;
            const endDate = document.getElementById('leave-form-end').value;
            const leaveDays = document.getElementById('leave-form-days').value;
            const status = document.getElementById('leave-form-status').value;
            const reason = document.getElementById('leave-form-reason').value.trim();
            const remarks = document.getElementById('leave-form-remarks').value.trim();

            if (!empId || !leaveType || !startDate || !endDate || !leaveDays || !reason) {
                alert('Please fill all required leave fields.');
                return;
            }
            if (!hasLeaveFormFullAccess && id > 0) {
                alert('Only admin/HR/IT/Manager can edit leave requests.');
                return;
            }

            const body = new URLSearchParams();
            body.append('action', 'saveLeaveRequest');
            if (id > 0) {
                body.append('id', String(id));
            }
            body.append('empId', empId);
            body.append('leaveType', leaveType);
            body.append('startDate', startDate);
            body.append('endDate', endDate);
            body.append('leaveDays', String(leaveDays));
            body.append('status', hasLeaveFormFullAccess ? status : 'Pending');
            body.append('reason', reason);
            body.append('remarks', remarks);

            const payload = await requestJson(apiUrl, { method: 'POST', body });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                resetLeaveForm();
                await renderLeaveGrid();
            }
        }

        function editLeaveRequest(id) {
            if (!hasLeaveFormFullAccess) {
                alert('Only admin/HR/IT/Manager can edit leave requests.');
                return;
            }
            const record = leaveRequests.find(item => Number(item.id) === Number(id));
            if (!record) return;
            document.getElementById('leave-form-id').value = String(record.id);
            document.getElementById('leave-form-emp-id').value = record.emp_id;
            document.getElementById('leave-form-type').value = record.leave_type || '';
            document.getElementById('leave-form-start').value = record.start_date || '';
            document.getElementById('leave-form-end').value = record.end_date || '';
            document.getElementById('leave-form-days').value = Number(record.leave_days || 1);
            document.getElementById('leave-form-status').value = record.status || 'Pending';
            document.getElementById('leave-form-reason').value = record.reason || '';
            document.getElementById('leave-form-remarks').value = record.remarks || '';
            document.getElementById('leave-form-title').innerHTML = '<i data-lucide="edit-3" class="w-5 h-5 text-blue-600"></i> Update Leave Request';
            document.getElementById('leave-submit-btn').textContent = 'Update Leave Request';
            lucide.createIcons();
            switchTab('tab-leave-management');
        }

        async function deleteLeaveRequest(id) {
            if (!hasLeaveFormFullAccess) {
                alert('Only admin/HR/IT/Manager can delete leave requests.');
                return;
            }
            if (!confirm('Delete this leave request?')) return;
            const body = new URLSearchParams();
            body.append('action', 'deleteLeaveRequest');
            body.append('id', String(id));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Deleting leave request...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                await renderLeaveGrid();
            }
        }

        function resetLeaveForm() {
            document.getElementById('leave-form').reset();
            document.getElementById('leave-form-id').value = '';
            document.getElementById('leave-form-title').innerHTML = '<i data-lucide="calendar-check-2" class="w-5 h-5 text-blue-600"></i> Register Leave Request';
            document.getElementById('leave-submit-btn').textContent = 'Save Leave Request';
            lucide.createIcons();
        }

        async function renderTravelGrid() {
            const start = document.getElementById('travel-filter-start').value;
            const end = document.getElementById('travel-filter-end').value;
            const department = document.getElementById('travel-filter-dept').value;
            const payload = await requestJson(`${apiUrl}?action=listTravelOrders&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&department=${encodeURIComponent(department)}`);
            const tbody = document.getElementById('travel-table-body');
            tbody.innerHTML = '';
            if (!payload.success || payload.travelOrders.length === 0) {
                travelOrders = [];
                tbody.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-slate-400 italic">No travel orders found for the selected filters.</td></tr>`;
                return;
            }

            travelOrders = payload.travelOrders;
            payload.travelOrders.forEach(record => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 border-b border-slate-150';
                tr.innerHTML = `
                    <td class="p-4 font-mono text-xs">${record.form_date || ''}</td>
                    <td class="p-4 font-mono font-bold text-slate-900">${record.emp_id}</td>
                    <td class="p-4">${record.name || ''}</td>
                    <td class="p-4 text-xs">${record.destination || ''}</td>
                    <td class="p-4 text-xs">${record.purpose || ''}</td>
                    <td class="p-4 text-xs">${record.mode_of_travel || ''}${record.mode_of_travel === 'Other' && record.mode_other ? ' (' + record.mode_other + ')' : ''}</td>
                    <td class="p-4 font-mono text-xs">${Number(record.advance_amount || 0).toFixed(2)}</td>
                    <td class="p-4 font-mono text-xs">${record.departure_date || ''}</td>
                    <td class="p-4 font-mono text-xs">${record.arrival_date || ''}</td>
                    <td class="p-4 text-right space-x-2">
                        ${isViewerRole ? '<span class="text-slate-300 text-xs">View only</span>' : `<button onclick="editTravelOrder(${record.id})" class="text-blue-600 hover:text-blue-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit</button>
                        <button onclick="deleteTravelOrder(${record.id})" class="text-red-600 hover:text-red-950 font-semibold text-xs inline-flex items-center gap-1"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>`}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
        }

        async function handleTravelSubmit(event) {
            event.preventDefault();
            const id = Number(document.getElementById('travel-form-id').value || 0);
            const empId = document.getElementById('travel-form-emp-id').value;
            const destination = document.getElementById('travel-form-destination').value.trim();
            const purpose = document.getElementById('travel-form-purpose').value.trim();
            if (!empId || !destination || !purpose) {
                alert('Employee, destination, and purpose are required.');
                return;
            }

            const body = new URLSearchParams();
            body.append('action', 'saveTravelOrder');
            if (id > 0) {
                body.append('id', String(id));
            }
            body.append('formDate', document.getElementById('travel-form-date').value);
            body.append('empId', empId);
            body.append('branch', document.getElementById('travel-form-branch').value.trim());
            body.append('destination', destination);
            body.append('purpose', purpose);
            body.append('departureDate', document.getElementById('travel-form-departure').value);
            body.append('arrivalDate', document.getElementById('travel-form-arrival').value);
            body.append('modeOfTravel', document.getElementById('travel-form-mode').value);
            body.append('modeOther', document.getElementById('travel-form-mode-other').value.trim());
            body.append('advanceAmount', document.getElementById('travel-form-advance').value || '0');
            body.append('requestedBy', document.getElementById('travel-form-requested-by').value.trim());
            body.append('checkedBy', document.getElementById('travel-form-checked-by').value.trim());
            body.append('approvedBy', document.getElementById('travel-form-approved-by').value.trim());
            body.append('totalDays', document.getElementById('travel-form-total-days').value);
            body.append('tadaPerDay', document.getElementById('travel-form-tada').value);
            body.append('otherExpenses', document.getElementById('travel-form-other-expenses').value);
            body.append('totalExpenses', document.getElementById('travel-form-total-expenses').value);
            body.append('settlementRequestedBy', document.getElementById('travel-form-settlement-requested-by').value.trim());
            body.append('settlementCheckedBy', document.getElementById('travel-form-settlement-checked-by').value.trim());
            body.append('settlementApprovedBy', document.getElementById('travel-form-settlement-approved-by').value.trim());
            body.append('notes', document.getElementById('travel-form-notes').value.trim());

            const payload = await requestJson(apiUrl, { method: 'POST', body });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                resetTravelForm();
                await renderTravelGrid();
            }
        }

        function editTravelOrder(id) {
            const record = travelOrders.find(item => Number(item.id) === Number(id));
            if (!record) return;
            document.getElementById('travel-form-id').value = String(record.id);
            document.getElementById('travel-form-date').value = record.form_date || '';
            document.getElementById('travel-form-emp-id').value = record.emp_id || '';
            document.getElementById('travel-form-branch').value = record.branch || '';
            document.getElementById('travel-form-destination').value = record.destination || '';
            document.getElementById('travel-form-purpose').value = record.purpose || '';
            document.getElementById('travel-form-departure').value = record.departure_date || '';
            document.getElementById('travel-form-arrival').value = record.arrival_date || '';
            document.getElementById('travel-form-mode').value = record.mode_of_travel || 'Office Vehicle';
            document.getElementById('travel-form-mode-other').value = record.mode_other || '';
            document.getElementById('travel-form-advance').value = Number(record.advance_amount || 0);
            document.getElementById('travel-form-requested-by').value = record.requested_by || '';
            document.getElementById('travel-form-checked-by').value = record.checked_by || '';
            document.getElementById('travel-form-approved-by').value = record.approved_by || '';
            document.getElementById('travel-form-total-days').value = record.total_days ?? '';
            document.getElementById('travel-form-tada').value = record.tada_per_day ?? '';
            document.getElementById('travel-form-other-expenses').value = record.other_expenses ?? '';
            document.getElementById('travel-form-total-expenses').value = record.total_expenses ?? '';
            document.getElementById('travel-form-settlement-requested-by').value = record.settlement_requested_by || '';
            document.getElementById('travel-form-settlement-checked-by').value = record.settlement_checked_by || '';
            document.getElementById('travel-form-settlement-approved-by').value = record.settlement_approved_by || '';
            document.getElementById('travel-form-notes').value = record.notes || '';
            document.getElementById('travel-form-title').innerHTML = '<i data-lucide="edit-3" class="w-5 h-5 text-blue-600"></i> Update Travel Order Form';
            document.getElementById('travel-submit-btn').textContent = 'Update Travel Form';
            lucide.createIcons();
            switchTab('tab-travel-form');
        }

        async function deleteTravelOrder(id) {
            if (!confirm('Delete this travel form record?')) return;
            const body = new URLSearchParams();
            body.append('action', 'deleteTravelOrder');
            body.append('id', String(id));
            const payload = await requestJson(apiUrl, { method: 'POST', body }, { isMutation: true, busyMessage: 'Deleting travel form...' });
            showHardwareScreen(payload.message, payload.success ? 'text-emerald-400' : 'text-red-400');
            if (payload.success) {
                await renderTravelGrid();
            }
        }

        function resetTravelForm() {
            document.getElementById('travel-form').reset();
            document.getElementById('travel-form-id').value = '';
            document.getElementById('travel-form-advance').value = '0';
            document.getElementById('travel-form-date').value = formatDateInputValue();
            document.getElementById('travel-form-title').innerHTML = '<i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i> Travel Order Form';
            document.getElementById('travel-submit-btn').textContent = 'Save Travel Form';
            lucide.createIcons();
        }

        function showHardwareScreen(message, colorClass = 'text-emerald-400') {
            const screen = document.getElementById('admin-screen');
            if (!screen) return;
            const isError = colorClass.includes('red');
            screen.className = `rounded-xl border px-4 py-3 text-sm font-semibold ${isError ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'}`;
            screen.innerText = message;
            screen.classList.remove('hidden');
        }

        function exportAttendance() {
            const start = document.getElementById('filter-start').value;
            const end = document.getElementById('filter-end').value;
            const department = document.getElementById('filter-dept').value;
            const empId = document.getElementById('filter-emp').value;
            if (!isValidDateRange(start, end)) {
                alert('Please choose a valid attendance date range before exporting.');
                return;
            }
            window.location.href = `${apiUrl}?action=exportAttendance&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&department=${encodeURIComponent(department)}&empId=${encodeURIComponent(empId)}`;
        }

        function exportVehicleUsage() {
            const start = document.getElementById('vehicle-filter-start').value;
            const end = document.getElementById('vehicle-filter-end').value;
            const department = document.getElementById('vehicle-filter-dept').value;
            window.location.href = `${apiUrl}?action=exportVehicleUsage&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&department=${encodeURIComponent(department)}`;
        }
    </script>
</body>
</html>
