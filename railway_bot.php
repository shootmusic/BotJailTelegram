<?php
// ====================================================
// JAILBREAK BOT - RAILWAY EDITION (FIX PASSWORD SINKRON)
// Repo: https://github.com/shootmusic/BotJailTelegram
// ====================================================

// ========== KONFIGURASI ==========
define('BOT_TOKEN', '8045718722:AAGfUipGjliHIqB0zJ9Y7y0JUCyQ8eYGyps');
define('ADMIN_ID', '7710155531');
define('GEMINI_API_KEY', 'AIzaSyB2ywseug3IrYfF0qN0jg5MArCgt7wE09k');
define('SAWERIA_LINK', 'https://saweria.co/Kikomaukiko');

// GOOGLE DRIVE FILE ID (dari link lu)
define('MASTER_PDF_ID', '1dK2tqUMK5WMGNPevoWipadd3y1c_XWn1');
define('PREVIEW_FILE_ID', 'BQACAgUAAxkBAANQaZ8AAcdi8rwd5JLrKVvV1x-h_vVrAAKXGwACR4b5VLZWFuSlBdUIOgQ');

// ========== DATABASE (JSON) ==========
define('DB_FILE', 'database.json');
$db = file_exists(DB_FILE) ? json_decode(file_get_contents(DB_FILE), true) : [
    'transactions' => [],
    'pending' => [],
    'chats' => []
];

function saveDB($db) {
    file_put_contents(DB_FILE, json_encode($db, JSON_PRETTY_PRINT));
}

// ========== WEBHOOK HANDLER ==========
$update = json_decode(file_get_contents('php://input'), true);
if ($update) {
    processUpdate($update);
} else {
    echo "🔥 Mr.X Jailbreak Bot - Railway Edition\n";
    echo "✅ Password SINKRON mode active\n";
    echo "⏳ " . date('Y-m-d H:i:s');
}

// ========== FUNGSI UTAMA ==========
function processUpdate($update) {
    global $db;
    
    // Callback query (tombol)
    if (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
        return;
    }
    
    if (!isset($update['message'])) return;
    
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $text = $msg['text'] ?? '';
    $username = $msg['from']['username'] ?? 'user_' . $chat_id;
    $nama = $msg['from']['first_name'] ?? 'Sob';
    
    // Handle foto / dokumen (bukti transfer)
    if (isset($msg['photo']) || isset($msg['document'])) {
        handlePaymentProof($chat_id, $msg, $username, $nama);
        return;
    }
    
    // Command handler
    switch ($text) {
        case '/start':
            sapaUser($chat_id, $nama, $username);
            break;
        case '/katalog':
            tampilKatalog($chat_id);
            break;
        case '/beli':
            prosesBeli($chat_id, $username, $nama);
            break;
        case '/chat':
            cekChatAccess($chat_id, $nama);
            break;
        case '/limit':
            cekLimit($chat_id);
            break;
        case '/lupapassword':
            kirimUlangPassword($chat_id);
            break;
        default:
            if (isset($db['chats'][$chat_id]['mode']) && $db['chats'][$chat_id]['mode'] == 'chat') {
                handleChat($chat_id, $text, $nama);
            }
            break;
    }
}

// ========== CALLBACK QUERY (TOMBOL KONFIRMASI) ==========
function handleCallbackQuery($callback) {
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
        
        // KIRIM PDF DENGAN PASSWORD BARU (SINKRON)
        kirimPDFdenganPassword($user_chat_id);
        
        // Update pesan admin
        $new_caption = $callback['message']['caption'] . "\n\n✅ *SUDAH DIKONFIRMASI*";
        editMessageCaption($chat_id, $message_id, $new_caption);
        
        answerCallbackQuery($callback['id'], "✅ PDF udah dikirim dengan password baru!");
    }
}

