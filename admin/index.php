<?php
session_start();

// ================= НАСТРОЙКИ =================
$data_dir = __DIR__ . '/data';
$users_file = $data_dir . '/users.json';
$orders_file = $data_dir . '/orders.json';
$news_file = $data_dir . '/news.json';
$promos_file = $data_dir . '/promos.json';
$gallery_file = $data_dir . '/gallery.json';

// Папка для загрузки (на уровень выше папки admin, то есть public/uploads)
$upload_dir = dirname(__DIR__) . '/uploads/';

if (!is_dir($data_dir)) mkdir($data_dir, 0777, true);
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

function readJson($file) {
    return file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}
function writeJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function uploadFile($file, $upload_dir) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = uniqid() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                // ВОЗВРАЩАЕМ ПУТЬ С СЛЕШЕМ В НАЧАЛЕ (абсолютный путь от корня сайта)
                return 'uploads/' . $filename; 
            }
        }
    }
    return null;
}

// Приводит старые абсолютные пути (/uploads/...) к относительным URL сайта.
function siteAssetUrl($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('~^(https?:)?//~i', $path) || strpos($path, 'data:') === 0) return $path;
    return ltrim($path, '/');
}

$users = readJson($users_file);
$error = '';
$success = '';

// ================= ВХОД =================
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_initial') {
    if (count($users) > 0) {
        $error = 'Регистрация закрыта.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        if (strlen($login) >= 3 && strlen($password) >= 5) {
            $users[] = ['id' => uniqid(), 'login' => $login, 'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'superadmin', 'created_at' => date('d.m.Y H:i')];
            writeJson($users_file, $users);
            $success = 'Главный администратор создан!';
        } else {
            $error = 'Логин (мин. 3) и пароль (мин. 5) слишком короткие.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    foreach ($users as $u) {
        if ($u['login'] === $login && password_verify($password, $u['password'])) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['user_login'] = $u['login'];
            $_SESSION['user_role'] = $u['role'];
            header('Location: index.php');
            exit;
        }
    }
    $error = 'Неверный логин или пароль';
}

