<?php
// ====================================================
// JAILBREAK BOT - KALI LINUX EDITION
// VERSION: 8.0 (27 Feb 2026)
// GEMINI FIXED - ALL USERS WELCOME
// ====================================================

// ========== LOAD ENVIRONMENT ==========
// Untuk testing di Kali, kita pake manual dulu
// Nanti kalo deploy ke Railway, pake getenv
define('BOT_TOKEN', '8045718722:AAGfUipGjliHIqB0zJ9Y7y0JUCyQ8eYGyps');
define('ADMIN_ID', '7710155531');
define('GEMINI_API_KEY', 'AIzaSyBwkeqNk7VFKDN3y1qhu-LFyWNT598UieU');
define('SAWERIA_LINK', 'https://saweria.co/Kikomaukiko');
define('PREVIEW_FILE_ID', 'BQACAgUAAxkBAANQaZ8AAcdi8rwd5JLrKVvV1x-h_vVrAAKXGwACR4b5VLZWFuSlBdUIOgQ');
define('FULL_PDF_FILE_ID', 'BQACAgUAAxkDAAIBQWmgFiL1zVp9BTcqBq1o4GHYYSUmAALEHAAC6xYAAVXigx0pjSHNNToE');
define('PDF_PASSWORD', 'GQ3A-J6G8-5235');
define('CLOUDCONVERT_LINK', 'https://share.google/BXdUWNT2rXBg3syi4');
define('QRIS_FILE_ID', 'AgACAgUAAxkDAAIBm2mg8iduJZA5v-PtiEjxzVailuP5AAJjDWsb6xYIVTD5YvSly8CBAQADAgADbQADOgQ');
define('DB_FILE', 'database.json');

// Aktifkan error logging
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');
error_log("🚀 BOT STARTED at " . date('Y-m-d H:i:s'));

// ========== DAFTAR MODEL GEMINI YANG PASTI AKTIF ==========
$GEMINI_MODELS = [
    'gemini-1.5-flash',      // Paling stabil
    'gemini-1.5-pro',        // Cadangan
    'gemini-1.0-pro'         // Legacy
];

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
        error_log("❌ Invalid JSON: " . $input);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
}

// ========== HEALTHCHECK HANDLER ==========
http_response_code(200);
header('Content-Type: text/plain');
echo "🚀 JAILBREAK BOT - KALI LINUX EDITION\n";
echo "====================================\n";
echo "✅ Status: RUNNING\n";
echo "✅ PHP Version: " . phpversion() . "\n";
echo "✅ Bot Token: " . substr(BOT_TOKEN, 0, 15) . "...\n";
echo "✅ Admin ID: " . ADMIN_ID . "\n";
echo "✅ File: ScriptMaster.pdf\n";
echo "✅ QRIS: TERPASANG\n";
echo "✅ Time: " . date('Y-m-d H:i:s') . "\n";
echo "====================================\n";
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

// ========== FUNGSI UPDATE HANDLER (TANPA FILTER) ==========
function processUpdate($update, &$db) {
    error_log("🔔 UPDATE RECEIVED: " . json_encode($update));
    
    if (isset($update['callback_query'])) {
        error_log("🟢 CALLBACK QUERY dari " . $update['callback_query']['from']['id']);
        handleCallbackQuery($update['callback_query'], $db);
        return;
    }
    
    if (!isset($update['message'])) {
        error_log("⚠️ UPDATE tanpa message");
        return;
    }
    
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $text = $msg['text'] ?? '';
    $username = $msg['from']['username'] ?? 'user_' . $chat_id;
    $nama = $msg['from']['first_name'] ?? 'Sob';
    
    error_log("💬 PESAN dari $chat_id ($username): $text");
    
    // HANDLE SEMUA USER, BUKAN CUMA ADMIN!
    if (isset($msg['photo'])) {
        error_log("📸 FOTO dari $chat_id");
        handlePaymentProof($chat_id, $msg, $username, $nama, $db);
        return;
    }
    
    if (isset($msg['document'])) {
        error_log("📄 DOKUMEN dari $chat_id");
        handlePaymentProof($chat_id, $msg, $username, $nama, $db);
        return;
    }
    
    switch ($text) {
        case '/start':
            error_log("✅ /start dari $chat_id");
            sapaUser($chat_id, $nama, $username);
            break;
        case '/katalog':
            error_log("✅ /katalog dari $chat_id");
            tampilKatalog($chat_id);
            break;
        case '/beli':
            error_log("✅ /beli dari $chat_id");
            prosesBeli($chat_id, $username, $nama, $db);
            break;
        case '/chat':
            error_log("✅ /chat dari $chat_id");
            cekChatAccess($chat_id, $nama, $db);
            break;
        case '/limit':
            error_log("✅ /limit dari $chat_id");
            cekLimit($chat_id, $db);
            break;
        case '/lupapassword':
            error_log("✅ /lupapassword dari $chat_id");
            kirimUlangPassword($chat_id, $db);
            break;
        default:
            error_log("❓ UNKNOWN COMMAND dari $chat_id: $text");
            if (isset($db['chats'][$chat_id]['mode']) && $db['chats'][$chat_id]['mode'] == 'chat') {
                error_log("💬 CHAT MODE dari $chat_id");
                handleChat($chat_id, $text, $nama, $db);
            }
            break;
    }
}

