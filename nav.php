<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentRole = $_SESSION['role'] ?? '';
$navigation = $currentRole === 'admin' ? [
  'index.php' => ['label' => 'Overview', 'href' => '/assessment_db/index.php'],
  'clients_list.php' => ['label' => 'Clients', 'href' => '/assessment_db/pages/clients_list.php'],
  'services_list.php' => ['label' => 'Services', 'href' => '/assessment_db/pages/services_list.php'],
  'bookings_list.php' => ['label' => 'Bookings', 'href' => '/assessment_db/pages/bookings_list.php'],
  'tools_list_assign.php' => ['label' => 'Tools', 'href' => '/assessment_db/pages/tools_list_assign.php'],
  'payments_list.php' => ['label' => 'Payments', 'href' => '/assessment_db/pages/payments_list.php'],
] : [
  'user_home.php' => ['label' => 'My bookings', 'href' => '/assessment_db/pages/user_home.php'],
];
$homeHref = $currentRole === 'admin' ? '/assessment_db/index.php' : '/assessment_db/pages/user_home.php';
?>
<header class="topbar">
  <a class="brand" href="<?php echo $homeHref; ?>">
    <span class="brand-mark" aria-hidden="true">A</span>
    <span><strong>Assessment</strong><small>Service management</small></span>
  </a>
  <nav class="primary-nav" aria-label="Main navigation">
    <?php foreach ($navigation as $page => $item) { ?>
      <a href="<?php echo $item['href']; ?>"<?php echo $currentPage === $page ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo $item['label']; ?></a>
    <?php } ?>
    <a href="/assessment_db/auth/logout.php">Log out</a>
  </nav>
</header>