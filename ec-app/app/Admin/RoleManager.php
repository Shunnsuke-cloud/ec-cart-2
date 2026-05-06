<?php

declare(strict_types=1);

/**
 * RoleManager クラス
 * 
 * ロールベースのアクセス制御（RBAC）を管理します。
 * 管理者ユーザーのロール関連付けと権限確認を提供します。
 */
class RoleManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * ユーザーにロールを付与する
     * 
     * @param int $userId ユーザーID
     * @param int $roleId ロールID
     * @return bool 成功時 true
     * @throws RuntimeException ロール付与に失敗した場合
     */
    public function assignRole(int $userId, int $roleId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
            return true;
        } catch (Throwable $e) {
            throw new RuntimeException('ロール付与に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * ユーザーからロールを削除する
     * 
     * @param int $userId ユーザーID
     * @param int $roleId ロールID
     * @return bool 成功時 true
     * @throws RuntimeException ロール削除に失敗した場合
     */
    public function removeRole(int $userId, int $roleId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id'
            );
            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            throw new RuntimeException('ロール削除に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * ユーザーの全ロールを取得する
     * 
     * @param int $userId ユーザーID
     * @return array ロール情報の配列（id, name, description を含む）
     */
    public function getUserRoles(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                <<<'SQL'
SELECT r.id, r.name, r.description
FROM roles r
INNER JOIN user_roles ur ON ur.role_id = r.id
WHERE ur.user_id = :user_id
ORDER BY r.id ASC
SQL
            );
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * ユーザーの全ロール名を取得する
     * 
     * @param int $userId ユーザーID
     * @return array ロール名の配列（['admin', 'manager'] など）
     */
    public function getUserRoleNames(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                <<<'SQL'
SELECT r.name
FROM roles r
INNER JOIN user_roles ur ON ur.role_id = r.id
WHERE ur.user_id = :user_id
ORDER BY r.id ASC
SQL
            );
            $stmt->execute(['user_id' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_column($rows, 'name');
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * ユーザーが指定のロールを持つかチェックする
     * 
     * @param int $userId ユーザーID
     * @param string $roleName ロール名（'admin', 'manager', 'user' など）
     * @return bool ロールを持つ場合 true
     */
    public function hasRole(int $userId, string $roleName): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                <<<'SQL'
SELECT COUNT(*) as cnt
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id
WHERE ur.user_id = :user_id AND r.name = :role_name
LIMIT 1
SQL
            );
            $stmt->execute([
                'user_id' => $userId,
                'role_name' => $roleName,
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ((int)($result['cnt'] ?? 0)) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * ユーザーが複数のロールのいずれかを持つかチェックする
     * 
     * @param int $userId ユーザーID
     * @param array $roleNames ロール名の配列（['admin', 'manager'] など）
     * @return bool いずれかのロールを持つ場合 true
     */
    public function hasAnyRole(int $userId, array $roleNames): bool
    {
        foreach ($roleNames as $roleName) {
            if ($this->hasRole($userId, $roleName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * ロール名からロールIDを取得する
     * 
     * @param string $roleName ロール名
     * @return int|null ロールID、見つからない場合は null
     */
    public function getRoleIdByName(string $roleName): ?int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
            $stmt->execute(['name' => $roleName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 全ロールを取得する
     * 
     * @return array ロール情報の配列
     */
    public function getAllRoles(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, name, description FROM roles ORDER BY id ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * ユーザーに全ロールを設定する（既存のロール関連付けはすべて置き換え）
     * 
     * @param int $userId ユーザーID
     * @param array $roleIds ロールIDの配列
     * @return bool 成功時 true
     * @throws RuntimeException ロール設定に失敗した場合
     */
    public function setUserRoles(int $userId, array $roleIds): bool
    {
        try {
            $this->pdo->beginTransaction();

            // 既存のロール関連付けを削除
            $stmt = $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            // 新しいロール関連付けを追加
            foreach ($roleIds as $roleId) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'role_id' => (int)$roleId,
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('ロール設定に失敗しました: ' . $e->getMessage());
        }
    }
}
