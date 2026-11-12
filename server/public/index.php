<?php
declare(strict_types=1);
require is_file(__DIR__.'/../src/Database.php') ? __DIR__.'/../src/Database.php' : '/opt/orbit/src/Database.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function fail(int $code,string $message): never {http_response_code($code);echo json_encode(['error'=>$message]);exit;}
function respond(mixed $data,int $code=200): never {http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
function input(): array {$raw=file_get_contents('php://input');$data=json_decode($raw,true);if(!is_array($data)) fail(400,'Expected a JSON object.');return $data;}
function validText(mixed $v,int $max,bool $required=false): string {if(!is_string($v)) fail(422,'Invalid text field.');$v=trim($v);if(($required&&$v==='')||mb_strlen($v)>$max) fail(422,'Text is empty or too long.');return $v;}
function positiveId(string $value): int {if(!ctype_digit($value)||(int)$value<1) fail(404,'Not found.');return (int)$value;}
function workspace(PDO $db): array {
  $token=$_COOKIE['orbit_demo']??'';
  if(is_string($token)&&preg_match('/^[a-f0-9]{64}$/',$token)){
    $stmt=$db->prepare('SELECT id, token_hash FROM workspaces WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()');$stmt->execute([hash('sha256',$token)]);
    $row=$stmt->fetch();if($row)return $row;
  }
  $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
  $stmt=$db->prepare('INSERT INTO workspaces(token_hash,expires_at) VALUES (?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))');$stmt->execute([$hash]);$id=(int)$db->lastInsertId();
  $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off') || getenv('RAILWAY_ENVIRONMENT_NAME')!==false;
  setcookie('orbit_demo',$token,['expires'=>time()+86400,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
  $samples=[['Website Redesign','Refresh content, navigation and accessibility'],['Mobile App','Plan and develop a companion experience'],['Marketing Launch','Organize launch activities and assets']];
  $p=$db->prepare('INSERT INTO projects(workspace_id,name,description) VALUES (?,?,?)');$t=$db->prepare('INSERT INTO tasks(project_id,title,description,status,priority) VALUES (?,?,?,?,?)');
  foreach($samples as $i=>$sample){$p->execute([$id,...$sample]);$pid=(int)$db->lastInsertId();foreach([['Review requirements','Confirm scope and acceptance criteria','todo','high'],['Build initial interface','Develop responsive screens','progress','medium'],['Set up project structure','Prepare application foundation','done','low']] as $task){$t->execute([$pid,...$task]);}}
  return ['id'=>$id,'token_hash'=>$hash];
}
try{
  $db=db();$method=$_SERVER['REQUEST_METHOD']??'GET';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
  if($path==='/api/health'){respond(['status'=>'ok']);}
  if(!str_starts_with($path,'/api/'))fail(404,'Not found.');
  if(random_int(1,100)===1){$db->exec('DELETE FROM workspaces WHERE expires_at < UTC_TIMESTAMP()');}
  $ws=workspace($db);$wid=(int)$ws['id'];
  // Derive a CSRF secret from the existing workspace token, rather than expose the cookie.
  $csrf=hash_hmac('sha256','orbit-csrf-v1',getenv('CSRF_SECRET') ?: 'dev-only-change-me-'.$ws['token_hash']);
  if($method!=='GET' && !hash_equals($csrf,(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) fail(403,'Invalid CSRF token. Refresh the page.');
  if($method==='GET'&&$path==='/api/bootstrap') respond(['csrfToken'=>$csrf,'demoExpiresHours'=>24]);
  if($method==='GET'&&$path==='/api/projects'){$s=$db->prepare('SELECT id,name,description,created_at FROM projects WHERE workspace_id=? ORDER BY id DESC');$s->execute([$wid]);respond(['projects'=>$s->fetchAll()]);}
  if($method==='POST'&&$path==='/api/projects'){$body=input();$name=validText($body['name']??null,100,true);$description=validText($body['description']??'',500);$c=$db->prepare('SELECT COUNT(*) FROM projects WHERE workspace_id=?');$c->execute([$wid]);if((int)$c->fetchColumn()>=10)fail(422,'Demo limit: 10 projects per workspace.');$s=$db->prepare('INSERT INTO projects(workspace_id,name,description) VALUES(?,?,?)');$s->execute([$wid,$name,$description]);respond(['id'=>(int)$db->lastInsertId()],201);}
  if(preg_match('#^/api/projects/(\d+)/tasks$#',$path,$m)){
    $pid=positiveId($m[1]);$o=$db->prepare('SELECT id FROM projects WHERE id=? AND workspace_id=?');$o->execute([$pid,$wid]);if(!$o->fetch())fail(404,'Project not found.');
    if($method==='GET'){$s=$db->prepare('SELECT id,title,description,status,priority,due_date FROM tasks WHERE project_id=? ORDER BY id DESC');$s->execute([$pid]);respond(['tasks'=>$s->fetchAll()]);}
    if($method==='POST'){$body=input();$title=validText($body['title']??null,140,true);$description=validText($body['description']??'',1000);$priority=$body['priority']??'medium';$due=$body['due_date']??null;if(!in_array($priority,['low','medium','high'],true))fail(422,'Invalid priority.');if($due!==null&&(!is_string($due)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$due)||!checkdate((int)substr($due,5,2),(int)substr($due,8,2),(int)substr($due,0,4))))fail(422,'Invalid due date.');$c=$db->prepare('SELECT COUNT(*) FROM tasks WHERE project_id=?');$c->execute([$pid]);if((int)$c->fetchColumn()>=150)fail(422,'Demo limit: 150 tasks per project.');$s=$db->prepare('INSERT INTO tasks(project_id,title,description,priority,due_date) VALUES (?,?,?,?,?)');$s->execute([$pid,$title,$description,$priority,$due]);respond(['id'=>(int)$db->lastInsertId()],201);}
  }
  if($method==='PATCH'&&preg_match('#^/api/tasks/(\d+)$#',$path,$m)){$id=positiveId($m[1]);$body=input();$status=$body['status']??null;if(!in_array($status,['todo','progress','done'],true))fail(422,'Invalid status.');$s=$db->prepare('UPDATE tasks t INNER JOIN projects p ON p.id=t.project_id SET t.status=? WHERE t.id=? AND p.workspace_id=?');$s->execute([$status,$id,$wid]);$exists=$db->prepare('SELECT t.id FROM tasks t JOIN projects p ON p.id=t.project_id WHERE t.id=? AND p.workspace_id=?');$exists->execute([$id,$wid]);if(!$exists->fetch())fail(404,'Task not found.');respond(['updated'=>true]);}
  fail(404,'Endpoint not found.');
}catch(PDOException $e){error_log($e->getMessage());fail(503,'Database unavailable. Check deployment configuration.');}