// ========== FUNGSI KIRIM PDF DENGAN PASSWORD (SINKRON 100%) ==========
function kirimPDFdenganPassword($chat_id) {
    global $db;
    
    // 1. Generate password random
    $part1 = strtoupper(substr(md5(uniqid() . $chat_id . rand()), 0, 4));
    $part2 = strtoupper(substr(md5(uniqid() . time() . rand()), 0, 4));
    $part3 = rand(1000, 9999);
    $password = $part1 . '-' . $part2 . '-' . $part3;
    
    // 2. Download file dari Google Drive pake gdown
    $master_file = 'master_' . $chat_id . '_' . time() . '.pdf';
    $download_cmd = "gdown https://drive.google.com/uc?id=" . MASTER_PDF_ID . " -O " . $master_file . " 2>&1";
    exec($download_cmd, $dl_output, $dl_return);
    
    if ($dl_return !== 0) {
        kirimPesan(ADMIN_ID, "❌ Gagal download dari Drive: " . implode("\n", $dl_output));
        kirimPesan($chat_id, "⚠️ Maaf, sistem error. Admin akan hubungi.");
        return;
    }
    
    // 3. Enkrip file dengan password yang baru
    $encrypted_file = 'enc_' . $chat_id . '_' . time() . '.pdf';
    $encrypt_cmd = "qpdf --encrypt user-password={$password} owner-password={$password} 256 -- "
                 . escapeshellarg($master_file) . " "
                 . escapeshellarg($encrypted_file) . " 2>&1";
    
    exec($encrypt_cmd, $enc_output, $enc_return);
    
    if ($enc_return !== 0) {
        kirimPesan(ADMIN_ID, "❌ Gagal encrypt PDF: " . implode("\n", $enc_output));
        kirimPesan($chat_id, "⚠️ Maaf, sistem error. Admin akan hubungi.");
        unlink($master_file);
        return;
    }
    
    // 4. Kirim file ke user
    $caption = "📄 *FULL PDF JAILBREAK*\n\n"
             . "🔑 *Password:* `$password`\n\n"
             . "⚠️ Password ini SINKRON 100% dengan file PDF.\n"
             . "Gunakan password di atas untuk membuka file.\n\n"
             . "🎁 Bonus chat: /chat (sisa " . (isset($db['chats'][$chat_id]['remaining']) ? $db['chats'][$chat_id]['remaining'] + 20 : 20) . ")";
    
    kirimFile($chat_id, $encrypted_file, $caption);
    
    // 5. Simpan ke database
    $db['transactions'][] = [
        'chat_id' => $chat_id,
        'password' => $password,
        'file' => $encrypted_file,
        'waktu' => time(),
        'bonus_chat' => 20
    ];
    
    // Update bonus chat
    if (!isset($db['chats'][$chat_id])) {
        $db['chats'][$chat_id] = [
            'history' => [],
            'remaining' => 20,
            'mode' => 'idle'
        ];
    } else {
        $db['chats'][$chat_id]['remaining'] += 20;
    }
    
    // Hapus dari pending
    $new_pending = [];
    foreach ($db['pending'] as $p) {
        if ($p['chat_id'] != $chat_id) {
            $new_pending[] = $p;
        }
    }
    $db['pending'] = $new_pending;
    
    saveDB($db);
    
    // Hapus file sementara
    unlink($master_file);
    unlink($encrypted_file);
    
    // Notifikasi admin
    kirimPesan(ADMIN_ID, "✅ PDF udah dikirim ke user `$chat_id`\nPassword: `$password`");
}

// ========== FUNGSI HANDLE BUKTI TRANSFER ==========
function handlePaymentProof($chat_id, $msg, $username, $nama) {
    global $db;
    
    $caption = "🔔 *BUKTI TRANSFER DARI USER*\n\n"
             . "Nama: $nama\n"
             . "Username: @$username\n"
             . "Chat ID: `$chat_id`\n"
             . "Waktu: " . date('d/m/Y H:i:s');
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ KONFIRMASI', 'callback_data' => 'confirm_' . $chat_id]
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
    
    kirimPesan($chat_id, "✅ *Bukti transfer diterima!*\n\nAdmin akan konfirmasi segera.");
}

