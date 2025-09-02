<?php
session_start();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!file_exists('data')) {
        mkdir('data', 0777, true);
    }

    $queueFile = 'data/queue.json';
    $currentCallFile = 'data/current_call.json';
    $settingsFile = 'data/settings.json';

    if ($action === 'getAdminData') {
        $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
        $currentCall = file_exists($currentCallFile) ? json_decode(file_get_contents($currentCallFile), true) : [];
        $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [
            'services' => [
                ['code' => 'A', 'name' => 'Layanan Umum', 'color' => 'blue', 'icon' => 'file-alt'],
                ['code' => 'B', 'name' => 'Pembayaran', 'color' => 'green', 'icon' => 'credit-card'],
                ['code' => 'C', 'name' => 'Informasi', 'color' => 'purple', 'icon' => 'question-circle']
            ]
        ];

        echo json_encode([
            'queue' => $queue,
            'currentCall' => $currentCall,
            'settings' => $settings
        ]);
        exit;
    }

    if ($action === 'callNext') {
        $counter = $_POST['counter'] ?? '1';

        $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];

        // Find next waiting ticket
        $nextTicket = null;
        for ($i = 0; $i < count($queue); $i++) {
            if ($queue[$i]['status'] === 'waiting') {
                $queue[$i]['status'] = 'called';
                $queue[$i]['counter'] = $counter;
                $queue[$i]['calledAt'] = date('Y-m-d H:i:s');
                $nextTicket = $queue[$i];
                break;
            }
        }

        if ($nextTicket) {
            file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));

            $currentCall = [
                'number' => $nextTicket['number'],
                'counter' => $counter,
                'service' => $nextTicket['service'],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            file_put_contents($currentCallFile, json_encode($currentCall, JSON_PRETTY_PRINT));

            echo json_encode(['success' => true, 'ticket' => $nextTicket, 'currentCall' => $currentCall]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tidak ada antrian']);
        }
        exit;
    }

    if ($action === 'completeTicket') {
        $ticketId = $_POST['ticketId'] ?? '';

        $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];

        for ($i = 0; $i < count($queue); $i++) {
            if ($queue[$i]['id'] === $ticketId) {
                $queue[$i]['status'] = 'completed';
                $queue[$i]['completedAt'] = date('Y-m-d H:i:s');
                break;
            }
        }

        file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'resetQueue') {
        file_put_contents($queueFile, json_encode([], JSON_PRETTY_PRINT));
        file_put_contents($currentCallFile, json_encode([], JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'updateSettings') {
        $services = json_decode($_POST['services'], true);
        $settings = ['services' => $services];

        // Cek apakah penulisan file berhasil
        if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT)) !== false) {
            // Jika berhasil, kirim pesan sukses
            echo json_encode(['success' => true]);
        } else {
            // Jika gagal, kirim pesan error
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pengaturan. Periksa izin file (file permissions) pada direktori /data.']);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Sistem Antrian Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 50%, #581c87 100%);
            min-height: 100vh;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            transition: all 0.3s ease;
        }

        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }

            100% {
                transform: scale(2.5);
                opacity: 0;
            }
        }

        .slide-up {
            animation: slideUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUp {
            0% {
                transform: translateY(50px);
                opacity: 0;
            }

            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .gradient-text {
            background: linear-gradient(135deg, #1e3a8a 0%, #581c87 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .modal-overlay {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }
    </style>
</head>

<body class="overflow-x-hidden">
    <!-- Header -->
    <div class="relative">
        <nav class="relative z-10 px-6 py-4">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="w-12 h-12 glass-effect rounded-xl flex items-center justify-center">
                            <i class="fas fa-shield-alt text-white text-xl"></i>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full pulse-ring"></div>
                    </div>
                    <div>
                        <h1 class="text-white text-xl font-bold">Admin Panel</h1>
                        <p class="text-white/80 text-sm">Kelola sistem antrian</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <button id="settingsBtn"
                        class="glass-effect text-white px-6 py-3 rounded-xl font-medium hover:bg-white/20 transition-all duration-300 group">
                        <i class="fas fa-cog mr-2 group-hover:rotate-180 transition-transform duration-500"></i>
                        Settings
                    </button>
                    <a href="index.php"
                        class="glass-effect text-white px-6 py-3 rounded-xl font-medium hover:bg-white/20 transition-all duration-300">
                        <i class="fas fa-users mr-2"></i>
                        User View
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8 space-y-8">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 slide-up">
            <div class="admin-card rounded-2xl p-6 text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-xl mx-auto mb-4 flex items-center justify-center">
                    <i class="fas fa-ticket-alt text-blue-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800" id="totalToday">0</div>
                <div class="text-sm text-gray-600">Total Hari Ini</div>
            </div>

            <div class="admin-card rounded-2xl p-6 text-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-xl mx-auto mb-4 flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800" id="totalWaiting">0</div>
                <div class="text-sm text-gray-600">Menunggu</div>
            </div>

            <div class="admin-card rounded-2xl p-6 text-center">
                <div class="w-12 h-12 bg-green-100 rounded-xl mx-auto mb-4 flex items-center justify-center">
                    <i class="fas fa-check text-green-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800" id="totalCompleted">0</div>
                <div class="text-sm text-gray-600">Selesai</div>
            </div>

            <div class="admin-card rounded-2xl p-6 text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-xl mx-auto mb-4 flex items-center justify-center">
                    <i class="fas fa-bullhorn text-purple-600 text-xl"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800" id="currentNumber">-</div>
                <div class="text-sm text-gray-600">Sedang Dipanggil</div>
            </div>
        </div>

        <!-- Call Controls -->
        <div class="admin-card rounded-3xl p-8 slide-up">
            <h2 class="text-2xl font-bold gradient-text mb-6">Kontrol Panggilan</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Pilih Loket:</label>
                    <select id="counterSelect"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:border-blue-500 focus:ring-0 transition-colors">
                        <option value="1">Loket 1</option>
                        <option value="2">Loket 2</option>
                        <option value="3">Loket 3</option>
                        <option value="4">Loket 4</option>
                        <option value="5">Loket 5</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Panggil Antrian:</label>
                    <button onclick="callNext()"
                        class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-6 rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-bullhorn mr-2"></i>
                        Panggil Berikutnya
                    </button>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Reset Sistem:</label>
                    <button onclick="resetQueue()"
                        class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white py-3 px-6 rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-refresh mr-2"></i>
                        Reset Antrian
                    </button>
                </div>
            </div>
        </div>

        <!-- Current Call Display -->
        <div class="admin-card rounded-3xl p-8 slide-up" id="currentCallDisplay">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Panggilan Saat Ini</h2>
            <div id="currentCallInfo" class="text-center py-8 text-gray-500">
                <i class="fas fa-volume-up text-4xl mb-4 opacity-50"></i>
                <p>Belum ada panggilan</p>
            </div>
        </div>

        <!-- Queue Management -->
        <div class="admin-card rounded-3xl p-8 slide-up">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold gradient-text">Manajemen Antrian</h2>
                <div class="flex items-center space-x-2 text-gray-500">
                    <i class="fas fa-sync-alt animate-spin"></i>
                    <span class="text-sm">Auto Update</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Nomor</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Layanan</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Waktu</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Loket</th>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="queueTableBody" class="divide-y divide-gray-200">
                        <!-- Queue items will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto slide-up">
            <div class="sticky top-0 bg-white border-b px-8 py-6 rounded-t-3xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-3xl font-bold gradient-text">Pengaturan Sistem</h2>
                        <p class="text-gray-600">Kelola jenis dan nama layanan antrian</p>
                    </div>
                    <button onclick="closeSettingsModal()"
                        class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>
            </div>

            <div class="p-8">
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Layanan Antrian</h3>
                        <button onclick="addService()"
                            class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-300">
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Layanan
                        </button>
                    </div>

                    <div id="servicesList" class="space-y-4">
                        <!-- Services will be loaded here -->
                    </div>
                </div>

                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button onclick="closeSettingsModal()"
                        class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button onclick="saveSettings()"
                        class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all duration-300">
                        <i class="fas fa-save mr-2"></i>
                        Simpan Pengaturan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast"
        class="fixed top-6 right-6 bg-white rounded-2xl shadow-2xl p-4 transform translate-x-full transition-all duration-300 z-50">
        <div class="flex items-center space-x-3">
            <div id="toastIcon" class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-check text-green-500 text-sm"></i>
            </div>
            <div>
                <div class="font-semibold text-gray-800" id="toastTitle">Berhasil!</div>
                <div class="text-sm text-gray-600" id="toastMessage">Pesan notifikasi</div>
            </div>
        </div>
    </div>

    <script>
        let services = [];
        let queue = [];

        // Available colors and icons for services
        const availableColors = ['blue', 'green', 'purple', 'red', 'yellow', 'indigo', 'pink', 'teal'];
        const availableIcons = ['file-alt', 'credit-card', 'question-circle', 'user', 'heart', 'star', 'phone', 'envelope', 'home', 'cog', 'bell', 'calendar'];

        // Speech Synthesis
        function speak(text) {
            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'id-ID';
                utterance.rate = 0.9;
                utterance.pitch = 1.1;
                speechSynthesis.speak(utterance);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            loadAdminData();
            setInterval(loadAdminData, 3000); // Update every 3 seconds

            // Settings modal trigger
            document.getElementById('settingsBtn').addEventListener('click', openSettingsModal);
        });

        // Load admin data
        function loadAdminData() {
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getAdminData'
            })
                .then(response => response.json())
                .then(data => {
                    services = data.settings.services;
                    queue = data.queue;
                    updateStats(data);
                    updateQueueTable(data.queue);
                    updateCurrentCall(data.currentCall);
                })
                .catch(error => console.error('Error:', error));
        }

        // Update statistics
        function updateStats(data) {
            const today = new Date().toDateString();
            const todayQueue = data.queue.filter(q => new Date(q.timestamp).toDateString() === today);

            document.getElementById('totalToday').textContent = todayQueue.length;
            document.getElementById('totalWaiting').textContent = data.queue.filter(q => q.status === 'waiting').length;
            document.getElementById('totalCompleted').textContent = data.queue.filter(q => q.status === 'completed').length;
            document.getElementById('currentNumber').textContent = data.currentCall.number || '-';
        }

        // Update current call display
        function updateCurrentCall(currentCall) {
            const display = document.getElementById('currentCallInfo');

            if (currentCall.number) {
                display.innerHTML = `
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-2xl p-6">
                        <div class="grid grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold mb-2">${currentCall.number}</div>
                                <div class="text-sm opacity-90">Nomor Antrian</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold mb-2">${currentCall.counter}</div>
                                <div class="text-sm opacity-90">Loket</div>
                            </div>
                            <div class="text-center">
                                <div class="text-lg font-bold mb-2">${currentCall.service.name}</div>
                                <div class="text-sm opacity-90">Layanan</div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                display.innerHTML = `
                    <i class="fas fa-volume-up text-4xl mb-4 opacity-50"></i>
                    <p>Belum ada panggilan</p>
                `;
            }
        }

        // Update queue table
        function updateQueueTable(queue) {
            const tbody = document.getElementById('queueTableBody');
            const sortedQueue = [...queue].reverse(); // Show newest first

            if (sortedQueue.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4 opacity-50"></i>
                            <p class="text-lg">Belum ada antrian</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = sortedQueue.map(ticket => {
                const statusColors = {
                    'waiting': 'bg-yellow-100 text-yellow-800',
                    'called': 'bg-blue-100 text-blue-800',
                    'completed': 'bg-green-100 text-green-800'
                };

                const statusTexts = {
                    'waiting': 'Menunggu',
                    'called': 'Dipanggil',
                    'completed': 'Selesai'
                };

                return `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-bold text-lg text-gray-900">${ticket.number}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-${ticket.service.color}-100 rounded-lg mr-3 flex items-center justify-center">
                                    <i class="fas fa-${ticket.service.icon} text-${ticket.service.color}-600 text-sm"></i>
                                </div>
                                <span class="font-medium text-gray-900">${ticket.service.name}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColors[ticket.status]}">
                                ${statusTexts[ticket.status]}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${new Date(ticket.timestamp).toLocaleString('id-ID')}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${ticket.counter || '-'}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            ${ticket.status === 'called' ?
                        `<button onclick="completeTicket('${ticket.id}')" class="text-green-600 hover:text-green-900 font-semibold">
                                    <i class="fas fa-check mr-1"></i>Selesaikan
                                </button>` :
                        '-'
                    }
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Call next ticket
        function callNext() {
            const counter = document.getElementById('counterSelect').value;

            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=callNext&counter=${counter}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        speak(`Nomor antrian ${data.ticket.number}, silakan menuju ke loket ${counter}.`);
                        showToast('Panggilan Berhasil!', `${data.ticket.number} dipanggil ke loket ${counter}`, 'success');
                        loadAdminData();
                    } else {
                        showToast('Tidak Ada Antrian', data.message, 'warning');
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Complete ticket
        function completeTicket(ticketId) {
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=completeTicket&ticketId=${ticketId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Tiket Selesai!', 'Tiket telah diselesaikan', 'success');
                        loadAdminData();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Reset queue
        function resetQueue() {
            if (confirm('Apakah Anda yakin ingin mereset seluruh antrian? Tindakan ini tidak dapat dibatalkan.')) {
                fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=resetQueue'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            speak('Sistem antrian telah direset.');
                            showToast('Reset Berhasil!', 'Seluruh antrian telah dihapus', 'success');
                            loadAdminData();
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        // Settings Modal Functions
        function openSettingsModal() {
            document.getElementById('settingsModal').classList.remove('hidden');
            document.getElementById('settingsModal').classList.add('flex');
            loadServicesSettings();
        }

        function closeSettingsModal() {
            document.getElementById('settingsModal').classList.add('hidden');
            document.getElementById('settingsModal').classList.remove('flex');
        }

        function loadServicesSettings() {
            const container = document.getElementById('servicesList');

            container.innerHTML = services.map((service, index) => `
                <div class="bg-gray-50 rounded-2xl p-6 border-2 border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Kode Layanan</label>
                            <input type="text" value="${service.code}" 
                                   onchange="updateService(${index}, 'code', this.value)"
                                   class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-0 font-mono text-center" 
                                   maxlength="1">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Layanan</label>
                            <input type="text" value="${service.name}" 
                                   onchange="updateService(${index}, 'name', this.value)"
                                   class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Warna</label>
                            <select onchange="updateService(${index}, 'color', this.value)" 
                                    class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-0">
                                ${availableColors.map(color =>
                `<option value="${color}" ${service.color === color ? 'selected' : ''}>${color.charAt(0).toUpperCase() + color.slice(1)}</option>`
            ).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ikon</label>
                            <select onchange="updateService(${index}, 'icon', this.value)" 
                                    class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-0">
                                ${availableIcons.map(icon =>
                `<option value="${icon}" ${service.icon === icon ? 'selected' : ''}>${icon}</option>`
            ).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-${service.color}-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-${service.icon} text-${service.color}-600"></i>
                            </div>
                            <span class="font-medium text-gray-700">Preview: ${service.code} - ${service.name}</span>
                        </div>
                        ${services.length > 1 ?
                    `<button onclick="removeService(${index})" class="text-red-500 hover:text-red-700 font-semibold">
                                <i class="fas fa-trash mr-1"></i>Hapus
                            </button>` : ''
                }
                    </div>
                </div>
            `).join('');
        }

        function updateService(index, field, value) {
            services[index][field] = value;
            loadServicesSettings(); // Refresh preview
        }

        function addService() {
            const newCode = String.fromCharCode(65 + services.length); // A, B, C, D, ...
            services.push({
                code: newCode,
                name: `Layanan ${newCode}`,
                color: availableColors[services.length % availableColors.length],
                icon: availableIcons[services.length % availableIcons.length]
            });
            loadServicesSettings();
        }

        function removeService(index) {
            if (confirm('Apakah Anda yakin ingin menghapus layanan ini?')) {
                services.splice(index, 1);
                loadServicesSettings();
            }
        }

        function saveSettings() {
            fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=updateSettings&services=${encodeURIComponent(JSON.stringify(services))}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Pengaturan Disimpan!', 'Konfigurasi layanan berhasil diperbarui', 'success');
                        closeSettingsModal();
                        loadAdminData();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Show toast notification
        function showToast(title, message, type = 'success') {
            const iconClasses = {
                'success': 'bg-green-100 text-green-500 fas fa-check',
                'warning': 'bg-yellow-100 text-yellow-500 fas fa-exclamation-triangle',
                'error': 'bg-red-100 text-red-500 fas fa-times'
            };

            document.getElementById('toastTitle').textContent = title;
            document.getElementById('toastMessage').textContent = message;

            const icon = document.getElementById('toastIcon');
            icon.className = `w-8 h-8 rounded-full flex items-center justify-center`;
            icon.innerHTML = `<i class="${iconClasses[type]} text-sm"></i>`;
            icon.classList.add(...iconClasses[type].split(' ').slice(0, 2));

            const toast = document.getElementById('toast');
            toast.classList.remove('translate-x-full');

            setTimeout(() => {
                toast.classList.add('translate-x-full');
            }, 4000);
        }
    </script>
</body>

</html>