<?php
$base_url = 'http://localhost/piki/';
$admin_name = 'admin';
$admin_password = 'password';

$db = null;

function logger($message) {
  error_log(date('Y-m-d H:i:s') . ': ' . $message . "\n", 3, 'piki.log');
}

function init_db($db_file_name) {
  global $db;
  $need_init_db = !file_exists($db_file_name);
  $dsn = 'sqlite:' . $db_file_name;
  $db = new PDO($dsn);
  if (!$db) {
      die('failed to open sqlite.');
  }
  if ($need_init_db) {
    $sql = <<<SQL
create table pages (
  id integer primary key,
  title varchar(255) unique,
  body text,
  created_at timestamp default current_timestamp
)
SQL;
    $result = $db->query($sql);

    $sql = <<<SQL
insert into pages
    (id, title, body)
  values
    (1, 'index', '...')
SQL;
    $result = $db->query($sql);
  }
}

function insert($title, $body) {
  global $db;
  if ($title == null) {
    return false;
  }
  $stmt = $db->prepare('insert into pages (title, body) values (:title, :body)');
  $stmt->execute(array(':title' => $title, ':body' => $body));
  return $db->lastInsertId();
}

function update($id, $title, $body) {
  global $db;
  $stmt = $db->prepare('update pages set title = :title, body = :body where id = :id');
  return $stmt->execute(array(':id' => $id, ':title' => $title, ':body' => $body));
}

function delete($id) {
  global $db;
  if ($id != '1') {
    $stmt = $db->prepare('delete from pages where id = :id');
    return $stmt->execute(array(':id' => $id));
  }
}

function select($id) {
  global $db;
  $stmt = $db->prepare('select * from pages where id = :id');
  $stmt->execute(array(':id' => $id));
  return $stmt->fetch();
}

function select_all() {
  global $db;
  return $db->query('select id, title from pages order by created_at desc');
}

function login() {
  global $admin_name, $admin_password;
  if (!isset($_SERVER["PHP_AUTH_USER"]) || ($_SERVER["PHP_AUTH_USER"] != $admin_name || $_SERVER["PHP_AUTH_PW"] != $admin_password)) {
    header("WWW-Authenticate: Basic realm=\"Please Enter Your Password\"");
    header("HTTP/1.0 401 Unauthorized");
    echo "Authorization Required";
    exit;
  }
}

function post() {
  global $base_url;

  $action = $_POST['action'];
  if ($action == 'new') {
    $id = insert($_POST['title'], $_POST['body']);
    header("Location: " . $base_url . "?id=" . $id);
  } else if ($action == 'edit') {
    update($_POST['id'], $_POST['title'], $_POST['body']);
    header("Location: " . $base_url . "?id=" . $_POST['id']);
  } else if ($action == 'delete') {
    delete($_POST['id']);
    header("Location: " . $base_url);
  }
}

function get() {
  global $base_url;

  $id = $_GET['id'];
  $action = $_GET['action'];

  $edit_link = '';
  $contents = '';
  $title = '';
  $page_link = '';

  if ($action == 'edit') {
    login();
    if (!$id) {
      $id = 1;
    }
    $piki_page = select($id);
    if (!$piki_page) {
      header("Location: " . $base_url . "?action=new");
      exit;
    }
    $title = htmlspecialchars($piki_page['title']);
    $body = htmlspecialchars($piki_page['body']);
    $cansel_url = $base_url . '?id=' . $id;
    $title_read_only = '';
    $contents = <<<EOS
      <form method="post">
        <input type="text" name="title" value="$title" size="30" /><br />
        <textarea name="body" rows=20 cols=80>$body</textarea><br />
        <input type="hidden" name="id" value="$id" />
        <input type="hidden" name="action" value="edit" />
        <input type="submit" value="Save" />
        <a href="$cansel_url">Cansel</a>
      </form>
EOS;
    if ($id != 1) {
      $contents .= <<<EOS
      <form method="post" onSubmit="return confirm('Are you sure?')">
        <input type="hidden" name="id" value="$id" />
        <input type="hidden" name="action" value="delete" />
        <input type="submit" value="Delete" />
      </form>
EOS;
    }
  } else if ($action == 'new') {
    login();
    $cansel_url = $base_url;
    $contents = <<<EOS
      <form method="post">
        <input type="text" name="title" value="$title" size="30" /><br />
        <textarea name="body" rows=20 cols=80></textarea><br />
        <input type="submit" value="Save" />
        <input type="hidden" name="action" value="new" />
        <a href="$cansel_url">Cansel</a>
      </form>
EOS;
  } else {
    if (!$id) {
      $id = 1;
    }
    $piki_page = select($id);
    if (!$piki_page) {
      header("Location: " . $base_url . "?action=new");
      exit;
    }
    $title = htmlspecialchars($piki_page['title']);
    $page_link = "<a href=\"$base_url?id=$id\">$title</a>";
    $edit_link = "<a class=\"action\" href=\"$base_url?id=$id&action=edit\">edit</a>";
    $contents = '<h2>' . $page_link . '</h2>' . $edit_link;
    $contents .= '<pre>' . htmlspecialchars($piki_page['body']) . '</pre>';
  }
  
  $piki_pages = select_all();
  $list .= '<ul>';
  foreach ($piki_pages as $piki_page) {
    $page_id = $piki_page['id'];
    $page_title = htmlspecialchars($piki_page['title']);
    if ($id == $page_id) {
      $list .= "<li class=\"current_page\">&gt; <a href=\"$base_url?id=$page_id\">$page_title</a></li>";
    } else {
      $list .= "<li><a href=\"$base_url?id=$page_id\">$page_title</a></li>";
    }
  }
  $list .= '</ul>';

  echo <<<EOS
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style type="text/css">
      #header {
        padding: 6px 0;
      }
      #header h1 {
        display: inline;
        margin: 4px 0;
      }
      #header h1 a {
        color: #FF00AE;
      }
      #page {
        border-left: dotted 1px silver;
        padding:6px 12px;
        float: left;
        width: 80%;
      }
      #page h2 {
        display: inline;
        margin: 0 0 4px 0;
      }
      #page h2 a {
        color: black;
      }
      #sidebar {
        float: left;
        width: 16%;
      }
      #footer {
        clear: both;
      }
      #sidebar ul {
        padding: 0;
        margin: 16px 0;
      }
      #sidebar li {
        margin: 1px 0;
        padding: 1px 2px;
        list-style-type: none;
      }
      #sidebar li.current_page {
        color: black;
      }
      #sidebar li.current_page a {
        color: black;
      }
      #contents {
        margin: 0;
      }
      a {
        color: #0063F7;
      }
      a.action  {
        margin: 0 8px;
        color: #CCCCCC;
      }
      a.action:hover {
        color: #0063F7;
      }
    </style>
    <title>$title - Piki</title>
  </head>
  <body>
    <div id="header">
      <h1><a href="$base_url">Piki</a></h1>
      <a class="action" href="$base_url?action=new">new</a>
    </div>
    <div id="sidebar">
      $list
    </div>
    <div id="page">
      <div id="contents">$contents</div>
    </div>
    <div id="footer"></div>
  </body>
</html>
EOS;
}

init_db('piki.db');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  post();
  exit;
} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
  get();
}
?>