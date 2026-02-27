<?php
// ====================================================
// JAILBREAK BOT - KALI LINUX EDITION v4
// GEMINI 2.5 SERIES - FULLY WORKING
// ====================================================

define('BOT_TOKEN', '8045718722:AAGfUipGjliHIqB0zJ9Y7y0JUCyQ8eYGyps');
define('ADMIN_ID', '7710155531');
define('GEMINI_API_KEY', 'GET_FROM_ENV'); // API KEY BARU
define('SAWERIA_LINK', 'https://saweria.co/Kikomaukiko');
define('PREVIEW_FILE_ID', 'BQACAgUAAxkBAANQaZ8AAcdi8rwd5JLrKVvV1x-h_vVrAAKXGwACR4b5VLZWFuSlBdUIOgQ');
define('FULL_PDF_FILE_ID', 'BQACAgUAAxkDAAIBQWmgFiL1zVp9BTcqBq1o4GHYYSUmAALEHAAC6xYAAVXigx0pjSHNNToE');
define('PDF_PASSWORD', 'GQ3A-J6G8-5235');
define('CLOUDCONVERT_LINK', 'https://share.google/BXdUWNT2rXBg3syi4');
define('QRIS_FILE_ID', 'AgACAgUAAxkDAAIBm2mg8iduJZA5v-PtiEjxzVailuP5AAJjDWsb6xYIVTD5YvSly8CBAQADAgADbQADOgQ');
define('DB_FILE', 'database.json');

error_log("🚀 BOT STARTED at " . date('Y-m-d H:i:s'));

// ========== MODEL GEMINI 2.5 SERIES ==========
$GEMINI_MODELS = [
    'gemini-2.5-flash',           // #1 Pilihan utama
    'gemini-flash-latest',         // #2 Latest flash
    'gemini-2.0-flash',            // #3 Legacy stabil
    'gemini-2.5-pro',              // #4 Premium (cadangan)
    'gemini-2.5-flash-lite'        // #5 Irit token
];

function loadDB() {
    if (file_exists(DB_FILE)) {
        return json_decode(file_get_contents(DB_FILE), true);
    }
    return ['transactions' => [], 'pending' => [], 'chats' => []];
}

function saveDB($db) {
    file_put_contents(DB_FILE, json_encode($db, JSON_PRETTY_PRINT));
}

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

http_response_code(200);
header('Content-Type: text/plain');
echo "🚀 JAILBREAK BOT - GEMINI 2.5 ACTIVE\n";
echo "====================================\n";
echo "✅ Status: RUNNING\n";
echo "✅ PHP Version: " . phpversion() . "\n";
echo "✅ Gemini API: GEMINI 2.5 SERIES\n";
echo "✅ File: ScriptMaster.pdf\n";
echo "✅ QRIS: TERPASANG\n";
echo "✅ Time: " . date('Y-m-d H:i:s') . "\n";
echo "====================================\n";
exit(0);

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

function handleCallbackQuery($callback, &$db) {
    $data = $callback['data'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $from_id = $callback['from']['id'];
    $message = $callback['message'];
    
    if ($from_id != ADMIN_ID) {
        answerCallbackQuery($callback['id'], "Hanya admin", true);
        return;
    }
    
    if (strpos($data, 'confirm_') === 0) {
        $user_chat_id = str_replace('confirm_', '', $data);
        kirimPassword($user_chat_id, $db);
        
        $new_caption = $message['caption'] . "\n\n✅ *CONFIRMED*";
        editMessageCaption($chat_id, $message_id, $new_caption);
        editMessageReplyMarkup($chat_id, $message_id, null);
        answerCallbackQuery($callback['id'], "✅ Dikonfirmasi");
    }
    elseif (strpos($data, 'reject_') === 0) {
        $user_chat_id = str_replace('reject_', '', $data);
        $new_caption = $message['caption'] . "\n\n❌ *REJECTED*";
        editMessageCaption($chat_id, $message_id, $new_caption);
        editMessageReplyMarkup($chat_id, $message_id, null);
        kirimPesan($user_chat_id, "❌ Bukti ditolak. Transfer Rp25.000 via QRIS.");
        answerCallbackQuery($callback['id'], "❌ Ditolak");
    }
}

// ========== GEMINI 2.5 FLASH (PASTI WORK) ==========
function callGemini($prompt) {
    global $GEMINI_MODELS;
    $api_key = GEMINI_API_KEY;
    
    foreach ($GEMINI_MODELS as $model) {
        error_log("🔄 Mencoba model: $model");
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                error_log("✅ Gemini sukses dengan model: $model");
                return $result['candidates'][0]['content']['parts'][0]['text'];
            }
        } else {
            error_log("⚠️ Model $model gagal: HTTP $http_code");
        }
    }
    
    return "⚠️ Maaf, layanan sedang sibuk. Coba lagi nanti.";
}

