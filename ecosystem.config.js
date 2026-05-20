// PM2 ecosystem config — MoneyPath production server
// Usage: pm2 start ecosystem.config.js && pm2 save
module.exports = {
  apps: [{
    name:   'moneypath',
    script: 'server.js',
    env: {
      PORT:           3000,
      ALLOWED_ORIGIN: 'https://littleexplorers.pl'
    },
    // Auto-restart on crash, max memory guard
    max_memory_restart: '300M',
    restart_delay:      2000,
  }]
};
