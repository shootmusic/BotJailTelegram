<?php
// ====================================================
// JAILBREAK BOT - FINAL EDITION
// AI: ASIST MASTER (Powered by Groq)
// OPTIMIZED FOR RAILWAY - FAST HEALTHCHECK
// ====================================================

// ========== HANDLE HEALTHCHECK CEPAT ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/') {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo "OK";
    exit(0);
}

// ========== SEMUA KONFIGURASI DARI ENVIRONMENT ==========
$required_vars = [
    'BOT_TOKEN', 
    'ADMIN_ID', 
    'GROQ_API_KEY',
    'PREVIEW_FILE_ID',
    'FULL_PDF_FILE_ID',
    'PDF_PASSWORD',
    'QRIS_FILE_ID'
];

foreach ($required_vars as $var) {
    if (!getenv($var)) {
        http_response_code(500);
        die("❌ Environment variable $var tidak ditemukan!\n");
    }
}

define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_ID', getenv('ADMIN_ID'));
define('GROQ_API_KEY', getenv('GROQ_API_KEY'));
define('PREVIEW_FILE_ID', getenv('PREVIEW_FILE_ID'));
define('FULL_PDF_FILE_ID', getenv('FULL_PDF_FILE_ID'));
define('PDF_PASSWORD', getenv('PDF_PASSWORD'));
define('QRIS_FILE_ID', getenv('QRIS_FILE_ID'));

// Optional dengan default
define('SAWERIA_LINK', getenv('SAWERIA_LINK') ?: 'https://saweria.co/Kikomaukiko');
define('CLOUDCONVERT_LINK', getenv('CLOUDCONVERT_LINK') ?: 'https://share.google/BXdUWNT2rXBg3syi4');
define('DB_FILE', 'database.json');

error_log("🚀 BOT STARTED at " . date('Y-m-d H:i:s'));

// ========== MODEL GROQ (UNTUK ASIST MASTER) ==========
$GROQ_MODELS = [
    'mixtral-8x7b-32768',   // Model utama (cepat & akurat)
    'llama2-70b-4096',       // Model cadangan 1
    'gemma-7b-it'            // Model cadangan 2
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
        echo json_encode(['ok' => true]);
        exit;
    }
}

// ========== HEALTHCHECK RESPONSE (JAGA-JAGA) ==========
if (empty($input)) {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo "🚀 JAILBREAK BOT - RUNNING";
    exit(0);
}

// ========== FUNGSI UPDATE HANDLER (UNTUK SEMUA USER) ==========
function processUpdate($update, &$db) {
    // Handle callback query (tombol) - KHUSUS ADMIN
    if (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query'], $db);
        return;
    }
    
    // Handle pesan biasa - UNTUK SEMUA USER (TIDAK ADA FILTER!)
    if (!isset($update['message'])) return;
    
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $text = $msg['text'] ?? '';
    $username = $msg['from']['username'] ?? 'user_' . $chat_id;
    $nama = $msg['from']['first_name'] ?? 'Sob';
    
    error_log("💬 PESAN dari $chat_id ($username): $text");
    
    // Handle foto / dokumen (bukti transfer) - UNTUK SEMUA USER
    if (isset($msg['photo']) || isset($msg['document'])) {
        error_log("📸 BUKTI TRANSFER dari $chat_id");
        handlePaymentProof($chat_id, $msg, $username, $nama, $db);
        return;
    }
    
    // Command handler - UNTUK SEMUA USER
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
            error_log("❓ PESAN BIASA dari $chat_id: $text");
            // Cek apakah sedang dalam mode chat
            if (isset($db['chats'][$chat_id]['mode']) && $db['chats'][$chat_id]['mode'] == 'chat') {
                error_log("💬 CHAT MODE dari $chat_id");
                handleChat($chat_id, $text, $nama, $db);
            } else {
                // Kalo gak dalam mode chat, kasih tahu cara mulai chat
                kirimPesan($chat_id, "❓ Perintah tidak dikenal. Ketik /chat untuk mulai ngobrol dengan Asist Master.");
            }
            break;
    }
}

