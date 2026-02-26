<?php
// ====================================================
// JAILBREAK BOT - RAILWAY EDITION (FINAL VERSION)
// PASSWORD: GQ3A-J6G8-5235
// FILE: ScriptMaster.pdf
// GEMINI FIXED
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
define('PREVIEW_FILE_ID', 'BQACAgUAAxkBAANQaZ8AAcdi8rwd5JLrKVvV1x-h_vVrAAKXGwACR4b5VLZWFuSlBdUIOgQ');

// ========== FILE PDF UTAMA (ScriptMaster.pdf) ==========
define('FULL_PDF_FILE_ID', 'BQACAgUAAxkDAAIBQWmgFiL1zVp9BTcqBq1o4GHYYSUmAALEHAAC6xYAAVXigx0pjSHNNToE');
define('PDF_PASSWORD', 'GQ3A-J6G8-5235');

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
echo "🚀 JAILBREAK BOT - FINAL VERSION\n";
echo "==========================\n";
echo "✅ Status: RUNNING\n";
echo "✅ PHP Version: " . phpversion() . "\n";
echo "✅ Bot Token: " . substr(BOT_TOKEN, 0, 15) . "...\n";
echo "✅ Admin ID: " . ADMIN_ID . "\n";
echo "✅ Gemini API: " . substr(GEMINI_API_KEY, 0, 10) . "...\n";
echo "✅ File: ScriptMaster.pdf\n";
echo "✅ Password: " . PDF_PASSWORD . "\n";
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
        
        // Kirim PDF dengan password statis
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

// ========== FUNGSI KIRIM PDF ==========
function kirimPDFdenganPassword($chat_id, &$db) {
    $password = PDF_PASSWORD;
    
    // Kirim file PDF ScriptMaster.pdf
    kirimFileId($chat_id, FULL_PDF_FILE_ID, "📄 *SCRIPT MASTER PDF*\n\n🔑 *Password:* `$password`\n\n⚠️ Password ini untuk semua pembeli.\n\n🎁 Bonus chat: /chat");
    
    // Simpan transaksi
    $db['transactions'][] = [
        'chat_id' => $chat_id,
        'password' => $password,
        'waktu' => time(),
        'bonus_chat' => 20
    ];
    
    // Update bonus chat
    if (!isset($db['chats'][$chat_id])) {
        $db['chats'][$chat_id] = ['remaining' => 20, 'mode' => 'idle', 'history' => []];
    } else {
        $db['chats'][$chat_id]['remaining'] += 20;
    }
    
    // Hapus dari pending
    $db['pending'] = array_filter($db['pending'], fn($p) => $p['chat_id'] != $chat_id);
    
    saveDB($db);
    
    kirimPesan(ADMIN_ID, "✅ ScriptMaster.pdf terkirim ke `$chat_id`\nPassword: `$password`");
    
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
    $text = "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "📌 *SCRIPT MASTER PACK*\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "Harga: Rp 25.000\n"
          . "File: `ScriptMaster.pdf` (terenkripsi)\n"
          . "Password: *GQ3A-J6G8-5235*\n"
          . "Isi: Unlock Deepseek, Gemini, Kimi AI tanpa batasan\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "🎁 *BONUS PER PEMBELIAN:*\n"
          . "• Akses *Gemini 2.5 Pro* via bot ini (20x chat)\n"
          . "• Update metode jailbreak terbaru\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "Cara beli:\n"
          . "1. Transfer Rp 25.000 ke Saweria:\n"
          . "   " . SAWERIA_LINK . "\n"
          . "2. Klik /beli\n"
          . "3. Kirim BUKTI TRANSFER (screenshot) ke bot ini\n\n"
          . "💡 *Password akan diberikan setelah konfirmasi admin*\n\n"
          . "Langsung klik:\n"
          . "/beli";
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI PROSES BELI ==========
function prosesBeli($chat_id, $username, $nama, &$db) {
    $db['pending'][] = ['chat_id' => $chat_id, 'username' => $username, 'nama' => $nama, 'waktu' => time()];
    saveDB($db);
    
    $text = "✅ *Pesanan diterima!*\n\n"
          . "Produk: Script Master Pack\n"
          . "Harga: Rp 25.000\n\n"
          . "Silakan transfer ke:\n"
          . SAWERIA_LINK . "\n\n"
          . "**SETELAH TRANSFER**, kirim BUKTI TRANSFER (screenshot) KE BOT INI.\n\n"
          . "Admin bakal verifikasi dan kirimkan password untuk membuka `ScriptMaster.pdf`.\n\n"
          . "Bonus: Nanti dapet 20x chat Gemini gratis!";
    
    kirimPesan($chat_id, $text);
    kirimPesan(ADMIN_ID, "🔔 Order baru dari @$username\nChat ID: `$chat_id`\nProduk: Script Master Pack");
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
        kirimPesan($chat_id, "🔑 Password terakhir: `$latest`\n\nFile: ScriptMaster.pdf");
        kirimPesan(ADMIN_ID, "🔔 User `$chat_id` minta password: `$latest`");
    } else {
        kirimPesan($chat_id, "❌ Belum pernah beli. Ketik /beli");
    }
}

// ========== FUNGSI GEMINI (FIXED) ==========
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
    // FIX: Gunakan model yang benar dan API key dari environment
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=" . GEMINI_API_KEY;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.9,
            'topK' => 1,
            'topP' => 1,
            'maxOutputTokens' => 2048,
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Tambahkan ini untuk menghindari SSL error
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code != 200) {
        // Log error untuk debugging
        error_log("Gemini API Error: HTTP $http_code - $curl_error");
        return "⚠️ Error Gemini (HTTP $http_code). Silakan coba lagi nanti.";
    }
    
    if (!$response) {
        return "⚠️ Error: Tidak ada respons dari Gemini.";
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    } else {
        error_log("Gemini API Unexpected Response: " . print_r($result, true));
        return "⚠️ Maaf, Gemini tidak bisa menjawab saat ini.";
    }
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

