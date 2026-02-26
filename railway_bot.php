<?php
// ====================================================
// JAILBREAK BOT - RAILWAY EDITION (FINAL VERSION)
// PASSWORD MATCHING 100% - SETIAP TRANSAKSI PASSWORD UNIK
// ====================================================

// ========== LOAD ENVIRONMENT ==========
$required_vars = ['BOT_TOKEN', 'ADMIN_ID', 'GEMINI_API_KEY'];
foreach ($required_vars as $var) {
    if (!getenv($var)) {
        http_response_code(500);
        die("❌ Environment variable $var tidak ditemukan!\n");
    }
}

define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_ID', getenv('ADMIN_ID'));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
define('SAWERIA_LINK', getenv('SAWERIA_LINK') ?: 'https://saweria.co/Kikomaukiko');
define('MASTER_PDF_ID', '1dK2tqUMK5WMGNPevoWipadd3y1c_XWn1');
define('PREVIEW_FILE_ID', 'BQACAgUAAxkBAANQaZ8AAcdi8rwd5JLrKVvV1x-h_vVrAAKXGwACR4b5VLZWFuSlBdUIOgQ');
define('DB_FILE', 'database.json');

// ========== DATABASE ==========
function loadDB() {
    if (file_exists(DB_FILE)) {
        return json_decode(file_get_contents(DB_FILE), true);
    }
    return ['transactions' => [], 'pending' => [], 'chats' => []];
}

function saveDB($db) {
    file_put_contents(DB_FILE, json_encode($db, JSON_PRETTY_PRINT));
}

// ========== WEBHOOK HANDLER ==========
$input = file_get_contents('php://input');

if (!empty($input)) {
    $update = json_decode($input, true);
    if ($update) {
        $db = loadDB();
        processUpdate($update, $db);
        saveDB($db);
        
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => 'Webhook processed']);
        exit;
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
}

// ========== HEALTHCHECK HANDLER ==========
http_response_code(200);
header('Content-Type: text/plain');
echo "🚀 JAILBREAK BOT - FINAL FIX\n";
echo "==========================\n";
echo "✅ Status: RUNNING\n";
echo "✅ PHP Version: " . phpversion() . "\n";
echo "✅ Bot Token: " . substr(BOT_TOKEN, 0, 15) . "...\n";
echo "✅ Admin ID: " . ADMIN_ID . "\n";
echo "✅ Gemini API: " . substr(GEMINI_API_KEY, 0, 10) . "...\n";
echo "✅ Time: " . date('Y-m-d H:i:s') . "\n";
echo "✅ Environment: " . (getenv('RAILWAY_ENVIRONMENT') ?: 'production') . "\n";
echo "==========================\n";
echo "📡 Webhook URL: https://botjailtelegram.up.railway.app\n";
echo "📦 Pending updates: " . getPendingCount() . "\n";
exit(0);

// ========== FUNGSI GET PENDING COUNT ==========
function getPendingCount() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/getWebhookInfo");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['result']['pending_update_count'] ?? 'unknown';
}

// ========== FUNGSI UPDATE HANDLER ==========
function processUpdate($update, &$db) {
    if (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query'], $db);
        return;
    }
    
    if (!isset($update['message'])) return;
    
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $text = $msg['text'] ?? '';
    $username = $msg['from']['username'] ?? 'user_' . $chat_id;
    $nama = $msg['from']['first_name'] ?? 'Sob';
    
    if (isset($msg['photo']) || isset($msg['document'])) {
        handlePaymentProof($chat_id, $msg, $username, $nama, $db);
        return;
    }
    
    switch ($text) {
        case '/start':
            sapaUser($chat_id, $nama, $username);
            break;
        case '/katalog':
            tampilKatalog($chat_id);
            break;
        case '/beli':
            prosesBeli($chat_id, $username, $nama, $db);
            break;
        case '/chat':
            cekChatAccess($chat_id, $nama, $db);
            break;
        case '/limit':
            cekLimit($chat_id, $db);
            break;
        case '/lupapassword':
            kirimUlangPassword($chat_id, $db);
            break;
        default:
            if (isset($db['chats'][$chat_id]['mode']) && $db['chats'][$chat_id]['mode'] == 'chat') {
                handleChat($chat_id, $text, $nama, $db);
            }
            break;
    }
}

