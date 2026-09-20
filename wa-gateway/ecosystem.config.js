// PM2 config untuk WhatsApp Gateway PAMSIMAS
// Jalankan dari folder wa-gateway:  pm2 start ecosystem.config.js
// Log: pm2 logs pamsimas-wa
module.exports = {
  apps: [
    {
      name: 'pamsimas-wa',
      script: 'gateway.js',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 20,
      restart_delay: 5000,
      watch: false,
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'production',
        // Webhook backend Laravel (gateway -> Laravel)
        WA_WEBHOOK_URL: 'http://127.0.0.1/api/api_wa',
        // Nomor gateway (opsional, dipakai sebagai fallback)
        WA_GATEWAY_NUMBER: '6285157275866'
      },
      out_file: './logs/pm2-out.log',
      error_file: './logs/pm2-error.log',
      merge_logs: true,
      time: true
    }
  ]
};
