<?php
// Student Study Planner - Single-file PHP app using SQLite (PDO)
// Save this file as student-study-planner.php and place in a PHP-enabled folder.
// Run with built-in server: php -S localhost:8000
// Open http://localhost:8000/student-study-planner.php

// --- Configuration ---
$dbFile = __DIR__ . '/study_planner.sqlite';

// --- Database setup ---
$init = !file_exists($dbFile);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if ($init) {
    $pdo->exec("CREATE TABLE subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        color TEXT DEFAULT '#88b4ff'
    )");

    $pdo->exec("CREATE TABLE tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subject_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        due_date TEXT,
        estimated_minutes INTEGER DEFAULT 30,
        completed INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(subject_id) REFERENCES subjects(id)
    )");

    $pdo->exec("CREATE TABLE sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER,
        start_at TEXT,
        end_at TEXT,
        notes TEXT,
        FOREIGN KEY(task_id) REFERENCES tasks(id)
    )");
}

// --- Helpers ---
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }

// --- Handle form submissions ---
$action = $_REQUEST['action'] ?? null;
if ($action === 'add_subject') {
    $name = trim($_POST['name']);
    $color = preg_replace('/[^#A-Fa-f0-9]/', '', $_POST['color'] ?? '#88b4ff');
    if ($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO subjects (name,color) VALUES (?,?)');
        $stmt->execute([$name, $color]);
    }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}
if ($action === 'add_task') {
    $title = trim($_POST['title']);
    $subject_id = $_POST['subject_id'] ?: null;
    $desc = trim($_POST['description'] ?? '');
    $due = $_POST['due_date'] ?: null;
    $mins = intval($_POST['estimated_minutes'] ?: 30);
    if ($title !== '') {
        $stmt = $pdo->prepare('INSERT INTO tasks (subject_id,title,description,due_date,estimated_minutes) VALUES (?,?,?,?,?)');
        $stmt->execute([$subject_id, $title, $desc, $due, $mins]);
    }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}