// ========== FUNGSI CALLBACK (TETAP FILTER ADMIN) ==========
function handleCallbackQuery($callback, &$db) {
    $data = $callback['data'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $from_id = $callback['from']['id'];
    $message = $callback['message'];
    
    error_log("🟢 CALLBACK: $data dari user $from_id");
    
    // INI FILTER KHUSUS ADMIN UNTUK KONFIRMASI/ TOLAK
    if ($from_id != ADMIN_ID) {
        error_log("⚠️ BUKAN ADMIN: $from_id - IGNORED");
        answerCallbackQuery($callback['id'], "Hanya admin yang bisa melakukan ini!", true);
        return;
    }
    
    if (strpos($data, 'confirm_') === 0) {
        $user_chat_id = str_replace('confirm_', '', $data);
        error_log("✅ KONFIRMASI untuk user $user_chat_id");
        
        kirimPassword($user_chat_id, $db);
        
        $new_caption = $message['caption'] . "\n\n✅ *CONFIRMED*";
        editMessageCaption($chat_id, $message_id, $new_caption);
        editMessageReplyMarkup($chat_id, $message_id, null);
        answerCallbackQuery($callback['id'], "✅ Pembayaran dikonfirmasi! Password udah dikirim.");
    }
    
    elseif (strpos($data, 'reject_') === 0) {
        $user_chat_id = str_replace('reject_', '', $data);
        error_log("❌ TOLAK untuk user $user_chat_id");
        
        $new_caption = $message['caption'] . "\n\n❌ *REJECTED*";
        editMessageCaption($chat_id, $message_id, $new_caption);
        editMessageReplyMarkup($chat_id, $message_id, null);
        
        kirimPesan($user_chat_id, "❌ *Maaf, bukti transfer Anda ditolak.*\n\nPastikan Anda mentransfer Rp25.000 ke Saweria dan kirim screenshot yang jelas.\n\nKalo ada kendala, hubungi admin.");
        
        answerCallbackQuery($callback['id'], "❌ Pembayaran ditolak. User sudah dinotifikasi.");
    }
}

// ========== FUNGSI GEMINI (STABIL PAKE 1.5) ==========
function callGemini($prompt) {
    $api_key = GEMINI_API_KEY;
    global $GEMINI_MODELS;
    
    $max_retries = 2;
    $retry_delay = 1;
    
    foreach ($GEMINI_MODELS as $model) {
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            error_log("🤖 Mencoba model: $model (percobaan $attempt)");
            
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
            
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1024,
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($http_code == 200) {
                $result = json_decode($response, true);
                if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    error_log("✅ Gemini sukses dengan model: $model");
                    return $result['candidates'][0]['content']['parts'][0]['text'];
                }
            } else {
                error_log("⚠️ Model $model gagal: HTTP $http_code");
                if ($attempt < $max_retries) {
                    sleep($retry_delay);
                }
            }
        }
    }
    
    error_log("❌ Semua model gagal");
    return "⚠️ Maaf, layanan sedang sibuk. Silakan coba lagi nanti.";
}