// ========== FUNGSI CALLBACK ==========
function handleCallbackQuery($callback, &$db) {
    $data = $callback['data'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $from_id = $callback['from']['id'];
    
    if ($from_id != ADMIN_ID) {
        answerCallbackQuery($callback['id'], "Lu bukan admin!");
        return;
    }
    
    if (strpos($data, 'confirm_') === 0) {
        $user_chat_id = str_replace('confirm_', '', $data);
        
        // Proses download dan kirim PDF dengan password
        $result = kirimPDFdenganPassword($user_chat_id, $db);
        
        if ($result['success']) {
            $new_caption = $callback['message']['caption'] . "\n\n✅ *SUDAH DIKONFIRMASI*\nPassword: `{$result['password']}`";
            editMessageCaption($chat_id, $message_id, $new_caption);
            answerCallbackQuery($callback['id'], "✅ PDF udah dikirim! Password: {$result['password']}");
        } else {
            $new_caption = $callback['message']['caption'] . "\n\n❌ *GAGAL: {$result['error']}*";
            editMessageCaption($chat_id, $message_id, $new_caption);
            answerCallbackQuery($callback['id'], "❌ Gagal: {$result['error']}");
        }
    }
}

// ========== FUNGSI KIRIM PDF DENGAN PASSWORD (MATCHING 100%) ==========
function kirimPDFdenganPassword($chat_id, &$db) {
    
    // 1. Generate password random dengan format XXXX-XXXX-XXXX
    $part1 = strtoupper(substr(md5(uniqid() . $chat_id . rand()), 0, 4));
    $part2 = strtoupper(substr(md5(uniqid() . time() . rand()), 0, 4));
    $part3 = rand(1000, 9999);
    $password = $part1 . '-' . $part2 . '-' . $part3;
    
    // 2. Download file dari Google Drive
    $master_file = 'master_' . $chat_id . '_' . time() . '.pdf';
    
    // Coba download dengan gdown, fallback ke curl/wget
    $download_success = false;
    $download_error = '';
    
    // Method 1: gdown
    $download_cmd = "gdown https://drive.google.com/uc?id=" . MASTER_PDF_ID . " -O " . $master_file . " 2>&1";
    exec($download_cmd, $dl_output, $dl_return);
    
    if ($dl_return === 0 && file_exists($master_file) && filesize($master_file) > 0) {
        $download_success = true;
    } else {
        // Method 2: curl fallback
        $curl_cmd = "curl -L -b /tmp/cookie.txt -c /tmp/cookie.txt -o " . $master_file . " 'https://drive.google.com/uc?export=download&id=" . MASTER_PDF_ID . "' 2>&1";
        exec($curl_cmd, $curl_output, $curl_return);
        
        if ($curl_return === 0 && file_exists($master_file) && filesize($master_file) > 0) {
            $download_success = true;
        } else {
            $download_error = "Gagal download: " . implode("\n", array_merge($dl_output, $curl_output));
        }
    }
    
    if (!$download_success) {
        kirimPesan(ADMIN_ID, "❌ Gagal download PDF untuk user $chat_id\n$download_error");
        return ['success' => false, 'error' => 'Gagal download file'];
    }
    
    // 3. Enkrip file dengan password yang baru (PASSWORD MATCHING 100%)
    $encrypted_file = 'enc_' . $chat_id . '_' . time() . '.pdf';
    
    // Gunakan qpdf untuk enkripsi dengan password yang sama
    $encrypt_cmd = "qpdf --encrypt user-password={$password} owner-password={$password} 256 -- "
                 . escapeshellarg($master_file) . " "
                 . escapeshellarg($encrypted_file) . " 2>&1";
    
    exec($encrypt_cmd, $enc_output, $enc_return);
    
    if ($enc_return !== 0 || !file_exists($encrypted_file)) {
        kirimPesan(ADMIN_ID, "❌ Gagal encrypt PDF untuk user $chat_id: " . implode("\n", $enc_output));
        unlink($master_file);
        return ['success' => false, 'error' => 'Gagal encrypt file'];
    }
    
    // 4. Kirim file ke user
    $caption = "📄 *FULL PDF JAILBREAK*\n\n"
             . "🔑 *Password:* `$password`\n\n"
             . "⚠️ Password ini 100% SINKRON dengan file PDF.\n"
             . "Gunakan password di atas untuk membuka file.\n\n"
             . "📌 Kalo lupa password, ketik /lupapassword\n"
             . "🎁 Bonus chat: /chat (sisa " . (isset($db['chats'][$chat_id]['remaining']) ? $db['chats'][$chat_id]['remaining'] + 20 : 20) . ")";
    
    $send_result = kirimFile($chat_id, $encrypted_file, $caption);
    
    // 5. Simpan ke database
    $db['transactions'][] = [
        'chat_id' => $chat_id,
        'password' => $password,
        'waktu' => time(),
        'bonus_chat' => 20
    ];
    
    if (!isset($db['chats'][$chat_id])) {
        $db['chats'][$chat_id] = ['remaining' => 20, 'mode' => 'idle', 'history' => []];
    } else {
        $db['chats'][$chat_id]['remaining'] += 20;
    }
    
    // Hapus dari pending
    $db['pending'] = array_filter($db['pending'], fn($p) => $p['chat_id'] != $chat_id);
    
    // Hapus file sementara
    @unlink($master_file);
    @unlink($encrypted_file);
    
    // Notifikasi admin
    kirimPesan(ADMIN_ID, "✅ PDF terkirim ke `$chat_id`\nPassword: `$password`");
    
    return ['success' => true, 'password' => $password];
}

// ========== FUNGSI PAYMENT PROOF ==========
function handlePaymentProof($chat_id, $msg, $username, $nama, &$db) {
    $caption = "🔔 *BUKTI TRANSFER*\n\nNama: $nama\nUsername: @$username\nChat ID: `$chat_id`\nWaktu: " . date('d/m/Y H:i:s');
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '✅ KONFIRMASI', 'callback_data' => 'confirm_' . $chat_id]]
        ]
    ];
    
    if (isset($msg['photo'])) {
        $file_id = end($msg['photo'])['file_id'];
        kirimFotoWithKeyboard(ADMIN_ID, $file_id, $caption, $keyboard);
    } elseif (isset($msg['document'])) {
        $file_id = $msg['document']['file_id'];
        kirimDokumenWithKeyboard(ADMIN_ID, $file_id, $caption, $keyboard);
    }
    
    kirimPesan($chat_id, "✅ Bukti diterima! Admin akan konfirmasi.");
}

