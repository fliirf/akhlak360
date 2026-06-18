# Notification Scheduler

The AKHLAK 360 MVP includes a reminder command for pending assessment assignments:

```bash
php artisan assessment:send-reminders
```

The command checks active assessment periods, skips assignments past the deadline, and sends reminders every 3 days while preventing duplicate reminders for the same assignment on the same day.

In-app reminders are stored in the `notifications` table. Email delivery uses Laravel Mail and defaults to the log mailer for this MVP, so local reminder emails are written to Laravel logs unless `MAIL_MAILER` is changed.

To execute the configured Laravel schedule during local simulation:

```bash
php artisan schedule:work
```

The application schedule is configured to invoke `assessment:send-reminders` daily at 08:00. This configuration alone is not evidence that a scheduler process is currently running. Use recorded reminder audit activity and generated notifications to verify actual execution. Production deployment can use the same command with the company scheduler or cron strategy.
