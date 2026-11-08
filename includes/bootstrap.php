<?php
/**
 * Shared bootstrap: session, database, security helpers and grading rules.
 * Every page includes this file first.
 */

require_once __DIR__ . '/../config.php';

/* ---------- Session ---------- */

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('ALIASGRADE');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ---------- Grading rules ---------- */

// Field => maximum mark (the maximum equals the weightage in %).
const MARK_LIMITS = [
    'test1'      => 20,
    'test2'      => 30,
    'final_exam' => 50,
];

const MARK_LABELS = [
    'test1'      => 'Test 1',
    'test2'      => 'Test 2',
    'final_exam' => 'Final Examination',
];

// Grade => [min, max] total marks (inclusive).
const GRADE_SCALE = [
    'A' => [80, 100],
    'B' => [60, 79],
    'C' => [40, 59],
    'D' => [30, 39],
    'F' => [0, 29],
];

function grade_for(float $total): string
{
    if ($total >= 80) return 'A';
    if ($total >= 60) return 'B';
    if ($total >= 40) return 'C';
    if ($total >= 30) return 'D';
    return 'F';
}

/** Format a stored mark: 18.00 -> "18", 18.50 -> "18.5". */
function fmt_mark($n): string
{
    if ($n === null || $n === '') return '';
    $s = number_format((float)$n, 2, '.', '');
    return rtrim(rtrim($s, '0'), '.');
}

/* ---------- Database ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Check config.php and make sure MySQL is running and database.sql has been imported.');
        }
    }
    return $pdo;
}

/* ---------- Output escaping ---------- */

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

/* ---------- Auth ---------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
    ];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']); // fresh token for the new session
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** True if logged in and the session has not timed out (refreshes the idle timer). */
function session_is_active(): bool
{
    if (!current_user()) return false;
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) return false;
    $_SESSION['last_activity'] = time();
    return true;
}

/** Block access to any page unless logged in. */
function require_login(): array
{
    if (!session_is_active()) {
        $expired = current_user() !== null;
        logout_user();
        session_start();
        if ($expired) flash('info', 'Your session expired. Please log in again.');
        redirect('login.php');
    }
    return current_user();
}

/* ---------- Misc helpers ---------- */

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---------- Students: validation ---------- */

/**
 * Validate student input. Returns [cleanData, errors].
 * $excludeId skips that record in the unique matric check (used when editing).
 */
function validate_student(array $in, ?int $excludeId = null): array
{
    $errors = [];
    $data   = [];

    $name = trim(preg_replace('/\s+/u', ' ', (string)($in['student_name'] ?? '')));
    if ($name === '') {
        $errors['student_name'] = 'Student name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors['student_name'] = 'Student name must be 100 characters or fewer.';
    } elseif (!preg_match("/^\\p{L}[\\p{L}\\p{M} .'\\/-]*$/u", $name)) {
        $errors['student_name'] = "Name may only contain letters, spaces and . ' / -";
    }
    $data['student_name'] = $name;

    $matric = strtoupper(trim((string)($in['matric_no'] ?? '')));
    if ($matric === '') {
        $errors['matric_no'] = 'Matric number is required.';
    } elseif (!preg_match('/^[A-Z0-9\/-]{3,20}$/', $matric)) {
        $errors['matric_no'] = 'Matric number must be 3-20 characters: letters, digits, / or -.';
    } elseif (matric_exists($matric, $excludeId)) {
        $errors['matric_no'] = 'This matric number is already registered.';
    }
    $data['matric_no'] = $matric;

    $total = 0.0;
    foreach (MARK_LIMITS as $field => $max) {
        $raw   = trim((string)($in[$field] ?? ''));
        $label = MARK_LABELS[$field];
        if ($raw === '') {
            $errors[$field] = "$label is required.";
        } elseif (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $raw)) {
            $errors[$field] = "$label must be a number from 0 to $max (up to 2 decimal places).";
        } elseif ((float)$raw > $max) {
            $errors[$field] = "$label cannot be more than $max.";
        } else {
            $data[$field] = round((float)$raw, 2);
            $total += $data[$field];
        }
    }

    if (!$errors) {
        $data['total'] = round($total, 2);
        $data['grade'] = grade_for($data['total']);
    }
    return [$data, $errors];
}

function matric_exists(string $matric, ?int $excludeId = null): bool
{
    $st = db()->prepare('SELECT 1 FROM students WHERE matric_no = :m AND id <> :id LIMIT 1');
    $st->execute([':m' => $matric, ':id' => $excludeId ?? 0]);
    return (bool)$st->fetchColumn();
}

/* ---------- Students: listing (search / filter / sort) ---------- */

const SORT_COLUMNS = [
    'name'    => 'student_name',
    'matric'  => 'matric_no',
    'test1'   => 'test1',
    'test2'   => 'test2',
    'final'   => 'final_exam',
    'total'   => 'total',
    'grade'   => 'grade',
    'created' => 'created_at',
];

/** Read and whitelist list filters from a request array (usually $_GET). */
function student_filters(array $src): array
{
    $q     = trim((string)($src['q'] ?? ''));
    $grade = strtoupper((string)($src['grade'] ?? ''));
    $sort  = (string)($src['sort'] ?? 'created');
    $dir   = strtolower((string)($src['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

    if (!isset(GRADE_SCALE[$grade])) $grade = '';
    if (!isset(SORT_COLUMNS[$sort])) $sort = 'created';
    if (mb_strlen($q) > 100) $q = mb_substr($q, 0, 100);

    return ['q' => $q, 'grade' => $grade, 'sort' => $sort, 'dir' => $dir];
}

function fetch_students(array $f): array
{
    $where  = [];
    $params = [];

    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where[] = '(student_name LIKE :q1 OR matric_no LIKE :q2)';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
    }
    if ($f['grade'] !== '') {
        $where[] = 'grade = :grade';
        $params[':grade'] = $f['grade'];
    }

    // Column and direction both come from whitelists, never raw input.
    $sql = 'SELECT * FROM students'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY ' . SORT_COLUMNS[$f['sort']] . ' ' . strtoupper($f['dir']) . ', id DESC';

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
