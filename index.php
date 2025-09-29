# public/index.php
<?php
// Single entry point: routing + controllers + rendering.
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/models.php';

$path = trim($_GET['path'] ?? ($_GET['r'] ?? '/'), '/'); // supports /?r=... too
if ($path === '') $path = '/';

$entities = [
    'users' => [
        'table'=>'users','title'=>'Users',
        'fields'=>[
            'name'=>['label'=>'Name','type'=>'text','required'=>true],
            'email'=>['label'=>'Email','type'=>'email','required'=>true],
            'role'=>['label'=>'Role','type'=>'select','options'=>['admin'=>'admin','staff'=>'staff','member'=>'member'],'required'=>true],
            'member_id'=>['label'=>'Linked Member','type'=>'select','options_callback'=>'options_members','nullable'=>true],
            'password'=>['label'=>'Password','type'=>'password','required_on_create'=>true,'never_list'=>true],
        ],
        'allowed'=>['name','email','role','member_id'],
        'permissions'=>['list'=>'admin','create'=>'admin','edit'=>'admin','delete'=>'admin'],
        'custom' => 'users', // for custom create/update logic
    ],
    'feds' => [
        'table'=>'feds','title'=>'Federations',
        'fields'=>[
            'name'=>['label'=>'Name','type'=>'text','required'=>true],
            'abbr'=>['label'=>'Abbrev','type'=>'text'],
            'description'=>['label'=>'Description','type'=>'textarea'],
        ],
        'allowed'=>['name','abbr','description'],
        'permissions'=>['list'=>'staff','create'=>'staff','edit'=>'staff','delete'=>'staff'],
    ],
    'members' => [
        'table'=>'members','title'=>'Roster',
        'fields'=>[
            'fed_id'=>['label'=>'Federation','type'=>'select','options_callback'=>'options_feds','required'=>true],
            'ring_name'=>['label'=>'Ring Name','type'=>'text','required'=>true],
            'real_name'=>['label'=>'Real Name','type'=>'text'],
            'status'=>['label'=>'Status','type'=>'select','options'=>['active'=>'active','inactive'=>'inactive','on_hiatus'=>'on_hiatus']],
        ],
        'allowed'=>['fed_id','ring_name','real_name','status'],
        'permissions'=>['list'=>'staff','create'=>'staff','edit'=>'staff','delete'=>'staff'],
    ],
    'events' => [
        'table'=>'events','title'=>'Events',
        'fields'=>[
            'fed_id'=>['label'=>'Federation','type'=>'select','options_callback'=>'options_feds','required'=>true],
            'title'=>['label'=>'Title','type'=>'text','required'=>true],
            'event_date'=>['label'=>'Date','type'=>'date','required'=>true],
            'location'=>['label'=>'Location','type'=>'text'],
        ],
        'allowed'=>['fed_id','title','event_date','location'],
        'permissions'=>['list'=>'staff','create'=>'staff','edit'=>'staff','delete'=>'staff'],
    ],
    'matches' => [
        'table'=>'matches','title'=>'Matches',
        'fields'=>[
            'event_id'=>['label'=>'Event','type'=>'select','options_callback'=>'options_events','required'=>true],
            'participants'=>['label'=>'Participants (comma-separated)','type'=>'text','required'=>true],
            'result'=>['label'=>'Result','type'=>'text'],
        ],
        'allowed'=>['event_id','participants','result'],
        'permissions'=>['list'=>'staff','create'=>'staff','edit'=>'staff','delete'=>'staff'],
    ],
    'promos' => [
        'table'=>'promos','title'=>'Promos',
        'fields'=>[
            'member_id'=>['label'=>'Member','type'=>'select','options_callback'=>'options_members','required'=>true],
            'event_id'=>['label'=>'Event (optional)','type'=>'select','options_callback'=>'options_events','nullable'=>true],
            'content'=>['label'=>'Content','type'=>'textarea','required'=>true],
        ],
        'allowed'=>['member_id','event_id','content'],
        'permissions'=>['list'=>'member','create'=>'member','edit'=>'staff','delete'=>'staff'],
    ],
];

function render_layout(string $title, string $content): void {
    ob_start();
    $title = $title;
    $content = $content;
    include __DIR__ . '/../app/views/layout.php';
    echo ob_get_clean();
}

