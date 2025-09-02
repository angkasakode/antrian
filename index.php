<?php
session_start();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'takeTicket') {
        $serviceCode = $_POST['serviceCode'] ?? '';
        
        // Load current data
        $queueFile = 'data/queue.json';
        $settingsFile = 'data/settings.json';
        
        if (!file_exists('data')) {
            mkdir('data', 0777, true);
        }
        
        $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
        $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [
            'services' => [
                ['code' => 'A', 'name' => 'Layanan Umum', 'color' => 'blue', 'icon' => 'file-alt'],
                ['code' => 'B', 'name' => 'Pembayaran', 'color' => 'green', 'icon' => 'credit-card'],
                ['code' => 'C', 'name' => 'Informasi', 'color' => 'purple', 'icon' => 'question-circle']
            ]
        ];
        
        // Find service
        $service = null;
        foreach ($settings['services'] as $s) {
            if ($s['code'] === $serviceCode) {
                $service = $s;
                break;
            }
        }
        
        if ($service) {
            // Get next number for this service
            $maxNumber = 0;
            foreach ($queue as $ticket) {
                if (substr($ticket['number'], 0, 1) === $serviceCode) {
                    $num = (int)substr($ticket['number'], 1);
                    if ($num > $maxNumber) {
                        $maxNumber = $num;
                    }
                }
            }
            
            $nextNumber = $maxNumber + 1;
            $ticketNumber = $serviceCode . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            
            // Create new ticket
            $ticket = [
                'id' => time() . rand(1000, 9999),
                'number' => $ticketNumber,
                'service' => $service,
                'status' => 'waiting',
                'timestamp' => date('Y-m-d H:i:s'),
                'called' => false
            ];
            
            $queue[] = $ticket;
            file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));
            
            echo json_encode(['success' => true, 'ticket' => $ticket]);
            exit;
        }
    }
    
    if ($action === 'getQueueData') {
        $queueFile = 'data/queue.json';
        $currentCallFile = 'data/current_call.json';
        $settingsFile = 'data/settings.json';
        
        $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
        $currentCall = file_exists($currentCallFile) ? json_decode(file_get_contents($currentCallFile), true) : [];
        $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [
            'services' => [
                ['code' => 'A', 'name' => 'Layanan Umum', 'color' => 'blue', 'icon' => 'file-alt'],
                ['code' => 'B', 'name' => 'Pembayaran', 'color' => 'green', 'icon' => 'credit-card'],
                ['code' => 'C', 'name' => 'Informasi', 'color' => 'purple', 'icon' => 'question-circle']
            ]
        ];
        
        $waitingCount = count(array_filter($queue, function($q) { return $q['status'] === 'waiting'; }));
        
        echo json_encode([
            'queue' => $queue,
            'currentCall' => $currentCall,
            'waitingCount' => $waitingCount,
            'settings' => $settings
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Antrian Digital - Pengunjung</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .card-hover {
            transition: all 0.3s ease;
            transform: perspective(1000px) rotateX(0deg);
        }
        
        .card-hover:hover {
            transform: perspective(1000px) rotateX(5deg) translateY(-10px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
        }
        
        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }
        
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(2.5); opacity: 0; }
        }
        
        .slide-up {
            animation: slideUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        @keyframes slideUp {
            0% { transform: translateY(50px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="overflow-x-hidden">
    <!-- Header -->
    <div class="relative">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-600/20 to-purple-600/20"></div>
        <nav class="relative z-10 px-6 py-4">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full pulse-ring"></div>
                    </div>
                    <div>
                        <h1 class="text-white text-xl font-bold">Sistem Antrian Digital</h1>
                        <p class="text-white/80 text-sm">Ambil nomor antrian Anda</p>
                    </div>
                </div>
                
                <a href="admin.php" class="glass-effect text-white px-6 py-3 rounded-xl font-medium hover:bg-white/30 transition-all duration-300 group">
                    <i class="fas fa-cog mr-2 group-hover:rotate-180 transition-transform duration-500"></i>
                    Admin Panel
                </a>
            </div>
        </nav>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8 space-y-8">
        <!-- Current Queue Display -->
        <div class="glass-effect rounded-3xl p-8 slide-up">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-white mb-2">Informasi Antrian Saat Ini</h2>
                <p class="text-white/80">Pantau status antrian secara real-time</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                    <div class="w-16 h-16 bg-blue-500 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-white text-2xl"></i>
                    </div>
                    <div class="text-4xl font-bold text-white mb-2" id="currentNumber">-</div>
                    <div class="text-white/80 text-sm">Nomor Dipanggil</div>
                </div>
                
                <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                    <div class="w-16 h-16 bg-green-500 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-desktop text-white text-2xl"></i>
                    </div>
                    <div class="text-4xl font-bold text-white mb-2" id="currentCounter">-</div>
                    <div class="text-white/80 text-sm">Loket</div>
                </div>
                
                <div class="bg-white/20 rounded-2xl p-6 text-center backdrop-blur-sm">
                    <div class="w-16 h-16 bg-purple-500 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-clock text-white text-2xl"></i>
                    </div>
                    <div class="text-4xl font-bold text-white mb-2" id="waitingCount">0</div>
                    <div class="text-white/80 text-sm">Menunggu</div>
                </div>
            </div>
        </div>

        <!-- Service Selection -->
        <div class="bg-white rounded-3xl p-8 shadow-2xl slide-up">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold gradient-text mb-4">Pilih Layanan</h2>
                <p class="text-gray-600">Klik pada layanan yang Anda butuhkan untuk mengambil nomor antrian</p>
            </div>
            
            <div id="serviceButtons" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Service buttons will be loaded here -->
            </div>
        </div>

        <!-- Queue Status -->
        <div class="bg-white rounded-3xl p-8 shadow-2xl slide-up">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Antrian Menunggu</h2>
                    <p class="text-gray-600">Daftar nomor yang sedang menunggu dipanggil</p>
                </div>
                <div class="flex items-center space-x-2 text-gray-500">
                    <i class="fas fa-sync-alt animate-spin"></i>
                    <span class="text-sm">Auto Update</span>
                </div>
            </div>
            
            <div id="queueStatus" class="space-y-4 max-h-96 overflow-y-auto">
                <!-- Queue items will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Ticket Modal -->
    <div id="ticketModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full slide-up">
            <div class="p-8 text-center">
                <div class="w-20 h-20 bg-green-100 rounded-full mx-auto mb-6 flex items-center justify-center float-animation">
                    <i class="fas fa-check text-green-500 text-3xl"></i>
                </div>
                
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Tiket Berhasil Diambil!</h2>
                <p class="text-gray-600 mb-6">Nomor antrian Anda adalah:</p>
                
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl p-6 mb-6 text-white">
                    <div class="text-5xl font-bold mb-2" id="ticketNumber">A001</div>
                    <div class="text-lg opacity-90" id="ticketService">Layanan Umum</div>
                </div>
                
                <p class="text-gray-600 mb-6">Silakan menunggu hingga nomor Anda dipanggil</p>
                
                <button onclick="closeTicketModal()" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-4 rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>
                    Mengerti
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-6 right-6 bg-white rounded-2xl shadow-2xl p-4 transform translate-x-full transition-all duration-300 z-50">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-check text-green-500 text-sm"></i>
            </div>
            <div>
                <div class="font-medium text-gray-800" id="toastTitle">Berhasil!</div>
                <div class="text-sm text-gray-600" id="toastMessage">Pesan notifikasi</div>
            </div>
        </div>
    </div>

    <script>
        let services = [];
        
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
        document.addEventListener('DOMContentLoaded', function() {
            loadQueueData();
            setInterval(loadQueueData, 3000); // Update every 3 seconds
        });

        // Load queue data and services
        function loadQueueData() {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getQueueData'
            })
            .then(response => response.json())
            .then(data => {
                services = data.settings.services;
                updateDisplay(data);
                updateServiceButtons();
                updateQueueStatus(data.queue);
            })
            .catch(error => console.error('Error:', error));
        }

        // Update service buttons
        function updateServiceButtons() {
            const container = document.getElementById('serviceButtons');
            const colors = {
                'blue': 'from-blue-500 to-blue-600',
                'green': 'from-green-500 to-green-600',
                'purple': 'from-purple-500 to-purple-600',
                'red': 'from-red-500 to-red-600',
                'yellow': 'from-yellow-500 to-yellow-600',
                'indigo': 'from-indigo-500 to-indigo-600'
            };
            
            container.innerHTML = services.map(service => `
                <button onclick="takeTicket('${service.code}')" 
                        class="bg-gradient-to-br ${colors[service.color] || colors.blue} text-white p-8 rounded-2xl card-hover shadow-lg group">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl mx-auto mb-4 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-${service.icon} text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">${service.name}</h3>
                        <p class="text-sm opacity-90">Klik untuk ambil nomor</p>
                        <div class="mt-4 bg-white/20 rounded-lg py-2 px-4 inline-block">
                            <span class="font-mono font-bold">${service.code}XXX</span>
                        </div>
                    </div>
                </button>
            `).join('');
        }

        // Take ticket
        function takeTicket(serviceCode) {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=takeTicket&serviceCode=${serviceCode}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('ticketNumber').textContent = data.ticket.number;
                    document.getElementById('ticketService').textContent = data.ticket.service.name;
                    document.getElementById('ticketModal').classList.remove('hidden');
                    document.getElementById('ticketModal').classList.add('flex');
                    
                    speak(`Tiket antrian Anda adalah ${data.ticket.number}, ${data.ticket.service.name}. Silakan menunggu panggilan.`);
                    showToast('Tiket Berhasil!', 'Nomor antrian telah diambil');
                    loadQueueData();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Update display
        function updateDisplay(data) {
            document.getElementById('currentNumber').textContent = data.currentCall.number || '-';
            document.getElementById('currentCounter').textContent = data.currentCall.counter || '-';
            document.getElementById('waitingCount').textContent = data.waitingCount;
        }

        // Update queue status
        function updateQueueStatus(queue) {
            const container = document.getElementById('queueStatus');
            const waitingQueue = queue.filter(q => q.status === 'waiting').slice(0, 10);
            
            if (waitingQueue.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-clock text-4xl mb-4 opacity-50"></i>
                        <p class="text-lg">Tidak ada antrian saat ini</p>
                        <p class="text-sm">Ambil nomor antrian untuk memulai</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = waitingQueue.map((ticket, index) => {
                const colors = {
                    'blue': 'border-blue-200 bg-blue-50',
                    'green': 'border-green-200 bg-green-50',
                    'purple': 'border-purple-200 bg-purple-50',
                    'red': 'border-red-200 bg-red-50',
                    'yellow': 'border-yellow-200 bg-yellow-50',
                    'indigo': 'border-indigo-200 bg-indigo-50'
                };
                
                return `
                    <div class="flex items-center justify-between p-4 border-2 ${colors[ticket.service.color] || colors.blue} rounded-xl hover:shadow-md transition-all duration-300">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <div class="w-14 h-14 bg-gradient-to-br from-${ticket.service.color}-500 to-${ticket.service.color}-600 text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-lg">
                                    ${ticket.number}
                                </div>
                                ${index === 0 ? '<div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full animate-pulse"></div>' : ''}
                            </div>
                            <div>
                                <div class="font-semibold text-gray-800">${ticket.service.name}</div>
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    ${new Date(ticket.timestamp).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'})}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            ${index === 0 ? 
                                '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium"><i class="fas fa-arrow-right mr-1"></i>Selanjutnya</span>' :
                                `<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Antrian ${index + 1}</span>`
                            }
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Close ticket modal
        function closeTicketModal() {
            document.getElementById('ticketModal').classList.add('hidden');
            document.getElementById('ticketModal').classList.remove('flex');
        }

        // Show toast notification
        function showToast(title, message) {
            document.getElementById('toastTitle').textContent = title;
            document.getElementById('toastMessage').textContent = message;
            
            const toast = document.getElementById('toast');
            toast.classList.remove('translate-x-full');
            
            setTimeout(() => {
                toast.classList.add('translate-x-full');
            }, 3000);
        }
    </script>
</body>
</html>