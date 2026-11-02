const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    jidNormalizedUser
} = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');
const pino = require('pino');
const axios = require('axios');
const path = require('path');
const fs = require('fs');

// Alamat URL API Laravel backend
const API_BASE_URL = process.env.API_BASE_URL || (fs.existsSync('/.dockerenv') ? 'http://127.0.0.1/api/v1' : 'http://127.0.0.1:8090/api/v1');

console.log('============================================================');
console.log(' 🚀 MEMULAI BOT WHATSAPP WARUNG IBU BETI (INTEGRASI PENUH)');
console.log(` 🔗 Terhubung ke Backend API: ${API_BASE_URL}`);
console.log('============================================================\n');

async function connectToWhatsApp() {
    const authFolder = path.join(__dirname, 'auth_info_baileys');
    const { state, saveCreds } = await useMultiFileAuthState(authFolder);
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        logger: pino({ level: 'silent' }),
        printQRInTerminal: false,
        auth: state,
        browser: ['Warung Ibu Beti', 'Chrome', '1.0.0'],
    });

    sock.ev.on('creds.update', saveCreds);

    let adminPhone = '';
    let adminJid = '';
    // Simpan riwayat asal chat pesanan (apakah dari grup atau chat pribadi)
    const orderOrigins = new Map();

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('\n============================================================');
            console.log(' 📲 SCAN KODE QR BERIKUT DENGAN WHATSAPP DI HP PRIBADI ANDA:');
            console.log(' 1. Buka WhatsApp di HP Anda');
            console.log(' 2. Ketuk Titik Tiga / Menu Pengaturan -> "Perangkat Tertaut"');
            console.log(' 3. Ketuk "Tautkan Perangkat" & scan QR di bawah:');
            console.log('============================================================\n');
            qrcode.generate(qr, { small: true });
            console.log('\n(Menunggu pemindaian QR dari HP Anda...)\n');
        }

        if (connection === 'close') {
            const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log('⚠️ Koneksi WhatsApp terputus. Alasan:', lastDisconnect?.error?.message || 'Unknown');
            if (shouldReconnect) {
                console.log('🔄 Menyambungkan ulang ke WhatsApp dalam 3 detik...');
                setTimeout(connectToWhatsApp, 3000);
            } else {
                console.log('❌ Anda telah logout dari WhatsApp. Silakan jalankan ulang bot untuk login kembali.');
            }
        } else if (connection === 'open') {
            adminPhone = jidNormalizedUser(sock.user?.id || '').replace(/@.+/, '');
            adminJid = `${adminPhone}@s.whatsapp.net`;

            console.log('\n============================================================');
            console.log(' 🎉 BOT WHATSAPP WARUNG IBU BETI BERHASIL TERHUBUNG!');
            console.log(` 📱 Nomor WA Admin Toko (HP Anda): +${adminPhone}`);
            console.log(' 🟢 Status: AKTIF & SIAP MENERIMA PESANAN');
            console.log('============================================================\n');
            console.log('💡 PANDUAN PENGUJIAN:');
            console.log('1. TRANSAKSI OFFLINE (Khusus Chat Admin ke Diri Sendiri):');
            console.log('   Ketik: OFFLINE#BRS-PREM-5KG#1 (Stok toko langsung terpotong via Kasir POS)');
            console.log('2. PEMESANAN ONLINE (Pelanggan Chat ke Nomor Bot):');
            console.log('   Ketik: PESAN#Nama Pelanggan#Alamat#BRS-PREM-5KG#2 (Stok BELUM berkurang)');
            console.log('   -> Notifikasi persetujuan akan masuk ke chat pribadi Admin');
            console.log('3. KONFIRMASI ADMIN:');
            console.log('   Admin ketik: KONFIRMASI#WA-XXXXX (Stok baru resmi terpotong & chat konfirmasi dikirim ke pembeli!)\n');
        }
    });

    // Menangani pesan chat yang masuk
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            if (!msg.message || msg.key.remoteJid === 'status@broadcast') continue;

            const from = msg.key.remoteJid;
            const isGroup = from.endsWith('@g.us');

            // Pengirim pesan sesungguhnya (di grup = participant, di chat pribadi = remoteJid)
            const senderJid = isGroup ? (msg.key.participant || msg.participant || from) : from;
            const senderNumber = senderJid.replace(/@.+/, '');
            const pushName = msg.pushName || 'Pelanggan';

            // Cek apakah pesan dikirim oleh Admin (chat ke diri sendiri / fromMe / nomor bot)
            if (!adminPhone && sock.user?.id) {
                adminPhone = jidNormalizedUser(sock.user.id).replace(/@.+/, '');
                adminJid = `${adminPhone}@s.whatsapp.net`;
            }
            const isFromAdmin = msg.key.fromMe || (adminPhone && senderNumber === adminPhone);

            const text = (
                msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                msg.message.imageMessage?.caption ||
                ''
            ).trim();

            if (!text) continue;

            console.log(`📩 [PESAN MASUK] Dari: +${senderNumber} (${pushName}) ${isGroup ? '[GRUP]' : '[PRIBADI]'} [Admin: ${isFromAdmin ? 'YA' : 'BUKAN'}]: "${text}"`);

            const cleanText = text.toLowerCase();

            // ========================================================
            // 1. MENU / KATALOG / BANTUAN
            // ========================================================
            if (cleanText === 'menu' || cleanText === 'halo' || cleanText === 'hi' || cleanText === 'bantuan' || cleanText === 'help') {
                let balasan = 
`🏪 *SELAMAT DATANG DI WARUNG IBU BETI!*
Layanan Pengadaan & Pemesanan Sembako Daring.

📋 *Daftar Katalog Sembako:*
1️⃣ *Beras Pandan Wangi 5kg*
   • Kode SKU: *BRS-PREM-5KG*
   • Harga: Rp 75.000 / karung
2️⃣ *Minyak Goreng Sawit 2L*
   • Kode SKU: *MYK-GRG-2L*
   • Harga: Rp 38.000 / pouch

🛒 *Format Pemesanan Pembeli:*
*PESAN#Nama Anda#Alamat Lengkap#Kode SKU#Jumlah*

_Contoh:_
*PESAN#${pushName}#Jl. Mawar No. 14 Bandung#BRS-PREM-5KG#2*`;

                if (isFromAdmin) {
                    balasan += `\n\n⚙️ *MENU KHUSUS ADMIN TOKO:*
• Catat Transaksi Kasir Offline: *OFFLINE#BRS-PREM-5KG#1*
• Restock / Tambah Stok: *RESTOCK#BRS-PREM-5KG#50*
• Konfirmasi Kirim Pesanan: *KONFIRMASI#WA-XXXXX*
• Tolak Pesanan: *TOLAK#WA-XXXXX*
• Cek Stok Real-Time: *stok*`;
                }

                await sock.sendMessage(from, { text: balasan }, { quoted: msg });
                continue;
            }

            // ========================================================
            // 2. CEK STOK REAL-TIME DARI DATABASE MYSQL
            // ========================================================
            if (cleanText === 'stok' || cleanText === 'cek stok' || cleanText === '!stok') {
                try {
                    const res = await axios.get(`${API_BASE_URL}/barang`);
                    const barangList = res.data?.data?.data || [];
                    
                    let stokText = `📦 *INFORMASI STOK SEMBAKO TERKINI (REAL-TIME)*\n`;
                    stokText += `_Diambil langsung dari database MySQL Warung Ibu Beti:_\n\n`;

                    if (barangList.length === 0) {
                        stokText += `⚠️ Katalog sembako sedang kosong.`;
                    } else {
                        barangList.forEach((b, idx) => {
                            stokText += `${idx + 1}. *${b.nama_barang}*\n`;
                            stokText += `   • SKU: \`${b.sku}\`\n`;
                            stokText += `   • Sisa Stok: *${b.stok} ${b.satuan || 'unit'}*\n`;
                            stokText += `   • Harga: Rp ${Number(b.harga_jual).toLocaleString('id-ID')}\n\n`;
                        });
                    }
                    stokText += `Untuk memesan, ketik: *menu*`;

                    await sock.sendMessage(from, { text: stokText }, { quoted: msg });
                } catch (err) {
                    await sock.sendMessage(from, { text: '⚠️ Gagal mengambil info stok: ' + err.message }, { quoted: msg });
                }
                continue;
            }

            // ========================================================
            // 3. FITUR TRANSAKSI OFFLINE (KHUSUS ADMIN CHAT KE DIRI SENDIRI)
            // Format: OFFLINE#SKU#Qty
            // ========================================================
            if (cleanText.startsWith('offline#') || cleanText.startsWith('kasir#')) {
                if (!isFromAdmin) {
                    await sock.sendMessage(from, { text: '⚠️ Perintah ini khusus untuk Admin Kasir Toko Warung Ibu Beti.' }, { quoted: msg });
                    continue;
                }

                const parts = text.split('#').map(p => p.trim());
                if (parts.length < 3) {
                    await sock.sendMessage(from, { 
                        text: '⚠️ Format offline salah! Gunakan: *OFFLINE#Kode SKU#Jumlah*\nContoh: *OFFLINE#BRS-PREM-5KG#1*' 
                    }, { quoted: msg });
                    continue;
                }

                const sku = parts[1].toUpperCase();
                const qty = parseInt(parts[2], 10);

                if (isNaN(qty) || qty <= 0) {
                    await sock.sendMessage(from, { text: '⚠️ Jumlah harus berupa angka positif!' }, { quoted: msg });
                    continue;
                }

                try {
                    console.log(`   🛒 [OFFLINE KASIR] Memproses penjualan toko: ${sku} (${qty} unit)...`);
                    const payloadKasir = {
                        kasir_nama: 'Admin Kasir Toko',
                        metode_bayar: 'tunai',
                        uang_diterima: 10000000,
                        items: [{ sku, qty }]
                    };

                    const resKasir = await axios.post(`${API_BASE_URL}/kasir/transaksi`, payloadKasir, {
                        headers: { 'Content-Type': 'application/json' }
                    });

                    const dataKasir = resKasir.data?.data;
                    const totalBayar = dataKasir?.total_belanja ? Number(dataKasir.total_belanja).toLocaleString('id-ID') : '-';

                    // Ambil sisa stok terkini
                    let sisaStokToko = '-';
                    try {
                        const resBarang = await axios.get(`${API_BASE_URL}/barang/${sku}`);
                        sisaStokToko = resBarang.data?.data?.stok;
                    } catch (_) {}

                    const offlineSuksesMsg = 
`🏬 *TRANSAKSI KASIR OFFLINE BERHASIL DICATAT!*

📋 *Rincian Penjualan Toko Fisik:*
• No. Struk Kasir : *${dataKasir?.nomor_transaksi || 'TRX-OFFLINE'}*
• Barang Terjual   : *${sku}* (${qty} unit)
• Total Nilai      : *Rp ${totalBayar}* (Tunai)

📉 *Dampak ke Inventori:*
Stok fisik toko & katalog online otomatis berkurang *-${qty} unit* via Shared Database ACID.
• Sisa Stok Toko Sekarang: *${sisaStokToko} unit*`;

                    await sock.sendMessage(from, { text: offlineSuksesMsg }, { quoted: msg });
                    console.log(`   ✅ [OFFLINE SUKSES] Stok ${sku} berkurang ${qty}. Sisa stok: ${sisaStokToko}`);

                } catch (err) {
                    console.error('   ❌ Error transaksi kasir offline:', err.response?.data || err.message);
                    const detail = err.response?.data?.detail || err.response?.data?.message || err.message;
                    await sock.sendMessage(from, { text: `❌ *TRANSAKSI OFFLINE GAGAL:* ${detail}` }, { quoted: msg });
                }
                continue;
            }

            // ========================================================
            // 3.5 FITUR RESTOCK / TAMBAH STOK (KHUSUS ADMIN)
            // Format: RESTOCK#SKU#Qty
            // ========================================================
            if (cleanText.startsWith('restock#') || cleanText.startsWith('tambah#') || cleanText.startsWith('kulakan#')) {
                if (!isFromAdmin) {
                    await sock.sendMessage(from, { text: '⚠️ Perintah ini khusus untuk Admin Warung Ibu Beti.' }, { quoted: msg });
                    continue;
                }

                const parts = text.split('#').map(p => p.trim());
                if (parts.length < 3) {
                    await sock.sendMessage(from, { 
                        text: '⚠️ Format restock salah! Gunakan: *RESTOCK#Kode SKU#Jumlah*\nContoh: *RESTOCK#BRS-PREM-5KG#50*' 
                    }, { quoted: msg });
                    continue;
                }

                const sku = parts[1].toUpperCase();
                const qty = parseInt(parts[2], 10);

                if (isNaN(qty) || qty <= 0) {
                    await sock.sendMessage(from, { text: '⚠️ Jumlah harus berupa angka positif!' }, { quoted: msg });
                    continue;
                }

                try {
                    console.log(`   📦 [RESTOCK] Menambah stok: ${sku} (+${qty} unit)...`);
                    const resRestock = await axios.patch(`${API_BASE_URL}/barang/${sku}/restock`, { jumlah: qty });
                    const barangBaru = resRestock.data?.data;

                    const restockMsg = 
`📦 *RESTOCK SEMBAKO BERHASIL!*

• Barang: *${barangBaru?.nama_barang}* (\`${sku}\`)
• Ditambahkan: *+${qty} ${barangBaru?.satuan || 'unit'}*
• Total Stok Sekarang: *${barangBaru?.stok} ${barangBaru?.satuan || 'unit'}*

✅ Stok terbaru langsung tersinkronisasi ke katalog online dan kasir offline!`;

                    await sock.sendMessage(from, { text: restockMsg }, { quoted: msg });
                    console.log(`   ✅ [RESTOCK SUKSES] Stok ${sku} bertambah +${qty}. Total: ${barangBaru?.stok}`);
                } catch (err) {
                    console.error('   ❌ Error restock:', err.response?.data || err.message);
                    await sock.sendMessage(from, { text: '❌ Gagal restock: ' + (err.response?.data?.message || err.message) }, { quoted: msg });
                }
                continue;
            }

            // ========================================================
            // 4. KONFIRMASI PESANAN ONLINE OLEH ADMIN (SANGAT MUDAH & SINGKAT)
            // Cukup balas: "YA", "ACC", "OK", "KIRIM", atau "ACC 5"
            // ========================================================
            const isConfirmCmd = 
                cleanText === 'ya' || 
                cleanText === 'acc' || 
                cleanText === 'ok' || 
                cleanText === 'kirim' || 
                cleanText === 'setuju' ||
                cleanText === '1' ||
                cleanText.startsWith('acc ') || 
                cleanText.startsWith('ya ') || 
                cleanText.startsWith('konfirmasi ') || 
                cleanText.startsWith('konfirmasi#') || 
                cleanText.startsWith('acc#');

            if (isConfirmCmd) {
                if (!isFromAdmin) {
                    await sock.sendMessage(from, { text: '⚠️ Hanya Admin Warung Ibu Beti yang berhak mengonfirmasi pesanan.' }, { quoted: msg });
                    continue;
                }

                // Ambil target nomor pesanan (jika tidak disebut, otomatis ambil pesanan pending terbaru)
                let targetOrder = 'latest';
                if (cleanText.includes('#')) {
                    targetOrder = text.split('#')[1].trim().toUpperCase();
                } else if (cleanText.includes(' ')) {
                    targetOrder = text.split(' ')[1].trim().toUpperCase();
                }

                try {
                    console.log(`   ⚡ [KONFIRMASI ADMIN] Memproses konfirmasi pesanan: ${targetOrder}...`);
                    const resKonfirm = await axios.post(`${API_BASE_URL}/wa/pesanan/${targetOrder}/konfirmasi`);
                    const dataPesanan = resKonfirm.data?.data;
                    const nomorPesanan = dataPesanan?.nomor_pesanan_wa || targetOrder;
                    const orderId = dataPesanan?.id || '';

                    const customerPhone = dataPesanan?.nomor_telepon;
                    const customerName = dataPesanan?.nama_pelanggan || 'Pelanggan';
                    const customerAddress = dataPesanan?.alamat_antar || 'Alamat Toko';
                    const totalNilai = dataPesanan?.total_nilai ? Number(dataPesanan.total_nilai).toLocaleString('id-ID') : '-';

                    // 1. Balas ke Chat Admin
                    const adminConfirmMsg = 
`✅ *PESANAN #${orderId} (${nomorPesanan}) RESMI DIKONFIRMASI!*

• Pelanggan : *${customerName}* (+${customerPhone})
• Total COD : *Rp ${totalNilai}*
• Status    : *Dikonfirmasi & Siap Dikirim*

📉 *Stok Terpotong:*
Stok barang di gudang telah resmi dipotong di MySQL via event RabbitMQ.
Pesan pemberitahuan siap kirim otomatis telah dikirim ke WhatsApp pembeli!`;

                    await sock.sendMessage(from, { text: adminConfirmMsg }, { quoted: msg });

                    // 2. Kirim Notifikasi Pengiriman ke WhatsApp Pembeli Asli
                    if (customerPhone) {
                        const targetCustomerJid = `${customerPhone.replace(/[^0-9]/g, '')}@s.whatsapp.net`;
                        const buyerNotifyMsg = 
`🚚 *PESANAN ANDA TELAH DIKONFIRMASI & SIAP DIKIRIM!*

Halo Kak *${customerName}*,
Kabar baik! Pesanan Anda dengan nomor *${nomorPesanan}* telah disetujui oleh Warung Ibu Beti.
Paket sembako sedang disiapkan dan segera meluncur ke alamat Anda:
📍 *${customerAddress}*

💵 *Total Tagihan COD:* *Rp ${totalNilai}*
_Mohon siapkan uang pas saat kurir tiba di lokasi._

Terima kasih banyak telah berbelanja di Warung Ibu Beti! 🙏`;

                        try {
                            await sock.sendMessage(targetCustomerJid, { text: buyerNotifyMsg });
                            console.log(`   📲 Notifikasi pengiriman terkirim ke WhatsApp pelanggan: +${customerPhone}`);
                        } catch (sendErr) {
                            console.warn('   ⚠️ Gagal kirim ke nomor pelanggan:', sendErr.message);
                        }
                    }

                    // 3. Jika Pesanan Berasal dari Grup WhatsApp, Umumkan di Grup
                    const originInfo = orderOrigins.get(nomorPesanan) || orderOrigins.get(String(orderId));
                    if (originInfo && originInfo.isGroup && originInfo.from !== from) {
                        const groupAnnouncement = 
`🚚 *PESANAN #${orderId} (${nomorPesanan}) TELAH DIKONFIRMASI!*

Pesanan dari Kak @${originInfo.senderJid.replace(/@.+/, '')} telah disetujui oleh Warung Ibu Beti dan sedang dalam perjalanan ke alamat pemesan.
Terima kasih telah berbelanja! 🙏`;
                        try {
                            await sock.sendMessage(originInfo.from, { text: groupAnnouncement, mentions: [originInfo.senderJid] });
                            console.log(`   📢 Pengumuman konfirmasi dikirim ke grup WhatsApp`);
                        } catch (_) {}
                    }

                } catch (err) {
                    console.error('   ❌ Gagal konfirmasi pesanan:', err.response?.data || err.message);
                    const detail = err.response?.data?.detail || err.response?.data?.message || err.message;
                    await sock.sendMessage(from, { text: `❌ *KONFIRMASI GAGAL:* ${detail}` }, { quoted: msg });
                }
                continue;
            }

            // ========================================================
            // 5. TOLAK PESANAN ONLINE OLEH ADMIN (SANGAT MUDAH)
            // Cukup balas: "TOLAK", "BATAL", atau "TOLAK 5"
            // ========================================================
            const isRejectCmd = 
                cleanText === 'tolak' || 
                cleanText === 'batal' || 
                cleanText === 'reject' || 
                cleanText === '0' ||
                cleanText.startsWith('tolak ') || 
                cleanText.startsWith('batal ') || 
                cleanText.startsWith('tolak#') || 
                cleanText.startsWith('batal#');

            if (isRejectCmd) {
                if (!isFromAdmin) {
                    await sock.sendMessage(from, { text: '⚠️ Hanya Admin yang dapat membatalkan pesanan.' }, { quoted: msg });
                    continue;
                }

                let targetOrder = 'latest';
                if (cleanText.includes('#')) {
                    targetOrder = text.split('#')[1].trim().toUpperCase();
                } else if (cleanText.includes(' ')) {
                    targetOrder = text.split(' ')[1].trim().toUpperCase();
                }

                try {
                    const resTolak = await axios.post(`${API_BASE_URL}/wa/pesanan/${targetOrder}/tolak`);
                    const dataPesanan = resTolak.data?.data;
                    const nomorPesanan = dataPesanan?.nomor_pesanan_wa || targetOrder;
                    const customerPhone = dataPesanan?.nomor_telepon;

                    await sock.sendMessage(from, { text: `🚫 Pesanan *${nomorPesanan}* berhasil ditolak/dibatalkan.` }, { quoted: msg });

                    if (customerPhone) {
                        const targetCustomerJid = `${customerPhone.replace(/[^0-9]/g, '')}@s.whatsapp.net`;
                        await sock.sendMessage(targetCustomerJid, { 
                            text: `Mohon maaf Kak, pesanan Anda *${nomorPesanan}* belum dapat kami penuhi saat ini dan telah dibatalkan oleh Admin Warung Ibu Beti.` 
                        });
                    }
                } catch (err) {
                    await sock.sendMessage(from, { text: '❌ Gagal membatalkan: ' + (err.response?.data?.message || err.message) }, { quoted: msg });
                }
                continue;
            }

            // ========================================================
            // 6. PEMESANAN ONLINE DARI PELANGGAN
            // Format: PESAN#Nama#Alamat#SKU#Qty
            // ========================================================
            if (cleanText.startsWith('pesan#') || cleanText.startsWith('order#')) {
                const parts = text.split('#').map(p => p.trim());
                if (parts.length < 5) {
                    const errorMsg = 
`⚠️ *Format Pemesanan Kurang Lengkap!*

Gunakan format:
*PESAN#Nama#Alamat#Kode SKU#Jumlah*

_Contoh:_
*PESAN#Ibu Siti#Jl. Melati No. 5#BRS-PREM-5KG#2*`;
                    await sock.sendMessage(from, { text: errorMsg }, { quoted: msg });
                    continue;
                }

                const nama = parts[1];
                const alamat = parts[2];
                const sku = parts[3].toUpperCase();
                const qty = parseInt(parts[4], 10);

                if (isNaN(qty) || qty <= 0) {
                    await sock.sendMessage(from, { text: '⚠️ Jumlah pesanan (qty) harus berupa angka positif!' }, { quoted: msg });
                    continue;
                }

                console.log(`   ⏳ Meneruskan pesanan awal ke API (${API_BASE_URL}/wa/pesanan)...`);
                try {
                    const payload = {
                        nomor_telepon: senderNumber,
                        nama_pelanggan: nama,
                        alamat_antar: alamat,
                        metode_bayar: 'tunai',
                        items: [
                            { sku: sku, qty: qty }
                        ]
                    };

                    const response = await axios.post(`${API_BASE_URL}/wa/pesanan`, payload, {
                        headers: { 'Content-Type': 'application/json' },
                        timeout: 5000
                    });

                    const resData = response.data?.data;
                    const nomorPesanan = resData?.nomor_pesanan_wa || 'WA-' + Date.now();
                    const orderId = resData?.id || '';
                    const totalNilai = resData?.total_nilai ? Number(resData.total_nilai).toLocaleString('id-ID') : '-';

                    // 1. Kirim Respons ke Pembeli (Status: Menunggu Konfirmasi, Stok Belum Terpotong)
                    const pembeliMsg = 
`⏳ *PESANAN DITERIMA & MENUNGGU KONFIRMASI ADMIN*

📋 *Rincian Pesanan:*
• No. Antrean : *#${orderId}* (${nomorPesanan})
• Nama Pemesan: *${nama}*
• Alamat Antar: *${alamat}*
• Total Tagihan: *Rp ${totalNilai}*
• Metode Bayar : *Tunai saat terima barang (COD)*
• Status       : *Menunggu Persetujuan Admin*

⚠️ _Catatan: Stok belum berkurang. Admin kami sedang memverifikasi pesanan Anda. Kami akan mengirim chat konfirmasi saat barang siap dikirim!_ 🙏`;

                    await sock.sendMessage(from, { text: pembeliMsg, mentions: isGroup ? [senderJid] : [] }, { quoted: msg });
                    console.log(`   ✅ Pesanan #${orderId} (${nomorPesanan}) dicatat pending. Menunggu konfirmasi admin.`);

                    // Catat asal chat pesanan untuk pengiriman notifikasi grup saat dikonfirmasi
                    orderOrigins.set(nomorPesanan, { from, senderJid, pushName, isGroup });
                    if (orderId) orderOrigins.set(String(orderId), { from, senderJid, pushName, isGroup });

                    // 2. Kirim Notifikasi Tiket Persetujuan ke Chat Pribadi Admin
                    if (adminJid) {
                        const adminAlertMsg = 
`🔔 *ADA PESANAN ONLINE BARU!*

📋 *Rincian Pesanan:*
• Antrean: *#${orderId}* (${nomorPesanan})
• Pembeli: *${nama}* (+${senderNumber})
• Alamat : *${alamat}*
• Barang : *${sku}* (${qty} unit)
• Total  : *Rp ${totalNilai} (COD)*

⚠️ _Stok online BELUM terpotong sampai Anda setujui._

👉 *CARA KONFIRMASI (SANGAT MUDAH):*
Cukup balas chat ini:
Ketik: *YA* atau *ACC*
_(Untuk langsung setujui pesanan ini & potong stok)_

Atau ketik: *TOLAK* (untuk membatalkan)`;

                        await sock.sendMessage(adminJid, { text: adminAlertMsg });
                        console.log(`   🔔 Notifikasi persetujuan pesanan #${orderId} terkirim ke WhatsApp Admin (+${adminPhone})`);
                    }

                } catch (err) {
                    console.error('   ❌ Error posting pesanan:', err.response?.data || err.message);

                    if (err.response?.status === 422) {
                        const errData = err.response.data;
                        const sisaStok = errData.stok_tersedia !== undefined ? errData.stok_tersedia : 0;
                        const tolakMsg = 
`❌ *PESANAN DITOLAK: STOK TIDAK MENCUKUPI!*

Mohon maaf, stok barang yang Anda pesan tidak mencukupi atau sudah habis terjual!
• Sisa Stok Tersedia: *${sisaStok} unit*
• Alasan: _${errData.detail || 'Stok tidak mencukupi'}_

💡 _Silakan kurangi jumlah pesanan Anda atau pilih barang sembako lainnya (ketik *stok* untuk cek sisa stok)._`;
                        await sock.sendMessage(from, { text: tolakMsg }, { quoted: msg });
                    } else {
                        const detail = err.response?.data?.message || err.message;
                        const gagalMsg = 
`❌ *PESANAN GAGAL DIPROSES*

Kendala: _${detail}_
Pastikan kode SKU barang sudah benar (misal: *BRS-PREM-5KG* atau *MYK-GRG-2L*).`;
                        await sock.sendMessage(from, { text: gagalMsg }, { quoted: msg });
                    }
                }
            }
        }
    });

    return sock;
}

// Mulai koneksi
connectToWhatsApp().catch(err => {
    console.error('Fatal Error saat memulai Baileys:', err);
});