function render_form(array $def, array $values=[], string $action='create', ?array $errors=null, bool $is_member_context=false): string {
    $html = '<form method="post" class="vstack gap-3">'.csrf_field();
    foreach ($def['fields'] as $name=>$cfg) {
        if (($cfg['never_list'] ?? false) && $action==='edit') continue; // hide password on edit unless changing
        // Hide member_id field for member role on create in promos
        if ($is_member_context && $action==='create' && $name==='member_id') continue;

        $label = e($cfg['label']);
        $req   = ($cfg['required'] ?? false) || ($cfg['required_on_create'] ?? false && $action==='create');
        $val   = $values[$name] ?? '';
        $err   = $errors[$name] ?? null;

        $html .= '<div>';
        $html .= '<label class="form-label'.($req?' required':'').'">'. $label .'</label>';

        $type = $cfg['type'];
        if ($type === 'textarea') {
            $html .= '<textarea class="form-control" name="'.e($name).'" rows="5">'.e($val).'</textarea>';
        } elseif ($type === 'select') {
            $opts = $cfg['options'] ?? [];
            if (isset($cfg['options_callback']) && function_exists($cfg['options_callback'])) {
                $opts = call_user_func($cfg['options_callback']);
            }
            $nullable = $cfg['nullable'] ?? false;
            $html .= '<select class="form-select" name="'.e($name).'">';
            if ($nullable) $html .= '<option value="">-- none --</option>';
            foreach ($opts as $k=>$v) {
                $sel = ((string)$val === (string)$k) ? ' selected' : '';
                $html .= '<option value="'.e($k).'"'.$sel.'>'.e($v).'</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input class="form-control" type="'.e($type).'" name="'.e($name).'" value="'.e($val).'">';
        }

        if ($err) $html .= '<div class="text-danger small">'.e($err).'</div>';
        $html .= '</div>';
    }
    $html .= '<div><button class="btn btn-primary" type="submit">Save</button></div></form>';
    return $html;
}

function validate(array $def, array $input, string $action='create', bool $is_member_context=false): array {
    $errors=[]; $data=[];
    foreach ($def['fields'] as $name=>$cfg) {
        if ($is_member_context && $action==='create' && $name==='member_id') continue; // assigned server-side
        $required = ($cfg['required'] ?? false) || ($cfg['required_on_create'] ?? false && $action==='create');
        $val = trim((string)($input[$name] ?? ''));
        if (($cfg['type'] ?? '') === 'select' && isset($cfg['nullable']) && $cfg['nullable'] && $val==='') {
            $val = null;
        }
        if ($required && ($val === '' || $val === null)) {
            $errors[$name] = 'Required';
        } else {
            $data[$name]=$val;
        }
    }
    return [$errors,$data];
}

/** ---------- ROUTES ---------- */

if ($path === 'install') {
    if (!install_needed()) redirect('/');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password)>=8) {
            model_user_create($name, $email, 'admin', $password, null);
            flash('success', 'Admin user created. Please log in.');
            redirect('login');
        } else {
            flash('error', 'Invalid input. Use a valid email and 8+ char password.');
        }
    }
    ob_start(); ?>
    <div class="col-lg-6 mx-auto">
      <h1 class="mb-3">First-time Setup</h1>
      <p class="text-muted">Create your initial admin account.</p>
      <form method="post" class="vstack gap-3">
        <?= csrf_field() ?>
        <div><label class="form-label required">Name</label><input class="form-control" name="name" required></div>
        <div><label class="form-label required">Email</label><input class="form-control" type="email" name="email" required></div>
        <div><label class="form-label required">Password</label><input class="form-control" type="password" name="password" minlength="8" required></div>
        <button class="btn btn-primary">Create Admin</button>
      </form>
    </div>
    <?php
    $content = ob_get_clean();
    render_layout('Install • eFed CRM', $content);
    exit;
}

if ($path === '/' || $path === 'dashboard') {
    if (install_needed()) redirect('install');
    auth_require();
    $u = auth_user();
    $stats = [
        'Feds' => db()->query("SELECT COUNT(*) FROM feds")->fetchColumn(),
        'Members' => db()->query("SELECT COUNT(*) FROM members")->fetchColumn(),
        'Events' => db()->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'Matches' => db()->query("SELECT COUNT(*) FROM matches")->fetchColumn(),
        'Promos (last 7d)' => (function(){
            $stmt = db()->prepare("SELECT COUNT(*) FROM promos WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            return $stmt->fetchColumn();
        })(),
    ];
    ob_start(); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3">Dashboard</h1>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url()) ?>/events">View Events</a>
    </div>
    <div class="row g-3">
      <?php foreach ($stats as $label=>$val): ?>
        <div class="col-sm-6 col-lg-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="text-muted small"><?= e($label) ?></div>
              <div class="fs-3 fw-semibold"><?= e($val) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    $content = ob_get_clean();
    render_layout('Dashboard • eFed CRM', $content);
    exit;
}

if ($path === 'login') {
    if (!install_needed() && auth_user()) redirect('/');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if (auth_login($email, $password)) {
            flash('success', 'Welcome back!');
            redirect('/');
        } else {
            flash('error', 'Invalid credentials.');
        }
    }
    ob_start(); ?>
    <div class="col-md-5 mx-auto">
      <h1 class="h3 mb-3">Sign in</h1>
      <form method="post" class="vstack gap-3">
        <?= csrf_field() ?>
        <div><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
        <div><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
        <button class="btn btn-primary">Sign in</button>
      </form>
      <?php if (install_needed()): ?>
        <div class="alert alert-warning mt-3">No users found. <a href="<?= e(base_url()) ?>/install">Run setup</a>.</div>
      <?php endif; ?>
    </div>
    <?php
    $content = ob_get_clean();
    render_layout('Login • eFed CRM', $content);
    exit;
}