// ========== FUNGSI SAPA USER ==========
function sapaUser($chat_id, $nama, $username) {
    $text = "Halo *$nama*! 😂😂\n"
          . "Selamat datang di *JAILBREAK SCRIPT MARKET*\n\n"
          . "Gue jual script² jailbreak premium buat unlock AI tanpa batasan.\n\n"
          . "📌 *FITUR:*\n"
          . "• /katalog - Lihat produk\n"
          . "• /beli - Beli produk (bisa berkali-kali!)\n"
          . "• /chat - Chat dengan Gemini (20x per pembelian)\n"
          . "• /lupapassword - Kirim ulang password terakhir\n\n"
          . "Username lu: $username\n"
          . "Chat ID: `$chat_id`\n\n"
          . "💰 *Cara Bayar:*\n"
          . "Transfer ke Saweria: " . SAWERIA_LINK . "\n"
          . "Kirim bukti transfer ke bot ini ya!";
    
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI KATALOG ==========
function tampilKatalog($chat_id) {
    kirimFileId($chat_id, PREVIEW_FILE_ID, "📄 *PREVIEW PDF (2 Halaman Pertama)*\n\nIni contoh isinya.");
    
    $text = "━━━━━━━━━━━━━━━━\n"
          . "📌 *AI JAILBREAK MEGA PACK*\n"
          . "Harga: Rp 25.000\n"
          . "Isi: Full script & metode unlock Deepseek, Gemini, Kimi AI tanpa batasan\n\n"
          . "━━━━━━━━━━━━━━━━\n"
          . "🎁 *BONUS PER PEMBELIAN:*\n"
          . "• Akses *Gemini 2.5 Pro* via bot ini (20x chat)\n"
          . "• Update metode jailbreak terbaru\n\n"
          . "━━━━━━━━━━━━━━━━\n"
          . "Cara beli:\n"
          . "1. Transfer Rp 25.000 ke Saweria:\n"
          . "   " . SAWERIA_LINK . "\n"
          . "2. Klik /beli\n"
          . "3. Kirim BUKTI TRANSFER (screenshot) ke bot ini\n"
          . "4. Admin verifikasi, dapet PDF + password\n\n"
          . "💡 *Bisa beli berkali-kali! Password berbeda tiap pembelian*\n\n"
          . "Langsung klik:\n"
          . "/beli";
    
    kirimPesan($chat_id, $text);
}

// ========== FUNGSI PROSES BELI ==========
function prosesBeli($chat_id, $username, $nama) {
    global $db;
    
    $db['pending'][] = [
        'chat_id' => $chat_id,
        'username' => $username,
        'nama' => $nama,
        'waktu' => time()
    ];
    saveDB($db);
    
    $text = "✅ *Pesanan diterima!*\n\n"
          . "Produk: AI Jailbreak Mega Pack\n"
          . "Harga: Rp 25.000\n\n"
          . "Silakan transfer ke:\n"
          . SAWERIA_LINK . "\n\n"
          . "**SETELAH TRANSFER**, kirim BUKTI TRANSFER (screenshot) KE BOT INI.\n\n"
          . "Admin bakal verifikasi dan kirimkan FILE PDF dengan PASSWORD UNIK.\n\n"
          . "💡 *Password akan SINKRON 100% dengan file yang dikirim!*";
    
    kirimPesan($chat_id, $text);
    
    $notif = "🔔 *ORDER BARU*\n"
           . "User: @$username ($nama)\n"
           . "Chat ID: `$chat_id`\n"
           . "Waktu: " . date('d/m H:i') . "\n\n"
           . "Tunggu user kirim bukti transfer.";
    
    kirimPesan(ADMIN_ID, $notif);
}

// ========== FUNGSI LUPA PASSWORD ==========
function kirimUlangPassword($chat_id) {
    global $db;
    
    $found = false;
    $latest_password = '';
    $latest_time = 0;
    
    foreach ($db['transactions'] as $trans) {
        if ($trans['chat_id'] == $chat_id && $trans['waktu'] > $latest_time) {
            $latest_time = $trans['waktu'];
            $latest_password = $trans['password'];
            $found = true;
        }
    }
    
    if ($found) {
        kirimPesan($chat_id, "🔑 *Password terakhir lo:* `$latest_password`\n\n"
                           . "Coba buka PDF pake password itu.\n\n"
                           . "Kalo file PDF-nya ilang, lo harus beli lagi ya.");
        kirimPesan(ADMIN_ID, "🔔 User `$chat_id` minta password lagi: `$latest_password`");
    } else {
        kirimPesan($chat_id, "❌ Lo belum pernah beli. Ketik /beli dulu.");
    }
}

// ========== FUNGSI GEMINI ==========
function cekChatAccess($chat_id, $nama) {
    global $db;
    if (!isset($db['chats'][$chat_id])) {
        kirimPesan($chat_id, "❌ Lo belum punya akses chat. Beli dulu: /beli");
        return;
    }
    $remaining = $db['chats'][$chat_id]['remaining'];
    if ($remaining <= 0) {
        kirimPesan($chat_id, "⚠️ Bonus chat lo udah habis. Beli lagi: /beli");
        return;
    }
    $db['chats'][$chat_id]['mode'] = 'chat';
    saveDB($db);
    kirimPesan($chat_id, "🤖 Mode chat aktif! Sisa: $remaining\nKetik /stop buat keluar.");
}

function handleChat($chat_id, $prompt, $nama) {
    global $db;
    if ($prompt == '/stop') {
        $db['chats'][$chat_id]['mode'] = 'idle';
        saveDB($db);
        kirimPesan($chat_id, "👋 Mode chat dimatikan.");
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
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != 200) return "⚠️ Error: Gak bisa connect ke Gemini.";
    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? "⚠️ Maaf, Gemini gak bisa jawab.";
}

function cekLimit($chat_id) {
    global $db;
    if (!isset($db['chats'][$chat_id])) {
        kirimPesan($chat_id, "Lo belum punya akses chat. Beli dulu: /beli");
        return;
    }
    $remaining = $db['chats'][$chat_id]['remaining'];
    $total = 0;
    foreach ($db['transactions'] as $t) {
        if ($t['chat_id'] == $chat_id) $total++;
    }
    kirimPesan($chat_id, "📊 *Status Chat:*\nTotal beli: $total kali\nSisa chat: $remaining\nTotal bonus: " . ($total * 20));
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
    curl_exec($ch);
}

function kirimFileId($chat_id, $file_id, $caption = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $post = ['chat_id' => $chat_id, 'document' => $file_id, 'caption' => $caption, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
}

function kirimFile($chat_id, $file_path, $caption = '') {
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
    curl_exec($ch);
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
    curl_exec($ch);
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
    curl_exec($ch);
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
    curl_exec($ch);
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
    curl_exec($ch);
}