// ========== FUNGSI SAPA USER ==========
function sapaUser($chat_id, $nama, $username) {
    $text = "Halo *$nama*! 😂\nSelamat datang di *JAILBREAK STORE*\n\n"
          . "Fitur:\n• /katalog\n• /beli\n• /chat\n• /lupapassword\n\n"
          . "Username: $username\nChat ID: `$chat_id`\n\n"
          . "💰 Transfer ke: " . SAWERIA_LINK;
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI KATALOG ==========
function tampilKatalog($chat_id) {
    kirimFileId($chat_id, PREVIEW_FILE_ID, "📄 *PREVIEW PDF (2 Halaman)*");
    $text = "━━━━━━━━━━━\n📌 *AI JAILBREAK MEGA PACK*\nHarga: Rp 25.000\n"
          . "Isi: Unlock Deepseek, Gemini, Kimi AI\n\n"
          . "🎁 Bonus: 20x chat Gemini per pembelian\n\n"
          . "Cara beli:\n1. Transfer ke Saweria\n2. Klik /beli\n3. Kirim bukti transfer";
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI PROSES BELI ==========
function prosesBeli($chat_id, $username, $nama, &$db) {
    $db['pending'][] = ['chat_id' => $chat_id, 'username' => $username, 'nama' => $nama, 'waktu' => time()];
    saveDB($db);
    
    $text = "✅ *Pesanan diterima!*\n\nTransfer Rp25.000 ke:\n" . SAWERIA_LINK . "\n\n"
          . "SETELAH TRANSFER, kirim BUKTI TRANSFER (screenshot) KE BOT INI.";
    kirimPesan($chat_id, $text);
    kirimPesan(ADMIN_ID, "🔔 Order baru dari @$username\nChat ID: `$chat_id`");
}

// ========== FUNGSI LUPA PASSWORD ==========
function kirimUlangPassword($chat_id, &$db) {
    $latest = null;
    foreach (array_reverse($db['transactions']) as $t) {
        if ($t['chat_id'] == $chat_id) {
            $latest = $t['password'];
            break;
        }
    }
    if ($latest) {
        kirimPesan($chat_id, "🔑 Password terakhir: `$latest`");
        kirimPesan(ADMIN_ID, "🔔 User `$chat_id` minta password: `$latest`");
    } else {
        kirimPesan($chat_id, "❌ Belum pernah beli. Ketik /beli");
    }
}

// ========== FUNGSI GEMINI ==========
function cekChatAccess($chat_id, $nama, &$db) {
    if (!isset($db['chats'][$chat_id])) {
        kirimPesan($chat_id, "❌ Beli dulu: /beli");
        return;
    }
    if ($db['chats'][$chat_id]['remaining'] <= 0) {
        kirimPesan($chat_id, "⚠️ Bonus habis. Beli lagi: /beli");
        return;
    }
    $db['chats'][$chat_id]['mode'] = 'chat';
    saveDB($db);
    kirimPesan($chat_id, "🤖 Mode chat aktif! Sisa: {$db['chats'][$chat_id]['remaining']}\nKetik /stop");
}

function handleChat($chat_id, $prompt, $nama, &$db) {
    if ($prompt == '/stop') {
        $db['chats'][$chat_id]['mode'] = 'idle';
        saveDB($db);
        kirimPesan($chat_id, "👋 Mode chat dimatikan.");
        return;
    }
    if ($db['chats'][$chat_id]['remaining'] <= 0) {
        $db['chats'][$chat_id]['mode'] = 'idle';
        saveDB($db);
        kirimPesan($chat_id, "⚠️ Bonus habis. Beli lagi: /beli");
        return;
    }
    $db['chats'][$chat_id]['remaining']--;
    $remaining = $db['chats'][$chat_id]['remaining'];
    $reply = callGemini($prompt);
    $db['chats'][$chat_id]['history'][] = ['user' => $prompt, 'bot' => $reply];
    saveDB($db);
    kirimPesan($chat_id, "*Gemini:* $reply\n\nSisa: $remaining\nKetik /stop");
}

function callGemini($prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=" . GEMINI_API_KEY;
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200) {
        return "⚠️ Error Gemini (HTTP $http_code)";
    }
    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? "⚠️ Gemini gak bisa jawab.";
}

function cekLimit($chat_id, &$db) {
    if (!isset($db['chats'][$chat_id])) {
        kirimPesan($chat_id, "Belum punya akses chat. Beli dulu: /beli");
        return;
    }
    $total = count(array_filter($db['transactions'], fn($t) => $t['chat_id'] == $chat_id));
    kirimPesan($chat_id, "📊 Status:\nTotal beli: $total\nSisa chat: {$db['chats'][$chat_id]['remaining']}");
}

// ========== FUNGSI KIRIM PESAN ==========
function kirimPesan($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $post = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function kirimFileId($chat_id, $file_id, $caption = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $post = ['chat_id' => $chat_id, 'document' => $file_id, 'caption' => $caption, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function kirimFile($chat_id, $file_path, $caption = '') {
    if (!file_exists($file_path)) {
        kirimPesan(ADMIN_ID, "❌ File $file_path tidak ditemukan!");
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $post = [
        'chat_id' => $chat_id,
        'document' => new CURLFile(realpath($file_path)),
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 200;
}

function kirimFotoWithKeyboard($chat_id, $file_id, $caption, $keyboard) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
    $post = [
        'chat_id' => $chat_id,
        'photo' => $file_id,
        'caption' => $caption,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($keyboard)
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function kirimDokumenWithKeyboard($chat_id, $file_id, $caption, $keyboard) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $post = [
        'chat_id' => $chat_id,
        'document' => $file_id,
        'caption' => $caption,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($keyboard)
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function editMessageCaption($chat_id, $message_id, $caption) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageCaption";
    $post = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function answerCallbackQuery($callback_query_id, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    $post = [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => false
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