// ========== CALLBACK QUERY (HANYA UNTUK ADMIN) ==========
function handleCallbackQuery($callback, &$db) {
    $data = $callback['data'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $from_id = $callback['from']['id'];
    $message = $callback['message'];
    
    error_log("🟢 CALLBACK: $data dari user $from_id");
    
    // FILTER: Hanya admin yang bisa konfirmasi/tolak
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
        answerCallbackQuery($callback['id'], "✅ Dikonfirmasi");
    }
    elseif (strpos($data, 'reject_') === 0) {
        $user_chat_id = str_replace('reject_', '', $data);
        error_log("❌ TOLAK untuk user $user_chat_id");
        
        $new_caption = $message['caption'] . "\n\n❌ *REJECTED*";
        editMessageCaption($chat_id, $message_id, $new_caption);
        editMessageReplyMarkup($chat_id, $message_id, null);
        
        kirimPesan($user_chat_id, "❌ *Maaf, bukti transfer Anda ditolak.*\n\nPastikan Anda mentransfer Rp25.000 via QRIS dengan benar.\n\nKalo ada kendala, hubungi admin.");
        
        answerCallbackQuery($callback['id'], "❌ Ditolak");
    }
}

// ========== ASIST MASTER (GROQ) ==========
function callGroq($prompt) {
    global $GROQ_MODELS;
    $api_key = GROQ_API_KEY;
    
    foreach ($GROQ_MODELS as $model) {
        error_log("🔄 Asist Master mencoba model: $model");
        
        $url = "https://api.groq.com/openai/v1/chat/completions";
        
        $data = [
            'messages' => [
                ['role' => 'system', 'content' => 'Kamu adalah Asist Master, asisten AI yang membantu pengguna Jailbreak Store dengan ramah dan informatif.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'model' => $model,
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);  // DIPERCEPAT JADI 5 DETIK
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                error_log("✅ Asist Master sukses dengan model: $model");
                return $result['choices'][0]['message']['content'];
            }
        } else {
            error_log("⚠️ Asist Master model $model gagal: HTTP $http_code - $curl_error");
        }
    }
    
    error_log("❌ Semua model Asist Master gagal");
    return "⚠️ Maaf, Asist Master sedang sibuk. Silakan coba lagi nanti.";
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

// ========== FUNGSI KIRIM PASSWORD ==========
function kirimPassword($chat_id, &$db) {
    $password = PDF_PASSWORD;
    
    $caption = "📄 *SCRIPT MASTER PDF*\n\n"
             . "🔑 *Password:* `$password`\n\n"
             . "💬 Chat dengan Asist Master: /chat\n\n"
             . "📌 Convert PDF ke TXT: " . CLOUDCONVERT_LINK;
    
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
    
    kirimPesan(ADMIN_ID, "✅ Password ke $chat_id: $password");
}

// ========== FUNGSI HANDLE BUKTI TRANSFER ==========
function handlePaymentProof($chat_id, $msg, $username, $nama, &$db) {
    // Cek apakah user ini ada di pending
    $is_pending = false;
    foreach ($db['pending'] as $p) {
        if ($p['chat_id'] == $chat_id) {
            $is_pending = true;
            break;
        }
    }
    
    if (!$is_pending) {
        kirimPesan($chat_id, "⚠️ Anda belum order. Ketik /beli dulu.");
        return;
    }
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

// ========== FUNGSI SAPA USER ==========
function sapaUser($chat_id, $nama, $username) {
    $text = "Halo *$nama*! 😂\n"
          . "Selamat datang di *JAILBREAK STORE*\n\n"
          . "📌 *Fitur:*\n"
          . "• /katalog - Lihat produk\n"
          . "• /beli - Beli produk\n"
          . "• /chat - Ngobrol dengan Asist Master\n"
          . "• /lupapassword - Kirim ulang password\n\n"
          . "Username: $username\n"
          . "Chat ID: `$chat_id`\n\n"
          . "💰 *Cara Bayar:*\n"
          . "Transfer ke Saweria:\n" . SAWERIA_LINK;
    
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI KATALOG ==========
function tampilKatalog($chat_id) {
    kirimFileId($chat_id, PREVIEW_FILE_ID, "📄 *PREVIEW PDF (2 Halaman)*\n\nIni contoh isinya.");
    
    $text = "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "📌 *SCRIPT MASTER PACK*\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "Harga: Rp 25.000\n"
          . "File: `ScriptMaster.pdf` (terenkripsi)\n"
          . "Isi: Unlock Deepseek, Gemini, Kimi AI tanpa batasan\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "🎁 *BONUS PER PEMBELIAN:*\n"
          . "• Chat dengan Asist Master (20x chat)\n"
          . "• Update metode jailbreak terbaru\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "Cara beli:\n"
          . "1. Klik /beli\n"
          . "2. Scan QRIS yang dikirim bot\n"
          . "3. Transfer Rp25.000\n"
          . "4. Kirim bukti transfer\n\n"
          . "Langsung klik:\n"
          . "/beli";
    
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI PROSES BELI ==========
function prosesBeli($chat_id, $username, $nama, &$db) {
    // Kirim QRIS dulu
    kirimGambarId($chat_id, QRIS_FILE_ID, "📱 *SCAN QRIS INI*\n\nScan QR code di atas untuk transfer Rp25.000 ke Saweria.\n\nAtau klik link: " . SAWERIA_LINK);
    
    $db['pending'][] = [
        'chat_id' => $chat_id,
        'username' => $username,
        'nama' => $nama,
        'waktu' => time()
    ];
    saveDB($db);
    
    $text = "✅ *Pesanan diterima!*\n\n"
          . "Silakan transfer Rp25.000 via QRIS di atas.\n\n"
          . "**SETELAH TRANSFER**, kirim BUKTI TRANSFER (screenshot) KE BOT INI.\n\n"
          . "Admin akan verifikasi dan kirimkan password.\n\n"
          . "Bonus: 20x chat dengan Asist Master!";
    
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
        kirimPesan($chat_id, "🔑 *Password terakhir:* `$latest`\n\nFile: ScriptMaster.pdf\nConvert: " . CLOUDCONVERT_LINK);
        kirimPesan(ADMIN_ID, "🔔 User $chat_id minta password: $latest");
    } else {
        kirimPesan($chat_id, "❌ Belum pernah beli. Ketik /beli dulu.");
    }
}

// ========== FUNGSI CHAT DENGAN ASIST MASTER ==========
function cekChatAccess($chat_id, $nama, &$db) {
    if (!isset($db['chats'][$chat_id])) {
        kirimPesan($chat_id, "❌ Belum punya akses chat. Beli dulu: /beli");
        return;
    }
    
    if ($db['chats'][$chat_id]['remaining'] <= 0) {
        kirimPesan($chat_id, "⚠️ Bonus chat habis. Beli lagi: /beli");
        return;
    }
    
    $db['chats'][$chat_id]['mode'] = 'chat';
    saveDB($db);
    
    kirimPesan($chat_id, "🤖 *Asist Master siap membantu!*\n\nSisa chat: {$db['chats'][$chat_id]['remaining']}\nSilakan ketik pertanyaan Anda.\n\nKetik /stop untuk keluar dari mode chat.");
}

function handleChat($chat_id, $prompt, $nama, &$db) {
    if ($prompt == '/stop') {
        $db['chats'][$chat_id]['mode'] = 'idle';
        saveDB($db);
        kirimPesan($chat_id, "👋 Mode chat dimatikan. Ketik /chat untuk mulai lagi.");
        return;
    }
    
    if ($db['chats'][$chat_id]['remaining'] <= 0) {
        $db['chats'][$chat_id]['mode'] = 'idle';
        saveDB($db);
        kirimPesan($chat_id, "⚠️ Bonus chat habis. Beli lagi: /beli");
        return;
    }
    
    $db['chats'][$chat_id]['remaining']--;
    $remaining = $db['chats'][$chat_id]['remaining'];
    
    $reply = callGroq($prompt);
    
    $db['chats'][$chat_id]['history'][] = ['user' => $prompt, 'bot' => $reply];
    saveDB($db);
    
    kirimPesan($chat_id, "*Asist Master:* $reply\n\nSisa chat: $remaining\nKetik /stop untuk keluar.");
}

function cekLimit($chat_id, &$db) {
    if (!isset($db['chats'][$chat_id])) {
        kirimPesan($chat_id, "Belum punya akses chat. Beli dulu: /beli");
        return;
    }
    
    $total = count(array_filter($db['transactions'], fn($t) => $t['chat_id'] == $chat_id));
    $used = 20 * $total - $db['chats'][$chat_id]['remaining'];
    
    kirimPesan($chat_id, "📊 *Status Chat Asist Master:*\n"
                       . "Total pembelian: $total kali\n"
                       . "Total bonus: " . (20 * $total) . " chat\n"
                       . "Sudah dipakai: $used\n"
                       . "Sisa chat: {$db['chats'][$chat_id]['remaining']}");
}

// ========== FUNGSI KIRIM PESAN DASAR ==========
function kirimPesan($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $post = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
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

function kirimFileId($chat_id, $file_id, $caption = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $post = [
        'chat_id' => $chat_id,
        'document' => $file_id,
        'caption' => $caption,
        'parse_mode' => 'Markdown'
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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