if ($path === 'logout') { auth_logout(); redirect('login'); }

/** Entity routes: /{entity}, /{entity}/create, /{entity}/{id}/edit, /{entity}/{id}/delete */
$parts = explode('/', $path);
$entity = $parts[0] ?? '';
if (isset($entities[$entity])) {
    auth_require();
    $def = $entities[$entity];

    // Permission helper
    $u = auth_user();
    $min = $def['permissions']['list'] ?? 'member';
    if (!auth_check($min)) { http_response_code(403); die('Forbidden'); }

    // Actions
    $action = $parts[1] ?? 'index';
    $id     = isset($parts[2]) ? (int)$parts[2] : null;

    // List
    if ($action === 'index') {
        $rows = model_list($def['table']);
        ob_start(); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h1 class="h4 mb-0"><?= e($def['title']) ?></h1>
          <?php if (auth_check($def['permissions']['create'] ?? 'member')): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(base_url()."/$entity/create") ?>">Create</a>
          <?php endif; ?>
        </div>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead><tr>
              <?php if ($entity==='users'): ?>
                <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Linked Member</th><th>Created</th><th></th>
              <?php else: ?>
                <?php
                  // simple columns per entity
                  if ($entity==='feds') echo '<th>ID</th><th>Name</th><th>Abbrev</th><th></th>';
                  if ($entity==='members') echo '<th>ID</th><th>Ring Name</th><th>Real Name</th><th>Status</th><th>Fed</th><th></th>';
                  if ($entity==='events') echo '<th>ID</th><th>Title</th><th>Date</th><th>Fed</th><th>Location</th><th></th>';
                  if ($entity==='matches') echo '<th>ID</th><th>Event</th><th>Participants</th><th>Result</th><th></th>';
                  if ($entity==='promos') echo '<th>ID</th><th>Member</th><th>Event</th><th>Created</th><th></th>';
                ?>
              <?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <?php if ($entity==='users'): ?>
                  <td><?= e($r['id']) ?></td>
                  <td><?= e($r['name']) ?></td>
                  <td><?= e($r['email']) ?></td>
                  <td><span class="badge text-bg-secondary"><?= e($r['role']) ?></span></td>
                  <td><?= e($r['member_id'] ?: '-') ?></td>
                  <td><?= e($r['created_at']) ?></td>
                  <td class="table-actions">
                    <?php if (auth_check($def['permissions']['edit'])): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()."/$entity/$r[id]/edit") ?>">Edit</a>
                    <?php endif; ?>
                    <?php if (auth_check($def['permissions']['delete'])): ?>
                        <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url()."/$entity/$r[id]/delete") ?>" onclick="return confirm('Delete?')">Delete</a>
                    <?php endif; ?>
                  </td>
                <?php elseif ($entity==='feds'): ?>
                  <td><?= e($r['id']) ?></td>
                  <td><?= e($r['name']) ?></td>
                  <td><?= e($r['abbr']) ?></td>
                  <td class="table-actions">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()."/$entity/$r[id]/edit") ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url()."/$entity/$r[id]/delete") ?>" onclick="return confirm('Delete?')">Delete</a>
                  </td>
                <?php elseif ($entity==='members'): ?>
                  <td><?= e($r['id']) ?></td>
                  <td><?= e($r['ring_name']) ?></td>
                  <td><?= e($r['real_name']) ?></td>
                  <td><span class="badge text-bg-secondary"><?= e($r['status']) ?></span></td>
                  <td><?= e($r['fed_id']) ?></td>
                  <td class="table-actions">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()."/$entity/$r[id]/edit") ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url()."/$entity/$r[id]/delete") ?>" onclick="return confirm('Delete?')">Delete</a>
                  </td>
                <?php elseif ($entity==='events'): ?>
                  <td><?= e($r['id']) ?></td>
                  <td><?= e($r['title']) ?></td>
                  <td><?= e($r['event_date']) ?></td>
                  <td><?= e($r['fed_id']) ?></td>
                  <td><?= e($r['location']) ?></td>
                  <td class="table-actions">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()."/$entity/$r[id]/edit") ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url()."/$entity/$r[id]/delete") ?>" onclick="return confirm('Delete?')">Delete</a>
                  </td>
                <?php elseif ($entity==='matches'): ?>
                  <td><?= e($r['id']) ?></td>
                  <td><?= e($r['event_id']) ?></td>
                  <td><?= e($r['participants']) ?></td>
                  <td><?= e($r['result']) ?></td>
                  <td class="table-actions">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()."/$entity/$r[id]/edit") ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url()."/$entity/$r[id]/delete") ?>" onclick="return confirm('Delete?')">Delete</a>
                  </td>
                <?php elseif ($entity==='promos'): ?>
                  <td><?= e($r['id']) ?></td>
                  <td><?= e($r['member_id']) ?></td>
                  <td><?= e($r['event_id'] ?: '-') ?></td>
                  <td><?= e($r['created_at']) ?></td>
                  <td class="table-actions">
                    <?php if (auth_check($def['permissions']['edit'])): ?>
                      <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url()."/$entity/$r[id]/edit") ?>">Edit</a>
                      <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url()."/$entity/$r[id]/delete") ?>" onclick="return confirm('Delete?')">Delete</a>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
        $content = ob_get_clean();
        render_layout($def['title'].' • eFed CRM', $content);
        exit;
    }

    // Create
    if ($action === 'create') {
        auth_require_role($def['permissions']['create'] ?? 'member');

        $is_member_context = ($entity==='promos' && auth_user()['role']==='member');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            [$errors, $data] = validate($def, $_POST, 'create', $is_member_context);

            if ($entity === 'users') {
                if (empty($_POST['password']) || strlen((string)$_POST['password'])<8) $errors['password']='Min 8 chars';
            }

            if (!$errors) {
                if ($entity === 'users') {
                    model_user_create($data['name'], $data['email'], $data['role'], $_POST['password'], $data['member_id'] ?? null);
                } else {
                    if ($is_member_context) {
                        // Force member_id to linked member of current user
                        $linked = auth_user()['member_id'] ?? null;
                        if (!$linked) { flash('error','Your account is not linked to a member profile.'); redirect("$entity"); }
                        $data['member_id'] = $linked;
                    }
                    model_insert($def['table'], $data, $def['allowed']);
                }
                flash('success', 'Created.');
                redirect($entity);
            } else {
                $form = render_form($def, $_POST, 'create', $errors, $is_member_context);
            }
        }
        if (empty($form)) $form = render_form($def, [], 'create', null, $is_member_context);
        ob_start(); ?>
          <div class="col-lg-8">
            <h1 class="h4 mb-3">Create <?= e($def['title']) ?></h1>
            <?= $form ?>
          </div>
        <?php
        $content = ob_get_clean();
        render_layout('Create • '.$def['title'], $content);
        exit;
    }

    // Edit
    if ($action === 'edit' && $id) {
        auth_require_role($def['permissions']['edit'] ?? 'staff');

        $row = model_get($def['table'], $id);
        if (!$row) { http_response_code(404); die('Not found'); }

        // Special: users edit uses custom model for password
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            if ($entity === 'users') {
                $data = [
                    'name'=>trim($_POST['name'] ?? ''),
                    'email'=>trim($_POST['email'] ?? ''),
                    'role'=>trim($_POST['role'] ?? ''),
                    'member_id'=>($_POST['member_id'] === '' ? null : (int)$_POST['member_id']),
                ];
                $pwd = (string)($_POST['password'] ?? '');
                if ($pwd) $data['password'] = $pwd;
                model_user_update($id, $data);
            } else {
                [$errors,$data] = validate($def, $_POST, 'edit');
                if (!$errors) model_update($def['table'], $id, $data, $def['allowed']);
                else {
                    $form = render_form($def, $_POST, 'edit', $errors);
                }
            }
            if (empty($form)) { flash('success','Updated.'); redirect($entity); }
        }
        if (empty($form)) $form = render_form($def, $row, 'edit');
        ob_start(); ?>
          <div class="col-lg-8">
            <h1 class="h4 mb-3">Edit <?= e($def['title']) ?> #<?= e($id) ?></h1>
            <?= $form ?>
          </div>
        <?php
        $content = ob_get_clean();
        render_layout('Edit • '.$def['title'], $content);
        exit;
    }

    // Delete
    if ($action === 'delete' && $id) {
        auth_require_role($def['permissions']['delete'] ?? 'staff');
        model_delete($def['table'], $id);
        flash('success','Deleted.');
        redirect($entity);
    }

    http_response_code(404); echo 'Not found'; exit;
}

// Fallback 404 or redirect
http_response_code(404);
echo 'Not found';
