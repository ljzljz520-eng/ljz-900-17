<?php
declare(strict_types=1);
namespace app\controller;
use app\model\Record;
use app\model\User;
use think\facade\Log;
use think\facade\Request;
use think\Response;
class UserController
{
    private function randomToken(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private function uniqueEmployeeToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = $this->randomToken(16);
            if (!User::where('token', $token)->find()) {
                return $token;
            }
        }
        // 极小概率冲突，兜底加时间
        return $this->randomToken(16) . dechex(time());
    }

    public function index(): Response
    {
        try {
            $list = User::where('role', 'employee')->order('id', 'asc')->select();
            $data = $list->isEmpty() ? [] : $list->toArray();
            if (empty($data)) {
                return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
            }
            // 每名员工的检查数量（records 总数）与整改数量（已完成）
            $userIds = array_column($data, 'id');
            $stats = Record::field('user_id, COUNT(*) AS check_count, SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) AS fix_count')
                ->whereIn('user_id', $userIds)
                ->group('user_id')
                ->select()
                ->toArray();
            $statsMap = [];
            foreach ($stats as $row) {
                $statsMap[(int) $row['user_id']] = [
                    'check_count' => (int) $row['check_count'],
                    'fix_count' => (int) $row['fix_count'],
                ];
            }
            foreach ($data as &$row) {
                $row['check_count'] = $statsMap[(int) $row['id']]['check_count'] ?? 0;
                $row['fix_count'] = $statsMap[(int) $row['id']]['fix_count'] ?? 0;
            }
            unset($row);
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('UserController@index: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
    public function read(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@read: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function create(): Response
    {
        try {
            $name = trim((string) Request::param('name', ''));
            if ($name === '') {
                return api_json(['code' => 400, 'message' => '缺少 name', 'data' => null]);
            }
            $user = User::create([
                'name' => $name,
                'role' => 'employee',
                'token' => $this->uniqueEmployeeToken(),
                'is_active' => 1,
            ]);
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@create: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function update(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            if ($user->role !== 'employee') {
                return api_json(['code' => 400, 'message' => '仅支持编辑员工账号', 'data' => null]);
            }
            $name = trim((string) Request::param('name', ''));
            if ($name !== '') {
                $user->name = $name;
            }
            if (Request::has('is_active')) {
                $user->is_active = (int) Request::param('is_active') ? 1 : 0;
            }
            $user->save();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@update: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function resetToken(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            if ($user->role !== 'employee') {
                return api_json(['code' => 400, 'message' => '仅支持重置员工 token', 'data' => null]);
            }
            $user->token = $this->uniqueEmployeeToken();
            $user->qr_code_url = null;
            $user->save();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@resetToken: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function toggleActive(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            if ($user->role !== 'employee') {
                return api_json(['code' => 400, 'message' => '仅支持禁用/启用员工账号', 'data' => null]);
            }
            $user->is_active = (int) $user->is_active ? 0 : 1;
            $user->save();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@toggleActive: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
