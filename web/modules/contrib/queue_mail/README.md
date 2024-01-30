Queue mail
==========

Enable queue for mails
----------------------
Queue mail module does not put your mails to queue after enabling. 
By default this feature is disabled.

You should go to the settings page /admin/config/system/queue_mail 
and add mail IDs to the "Mail IDs to queue" field.

Use "*" to enable queue for sending all emails.

When Queue mail processes mails it marks all mails as queued or not.
So you can check status of mail in your code:

```
$message = \Drupal::service('plugin.manager.mail')->mail();
if ($message['queued']] {
  // Message has been added to the queue.
}
else {
  // Message has not been added to the queue. 
}
``` 

Language
--------
If you need full language support in mail formatting please enable 
Queue Mail Language (queue_mail_language) module. It will use language
of mail instead of default system language in mail formatting.

Drush
-----
Drush has his own command to process specific queue: 

`drush queue-run QUEUE_NAME`

So you can use command to run queue_mail worker:

`drush queue-run queue_mail --time-limit=15` 

__Note__: you have to use time-limit option with "drush queue-run queue_mail"
because "Queue processing time" setting doesn't work in this case. If system
can't send mails it adds them back to queue. Without time-limit option this 
process won't be finished.

API
---
Queue mail implements hook_queue_mail_send_alter(). This hook is very similar
hook_mail_alter() and allows change mail message right before sending.

See queue_mail.api.php for more information.

