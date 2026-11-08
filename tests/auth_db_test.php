<?php
// Auth::register / Auth::login against a real database: bcrypt on register,
// old SHA3 hashes upgraded at login, wrong password and disabled account.
//   DBTEST=1 DBHOST=127.0.0.1 DBUSER=root DBNAME=the_test php tests/auth_db_test.php
// (DBDRIVER=postgres for PostgreSQL.)

namespace {
    foreach (['Req', 'Response', 'DB', 'Model', 'Password', 'Sessions', 'Auth'] as $f) {
        require __DIR__ . "/../src/$f.php";
    }
}

namespace App\Model {
    class Role extends \The\Model
    {
        public $model = ['id', 'name'];
        public $name = 'role';
        protected $table = 'the_p_roles';
        protected $fillable = ['name'];
    }
    class Active_role extends \The\Model
    {
        public $model = ['id', 'user_id', 'role_id'];
        public $name = 'active_role';
        protected $table = 'the_p_active_roles';
        protected $fillable = ['user_id', 'role_id'];
        protected $relations = ['role' => ['table' => 'the_p_roles', 'name' => 'role_id', 'key' => 'id', 'callback' => Role::class]];
    }
    class User extends \The\Model
    {
        public $model = ['id', 'name', 'email', 'password', 'enable'];
        public $name = 'user';
        protected $table = 'the_p_users';
        protected $fillable = ['name', 'email', 'password', 'enable'];
    }
}

namespace {
    if (getenv('DBTEST') !== '1') {
        echo "skipped (set DBTEST=1)\n";
        exit(0);
    }
    define('secure', false);
    define('sslhost', '');
    define('httponly', true);
    define('samesite', 'Lax');

    use The\{Auth, DB, Password};
    use App\Model\User;

    $failed = 0;
    function check(string $name, $got, $want): void
    {
        global $failed;
        if ($got === $want) {
            echo "ok   $name\n";
        } else {
            $failed++;
            echo "FAIL $name\n     got:  " . json_encode($got) . "\n     want: " . json_encode($want) . "\n";
        }
    }
    /** Run an Auth action with $_POST; returns [status, decoded body]. */
    function call(callable $action, array $post): array
    {
        $_POST = $post;
        http_response_code(200);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        @session_start();
        $body = @$action();
        return [http_response_code(), json_decode((string) $body, true)];
    }

    $pg = DB::driver() === 'postgres';
    $serial = $pg ? 'bigserial' : 'bigint unsigned AUTO_INCREMENT';
    foreach (['the_p_active_roles', 'the_p_roles', 'the_p_users'] as $t) {
        DB::raw("DROP TABLE IF EXISTS $t");
    }
    DB::raw("CREATE TABLE the_p_users (id $serial PRIMARY KEY, name varchar(100) NOT NULL DEFAULT '', email varchar(100) NOT NULL UNIQUE, password varchar(200) NULL, enable smallint NOT NULL DEFAULT 1)");
    DB::raw("CREATE TABLE the_p_roles (id $serial PRIMARY KEY, name varchar(50) NOT NULL)");
    DB::raw("CREATE TABLE the_p_active_roles (id $serial PRIMARY KEY, user_id bigint NOT NULL, role_id bigint NOT NULL)");
    ob_start();
    try {
        User::create(['name' => 'Root', 'email' => 'root@x.in', 'password' => Password::hash('r00t')]);

        [$s, $b] = call([Auth::class, 'register'], ['name' => 'Alice', 'email' => 'alice@x.in', 'password' => 'pa55']);
        check('register', [$s, $b['email'] ?? null], [200, 'alice@x.in']);
        $stored = User::find('alice@x.in', 'email')->array()['password'];
        check('register stores bcrypt cost 10', substr($stored, 0, 7), '$2y$10$');
        [$s] = call([Auth::class, 'register'], ['email' => 'long@x.in', 'password' => str_repeat('x', 73)]);
        check('register refuses over 72 bytes', $s, 422);
        [$s] = call([Auth::class, 'register'], ['email' => 'alice@x.in', 'password' => 'x']);
        check('email taken', $s, 422);

        [$s, $b] = call([Auth::class, 'login'], ['email' => 'alice@x.in', 'password' => 'pa55']);
        check('login', [$s, $b['email'] ?? null], [200, 'alice@x.in']);
        [$s, $b] = call([Auth::class, 'login'], ['email' => 'alice@x.in', 'password' => 'wrong']);
        check('wrong password is 401 (it was 404 User Not Found)', [$s, $b], [401, 'Password is Incorrect']);
        [$s, $b] = call([Auth::class, 'login'], ['email' => 'nobody@x.in', 'password' => 'x']);
        check('unknown user', [$s, $b], [404, 'User Not Found']);

        User::create(['name' => 'Carol', 'email' => 'carol@x.in', 'password' => Password::hash('c'), 'enable' => 0]);
        [$s, $b] = call([Auth::class, 'login'], ['email' => 'carol@x.in', 'password' => 'c']);
        check('disabled account', [$s, $b], [403, 'This account has been disabled']);

        $spec = json_decode(file_get_contents(__DIR__ . '/../spec/auth_cases.json'), true);
        foreach (['old SHA3-256 hex (the_lib)' => 'dave', 'old SHA3-256 base64 (the_deno)' => 'erin', 'bcrypt from Go ($2a$)' => 'gus', 'bcrypt from Deno ($2a$)' => 'dina'] as $case => $who) {
            $stored = array_values(array_filter($spec['verify'], fn ($c) => $c['name'] === $case))[0]['stored'];
            User::create(['name' => $who, 'email' => "$who@x.in", 'password' => $stored]);
            [$s] = call([Auth::class, 'login'], ['email' => "$who@x.in", 'password' => $spec['password']]);
            $after = User::find("$who@x.in", 'email')->array()['password'];
            $upgraded = str_starts_with($stored, '$2') ? $after === $stored : str_starts_with($after, '$2y$10$');
            check("$case logs in" . (str_starts_with($stored, '$2') ? ', hash kept' : ', upgraded to bcrypt'), [$s, $upgraded], [200, true]);
        }
        [$s] = call([Auth::class, 'login'], ['email' => 'dave@x.in', 'password' => $spec['password']]);
        check('the upgraded account logs in again', $s, 200);
    } finally {
        ob_end_clean();
        foreach (['the_p_active_roles', 'the_p_roles', 'the_p_users'] as $t) {
            DB::raw("DROP TABLE IF EXISTS $t");
        }
    }
    echo ($pg ? 'PostgreSQL' : 'MySQL') . ': ' . ($failed ? "$failed failed\n" : "all passed\n");
    exit($failed ? 1 : 0);
}
