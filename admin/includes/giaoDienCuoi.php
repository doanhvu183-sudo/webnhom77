<?php
// admin/includes/giaoDienCuoi.php
// Đóng container + main + body + html.

if (!defined('APP_MAIN_OPENED')) {
  // nếu trang include sai thứ tự, vẫn không vỡ trắng trang
  define('APP_MAIN_OPENED', true);
  echo '<main class="flex-1"></main>';
}
?>

    </div><!-- /container -->
  </div><!-- /scroll -->
</main><!-- /main -->

  </div><!-- /root flex -->
</body>
</html>
