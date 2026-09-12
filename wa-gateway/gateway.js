const { makeWASocket, useMultiFileAuthState, downloadMediaMessage, DisconnectReason } = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');
const axios = require('axios');
const FormData = require('form-data');
const pino = require('pino');
const { Boom } = require('@hapi/boom');
const http = require('http');

// Konfigurasi dari environment variable
const WEBHOOK_URL = process.env.WEBHOOK_URL || process.env.WA_WEBHOOK_URL || 'http://127.0.0.1:8000/api/api_wa';
const NOMOR_GATEWAY_STB = process.env.NOMOR_GATEWAY_STB || process.env.WA_GATEWAY_NUMBER || "6285157275866";

let sock;

// =================================================================
// MAIN SERVER: PORT 3000 (MENERIMA INSTRUKSI OUTBOX DARI PHP)
// =================================================================
const server = http.createServer(async (req, res) => {
    if (req.method === 'POST') {
        let body = '';
        req.on('data', chunk => { body += chunk.toString(); });
        req.on('end', async () => {
            try {
                const data = JSON.parse(body);
                const { phone, message } = data;

                if (!phone || !message) {
                    res.writeHead(400); return res.end('Missing phone or message');
                }

                // FIX LID MODE: Deteksi apakah tujuan menggunakan JID @lid atau @s.whatsapp.net bawaan
                let jid = phone.includes('@') ? phone : `${phone}@s.whatsapp.net`;
                if (phone.endsWith('@lid')) {
                    jid = phone;
                }

                if (sock) {
                    try {
                        await sock.sendMessage(jid, { text: message });
                        console.log(`\x1b[32m%s\x1b[0m`, `[Outbox] Sukses kirim ke JID: ${jid}`);
                        res.writeHead(200, { 'Content-Type': 'application/json' });
                        res.end(JSON.stringify({ status: 'sent' }));
                    } catch (sendErr) {
                        console.error(`[Outbox Error] Gagal kirim ke ${jid}:`, sendErr.message);
                        res.writeHead(500);
                        res.end(JSON.stringify({ status: 'error', message: sendErr.message }));
                    }
                } else {
                    res.writeHead(503); res.end('WhatsApp Socket Not Ready');
                }
            } catch (err) {
                res.writeHead(500); res.end(err.message);
            }
        });
    } else {
        res.writeHead(404); res.end();
    }
});

// =================================================================
// WA ENGINE & INBOX HANDLER
// =================================================================
async function startWA() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_pamsimas');
    sock = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'info' })
    });

    sock.ev.on('creds.update', saveCreds);
    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;
        if (qr) {
            console.log('=== SCAN QR PAMSIMAS ===');
            qrcode.generate(qr, { small: true });
        }
        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect.error instanceof Boom)?.output?.statusCode !== DisconnectReason.loggedOut;
            if (shouldReconnect) setTimeout(startWA, 5000);
        } else if (connection === 'open') {
            if (!server.listening) server.listen(3000, '0.0.0.0');
            console.log(`\x1b[42m%s\x1b[0m`, ` GATEWAY PAMSIMAS AKTIF `);
        }
    });

    sock.ev.on('messages.upsert', async ({ messages }) => {
        const msg = messages[0];

        // PENGAMAN UTAMA: Jangan membaca broadcast status, grup, atau pesan dari bot itu sendiri (Anti-Loop)
        if (!msg.message || msg.key.fromMe || msg.key.remoteJid === 'status@broadcast' || msg.key.remoteJid.includes('@g.us')) return;

        const from = msg.key.remoteJid; // JID utuh warga (bisa @s.whatsapp.net atau @lid)
        const isPhoto = msg.message.imageMessage;
        const text = msg.message.conversation || msg.message.extendedTextMessage?.text;

        try {
            // JALUR TEXT (Aktivasi / Verifikasi manual)
            if (text && !isPhoto) {
                console.log(`\x1b[36m%s\x1b[0m`, `[Inbox Text] Pesan masuk dari JID: ${from} -> Isi: ${text}`);

                const form = new FormData();
                form.append('phone', from); // Kirim JID utuh ke backend PHP
                form.append('type', 'text');
                form.append('text', text);

                // Diteruskan ke PHP dengan timeout 45 detik agar proses antrean aman
                await axios.post(WEBHOOK_URL, form, {
                    headers: form.getHeaders(),
                    timeout: 45000
                });
            }

            // JALUR FOTO METERAN
            if (isPhoto) {
                console.log(`\x1b[35m%s\x1b[0m`, `\n📸 [Inbox Photo] File masuk dari JID: ${from}`);

                const buffer = await downloadMediaMessage(msg, 'buffer', {});
                const form = new FormData();
                form.append('phone', from);
                form.append('type', 'photo');
                form.append('photo', buffer, { filename: 'meter.jpg', contentType: 'image/jpeg' });

                // Diteruskan ke PHP untuk OCR & Gemini API. Diberi timeout 45 detik agar sistem sabar menunggu proses selesai.
                await axios.post(WEBHOOK_URL, form, {
                    headers: form.getHeaders(),
                    timeout: 45000
                });
            }
        } catch (e) {
            const errorDetail = e.response ? JSON.stringify(e.response.data) : e.message;
            console.error(`[Error Handler Inbox]: Request failed. Details: ${errorDetail}`);
        }
    });
}

startWA();