// ========== FUNGSI KIRIM GAMBAR ==========
function kirimGambarId($chat_id, $file_id, $caption = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
    $post = [
        'chat_id' => $chat_id,
        'photo' => $file_id,
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

// ========== FUNGSI KIRIM PASSWORD ==========
function kirimPassword($chat_id, &$db) {
    $password = PDF_PASSWORD;
    
    $caption = "📄 *SCRIPT MASTER PDF*\n\n"
             . "🔑 *Password:* `$password`\n\n"
             . "🎁 Bonus chat: /chat\n\n"
             . "📌 *Convert PDF ke TXT:*\n"
             . CLOUDCONVERT_LINK;
    
    kirimFileId($chat_id, FULL_PDF_FILE_ID, $caption);
    
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
    
    $db['pending'] = array_filter($db['pending'], fn($p) => $p['chat_id'] != $chat_id);
    saveDB($db);
    
    kirimPesan(ADMIN_ID, "✅ Password terkirim ke `$chat_id`\nPassword: `$password`");
}

// ========== FUNGSI HANDLE BUKTI TRANSFER ==========
function handlePaymentProof($chat_id, $msg, $username, $nama, &$db) {
    $caption = "🔔 *BUKTI TRANSFER*\n\n"
             . "Nama: $nama\n"
             . "Username: @$username\n"
             . "Chat ID: `$chat_id`\n"
             . "Waktu: " . date('d/m/Y H:i:s');
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ KONFIRMASI', 'callback_data' => 'confirm_' . $chat_id],
                ['text' => '❌ TOLAK', 'callback_data' => 'reject_' . $chat_id]
            ]
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

// ========== FUNGSI KIRIM DENGAN KEYBOARD ==========
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
    $text = "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "📌 *SCRIPT MASTER PACK*\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "Harga: Rp 25.000\n"
          . "File: `ScriptMaster.pdf` (terenkripsi)\n"
          . "Isi: Unlock Deepseek, Gemini, Kimi AI tanpa batasan\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "🎁 *BONUS PER PEMBELIAN:*\n"
          . "• Akses *Gemini 2.5 Pro* via bot ini (20x chat)\n"
          . "• Update metode jailbreak terbaru\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "Cara beli:\n"
          . "1. Klik /beli\n"
          . "2. Scan QRIS\n"
          . "3. Transfer Rp25.000\n"
          . "4. Kirim bukti transfer\n\n"
          . "Langsung klik:\n"
          . "/beli";
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI PROSES BELI ==========
function prosesBeli($chat_id, $username, $nama, &$db) {
    kirimGambarId($chat_id, QRIS_FILE_ID, "📱 *SCAN QRIS INI*\n\nScan QR code di atas untuk transfer Rp25.000 ke Saweria.\n\nAtau klik link: " . SAWERIA_LINK);
    
    $db['pending'][] = ['chat_id' => $chat_id, 'username' => $username, 'nama' => $nama, 'waktu' => time()];
    saveDB($db);
    
    $text = "✅ *Pesanan diterima!*\n\n"
          . "Silakan transfer Rp25.000 via QRIS di atas.\n\n"
          . "**SETELAH TRANSFER**, kirim BUKTI TRANSFER (screenshot) KE BOT INI.\n\n"
          . "Admin bakal verifikasi dan kirimkan password.\n\n"
          . "Bonus: 20x chat Gemini!";
    
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
        $caption = "🔑 *Password terakhir:* `$latest`\n\nFile: ScriptMaster.pdf\n\n"
                 . "📌 *Convert PDF ke TXT:*\n" . CLOUDCONVERT_LINK;
        kirimPesan($chat_id, $caption);
        kirimPesan(ADMIN_ID, "🔔 User `$chat_id` minta password: `$latest`");
    } else {
        kirimPesan($chat_id, "❌ Belum pernah beli. Ketik /beli");
    }
}

// ========== FUNGSI CHAT GEMINI ==========
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

function cekLimit($chat_id, &$db) {
    if (!isset($db['chats'][$chat_id])) {
        kirimPesan($chat_id, "Belum punya akses chat. Beli dulu: /beli");
        return;
    }
    $total = count(array_filter($db['transactions'], fn($t) => $t['chat_id'] == $chat_id));
    $used = 20 * $total - $db['chats'][$chat_id]['remaining'];
    kirimPesan($chat_id, "📊 *Status Chat:*\n"
                       . "Total pembelian: $total kali\n"
                       . "Total bonus: " . (20 * $total) . " chat\n"
                       . "Sudah dipakai: $used\n"
                       . "Sisa chat: {$db['chats'][$chat_id]['remaining']}");
}

// ========== FUNGSI KIRIM PESAN DASAR ==========
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

function editMessageReplyMarkup($chat_id, $message_id, $reply_markup) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageReplyMarkup";
    $post = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => $reply_markup
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

function answerCallbackQuery($callback_query_id, $text, $show_alert = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    $post = [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => $show_alert
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

