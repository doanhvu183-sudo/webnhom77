<?php
// admin/includes/thanhTren.php

if (session_status() === PHP_SESSION_NONE) session_start();

$ACTIVE = $ACTIVE ?? 'tong_quan';

$PAGE_TITLE   = $PAGE_TITLE   ?? 'Crocs Admin';
$PAGE_HEADING = $PAGE_HEADING ?? $PAGE_TITLE;
$PAGE_DESC    = $PAGE_DESC    ?? '';

$SHOW_SEARCH  = $SHOW_SEARCH  ?? true;
$SEARCH_NAME  = $SEARCH_NAME  ?? 'q';
$SEARCH_Q     = trim((string)($_GET[$SEARCH_NAME] ?? ''));
$SEARCH_PLACEHOLDER = $SEARCH_PLACEHOLDER ?? 'Tìm kiếm nhanh...';
$SEARCH_ACTION = $SEARCH_ACTION ?? basename($_SERVER['PHP_SELF'] ?? '');

$BACK_URL   = $BACK_URL ?? ($_SERVER['HTTP_REFERER'] ?? '');
$SHOW_BACK  = $SHOW_BACK ?? true;

$CONTAINER_CLASS = $CONTAINER_CLASS ?? 'max-w-7xl mx-auto px-4 md:px-8 py-6';

$me = $_SESSION['admin'] ?? [];
$displayName = (string)($me['ho_ten'] ?? $me['username'] ?? $me['email'] ?? 'Tài khoản');
$role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'NHANVIEN')));
$dept = strtoupper(trim((string)($me['bo_phan'] ?? $me['phong_ban'] ?? '')));

// ✅ CHỈ tạo h() nếu chưa tồn tại
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$can = function(string $key) use ($role, $dept) {
  if (function_exists('can_access')) return can_access($key, $role, $dept);
  return true;
};

$roleLabel = ($role === 'ADMIN') ? 'ADMIN' : (str_replace('_',' ', $role) ?: 'NHÂN VIÊN');

?>
<?php if (!defined('APP_MAIN_OPENED')): define('APP_MAIN_OPENED', true); ?>
<main class="flex-1 flex flex-col h-screen overflow-hidden">

  <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 h-16 flex items-center justify-between px-4 md:px-6 sticky top-0 z-30">
    <div class="flex items-center gap-3 min-w-0">
      <button type="button"
        class="md:hidden inline-flex items-center justify-center size-10 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50"
        onclick="window.__toggleMobileSidebar && window.__toggleMobileSidebar(true)">
        <span class="material-symbols-outlined text-slate-700">menu</span>
      </button>

      <?php if($SHOW_BACK && $BACK_URL): ?>
        <a href="<?= h($BACK_URL) ?>"
           class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 text-sm font-extrabold text-slate-700">
          <span class="material-symbols-outlined text-[20px]">arrow_back</span>
          Quay lại
        </a>
      <?php endif; ?>

      <div class="min-w-0">
        <div class="text-lg md:text-xl font-extrabold text-slate-900 truncate"><?= h($PAGE_HEADING) ?></div>
        <?php if($PAGE_DESC): ?>
          <div class="text-xs text-slate-500 font-semibold truncate"><?= h($PAGE_DESC) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <?php if($SHOW_SEARCH): ?>
        <form class="hidden md:block relative" method="get" action="<?= h($SEARCH_ACTION) ?>">
          <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-[20px]">search</span>
          <input name="<?= h($SEARCH_NAME) ?>" value="<?= h($SEARCH_Q) ?>"
            class="pl-10 pr-4 py-2 bg-slate-100 border-none rounded-2xl text-sm w-[340px] focus:ring-2 focus:ring-primary/40"
            placeholder="<?= h($SEARCH_PLACEHOLDER) ?>"/>
          <?php
            foreach($_GET as $k=>$v){
              if ($k === $SEARCH_NAME) continue;
              if (is_array($v)) continue;
              echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
            }
          ?>
        </form>
      <?php endif; ?>

      <?php if($can('thongbao')): ?>
      <a href="thongbao.php"
         class="relative inline-flex items-center justify-center size-10 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50">
        <span class="material-symbols-outlined text-slate-700">notifications</span>
      </a>
      <?php endif; ?>

      <span class="hidden md:inline-flex text-xs font-extrabold px-3 py-1.5 rounded-full bg-slate-100 text-slate-700">
        <?= h($roleLabel) ?><?= $dept ? ' • '.h($dept) : '' ?>
      </span>

      
      </a>
    </div>
  </header>

  <div class="flex-1 overflow-y-auto">
    <div class="<?= h($CONTAINER_CLASS) ?>">
<?php endif; ?>
