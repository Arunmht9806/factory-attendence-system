<?php
session_start();
require_once __DIR__ . '/db.php';

$loginError = '';
if (empty($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $loginError = 'Employee ID and password are required.';
    } else {
        $mysqli = db_connect();
        $stmt = $mysqli->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result && password_verify($password, $result['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $result['id'];
                $_SESSION['username'] = $result['username'];
                $_SESSION['role'] = $result['role'];
                header('Location: punch.php');
                exit;
            }

            $loginError = 'Invalid employee ID or password.';
        } else {
            $loginError = 'Punch terminal login is not available right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

<?php if (empty($_SESSION['user_id'])): ?>
    <main class="flex-grow flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md bg-slate-900 rounded-2xl p-6 shadow-2xl border border-slate-800">
            <div class="flex items-center gap-3 mb-6">
                <div class="bg-white p-1.5 rounded-lg shrink-0 shadow-sm">
                    <img src="assets/images/wellhope-logo.png" alt="Wellhope Logo" class="w-9 h-9 object-contain rounded">
                </div>
                <div>
                    <h1 class="text-lg font-bold">Punch Terminal Login</h1>
                    <p class="text-xs text-blue-200">Use your Employee ID and password to punch in or out.</p>
                </div>
            </div>

            <?php if ($loginError): ?>
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    <?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1.5">Employee ID</label>
                    <input type="text" name="username" autocomplete="username" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 transition font-mono text-sm"
                           placeholder="e.g. EMP101">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1.5">Password</label>
                    <input type="password" name="password" autocomplete="current-password" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 transition font-mono text-sm"
                           placeholder="Enter password">
                </div>
                <button type="submit" class="w-full bg-gradient-to-b from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 transition-all p-3 rounded-xl font-bold uppercase text-sm tracking-wider shadow-md">
                    Enter Punch Terminal
                </button>
            </form>

            <p class="mt-4 text-[11px] text-slate-500 leading-relaxed">
                Employee credentials are created automatically when staff are added. If you were given a default account, use your Employee ID and the assigned password.
            </p>
        </div>
    </main>
<?php else: ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punch Terminal — NEPAL WELLHOPE AGRI TECH</title>
    <link rel="icon" type="image/png" href="assets/images/wellhope-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-950 min-h-screen flex flex-col antialiased text-white">

    <!-- Header bar -->
    <header class="bg-gradient-to-r from-blue-800 to-indigo-900 shadow-xl border-b border-white/10">
        <div class="max-w-2xl mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="bg-white p-1.5 rounded-lg shrink-0 shadow-sm">
                    <img src="assets/images/wellhope-logo.png" alt="Wellhope Logo" class="w-9 h-9 object-contain rounded">
                </div>
                <div class="min-w-0">
                    <h1 class="text-base font-bold truncate">NEPAL WELLHOPE AGRI TECH PVT.LTD.</h1>
                    <p class="text-xs text-blue-200 truncate">Biometric Punch Terminal</p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span id="clock-display" class="bg-black/30 px-3 py-1.5 rounded text-sm font-mono tracking-wider"></span>
                <a href="index.php" class="text-xs text-blue-200 hover:text-white flex items-center gap-1 transition">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Admin
                </a>
            </div>
        </div>
    </header>

    <!-- Main terminal card -->
    <main class="flex-grow flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md space-y-6">

            <!-- Terminal device card -->
            <div class="bg-slate-900 rounded-2xl p-6 shadow-2xl border-4 border-slate-800 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex items-center justify-between mb-5">
                    <span class="text-[10px] tracking-widest text-slate-400 font-mono font-bold uppercase">Hardware Terminal v1.4</span>
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full shadow-[0_0_8px_#10b981]"></span>
                </div>

                <!-- Console output -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 font-mono mb-5">
                    <div class="text-slate-400 text-[10px] uppercase tracking-wider mb-1">Interactive Console</div>
                    <div id="device-screen" class="text-emerald-400 text-sm font-semibold leading-relaxed min-h-[52px] whitespace-pre-wrap">
                        Ready for punch... Please select employee.
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Step 1: department -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">1. Choose Department</label>
                        <select id="terminal-department" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500 transition font-mono text-sm">
                            <option value="All">All Departments</option>
                        </select>
                        <span class="text-[10px] text-slate-500 mt-1 block">Filter the employee list by department before punching.</span>
                    </div>

                    <!-- Step 1: employee -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">2. Choose Employee</label>
                        <input id="terminal-employee-search" list="terminal-employee-options" autocomplete="off"
                               placeholder="Type employee name or ID"
                               class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 transition font-mono text-sm">
                        <datalist id="terminal-employee-options"></datalist>
                        <input type="hidden" id="terminal-employee-id" value="">
                           <input type="hidden" id="terminal-employee-name" value="">
                           <input type="hidden" id="terminal-employee-department" value="">
                           <input type="hidden" id="terminal-employee-designation" value="">
                        <span id="terminal-employee-helper" class="text-[10px] text-slate-500 mt-1 block">Type to search by name or employee ID.</span>
                    </div>

                    <!-- Step 2: live clock -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">3. Employee Password</label>
                        <input type="password" id="terminal-employee-password" autocomplete="off"
                               placeholder="Enter employee password"
                               class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 transition font-mono text-sm">
                        <span class="text-[10px] text-slate-500 mt-1 block">Password is required for every punch-in / punch-out action.</span>
                    </div>

                    <!-- Step 3: live clock -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">4. Punch Time (Auto Real-Time)</label>
                        <input type="datetime-local" id="terminal-timestamp" step="1" readonly
                               class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-emerald-400 focus:outline-none font-mono text-sm cursor-default">
                        <span class="text-[10px] text-slate-500 mt-1 block">Follows your computer clock. Max 6 sessions/day.</span>
                    </div>

                    <!-- Step 5: vehicle -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">5. Vehicle Number <span class="text-slate-600 font-normal">(for vehicle punch)</span></label>
                        <input type="text" id="terminal-vehicle-name" placeholder="e.g. BA 1 CHA 1234"
                               class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 transition font-mono text-sm">
                    </div>

                    <!-- Step 6: purpose -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">6. Vehicle Purpose <span class="text-slate-600 font-normal">(for vehicle punch)</span></label>
                        <input type="text" id="terminal-vehicle-purpose" placeholder="e.g. Client visit, delivery, office work"
                               class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 transition font-mono text-sm">
                        <span class="text-[10px] text-slate-500 mt-1 block">Regular punch ignores these fields. Vehicle punch requires both.</span>
                    </div>

                    <!-- Punch buttons -->
                    <div class="pt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button id="btn-regular-punch"
                                class="w-full bg-gradient-to-b from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 active:scale-95 transition-all p-4 rounded-xl font-bold uppercase text-sm tracking-wider flex items-center justify-center gap-2 border-b-4 border-blue-900 shadow-md">
                            <i data-lucide="fingerprint" class="w-5 h-5"></i>
                            Regular Punch
                        </button>
                        <button id="btn-vehicle-punch"
                                class="w-full bg-gradient-to-b from-amber-600 to-amber-700 hover:from-amber-500 hover:to-amber-600 active:scale-95 transition-all p-4 rounded-xl font-bold uppercase text-sm tracking-wider flex items-center justify-center gap-2 border-b-4 border-amber-900 shadow-md">
                            <i data-lucide="truck" class="w-5 h-5"></i>
                            Vehicle Punch
                        </button>
                    </div>
                </div>
            </div>

            <!-- Terminal log feed -->
            <div class="bg-slate-900 rounded-xl p-5 border border-slate-800 shadow-lg">
                <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                    <i data-lucide="bell" class="w-3.5 h-3.5 text-blue-500"></i> Terminal Output Feed
                </h3>
                <div id="terminal-logs" class="space-y-2 max-h-[200px] overflow-y-auto text-xs font-mono">
                    <div class="text-slate-500 italic">System waiting for the first punch action.</div>
                </div>
            </div>

        </div>
    </main>

    <footer class="text-center text-xs text-slate-600 py-4">
        © 2026 NEPAL WELLHOPE AGRI TECH PVT.LTD. — Biometric Punch Terminal
    </footer>

<script>
    const apiUrl = 'api.php';
    let employees = [];
    let employeeOptions = [];

    // ── helpers ──────────────────────────────────────────────────────────────

    function formatDateTimeLocal(date) {
        const pad = n => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}` +
               `T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function showScreen(message, colorClass = 'text-emerald-400') {
        const el = document.getElementById('device-screen');
        el.className = `${colorClass} text-sm font-semibold leading-relaxed min-h-[52px] whitespace-pre-wrap`;
        el.innerText = message;
    }

    function logTerminal(message) {
        const box = document.getElementById('terminal-logs');
        const row = document.createElement('div');
        row.className = 'border-b border-slate-800 pb-1 text-slate-400';
        row.innerHTML = `<span class="text-blue-500 font-bold">[${new Date().toLocaleTimeString()}]</span> ${message}`;
        box.insertBefore(row, box.firstChild);
    }

    function tickClock() {
        const now = new Date();
        document.getElementById('clock-display').innerText = now.toLocaleString();
        document.getElementById('terminal-timestamp').value = formatDateTimeLocal(now);
    }

    // ── data ──────────────────────────────────────────────────────────────────

    async function loadEmployees() {
        try {
            const res = await fetch(`${apiUrl}?action=listEmployees`);
            const data = await res.json();
            if (!data.success) return;
            employees = data.employees;
            const departmentSelect = document.getElementById('terminal-department');
            const departments = [...new Set(employees.map(emp => (emp.department || '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b));
            departmentSelect.innerHTML = '<option value="All">All Departments</option>';
            departments.forEach(department => {
                const option = document.createElement('option');
                option.value = department;
                option.textContent = department;
                departmentSelect.appendChild(option);
            });
            const datalist = document.getElementById('terminal-employee-options');
            datalist.innerHTML = '';
            employeeOptions = [];
            employees.forEach(emp => {
                const labelParts = [`${emp.name}`, `[${emp.id}]`, `(${emp.department})`];
                if (emp.designation) {
                    labelParts.splice(1, 0, emp.designation);
                }
                const label = labelParts.join(' ');
                employeeOptions.push({
                    id: emp.id,
                    label,
                    name: emp.name,
                    department: emp.department,
                    designation: emp.designation || '',
                    last_vehicle_name: emp.last_vehicle_name || '',
                    last_vehicle_purpose: emp.last_vehicle_purpose || '',
                });
                const opt = document.createElement('option');
                opt.value = label;
                datalist.appendChild(opt);
            });
            const search = document.getElementById('terminal-employee-search');
            departmentSelect.addEventListener('change', handleDepartmentSelectionChange);
            search.addEventListener('input', handleEmployeeSelectionChange);
            search.addEventListener('change', handleEmployeeSelectionChange);
        } catch (e) {
            showScreen('Could not load staff list. Check server connection.', 'text-red-400');
        }
    }

    function handleDepartmentSelectionChange() {
        document.getElementById('terminal-employee-search').value = '';
        document.getElementById('terminal-employee-id').value = '';
        document.getElementById('terminal-employee-name').value = '';
        document.getElementById('terminal-employee-department').value = '';
        document.getElementById('terminal-employee-designation').value = '';
        document.getElementById('terminal-employee-helper').textContent = 'Type to search by name or employee ID.';
        document.getElementById('terminal-vehicle-name').value = '';
        document.getElementById('terminal-vehicle-purpose').value = '';
        refreshEmployeeOptions();
    }

    function refreshEmployeeOptions() {
        const department = document.getElementById('terminal-department').value;
        const datalist = document.getElementById('terminal-employee-options');
        datalist.innerHTML = '';

        employeeOptions
            .filter(employee => department === 'All' || employee.department === department)
            .forEach(employee => {
                const opt = document.createElement('option');
                opt.value = employee.label;
                datalist.appendChild(opt);
            });
    }

    function handleEmployeeSelectionChange() {
        const searchValue = document.getElementById('terminal-employee-search').value.trim();
        const hiddenId = document.getElementById('terminal-employee-id');
        const hiddenName = document.getElementById('terminal-employee-name');
        const hiddenDepartment = document.getElementById('terminal-employee-department');
        const hiddenDesignation = document.getElementById('terminal-employee-designation');
        const vehicleNameInput = document.getElementById('terminal-vehicle-name');
        const vehiclePurposeInput = document.getElementById('terminal-vehicle-purpose');
        const helper = document.getElementById('terminal-employee-helper');
        const department = document.getElementById('terminal-department').value;

        let employee = employeeOptions.find(item => item.label === searchValue && (department === 'All' || item.department === department));
        if (!employee && searchValue) {
            const normalized = searchValue.toLowerCase();
            const filteredEmployees = employeeOptions.filter(item => department === 'All' || item.department === department);
            const exactIdMatch = filteredEmployees.find(item => item.id.toLowerCase() === normalized);
            const nameMatch = filteredEmployees.find(item => item.name.toLowerCase() === normalized);
            const startsWithMatch = filteredEmployees.find(item => item.name.toLowerCase().startsWith(normalized));
            employee = exactIdMatch || nameMatch || startsWithMatch || filteredEmployees.find(item => item.label.toLowerCase().includes(normalized));
        }

        if (!employee) {
            hiddenId.value = '';
            hiddenName.value = '';
            hiddenDepartment.value = '';
            hiddenDesignation.value = '';
            vehicleNameInput.value = '';
            vehiclePurposeInput.value = '';
            if (helper) helper.textContent = searchValue ? 'No match in the selected department yet. Continue typing or pick a suggestion.' : 'Type to search by name or employee ID.';
            return;
        }

        hiddenId.value = employee.id;
        hiddenName.value = employee.name || '';
        hiddenDepartment.value = employee.department || '';
        hiddenDesignation.value = employee.designation || '';
        vehicleNameInput.value = employee.last_vehicle_name || '';
        vehiclePurposeInput.value = employee.last_vehicle_purpose || '';
        if (helper) helper.textContent = `${employee.name} selected (${employee.id}).`;
    }

    // ── punch ─────────────────────────────────────────────────────────────────

    async function submitPunch(actionName) {
        const empId        = document.getElementById('terminal-employee-id').value.trim();
        const empName = document.getElementById('terminal-employee-name').value.trim();
        const empDepartment = document.getElementById('terminal-employee-department').value.trim();
        const empDesignation = document.getElementById('terminal-employee-designation').value.trim();
        const employeePassword = document.getElementById('terminal-employee-password').value;
        const vehicleName  = document.getElementById('terminal-vehicle-name').value.trim();
        const vehiclePurpose = document.getElementById('terminal-vehicle-purpose').value.trim();
        const timestamp    = formatDateTimeLocal(new Date());
        document.getElementById('terminal-timestamp').value = timestamp;

        if (!empId) {
            showScreen('Please select an employee first.', 'text-red-400');
            return;
        }
        if (!empName || !empDepartment) {
            showScreen('Employee details are incomplete. Please select employee again.', 'text-red-400');
            return;
        }
        if (!employeePassword) {
            showScreen('Employee password is required to punch.', 'text-red-400');
            return;
        }
        const isVehiclePunch = actionName === 'punchVehicle';
        if (isVehiclePunch && (!vehicleName || !vehiclePurpose)) {
            showScreen('Vehicle punch requires both vehicle number and vehicle purpose.', 'text-red-400');
            return;
        }

        const regularBtn = document.getElementById('btn-regular-punch');
        const vehicleBtn = document.getElementById('btn-vehicle-punch');
        regularBtn.disabled = true;
        vehicleBtn.disabled = true;
        regularBtn.classList.add('opacity-70', 'cursor-not-allowed');
        vehicleBtn.classList.add('opacity-70', 'cursor-not-allowed');

        const body = new URLSearchParams();
        body.append('action',          actionName);
        body.append('empId',           empId);
        body.append('empName',         empName);
        body.append('empDepartment',   empDepartment);
        body.append('empDesignation',  empDesignation);
        body.append('employeePassword', employeePassword);
        body.append('timestamp',       timestamp);
        if (isVehiclePunch) {
            body.append('vehicleName', vehicleName);
            body.append('vehiclePurpose', vehiclePurpose);
        }

        try {
            const res  = await fetch(apiUrl, { method: 'POST', body });
            const data = await res.json();
            const emp  = employees.find(e => e.id === empId);

            if (data.success) {
                const t = new Date(timestamp).toLocaleTimeString();
                const punchLabel = isVehiclePunch ? 'Vehicle punch' : 'Regular punch';
                const vNote = isVehiclePunch ? ` | Vehicle: ${vehicleName} — ${vehiclePurpose}` : '';
                const statusNote = isVehiclePunch && data.sessionCompleted ? ' Session completed.' : '';
                showScreen(`✓ ${punchLabel} saved for ${emp?.name || empId}\nat ${t}.${vNote}${statusNote}`, 'text-emerald-400');
                logTerminal(`${punchLabel} recorded: ${empId} ${timestamp}${vNote}`);
                if (isVehiclePunch) {
                    if (emp) {
                        if (data.sessionCompleted) {
                            emp.last_vehicle_name = '';
                            emp.last_vehicle_purpose = '';
                        } else {
                            emp.last_vehicle_name = vehicleName;
                            emp.last_vehicle_purpose = vehiclePurpose;
                        }
                    }
                    document.getElementById('terminal-vehicle-name').value = '';
                    document.getElementById('terminal-vehicle-purpose').value = '';
                }
            } else {
                if (data.message === 'Employee details do not match. Please select employee again.') {
                    alert(data.message);
                } else if (isVehiclePunch && data.message) {
                    alert(data.message);
                }
                showScreen(data.message, 'text-red-400');
                logTerminal(`FAILED: ${empId} — ${data.message}`);
            }
        } catch (e) {
            showScreen('Punch request failed. Check connection.', 'text-red-400');
        }

        document.getElementById('terminal-employee-password').value = '';

        regularBtn.disabled = false;
        vehicleBtn.disabled = false;
        regularBtn.classList.remove('opacity-70', 'cursor-not-allowed');
        vehicleBtn.classList.remove('opacity-70', 'cursor-not-allowed');
    }

    // ── boot ──────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        tickClock();
        setInterval(tickClock, 1000);
        loadEmployees();
        document.getElementById('btn-regular-punch').addEventListener('click', () => submitPunch('punch'));
        document.getElementById('btn-vehicle-punch').addEventListener('click', () => submitPunch('punchVehicle'));
    });
</script>
<?php endif; ?>
</body>
</html>
