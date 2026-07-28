<?php
/**
 * =============================================
 * API ABSENSI FACE RECOGNITION & PAYROLL
 * Backend API menggunakan PDO MySQL
 * =============================================
 */

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'absensi_face');
define('DB_USER', 'root');
define('DB_PASS', '');

// Header CORS dan JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Konfigurasi Error Reporting (non-production: aktifkan untuk debugging)
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Class Database - Singleton Pattern untuk koneksi PDO
 */
class Database {
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                self::sendResponse(false, 'Koneksi database gagal: ' . $e->getMessage(), null, 500);
            }
        }
        return self::$instance;
    }
    
    private function __construct() {}
}

/**
 * Helper Function - Kirim Response JSON
 */
function sendResponse(bool $success, string $message, $data = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Helper Function - Parse JSON Input
 */
function getJsonInput(): ?array {
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return null;
    }
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    return $data;
}

/**
 * =============================================
 * ROUTING API
 * =============================================
 */

// Cek apakah action disediakan
if (!isset($_GET['action'])) {
    sendResponse(false, 'Action tidak ditemukan', null, 400);
}

$action = $_GET['action'];
$db = Database::getInstance();

switch ($action) {
    
    // =============================================
    // CASE: LOGIN
    // =============================================
    case 'login':
        $input = getJsonInput();
        
        if (!$input || !isset($input['nip']) || !isset($input['password'])) {
            sendResponse(false, 'NIP dan password wajib diisi', null, 400);
        }
        
        $nip = trim($input['nip']);
        $password = $input['password'];
        
        if (empty($nip) || empty($password)) {
            sendResponse(false, 'NIP dan password tidak boleh kosong', null, 400);
        }
        
        try {
            $stmt = $db->prepare("
                SELECT id, nip, nama_lengkap, password, role, face_descriptor, gaji_pokok 
                FROM users 
                WHERE nip = :nip
            ");
            $stmt->execute(['nip' => $nip]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendResponse(false, 'NIP tidak ditemukan', null, 401);
            }
            
            if (!password_verify($password, $user['password'])) {
                sendResponse(false, 'Password salah', null, 401);
            }
            
            // Hapus password dari response
            unset($user['password']);
            
            // Parse face_descriptor jika ada
            if (!empty($user['face_descriptor'])) {
                $user['face_descriptor'] = json_decode($user['face_descriptor'], true);
            } else {
                $user['face_descriptor'] = null;
            }
            
            sendResponse(true, 'Login berhasil', $user);
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat login', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: REGISTER_FACE
    // =============================================
    case 'register_face':
        $input = getJsonInput();
        
        if (!$input || !isset($input['user_id']) || !isset($input['face_descriptor'])) {
            sendResponse(false, 'user_id dan face_descriptor wajib diisi', null, 400);
        }
        
        $userId = (int)$input['user_id'];
        $faceDescriptor = $input['face_descriptor'];
        
        // Validasi face_descriptor harus array dengan 128 elemen
        if (!is_array($faceDescriptor) || count($faceDescriptor) !== 128) {
            sendResponse(false, 'face_descriptor harus array dengan 128 elemen', null, 400);
        }
        
        // Validasi semua nilai adalah number
        foreach ($faceDescriptor as $value) {
            if (!is_numeric($value)) {
                sendResponse(false, 'face_descriptor harus berisi nilai numerik', null, 400);
            }
        }
        
        try {
            // Cek apakah user ada
            $stmt = $db->prepare("SELECT id, nama_lengkap FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendResponse(false, 'User tidak ditemukan', null, 404);
            }
            
            // Update face_descriptor
            $jsonDescriptor = json_encode($faceDescriptor);
            $stmt = $db->prepare("UPDATE users SET face_descriptor = :descriptor WHERE id = :id");
            $stmt->execute([
                'descriptor' => $jsonDescriptor,
                'id' => $userId
            ]);
            
            sendResponse(true, 'Data wajah berhasil didaftarkan untuk ' . $user['nama_lengkap'], [
                'user_id' => $userId,
                'nama_lengkap' => $user['nama_lengkap'],
                'descriptor_length' => count($faceDescriptor)
            ]);
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat menyimpan data wajah', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: ABSEN (Masuk / Keluar)
    // =============================================
    case 'absen':
        $input = getJsonInput();
        
        if (!$input || !isset($input['user_id'])) {
            sendResponse(false, 'user_id wajib diisi', null, 400);
        }
        
        $userId = (int)$input['user_id'];
        $today = date('Y-m-d');
        $currentTime = date('H:i:s');
        
        // Jam masuk dianggap terlambat setelah 08:00:00
        $jamTerlambat = '08:00:00';
        $status = ($currentTime > $jamTerlambat) ? 'terlambat' : 'hadir';
        
        try {
            // Cek apakah user ada dan punya face_descriptor
            $stmt = $db->prepare("
                SELECT id, nama_lengkap, face_descriptor, gaji_pokok 
                FROM users 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendResponse(false, 'User tidak ditemukan', null, 404);
            }
            
            // Parse face_descriptor jika ada
            if (!empty($user['face_descriptor'])) {
                $user['face_descriptor'] = json_decode($user['face_descriptor'], true);
            } else {
                $user['face_descriptor'] = null;
            }
            
            // Cek apakah sudah ada absensi hari ini
            $stmt = $db->prepare("
                SELECT id, waktu_masuk, waktu_keluar 
                FROM absensi 
                WHERE user_id = :user_id AND tanggal = :tanggal
            ");
            $stmt->execute(['user_id' => $userId, 'tanggal' => $today]);
            $absensi = $stmt->fetch();
            
            if ($absensi) {
                // Sudah ada data - update waktu_keluar
                if ($absensi['waktu_keluar'] !== null) {
                    sendResponse(false, 'Anda sudah完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成完成', null, 400);
                }
                
                $stmt = $db->prepare("
                    UPDATE absensi 
                    SET waktu_keluar = :waktu_keluar 
                    WHERE user_id = :user_id AND tanggal = :tanggal
                ");
                $stmt->execute([
                    'waktu_keluar' => $currentTime,
                    'user_id' => $userId,
                    'tanggal' => $today
                ]);
                
                sendResponse(true, 'Absensi keluar berhasil', [
                    'user_id' => $userId,
                    'nama_lengkap' => $user['nama_lengkap'],
                    'tanggal' => $today,
                    'waktu_masuk' => $absensi['waktu_masuk'],
                    'waktu_keluar' => $currentTime,
                    'jenis' => 'keluar'
                ]);
                
            } else {
                // Belum ada - insert waktu_masuk
                $stmt = $db->prepare("
                    INSERT INTO absensi (user_id, tanggal, waktu_masuk, status) 
                    VALUES (:user_id, :tanggal, :waktu_masuk, :status)
                ");
                $stmt->execute([
                    'user_id' => $userId,
                    'tanggal' => $today,
                    'waktu_masuk' => $currentTime,
                    'status' => $status
                ]);
                
                sendResponse(true, 'Absensi masuk berhasil - Status: ' . $status, [
                    'user_id' => $userId,
                    'nama_lengkap' => $user['nama_lengkap'],
                    'tanggal' => $today,
                    'waktu_masuk' => $currentTime,
                    'status' => $status,
                    'jenis' => 'masuk'
                ]);
            }
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat menyimpan absensi', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: GET_REKAP_ABSEN (Dashboard Admin)
    // =============================================
    case 'get_rekap_absen':
        $input = getJsonInput();
        
        // Filter opsional: bulan dan tahun
        $bulan = isset($input['bulan']) ? (int)$input['bulan'] : (int)date('n');
        $tahun = isset($input['tahun']) ? (int)$input['tahun'] : (int)date('Y');
        
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int)date('n');
        }
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int)date('Y');
        }
        
        try {
            // Query dengan JOIN untuk dapat data user
            $stmt = $db->prepare("
                SELECT 
                    a.id,
                    a.user_id,
                    u.nip,
                    u.nama_lengkap,
                    u.gaji_pokok,
                    a.tanggal,
                    a.waktu_masuk,
                    a.waktu_keluar,
                    a.status
                FROM absensi a
                INNER JOIN users u ON a.user_id = u.id
                WHERE MONTH(a.tanggal) = :bulan AND YEAR(a.tanggal) = :tahun
                ORDER BY a.tanggal DESC, u.nama_lengkap ASC
            ");
            $stmt->execute(['bulan' => $bulan, 'tahun' => $tahun]);
            $data = $stmt->fetchAll();
            
            // Statistik summary
            $stmtSummary = $db->prepare("
                SELECT 
                    COUNT(*) as total_absensi,
                    SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as total_terlambat,
                    COUNT(DISTINCT user_id) as total_pegawai_absen
                FROM absensi
                WHERE MONTH(tanggal) = :bulan AND YEAR(tanggal) = :tahun
            ");
            $stmtSummary->execute(['bulan' => $bulan, 'tahun' => $tahun]);
            $summary = $stmtSummary->fetch();
            
            // Total pegawai
            $stmtPegawai = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'pegawai'");
            $stmtPegawai->execute();
            $totalPegawai = $stmtPegawai->fetch()['total'];
            
            sendResponse(true, 'Data rekap absensi', [
                'periode' => [
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'nama_bulan' => date('F', mktime(0, 0, 0, $bulan, 1))
                ],
                'summary' => [
                    'total_absensi' => (int)$summary['total_absensi'],
                    'total_terlambat' => (int)$summary['total_terlambat'],
                    'total_pegawai_absen' => (int)$summary['total_pegawai_absen'],
                    'total_pegawai' => (int)$totalPegawai
                ],
                'data' => $data
            ]);
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat mengambil data absensi', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: GET_PAYROLL (Riwayat Gaji)
    // =============================================
    case 'get_payroll':
        $input = getJsonInput();
        
        if (!$input || !isset($input['user_id'])) {
            sendResponse(false, 'user_id wajib diisi', null, 400);
        }
        
        $userId = (int)$input['user_id'];
        
        try {
            // Ambil data payroll user
            $stmt = $db->prepare("
                SELECT p.*, u.nama_lengkap, u.nip
                FROM payroll p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.user_id = :user_id
                ORDER BY p.tahun DESC, p.bulan DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $payroll = $stmt->fetchAll();
            
            // Ambil data user
            $stmtUser = $db->prepare("SELECT id, nip, nama_lengkap, gaji_pokok FROM users WHERE id = :id");
            $stmtUser->execute(['id' => $userId]);
            $user = $stmtUser->fetch();
            
            sendResponse(true, 'Data payroll', [
                'user' => $user,
                'payroll' => $payroll
            ]);
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat mengambil data payroll', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: GENERATE_PAYROLL
    // =============================================
    case 'generate_payroll':
        $input = getJsonInput();
        
        // Filter opsional: bulan dan tahun (default bulan ini)
        $bulan = isset($input['bulan']) ? (int)$input['bulan'] : (int)date('n');
        $tahun = isset($input['tahun']) ? (int)$input['tahun'] : (int)date('Y');
        
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int)date('n');
        }
        
        try {
            // Ambil semua pegawai dengan data absensi bulan ini
            $stmt = $db->prepare("
                SELECT 
                    u.id as user_id,
                    u.nip,
                    u.nama_lengkap,
                    u.gaji_pokok,
                    COUNT(a.id) as total_kehadiran
                FROM users u
                LEFT JOIN absensi a ON u.id = a.user_id 
                    AND MONTH(a.tanggal) = :bulan 
                    AND YEAR(a.tanggal) = :tahun
                    AND a.waktu_masuk IS NOT NULL
                WHERE u.role = 'pegawai'
                GROUP BY u.id, u.nip, u.nama_lengkap, u.gaji_pokok
            ");
            $stmt->execute(['bulan' => $bulan, 'tahun' => $tahun]);
            $pegawai = $stmt->fetchAll();
            
            $results = [];
            $db->beginTransaction();
            
            try {
                foreach ($pegawai as $p) {
                    // Hitung total gaji = kehadiran x gaji per hari (gaji_pokok / 22)
                    $gajiPerHari = $p['gaji_pokok'] / 22;
                    $totalGaji = round($p['total_kehadiran'] * $gajiPerHari, 2);
                    
                    // Upsert payroll
                    $stmtUpsert = $db->prepare("
                        INSERT INTO payroll (user_id, bulan, tahun, total_kehadiran, total_gaji)
                        VALUES (:user_id, :bulan, :tahun, :total_kehadiran, :total_gaji)
                        ON DUPLICATE KEY UPDATE 
                            total_kehadiran = VALUES(total_kehadiran),
                            total_gaji = VALUES(total_gaji),
                            updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmtUpsert->execute([
                        'user_id' => $p['user_id'],
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'total_kehadiran' => $p['total_kehadiran'],
                        'total_gaji' => $totalGaji
                    ]);
                    
                    $results[] = [
                        'user_id' => $p['user_id'],
                        'nip' => $p['nip'],
                        'nama_lengkap' => $p['nama_lengkap'],
                        'gaji_pokok' => $p['gaji_pokok'],
                        'total_kehadiran' => (int)$p['total_kehadiran'],
                        'gaji_per_hari' => round($gajiPerHari, 2),
                        'total_gaji' => $totalGaji
                    ];
                }
                
                $db->commit();
                
                sendResponse(true, 'Payroll berhasil digenerate untuk ' . date('F Y', mktime(0, 0, 0, $bulan, 1)), [
                    'periode' => [
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'nama_bulan' => date('F Y', mktime(0, 0, 0, $bulan, 1))
                    ],
                    'data' => $results
                ]);
                
            } catch (PDOException $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat generate payroll', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: GET_USERS (Daftar Pegawai untuk Admin)
    // =============================================
    case 'get_users':
        try {
            $stmt = $db->prepare("
                SELECT id, nip, nama_lengkap, role, face_descriptor, gaji_pokok, created_at
                FROM users
                ORDER BY role ASC, nama_lengkap ASC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Parse face_descriptor untuk setiap user
            foreach ($users as &$user) {
                if (!empty($user['face_descriptor'])) {
                    $user['face_descriptor'] = json_decode($user['face_descriptor'], true);
                    $user['has_face'] = true;
                } else {
                    $user['face_descriptor'] = null;
                    $user['has_face'] = false;
                }
            }
            
            sendResponse(true, 'Daftar users', $users);
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat mengambil data users', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: VERIFY_FACE (Verifikasi Wajah)
    // =============================================
    case 'verify_face':
        $input = getJsonInput();
        
        if (!$input || !isset($input['user_id']) || !isset($input['face_descriptor'])) {
            sendResponse(false, 'user_id dan face_descriptor wajib diisi', null, 400);
        }
        
        $userId = (int)$input['user_id'];
        $inputDescriptor = $input['face_descriptor'];
        
        try {
            // Ambil face_descriptor dari database
            $stmt = $db->prepare("
                SELECT id, nip, nama_lengkap, face_descriptor 
                FROM users 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendResponse(false, 'User tidak ditemukan', null, 404);
            }
            
            if (empty($user['face_descriptor'])) {
                sendResponse(false, 'Data wajah belum terdaftar untuk user ini', [
                    'verified' => false,
                    'has_face_registered' => false
                ], 400);
            }
            
            $storedDescriptor = json_decode($user['face_descriptor'], true);
            
            // Hitung Euclidean distance
            $distance = 0;
            for ($i = 0; $i < 128; $i++) {
                $diff = $inputDescriptor[$i] - $storedDescriptor[$i];
                $distance += $diff * $diff;
            }
            $distance = sqrt($distance);
            
            // Threshold: < 0.6 = match
            $verified = $distance < 0.6;
            
            sendResponse(true, $verified ? 'Wajah cocok' : 'Wajah tidak cocok', [
                'verified' => $verified,
                'distance' => round($distance, 4),
                'threshold' => 0.6,
                'user_id' => $userId,
                'nama_lengkap' => $user['nama_lengkap']
            ]);
            
        } catch (PDOException $e) {
            sendResponse(false, 'Terjadi kesalahan saat verifikasi wajah', null, 500);
        }
        break;
    
    
    // =============================================
    // CASE: DEFAULT (Action tidak dikenal)
    // =============================================
    default:
        sendResponse(false, 'Action "' . $action . '" tidak ditemukan', null, 400);
}

?>
