# app/models.php
<?php
require_once __DIR__ . '/db.php';

/** USERS */
function model_users_count(): int {
    return (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

function model_user_by_email(string $email): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function model_user_by_id(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function model_user_create(string $name, string $email, string $role, string $password, ?int $member_id=null): int {
    $stmt = db()->prepare("INSERT INTO users (name,email,role,password_hash,member_id) VALUES (?,?,?,?,?)");
    $stmt->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $member_id]);
    return (int)db()->lastInsertId();
}

function model_user_update(int $id, array $data): void {
    $fields = [];
    $params = [];
    foreach (['name','email','role','member_id'] as $f) {
        if (array_key_exists($f, $data)) { $fields[]="$f=?"; $params[]=$data[$f]; }
    }
    if (!empty($data['password'])) {
        $fields[] = "password_hash=?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    if (!$fields) return;
    $params[] = $id;
    $sql = "UPDATE users SET ".implode(',', $fields)." WHERE id=?";
    db()->prepare($sql)->execute($params);
}

function model_user_delete(int $id): void {
    db()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
}

function model_users_list(): array {
    return db()->query("SELECT id,name,email,role,member_id,created_at FROM users ORDER BY id DESC")->fetchAll();
}

/** GENERIC CRUD (for other entities) */
function model_insert(string $table, array $data, array $allowed): int {
    $data = array_intersect_key($data, array_flip($allowed));
    $cols = array_keys($data);
    $place = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO $table (".implode(',', $cols).") VALUES ($place)";
    db()->prepare($sql)->execute(array_values($data));
    return (int)db()->lastInsertId();
}

function model_update(string $table, int $id, array $data, array $allowed): void {
    $data = array_intersect_key($data, array_flip($allowed));
    if (!$data) return;
    $sets = implode(',', array_map(fn($c)=>"$c=?", array_keys($data)));
    $sql = "UPDATE $table SET $sets WHERE id=?";
    db()->prepare($sql)->execute([...array_values($data), $id]);
}

function model_delete(string $table, int $id): void {
    db()->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
}

function model_get(string $table, int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM $table WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function model_list(string $table, string $order='id DESC', ?callable $filter=null): array {
    $sql = "SELECT * FROM $table";
    $params = [];
    if ($filter) {
        [$where, $params] = $filter();
        if ($where) $sql .= " WHERE $where";
    }
    $sql .= " ORDER BY $order";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Options for selects */
function options_feds(): array {
    $rows = db()->query("SELECT id, name FROM feds ORDER BY name")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['id']] = $r['name'];
    return $out;
}

function options_events(): array {
    $rows = db()->query("SELECT id, title FROM events ORDER BY event_date DESC")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['id']] = $r['title'];
    return $out;
}

function options_members(): array {
    $rows = db()->query("SELECT id, ring_name FROM members ORDER BY ring_name")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['id']] = $r['ring_name'];
    return $out;
}
