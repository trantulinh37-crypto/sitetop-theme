<?php
/**
 * Mẫu file cấu hình secret cho deploy webhook.
 * Copy file này thành `deploy-config.php` (cùng thư mục) và điền giá trị bí mật thật.
 * `deploy-config.php` đã nằm trong .gitignore — KHÔNG commit lên git.
 */

define( 'SITETOP_DEPLOY_WEBHOOK_SECRET', 'CHANGE_ME' ); // dùng trong deploy-webhook.php
define( 'SITETOP_DEPLOY_KEY', 'CHANGE_ME' );             // dùng trong deploy.php
