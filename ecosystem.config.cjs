// pm2 process definitions for the Valyd Verify backend background jobs.
// (Alternative to the supervisor configs in deploy/ — use one or the other,
// not both.) Runs as the invoking user; storage/logs is group-writable by
// www-data so php-fpm and these workers can share the queue/cache tables.
//   pm2 start ecosystem.config.cjs
//   pm2 save
const BACKEND = "/var/www/pollus_main_servers/verify.pollus.tech/backend";

module.exports = {
  apps: [
    {
      name: "verify-worker",
      cwd: BACKEND,
      script: "artisan",
      interpreter: "php",
      args: "queue:work database --queue=default --sleep=3 --tries=10 --max-time=3600 --timeout=120 --memory=256",
      instances: 2,
      autorestart: true,
      max_memory_restart: "300M",
    },
    {
      name: "verify-scheduler",
      cwd: BACKEND,
      script: "artisan",
      interpreter: "php",
      args: "schedule:work",
      autorestart: true,
      max_memory_restart: "200M",
    },
  ],
};