if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход — Админ-панель Посейдон</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Montserrat', sans-serif; background: #eaf6fc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: #0b2b4a; }
            .login-box { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 20px 50px rgba(11, 43, 74, 0.1); width: 100%; max-width: 400px; }
            h2 { text-align: center; margin-top: 0; font-weight: 800; margin-bottom: 20px; }
            input { width: 100%; padding: 15px; margin-bottom: 15px; border: 1.5px solid #dce7f0; border-radius: 10px; font-family: inherit; box-sizing: border-box; outline: none; }
            input:focus { border-color: #2ec4e6; }
            button { width: 100%; padding: 15px; background: linear-gradient(135deg, #2ec4e6, #1a6fb0); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem; }
            .error { color: #ff4d4f; text-align: center; margin-bottom: 15px; font-weight: 600; font-size: 0.9rem; }
            .success { color: #10b981; text-align: center; margin-bottom: 15px; font-weight: 600; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2><?= count($users) === 0 ? 'Создание Главного Админа' : 'Вход в админку' ?></h2>
            <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
            <form method="POST">
                <?php if (count($users) === 0): ?>
                    <input type="hidden" name="action" value="register_initial">
                    <input type="text" name="login" placeholder="Придумайте логин" required>
                    <input type="password" name="password" placeholder="Придумайте пароль" required>
                    <button type="submit">Создать аккаунт</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="login" placeholder="Логин" required>
                    <input type="password" name="password" placeholder="Пароль" required>
                    <button type="submit">Войти</button>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ================= ЛОГИКА ДАШБОРДА =================
$user_role = $_SESSION['user_role'];
$current_tab = $_GET['tab'] ?? 'orders';

$roles_dict = ['superadmin' => 'Главный админ', 'operator' => 'Оператор', 'editor' => 'Редактор', 'employee' => 'Сотрудник'];

// Пользователи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user' && $user_role === 'superadmin') {
    $new_login = trim($_POST['new_login'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $new_role = $_POST['new_role'] ?? 'employee';
    $exists = false;
    foreach ($users as $u) { if ($u['login'] === $new_login) $exists = true; }
    
    if ($exists) { $error = 'Логин уже занят.'; } 
    elseif (strlen($new_login) >= 3 && strlen($new_password) >= 5) {
        $users[] = ['id' => uniqid(), 'login' => $new_login, 'password' => password_hash($new_password, PASSWORD_DEFAULT), 'role' => $new_role, 'created_at' => date('d.m.Y H:i')];
        writeJson($users_file, $users);
        $success = 'Сотрудник создан!';
    } else { $error = 'Слишком короткий логин или пароль.'; }
}

if (isset($_GET['delete_user']) && $user_role === 'superadmin' && $_GET['delete_user'] !== $_SESSION['user_id']) {
    $users = array_filter($users, function($u) { return $u['id'] !== $_GET['delete_user']; });
    writeJson($users_file, array_values($users));
    header('Location: index.php?tab=users'); exit;
}

if (isset($_POST['change_role']) && $user_role === 'superadmin') {
    foreach ($users as &$u) {
        if ($u['id'] === $_POST['user_id'] && $u['id'] !== $_SESSION['user_id']) { $u['role'] = $_POST['new_role']; }
    }
    writeJson($users_file, $users);
    header('Location: index.php?tab=users'); exit;
}

// Заказы
if (isset($_GET['delete']) && in_array($user_role, ['superadmin', 'operator'])) {
    $orders = readJson($orders_file);
    $orders = array_filter($orders, function($o) { return $o['id'] !== $_GET['delete']; });
    writeJson($orders_file, array_values($orders));
    header('Location: index.php?tab=orders'); exit;
}
if (isset($_POST['update_status']) && in_array($user_role, ['superadmin', 'operator'])) {
    $orders = readJson($orders_file);
    foreach ($orders as &$o) { if ($o['id'] === $_POST['order_id']) $o['status'] = $_POST['status']; }
    writeJson($orders_file, $orders);
    header('Location: index.php?tab=orders'); exit;
}

// Контент
if (in_array($user_role, ['superadmin', 'editor'])) {
    // Новости
    if (isset($_POST['add_news'])) {
        $img = uploadFile($_FILES['news_image'], $upload_dir);
        if ($img) {
            $news = readJson($news_file);
            array_unshift($news, ['id'=>uniqid(), 'title'=>$_POST['news_title'], 'date'=>$_POST['news_date'], 'desc'=>$_POST['news_desc'], 'image'=>$img]);
            writeJson($news_file, $news);
            $success = 'Новость добавлена!';
        } else { $error = 'Ошибка загрузки фото новости. Проверьте папку uploads.'; }
    }
    if (isset($_GET['delete_news'])) {
        $news = array_filter(readJson($news_file), function($n) { return $n['id'] !== $_GET['delete_news']; });
        writeJson($news_file, array_values($news));
        header('Location: index.php?tab=content'); exit;
    }

    // Акции
    if (isset($_POST['add_promo'])) {
        $img = uploadFile($_FILES['promo_image'], $upload_dir);
        if ($img) {
            $promos = readJson($promos_file);
            array_unshift($promos, ['id'=>uniqid(), 'image'=>$img]);
            writeJson($promos_file, $promos);
            $success = 'Акция добавлена!';
        } else { $error = 'Ошибка загрузки фото акции.'; }
    }
    if (isset($_GET['delete_promo'])) {
        $promos = array_filter(readJson($promos_file), function($p) { return $p['id'] !== $_GET['delete_promo']; });
        writeJson($promos_file, array_values($promos));
        header('Location: index.php?tab=content'); exit;
    }

    // Галерея
    if (isset($_POST['add_gallery'])) {
        $img = uploadFile($_FILES['gallery_image'], $upload_dir);
        if ($img) {
            $gallery = readJson($gallery_file);
            array_unshift($gallery, ['id'=>uniqid(), 'image'=>$img]);
            writeJson($gallery_file, $gallery);
            $success = 'Фото добавлено в галерею!';
        } else { $error = 'Ошибка загрузки фото в галерею.'; }
    }
    if (isset($_GET['delete_gallery'])) {
        $gallery = array_filter(readJson($gallery_file), function($g) { return $g['id'] !== $_GET['delete_gallery']; });
        writeJson($gallery_file, array_values($gallery));
        header('Location: index.php?tab=content'); exit;
    }
}

$orders = readJson($orders_file);
$news = readJson($news_file);
$promos = readJson($promos_file);
$gallery = readJson($gallery_file);

$total = count($orders);
$new_count = count(array_filter($orders, function($o) { return ($o['status'] ?? 'new') === 'new'; }));
$done_count = count(array_filter($orders, function($o) { return ($o['status'] ?? 'new') === 'done'; }));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель управления — Посейдон</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --deep: #0b2b4a; --blue: #1a6fb0; --aqua: #2ec4e6; --bg: #f4f7f9; --white: #fff; --gray: #5a7a8a; --border: #e2e8f0; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); margin: 0; color: var(--deep); }
        .header { background: var(--deep); color: #fff; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.3rem; font-weight: 800; }
        .header__right { display: flex; align-items: center; gap: 20px; font-size: 0.9rem; }
        .user-info { display: flex; flex-direction: column; align-items: flex-end; margin-right: 10px; }
        .user-name { font-weight: 700; }
        .user-role { font-size: 0.75rem; color: var(--aqua); text-transform: uppercase; letter-spacing: 1px; }
        .logout-btn { background: rgba(255,255,255,0.1); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; }
        .tabs { display: flex; gap: 20px; background: var(--white); padding: 0 40px; border-bottom: 1px solid var(--border); margin-bottom: 30px; }
        .tab { padding: 15px 10px; text-decoration: none; color: var(--gray); font-weight: 600; border-bottom: 3px solid transparent; }
        .tab.active { color: var(--blue); border-bottom-color: var(--blue); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px 40px; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #fee2e2; color: #ef4444; }
        .alert-success { background: #dcfce7; color: #15803d; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); padding: 24px; border-radius: 16px; box-shadow: 0 4px 15px rgba(11, 43, 74, 0.05); border-left: 4px solid var(--blue); }
        .stat-card.new { border-left-color: var(--aqua); }
        .stat-card.done { border-left-color: #10b981; }
        .stat-title { font-size: 0.8rem; color: var(--gray); text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
        .stat-value { font-size: 2rem; font-weight: 800; }
        .table-card { background: var(--white); border-radius: 16px; box-shadow: 0 4px 20px rgba(11, 43, 74, 0.05); overflow: hidden; margin-bottom: 30px; }
        .table-header { padding: 20px 24px; border-bottom: 1px solid var(--border); }
        .table-header h2 { margin: 0; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        th { background: #fafbfc; color: var(--gray); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .status-new { background: #e0f2fe; color: #0284c7; }
        .status-done { background: #dcfce7; color: #15803d; }
        .status-processing { background: #fef3c7; color: #d97706; }
        .btn-sm { padding: 8px 12px; border: none; border-radius: 8px; font-weight: 600; font-size: 0.8rem; cursor: pointer; text-decoration: none; }
        .btn-update { background: var(--blue); color: #fff; }
        .btn-delete { background: #fee2e2; color: #ef4444; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: var(--gray); }
        .form-group input, .form-group textarea { padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .content-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .content-item { background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; position: relative; }
        .content-item img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; }
        .content-item .btn-delete { display: block; margin-top: 10px; }
    </style>
</head>
<body>
<div class="header">
    <h1>Посейдон · Управление</h1>
    <div class="header__right">
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user_login']) ?></span>
            <span class="user-role"><?= $roles_dict[$user_role] ?? $user_role ?></span>
        </div>
        <a href="?logout=1" class="logout-btn">Выйти</a>
    </div>
</div>

<div class="tabs">
    <?php if (in_array($user_role, ['superadmin', 'operator', 'employee'])): ?>
        <a href="?tab=orders" class="tab <?= $current_tab === 'orders' ? 'active' : '' ?>">Заказы</a>
    <?php endif; ?>
    <?php if (in_array($user_role, ['superadmin', 'editor'])): ?>
        <a href="?tab=content" class="tab <?= $current_tab === 'content' ? 'active' : '' ?>">Управление контентом</a>
    <?php endif; ?>
    <?php if ($user_role === 'superadmin'): ?>
        <a href="?tab=users" class="tab <?= $current_tab === 'users' ? 'active' : '' ?>">Сотрудники</a>
    <?php endif; ?>
</div>

<div class="container">
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <?php if ($current_tab === 'orders'): ?>
        <div class="stats">
            <div class="stat-card"><div class="stat-title">Всего заказов</div><div class="stat-value"><?= $total ?></div></div>
            <div class="stat-card new"><div class="stat-title">Новые</div><div class="stat-value"><?= $new_count ?></div></div>
            <div class="stat-card done"><div class="stat-title">Выполнено</div><div class="stat-value"><?= $done_count ?></div></div>
        </div>
        <div class="table-card">
            <div class="table-header"><h2>Список заказов</h2></div>
            <?php if (empty($orders)): ?>
                <p style="padding: 20px;">Нет заказов.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Дата</th><th>Клиент</th><th>Телефон</th><th>Адрес</th><th>Статус</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach ($orders as $o): 
                            $s = $o['status'] ?? 'new'; $sc = 'status-new'; $st = 'Новый';
                            if($s==='done'){ $sc='status-done'; $st='Доставлен'; } elseif($s==='processing'){ $sc='status-processing'; $st='В работе'; }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($o['date']) ?></td>
                            <td><strong><?= htmlspecialchars($o['name']) ?></strong></td>
                            <td><?= htmlspecialchars($o['phone']) ?></td>
                            <td><?= htmlspecialchars($o['address']) ?></td>
                            <td><span class="status-badge <?= $sc ?>"><?= $st ?></span></td>
                            <td>
                                <?php if (in_array($user_role, ['superadmin', 'operator'])): ?>
                                    <form method="POST" style="display:inline-flex; gap:5px;">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <select name="status" style="padding:5px; border-radius:5px;">
                                            <option value="new" <?= $s==='new'?'selected':'' ?>>Новый</option>
                                            <option value="processing" <?= $s==='processing'?'selected':'' ?>>В работе</option>
                                            <option value="done" <?= $s==='done'?'selected':'' ?>>Доставлен</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn-sm btn-update">ОК</button>
                                    </form>
                                    <a href="?delete=<?= $o['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Удалить?')">Удалить</a>
                                <?php else: ?>
                                    <span style="color:gray; font-size:0.8rem;">Только просмотр</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($current_tab === 'content' && in_array($user_role, ['superadmin', 'editor'])): ?>
        <!-- НОВОСТИ -->
        <div class="table-card">
            <div class="table-header"><h2>Добавить новость</h2></div>
            <form method="POST" enctype="multipart/form-data" style="padding: 24px;">
                <div class="form-grid">
                    <div class="form-group"><label>Заголовок</label><input type="text" name="news_title" required></div>
                    <div class="form-group"><label>Дата</label><input type="text" name="news_date" value="<?= date('d.m.Y') ?>" required></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Описание</label><textarea name="news_desc" required></textarea></div>
                    <div class="form-group"><label>Картинка</label><input type="file" name="news_image" accept="image/*" required></div>
                </div>
                <button type="submit" name="add_news" class="btn-sm btn-update" style="margin-top: 15px;">Опубликовать</button>
            </form>
            <div style="padding: 0 24px 24px;">
                <div class="content-grid">
                    <?php foreach ($news as $n): ?>
                        <div class="content-item">
                            <img src="<?= htmlspecialchars(siteAssetUrl($n['image']), ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <div style="font-weight:bold; font-size:0.85rem; margin-bottom:5px;"><?= htmlspecialchars($n['title']) ?></div>
                            <a href="?tab=content&delete_news=<?= $n['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Удалить?')">Удалить</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- АКЦИИ -->
        <div class="table-card">
            <div class="table-header"><h2>Добавить акцию (Баннер)</h2></div>
            <form method="POST" enctype="multipart/form-data" style="padding: 24px; display:flex; gap:20px; align-items:flex-end;">
                <div class="form-group" style="flex:1;"><label>Широкое фото</label><input type="file" name="promo_image" accept="image/*" required></div>
                <button type="submit" name="add_promo" class="btn-sm btn-update">Добавить</button>
            </form>
            <div style="padding: 0 24px 24px;">
                <div class="content-grid" style="grid-template-columns: 1fr;">
                    <?php foreach ($promos as $p): ?>
                        <div class="content-item" style="display:flex; justify-content:space-between; align-items:center;">
                            <img src="<?= htmlspecialchars(siteAssetUrl($p['image']), ENT_QUOTES, 'UTF-8') ?>" style="width:300px; height:auto; object-fit:contain; margin:0;" alt="">
                            <a href="?tab=content&delete_promo=<?= $p['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Удалить?')">Удалить</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ГАЛЕРЕЯ -->
        <div class="table-card">
            <div class="table-header"><h2>Добавить фото в галерею</h2></div>
            <form method="POST" enctype="multipart/form-data" style="padding: 24px; display:flex; gap:20px; align-items:flex-end;">
                <div class="form-group" style="flex:1;"><label>Изображение</label><input type="file" name="gallery_image" accept="image/*" required></div>
                <button type="submit" name="add_gallery" class="btn-sm btn-update">Загрузить</button>
            </form>
            <div style="padding: 0 24px 24px;">
                <div class="content-grid">
                    <?php foreach ($gallery as $g): ?>
                        <div class="content-item">
                            <img src="<?= htmlspecialchars(siteAssetUrl($g['image']), ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <a href="?tab=content&delete_gallery=<?= $g['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Удалить?')">Удалить</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($current_tab === 'users' && $user_role === 'superadmin'): ?>
        <div class="table-card">
            <div class="table-header"><h2>Добавить сотрудника</h2></div>
            <form method="POST" style="padding: 24px; display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="action" value="create_user">
                <div class="form-group"><label>Логин</label><input type="text" name="new_login" required></div>
                <div class="form-group"><label>Пароль</label><input type="text" name="new_password" required></div>
                <div class="form-group"><label>Роль</label>
                    <select name="new_role" style="padding:12px; border:1px solid #ccc; border-radius:8px;">
                        <option value="employee">Сотрудник</option>
                        <option value="operator">Оператор</option>
                        <option value="editor">Редактор</option>
                        <option value="superadmin">Главный админ</option>
                    </select>
                </div>
                <button type="submit" class="btn-sm btn-update">Создать</button>
            </form>
        </div>
        <div class="table-card">
            <div class="table-header"><h2>Сотрудники</h2></div>
            <table>
                <thead><tr><th>Логин</th><th>Роль</th><th>Дата</th><th>Роль (изменить)</th><th>Действия</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): $ur = $u['role'] ?? 'employee'; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['login']) ?></strong></td>
                            <td><?= $roles_dict[$ur] ?? $ur ?></td>
                            <td><?= htmlspecialchars($u['created_at'] ?? '—') ?></td>
                            <td>
                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline-flex; gap:5px;">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="new_role" style="padding:5px; border-radius:5px;">
                                            <option value="employee" <?= $ur==='employee'?'selected':'' ?>>Сотрудник</option>
                                            <option value="operator" <?= $ur==='operator'?'selected':'' ?>>Оператор</option>
                                            <option value="editor" <?= $ur==='editor'?'selected':'' ?>>Редактор</option>
                                            <option value="superadmin" <?= $ur==='superadmin'?'selected':'' ?>>Главный админ</option>
                                        </select>
                                        <button type="submit" name="change_role" class="btn-sm btn-update">ОК</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:gray; font-size:0.8rem;">Это вы</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                    <a href="?tab=users&delete_user=<?= $u['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Удалить?')">Удалить</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>