if ($action === 'toggle_complete') {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('UPDATE tasks SET completed = 1 - completed WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}
if ($action === 'delete_task') {
    $id = intval($_GET['id']);
    $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}
if ($action === 'add_session') {
    $task_id = intval($_POST['task_id']);
    $start = $_POST['start_at'] ?: null;
    $end = $_POST['end_at'] ?: null;
    $notes = trim($_POST['notes'] ?? '');
    if ($task_id && $start && $end) {
        $pdo->prepare('INSERT INTO sessions (task_id,start_at,end_at,notes) VALUES (?,?,?,?)')
            ->execute([$task_id, $start, $end, $notes]);
    }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// --- Data retrieval for display ---
$subjects = $pdo->query('SELECT * FROM subjects ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$tasks = $pdo->query('SELECT t.*, s.name as subject_name, s.color as subject_color FROM tasks t LEFT JOIN subjects s ON t.subject_id=s.id ORDER BY due_date IS NULL, due_date ASC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

// upcoming sessions (next 7 days)
$today = new DateTimeImmutable('today');
$seven = $today->modify('+7 days')->format('Y-m-d');
$stmt = $pdo->prepare('SELECT se.*, t.title as task_title FROM sessions se LEFT JOIN tasks t ON se.task_id=t.id WHERE date(start_at) BETWEEN ? AND ? ORDER BY start_at');
$stmt->execute([$today->format('Y-m-d'), $seven]);
sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no subjects exist, add a default one
if (count($subjects) === 0) {
    $pdo->prepare('INSERT INTO subjects (name,color) VALUES (?,?)')->execute(['General', '#88b4ff']);
    $subjects = $pdo->query('SELECT * FROM subjects ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}

// --- Simple utility for week calendar ---
function week_days($startOfWeek = null) {
    $start = $startOfWeek ? new DateTimeImmutable($startOfWeek) : new DateTimeImmutable('monday this week');
    $days = [];
    for ($i=0;$i<7;$i++) {
        $d = $start->modify("+$i day");
        $days[] = $d;
    }
    return $days;
}
$week = week_days();

?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Study Planner</title>
<style>
:root{--bg:#f7f9fc;--card:#fff;--muted:#667;}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial;margin:16px;background:var(--bg);color:#222}
.container{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:360px 1fr;gap:16px}
.card{background:var(--card);border-radius:10px;padding:14px;box-shadow:0 6px 18px rgba(20,30,60,0.06)}
h2{margin:4px 0 12px;font-size:18px}
.form-row{display:flex;gap:8px;margin-bottom:8px}
.input,textarea,select{width:100%;padding:8px;border-radius:6px;border:1px solid #e0e6ef}
.btn{padding:8px 12px;border-radius:8px;border:0;background:#2d7dff;color:#fff;cursor:pointer}
.small{font-size:13px;color:var(--muted)}
.task{display:flex;justify-content:space-between;align-items:center;padding:8px;border-radius:8px;margin-bottom:8px;border:1px solid #eef3ff}
.task .left{display:flex;gap:8px;align-items:center}
.color-bullet{width:12px;height:12px;border-radius:3px}
.task .meta{font-size:12px;color:#556}
.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}
.day{background:#fbfdff;border-radius:8px;padding:8px;min-height:90px}
.session{font-size:12px;padding:6px;border-radius:6px;margin-top:6px;border:1px solid #e6eefc}
.footer{margin-top:12px;font-size:13px;color:#666}
</style>
</head>
<body>
<div class="container">
  <div>
    <div class="card">
      <h2>Add Subject</h2>
      <form method="post">
        <input type="hidden" name="action" value="add_subject">
        <div class="form-row">
          <input class="input" type="text" name="name" placeholder="Subject name" required>
          <input class="input" type="color" name="color" value="#88b4ff">
        </div>
        <button class="btn">Add Subject</button>
      </form>
    </div>

    <div class="card" style="margin-top:12px">
      <h2>Add Task</h2>
      <form method="post">
        <input type="hidden" name="action" value="add_task">
        <div class="form-row">
          <select name="subject_id" class="input">
            <option value="">-- Select subject --</option>
            <?php foreach($subjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <input class="input" type="text" name="title" placeholder="Task title" required>
        </div>
        <div style="margin-bottom:8px"><textarea class="input" name="description" rows="3" placeholder="Notes or description"></textarea></div>
        <div class="form-row">
          <input class="input" type="date" name="due_date">
          <input class="input" type="number" name="estimated_minutes" placeholder="Est. minutes" min="5" value="30">
        </div>
        <button class="btn">Add Task</button>
      </form>
    </div>

    <div class="card" style="margin-top:12px">
      <h2>Quick Session (log)</h2>
      <form method="post">
        <input type="hidden" name="action" value="add_session">
        <div class="form-row">
          <select name="task_id" class="input" required>
            <option value="">-- Select task --</option>
            <?php foreach($tasks as $t): ?>
              <option value="<?= $t['id'] ?>"><?= h($t['title']) ?> <?php if($t['due_date']): ?>(due <?= h($t['due_date']) ?>)<?php endif; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <input class="input" type="datetime-local" name="start_at" required>
          <input class="input" type="datetime-local" name="end_at" required>
        </div>
        <div style="margin-bottom:8px"><input class="input" name="notes" placeholder="Session notes"></div>
        <button class="btn">Log Session</button>
      </form>
    </div>

    <div class="card" style="margin-top:12px">
      <h2>Subjects</h2>
      <?php foreach($subjects as $s): ?>
        <div class="task">
          <div class="left">
            <div class="color-bullet" style="background:<?= h($s['color']) ?>"></div>
            <div>
              <div><strong><?= h($s['name']) ?></strong></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <div>
    <div class="card">
      <h2>Tasks</h2>
      <?php if(count($tasks)===0): ?>
        <div class="small">No tasks yet — add one on the left.</div>
      <?php endif; ?>
      <?php foreach($tasks as $t): ?>
        <div class="task">
          <div class="left">
            <div class="color-bullet" style="background:<?= h($t['subject_color'] ?: '#ddd') ?>"></div>
            <div>
              <div><strong><?= h($t['title']) ?></strong> <span class="small"><?= $t['subject_name'] ? ' · '.h($t['subject_name']) : '' ?></span></div>
              <div class="meta">Est: <?= intval($t['estimated_minutes']) ?> min · <?= $t['due_date'] ? 'Due '.h($t['due_date']) : 'No due date' ?> · Created <?= h($t['created_at']) ?></div>
            </div>
          </div>
          <div style="text-align:right">
            <div style="margin-bottom:6px">
              <a class="small" href="?action=toggle_complete&id=<?= $t['id'] ?>">[<?= $t['completed'] ? 'Undo' : 'Complete' ?>]</a>
              &nbsp;
              <a class="small" href="?action=delete_task&id=<?= $t['id'] ?>" onclick="return confirm('Delete task?')">[Delete]</a>
            </div>
            <?php if($t['completed']): ?>
              <div class="small">✅ Completed</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="margin-top:10px">
        <h3 style="margin:6px 0">Study Sessions (Next 7 days)</h3>
        <?php if(count($sessions) === 0): ?>
          <div class="small">No sessions planned or logged for the next 7 days.</div>
        <?php else: ?>
          <?php foreach($sessions as $se): ?>
            <div class="session">
              <strong><?= h($se['task_title'] ?: 'Task #' . $se['task_id']) ?></strong>
              <div class="small"><?php
                echo date('Y-m-d H:i', strtotime($se['start_at'])) . ' — ' . date('Y-m-d H:i', strtotime($se['end_at']));
              ?></div>
              <?php if($se['notes']): ?><div class="small">Notes: <?= h($se['notes']) ?></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

    <div class="card" style="margin-top:12px">
      <h2>Week View</h2>
      <div class="calendar">
        <?php foreach($week as $day): ?>
          <?php $dkey = $day->format('Y-m-d'); ?>
          <div class="day">
            <strong><?= $day->format('D') ?></strong>
            <div class="small"><?= $day->format('Y-m-d') ?></div>
            <?php
              $stmt = $pdo->prepare('SELECT se.*, t.title as task_title FROM sessions se LEFT JOIN tasks t ON se.task_id=t.id WHERE date(start_at)=? ORDER BY start_at');
              $stmt->execute([$dkey]);
              $daySessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php foreach($daySessions as $ds): ?>
              <div class="session">
                <div><strong><?= h($ds['task_title'] ?: 'Task') ?></strong></div>
                <div class="small"><?= date('H:i', strtotime($ds['start_at'])) ?> — <?= date('H:i', strtotime($ds['end_at'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="footer">Tips: break tasks into 25–50 minute focused sessions, schedule them on the calendar, review progress weekly.</div>
    </div>

  </div>
</div>

</body>
</html>
