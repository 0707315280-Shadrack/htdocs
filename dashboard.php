<?php
// Legacy URL compatibility: the canonical management dashboard is under /admin.
header('Location: /admin/dashboard.php', true, 302);
exit;