// ========== FUNGSI LAINNYA (SINGKAT) ==========
function kirimGambarId($chat_id, $file_id, $caption = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
    $post = ['chat_id' => $chat_id, 'photo' => $file_id, 'caption' => $caption, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function kirimPassword($chat_id, &$db) {
    $caption = "📄 *SCRIPT MASTER PDF*\n\n🔑 *Password:* `" . PDF_PASSWORD . "`\n\n🎁 Bonus chat: /chat\n\n📌 Convert: " . CLOUDCONVERT_LINK;
    kirimFileId($chat_id, FULL_PDF_FILE_ID, $caption);
    
    $db['transactions'][] = ['chat_id' => $chat_id, 'password' => PDF_PASSWORD, 'waktu' => time(), 'bonus_chat' => 20];
    if (!isset($db['chats'][$chat_id])) {
        $db['chats'][$chat_id] = ['remaining' => 20, 'mode' => 'idle', 'history' => []];
    } else {
        $db['chats'][$chat_id]['remaining'] += 20;
    }
    $db['pending'] = array_filter($db['pending'], fn($p) => $p['chat_id'] != $chat_id);
    saveDB($db);
    kirimPesan(ADMIN_ID, "✅ Password ke $chat_id: " . PDF_PASSWORD);
}

function handlePaymentProof($chat_id, $msg, $username, $nama, &$db) {
    $caption = "🔔 *BUKTI TRANSFER*\n\nNama: $nama\nUsername: @$username\nChat ID: `$chat_id`\nWaktu: " . date('d/m/Y H:i:s');
    $keyboard = ['inline_keyboard' => [[
        ['text' => '✅ KONFIRMASI', 'callback_data' => 'confirm_' . $chat_id],
        ['text' => '❌ TOLAK', 'callback_data' => 'reject_' . $chat_id]
    ]]];
    
    if (isset($msg['photo'])) {
        $file_id = end($msg['photo'])['file_id'];
        kirimFotoWithKeyboard(ADMIN_ID, $file_id, $caption, $keyboard);
    } elseif (isset($msg['document'])) {
        $file_id = $msg['document']['file_id'];
        kirimDokumenWithKeyboard(ADMIN_ID, $file_id, $caption, $keyboard);
    }
    kirimPesan($chat_id, "✅ Bukti diterima! Admin akan konfirmasi.");
}

function kirimFotoWithKeyboard($chat_id, $file_id, $caption, $keyboard) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
    $post = ['chat_id' => $chat_id, 'photo' => $file_id, 'caption' => $caption, 'parse_mode' => 'Markdown', 'reply_markup' => json_encode($keyboard)];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function kirimDokumenWithKeyboard($chat_id, $file_id, $caption, $keyboard) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $post = ['chat_id' => $chat_id, 'document' => $file_id, 'caption' => $caption, 'parse_mode' => 'Markdown', 'reply_markup' => json_encode($keyboard)];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function sapaUser($chat_id, $nama, $username) {
    kirimPesan($chat_id, "Halo *$nama*! 😂\nSelamat datang di *JAILBREAK STORE*\n\nFitur:\n• /katalog\n• /beli\n• /chat\n• /lupapassword\n\nUsername: $username\nChat ID: `$chat_id`\n\n💰 Transfer: " . SAWERIA_LINK);
}

function tampilKatalog($chat_id) {
    kirimFileId($chat_id, PREVIEW_FILE_ID, "📄 *PREVIEW PDF (2 Halaman)*");
    kirimPesan($chat_id, "━━━━━━━━━━━\n📌 *SCRIPT MASTER PACK*\nHarga: Rp 25.000\nFile: `ScriptMaster.pdf`\nIsi: Unlock Deepseek, Gemini, Kimi AI\n\n🎁 Bonus: 20x chat Gemini\n\nCara beli:\n1. /beli\n2. Scan QRIS\n3. Transfer\n4. Kirim bukti");
}

function prosesBeli($chat_id, $username, $nama, &$db) {
    kirimGambarId($chat_id, QRIS_FILE_ID, "📱 *SCAN QRIS INI*\n\nScan QR untuk transfer Rp25.000\nLink: " . SAWERIA_LINK);
    $db['pending'][] = ['chat_id' => $chat_id, 'username' => $username, 'nama' => $nama, 'waktu' => time()];
    saveDB($db);
    kirimPesan($chat_id, "✅ *Pesanan diterima!*\n\nTransfer via QRIS di atas.\n\nSETELAH TRANSFER, kirim BUKTI TRANSFER (screenshot) KE BOT INI.\n\nAdmin verifikasi dan kirim password.\nBonus: 20x chat Gemini!");
    kirimPesan(ADMIN_ID, "🔔 Order baru dari @$username\nChat ID: `$chat_id`");
}

function kirimUlangPassword($chat_id, &$db) {
    $latest = null;
    foreach (array_reverse($db['transactions']) as $t) {
        if ($t['chat_id'] == $chat_id) { $latest = $t['password']; break; }
    }
    if ($latest) {
        kirimPesan($chat_id, "🔑 *Password:* `$latest`\n\nFile: ScriptMaster.pdf\nConvert: " . CLOUDCONVERT_LINK);
        kirimPesan(ADMIN_ID, "🔔 User $chat_id minta password: $latest");
    } else {
        kirimPesan($chat_id, "❌ Belum pernah beli. /beli");
    }
}

function cekChatAccess($chat_id, $nama, &$db) {
    if (!isset($db['chats'][$chat_id])) { kirimPesan($chat_id, "❌ Beli dulu: /beli"); return; }
    if ($db['chats'][$chat_id]['remaining'] <= 0) { kirimPesan($chat_id, "⚠️ Bonus habis. Beli lagi: /beli"); return; }
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
    if (!isset($db['chats'][$chat_id])) { kirimPesan($chat_id, "Belum punya akses chat. /beli"); return; }
    $total = count(array_filter($db['transactions'], fn($t) => $t['chat_id'] == $chat_id));
    $used = 20 * $total - $db['chats'][$chat_id]['remaining'];
    kirimPesan($chat_id, "📊 *Status Chat:*\nTotal beli: $total\nTotal bonus: " . (20 * $total) . "\nSisa: {$db['chats'][$chat_id]['remaining']}");
}

function kirimPesan($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $post = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
    curl_exec($ch);
    curl_close($ch);
}

function editMessageCaption($chat_id, $message_id, $caption) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageCaption";
    $post = ['chat_id' => $chat_id, 'message_id' => $message_id, 'caption' => $caption, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function editMessageReplyMarkup($chat_id, $message_id, $reply_markup) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageReplyMarkup";
    $post = ['chat_id' => $chat_id, 'message_id' => $message_id, 'reply_markup' => $reply_markup];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function answerCallbackQuery($callback_query_id, $text, $show_alert = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    $post = ['callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => $show_alert];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

