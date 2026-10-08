<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class AdminUser extends BaseController
{
    private const ROLES = ['ADMIN', 'PRODI', 'KEUANGAN'];
    private BaseConnection $db;

    public function initController($request, $response, $logger): void { parent::initController($request, $response, $logger); $this->db = db_connect(); }

    public function index(): string
    {
        $programs = $this->db->tableExists('study_programs') ? $this->db->table('study_programs')->select('id,code,name')->where('is_active', 1)->orderBy('code')->get()->getResultArray() : [];
        return view('pages/admin/users', ['live'=>true,'activeMenu'=>'admin-users','pageTitle'=>'Pengguna Sistem','pageSubtitle'=>'Akun administrator, keuangan, dan operator Prodi','programs'=>$programs,'roles'=>self::ROLES,'csrfHeader'=>config('Security')->headerName,'csrfHash'=>csrf_hash()]);
    }

    public function read(): ResponseInterface
    {
        try {
            $users = $this->db->table('users u')->select('u.id,u.username,u.email,u.full_name,u.role,u.is_active,u.last_login_at,u.created_at,u.updated_at')->whereIn('u.role', self::ROLES)->orderBy('u.full_name')->get()->getResultArray();
            if ($this->db->tableExists('user_study_programs')) {
                $scopes = $this->db->table('user_study_programs usp')->select('usp.user_id,sp.id,sp.code,sp.name')->join('study_programs sp','sp.id=usp.study_program_id')->orderBy('sp.code')->get()->getResultArray(); $byUser=[];
                foreach ($scopes as $scope) $byUser[(int)$scope['user_id']][]=['id'=>(int)$scope['id'],'code'=>$scope['code'],'name'=>$scope['name']];
                foreach ($users as &$user) $user['programs']=$byUser[(int)$user['id']]??[]; unset($user);
            }
            return $this->ok($users);
        } catch (Throwable $e) { return $this->err($e); }
    }

    public function create(): ResponseInterface
    {
        try { $input=$this->payload(); $data=$this->clean($input,true); $now=date('Y-m-d H:i:s'); $this->db->transStart(); $this->db->table('users')->insert([...$data,'password_hash'=>password_hash((string)$input['password'],PASSWORD_DEFAULT),'created_at'=>$now,'updated_at'=>$now]); $id=(int)$this->db->insertID(); $this->syncPrograms($id,$data['role'],$input['study_program_ids']??[]); $created=$this->byId($id); $this->audit($data['role'].'_USER_CREATED',$id,null,$created); $this->db->transComplete(); if(!$this->db->transStatus())throw new RuntimeException('Pengguna belum dapat dibuat.'); return $this->ok($created,'Pengguna berhasil dibuat.',201); } catch(Throwable $e){return $this->err($e);}
    }

    public function update(int $id): ResponseInterface
    {
        try { $old=$this->byId($id); if(!$old)throw new RuntimeException('Pengguna tidak ditemukan.'); $input=$this->payload(); $data=$this->clean($input,false,$id); $auth=session('auth'); if(is_array($auth)&&(int)($auth['id']??0)===$id&&!$data['is_active'])throw new RuntimeException('Akun yang sedang digunakan tidak dapat dinonaktifkan.'); $this->db->transStart(); $this->db->table('users')->where('id',$id)->update([...$data,'updated_at'=>date('Y-m-d H:i:s')]); $this->syncPrograms($id,$data['role'],$input['study_program_ids']??[]); $updated=$this->byId($id); $this->audit('USER_UPDATED',$id,$old,$updated); $this->db->transComplete(); if(!$this->db->transStatus())throw new RuntimeException('Pengguna belum dapat diperbarui.'); return $this->ok($updated,'Pengguna berhasil diperbarui.'); } catch(Throwable $e){return $this->err($e);}
    }

    public function resetPassword(int $id): ResponseInterface
    {
        try { if(!$this->byId($id))throw new RuntimeException('Pengguna tidak ditemukan.'); $password=(string)($this->payload()['password']??''); if(strlen($password)<8)throw new RuntimeException('Kata sandi minimal 8 karakter.'); $this->db->table('users')->where('id',$id)->update(['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'updated_at'=>date('Y-m-d H:i:s')]); $this->audit('USER_PASSWORD_RESET',$id,null,['password_changed'=>true]); return $this->ok(null,'Kata sandi berhasil diperbarui.'); } catch(Throwable $e){return $this->err($e);}
    }

    private function clean(array $input,bool $create,?int $except=null):array
    {
        $username=strtolower(trim((string)($input['username']??''))); $name=trim((string)($input['full_name']??'')); $email=strtolower(trim((string)($input['email']??'')))?:null; $role=strtoupper(trim((string)($input['role']??'ADMIN')));
        if(!preg_match('/^[a-z0-9._-]{3,50}$/',$username)||$name===''||($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)))throw new RuntimeException('Username, nama lengkap, atau email tidak valid.'); if(!in_array($role,self::ROLES,true))throw new RuntimeException('Peran pengguna tidak valid.'); if($create&&strlen((string)($input['password']??''))<8)throw new RuntimeException('Kata sandi minimal 8 karakter.');
        $q=$this->db->table('users')->groupStart()->where('username',$username); if($email)$q->orWhere('email',$email); $q->groupEnd(); if($except!==null)$q->where('id !=',$except); if($q->countAllResults())throw new RuntimeException('Username atau email sudah digunakan.'); if($role==='PRODI'&&$this->db->tableExists('user_study_programs')&&empty($input['study_program_ids']))throw new RuntimeException('Pengguna Prodi wajib dikaitkan dengan minimal satu program studi.');
        return ['username'=>$username,'email'=>$email,'full_name'=>$name,'role'=>$role,'is_active'=>(int)($input['is_active']??1)];
    }

    private function syncPrograms(int $userId,string $role,mixed $programIds):void
    {
        if(!$this->db->tableExists('user_study_programs'))return; $this->db->table('user_study_programs')->where('user_id',$userId)->delete(); if($role!=='PRODI')return; $ids=array_values(array_unique(array_filter(array_map('intval',is_array($programIds)?$programIds:[$programIds])))); if($ids===[])throw new RuntimeException('Pengguna Prodi wajib memiliki program studi.'); foreach($ids as $programId){if($this->db->table('study_programs')->where(['id'=>$programId,'is_active'=>1])->countAllResults()!==1)throw new RuntimeException('Program studi yang dipilih tidak aktif atau tidak ditemukan.'); $this->db->table('user_study_programs')->insert(['user_id'=>$userId,'study_program_id'=>$programId,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);}
    }

    private function byId(int $id):?array
    {
        $user=$this->db->table('users')->select('id,username,email,full_name,role,is_active,last_login_at,created_at,updated_at')->where('id',$id)->whereIn('role',self::ROLES)->get()->getRowArray(); if($user&&$this->db->tableExists('user_study_programs'))$user['programs']=$this->db->table('user_study_programs usp')->select('sp.id,sp.code,sp.name')->join('study_programs sp','sp.id=usp.study_program_id')->where('usp.user_id',$id)->orderBy('sp.code')->get()->getResultArray(); return $user?:null;
    }

    private function payload():array{$json=$this->request->getJSON(true);return is_array($json)?$json:$this->request->getPost();}
    private function audit(string $action,int $id,?array $old,?array $new):void{$u=session('auth');$this->db->table('audit_logs')->insert(['user_id'=>is_array($u)?($u['id']??null):null,'action'=>$action,'entity_type'=>'users','entity_id'=>$id,'old_values'=>$old?json_encode($old):null,'new_values'=>$new?json_encode($new):null,'ip_address'=>$this->request->getIPAddress(),'created_at'=>date('Y-m-d H:i:s')]);}
    private function ok(mixed $data=null,?string $message=null,int $status=200):ResponseInterface{return $this->response->setStatusCode($status)->setJSON(['ok'=>true,'message'=>$message,'data'=>$data,'csrf'=>['header'=>config('Security')->headerName,'hash'=>csrf_hash()]]);}
    private function err(Throwable $e):ResponseInterface{return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'message'=>$e->getMessage(),'csrf'=>['header'=>config('Security')->headerName,'hash'=>csrf_hash()]]);}
}
